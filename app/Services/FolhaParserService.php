<?php

namespace App\Services;

use App\Models\AtribuicaoLotacao;
use App\Models\DetalheEscala;
use App\Models\DetalheFolha;
use App\Models\Escala;
use App\Models\Evento;
use App\Models\EventoDetalheFolha;
use App\Models\Folha;
use App\Models\Funcionario;
use App\MyLibs\VinculoEnum;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;

/**
 * Motor de cálculo da Folha de Pagamento — GENTE 2.0
 *
 * Suporta:
 *  - Servidor Público Estatutário (RPPS) — INSS 14%, descontos proporcionais por falta
 *  - Cargo em Comissão (RGPS)           — INSS progressivo, FGTS 8%, IRRF progressivo
 *
 * Substitui gradualmente a sp_gera_folha do sistema legado.
 */
class FolhaParserService
{
    private TabelasImpostoService $impostos;

    public function __construct()
    {
        $this->impostos = new TabelasImpostoService();
    }

    // =========================================================================
    // ENTRADA PRINCIPAL
    // =========================================================================

    public function processar(Folha $folha): bool
    {
        Log::info("[FolhaParser] Iniciando folha ID {$folha->FOLHA_ID} — competência {$folha->FOLHA_COMPETENCIA}");

        DB::beginTransaction();
        try {
            $this->limparFolhaAnterior($folha->FOLHA_ID);

            $escalas = Escala::where('ESCALA_COMPETENCIA', $folha->FOLHA_COMPETENCIA)
                ->where('TIPO_ESCALA_ID', $folha->FOLHA_TIPO)
                ->get();

            foreach ($escalas as $escala) {
                $detalhes = DetalheEscala::with('detalheEscalaItens')
                    ->where('ESCALA_ID', $escala->ESCALA_ID)
                    ->get();

                foreach ($detalhes as $detalhe) {
                    $this->apurarFuncionario($folha, $detalhe);
                }
            }

            DB::commit();
            Log::info("[FolhaParser] Concluído com sucesso.");
            return true;

        } catch (\Throwable $e) {
            DB::rollBack();
            Log::error("[FolhaParser] Erro fatal: " . $e->getMessage());
            throw $e;
        }
    }

    // =========================================================================
    // APURAÇÃO DE FREQUÊNCIA
    // =========================================================================

    private function apurarFuncionario(Folha $folha, DetalheEscala $detalheEscala): void
    {
        $diasTrabalhados = 0;
        $faltas = 0;
        $atrasosMinutos = 0;

        foreach ($detalheEscala->detalheEscalaItens as $item) {
            if ($item->DETALHE_ESCALA_ITEM_FALTA) {
                $faltas++;
            } elseif ($item->TURNO_ID !== 4) { // 4 = Folga
                $diasTrabalhados++;
            }
            $atrasosMinutos += $item->DETALHE_ESCALA_ITEM_ATRASO ?? 0;
        }

        // Cabeçalho do funcionário na folha (valores preenchidos ao final)
        $detalheFolha = DetalheFolha::create([
            'FOLHA_ID' => $folha->FOLHA_ID,
            'FUNCIONARIO_ID' => $detalheEscala->FUNCIONARIO_ID,
            'DETALHE_FOLHA_PROVENTOS' => 0.0,
            'DETALHE_FOLHA_DESCONTOS' => 0.0,
        ]);

        $this->calcularRubricas($detalheFolha, $detalheEscala->FUNCIONARIO_ID, $diasTrabalhados, $faltas);
    }

    // =========================================================================
    // CÁLCULO PRINCIPAL — busca vínculo e delega às regras específicas
    // =========================================================================

    private function calcularRubricas(
        DetalheFolha $detalheFolha,
        int $funcionarioId,
        int $diasTrabalhados,
        int $faltas
    ): void {
        // 1. Carrega o funcionário com sua lotação ativa e vínculo
        $funcionario = Funcionario::with([
            'lotacoes' => fn($q) => $q->whereNull('LOTACAO_DATA_FIM')
                ->with(['vinculo', 'atribuicaoLotacoes.atribuicao']),
        ])->find($funcionarioId);

        if (!$funcionario) {
            Log::warning("[FolhaParser] Funcionário {$funcionarioId} não encontrado.");
            return;
        }

        $lotacaoAtiva = $funcionario->lotacoes
            ->sortByDesc('LOTACAO_ID')
            ->first();

        // 2. Busca o salário base real da AtribuicaoLotacao vigente
        $salarioBase = $this->lerSalarioBase($lotacaoAtiva);
        $vinculo = optional($lotacaoAtiva)->vinculo;
        $tipoVinculo = VinculoEnum::resolveVinculo(
            $vinculo?->VINCULO_SIGLA,
            $vinculo?->VINCULO_DESCRICAO
        );

        Log::info("[FolhaParser] Func {$funcionarioId} | Vínculo: {$tipoVinculo} | Salário: {$salarioBase} | Dias: {$diasTrabalhados} | Faltas: {$faltas}");

        // 3. Delega para o cálculo específico do vínculo
        $resultado = match ($tipoVinculo) {
            VinculoEnum::SERVIDOR_EFETIVO => $this->calcularServidorEstatutario($salarioBase, $diasTrabalhados, $faltas),
            VinculoEnum::CARGO_COMISSAO => $this->calcularCargoComissao($salarioBase, $diasTrabalhados, $faltas),
            default => $this->calcularGenerico($salarioBase, $diasTrabalhados, $faltas),
        };

        // 4. Persiste os eventos (rubricas) individualmente
        $this->persistirRubricas($detalheFolha, $resultado['rubricas']);

        // 5. Atualiza totais do cabeçalho
        $detalheFolha->DETALHE_FOLHA_PROVENTOS = $resultado['total_proventos'];
        $detalheFolha->DETALHE_FOLHA_DESCONTOS = $resultado['total_descontos'];
        $detalheFolha->save();
    }

    // =========================================================================
    // REGRAS DE CÁLCULO POR TIPO DE VÍNCULO
    // =========================================================================

    /**
     * Servidor Público Estatutário — Regime Próprio (RPPS).
     *
     * Proventos:
     *  - Vencimento Básico (proporcional a dias trabalhados)
     *
     * Descontos:
     *  - INSS RPPS: 14% sobre vencimento bruto
     *  - Falta: desconto proporcional (vencimento / 30 × faltas)
     *  - IRRF: sobre base (vencimento − INSS − deduções)
     */
    private function calcularServidorEstatutario(float $salario, int $diasTrabalhados, int $faltas): array
    {
        $vencimentoBruto = round($salario / 30 * ($diasTrabalhados + $faltas), 2); // base sobre mês cheio
        $descontoFalta = round($salario / 30 * $faltas, 2);
        $inss = $this->impostos->calcularInssRpps($vencimentoBruto);
        $baseIrrf = max(0, $vencimentoBruto - $inss - $descontoFalta);
        $irrf = $this->impostos->calcularIrrf($baseIrrf);

        $rubricas = [];

        // Provento: Vencimento
        if ($vencimentoBruto > 0) {
            $rubricas[] = [
                'descricao' => 'VENCIMENTO BÁSICO',
                'tipo' => 'P', // P=Provento
                'valor' => $vencimentoBruto,
            ];
        }

        // Desconto: Falta
        if ($descontoFalta > 0) {
            $rubricas[] = [
                'descricao' => 'DESCONTO DE FALTA',
                'tipo' => 'D',
                'valor' => $descontoFalta,
            ];
        }

        // Desconto: INSS RPPS
        if ($inss > 0) {
            $rubricas[] = [
                'descricao' => 'INSS RPPS (14%)',
                'tipo' => 'D',
                'valor' => $inss,
            ];
        }

        // Desconto: IRRF
        if ($irrf > 0) {
            $rubricas[] = [
                'descricao' => 'IRRF',
                'tipo' => 'D',
                'valor' => $irrf,
            ];
        }

        return $this->totalizarRubricas($rubricas);
    }

    /**
     * Cargo em Comissão — Regime Geral (RGPS).
     *
     * Proventos:
     *  - Remuneração do Cargo (proporcional a dias trabalhados)
     *
     * Descontos:
     *  - INSS RGPS: alíquotas progressivas 2024
     *  - FGTS: 8% (registro interno, não descontado do líquido — apenas informativo)
     *  - Falta: desconto proporcional
     *  - IRRF: sobre base (remuneração − INSS − falta)
     */
    private function calcularCargoComissao(float $salario, int $diasTrabalhados, int $faltas): array
    {
        $remuneracaoBruta = round($salario / 30 * ($diasTrabalhados + $faltas), 2);
        $descontoFalta = round($salario / 30 * $faltas, 2);
        $inss = $this->impostos->calcularInssRgps($remuneracaoBruta);
        $fgts = round($remuneracaoBruta * 0.08, 2); // 8% — fica no FGTS, não desconta salário
        $baseIrrf = max(0, $remuneracaoBruta - $inss - $descontoFalta);
        $irrf = $this->impostos->calcularIrrf($baseIrrf);

        $rubricas = [];

        if ($remuneracaoBruta > 0) {
            $rubricas[] = [
                'descricao' => 'REMUNERAÇÃO DE CARGO EM COMISSÃO',
                'tipo' => 'P',
                'valor' => $remuneracaoBruta,
            ];
        }

        if ($descontoFalta > 0) {
            $rubricas[] = [
                'descricao' => 'DESCONTO DE FALTA',
                'tipo' => 'D',
                'valor' => $descontoFalta,
            ];
        }

        if ($inss > 0) {
            $rubricas[] = [
                'descricao' => 'INSS RGPS',
                'tipo' => 'D',
                'valor' => $inss,
            ];
        }

        // FGTS: registrado como informativo (não desconta do líquido)
        if ($fgts > 0) {
            $rubricas[] = [
                'descricao' => 'FGTS (8%) - INFORMATIVO',
                'tipo' => 'I', // I=Informativo
                'valor' => $fgts,
            ];
        }

        if ($irrf > 0) {
            $rubricas[] = [
                'descricao' => 'IRRF',
                'tipo' => 'D',
                'valor' => $irrf,
            ];
        }

        return $this->totalizarRubricas($rubricas);
    }

    /**
     * Cálculo genérico para vínculos não mapeados.
     * Apenas vencimento proporcional sem descontos previdenciários.
     */
    private function calcularGenerico(float $salario, int $diasTrabalhados, int $faltas): array
    {
        $vencimento = round($salario / 30 * $diasTrabalhados, 2);
        $descontoFalta = round($salario / 30 * $faltas, 2);

        $rubricas = [];

        if ($vencimento > 0) {
            $rubricas[] = ['descricao' => 'VENCIMENTO', 'tipo' => 'P', 'valor' => $vencimento];
        }
        if ($descontoFalta > 0) {
            $rubricas[] = ['descricao' => 'DESCONTO DE FALTA', 'tipo' => 'D', 'valor' => $descontoFalta];
        }

        return $this->totalizarRubricas($rubricas);
    }

    // =========================================================================
    // AUXILIARES
    // =========================================================================

    /**
     * Lê o salário base da AtribuicaoLotacao vigente.
     * Fallback: HistAtribuicaoConfig → HIST_ATRIBUICAO_CONFIG_VALOR mais recente.
     */
    private function lerSalarioBase($lotacaoAtiva): float
    {
        if (!$lotacaoAtiva)
            return 0.0;

        // Prioridade 1: valor direto na AtribuicaoLotacao
        $atribuicaoLotacao = $lotacaoAtiva->atribuicaoLotacoes
            ->whereNull('ATRIBUICAO_LOTACAO_FIM')
            ->sortByDesc('ATRIBUICAO_LOTACAO_ID')
            ->first();

        if ($atribuicaoLotacao && $atribuicaoLotacao->ATRIBUICAO_LOTACAO_VALOR > 0) {
            return (float) $atribuicaoLotacao->ATRIBUICAO_LOTACAO_VALOR;
        }

        // Prioridade 2: HistAtribuicaoConfig do cargo nessa configuração
        if ($atribuicaoLotacao) {
            $histConfig = \App\Models\HistAtribuicaoConfig::whereHas(
                'atribuicaoConfig',
                fn($q) => $q->where('ATRIBUICAO_ID', $atribuicaoLotacao->ATRIBUICAO_ID)
            )
                ->whereNull('HIST_ATRIBUICAO_CONFIG_FIM')
                ->orderByDesc('HIST_ATRIBUICAO_CONFIG_ID')
                ->first();

            if ($histConfig) {
                return (float) $histConfig->HIST_ATRIBUICAO_CONFIG_VALOR;
            }
        }

        Log::warning("[FolhaParser] Salário base não encontrado para lotação {$lotacaoAtiva->LOTACAO_ID}. Usando R$ 0.");
        return 0.0;
    }

    /**
     * Soma proventos e descontos das rubricas.
     */
    private function totalizarRubricas(array $rubricas): array
    {
        $proventos = 0.0;
        $descontos = 0.0;

        foreach ($rubricas as $r) {
            if ($r['tipo'] === 'P')
                $proventos += $r['valor'];
            if ($r['tipo'] === 'D')
                $descontos += $r['valor'];
        }

        return [
            'rubricas' => $rubricas,
            'total_proventos' => round($proventos, 2),
            'total_descontos' => round($descontos, 2),
        ];
    }

    /**
     * Persiste cada rubrica como EventoDetalheFolha,
     * buscando ou criando o EVENTO correspondente pela descrição.
     */
    private function persistirRubricas(DetalheFolha $detalheFolha, array $rubricas): void
    {
        foreach ($rubricas as $rubrica) {
            // Busca evento pelo nome (case-insensitive) ou cria um novo
            $evento = Evento::where('EVENTO_DESCRICAO', 'like', $rubrica['descricao'])
                ->first();

            if (!$evento) {
                $evento = Evento::create([
                    'EVENTO_DESCRICAO' => $rubrica['descricao'],
                    'EVENTO_SALARIO' => $rubrica['tipo'] === 'P' ? 1 : 0,
                    'EVENTO_IMPOSTO' => in_array($rubrica['descricao'], ['IRRF', 'INSS RPPS (14%)', 'INSS RGPS']) ? 1 : 0,
                    'EVENTO_SISTEMA' => 1,
                    'EVENTO_ATIVO' => 1,
                ]);
            }

            EventoDetalheFolha::create([
                'EVENTO_ID' => $evento->EVENTO_ID,
                'DETALHE_FOLHA_ID' => $detalheFolha->DETALHE_FOLHA_ID,
                'EVENTO_DETALHE_FOLHA_VALOR' => $rubrica['valor'],
            ]);
        }
    }

    private function limparFolhaAnterior(int $folhaId): void
    {
        $detalhes = DetalheFolha::where('FOLHA_ID', $folhaId)->get();
        foreach ($detalhes as $det) {
            EventoDetalheFolha::where('DETALHE_FOLHA_ID', $det->DETALHE_FOLHA_ID)->delete();
            $det->delete();
        }
    }
}
