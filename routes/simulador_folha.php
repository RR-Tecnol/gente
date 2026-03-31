<?php

use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Auth;
use App\Services\FolhaParserService;
use App\Models\Folha;

// ── Simulador de Folha — dry-run via rollback de transação ───────────────────
// Calcula a folha sem persistir nada. Retorna preview com proventos/descontos.
Route::post('/simulador/folha', function () {
    try {
        $data = request()->validate([
            'competencia'    => 'required|string', // AAAAMM
            'vinculo_id'     => 'nullable|integer',
            'setor_id'       => 'nullable|integer',
            'reajuste_pct'   => 'nullable|numeric|min:0|max:100', // % de reajuste a simular
        ]);

        $competencia  = $data['competencia'];
        $reajustePct  = (float) ($data['reajuste_pct'] ?? 0);

        // Buscar folha existente ou simular sem folha base
        $folha = DB::table('FOLHA')
            ->where('FOLHA_COMPETENCIA', $competencia)
            ->when(isset($data['vinculo_id']), fn($q) => $q->where('VINCULO_ID', $data['vinculo_id']))
            ->first();

        if (!$folha) {
            return response()->json([
                'aviso'      => "Nenhuma folha encontrada para competência {$competencia}. Execute o pré-processamento primeiro.",
                'simulacao'  => null,
            ]);
        }

        $resultadoSimulacao = null;

        // Executar dentro de uma transação que será revertida — dry-run real
        DB::transaction(function () use ($folha, $reajustePct, &$resultadoSimulacao) {
            // Se há reajuste, aplicar temporariamente nos salários dos cargos
            if ($reajustePct > 0) {
                DB::statement("
                    UPDATE CARGO SET CARGO_SALARIO = CARGO_SALARIO * (1 + ? / 100)
                    WHERE CARGO_SALARIO > 0
                ", [$reajustePct]);
            }

            // Rodar o parser em modo simulação
            $parser = new FolhaParserService();
            $folhaModel = Folha::find($folha->FOLHA_ID);

            if ($folhaModel) {
                $parser->processar($folhaModel);
            }

            // Coletar resultados da simulação
            $totais = DB::table('DETALHE_FOLHA')
                ->where('FOLHA_ID', $folha->FOLHA_ID)
                ->selectRaw('
                    COUNT(*) as total_servidores,
                    SUM(COALESCE(DETALHE_FOLHA_PROVENTOS, 0)) as total_proventos,
                    SUM(COALESCE(DETALHE_FOLHA_DESCONTOS, 0)) as total_descontos,
                    SUM(COALESCE(DETALHE_FOLHA_LIQUIDO, 0))   as total_liquido
                ')
                ->first();

            // Breakdown por vínculo
            $porVinculo = DB::table('DETALHE_FOLHA as df')
                ->join('FUNCIONARIO as f', 'f.FUNCIONARIO_ID', '=', 'df.FUNCIONARIO_ID')
                ->leftJoin('LOTACAO as l', function ($j) {
                    $j->on('l.FUNCIONARIO_ID', '=', 'f.FUNCIONARIO_ID')
                      ->whereNull('l.LOTACAO_DATA_FIM');
                })
                ->leftJoin('VINCULO as v', 'v.VINCULO_ID', '=', 'f.VINCULO_ID')
                ->where('df.FOLHA_ID', $folha->FOLHA_ID)
                ->groupBy('v.VINCULO_ID', 'v.VINCULO_NOME')
                ->selectRaw('
                    v.VINCULO_NOME,
                    COUNT(*) as qtd,
                    SUM(COALESCE(df.DETALHE_FOLHA_PROVENTOS, 0)) as proventos,
                    SUM(COALESCE(df.DETALHE_FOLHA_DESCONTOS, 0)) as descontos,
                    SUM(COALESCE(df.DETALHE_FOLHA_LIQUIDO, 0))   as liquido
                ')
                ->get();

            $resultadoSimulacao = [
                'competencia'       => $folha->FOLHA_COMPETENCIA,
                'folha_id'          => $folha->FOLHA_ID,
                'reajuste_simulado' => $reajustePct,
                'totais'            => $totais,
                'por_vinculo'       => $porVinculo,
                'aviso'             => 'SIMULAÇÃO — nenhum dado foi persistido.',
            ];

            // ROLLBACK automático ao lançar exceção específica
            throw new \RuntimeException('__ROLLBACK_SIMULACAO__');
        });

    } catch (\RuntimeException $e) {
        if ($e->getMessage() === '__ROLLBACK_SIMULACAO__' && $resultadoSimulacao) {
            return response()->json(['ok' => true, 'simulacao' => $resultadoSimulacao]);
        }
        return response()->json(['erro' => $e->getMessage()], 500);
    } catch (\Throwable $e) {
        return response()->json(['erro' => $e->getMessage()], 500);
    }
});

// ── Simulador de Impacto LRF ─────────────────────────────────────────────────
// Dado um % de reajuste, calcula o novo custo de pessoal e o impacto no limite LRF.
Route::post('/simulador/lrf', function () {
    try {
        $data = request()->validate([
            'reajuste_pct' => 'required|numeric|min:0|max:100',
            'rcl_anual'    => 'required|numeric|min:0', // Receita Corrente Líquida informada pelo usuário
            'ano'          => 'nullable|integer',
        ]);

        $ano         = (int) ($data['ano'] ?? date('Y'));
        $reajustePct = (float) $data['reajuste_pct'];
        $rcl         = (float) $data['rcl_anual'];

        // Custo atual de pessoal (soma de todas as folhas do ano)
        $custoAtual = DB::table('DETALHE_FOLHA as df')
            ->join('FOLHA as f', 'f.FOLHA_ID', '=', 'df.FOLHA_ID')
            ->whereYear('f.FOLHA_COMPETENCIA', $ano)
            ->sum(DB::raw('COALESCE(df.DETALHE_FOLHA_PROVENTOS, 0)'));

        $custoAtual     = (float) $custoAtual;
        $custoSimulado  = round($custoAtual * (1 + $reajustePct / 100), 2);
        $pctAtual       = $rcl > 0 ? round($custoAtual / $rcl * 100, 2) : 0;
        $pctSimulado    = $rcl > 0 ? round($custoSimulado / $rcl * 100, 2) : 0;

        // Limite LRF art. 19 — municípios: 60% da RCL
        // Limite prudencial: 95% do limite = 57% da RCL
        $limiteLrf      = 60.0;
        $limitePrudencial = 57.0;

        return response()->json([
            'ano'                 => $ano,
            'rcl_anual'           => $rcl,
            'reajuste_pct'        => $reajustePct,
            'custo_atual'         => $custoAtual,
            'custo_simulado'      => $custoSimulado,
            'diferenca'           => round($custoSimulado - $custoAtual, 2),
            'pct_rcl_atual'       => $pctAtual,
            'pct_rcl_simulado'    => $pctSimulado,
            'limite_lrf'          => $limiteLrf,
            'limite_prudencial'   => $limitePrudencial,
            'status_atual'        => $pctAtual > $limiteLrf ? 'ACIMA_LIMITE' : ($pctAtual > $limitePrudencial ? 'ZONA_ATENCAO' : 'REGULAR'),
            'status_simulado'     => $pctSimulado > $limiteLrf ? 'ACIMA_LIMITE' : ($pctSimulado > $limitePrudencial ? 'ZONA_ATENCAO' : 'REGULAR'),
            'margem_disponivel'   => round(($limiteLrf / 100 * $rcl) - $custoSimulado, 2),
        ]);
    } catch (\Throwable $e) {
        return response()->json(['erro' => $e->getMessage()], 500);
    }
});
