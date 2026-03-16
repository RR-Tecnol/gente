<?php
// ══════════════════════════════════════════════════════════════════
// VERBAS INDENIZATÓRIAS MENSAIS — não usar use statements aqui
// ══════════════════════════════════════════════════════════════════

// ── GET: tipos de verbas configuráveis ───────────────────────────
Route::get('/verba-indenizatoria/tipos', function () {
    try {
        $tipos = DB::table('VERBA_TIPO')
            ->orderBy('VERBA_GRUPO')
            ->orderBy('VERBA_NOME')
            ->get();
        return response()->json(['tipos' => $tipos]);
    } catch (\Throwable $e) {
        return response()->json(['erro' => $e->getMessage()], 500);
    }
});

// ── POST: criar / editar tipo de verba ───────────────────────────
Route::post('/verba-indenizatoria/tipos', function (Request $request) {
    try {
        $id = $request->verba_tipo_id;
        $payload = [
            'VERBA_NOME' => $request->verba_nome,
            'VERBA_GRUPO' => $request->verba_grupo ?? 'MENSAL',
            'INCIDE_IR' => (bool) ($request->incide_ir ?? false),
            'INCIDE_INSS' => (bool) ($request->incide_inss ?? false),
            'INCIDE_RPPS' => (bool) ($request->incide_rpps ?? false),
            'REQUER_COMPROVANTE' => (bool) ($request->requer_comprovante ?? false),
            'ATIVO' => (bool) ($request->ativo ?? true),
            'updated_at' => now(),
        ];

        if ($id) {
            DB::table('VERBA_TIPO')->where('VERBA_TIPO_ID', $id)->update($payload);
        } else {
            $payload['created_at'] = now();
            $id = DB::table('VERBA_TIPO')->insertGetId($payload);
        }
        return response()->json(['ok' => true, 'id' => $id]);
    } catch (\Throwable $e) {
        return response()->json(['erro' => $e->getMessage()], 500);
    }
});

// ── GET: lançamentos de verbas indenizatórias ─────────────────────
Route::get('/verba-indenizatoria', function (Request $request) {
    try {
        $query = DB::table('VERBA_LANCAMENTO as vl')
            ->join('FUNCIONARIO as f', 'f.FUNCIONARIO_ID', '=', 'vl.FUNCIONARIO_ID')
            ->join('PESSOA as p', 'p.PESSOA_ID', '=', 'f.PESSOA_ID')
            ->join('VERBA_TIPO as vt', 'vt.VERBA_TIPO_ID', '=', 'vl.VERBA_TIPO_ID')
            ->leftJoin('UNIDADE as u', 'u.UNIDADE_ID', '=', 'vl.UNIDADE_ID');

        if ($request->competencia)
            $query->where('vl.COMPETENCIA', $request->competencia);
        if ($request->unidade_id)
            $query->where('vl.UNIDADE_ID', $request->unidade_id);
        if ($request->tipo_id)
            $query->where('vl.VERBA_TIPO_ID', $request->tipo_id);
        if ($request->status)
            $query->where('vl.STATUS', $request->status);

        $lista = $query->select(
            'vl.*',
            'p.PESSOA_NOME as nome',
            'f.FUNCIONARIO_MATRICULA as matricula',
            'vt.VERBA_NOME as verba_nome',
            'vt.INCIDE_IR as incide_ir',
            'vt.INCIDE_INSS as incide_inss',
            'vt.REQUER_COMPROVANTE as requer_comprovante',
            'u.UNIDADE_NOME as secretaria'
        )->orderBy('vl.COMPETENCIA', 'desc')->orderBy('p.PESSOA_NOME')->get();

        // Totais por tipo de verba
        $porTipo = $lista->groupBy('verba_nome')->map(fn($g) => [
            'qtd' => $g->count(),
            'total' => round($g->sum('VALOR'), 2),
        ]);

        // Totais por secretaria
        $porSecretaria = $lista->groupBy('secretaria')->map(fn($g) => [
            'qtd' => $g->count(),
            'total' => round($g->sum('VALOR'), 2),
        ]);

        $unidades = DB::table('UNIDADE')->select('UNIDADE_ID as id', 'UNIDADE_NOME as nome')->get();
        $tipos = DB::table('VERBA_TIPO')->where('ATIVO', true)->where('VERBA_GRUPO', 'MENSAL')
            ->select('VERBA_TIPO_ID as id', 'VERBA_NOME as nome')->get();

        return response()->json([
            'lista' => $lista,
            'por_tipo' => $porTipo,
            'por_secretaria' => $porSecretaria,
            'total_geral' => round($lista->sum('VALOR'), 2),
            'unidades' => $unidades,
            'tipos' => $tipos,
        ]);
    } catch (\Throwable $e) {
        return response()->json(['erro' => $e->getMessage()], 500);
    }
});

// ── POST: lançar verba indenizatória para um servidor ────────────
Route::post('/verba-indenizatoria', function (Request $request) {
    try {
        $user = Auth::user();
        $funcId = $request->funcionario_id;

        if (!$funcId || !$request->verba_tipo_id || !$request->competencia || !$request->valor)
            return response()->json(['erro' => 'funcionario_id, verba_tipo_id, competencia e valor são obrigatórios.'], 422);

        // Lotação para capturar secretaria automaticamente
        $lot = DB::table('LOTACAO as l')
            ->leftJoin('SETOR as s', 's.SETOR_ID', '=', 'l.SETOR_ID')
            ->where('l.FUNCIONARIO_ID', $funcId)
            ->whereNull('l.LOTACAO_DATA_FIM')
            ->select('s.UNIDADE_ID')
            ->first();

        $id = DB::table('VERBA_LANCAMENTO')->insertGetId([
            'FUNCIONARIO_ID' => $funcId,
            'VERBA_TIPO_ID' => $request->verba_tipo_id,
            'COMPETENCIA' => $request->competencia,
            'VALOR' => $request->valor,
            'JUSTIFICATIVA' => $request->justificativa,
            'UNIDADE_ID' => $request->unidade_id ?? $lot?->UNIDADE_ID,
            'STATUS' => 'PENDENTE',
            'LANCADO_POR' => $user?->USUARIO_ID ?? null,
            'created_at' => now(),
            'updated_at' => now(),
        ]);

        return response()->json(['ok' => true, 'id' => $id]);
    } catch (\Throwable $e) {
        return response()->json(['erro' => $e->getMessage()], 500);
    }
});

// ── POST: lançar para múltiplos servidores em lote ───────────────
Route::post('/verba-indenizatoria/lote', function (Request $request) {
    try {
        $user = Auth::user();
        $verbaTipoId = $request->verba_tipo_id;
        $competencia = $request->competencia;
        $servidores = $request->servidores; // [{funcionario_id, valor, justificativa}]

        if (!$verbaTipoId || !$competencia || empty($servidores))
            return response()->json(['erro' => 'Parâmetros obrigatórios faltando.'], 422);

        $incluidos = 0;
        foreach ($servidores as $s) {
            if (empty($s['funcionario_id']) || empty($s['valor']))
                continue;

            $lot = DB::table('LOTACAO as l')
                ->leftJoin('SETOR as se', 'se.SETOR_ID', '=', 'l.SETOR_ID')
                ->where('l.FUNCIONARIO_ID', $s['funcionario_id'])
                ->whereNull('l.LOTACAO_DATA_FIM')
                ->value('se.UNIDADE_ID');

            DB::table('VERBA_LANCAMENTO')->insert([
                'FUNCIONARIO_ID' => $s['funcionario_id'],
                'VERBA_TIPO_ID' => $verbaTipoId,
                'COMPETENCIA' => $competencia,
                'VALOR' => $s['valor'],
                'JUSTIFICATIVA' => $s['justificativa'] ?? null,
                'UNIDADE_ID' => $lot,
                'STATUS' => 'PENDENTE',
                'LANCADO_POR' => $user?->USUARIO_ID ?? null,
                'created_at' => now(),
                'updated_at' => now(),
            ]);
            $incluidos++;
        }

        return response()->json(['ok' => true, 'incluidos' => $incluidos]);
    } catch (\Throwable $e) {
        return response()->json(['erro' => $e->getMessage()], 500);
    }
});

// ── PATCH: aprovar / rejeitar lançamento ──────────────────────────
Route::patch('/verba-indenizatoria/{id}/status', function (Request $request, $id) {
    try {
        DB::table('VERBA_LANCAMENTO')->where('VERBA_LANCAMENTO_ID', $id)->update([
            'STATUS' => $request->status,
            'updated_at' => now(),
        ]);
        return response()->json(['ok' => true]);
    } catch (\Throwable $e) {
        return response()->json(['erro' => $e->getMessage()], 500);
    }
});

// ── DELETE: cancelar lançamento (somente PENDENTE) ───────────────
Route::delete('/verba-indenizatoria/{id}', function ($id) {
    try {
        $vl = DB::table('VERBA_LANCAMENTO')->where('VERBA_LANCAMENTO_ID', $id)->first();
        if (!$vl || $vl->STATUS !== 'PENDENTE')
            return response()->json(['erro' => 'Só é possível cancelar lançamentos PENDENTES.'], 422);
        DB::table('VERBA_LANCAMENTO')->where('VERBA_LANCAMENTO_ID', $id)->delete();
        return response()->json(['ok' => true]);
    } catch (\Throwable $e) {
        return response()->json(['erro' => $e->getMessage()], 500);
    }
});

// ── GET: relatório agregado por tipo e secretaria ─────────────────
Route::get('/verba-indenizatoria/relatorio', function (Request $request) {
    try {
        $competencia = $request->competencia ?? now()->format('Y-m');

        $porTipo = DB::table('VERBA_LANCAMENTO as vl')
            ->join('VERBA_TIPO as vt', 'vt.VERBA_TIPO_ID', '=', 'vl.VERBA_TIPO_ID')
            ->where('vl.COMPETENCIA', $competencia)
            ->whereIn('vl.STATUS', ['APROVADO', 'INCLUIDO_FOLHA'])
            ->groupBy('vt.VERBA_TIPO_ID', 'vt.VERBA_NOME', 'vt.INCIDE_IR', 'vt.INCIDE_INSS')
            ->select(
                'vt.VERBA_TIPO_ID',
                'vt.VERBA_NOME as verba',
                'vt.INCIDE_IR as incide_ir',
                'vt.INCIDE_INSS as incide_inss',
                DB::raw('COUNT(vl.FUNCIONARIO_ID) as qtd'),
                DB::raw('SUM(vl.VALOR) as total')
            )->get();

        $porSecretaria = DB::table('VERBA_LANCAMENTO as vl')
            ->join('UNIDADE as u', 'u.UNIDADE_ID', '=', 'vl.UNIDADE_ID')
            ->where('vl.COMPETENCIA', $competencia)
            ->whereIn('vl.STATUS', ['APROVADO', 'INCLUIDO_FOLHA'])
            ->groupBy('u.UNIDADE_ID', 'u.UNIDADE_NOME')
            ->select(
                'u.UNIDADE_ID',
                'u.UNIDADE_NOME as secretaria',
                DB::raw('COUNT(DISTINCT vl.FUNCIONARIO_ID) as qtd_servidores'),
                DB::raw('SUM(vl.VALOR) as total')
            )->get();

        return response()->json([
            'competencia' => $competencia,
            'por_tipo' => $porTipo,
            'por_secretaria' => $porSecretaria,
            'total' => round(DB::table('VERBA_LANCAMENTO')
                ->where('COMPETENCIA', $competencia)
                ->whereIn('STATUS', ['APROVADO', 'INCLUIDO_FOLHA'])
                ->sum('VALOR'), 2),
        ]);
    } catch (\Throwable $e) {
        return response()->json(['erro' => $e->getMessage()], 500);
    }
});
