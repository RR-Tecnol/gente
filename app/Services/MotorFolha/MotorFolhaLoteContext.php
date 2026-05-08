<?php

namespace App\Services\MotorFolha;

use Carbon\Carbon;
use Illuminate\Support\Collection;

/**
 * Dados pré-carregados por lote (AFASTAMENTO, AVALIACAO_DESEMPENHO, CARGO_SALARIO)
 * injectados no MotorFolhaService — sem queries lazy dentro do loop por FUNCIONARIO_ID.
 */
final class MotorFolhaLoteContext
{
    /** Competência da folha no formato AAAA-MM */
    private string $competenciaYm;

    private Carbon $competenciaInicio;

    private Carbon $competenciaFim;

    /** @var array<int, float> */
    private array $cargoSalarioPorFuncionario = [];

    /** @var array<int, Collection> */
    private array $afastamentosPorFuncionario = [];

    /** @var array<int, Collection> */
    private array $avaliacoesPorFuncionario = [];

    /** @var array<int, array{inicio: ?string, fim: ?string}> Datas de admissão/exoneração por funcionário (GAP-MF-03) */
    private array $datasContratuaisPorFuncionario = [];

    /**
     * @param  array<int, float>  $cargoSalarioPorFuncionario
     * @param  array<int, Collection>  $afastamentosPorFuncionario
     * @param  array<int, Collection>  $avaliacoesPorFuncionario
     * @param  array<int, array{inicio: ?string, fim: ?string}>  $datasContratuaisPorFuncionario
     */
    public function __construct(
        string $competenciaYm,
        array $cargoSalarioPorFuncionario,
        array $afastamentosPorFuncionario,
        array $avaliacoesPorFuncionario,
        array $datasContratuaisPorFuncionario = []
    ) {
        $this->competenciaYm = substr($competenciaYm, 0, 7);
        $this->competenciaInicio = Carbon::parse($this->competenciaYm . '-01')->startOfDay();
        $this->competenciaFim = $this->competenciaInicio->copy()->endOfMonth()->endOfDay();
        $this->cargoSalarioPorFuncionario = $cargoSalarioPorFuncionario;
        $this->afastamentosPorFuncionario = $afastamentosPorFuncionario;
        $this->avaliacoesPorFuncionario = $avaliacoesPorFuncionario;
        $this->datasContratuaisPorFuncionario = $datasContratuaisPorFuncionario;
    }

    public function competenciaYm(): string
    {
        return $this->competenciaYm;
    }

    public function getCargoSalario(int $funcionarioId): float
    {
        return (float) ($this->cargoSalarioPorFuncionario[$funcionarioId] ?? 0.0);
    }

    public function afastamentos(int $funcionarioId): Collection
    {
        return $this->afastamentosPorFuncionario[$funcionarioId] ?? collect();
    }

    public function avaliacoesDesempenho(int $funcionarioId): Collection
    {
        return $this->avaliacoesPorFuncionario[$funcionarioId] ?? collect();
    }

    /**
     * Maior nota final conhecida no lote (pré-carregada) — extensível para rubrica de desempenho.
     */
    public function melhorNotaFinal(int $funcionarioId): ?float
    {
        $col = $this->avaliacoesDesempenho($funcionarioId);
        if ($col->isEmpty()) {
            return null;
        }

        return (float) $col->max('AVALIACAO_NOTA_FINAL');
    }

    /**
     * Factor multiplicador da progressão/anuênio (placeholder 1.0; regra São Luís futura lê {@see melhorNotaFinal}).
     */
    public function fatorProgressaoPorDesempenho(int $funcionarioId): float
    {
        unset($funcionarioId);

        return 1.0;
    }

    /**
     * Indica sobreposição de afastamento com a competência (percorre apenas dados injectados).
     */
    public function possuiAfastamentoSobrepostoNaCompetencia(int $funcionarioId): bool
    {
        foreach ($this->afastamentos($funcionarioId) as $a) {
            $ini = $a->AFASTAMENTO_DATA_INICIO ?? null;
            $fim = $a->AFASTAMENTO_DATA_FIM ?? null;
            if ($this->intervaloSobrepoeCompetencia($ini, $fim)) {
                return true;
            }
        }

        return false;
    }

    private function intervaloSobrepoeCompetencia($dataInicio, $dataFim): bool
    {
        if (! $dataInicio) {
            return false;
        }
        try {
            $ini = Carbon::parse($dataInicio)->startOfDay();
        } catch (\Throwable $e) {
            return false;
        }
        try {
            $fim = $dataFim ? Carbon::parse($dataFim)->endOfDay() : $this->competenciaFim->copy()->addYears(50);
        } catch (\Throwable $e) {
            $fim = $this->competenciaFim->copy()->addYears(50);
        }

        return $ini <= $this->competenciaFim && $fim >= $this->competenciaInicio;
    }

    // ═══════════════════════════════════════════════════════════════════════════
    // GAP-MF-01/02/03 — Frequência, abono e pró-rata
    // ═══════════════════════════════════════════════════════════════════════════

    /**
     * GAP-MF-06: dias reais do mês de competência (28/29/30/31).
     * Substitui o "30 fixo" implícito.
     */
    public function diasNoMesCompetencia(): int
    {
        return $this->competenciaInicio->daysInMonth;
    }

    /**
     * GAP-MF-02: total de dias abonados por afastamento remunerado dentro da competência.
     *
     * Conta dias entre AFASTAMENTO_DATA_INICIO e AFASTAMENTO_DATA_FIM cobertos por LM/LMA/LP/etc.,
     * limitado ao mês de competência (sem sqlite_functions — só Carbon).
     *
     * Tipos abonados (compatível com o que o FolhaParserService listava):
     *   LICENCA_MEDICA, LICENCA_SAUDE, LICENCA_MATERNIDADE, LICENCA_PATERNIDADE,
     *   LICENCA_NOJO, LICENCA_GALA, AFASTAMENTO_JUDICIAL, AFASTAMENTO_REMUNERADO
     */
    public function diasAbonadosNoMes(int $funcionarioId): int
    {
        static $tiposAbonados = [
            'LICENCA_MEDICA', 'LICENCA_SAUDE', 'LICENCA_MATERNIDADE', 'LICENCA_PATERNIDADE',
            'LICENCA_NOJO', 'LICENCA_GALA', 'AFASTAMENTO_JUDICIAL', 'AFASTAMENTO_REMUNERADO',
        ];

        $totalDias = 0;
        foreach ($this->afastamentos($funcionarioId) as $a) {
            $tipo = (string) ($a->AFASTAMENTO_TIPO ?? '');
            if (! in_array($tipo, $tiposAbonados, true)) {
                continue;
            }

            $ini = $a->AFASTAMENTO_DATA_INICIO ?? null;
            $fim = $a->AFASTAMENTO_DATA_FIM ?? null;
            if (! $ini) {
                continue;
            }

            try {
                $iniDate = Carbon::parse($ini)->startOfDay();
            } catch (\Throwable) {
                continue;
            }

            try {
                $fimDate = $fim
                    ? Carbon::parse($fim)->startOfDay()
                    : $this->competenciaFim->copy()->startOfDay();
            } catch (\Throwable) {
                $fimDate = $this->competenciaFim->copy()->startOfDay();
            }

            // Intersecção do intervalo do afastamento com a competência
            $iniEfetivo = $iniDate->greaterThan($this->competenciaInicio) ? $iniDate : $this->competenciaInicio->copy();
            $fimEfetivo = $fimDate->lessThan($this->competenciaFim) ? $fimDate : $this->competenciaFim->copy()->startOfDay();

            if ($iniEfetivo->lessThanOrEqualTo($fimEfetivo)) {
                $totalDias += $iniEfetivo->diffInDays($fimEfetivo) + 1;
            }
        }

        // Limitar ao número de dias no mês (defesa contra dupla contagem)
        return min($totalDias, $this->diasNoMesCompetencia());
    }

    /**
     * GAP-MF-03: dias trabalhados proporcionais por admissão/exoneração no mês.
     *
     * Retorna o total de dias do mês ajustado:
     *   - Admitido em D dentro do mês → diasNoMes - D + 1
     *   - Exonerado em D dentro do mês → D
     *   - Não admitido nem exonerado neste mês → diasNoMes inteiro
     *   - Admitido E exonerado no mesmo mês (raro) → fim - inicio + 1
     */
    public function diasContratuaisNoMes(int $funcionarioId): int
    {
        $diasMes = $this->diasNoMesCompetencia();
        $datas = $this->datasContratuaisPorFuncionario[$funcionarioId] ?? null;

        if (! $datas) {
            return $diasMes;
        }

        $inicioContrato = null;
        $fimContrato = null;

        if (! empty($datas['inicio'])) {
            try {
                $inicioContrato = Carbon::parse($datas['inicio'])->startOfDay();
            } catch (\Throwable) {
                $inicioContrato = null;
            }
        }
        if (! empty($datas['fim'])) {
            try {
                $fimContrato = Carbon::parse($datas['fim'])->startOfDay();
            } catch (\Throwable) {
                $fimContrato = null;
            }
        }

        // Limites efetivos ao mês de competência
        $iniEfetivo = ($inicioContrato && $inicioContrato->greaterThan($this->competenciaInicio))
            ? $inicioContrato
            : $this->competenciaInicio->copy();
        $fimEfetivo = ($fimContrato && $fimContrato->lessThan($this->competenciaFim))
            ? $fimContrato
            : $this->competenciaFim->copy()->startOfDay();

        if ($iniEfetivo->greaterThan($fimEfetivo)) {
            return 0; // contrato não vigia neste mês
        }

        $dias = $iniEfetivo->diffInDays($fimEfetivo) + 1;

        return min($dias, $diasMes);
    }

    /**
     * GAP-MF-01: dias efetivamente trabalhados na competência.
     *
     * Fórmula: dias_contratuais - dias_abonados (faltas legítimas não entram aqui — entram
     * como diferença `diasMes - diasTrabalhados` no cálculo do desconto proporcional).
     *
     * Para a Fase 2-A, "faltas não justificadas" não são apuradas automaticamente — fica
     * para uma Fase futura quando o módulo de Ponto Eletrônico estiver consolidado em produção.
     * Por ora, o cálculo proporcional do MotorFolha trata cada servidor como tendo trabalhado
     * (dias_contratuais - dias_abonados) dias. Faltas não justificadas só descontam quando
     * vierem como LANCAMENTO_FOLHA tipo D.
     */
    public function diasTrabalhadosNoMes(int $funcionarioId): int
    {
        $contratuais = $this->diasContratuaisNoMes($funcionarioId);
        $abonados = $this->diasAbonadosNoMes($funcionarioId);

        // Abonados são considerados como trabalhados para fins de remuneração
        // (não afetam o vencimento). O método retorna apenas dias_contratuais
        // — o uso de "abonados" é informativo e auditável separadamente.
        unset($abonados);

        return $contratuais;
    }

    /**
     * Razão proporcional [0.0, 1.0] aplicada ao vencimento estrutural.
     *
     * Exemplo:
     *   - Mês 30 dias, admitido dia 16 → diasContratuais=15 → 15/30 = 0.5
     *   - Mês 28 dias (fevereiro), inteiro → 28/28 = 1.0
     *   - Mês 30 dias, sem dados contratuais → 30/30 = 1.0
     */
    public function razaoProporcionalVencimento(int $funcionarioId): float
    {
        $diasMes = $this->diasNoMesCompetencia();
        if ($diasMes <= 0) {
            return 0.0;
        }

        return $this->diasTrabalhadosNoMes($funcionarioId) / $diasMes;
    }
}
