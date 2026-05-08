<?php
// ══════════════════════════════════════════════════════════════════
// TESOURARIA — Contas Bancárias + Fluxo de Caixa (ERP Sprint 4)
// Rotas alinhadas a gente-v3/.../TesourariaView.vue (contas, fluxo, movimentos, etc.)
// ══════════════════════════════════════════════════════════════════

use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;
use Illuminate\Support\Facades\Auth;

if (!function_exists('_tesou_conta_tabela')) {
    function _tesou_conta_tabela(): bool
    {
        return Schema::hasTable('CONTA_BANCARIA');
    }
}

/** Mapeia linha de CONTA_BANCARIA para o shape esperado pelo Vue (BANCO_NOME, etc.) */
if (!function_exists('_tesou_mapear_conta')) {
    function _tesou_mapear_conta(object $c, float $saldoAtual): object
    {
        $c->BANCO_NOME = $c->CONTA_BANCO_NOME ?? '';
        $c->BANCO_CODIGO = $c->CONTA_BANCO ?? '';
        $c->AGENCIA = $c->CONTA_AGENCIA ?? '';
        $c->NUMERO_CONTA = $c->CONTA_NUMERO ?? '';
        $c->DIGITO = '';
        $c->TIPO_CONTA = $c->CONTA_TIPO ?? 'CORRENTE';
        $c->SALDO_ATUAL = $saldoAtual;
        $c->STATUS = !empty($c->CONTA_ATIVA) ? 'ATIVA' : 'INATIVA';
        return $c;
    }
}

// ── GET: contas bancárias (legado) ──────────────────────────────
Route::get('/contas-bancarias', function () {
    if (!_tesou_conta_tabela()) {
        return response()->json(['contas' => [], 'saldo_total' => 0]);
    }
    try {
        $contas = DB::table('CONTA_BANCARIA')
            ->orderBy('CONTA_DESCRICAO')
            ->get();

        $contas = $contas->map(function ($c) {
            $creditos = (float) DB::table('MOVIMENTACAO_BANCARIA')
                ->where('CONTA_ID', $c->CONTA_ID)
                ->where('MOV_TIPO', 'CREDITO')
                ->where('MOV_STATUS', '!=', 'CANCELADO')
                ->sum('MOV_VALOR');

            $debitos = (float) DB::table('MOVIMENTACAO_BANCARIA')
                ->where('CONTA_ID', $c->CONTA_ID)
                ->where('MOV_TIPO', 'DEBITO')
                ->where('MOV_STATUS', '!=', 'CANCELADO')
                ->sum('MOV_VALOR');

            $saldo = (float) $c->CONTA_SALDO_INICIAL + $creditos - $debitos;
            $c->saldo_atual = $saldo;
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

// ── GET /tesouraria/contas — resumo + contas mapeadas para o Vue ─
Route::get('/tesouraria/contas', function () {
    if (!_tesou_conta_tabela()) {
        return response()->json(['contas' => [], 'resumo' => ['saldo_total' => 0, 'entradas_hoje' => 0, 'saidas_hoje' => 0]]);
    }
    try {
        $hoje = now()->toDateString();
        $rows = DB::table('CONTA_BANCARIA')->orderBy('CONTA_DESCRICAO')->get();
        $eH = 0.0;
        $sH = 0.0;
        $mapped = $rows->map(function ($c) use ($hoje, &$eH, &$sH) {
            $creditos = (float) DB::table('MOVIMENTACAO_BANCARIA')
                ->where('CONTA_ID', $c->CONTA_ID)
                ->where('MOV_TIPO', 'CREDITO')
                ->where('MOV_STATUS', '!=', 'CANCELADO')
                ->sum('MOV_VALOR');
            $debitos = (float) DB::table('MOVIMENTACAO_BANCARIA')
                ->where('CONTA_ID', $c->CONTA_ID)
                ->where('MOV_TIPO', 'DEBITO')
                ->where('MOV_STATUS', '!=', 'CANCELADO')
                ->sum('MOV_VALOR');
            $saldo = (float) $c->CONTA_SALDO_INICIAL + $creditos - $debitos;
            $entHoje = (float) DB::table('MOVIMENTACAO_BANCARIA')
                ->where('CONTA_ID', $c->CONTA_ID)
                ->where('MOV_TIPO', 'CREDITO')
                ->where('MOV_DATA', $hoje)
                ->where('MOV_STATUS', '!=', 'CANCELADO')
                ->sum('MOV_VALOR');
            $saiHoje = (float) DB::table('MOVIMENTACAO_BANCARIA')
                ->where('CONTA_ID', $c->CONTA_ID)
                ->where('MOV_TIPO', 'DEBITO')
                ->where('MOV_DATA', $hoje)
                ->where('MOV_STATUS', '!=', 'CANCELADO')
                ->sum('MOV_VALOR');
            $eH += $entHoje;
            $sH += $saiHoje;
            return _tesou_mapear_conta($c, $saldo);
        });

        $resumo = [
            'saldo_total' => $mapped->sum('SALDO_ATUAL'),
            'entradas_hoje' => $eH,
            'saidas_hoje' => $sH,
        ];

        return response()->json(['contas' => $mapped->values(), 'resumo' => $resumo, 'saldo_total' => $resumo['saldo_total']]);
    } catch (\Throwable $e) {
        return response()->json(['contas' => [], 'resumo' => ['saldo_total' => 0, 'entradas_hoje' => 0, 'saidas_hoje' => 0], 'erro' => $e->getMessage()], 200);
    }
});

// ── GET /tesouraria/fluxo?dias=7|30|90 ───────────────────────────
Route::get('/tesouraria/fluxo', function (Request $request) {
    if (!Schema::hasTable('MOVIMENTACAO_BANCARIA')) {
        return response()->json(['total_entradas' => 0, 'total_saidas' => 0, 'saldo_periodo' => 0, 'dias' => []]);
    }
    try {
        $dias = max(1, min(366, (int) $request->input('dias', 30)));
        $fim = now()->toDateString();
        $inicio = now()->subDays($dias - 1)->toDateString();

        $movs = DB::table('MOVIMENTACAO_BANCARIA as m')
            ->whereBetween('m.MOV_DATA', [$inicio, $fim])
            ->where('m.MOV_STATUS', '!=', 'CANCELADO')
            ->get();

        $entradas = (float) $movs->where('MOV_TIPO', 'CREDITO')->sum('MOV_VALOR');
        $saidas = (float) $movs->where('MOV_TIPO', 'DEBITO')->sum('MOV_VALOR');

        $porDia = [];
        foreach ($movs as $m) {
            $d = $m->MOV_DATA;
            if (!isset($porDia[$d])) {
                $porDia[$d] = ['data' => $d, 'entradas' => 0.0, 'saidas' => 0.0, 'saldo' => 0.0];
            }
            if ($m->MOV_TIPO === 'CREDITO') {
                $porDia[$d]['entradas'] += (float) $m->MOV_VALOR;
            } else {
                $porDia[$d]['saidas'] += (float) $m->MOV_VALOR;
            }
        }
        ksort($porDia);
        $acum = 0.0;
        $diasArr = [];
        foreach ($porDia as &$row) {
            $row['saldo'] = $acum = $acum + $row['entradas'] - $row['saidas'];
            $diasArr[] = $row;
        }
        unset($row);

        return response()->json([
            'total_entradas' => $entradas,
            'total_saidas' => $saidas,
            'saldo_periodo' => $entradas - $saidas,
            'periodo' => ['inicio' => $inicio, 'fim' => $fim],
            'dias' => $diasArr,
        ]);
    } catch (\Throwable $e) {
        return response()->json(['total_entradas' => 0, 'total_saidas' => 0, 'saldo_periodo' => 0, 'dias' => [], 'erro' => $e->getMessage()]);
    }
});

// ── GET /tesouraria/movimentos?conta_id= ────────────────────────
Route::get('/tesouraria/movimentos', function (Request $request) {
    if (!Schema::hasTable('MOVIMENTACAO_BANCARIA')) {
        return response()->json(['movimentos' => []]);
    }
    try {
        $contaId = $request->input('conta_id');
        $q = DB::table('MOVIMENTACAO_BANCARIA as m')
            ->join('CONTA_BANCARIA as c', 'c.CONTA_ID', '=', 'm.CONTA_ID')
            ->orderByDesc('m.MOV_DATA')
            ->orderByDesc('m.MOV_ID')
            ->select('m.*', 'c.CONTA_BANCO_NOME as banco_nome');
        if ($contaId) {
            $q->where('m.CONTA_ID', (int) $contaId);
        }
        $raw = $q->limit(500)->get();
        $movs = $raw->map(function ($m) {
            $tipoC = $m->MOV_TIPO === 'CREDITO' ? 'C' : 'D';
            return [
                'MOVIM_ID' => (int) $m->MOV_ID,
                'MOVIM_DATA' => (string) $m->MOV_DATA,
                'MOVIM_HISTORICO' => $m->MOV_HISTORICO ?? '',
                'MOVIM_TIPO' => $tipoC,
                'MOVIM_VALOR' => (float) $m->MOV_VALOR,
                'banco_nome' => $m->banco_nome ?? '',
                'CONTA_ID' => (int) $m->CONTA_ID,
            ];
        });
        return response()->json(['movimentos' => $movs]);
    } catch (\Throwable $e) {
        return response()->json(['movimentos' => []]);
    }
});

// ── POST /tesouraria/conta — cadastro mínimo ────────────────────
Route::post('/tesouraria/conta', function (Request $request) {
    if (!Schema::hasTable('CONTA_BANCARIA')) {
        return response()->json(['erro' => 'Tabela CONTA_BANCARIA indisponível.'], 503);
    }
    try {
        $id = DB::table('CONTA_BANCARIA')->insertGetId([
            'CONTA_DESCRICAO' => $request->input('BANCO_NOME', 'Conta') ?: 'Conta',
            'CONTA_BANCO' => substr((string) $request->input('BANCO_CODIGO', ''), 0, 3),
            'CONTA_BANCO_NOME' => $request->input('BANCO_NOME', ''),
            'CONTA_AGENCIA' => $request->input('AGENCIA', ''),
            'CONTA_NUMERO' => trim((string) $request->input('NUMERO_CONTA', ''), '- '),
            'CONTA_TIPO' => $request->input('TIPO_CONTA', 'CORRENTE') ?: 'CORRENTE',
            'CONTA_SALDO_INICIAL' => 0,
            'CONTA_SALDO_DATA' => null,
            'CONTA_ATIVA' => true,
            'created_at' => now(),
            'updated_at' => now(),
        ]);
        return response()->json(['ok' => true, 'CONTA_ID' => $id], 201);
    } catch (\Throwable $e) {
        return response()->json(['erro' => $e->getMessage()], 422);
    }
});

// ── POST /tesouraria/movimentacao — lançamento (C/D) ────────────
Route::post('/tesouraria/movimentacao', function (Request $request) {
    if (!Schema::hasTable('MOVIMENTACAO_BANCARIA')) {
        return response()->json(['erro' => 'Tabela MOVIMENTACAO_BANCARIA indisponível.'], 503);
    }
    try {
        $tipo = strtoupper((string) $request->input('MOVIM_TIPO', 'C'));
        $movTipo = $tipo === 'D' ? 'DEBITO' : 'CREDITO';
        $id = DB::table('MOVIMENTACAO_BANCARIA')->insertGetId([
            'CONTA_ID' => (int) $request->input('CONTA_ID'),
            'MOV_DATA' => $request->input('MOVIM_DATA', now()->toDateString()),
            'MOV_TIPO' => $movTipo,
            'MOV_VALOR' => (float) $request->input('MOVIM_VALOR', 0),
            'MOV_HISTORICO' => (string) $request->input('MOVIM_HISTORICO', 'Lançamento'),
            'MOV_ORIGEM' => 'MANUAL',
            'MOV_ORIGEM_ID' => null,
            'MOV_STATUS' => 'PENDENTE',
            'USUARIO_ID' => optional(Auth::user())->USUARIO_ID,
            'created_at' => now(),
            'updated_at' => now(),
        ]);
        return response()->json(['ok' => true, 'MOV_ID' => $id], 201);
    } catch (\Throwable $e) {
        return response()->json(['erro' => $e->getMessage()], 422);
    }
});

// ── GET: fluxo de caixa (legado) ─────────────────────────────────
Route::get('/fluxo-caixa', function () {
    if (!Schema::hasTable('MOVIMENTACAO_BANCARIA')) {
        return response()->json(['periodo' => [], 'total_creditos' => 0, 'total_debitos' => 0, 'movimentacoes' => []]);
    }
    try {
        $contaId = request('conta_id');
        $inicio = request('inicio', date('Y-m-01'));
        $fim = request('fim', date('Y-m-t'));

        $q = DB::table('MOVIMENTACAO_BANCARIA as m')
            ->join('CONTA_BANCARIA as c', 'c.CONTA_ID', '=', 'm.CONTA_ID')
            ->whereBetween('m.MOV_DATA', [$inicio, $fim])
            ->where('m.MOV_STATUS', '!=', 'CANCELADO');

        if ($contaId) {
            $q->where('m.CONTA_ID', $contaId);
        }

        $movs = $q->select('m.*', 'c.CONTA_DESCRICAO')->orderBy('m.MOV_DATA')->get();

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

// ── POST: conciliar movimentação (legado) ───────────────────────
Route::post('/conciliar', function () {
    if (!Schema::hasTable('MOVIMENTACAO_BANCARIA')) {
        return response()->json(['erro' => 'Módulo indisponível.'], 503);
    }
    try {
        $ids = request('ids', []);
        if (empty($ids)) {
            return response()->json(['erro' => 'ids é obrigatório.'], 422);
        }

        DB::table('MOVIMENTACAO_BANCARIA')
            ->whereIn('MOV_ID', $ids)
            ->update(['MOV_STATUS' => 'CONCILIADO', 'updated_at' => now()]);

        return response()->json(['ok' => true, 'conciliados' => count($ids)]);
    } catch (\Throwable $e) {
        return response()->json(['erro' => $e->getMessage()], 500);
    }
});

// ALIASES legados
Route::get('/tesouraria/fluxo-caixa', function () {
    return app()->make('router')->dispatch(request()->create('/api/v3/fluxo-caixa', 'GET'));
});
Route::post('/tesouraria/movimentacoes', function () {
    return app()->make('router')->dispatch(request()->create('/api/v3/conciliar', 'POST'));
});
