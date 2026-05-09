<?php

namespace App\Services\Folha;

use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Schema;

/**
 * GAP-MF-07 — Persistência granular de rubricas em EVENTO_DETALHE_FOLHA.
 *
 * Responsabilidade:
 *   - Para cada DETALHE_FOLHA persistido, gerar 1 EVENTO_DETALHE_FOLHA por componente
 *     (vencimento, anuênio, cada adicional C2, cada lançamento C3, INSS, IRRF, consignações,
 *     complemento SM).
 *
 * Idempotência:
 *   - Antes de inserir, deleta EVENTO_DETALHE_FOLHA prévios por DETALHE_FOLHA_ID.
 *   - Re-execução produz o mesmo conjunto de registros (sem duplicação).
 *
 * Resolução de EVENTO_ID:
 *   - Cache em memória (1 query por descrição por execução).
 *   - Se EVENTO não existe, NÃO cria automaticamente — apenas loga warning e pula a rubrica.
 *     A criação de eventos é responsabilidade de seeders (EventosBaseSeeder), não do motor.
 */
final class PersistenciaRubricasService
{
    /**
     * Descrições padronizadas dos eventos do motor.
     * Estes eventos DEVEM existir na tabela EVENTO (seedados pelo EventosBaseSeeder).
     */
    public const EVENTO_VENCIMENTO_BASE = 'VENCIMENTO BASE';
    public const EVENTO_ANUENIO = 'ANUENIO';
    public const EVENTO_INSS_RPPS = 'INSS RPPS';
    public const EVENTO_INSS_RGPS = 'INSS RGPS';
    public const EVENTO_IRRF = 'IRRF';
    public const EVENTO_CONSIGNACOES = 'CONSIGNACOES';
    public const EVENTO_COMPLEMENTO_SM = 'COMPLEMENTO SALARIO MINIMO';

    /** @var array<string, ?int> */
    private array $cacheEventoIdPorDescricao = [];

    private ?string $colunaNomeEventoCache = null;
    private bool $colunaNomeEventoDetectada = false;

    /**
     * Persiste rubricas detalhadas para um lote de DETALHE_FOLHA já persistidos.
     *
     * @param  array<int, array<string, mixed>>  $rubricasPorDetalheFolha
     *         [DETALHE_FOLHA_ID => [['descricao' => string, 'valor' => float], ...]]
     */
    public function persistirRubricasLote(array $rubricasPorDetalheFolha): int
    {
        if (! Schema::hasTable('EVENTO_DETALHE_FOLHA')) {
            Log::warning('[PersistenciaRubricas] Tabela EVENTO_DETALHE_FOLHA não existe — operação ignorada.');
            return 0;
        }

        $detalheFolhaIds = array_keys($rubricasPorDetalheFolha);
        if ($detalheFolhaIds === []) {
            return 0;
        }

        return DB::transaction(function () use ($rubricasPorDetalheFolha, $detalheFolhaIds) {
            // Idempotência: limpar EVENTO_DETALHE_FOLHA prévio dos DETALHE_FOLHA do lote
            DB::table('EVENTO_DETALHE_FOLHA')
                ->whereIn('DETALHE_FOLHA_ID', $detalheFolhaIds)
                ->delete();

            $rows = [];
            $now = now();
            $eventosFaltantes = [];

            foreach ($rubricasPorDetalheFolha as $dfId => $rubricas) {
                foreach ($rubricas as $r) {
                    $descricao = (string) ($r['descricao'] ?? '');
                    $valor = (float) ($r['valor'] ?? 0);
                    if ($descricao === '' || $valor == 0.0) {
                        continue;
                    }

                    // GAP-MF-07: aceitar formato '__POR_EVENTO_ID__:N' (vindo de RUBRICA com EVENTO_ID)
                    if (str_starts_with($descricao, '__POR_EVENTO_ID__:')) {
                        $eventoId = (int) substr($descricao, strlen('__POR_EVENTO_ID__:'));
                        if ($eventoId > 0) {
                            $rows[] = [
                                'EVENTO_ID' => $eventoId,
                                'DETALHE_FOLHA_ID' => (int) $dfId,
                                'EVENTO_DETALHE_FOLHA_VALOR' => round($valor, 2),
                            ];
                            continue;
                        }
                    }

                    $eventoId = $this->resolverEventoIdPorDescricao($descricao);
                    if ($eventoId === null) {
                        $eventosFaltantes[$descricao] = ($eventosFaltantes[$descricao] ?? 0) + 1;
                        continue;
                    }

                    $rows[] = [
                        'EVENTO_ID' => $eventoId,
                        'DETALHE_FOLHA_ID' => (int) $dfId,
                        'EVENTO_DETALHE_FOLHA_VALOR' => round($valor, 2),
                    ];
                }
            }

            if ($rows !== []) {
                // Insert em chunks para não estourar limites do driver
                foreach (array_chunk($rows, 500) as $chunk) {
                    DB::table('EVENTO_DETALHE_FOLHA')->insert($chunk);
                }
            }

            if ($eventosFaltantes !== []) {
                Log::warning('[PersistenciaRubricas] Eventos não encontrados na tabela EVENTO — rubricas ignoradas.', [
                    'eventos_faltantes' => $eventosFaltantes,
                    'sugestao' => 'Rodar seeder EventosBaseSeeder para criar.',
                ]);
            }

            Log::info('[PersistenciaRubricas] Rubricas persistidas', [
                'detalhe_folha_ids' => count($detalheFolhaIds),
                'rubricas_inseridas' => count($rows),
                'eventos_faltantes' => count($eventosFaltantes),
            ]);

            return count($rows);
        });
    }

    /**
     * Resolve o EVENTO_ID por descrição (com cache memoizado).
     *
     * Schema-defensive: detecta se a coluna principal de nome do evento é
     * EVENTO_NOME (PMSL e schemas modernos) ou EVENTO_DESCRICAO (legado).
     */
    private function resolverEventoIdPorDescricao(string $descricao): ?int
    {
        if (array_key_exists($descricao, $this->cacheEventoIdPorDescricao)) {
            return $this->cacheEventoIdPorDescricao[$descricao];
        }

        $colNome = $this->detectarColunaNomeEvento();
        if ($colNome === null) {
            $this->cacheEventoIdPorDescricao[$descricao] = null;
            return null;
        }

        $id = DB::table('EVENTO')
            ->where($colNome, $descricao)
            ->where('EVENTO_ATIVO', 1)
            ->value('EVENTO_ID');

        $this->cacheEventoIdPorDescricao[$descricao] = $id ? (int) $id : null;

        return $this->cacheEventoIdPorDescricao[$descricao];
    }

    /**
     * Detecta uma única vez (memoizado) qual a coluna de nome do EVENTO.
     * PMSL: EVENTO_NOME. Legado: EVENTO_DESCRICAO. Erro: null.
     */
    private function detectarColunaNomeEvento(): ?string
    {
        if ($this->colunaNomeEventoDetectada) {
            return $this->colunaNomeEventoCache;
        }

        $this->colunaNomeEventoDetectada = true;

        if (Schema::hasColumn('EVENTO', 'EVENTO_NOME')) {
            $this->colunaNomeEventoCache = 'EVENTO_NOME';
        } elseif (Schema::hasColumn('EVENTO', 'EVENTO_DESCRICAO')) {
            $this->colunaNomeEventoCache = 'EVENTO_DESCRICAO';
        } else {
            $this->colunaNomeEventoCache = null;
            Log::warning('[PersistenciaRubricas] Tabela EVENTO sem coluna de nome (EVENTO_NOME ou EVENTO_DESCRICAO).');
        }

        return $this->colunaNomeEventoCache;
    }

    /**
     * Resolve o EVENTO_ID de uma rubrica específica (LANCAMENTO_FOLHA / ADICIONAL_SERVIDOR).
     * Diferente de `resolverEventoIdPorDescricao`, este busca via tabela RUBRICA → EVENTO.
     *
     * Estratégia de fallback:
     *   1. Tentar resolver pela RUBRICA_ID (se RUBRICA tiver EVENTO_ID associado)
     *   2. Senão, resolver pela RUBRICA_DESCRICAO usando descrição como chave
     *   3. Se nada bater, retornar null (warning logado)
     */
    public function resolverEventoIdPorRubrica(int $rubricaId, ?string $rubricaDescricao = null): ?int
    {
        // Se RUBRICA tem coluna EVENTO_ID, usar
        if (Schema::hasColumn('RUBRICA', 'EVENTO_ID')) {
            $eventoId = DB::table('RUBRICA')
                ->where('RUBRICA_ID', $rubricaId)
                ->value('EVENTO_ID');
            if ($eventoId) {
                return (int) $eventoId;
            }
        }

        // Fallback: usar descrição da rubrica
        $desc = $rubricaDescricao
            ?? DB::table('RUBRICA')->where('RUBRICA_ID', $rubricaId)->value('RUBRICA_DESCRICAO');

        if ($desc) {
            return $this->resolverEventoIdPorDescricao((string) $desc);
        }

        return null;
    }
}
