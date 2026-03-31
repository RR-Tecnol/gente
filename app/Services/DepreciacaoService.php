<?php

namespace App\Services;

use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;

class DepreciacaoService
{
    // Vida útil e valor residual padrão por categoria (NBCASP 16.9)
    const PARAMETROS = [
        'IMOVEL'      => ['vida_util' => 25, 'residual_pct' => 0],
        'VEICULO'     => ['vida_util' =>  5, 'residual_pct' => 20],
        'TI'          => ['vida_util' =>  3, 'residual_pct' => 10],
        'EQUIPAMENTO' => ['vida_util' => 10, 'residual_pct' => 10],
        'MOVEL'       => ['vida_util' => 10, 'residual_pct' => 10],
    ];

    /**
     * Deprecia todos os bens ativos para a competência informada (AAAAMM).
     * Método linear mensal conforme NBCASP 16.9.
     */
    public function depreciarMes(string $competencia): array
    {
        // Competência AAAAMM → YYYY-MM para comparação
        $anoMes = substr($competencia, 0, 4) . '-' . substr($competencia, 4, 2);

        $bens = DB::table('BEM_PATRIMONIAL')
            ->where('BEM_STATUS', 'ATIVO')
            ->where(function ($q) use ($anoMes) {
                $q->whereNull('BEM_DATA_ULTIMA_DEPRECIACAO')
                  ->orWhereRaw("strftime('%Y-%m', BEM_DATA_ULTIMA_DEPRECIACAO) < ?", [$anoMes]);
            })
            ->get();

        $depreciados = 0;

        foreach ($bens as $bem) {
            $deprecMensal = round(
                ($bem->BEM_VALOR_AQUISICAO - $bem->BEM_VALOR_RESIDUAL)
                / ($bem->BEM_VIDA_UTIL_ANOS * 12),
                2
            );

            // Não depreciar além do valor depreciável
            $maxDepreciavel = $bem->BEM_VALOR_AQUISICAO - $bem->BEM_VALOR_RESIDUAL;
            $novaAcumulada  = min(
                $bem->BEM_DEPRECIACAO_ACUMULADA + $deprecMensal,
                $maxDepreciavel
            );
            $novoValorAtual = $bem->BEM_VALOR_AQUISICAO - $novaAcumulada;

            DB::table('BEM_PATRIMONIAL')->where('BEM_ID', $bem->BEM_ID)->update([
                'BEM_DEPRECIACAO_ACUMULADA'   => $novaAcumulada,
                'BEM_VALOR_ATUAL'             => $novoValorAtual,
                'BEM_DATA_ULTIMA_DEPRECIACAO' => now()->toDateString(),
                'updated_at'                  => now(),
            ]);

            $depreciados++;
        }

        Log::info('[DepreciacaoService] Depreciação mensal executada.', [
            'competencia'  => $competencia,
            'bens_depreciados' => $depreciados,
        ]);

        return ['bens_depreciados' => $depreciados, 'competencia' => $competencia];
    }

    /**
     * Retorna parâmetros NBCASP padrão para uma categoria,
     * calculando residual em valor absoluto.
     */
    public function parametrosPorCategoria(string $categoria, float $valorAquisicao): array
    {
        $params = self::PARAMETROS[strtoupper($categoria)]
            ?? ['vida_util' => 10, 'residual_pct' => 10];

        return [
            'vida_util_anos'  => $params['vida_util'],
            'valor_residual'  => round($valorAquisicao * ($params['residual_pct'] / 100), 2),
            'depreciacao_mensal' => round(
                ($valorAquisicao - ($valorAquisicao * $params['residual_pct'] / 100))
                / ($params['vida_util'] * 12), 2
            ),
        ];
    }
}
