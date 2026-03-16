<?php
// ══════════════════════════════════════════════════════════════════
// TESOURARIA — Contas Bancárias + Fluxo de Caixa (ERP Sprint 4)
// ══════════════════════════════════════════════════════════════════

// ── GET: contas bancárias ─────────────────────────────────────────
Route::get('/contas-bancarias', function () {
    try {
        $contas = DB::table('CONTA_BANCARIA')
            ->orderBy('CONTA_DESCRICAO')
            ->get();

        // Calcular saldo atual de cada conta
        $contas = $contas->map(function ($c) {
            $creditos = DB::table('MOVIMENTACAO_BANCARIA')
                ->where('CONTA_ID', $c->CONTA_ID)
                ->where('MOV_TIPO', 'CREDITO')
                ->where('MOV_STATUS', '!=', 'CANCELADO')
                ->sum('MOV_VALOR');

            $debitos = DB::table('MOVIMENTACAO_BANCARIA')
                ->where('CONTA_ID', $c->CONTA_ID)
                ->where('MOV_TIPO', 'DEBITO')
                ->where('MOV_STATUS', '!=', 'CANCELADO')
                ->sum('MOV_VALOR');

            $c->saldo_atual = $c->CONTA_SALDO_INICIAL + $creditos - $debitos;
            return $c;
        });

        return response()->json([
            'contas' => $contas,
            'saldo_total' => $contas->sum('saldo_atual'),
        ]);
    } catch (\Throwable $e) {
        return response()->json(['erro' => $e->getMessage()], 500);
    }
});

// ── GET: fluxo de caixa (movimentações de um período) ────────────
Route::get('/fluxo-caixa', function () {
    try {
        $contaId = request('conta_id');
        $inicio = request('inicio', date('Y-m-01'));
        $fim = request('fim', date('Y-m-t'));

        $q = DB::table('MOVIMENTACAO_BANCARIA as m')
            ->join('CONTA_BANCARIA as c', 'c.CONTA_ID', '=', 'm.CONTA_ID')
            ->whereBetween('m.MOV_DATA', [$inicio, $fim])
            ->where('m.MOV_STATUS', '!=', 'CANCELADO');

        if ($contaId)
            $q->where('m.CONTA_ID', $contaId);

        $movs = $q->select('m.*', 'c.CONTA_DESCRICAO')
            ->orderBy('m.MOV_DATA')
            ->get();

        return response()->json([
            'periodo' => ['inicio' => $inicio, 'fim' => $fim],
            'total_creditos' => $movs->where('MOV_TIPO', 'CREDITO')->sum('MOV_VALOR'),
            'total_debitos' => $movs->where('MOV_TIPO', 'DEBITO')->sum('MOV_VALOR'),
            'movimentacoes' => $movs,
        ]);
    } catch (\Throwable $e) {
        return response()->json(['erro' => $e->getMessage()], 500);
    }
});

// ── POST: conciliar movimentação ──────────────────────────────────
Route::post('/conciliar', function () {
    try {
        $ids = request('ids', []);
        if (empty($ids))
            return response()->json(['erro' => 'ids é obrigatório.'], 422);

        DB::table('MOVIMENTACAO_BANCARIA')
            ->whereIn('MOV_ID', $ids)
            ->update(['MOV_STATUS' => 'CONCILIADO', 'updated_at' => now()]);

        return response()->json(['ok' => true, 'conciliados' => count($ids)]);
    } catch (\Throwable $e) {
        return response()->json(['erro' => $e->getMessage()], 500);
    }
});
