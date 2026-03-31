<?php

namespace App\Services;

use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Auth;

class DecimoTerceiroService
{
    protected TabelasImpostoService $impostos;

    public function __construct()
    {
        $this->impostos = new TabelasImpostoService();
    }

    /**
     * Calcula e persiste o 13º para todos os funcionários ativos.
     *
     * Regra legal:
     *  1ª Parcela (fev–nov): salário_base / 2, sem INSS, sem IRRF
     *  2ª Parcela (dez): base_completa - INSS - IRRF - adiantamento_1a
     *  Rescisório: base × (meses_no_ano / 12)
     */
    public function calcularLote(int $ano, string $tipo, string $competencia): array
    {
        if (!in_array($tipo, ['PRIMEIRA_PARCELA', 'SEGUNDA_PARCELA', 'RESCISORIO'])) {
            throw new \RuntimeException("Tipo inválido: {$tipo}");
        }

        // Buscar todos os funcionários ativos com salário
        $funcionarios = DB::table('FUNCIONARIO as f')
            ->leftJoin('LOTACAO as l', function ($j) {
                $j->on('l.FUNCIONARIO_ID', '=', 'f.FUNCIONARIO_ID')
                  ->whereNull('l.LOTACAO_DATA_FIM');
            })
            ->leftJoin('CARGO as c', 'c.CARGO_ID', '=', 'l.CARGO_ID')
            ->whereNull('f.FUNCIONARIO_DATA_FIM')
            ->whereNull('f.deleted_at')
            ->select(
                'f.FUNCIONARIO_ID',
                'f.FUNCIONARIO_DATA_INICIO',
                DB::raw('COALESCE(c.CARGO_SALARIO, f.FUNCIONARIO_SALARIO, 0) as salario')
            )
            ->get();

        $resultados = [];
        $geradoPor  = Auth::id();

        foreach ($funcionarios as $func) {
            if ((float) $func->salario <= 0) continue;

            // Calcular meses trabalhados no ano
            $meses = $this->calcularMesesTrabalhados($func->FUNCIONARIO_DATA_INICIO, $ano);
            if ($meses <= 0) continue;

            $salario    = (float) $func->salario;
            $baseProporcional = round($salario * $meses / 12, 2);

            $inss      = 0;
            $irrf      = 0;
            $adiantamento = 0;
            $bruto     = 0;
            $liquido   = 0;

            if ($tipo === 'PRIMEIRA_PARCELA') {
                // Adiantamento: metade do salário, sem tributação
                $bruto   = round($salario / 2, 2);
                $liquido = $bruto;

            } elseif ($tipo === 'SEGUNDA_PARCELA') {
                // Base = proporcional ao ano (já desconta meses se admitido no ano)
                $bruto = $baseProporcional;
                $inss  = $this->impostos->calcularInssRpps($bruto);
                $irrf  = $this->impostos->calcularIrrf(max(0, $bruto - $inss));

                // Buscar adiantamento da 1ª parcela já pago
                $primeira = DB::table('DECIMO_TERCEIRO')
                    ->where('FUNCIONARIO_ID', $func->FUNCIONARIO_ID)
                    ->where('DT_ANO', $ano)
                    ->where('DT_TIPO', 'PRIMEIRA_PARCELA')
                    ->first();
                $adiantamento = $primeira ? (float) $primeira->DT_VALOR_LIQUIDO : 0;
                $liquido      = round($bruto - $inss - $irrf - $adiantamento, 2);

            } elseif ($tipo === 'RESCISORIO') {
                $bruto   = $baseProporcional;
                $inss    = $this->impostos->calcularInssRpps($bruto);
                $irrf    = $this->impostos->calcularIrrf(max(0, $bruto - $inss));
                $liquido = round($bruto - $inss - $irrf, 2);
            }

            // Upsert — se já calculado para este ano/tipo, atualiza
            DB::table('DECIMO_TERCEIRO')->updateOrInsert(
                [
                    'FUNCIONARIO_ID' => $func->FUNCIONARIO_ID,
                    'DT_ANO'         => $ano,
                    'DT_TIPO'        => $tipo,
                ],
                [
                    'DT_STATUS'             => 'CALCULADO',
                    'DT_MESES_TRABALHADOS'  => $meses,
                    'DT_SALARIO_BASE'       => $salario,
                    'DT_VALOR_BRUTO'        => $bruto,
                    'DT_VALOR_INSS'         => $inss,
                    'DT_VALOR_IRRF'         => $irrf,
                    'DT_ADIANTAMENTO'       => $adiantamento,
                    'DT_VALOR_LIQUIDO'      => $liquido,
                    'DT_COMPETENCIA'        => $competencia,
                    'GERADO_POR'            => $geradoPor,
                    'updated_at'            => now(),
                    'created_at'            => now(),
                ]
            );

            $resultados[] = [
                'funcionario_id' => $func->FUNCIONARIO_ID,
                'meses'          => $meses,
                'bruto'          => $bruto,
                'inss'           => $inss,
                'irrf'           => $irrf,
                'adiantamento'   => $adiantamento,
                'liquido'        => $liquido,
            ];
        }

        Log::info('DecimoTerceiroService: lote calculado', [
            'ano'        => $ano,
            'tipo'       => $tipo,
            'competencia'=> $competencia,
            'total'      => count($resultados),
        ]);

        return [
            'tipo'       => $tipo,
            'ano'        => $ano,
            'competencia'=> $competencia,
            'total'      => count($resultados),
            'registros'  => $resultados,
        ];
    }

    private function calcularMesesTrabalhados(?string $dataInicio, int $ano): int
    {
        if (!$dataInicio) return 12;

        $inicio = \Carbon\Carbon::parse($dataInicio);
        $anoInicio = $inicio->year;

        // Admitido antes do ano de referência = 12 meses
        if ($anoInicio < $ano) return 12;

        // Admitido no ano de referência = meses restantes
        // Regra: fração ≥ 15 dias = 1 mês
        if ($anoInicio === $ano) {
            $dia = $inicio->day;
            $mes = $inicio->month;
            return $dia <= 15 ? (13 - $mes) : (12 - $mes);
        }

        // Admitido após o ano = não tem direito
        return 0;
    }
}
