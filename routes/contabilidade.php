<?php
// ══════════════════════════════════════════════════════════════════
// CONTABILIDADE PÚBLICA — PCASP (ERP Sprint 3)
// ══════════════════════════════════════════════════════════════════
use Illuminate\Support\Facades\Auth;

// ── GET: plano de contas ─────────────────────────────────────────
Route::get('/pcasp', function () {
    try {
        $contas = DB::table('PCASP_CONTA')
            ->where('CONTA_ATIVA', 1)
            ->orderBy('CONTA_CODIGO')
            ->get();

        // Montar hierarquia (nested set simplificado)
        $contasIndexed = $contas->keyBy('CONTA_ID');
        return response()->json(['contas' => $contas, 'total' => $contas->count()]);
    } catch (\Throwable $e) {
        return response()->json(['erro' => $e->getMessage()], 500);
    }
});

// ── POST: lançamento contábil ────────────────────────────────────
Route::post('/lancamentos', function () {
    try {
        $user = Auth::user();
        $data = request()->validate([
            'lancamento_data' => 'required|date',
            'lancamento_historico' => 'required|string',
            'lancamento_valor' => 'required|numeric|min:0.01',
            'conta_debito_id' => 'required|integer',
            'conta_credito_id' => 'required|integer',
            'origem_tipo' => 'nullable|string|max:30',
            'origem_id' => 'nullable|integer',
        ]);

        $dt = new \DateTime($data['lancamento_data']);
        $id = DB::table('LANCAMENTO_CONTABIL')->insertGetId([
            'LANCAMENTO_DATA' => $data['lancamento_data'],
            'LANCAMENTO_ANO' => (int) $dt->format('Y'),
            'LANCAMENTO_MES' => (int) $dt->format('n'),
            'LANCAMENTO_HISTORICO' => $data['lancamento_historico'],
            'LANCAMENTO_VALOR' => $data['lancamento_valor'],
            'CONTA_DEBITO_ID' => $data['conta_debito_id'],
            'CONTA_CREDITO_ID' => $data['conta_credito_id'],
            'ORIGEM_TIPO' => $data['origem_tipo'] ?? null,
            'ORIGEM_ID' => $data['origem_id'] ?? null,
            'USUARIO_ID' => $user?->USUARIO_ID,
            'created_at' => now(),
            'updated_at' => now(),
        ]);

        return response()->json(['ok' => true, 'id' => $id]);
    } catch (\Throwable $e) {
        return response()->json(['erro' => $e->getMessage()], 422);
    }
});

// ── GET: balancete mensal ─────────────────────────────────────────
Route::get('/balancete', function () {
    try {
        $mes = (int) request('mes', date('n'));
        $ano = (int) request('ano', date('Y'));

        // Saldos por conta (resumo)
        $debitos = DB::table('LANCAMENTO_CONTABIL as l')
            ->join('PCASP_CONTA as c', 'c.CONTA_ID', '=', 'l.CONTA_DEBITO_ID')
            ->where('l.LANCAMENTO_ANO', $ano)
            ->where('l.LANCAMENTO_MES', '<=', $mes)
            ->select(
                'c.CONTA_CODIGO',
                'c.CONTA_NOME',
                'c.CONTA_NATUREZA',
                'c.CONTA_GRUPO',
                DB::raw('SUM(l.LANCAMENTO_VALOR) as total_debito')
            )
            ->groupBy('c.CONTA_ID', 'c.CONTA_CODIGO', 'c.CONTA_NOME', 'c.CONTA_NATUREZA', 'c.CONTA_GRUPO')
            ->get()->keyBy('CONTA_CODIGO');

        $creditos = DB::table('LANCAMENTO_CONTABIL as l')
            ->join('PCASP_CONTA as c', 'c.CONTA_ID', '=', 'l.CONTA_CREDITO_ID')
            ->where('l.LANCAMENTO_ANO', $ano)
            ->where('l.LANCAMENTO_MES', '<=', $mes)
            ->select(
                'c.CONTA_CODIGO',
                'c.CONTA_NOME',
                'c.CONTA_NATUREZA',
                'c.CONTA_GRUPO',
                DB::raw('SUM(l.LANCAMENTO_VALOR) as total_credito')
            )
            ->groupBy('c.CONTA_ID', 'c.CONTA_CODIGO', 'c.CONTA_NOME', 'c.CONTA_NATUREZA', 'c.CONTA_GRUPO')
            ->get()->keyBy('CONTA_CODIGO');

        // Mesclar débitos e créditos
        $codigos = array_unique(array_merge($debitos->keys()->toArray(), $creditos->keys()->toArray()));
        sort($codigos);

        $balancete = collect($codigos)->map(function ($cod) use ($debitos, $creditos) {
            $d = $debitos[$cod] ?? null;
            $c = $creditos[$cod] ?? null;
            $base = $d ?? $c;
            $td = $d?->total_debito ?? 0;
            $tc = $c?->total_credito ?? 0;

            return [
                'conta_codigo' => $cod,
                'conta_nome' => $base->CONTA_NOME,
                'conta_natureza' => $base->CONTA_NATUREZA,
                'conta_grupo' => $base->CONTA_GRUPO,
                'total_debito' => $td,
                'total_credito' => $tc,
                'saldo' => $base->CONTA_NATUREZA === 'DEVEDORA' ? ($td - $tc) : ($tc - $td),
            ];
        });

        return response()->json([
            'mes' => $mes,
            'ano' => $ano,
            'balancete' => $balancete,
            'total_debitos' => $balancete->sum('total_debito'),
            'total_creditos' => $balancete->sum('total_credito'),
        ]);
    } catch (\Throwable $e) {
        return response()->json(['erro' => $e->getMessage()], 500);
    }
});
