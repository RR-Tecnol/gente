<?php
// ══════════════════════════════════════════════════════════════════
// RECEITA MUNICIPAL (ERP Sprint 5)
// ══════════════════════════════════════════════════════════════════
use Illuminate\Support\Facades\Auth;

// ── GET: lançamentos de receita ──────────────────────────────────
Route::get('/receita', function () {
    try {
        $ano = (int) request('ano', date('Y'));
        $mes = request('mes'); // opcional

        $q = DB::table('RECEITA_LANCAMENTO')
            ->where('RECEITA_ANO', $ano);

        if ($mes)
            $q->where('RECEITA_MES', (int) $mes);
        if (request('tipo'))
            $q->where('RECEITA_TIPO', request('tipo'));

        $receitas = $q->orderBy('RECEITA_DATA', 'desc')->get();

        $resumo = [
            'previsto' => $receitas->sum('RECEITA_VALOR_PREVISTO'),
            'arrecadado' => $receitas->sum('RECEITA_VALOR_ARRECADADO'),
            'percentual' => $receitas->sum('RECEITA_VALOR_PREVISTO') > 0
                ? round($receitas->sum('RECEITA_VALOR_ARRECADADO') / $receitas->sum('RECEITA_VALOR_PREVISTO') * 100, 2)
                : 0,
        ];

        return response()->json(['ano' => $ano, 'receitas' => $receitas, 'resumo' => $resumo]);
    } catch (\Throwable $e) {
        return response()->json(['erro' => $e->getMessage()], 500);
    }
});

// ── POST: lançar receita ─────────────────────────────────────────
Route::post('/receita', function () {
    try {
        $user = Auth::user();
        $data = request()->validate([
            'receita_data' => 'required|date',
            'receita_codigo_natureza' => 'required|string|max:30',
            'receita_descricao' => 'required|string|max:200',
            'receita_tipo' => 'required|in:TRIBUTARIA,CONTRIBUICOES,PATRIMONIAL,TRANSFERENCIAS_CORRENTES,OUTRAS_CORRENTES,CAPITAL',
            'receita_valor_previsto' => 'numeric|min:0',
            'receita_valor_arrecadado' => 'numeric|min:0',
        ]);

        $dt = new \DateTime($data['receita_data']);
        $id = DB::table('RECEITA_LANCAMENTO')->insertGetId([
            'RECEITA_DATA' => $data['receita_data'],
            'RECEITA_ANO' => (int) $dt->format('Y'),
            'RECEITA_MES' => (int) $dt->format('n'),
            'RECEITA_CODIGO_NATUREZA' => $data['receita_codigo_natureza'],
            'RECEITA_DESCRICAO' => $data['receita_descricao'],
            'RECEITA_TIPO' => $data['receita_tipo'],
            'RECEITA_VALOR_PREVISTO' => $data['receita_valor_previsto'] ?? 0,
            'RECEITA_VALOR_ARRECADADO' => $data['receita_valor_arrecadado'] ?? 0,
            'RECEITA_FONTE' => request('receita_fonte'),
            'CONTA_ID' => request('conta_id'),
            'USUARIO_ID' => $user?->USUARIO_ID,
            'created_at' => now(),
            'updated_at' => now(),
        ]);

        return response()->json(['ok' => true, 'id' => $id]);
    } catch (\Throwable $e) {
        return response()->json(['erro' => $e->getMessage()], 422);
    }
});

// ── GET: receita por tipo (classificação) ────────────────────────
Route::get('/receita/por-tipo', function () {
    try {
        $ano = (int) request('ano', date('Y'));

        $porTipo = DB::table('RECEITA_LANCAMENTO')
            ->where('RECEITA_ANO', $ano)
            ->select(
                'RECEITA_TIPO',
                DB::raw('SUM(RECEITA_VALOR_PREVISTO)   as previsto'),
                DB::raw('SUM(RECEITA_VALOR_ARRECADADO) as arrecadado'),
                DB::raw('COUNT(*) as qtd')
            )
            ->groupBy('RECEITA_TIPO')
            ->orderBy('arrecadado', 'desc')
            ->get();

        return response()->json(['ano' => $ano, 'por_tipo' => $porTipo]);
    } catch (\Throwable $e) {
        return response()->json(['erro' => $e->getMessage()], 500);
    }
});

// ── GET: dívida ativa ────────────────────────────────────────────
Route::get('/receita/divida-ativa', function () {
    try {
        $q = DB::table('RECEITA_DIVIDA_ATIVA');
        if (request('status'))
            $q->where('DA_STATUS', request('status'));
        if (request('devedor'))
            $q->where('DA_DEVEDOR', 'like', '%' . request('devedor') . '%');

        $dividas = $q->orderBy('DA_DATA_INSCRICAO', 'desc')->limit(100)->get();

        $totais = [
            'ativo' => DB::table('RECEITA_DIVIDA_ATIVA')->where('DA_STATUS', 'ATIVA')->count(),
            'valor_total' => DB::table('RECEITA_DIVIDA_ATIVA')->where('DA_STATUS', '!=', 'QUITADA')->sum(DB::raw('DA_VALOR_PRINCIPAL + DA_MULTA + DA_JUROS + DA_HONORARIO')),
        ];

        return response()->json(['dividas' => $dividas, 'totais' => $totais]);
    } catch (\Throwable $e) {
        return response()->json(['erro' => $e->getMessage()], 500);
    }
});
