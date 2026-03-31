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
use Carbon\Carbon;
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

        $this->calcularRubricas($detalheFolha, $detalheEscala->FUNCIONARIO_ID, $diasTrabalhados, $faltas, $folha->FOLHA_COMPETENCIA);
    }

    // =========================================================================
    // CÁLCULO PRINCIPAL — busca vínculo e delega às regras específicas
    // =========================================================================

    private function calcularRubricas(
        DetalheFolha $detalheFolha,
        int $funcionarioId,
        int $diasTrabalhados,
        int $faltas,
        string $competencia = ''
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

        // BUG-S2-07: abono por afastamentos remunerados (licença médica, maternidade, etc.)
        $faltasEfetivas = $faltas;
        if ($competencia && $faltas > 0) {
            $compFormatada = strlen($competencia) === 6
                ? substr($competencia, 0, 4) . '-' . substr($competencia, 4, 2)
                : $competencia;
            $diasAbonados = (int) DB::table('AFASTAMENTO')
                ->where('FUNCIONARIO_ID', $funcionarioId)
                ->whereIn('AFASTAMENTO_TIPO', [
                    'LICENCA_MEDICA',
                    'LICENCA_SAUDE',
                    'LICENCA_MATERNIDADE',
                    'LICENCA_PATERNIDADE',
                    'LICENCA_NOJO',
                    'LICENCA_GALA',
                    'AFASTAMENTO_JUDICIAL',
                    'AFASTAMENTO_REMUNERADO',
                ])
                ->where(function ($q) use ($compFormatada) {
                    $q->whereRaw("strftime('%Y-%m', AFASTAMENTO_DATA_INICIO) = ?", [$compFormatada])
                        ->orWhereRaw("strftime('%Y-%m', AFASTAMENTO_DATA_FIM) = ?", [$compFormatada]);
                })
                ->selectRaw("SUM(CASE WHEN AFASTAMENTO_DATA_FIM IS NULL THEN julianday('now') - julianday(AFASTAMENTO_DATA_INICIO) ELSE julianday(AFASTAMENTO_DATA_FIM) - julianday(AFASTAMENTO_DATA_INICIO) + 1 END) as total_dias")
                ->value('total_dias') ?? 0;
            $faltasEfetivas = max(0, $faltas - $diasAbonados);
        }

        // BUG-S2-05/06: dias reais do mês da competência (fevereiro=28/29, não 30)
        $diasDoMes = 30;
        if ($competencia) {
            $ano = (int) substr($competencia, 0, 4);
            $mes = (int) substr($competencia, strlen($competencia) === 6 ? 4 : 5, 2);
            if ($ano > 0 && $mes >= 1 && $mes <= 12) {
                $diasDoMes = cal_days_in_month(CAL_GREGORIAN, $mes, $ano);
            }
        }

        // Carregar config do funcionário
        $pontoConfig = \Illuminate\Support\Facades\DB::table('PONTO_CONFIG_FUNCIONARIO')
            ->where('FUNCIONARIO_ID', $funcionario->FUNCIONARIO_ID)
            ->first();

        // Se há jornada financeira configurada (acordo informal), usar ela para fins de folha
        // O ponto físico NÃO é alterado — só o cálculo de remuneração
        if ($pontoConfig && $pontoConfig->JORNADA_FINANCEIRA_HORAS) {
            // Substituir a carga horária do turno pela jornada acordada
            $horasJornadaDia = (float) $pontoConfig->JORNADA_FINANCEIRA_HORAS;
            // Log para auditoria
            \Illuminate\Support\Facades\Log::info("Jornada financeira aplicada", [
                'funcionario_id' => $funcionario->FUNCIONARIO_ID,
                'jornada_financeira' => $horasJornadaDia,
                'obs' => $pontoConfig->JORNADA_FINANCEIRA_OBS,
            ]);
        }

        // TASK-A0: ajuste proporcional por admissão ou exoneração no mês de competência
        if ($competencia) {
            $anoComp = (int) substr($competencia, 0, 4);
            $mesComp = (int) (strlen($competencia) === 6 ? substr($competencia, 4, 2) : substr($competencia, 5, 2));
            $inicioMes = Carbon::create($anoComp, $mesComp, 1)->startOfDay();
            $fimMes    = $inicioMes->copy()->endOfMonth()->startOfDay();

            $dataAdmissao   = $funcionario->FUNCIONARIO_DATA_INICIO
                ? Carbon::parse($funcionario->FUNCIONARIO_DATA_INICIO)->startOfDay()
                : null;
            $dataExoneracao = $funcionario->FUNCIONARIO_DATA_FIM
                ? Carbon::parse($funcionario->FUNCIONARIO_DATA_FIM)->startOfDay()
                : null;

            // Admitido neste mês → contar a partir da admissão até o fim do mês
            if ($dataAdmissao && $dataAdmissao->format('Ym') === (strlen($competencia) === 6 ? $competencia : str_replace('-', '', $competencia))) {
                $diasTrabalhados = (int) $dataAdmissao->diffInDays($fimMes) + 1;
                Log::info("[FolhaParser] TASK-A0 — Func {$funcionarioId} admitido em {$dataAdmissao->toDateString()} — proporcional: {$diasTrabalhados} dias.");
            }

            // Exonerado neste mês → contar do início do mês até a exoneração
            if ($dataExoneracao && $dataExoneracao->format('Ym') === (strlen($competencia) === 6 ? $competencia : str_replace('-', '', $competencia))) {
                $diasTrabalhados = (int) $inicioMes->diffInDays($dataExoneracao) + 1;
                Log::info("[FolhaParser] TASK-A0 — Func {$funcionarioId} exonerado em {$dataExoneracao->toDateString()} — proporcional: {$diasTrabalhados} dias.");
            }
        }

        Log::info("[FolhaParser] Func {$funcionarioId} | Vínculo: {$tipoVinculo} | Salário: {$salarioBase} | Dias: {$diasTrabalhados} | Faltas: {$faltasEfetivas} | DiasMes: {$diasDoMes}");

        // 3. Delega para o cálculo específico do vínculo
        $resultado = match ($tipoVinculo) {
            VinculoEnum::SERVIDOR_EFETIVO => $this->calcularServidorEstatutario($salarioBase, $diasTrabalhados, $faltasEfetivas, $diasDoMes),
            VinculoEnum::CARGO_COMISSAO => $this->calcularCargoComissao($salarioBase, $diasTrabalhados, $faltasEfetivas, $diasDoMes),
            default => $this->calcularGenerico($salarioBase, $diasTrabalhados, $faltasEfetivas, $diasDoMes),
        };

        // BUG-HE-02: incluir horas extras e plantões aprovados na competência
        if ($competencia) {
            $resultado = $this->incluirHorasExtras($resultado, $funcionarioId, $competencia, $tipoVinculo);
        }

        // 4. Persiste os eventos (rubricas) individualmente
        $this->persistirRubricas($detalheFolha, $resultado['rubricas']);

        // 5. Atualiza totais do cabeçalho
        $detalheFolha->DETALHE_FOLHA_PROVENTOS = $resultado['total_proventos'];
        $detalheFolha->DETALHE_FOLHA_DESCONTOS = $resultado['total_descontos'];
        $detalheFolha->DETALHE_FOLHA_LIQUIDO = round($resultado['total_proventos'] - $resultado['total_descontos'], 2);
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
    private function calcularServidorEstatutario(float $salario, int $diasTrabalhados, int $faltas, int $diasMes = 30): array
    {
        $vencimentoBruto = round($salario / $diasMes * ($diasTrabalhados + $faltas), 2); // base sobre mês cheio (BUG-S2-06 corrigido)
        $descontoFalta = round($salario / $diasMes * $faltas, 2);
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
    private function calcularCargoComissao(float $salario, int $diasTrabalhados, int $faltas, int $diasMes = 30): array
    {
        $remuneracaoBruta = round($salario / $diasMes * ($diasTrabalhados + $faltas), 2); // BUG-S2-06 corrigido
        $descontoFalta = round($salario / $diasMes * $faltas, 2);
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
    private function calcularGenerico(float $salario, int $diasTrabalhados, int $faltas, int $diasMes = 30): array
    {
        $vencimento = round($salario / $diasMes * $diasTrabalhados, 2); // BUG-S2-06 corrigido
        $descontoFalta = round($salario / $diasMes * $faltas, 2);

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

    // =========================================================================
    // BUG-HE-02 — HORAS EXTRAS E PLANTÕES NA FOLHA
    // =========================================================================

    /**
     * Busca horas extras e plantões aprovados para o funcionário na competência
     * e os inclui como proventos no resultado do cálculo.
     *
     * Recalcula o IRRF sobre a base acumulada (vencimento + horas extras).
     * Marca os registros como INCLUIDA_FOLHA no banco.
     *
     * @param array  $resultado   resultado atual do calcularServidorEstatutario/calcularCargoComissao
     * @param int    $funcionarioId
     * @param string $competencia  AAAAMM ou AAAA-MM
     * @param string $tipoVinculo  VinculoEnum::*
     * @return array resultado atualizado com HE incluídas
     */
    private function incluirHorasExtras(array $resultado, int $funcionarioId, string $competencia, string $tipoVinculo): array
    {
        // Normalizar competência para AAAA-MM (formato das colunas COMPETENCIA)
        $compFormatada = strlen($competencia) === 6
            ? substr($competencia, 0, 4) . '-' . substr($competencia, 4, 2)
            : $competencia;

        $statusBusca = ['APROVADA', 'INCLUIDA_FOLHA'];

        // ── Horas Extras ───────────────────────────────────────────────────────
        $horasExtras = DB::table('HORA_EXTRA')
            ->where('FUNCIONARIO_ID', $funcionarioId)
            ->where('COMPETENCIA', $compFormatada)
            ->whereIn('STATUS', $statusBusca)
            ->get(['HORA_EXTRA_ID', 'TIPO_HORA_EXTRA', 'TOTAL_HORAS', 'PERCENTUAL', 'VALOR_CALCULADO']);

        $idsHE = [];
        foreach ($horasExtras as $he) {
            $valor = (float) ($he->VALOR_CALCULADO ?? 0);
            if ($valor <= 0)
                continue;

            $pct = (int) ($he->PERCENTUAL ?? 50);
            $descricao = match (true) {
                str_contains((string) $he->TIPO_HORA_EXTRA, '100') => "HORA EXTRA 100%",
                str_contains((string) $he->TIPO_HORA_EXTRA, 'FERIADO') => "HORA EXTRA FERIADO",
                default => "HORA EXTRA {$pct}%",
            };

            $resultado['rubricas'][] = [
                'descricao' => $descricao,
                'tipo' => 'P',
                'valor' => $valor,
            ];
            $resultado['total_proventos'] += $valor;
            $idsHE[] = $he->HORA_EXTRA_ID;
        }

        // ── Plantões Extras ────────────────────────────────────────────────────
        $plantoes = DB::table('PLANTAO_EXTRA')
            ->where('FUNCIONARIO_ID', $funcionarioId)
            ->where('COMPETENCIA', $compFormatada)
            ->whereIn('STATUS', $statusBusca)
            ->get(['PLANTAO_EXTRA_ID', 'VALOR_CALCULADO', 'ADICIONAL_NOTURNO', 'VALOR_ADICIONAL_NOTURNO']);

        $idsPE = [];
        foreach ($plantoes as $pe) {
            $valor = (float) ($pe->VALOR_CALCULADO ?? 0);
            if ($valor <= 0)
                continue;

            $resultado['rubricas'][] = [
                'descricao' => 'PLANTÃO EXTRA',
                'tipo' => 'P',
                'valor' => $valor,
            ];
            $resultado['total_proventos'] += $valor;
            $idsPE[] = $pe->PLANTAO_EXTRA_ID;
        }

        // ── Recalcular IRRF sobre base acumulada (vencimento + HE + plantão) ──
        if (!empty($idsHE) || !empty($idsPE)) {
            // Remove IRRF anterior
            $resultado['rubricas'] = array_filter(
                $resultado['rubricas'],
                fn($r) => $r['descricao'] !== 'IRRF'
            );

            // Base IRRF = proventos totais − INSS
            $inssTotal = array_sum(
                array_map(
                    fn($r) => in_array($r['descricao'], ['INSS RPPS (14%)', 'INSS RGPS']) ? $r['valor'] : 0,
                    $resultado['rubricas']
                )
            );
            $baseIrrf = max(0, $resultado['total_proventos'] - $inssTotal);
            $novoIrrf = $this->impostos->calcularIrrf($baseIrrf);

            if ($novoIrrf > 0) {
                $resultado['rubricas'][] = [
                    'descricao' => 'IRRF',
                    'tipo' => 'D',
                    'valor' => $novoIrrf,
                ];
            }

            // Recalcular totais corretamente
            $resultado = $this->totalizarRubricas(array_values($resultado['rubricas']));

            // Marcar como INCLUIDA_FOLHA
            if (!empty($idsHE)) {
                DB::table('HORA_EXTRA')
                    ->whereIn('HORA_EXTRA_ID', $idsHE)
                    ->update(['STATUS' => 'INCLUIDA_FOLHA', 'updated_at' => now()]);
            }
            if (!empty($idsPE)) {
                DB::table('PLANTAO_EXTRA')
                    ->whereIn('PLANTAO_EXTRA_ID', $idsPE)
                    ->update(['STATUS' => 'INCLUIDA_FOLHA', 'updated_at' => now()]);
            }

            $qtd = count($idsHE) + count($idsPE);
            Log::info("[FolhaParser] HE-02 — {$qtd} evento(s) de hora extra/plantão incluídos na folha do func {$funcionarioId}.");
        }

        return $resultado;
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
