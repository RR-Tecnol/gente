<?php
// ══════════════════════════════════════════════════════════════════
// ACUMULAÇÃO DE CARGOS — obrigação constitucional (CF art. 37 XVI)
// LAT-01 / GAP-09
// ⚠️ NÃO abrir Route::middleware()->prefix()->group() aqui
//    O contexto api/v3 + auth já é herdado do web.php
// ══════════════════════════════════════════════════════════════════

// GET /acumulacao — lista declarações com dados do servidor
Route::get('/acumulacao', function (Request $request) {
    try {
        $query = DB::table('ACUMULACAO_CARGO as a')
            ->join('FUNCIONARIO as f', 'f.FUNCIONARIO_ID', '=', 'a.FUNCIONARIO_ID')
            ->join('PESSOA as p', 'p.PESSOA_ID', '=', 'f.PESSOA_ID')
            ->leftJoin('CARGO as c', 'c.CARGO_ID', '=', 'f.CARGO_ID');

        if ($request->status) {
            $query->where('a.STATUS', $request->status);
        }
        if ($request->funcionario_id) {
            $query->where('a.FUNCIONARIO_ID', $request->funcionario_id);
        }

        $declaracoes = $query->select(
            'a.ACUMULACAO_ID as id',
            'a.FUNCIONARIO_ID as funcionario_id',
            'p.PESSOA_NOME as nome',
            'f.FUNCIONARIO_MATRICULA as matricula',
            'c.CARGO_NOME as cargo_principal',
            'a.ORGAO_EXTERNO',
            'a.CARGO_EXTERNO',
            'a.REMUNERACAO_EXTERNA',
            'a.REGIME_EXTERNO',
            'a.DATA_INICIO_EXTERNO',
            'a.STATUS',
            'a.ANALISADO_POR',
            'a.DATA_ANALISE',
            'a.OBSERVACAO',
            'a.created_at'
        )->orderByDesc('a.created_at')->get();

        return response()->json(['declaracoes' => $declaracoes]);
    } catch (\Throwable $e) {
        return response()->json(['erro' => $e->getMessage()], 500);
    }
});

// POST /acumulacao — servidor declara cargo externo
Route::post('/acumulacao', function (Request $request) {
    try {
        $user = Auth::user();
        $funcionario_id = $request->funcionario_id ?? DB::table('FUNCIONARIO')
            ->where('USUARIO_ID', $user->USUARIO_ID)
            ->value('FUNCIONARIO_ID');

        if (!$funcionario_id) {
            return response()->json(['erro' => 'Vínculo funcional não encontrado.'], 422);
        }

        $id = DB::table('ACUMULACAO_CARGO')->insertGetId([
            'FUNCIONARIO_ID' => $funcionario_id,
            'ORGAO_EXTERNO' => $request->orgao_externo,
            'CARGO_EXTERNO' => $request->cargo_externo,
            'REMUNERACAO_EXTERNA' => $request->remuneracao_externa,
            'REGIME_EXTERNO' => $request->regime_externo ?? 'CLT',
            'DATA_INICIO_EXTERNO' => $request->data_inicio_externo,
            'STATUS' => 'PENDENTE',
            'created_at' => now(),
            'updated_at' => now(),
        ]);

        return response()->json(['ok' => true, 'id' => $id], 201);
    } catch (\Throwable $e) {
        return response()->json(['erro' => $e->getMessage()], 500);
    }
});

// PATCH /acumulacao/{id}/analisar — RH analisa (APROVADO | IRREGULAR | SUSPENSO)
Route::patch('/acumulacao/{id}/analisar', function (Request $request, $id) {
    try {
        $user = Auth::user();
        $statusValidos = ['APROVADO', 'IRREGULAR', 'SUSPENSO', 'PENDENTE'];

        if (!in_array($request->status, $statusValidos)) {
            return response()->json(['erro' => 'Status inválido. Use: ' . implode(', ', $statusValidos)], 422);
        }

        DB::table('ACUMULACAO_CARGO')->where('ACUMULACAO_ID', $id)->update([
            'STATUS' => $request->status,
            'ANALISADO_POR' => $user->USUARIO_ID ?? null,
            'DATA_ANALISE' => now()->toDateString(),
            'OBSERVACAO' => $request->observacao,
            'updated_at' => now(),
        ]);

        return response()->json(['ok' => true]);
    } catch (\Throwable $e) {
        return response()->json(['erro' => $e->getMessage()], 500);
    }
});

// GET /acumulacao/irregulares — relatório para CGM/CGJ
Route::get('/acumulacao/irregulares', function () {
    try {
        $irregulares = DB::table('ACUMULACAO_CARGO as a')
            ->join('FUNCIONARIO as f', 'f.FUNCIONARIO_ID', '=', 'a.FUNCIONARIO_ID')
            ->join('PESSOA as p', 'p.PESSOA_ID', '=', 'f.PESSOA_ID')
            ->leftJoin('CARGO as c', 'c.CARGO_ID', '=', 'f.CARGO_ID')
            ->where('a.STATUS', 'IRREGULAR')
            ->select(
                'p.PESSOA_NOME as nome',
                'f.FUNCIONARIO_MATRICULA as matricula',
                'p.PESSOA_CPF_NUMERO as cpf',
                'c.CARGO_NOME as cargo_municipal',
                'a.ORGAO_EXTERNO',
                'a.CARGO_EXTERNO',
                'a.REMUNERACAO_EXTERNA',
                'a.DATA_ANALISE',
                'a.OBSERVACAO'
            )
            ->orderBy('p.PESSOA_NOME')
            ->get();

        return response()->json([
            'irregulares' => $irregulares,
            'total' => $irregulares->count(),
            'gerado_em' => now()->toDateTimeString(),
        ]);
    } catch (\Throwable $e) {
        return response()->json(['erro' => $e->getMessage()], 500);
    }
});
