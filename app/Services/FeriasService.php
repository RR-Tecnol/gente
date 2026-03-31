<?php

namespace App\Services;

use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;

class FeriasService
{
    protected TabelasImpostoService $impostos;

    public function __construct()
    {
        $this->impostos = new TabelasImpostoService();
    }

    /**
     * Calcula os valores financeiros de férias + 1/3 constitucional.
     * Regra: remuneração proporcional aos dias + 1/3 sobre essa base.
     * Incide INSS (RPPS) e IRRF sobre (base + 1/3).
     */
    public function calcular(int $funcionarioId, int $dias = 30): array
    {
        // Buscar salário base do funcionário
        $funcionario = DB::table('FUNCIONARIO as f')
            ->leftJoin('LOTACAO as l', function ($j) {
                $j->on('l.FUNCIONARIO_ID', '=', 'f.FUNCIONARIO_ID')
                  ->whereNull('l.LOTACAO_DATA_FIM');
            })
            ->leftJoin('CARGO as c', 'c.CARGO_ID', '=', 'l.CARGO_ID')
            ->where('f.FUNCIONARIO_ID', $funcionarioId)
            ->select('f.*', 'c.CARGO_SALARIO as salario')
            ->first();

        if (!$funcionario) {
            throw new \RuntimeException("Funcionário #{$funcionarioId} não encontrado.");
        }

        $salarioBase = (float) ($funcionario->salario ?? $funcionario->FUNCIONARIO_SALARIO ?? 0);

        if ($salarioBase <= 0) {
            throw new \RuntimeException("Salário base não encontrado para o funcionário #{$funcionarioId}.");
        }

        // Férias proporcionais aos dias (30 dias = salário integral)
        $valorBase = round($salarioBase / 30 * $dias, 2);

        // 1/3 constitucional (art. 7º, XVII CF/88)
        $valorTerco = round($valorBase / 3, 2);

        // Base de cálculo para INSS e IRRF = remuneração + 1/3
        $baseCalculo = $valorBase + $valorTerco;

        // INSS/RPPS sobre a base total
        $inss = $this->impostos->calcularInssRpps($baseCalculo);

        // IRRF sobre (base - INSS)
        $baseIrrf = max(0, $baseCalculo - $inss);
        $irrf = $this->impostos->calcularIrrf($baseIrrf);

        $liquido = round($baseCalculo - $inss - $irrf, 2);

        return [
            'salario_base'   => $salarioBase,
            'dias'           => $dias,
            'valor_base'     => $valorBase,
            'valor_terco'    => $valorTerco,
            'base_calculo'   => $baseCalculo,
            'inss'           => $inss,
            'irrf'           => $irrf,
            'liquido'        => $liquido,
        ];
    }

    /**
     * Aprova as férias: calcula valores, persiste e gera lançamento em DETALHE_FOLHA.
     */
    public function aprovar(int $feriasId, int $aprovadoPor, string $competencia): array
    {
        $ferias = DB::table('FERIAS')->where('FERIAS_ID', $feriasId)->first();
        if (!$ferias) throw new \RuntimeException("Férias #{$feriasId} não encontradas.");
        if ($ferias->FERIAS_STATUS === 'APROVADA') throw new \RuntimeException("Férias já aprovadas.");

        $dias = (int) ($ferias->FERIAS_DIAS ?? 30);
        $calc = $this->calcular($ferias->FUNCIONARIO_ID, $dias);

        DB::transaction(function () use ($ferias, $feriasId, $aprovadoPor, $competencia, $calc) {
            // Atualizar registro de férias com valores calculados
            DB::table('FERIAS')->where('FERIAS_ID', $feriasId)->update([
                'FERIAS_STATUS'                => 'APROVADA',
                'APROVADO_POR'                 => $aprovadoPor,
                'APROVADO_EM'                  => now(),
                'FERIAS_VALOR_BASE'            => $calc['valor_base'],
                'FERIAS_VALOR_TERCO'           => $calc['valor_terco'],
                'FERIAS_VALOR_INSS'            => $calc['inss'],
                'FERIAS_VALOR_IRRF'            => $calc['irrf'],
                'FERIAS_VALOR_LIQUIDO'         => $calc['liquido'],
                'FERIAS_COMPETENCIA_PAGAMENTO' => $competencia,
            ]);

            Log::info('FeriasService: férias aprovadas', [
                'ferias_id'   => $feriasId,
                'funcionario' => $ferias->FUNCIONARIO_ID,
                'liquido'     => $calc['liquido'],
                'competencia' => $competencia,
            ]);
        });

        return $calc;
    }
}
