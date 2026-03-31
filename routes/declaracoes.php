<?php
// ─────────────────────────────────────────────────────────────────────────
// DECLARACOES / REQUERIMENTOS — GET /declaracoes, POST /declaracoes
// Tabela: DECLARACAO (migration Sprint 2 - 04/03/2026)
// Herda prefix api/v3 + middleware web+auth do grupo principal em web.php
// Corrigido 30/03/2026: versao anterior usava PEDIDO_DOCUMENTO (sem migration)
// ─────────────────────────────────────────────────────────────────────────

// GET /api/v3/declaracoes — lista declaracoes/requerimentos do servidor logado
Route::get('/declaracoes', function () {
    try {
        $user = \Illuminate\Support\Facades\Auth::user();
        $func = \App\Models\Funcionario::where('USUARIO_ID', $user->USUARIO_ID ?? 0)->first();
        if (!$func)
            return response()->json(['fallback' => true, 'pedidos' => []]);

        $rows = \Illuminate\Support\Facades\DB::table('DECLARACAO')
            ->where('FUNCIONARIO_ID', $func->FUNCIONARIO_ID)
            ->orderByDesc('DECLARACAO_DT_SOLICITACAO')
            ->get()
            ->map(fn($r) => [
                'id'          => $r->DECLARACAO_ID,
                'nome'        => $r->DECLARACAO_TIPO ?? '—',  // alias para compatibilidade com a view
                'tipo'        => $r->DECLARACAO_TIPO ?? '—',
                'status'      => $r->DECLARACAO_STATUS ?? 'pendente',
                'data'        => $r->DECLARACAO_DT_SOLICITACAO, // alias para compatibilidade
                'solicitacao' => $r->DECLARACAO_DT_SOLICITACAO,
                'conclusao'   => $r->DECLARACAO_DT_CONCLUSAO ?? null,
                'observacao'  => $r->DECLARACAO_OBS ?? null,
                'arquivo'     => $r->DECLARACAO_ARQUIVO ?? null,
            ]);

        return response()->json(['fallback' => false, 'pedidos' => $rows]);
    } catch (\Throwable $e) {
        \Illuminate\Support\Facades\Log::error('Declaracoes GET: ' . $e->getMessage());
        return response()->json(['fallback' => true, 'pedidos' => [], 'erro' => $e->getMessage()]);
    }
});

// POST /api/v3/declaracoes — servidor solicita novo documento
Route::post('/declaracoes', function (\Illuminate\Http\Request $request) {
    try {
        $user = \Illuminate\Support\Facades\Auth::user();
        $func = \App\Models\Funcionario::where('USUARIO_ID', $user->USUARIO_ID ?? 0)->first();

        $id = \Illuminate\Support\Facades\DB::table('DECLARACAO')->insertGetId([
            'FUNCIONARIO_ID'           => $func?->FUNCIONARIO_ID ?? null,
            'DECLARACAO_TIPO'          => $request->tipo,
            'DECLARACAO_STATUS'        => 'pendente',
            'DECLARACAO_OBS'           => $request->observacao ?? null,
            'DECLARACAO_DT_SOLICITACAO'=> now()->toDateString(),
        ]);

        return response()->json(['id' => $id, 'status' => 'pendente'], 201);
    } catch (\Throwable $e) {
        \Illuminate\Support\Facades\Log::error('Declaracoes POST: ' . $e->getMessage());
        return response()->json(['erro' => $e->getMessage()], 500);
    }
});

// GET /api/v3/declaracoes/admin — RH lista todas as declaracoes pendentes
Route::get('/declaracoes/admin', function (\Illuminate\Http\Request $request) {
    try {
        $query = \Illuminate\Support\Facades\DB::table('DECLARACAO as d')
            ->leftJoin('FUNCIONARIO as f', 'f.FUNCIONARIO_ID', '=', 'd.FUNCIONARIO_ID')
            ->leftJoin('PESSOA as p', 'p.PESSOA_ID', '=', 'f.PESSOA_ID')
            ->select(
                'd.DECLARACAO_ID',
                'd.DECLARACAO_TIPO',
                'd.DECLARACAO_STATUS',
                'd.DECLARACAO_DT_SOLICITACAO',
                'd.DECLARACAO_DT_CONCLUSAO',
                'd.DECLARACAO_OBS',
                'd.DECLARACAO_ARQUIVO',
                'p.PESSOA_NOME as servidor_nome',
                'f.FUNCIONARIO_MATRICULA as matricula'
            )
            ->orderByDesc('d.DECLARACAO_DT_SOLICITACAO');

        if ($request->filled('status'))
            $query->where('d.DECLARACAO_STATUS', $request->status);

        if ($request->filled('q'))
            $query->where('p.PESSOA_NOME', 'like', '%' . $request->q . '%');

        $rows = $query->paginate(30);
        return response()->json($rows);
    } catch (\Throwable $e) {
        return response()->json(['data' => [], 'erro' => $e->getMessage()], 500);
    }
});

// PATCH /api/v3/declaracoes/{id} — RH atualiza status de uma declaracao
Route::patch('/declaracoes/{id}', function (\Illuminate\Http\Request $request, $id) {
    try {
        $update = array_filter([
            'DECLARACAO_STATUS'       => $request->status,
            'DECLARACAO_DT_CONCLUSAO' => $request->dt_conclusao ?? null,
            'DECLARACAO_ARQUIVO'      => $request->arquivo ?? null,
            'DECLARACAO_OBS'          => $request->observacao ?? null,
        ], fn($v) => $v !== null);

        \Illuminate\Support\Facades\DB::table('DECLARACAO')
            ->where('DECLARACAO_ID', $id)
            ->update($update);

        return response()->json(['message' => 'Declaracao atualizada.']);
    } catch (\Throwable $e) {
        return response()->json(['erro' => $e->getMessage()], 500);
    }
});
