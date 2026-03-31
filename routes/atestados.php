<?php
// ══════════════════════════════════════════════════════════════════
// ATESTADOS MÉDICOS — GAP-06 / Sprint 5
// Lei nº 9.394/1996 + Lei nº 8.112/1990 (servidores públicos)
// ⚠️ NÃO abrir Route::middleware()->prefix()->group() aqui
//    O contexto api/v3 + auth já é herdado do web.php
// ══════════════════════════════════════════════════════════════════

// GET /atestados — lista com filtro por perfil
Route::get('/atestados', function (Request $request) {
    try {
        $user = Auth::user();
        $isAdmin = strtolower($user->USUARIO_LOGIN ?? '') === 'admin'
            || in_array(strtolower(optional($user->usuarioPerfis()->with('perfil')->first())->perfil->PERFIL_NOME ?? ''), ['rh', 'admin']);

        $query = DB::table('ATESTADO_MEDICO as a')
            ->join('FUNCIONARIO as f', 'f.FUNCIONARIO_ID', '=', 'a.FUNCIONARIO_ID')
            ->join('PESSOA as p', 'p.PESSOA_ID', '=', 'f.PESSOA_ID')
            ->leftJoin('USUARIO as u', 'u.USUARIO_ID', '=', 'a.VALIDADO_POR');

        // Servidor comum só vê os próprios atestados
        if (!$isAdmin) {
            $func_id = DB::table('FUNCIONARIO')
                ->where('USUARIO_ID', $user->USUARIO_ID)
                ->value('FUNCIONARIO_ID');
            if ($func_id) {
                $query->where('a.FUNCIONARIO_ID', $func_id);
            } else {
                return response()->json(['atestados' => []]);
            }
        }

        if ($request->funcionario_id)
            $query->where('a.FUNCIONARIO_ID', $request->funcionario_id);
        if ($request->status)
            $query->where('a.STATUS', $request->status);
        if ($request->ano)
            $query->whereYear('a.ATESTADO_DATA', $request->ano);

        $atestados = $query->select(
            'a.ATESTADO_ID as id',
            'a.FUNCIONARIO_ID as funcionario_id',
            'p.PESSOA_NOME as nome',
            'f.FUNCIONARIO_MATRICULA as matricula',
            'a.ATESTADO_DATA as data',
            'a.ATESTADO_DIAS as dias',
            'a.ATESTADO_CID as cid',
            'a.MEDICO_NOME as medico',
            'a.MEDICO_CRM as crm',
            'a.STATUS as status',
            'u.USUARIO_NOME as validado_por',
            'a.created_at as registrado_em'
        )
            ->orderByDesc('a.ATESTADO_DATA')
            ->get();

        return response()->json([
            'atestados' => $atestados,
            'total_dias' => $atestados->sum('dias'),
            'total_atestados' => $atestados->count(),
        ]);
    } catch (\Throwable $e) {
        return response()->json(['erro' => $e->getMessage()], 500);
    }
});

// POST /atestados — registrar atestado
Route::post('/atestados', function (Request $request) {
    try {
        if (!$request->funcionario_id || !$request->atestado_data || !$request->atestado_dias) {
            return response()->json(['erro' => 'funcionario_id, atestado_data e atestado_dias são obrigatórios.'], 422);
        }

        $id = DB::table('ATESTADO_MEDICO')->insertGetId([
            'FUNCIONARIO_ID' => $request->funcionario_id,
            'ATESTADO_DATA' => $request->atestado_data,
            'ATESTADO_DIAS' => (int) $request->atestado_dias,
            'ATESTADO_CID' => $request->cid,
            'MEDICO_NOME' => $request->medico_nome,
            'MEDICO_CRM' => $request->medico_crm,
            'ARQUIVO_PATH' => null, // upload via endpoint separado se necessário
            'STATUS' => 'PENDENTE',
            'created_at' => now(),
            'updated_at' => now(),
        ]);

        return response()->json(['ok' => true, 'id' => $id], 201);
    } catch (\Throwable $e) {
        return response()->json(['erro' => $e->getMessage()], 500);
    }
})->middleware('upload.safe');

// PATCH /atestados/{id}/validar — RH valida ou rejeita
Route::patch('/atestados/{id}/validar', function (Request $request, $id) {
    try {
        $user = Auth::user();
        $statusValidos = ['VALIDADO', 'REJEITADO'];

        if (!in_array($request->status, $statusValidos)) {
            return response()->json(['erro' => 'Status deve ser VALIDADO ou REJEITADO.'], 422);
        }

        DB::table('ATESTADO_MEDICO')->where('ATESTADO_ID', $id)->update([
            'STATUS' => $request->status,
            'VALIDADO_POR' => $user->USUARIO_ID ?? null,
            'OBSERVACAO' => $request->observacao,
            'updated_at' => now(),
        ]);

        return response()->json(['ok' => true]);
    } catch (\Throwable $e) {
        return response()->json(['erro' => $e->getMessage()], 500);
    }
});

// GET /atestados/relatorio — relatório por secretaria/período (para medicina do trabalho)
Route::get('/atestados/relatorio', function (Request $request) {
    try {
        $ano = $request->ano ?? now()->year;

        $dados = DB::table('ATESTADO_MEDICO as a')
            ->join('FUNCIONARIO as f', 'f.FUNCIONARIO_ID', '=', 'a.FUNCIONARIO_ID')
            ->join('PESSOA as p', 'p.PESSOA_ID', '=', 'f.PESSOA_ID')
            ->leftJoin('LOTACAO as l', function ($j) {
                $j->on('l.FUNCIONARIO_ID', '=', 'f.FUNCIONARIO_ID')->whereNull('l.LOTACAO_DATA_FIM');
            })
            ->leftJoin('SETOR as s', 's.SETOR_ID', '=', 'l.SETOR_ID')
            ->leftJoin('UNIDADE as u', 'u.UNIDADE_ID', '=', 's.UNIDADE_ID')
            ->where('a.STATUS', 'VALIDADO')
            ->whereYear('a.ATESTADO_DATA', $ano)
            ->groupBy('f.FUNCIONARIO_ID', 'p.PESSOA_NOME', 'f.FUNCIONARIO_MATRICULA', 's.SETOR_NOME', 'u.UNIDADE_NOME')
            ->select(
                'p.PESSOA_NOME as nome',
                'f.FUNCIONARIO_MATRICULA as matricula',
                's.SETOR_NOME as setor',
                'u.UNIDADE_NOME as secretaria',
                DB::raw('COUNT(a.ATESTADO_ID) as qtd_atestados'),
                DB::raw('SUM(a.ATESTADO_DIAS) as total_dias')
            )
            ->orderByDesc('total_dias')
            ->get();

        return response()->json([
            'ano' => $ano,
            'servidores' => $dados,
            'total_dias' => $dados->sum('total_dias'),
            'media_por_srv' => $dados->count() > 0 ? round($dados->sum('total_dias') / $dados->count(), 1) : 0,
        ]);
    } catch (\Throwable $e) {
        return response()->json(['erro' => $e->getMessage()], 500);
    }
});
