<?php
// ══════════════════════════════════════════════════════════════════
// BANCO DE HORAS — GAP-05 / Sprint 5
// ⚠️ NÃO abrir Route::middleware()->prefix()->group() aqui
//    O contexto api/v3 + auth já é herdado do web.php
// ══════════════════════════════════════════════════════════════════

// GET /banco-horas — saldo atual + histórico de 12 meses
Route::get('/banco-horas', function (Request $request) {
    try {
        $funcionario_id = $request->funcionario_id;
        $user = Auth::user();

        if (!$funcionario_id) {
            $funcionario_id = DB::table('FUNCIONARIO')
                ->where('USUARIO_ID', $user->USUARIO_ID)
                ->value('FUNCIONARIO_ID');
        }

        if (!$funcionario_id) {
            return response()->json(['saldo' => null, 'historico' => [], 'aviso' => 'Vínculo funcional não encontrado.']);
        }

        $saldo = DB::table('BANCO_HORAS')
            ->where('FUNCIONARIO_ID', $funcionario_id)
            ->orderByDesc('COMPETENCIA')
            ->first();

        // Saldo acumulado: soma de créditos - débitos
        $saldoAcumulado = DB::table('BANCO_HORAS')
            ->where('FUNCIONARIO_ID', $funcionario_id)
            ->selectRaw('SUM(COALESCE(HORAS_CREDITADAS,0)) - SUM(COALESCE(HORAS_DEBITADAS,0)) as saldo_total')
            ->value('saldo_total') ?? 0;

        $historico = DB::table('BANCO_HORAS as bh')
            ->leftJoin('USUARIO as u', 'u.USUARIO_ID', '=', 'bh.REGISTRADO_POR')
            ->where('bh.FUNCIONARIO_ID', $funcionario_id)
            ->select('bh.*', 'u.USUARIO_NOME as registrado_por_nome')
            ->orderByDesc('bh.COMPETENCIA')
            ->limit(24)
            ->get();

        return response()->json([
            'saldo_acumulado' => round($saldoAcumulado, 2),
            'ultimo_registro' => $saldo,
            'historico' => $historico,
        ]);
    } catch (\Throwable $e) {
        return response()->json(['erro' => $e->getMessage()], 500);
    }
});

// POST /banco-horas/lancar — registrar crédito ou débito
Route::post('/banco-horas/lancar', function (Request $request) {
    try {
        $user = Auth::user();

        if (!$request->funcionario_id || !$request->competencia || !$request->tipo) {
            return response()->json(['erro' => 'funcionario_id, competencia e tipo são obrigatórios.'], 422);
        }

        $tiposValidos = ['CREDITO', 'COMPENSACAO', 'PAGAMENTO', 'EXPIRADO'];
        if (!in_array($request->tipo, $tiposValidos)) {
            return response()->json(['erro' => 'Tipo inválido. Use: ' . implode(', ', $tiposValidos)], 422);
        }

        $id = DB::table('BANCO_HORAS')->insertGetId([
            'FUNCIONARIO_ID' => $request->funcionario_id,
            'COMPETENCIA' => $request->competencia,
            'HORAS_CREDITADAS' => $request->tipo === 'CREDITO' ? ($request->horas ?? 0) : 0,
            'HORAS_DEBITADAS' => in_array($request->tipo, ['COMPENSACAO', 'PAGAMENTO', 'EXPIRADO']) ? ($request->horas ?? 0) : 0,
            'TIPO' => $request->tipo,
            'OBSERVACAO' => $request->observacao,
            'REGISTRADO_POR' => $user->USUARIO_ID ?? null,
            'created_at' => now(),
            'updated_at' => now(),
        ]);

        return response()->json(['ok' => true, 'id' => $id], 201);
    } catch (\Throwable $e) {
        return response()->json(['erro' => $e->getMessage()], 500);
    }
});

// POST /banco-horas/compensar — solicitar compensação de horas
Route::post('/banco-horas/compensar', function (Request $request) {
    try {
        $user = Auth::user();
        $func_id = $request->funcionario_id ?? DB::table('FUNCIONARIO')
            ->where('USUARIO_ID', $user->USUARIO_ID)
            ->value('FUNCIONARIO_ID');

        if (!$func_id) {
            return response()->json(['erro' => 'Vínculo funcional não encontrado.'], 422);
        }

        // Verificar saldo disponível
        $saldo = DB::table('BANCO_HORAS')
            ->where('FUNCIONARIO_ID', $func_id)
            ->selectRaw('SUM(COALESCE(HORAS_CREDITADAS,0)) - SUM(COALESCE(HORAS_DEBITADAS,0)) as saldo')
            ->value('saldo') ?? 0;

        if ((float) $saldo < (float) ($request->horas ?? 0)) {
            return response()->json([
                'erro' => 'Saldo insuficiente de banco de horas.',
                'saldo' => round($saldo, 2),
            ], 422);
        }

        $id = DB::table('BANCO_HORAS')->insertGetId([
            'FUNCIONARIO_ID' => $func_id,
            'COMPETENCIA' => $request->competencia ?? now()->format('Y-m'),
            'HORAS_CREDITADAS' => 0,
            'HORAS_DEBITADAS' => $request->horas ?? 0,
            'TIPO' => 'COMPENSACAO',
            'OBSERVACAO' => $request->observacao ?? 'Compensação solicitada pelo servidor',
            'REGISTRADO_POR' => $user->USUARIO_ID ?? null,
            'created_at' => now(),
            'updated_at' => now(),
        ]);

        return response()->json(['ok' => true, 'id' => $id, 'saldo_restante' => round($saldo - ($request->horas ?? 0), 2)], 201);
    } catch (\Throwable $e) {
        return response()->json(['erro' => $e->getMessage()], 500);
    }
});

// GET /banco-horas/relatorio — consolidado por setor/secretaria
Route::get('/banco-horas/relatorio', function (Request $request) {
    try {
        $comp = $request->competencia ?? now()->format('Y-m');

        $consolidado = DB::table('BANCO_HORAS as bh')
            ->join('FUNCIONARIO as f', 'f.FUNCIONARIO_ID', '=', 'bh.FUNCIONARIO_ID')
            ->join('PESSOA as p', 'p.PESSOA_ID', '=', 'f.PESSOA_ID')
            ->leftJoin('LOTACAO as l', function ($j) {
                $j->on('l.FUNCIONARIO_ID', '=', 'f.FUNCIONARIO_ID')->whereNull('l.LOTACAO_DATA_FIM');
            })
            ->leftJoin('SETOR as s', 's.SETOR_ID', '=', 'l.SETOR_ID')
            ->leftJoin('UNIDADE as u', 'u.UNIDADE_ID', '=', 's.UNIDADE_ID')
            ->where('bh.COMPETENCIA', $comp)
            ->groupBy('f.FUNCIONARIO_ID', 'p.PESSOA_NOME', 'f.FUNCIONARIO_MATRICULA', 's.SETOR_NOME', 'u.UNIDADE_NOME')
            ->select(
                'p.PESSOA_NOME as nome',
                'f.FUNCIONARIO_MATRICULA as matricula',
                's.SETOR_NOME as setor',
                'u.UNIDADE_NOME as secretaria',
                DB::raw('SUM(bh.HORAS_CREDITADAS) as total_creditado'),
                DB::raw('SUM(bh.HORAS_DEBITADAS) as total_debitado'),
                DB::raw('SUM(bh.HORAS_CREDITADAS) - SUM(bh.HORAS_DEBITADAS) as saldo_periodo')
            )
            ->orderBy('u.UNIDADE_NOME')
            ->get();

        return response()->json([
            'competencia' => $comp,
            'servidores' => $consolidado,
            'total_creditado' => round($consolidado->sum('total_creditado'), 2),
            'total_debitado' => round($consolidado->sum('total_debitado'), 2),
        ]);
    } catch (\Throwable $e) {
        return response()->json(['erro' => $e->getMessage()], 500);
    }
});
