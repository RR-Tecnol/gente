<?php

namespace App\Services;

use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;
use Carbon\Carbon;

class RescisaoService
{
    protected TabelasImpostoService $impostos;

    public function __construct()
    {
        $this->impostos = new TabelasImpostoService();
    }

    /**
     * Calcula verbas rescisórias para um funcionário.
     * Extrai e organiza a lógica já existente em exoneracao.php.
     */
    public function calcular(int $funcionarioId, string $dataExoneracao, string $motivoSaida): array
    {
        $func = DB::table('FUNCIONARIO as f')
            ->join('PESSOA as p', 'p.PESSOA_ID', '=', 'f.PESSOA_ID')
            ->leftJoin('LOTACAO as l', function ($j) {
                $j->on('l.FUNCIONARIO_ID', '=', 'f.FUNCIONARIO_ID')
                  ->whereNull('l.LOTACAO_DATA_FIM');
            })
            ->leftJoin('CARGO as c', 'c.CARGO_ID', '=', 'l.CARGO_ID')
            ->where('f.FUNCIONARIO_ID', $funcionarioId)
            ->select('f.*', 'p.PESSOA_NOME', 'p.PESSOA_CPF_NUMERO',
                     'c.CARGO_NOME', 'c.CARGO_SALARIO')
            ->first();

        if (!$func) throw new \RuntimeException("Funcionário #{$funcionarioId} não encontrado.");

        $salarioBase = (float) ($func->CARGO_SALARIO ?? $func->FUNCIONARIO_SALARIO ?? 0);
        $regime      = $func->FUNCIONARIO_REGIME_PREV ?? 'RPPS';

        $dataExon  = Carbon::parse($dataExoneracao);
        $admissao  = $func->FUNCIONARIO_DATA_INICIO
            ? Carbon::parse($func->FUNCIONARIO_DATA_INICIO)
            : $dataExon;

        $anosServico      = (int) $admissao->diffInYears($dataExon);
        $anosCompletos    = $anosServico;
        $inicioAquisitivo = $admissao->copy()->addYears($anosCompletos);

        // Saldo de salário
        $diaExon      = (int) $dataExon->format('d');
        $saldoSalario = round($salarioBase / 30 * $diaExon, 2);

        // Férias proporcionais + 1/3
        $mesesAquisitivo  = min((int) $inicioAquisitivo->diffInMonths($dataExon), 12);
        $feriasProp       = round($salarioBase / 12 * $mesesAquisitivo, 2);
        $feriasPropTercio = round($feriasProp / 3, 2);

        // Férias vencidas + 1/3 (períodos não gozados)
        $feriasVencidas      = 0.0;
        $feriasVencidasTercio = 0.0;
        try {
            $gozadas = DB::table('FERIAS')
                ->where('FUNCIONARIO_ID', $funcionarioId)
                ->whereIn('FERIAS_STATUS', ['APROVADA', 'PAGA'])
                ->count();
            $periodosTotais = max(0, $anosCompletos - $gozadas);
            if ($periodosTotais > 0) {
                $feriasVencidas       = round($salarioBase * $periodosTotais, 2);
                $feriasVencidasTercio = round($feriasVencidas / 3, 2);
            }
        } catch (\Throwable $e) {}

        // 13º proporcional
        $mesAtual            = (int) $dataExon->format('n');
        $decimoProporcional  = round($salarioBase / 12 * $mesAtual, 2);

        // FGTS + multa 40% (apenas RGPS)
        $fgtsMulta = 0.0;
        if ($regime === 'RGPS') {
            $mesesTotais = $anosServico * 12 + $mesAtual;
            $fgtsMulta   = round($salarioBase * 0.08 * $mesesTotais * 0.40, 2);
        }

        // IRRF sobre base tributável rescisória
        $baseTributavel = $feriasVencidas + $feriasVencidasTercio + $decimoProporcional;
        $descontoIrrf   = $this->impostos->calcularIrrf($baseTributavel);

        $totalBruto  = $saldoSalario + $feriasProp + $feriasPropTercio
                     + $feriasVencidas + $feriasVencidasTercio + $decimoProporcional + $fgtsMulta;
        $totalLiquido = round($totalBruto - $descontoIrrf, 2);

        return [
            'funcionario_id'       => $funcionarioId,
            'nome'                 => $func->PESSOA_NOME,
            'cpf'                  => $func->PESSOA_CPF_NUMERO,
            'cargo'                => $func->CARGO_NOME,
            'salario_base'         => $salarioBase,
            'data_admissao'        => $admissao->format('Y-m-d'),
            'data_exoneracao'      => $dataExoneracao,
            'motivo_saida'         => $motivoSaida,
            'regime'               => $regime,
            'anos_servico'         => $anosServico,
            'saldo_salario'        => $saldoSalario,
            'ferias_prop'          => $feriasProp,
            'ferias_prop_tercio'   => $feriasPropTercio,
            'ferias_vencidas'      => $feriasVencidas,
            'ferias_vencidas_tercio' => $feriasVencidasTercio,
            'decimo_prop'          => $decimoProporcional,
            'fgts_multa'           => $fgtsMulta,
            'desconto_irrf'        => $descontoIrrf,
            'total_bruto'          => $totalBruto,
            'total_liquido'        => $totalLiquido,
        ];
    }

    /**
     * Persiste o cálculo na tabela RESCISAO_CALCULO.
     */
    public function salvar(array $calc, int $calculadoPor): int
    {
        return DB::table('RESCISAO_CALCULO')->insertGetId([
            'FUNCIONARIO_ID'         => $calc['funcionario_id'],
            'DATA_EXONERACAO'        => $calc['data_exoneracao'],
            'MOTIVO_SAIDA'           => $calc['motivo_saida'],
            'DATA_CALCULO'           => now(),
            'CALCULADO_POR'          => $calculadoPor,
            'STATUS'                 => 'RASCUNHO',
            'SALDO_SALARIO'          => $calc['saldo_salario'],
            'FERIAS_PROP'            => $calc['ferias_prop'],
            'FERIAS_PROP_TERCIO'     => $calc['ferias_prop_tercio'],
            'FERIAS_VENCIDAS'        => $calc['ferias_vencidas'],
            'FERIAS_VENCIDAS_TERCIO' => $calc['ferias_vencidas_tercio'],
            'DECIMO_TERCEIRO_PROP'   => $calc['decimo_prop'],
            'FGTS_MULTA'             => $calc['fgts_multa'],
            'LICENCA_PREMIO'         => 0,
            'INDENIZACAO_CARGO'      => 0,
            'OUTROS'                 => 0,
            'TOTAL_BRUTO'            => $calc['total_bruto'],
            'DESCONTO_IRRF'          => $calc['desconto_irrf'],
            'DESCONTO_OUTROS'        => 0,
            'TOTAL_LIQUIDO'          => $calc['total_liquido'],
            'REGIME_PREV'            => $calc['regime'],
            'created_at'             => now(),
            'updated_at'             => now(),
        ]);
    }
}
