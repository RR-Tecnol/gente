<?php
// ══════════════════════════════════════════════════════════════════
// ORÇAMENTO PÚBLICO — PPA / LOA (ERP Sprint 1)
// ══════════════════════════════════════════════════════════════════
use Illuminate\Support\Facades\Auth;

// ── GET: programas do PPA ────────────────────────────────────────
Route::get('/orcamento/ppa', function () {
    try {
        $ppas = DB::table('ORCAMENTO_PPA')
            ->orderBy('PPA_ANO_INICIO', 'desc')
            ->get();

        $programas = DB::table('ORCAMENTO_PROGRAMA as pr')
            ->join('ORCAMENTO_PPA as p', 'p.PPA_ID', '=', 'pr.PPA_ID')
            ->select('pr.*', 'p.PPA_DESCRICAO', 'p.PPA_ANO_INICIO', 'p.PPA_ANO_FIM')
            ->orderBy('pr.PROGRAMA_CODIGO')
            ->get();

        return response()->json(['ppas' => $ppas, 'programas' => $programas]);
    } catch (\Throwable $e) {
        return response()->json(['erro' => $e->getMessage()], 500);
    }
});

// ── GET: LOA por ano ─────────────────────────────────────────────
Route::get('/orcamento/loa', function () {
    try {
        $ano = request('ano', date('Y'));

        $itens = DB::table('ORCAMENTO_LOA as l')
            ->join('ORCAMENTO_ACAO as a', 'a.ACAO_ID', '=', 'l.ACAO_ID')
            ->join('ORCAMENTO_PROGRAMA as pr', 'pr.PROGRAMA_ID', '=', 'a.PROGRAMA_ID')
            ->where('l.LOA_ANO', $ano)
            ->select(
                'l.*',
                'a.ACAO_CODIGO',
                'a.ACAO_NOME',
                'a.ACAO_TIPO',
                'pr.PROGRAMA_CODIGO',
                'pr.PROGRAMA_NOME',
                DB::raw('(l.LOA_VALOR_APROVADO + l.LOA_VALOR_ADICIONADO - l.LOA_VALOR_REDUZIDO) as LOA_DOTACAO_ATUAL')
            )
            ->orderBy('pr.PROGRAMA_CODIGO')
            ->orderBy('a.ACAO_CODIGO')
            ->get();

        $totais = [
            'dotacao_inicial' => $itens->sum('LOA_VALOR_APROVADO'),
            'creditos_adicionais' => $itens->sum('LOA_VALOR_ADICIONADO'),
            'reducoes' => $itens->sum('LOA_VALOR_REDUZIDO'),
            'dotacao_atual' => $itens->sum('LOA_DOTACAO_ATUAL'),
        ];

        return response()->json(['ano' => (int) $ano, 'itens' => $itens, 'totais' => $totais]);
    } catch (\Throwable $e) {
        return response()->json(['erro' => $e->getMessage()], 500);
    }
});

// ── POST: criar ação orçamentária ────────────────────────────────
Route::post('/orcamento/acao', function () {
    try {
        $data = request()->validate([
            'programa_id' => 'required|integer',
            'acao_codigo' => 'required|string|max:20',
            'acao_nome' => 'required|string|max:200',
            'acao_tipo' => 'in:ATIVIDADE,PROJETO,OPERACAO_ESPECIAL',
            'acao_valor_previsto' => 'numeric|min:0',
        ]);

        $id = DB::table('ORCAMENTO_ACAO')->insertGetId([
            'PROGRAMA_ID' => $data['programa_id'],
            'ACAO_CODIGO' => strtoupper($data['acao_codigo']),
            'ACAO_NOME' => $data['acao_nome'],
            'ACAO_TIPO' => $data['acao_tipo'] ?? 'ATIVIDADE',
            'ACAO_VALOR_PREVISTO' => $data['acao_valor_previsto'] ?? 0,
            'created_at' => now(),
            'updated_at' => now(),
        ]);

        return response()->json(['ok' => true, 'id' => $id]);
    } catch (\Throwable $e) {
        return response()->json(['erro' => $e->getMessage()], 422);
    }
});

// ── GET: resumo por programa (execução LOA) ─────────────────────
Route::get('/orcamento/execucao', function () {
    try {
        $ano = (int) request('ano', date('Y'));

        $resumo = DB::table('ORCAMENTO_PROGRAMA as pr')
            ->join('ORCAMENTO_ACAO as a', 'a.PROGRAMA_ID', '=', 'pr.PROGRAMA_ID')
            ->join('ORCAMENTO_LOA as l', 'l.ACAO_ID', '=', 'a.ACAO_ID')
            ->leftJoin('EMPENHO as e', function ($j) use ($ano) {
                $j->on('e.LOA_ID', '=', 'l.LOA_ID')
                    ->whereYear('e.EMPENHO_DATA', $ano)
                    ->where('e.EMPENHO_STATUS', '!=', 'ANULADO');
            })
            ->where('l.LOA_ANO', $ano)
            ->select(
                'pr.PROGRAMA_CODIGO',
                'pr.PROGRAMA_NOME',
                DB::raw('SUM(l.LOA_VALOR_APROVADO + l.LOA_VALOR_ADICIONADO - l.LOA_VALOR_REDUZIDO) as dotacao_atual'),
                DB::raw('SUM(e.EMPENHO_VALOR) as empenhado'),
                DB::raw('COUNT(e.EMPENHO_ID) as qtd_empenhos')
            )
            ->groupBy('pr.PROGRAMA_ID', 'pr.PROGRAMA_CODIGO', 'pr.PROGRAMA_NOME')
            ->get();

        return response()->json(['ano' => $ano, 'resumo' => $resumo]);
    } catch (\Throwable $e) {
        return response()->json(['erro' => $e->getMessage()], 500);
    }
});
