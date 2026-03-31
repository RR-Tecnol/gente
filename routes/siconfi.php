<?php

use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Auth;

/**
 * SICONFI — Sistema de Informações Contábeis e Fiscais (STN/LRF)
 *
 * RREO — Relatório Resumido da Execução Orçamentária (bimestral)
 * RGF  — Relatório de Gestão Fiscal (quadrimestral)
 *
 * Limite LRF art. 19: despesa total com pessoal ≤ 60% da RCL para municípios
 * Limite prudencial: 95% do limite = 57% da RCL
 * Limite de alerta:  90% do limite = 54% da RCL
 */

// ── RGF — Relatório de Gestão Fiscal ─────────────────────────────────────────
Route::get('/siconfi/rgf/{ano}/{quadrimestre}', function (int $ano, int $quadrimestre) {
    try {
        if (!in_array($quadrimestre, [1, 2, 3])) {
            return response()->json(['erro' => 'Quadrimestre inválido. Use 1, 2 ou 3.'], 422);
        }

        // Definir período (meses do quadrimestre)
        $meses = match($quadrimestre) {
            1 => [1, 2, 3, 4],
            2 => [5, 6, 7, 8],
            3 => [9, 10, 11, 12],
        };

        // Despesa total com pessoal no quadrimestre
        $despesaPessoal = DB::table('DETALHE_FOLHA as df')
            ->join('FOLHA as f', 'f.FOLHA_ID', '=', 'df.FOLHA_ID')
            ->whereYear('f.FOLHA_COMPETENCIA', $ano)
            ->whereIn(DB::raw('CAST(strftime("%m", f.FOLHA_COMPETENCIA) AS INTEGER)'), $meses)
            ->whereNull('df.DETALHE_FOLHA_ERRO')
            ->sum(DB::raw('COALESCE(df.DETALHE_FOLHA_PROVENTOS, 0)'));

        $despesaPessoal = (float) $despesaPessoal;

        // Despesa por secretaria/unidade no período
        $porUnidade = DB::table('DETALHE_FOLHA as df')
            ->join('FOLHA as f', 'f.FOLHA_ID', '=', 'df.FOLHA_ID')
            ->leftJoin('SETOR as s', 's.SETOR_ID', '=', 'df.SETOR_ID')
            ->whereYear('f.FOLHA_COMPETENCIA', $ano)
            ->whereIn(DB::raw('CAST(strftime("%m", f.FOLHA_COMPETENCIA) AS INTEGER)'), $meses)
            ->whereNull('df.DETALHE_FOLHA_ERRO')
            ->groupBy('df.SETOR_ID', 's.SETOR_NOME')
            ->selectRaw('s.SETOR_NOME as setor, SUM(COALESCE(df.DETALHE_FOLHA_PROVENTOS, 0)) as total')
            ->orderByRaw('total DESC')
            ->get();

        // RCL informada via parâmetro ou buscada do banco
        $rcl = (float) request('rcl', 0);

        $pctPessoal      = $rcl > 0 ? round($despesaPessoal / $rcl * 100, 4) : 0;
        $limiteLrf       = 60.0;
        $limitePrudencial = 57.0;
        $limiteAlerta    = 54.0;

        $status = 'REGULAR';
        if ($pctPessoal > $limiteLrf)       $status = 'ACIMA_LIMITE_LRF';
        elseif ($pctPessoal > $limitePrudencial) $status = 'LIMITE_PRUDENCIAL';
        elseif ($pctPessoal > $limiteAlerta)     $status = 'ZONA_ALERTA';

        $relatorio = [
            'tipo'              => 'RGF',
            'ano'               => $ano,
            'quadrimestre'      => $quadrimestre,
            'periodo_meses'     => $meses,
            'rcl'               => $rcl,
            'despesa_pessoal'   => $despesaPessoal,
            'pct_rcl'           => $pctPessoal,
            'limite_lrf'        => $limiteLrf,
            'limite_prudencial' => $limitePrudencial,
            'limite_alerta'     => $limiteAlerta,
            'status_lrf'        => $status,
            'margem_disponivel' => $rcl > 0 ? round(($limiteLrf / 100 * $rcl) - $despesaPessoal, 2) : null,
            'por_unidade'       => $porUnidade,
        ];

        // Persistir
        DB::table('SICONFI_RELATORIO')->updateOrInsert(
            ['RELATORIO_ANO' => $ano, 'RELATORIO_TIPO' => 'RGF', 'RELATORIO_PERIODO' => $quadrimestre],
            [
                'RELATORIO_STATUS'       => 'GERADO',
                'RCL_VALOR'              => $rcl,
                'DESPESA_PESSOAL_TOTAL'  => $despesaPessoal,
                'DESPESA_PESSOAL_PCT'    => $pctPessoal,
                'RELATORIO_JSON'         => json_encode($relatorio),
                'RELATORIO_ARQUIVO_NOME' => "RGF_{$ano}_Q{$quadrimestre}.json",
                'GERADO_POR'             => Auth::id(),
                'updated_at'             => now(),
                'created_at'             => now(),
            ]
        );

        return response()->json($relatorio);
    } catch (\Throwable $e) {
        return response()->json(['erro' => $e->getMessage()], 500);
    }
});

// ── RREO — Relatório Resumido da Execução Orçamentária ────────────────────────
Route::get('/siconfi/rreo/{ano}/{bimestre}', function (int $ano, int $bimestre) {
    try {
        if (!in_array($bimestre, [1, 2, 3, 4, 5, 6])) {
            return response()->json(['erro' => 'Bimestre inválido. Use 1 a 6.'], 422);
        }

        $meses = match($bimestre) {
            1 => [1, 2],   2 => [3, 4],   3 => [5, 6],
            4 => [7, 8],   5 => [9, 10],  6 => [11, 12],
        };

        // Receitas arrecadadas no bimestre
        $receitaArrecadada = (float) DB::table('RECEITA_LANCAMENTO')
            ->where('RECEITA_ANO', $ano)
            ->whereIn('RECEITA_MES', $meses)
            ->sum('RECEITA_VALOR_ARRECADADO');

        // Receita prevista (LOA)
        $receitaPrevista = (float) DB::table('RECEITA_LANCAMENTO')
            ->where('RECEITA_ANO', $ano)
            ->whereIn('RECEITA_MES', $meses)
            ->sum('RECEITA_VALOR_PREVISTO');

        // Despesas empenhadas, liquidadas e pagas
        $empenhadoTotal = (float) DB::table('EMPENHO')
            ->whereYear('EMPENHO_DATA', $ano)
            ->whereIn(DB::raw('CAST(strftime("%m", EMPENHO_DATA) AS INTEGER)'), $meses)
            ->where('EMPENHO_STATUS', '!=', 'ANULADO')
            ->sum('EMPENHO_VALOR');

        // Despesa com pessoal no bimestre
        $despesaPessoal = (float) DB::table('DETALHE_FOLHA as df')
            ->join('FOLHA as f', 'f.FOLHA_ID', '=', 'df.FOLHA_ID')
            ->whereYear('f.FOLHA_COMPETENCIA', $ano)
            ->whereIn(DB::raw('CAST(strftime("%m", f.FOLHA_COMPETENCIA) AS INTEGER)'), $meses)
            ->whereNull('df.DETALHE_FOLHA_ERRO')
            ->sum(DB::raw('COALESCE(df.DETALHE_FOLHA_PROVENTOS, 0)'));

        $relatorio = [
            'tipo'               => 'RREO',
            'ano'                => $ano,
            'bimestre'           => $bimestre,
            'periodo_meses'      => $meses,
            'receita_prevista'   => $receitaPrevista,
            'receita_arrecadada' => $receitaArrecadada,
            'pct_arrecadacao'    => $receitaPrevista > 0
                ? round($receitaArrecadada / $receitaPrevista * 100, 2) : 0,
            'despesa_empenhada'  => $empenhadoTotal,
            'despesa_pessoal'    => $despesaPessoal,
            'superavit_deficit'  => round($receitaArrecadada - $empenhadoTotal, 2),
        ];

        DB::table('SICONFI_RELATORIO')->updateOrInsert(
            ['RELATORIO_ANO' => $ano, 'RELATORIO_TIPO' => 'RREO', 'RELATORIO_PERIODO' => $bimestre],
            [
                'RELATORIO_STATUS'       => 'GERADO',
                'RCL_VALOR'              => $receitaArrecadada,
                'DESPESA_PESSOAL_TOTAL'  => $despesaPessoal,
                'DESPESA_PESSOAL_PCT'    => 0,
                'RELATORIO_JSON'         => json_encode($relatorio),
                'RELATORIO_ARQUIVO_NOME' => "RREO_{$ano}_B{$bimestre}.json",
                'GERADO_POR'             => Auth::id(),
                'updated_at'             => now(),
                'created_at'             => now(),
            ]
        );

        return response()->json($relatorio);
    } catch (\Throwable $e) {
        return response()->json(['erro' => $e->getMessage()], 500);
    }
});

// Histórico de relatórios gerados
Route::get('/siconfi/historico', function () {
    try {
        return response()->json([
            'historico' => DB::table('SICONFI_RELATORIO')
                ->orderByDesc('RELATORIO_ANO')
                ->orderByDesc('RELATORIO_PERIODO')
                ->limit(20)
                ->get()
        ]);
    } catch (\Throwable $e) {
        return response()->json(['erro' => $e->getMessage()], 500);
    }
});
