<?php
// ══════════════════════════════════════════════════════════════════
// EXECUÇÃO DA DESPESA — Empenho → Liquidação → Pagamento (ERP Sprint 2)
// ══════════════════════════════════════════════════════════════════
use Illuminate\Support\Facades\Auth;

// ── GET: lista de empenhos com filtros ───────────────────────────
Route::get('/empenho', function () {
    try {
        $q = DB::table('EMPENHO as e')
            ->join('ORCAMENTO_LOA as l', 'l.LOA_ID', '=', 'e.LOA_ID')
            ->join('ORCAMENTO_ACAO as a', 'a.ACAO_ID', '=', 'l.ACAO_ID');

        if (request('ano'))
            $q->whereYear('e.EMPENHO_DATA', request('ano'));
        if (request('status'))
            $q->where('e.EMPENHO_STATUS', request('status'));
        if (request('credor'))
            $q->where('e.EMPENHO_CREDOR', 'like', '%' . request('credor') . '%');

        $empenhos = $q->select(
            'e.*',
            'a.ACAO_NOME',
            'a.ACAO_CODIGO',
            'l.LOA_NATUREZA_DESPESA'
        )->orderBy('e.EMPENHO_DATA', 'desc')->limit(200)->get();

        $stats = [
            'total_emitido' => DB::table('EMPENHO')->where('EMPENHO_STATUS', 'EMITIDO')->whereYear('EMPENHO_DATA', date('Y'))->sum('EMPENHO_VALOR'),
            'total_liquidado' => DB::table('EMPENHO')->where('EMPENHO_STATUS', 'LIQUIDADO')->whereYear('EMPENHO_DATA', date('Y'))->sum('EMPENHO_VALOR'),
            'total_pago' => DB::table('EMPENHO')->where('EMPENHO_STATUS', 'PAGO')->whereYear('EMPENHO_DATA', date('Y'))->sum('EMPENHO_VALOR'),
        ];

        return response()->json(['empenhos' => $empenhos, 'stats' => $stats]);
    } catch (\Throwable $e) {
        return response()->json(['erro' => $e->getMessage()], 500);
    }
});

// ── POST: emitir empenho ─────────────────────────────────────────
Route::post('/empenho', function () {
    try {
        $user = Auth::user();
        $data = request()->validate([
            'loa_id' => 'required|integer',
            'empenho_numero' => 'required|string|max:30',
            'empenho_data' => 'required|date',
            'empenho_credor' => 'required|string|max:150',
            'empenho_cpfcnpj' => 'nullable|string|max:18',
            'empenho_historico' => 'nullable|string',
            'empenho_valor' => 'required|numeric|min:0.01',
            'empenho_tipo' => 'in:ORDINARIO,ESTIMATIVO,GLOBAL',
        ]);

        $id = DB::table('EMPENHO')->insertGetId([
            'LOA_ID' => $data['loa_id'],
            'EMPENHO_NUMERO' => strtoupper($data['empenho_numero']),
            'EMPENHO_DATA' => $data['empenho_data'],
            'EMPENHO_CREDOR' => $data['empenho_credor'],
            'EMPENHO_CPFCNPJ' => $data['empenho_cpfcnpj'] ?? null,
            'EMPENHO_HISTORICO' => $data['empenho_historico'] ?? null,
            'EMPENHO_VALOR' => $data['empenho_valor'],
            'EMPENHO_TIPO' => $data['empenho_tipo'] ?? 'ORDINARIO',
            'EMPENHO_STATUS' => 'EMITIDO',
            'USUARIO_ID' => $user?->USUARIO_ID,
            'created_at' => now(),
            'updated_at' => now(),
        ]);

        return response()->json(['ok' => true, 'id' => $id]);
    } catch (\Throwable $e) {
        return response()->json(['erro' => $e->getMessage()], 422);
    }
});

// ── POST: liquidar empenho ───────────────────────────────────────
Route::post('/empenho/{id}/liquidar', function (int $id) {
    try {
        $user = Auth::user();
        $emp = DB::table('EMPENHO')->where('EMPENHO_ID', $id)->first();
        if (!$emp)
            return response()->json(['erro' => 'Empenho não encontrado.'], 404);
        if ($emp->EMPENHO_STATUS !== 'EMITIDO')
            return response()->json(['erro' => 'Empenho já está ' . $emp->EMPENHO_STATUS . '.'], 422);

        $liqId = DB::table('LIQUIDACAO')->insertGetId([
            'EMPENHO_ID' => $id,
            'LIQUIDACAO_DATA' => request('liquidacao_data', now()->format('Y-m-d')),
            'LIQUIDACAO_VALOR' => request('liquidacao_valor', $emp->EMPENHO_VALOR),
            'LIQUIDACAO_HISTORICO' => request('historico'),
            'LIQUIDACAO_NF' => request('nf'),
            'USUARIO_ID' => $user?->USUARIO_ID,
            'created_at' => now(),
            'updated_at' => now(),
        ]);

        DB::table('EMPENHO')->where('EMPENHO_ID', $id)->update(['EMPENHO_STATUS' => 'LIQUIDADO', 'updated_at' => now()]);

        return response()->json(['ok' => true, 'liquidacao_id' => $liqId]);
    } catch (\Throwable $e) {
        return response()->json(['erro' => $e->getMessage()], 500);
    }
});

// ── POST: pagar liquidação ───────────────────────────────────────
Route::post('/liquidacao/{id}/pagar', function (int $id) {
    try {
        $user = Auth::user();
        $liq = DB::table('LIQUIDACAO')->where('LIQUIDACAO_ID', $id)->first();
        if (!$liq)
            return response()->json(['erro' => 'Liquidação não encontrada.'], 404);

        $pagId = DB::table('PAGAMENTO_DESPESA')->insertGetId([
            'LIQUIDACAO_ID' => $id,
            'PAGAMENTO_DATA' => request('pagamento_data', now()->format('Y-m-d')),
            'PAGAMENTO_VALOR' => request('pagamento_valor', $liq->LIQUIDACAO_VALOR),
            'PAGAMENTO_FORMA' => request('forma', 'TRANSFERENCIA'),
            'PAGAMENTO_BANCO' => request('banco'),
            'PAGAMENTO_CONTA' => request('conta'),
            'PAGAMENTO_HISTORICO' => request('historico'),
            'USUARIO_ID' => $user?->USUARIO_ID,
            'created_at' => now(),
            'updated_at' => now(),
        ]);

        DB::table('EMPENHO')
            ->where('EMPENHO_ID', $liq->EMPENHO_ID)
            ->update(['EMPENHO_STATUS' => 'PAGO', 'updated_at' => now()]);

        return response()->json(['ok' => true, 'pagamento_id' => $pagId]);
    } catch (\Throwable $e) {
        return response()->json(['erro' => $e->getMessage()], 500);
    }
});
