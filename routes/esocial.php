<?php
// ══════════════════════════════════════════════════════════════════
// ESOCIAL — painel de rastreamento de eventos
// ══════════════════════════════════════════════════════════════════

// ── GET: lista de eventos com filtros ────────────────────────────
Route::get('/esocial/eventos', function (Request $request) {
    try {
        $query = DB::table('ESOCIAL_EVENTO as e')
            ->join('FUNCIONARIO as f', 'f.FUNCIONARIO_ID', '=', 'e.FUNCIONARIO_ID')
            ->join('PESSOA as p', 'p.PESSOA_ID', '=', 'f.PESSOA_ID');

        if ($request->tipo_evento)
            $query->where('e.TIPO_EVENTO', $request->tipo_evento);
        if ($request->status)
            $query->where('e.STATUS', $request->status);
        if ($request->competencia)
            $query->where('e.COMPETENCIA', $request->competencia);

        $eventos = $query->select(
            'e.*',
            'p.PESSOA_NOME as nome',
            'f.FUNCIONARIO_MATRICULA as matricula'
        )->orderBy('e.created_at', 'desc')->limit(200)->get();

        $stats = [
            'pendentes' => DB::table('ESOCIAL_EVENTO')->where('STATUS', 'PENDENTE')->count(),
            'gerados' => DB::table('ESOCIAL_EVENTO')->where('STATUS', 'GERADO')->count(),
            'enviados' => DB::table('ESOCIAL_EVENTO')->where('STATUS', 'ENVIADO')->count(),
            'processados' => DB::table('ESOCIAL_EVENTO')->where('STATUS', 'PROCESSADO')->count(),
            'rejeitados' => DB::table('ESOCIAL_EVENTO')->where('STATUS', 'REJEITADO')->count(),
        ];

        return response()->json(['eventos' => $eventos, 'stats' => $stats]);
    } catch (\Throwable $e) {
        return response()->json(['erro' => $e->getMessage()], 500);
    }
});

// ── POST: gerar evento manualmente para um servidor ──────────────
Route::post('/esocial/eventos', function (Request $request) {
    try {
        $user = Auth::user();
        if (!$request->funcionario_id || !$request->tipo_evento)
            return response()->json(['erro' => 'funcionario_id e tipo_evento são obrigatórios.'], 422);

        $xmlService = new \App\Services\EsocialXmlService();
        $xml = '';
        
        switch ($request->tipo_evento) {
            case 'S-1200':
                if (!$request->competencia) return response()->json(['erro' => 'competencia obrigatória para S-1200'], 422);
                $xml = $xmlService->gerarS1200($request->funcionario_id, $request->competencia);
                break;
            case 'S-2200':
                $xml = $xmlService->gerarS2200($request->funcionario_id);
                break;
            case 'S-2206':
                $xml = $xmlService->gerarS2206($request->funcionario_id);
                break;
            case 'S-2299':
                $xml = $xmlService->gerarS2299($request->funcionario_id, $request->data_desligamento ?? null);
                break;
            default:
                return response()->json(['erro' => 'Tipo de evento não suportado.'], 400);
        }

        $id = DB::table('ESOCIAL_EVENTO')->insertGetId([
            'FUNCIONARIO_ID' => $request->funcionario_id,
            'TIPO_EVENTO' => $request->tipo_evento,
            'COMPETENCIA' => $request->competencia,
            'DATA_REFERENCIA' => $request->data_referencia ?? now()->format('Y-m-d'),
            'STATUS' => 'GERADO',
            'XML_GERADO' => $xml,
            'GERADO_POR' => $user?->USUARIO_ID ?? null,
            'created_at' => now(),
            'updated_at' => now(),
        ]);

        return response()->json(['ok' => true, 'evento_id' => $id, 'xml' => $xml]);
    } catch (\Throwable $e) {
        return response()->json(['erro' => $e->getMessage()], 500);
    }
});

// ── GET: endpoint explícito para teste S-1200 ──────────────
Route::get('/esocial/gerar/S-1200/{competencia}', function (string $competencia) {
    try {
        $firstFuncionario = DB::table('FUNCIONARIO')->orderBy('FUNCIONARIO_ID')->first();
        if (!$firstFuncionario) return response('Nenhum funcionário ativo', 404);
        
        $xmlService = new \App\Services\EsocialXmlService();
        $xml = $xmlService->gerarS1200($firstFuncionario->FUNCIONARIO_ID, $competencia);
        
        return response($xml, 200)->header('Content-Type', 'application/xml');
    } catch (\Throwable $e) {
        return response($e->getMessage(), 500);
    }
});

// ── PATCH: marcar como enviado / reprocessar ─────────────────────
Route::patch('/esocial/eventos/{id}', function (Request $request, $id) {
    try {
        DB::table('ESOCIAL_EVENTO')->where('EVENTO_ID', $id)->update([
            'STATUS' => $request->status,
            'NUMERO_RECIBO' => $request->numero_recibo,
            'MOTIVO_ERRO' => $request->motivo_erro,
            'DT_ENVIO' => $request->status === 'ENVIADO' ? now() : null,
            'updated_at' => now(),
        ]);
        return response()->json(['ok' => true]);
    } catch (\Throwable $e) {
        return response()->json(['erro' => $e->getMessage()], 500);
    }
});

// ── GET: pendências eSocial de uma competência ───────────────────
Route::get('/esocial/pendencias', function (Request $request) {
    try {
        $comp = $request->competencia ?? now()->format('Y-m');

        // Admissões sem S-2200 — LEFT JOIN (evita subquery O(n²))
        $admissoes = DB::table('FUNCIONARIO as f')
            ->join('PESSOA as p', 'p.PESSOA_ID', '=', 'f.PESSOA_ID')
            ->leftJoin('ESOCIAL_EVENTO as e2200', function ($j) {
                $j->on('e2200.FUNCIONARIO_ID', '=', 'f.FUNCIONARIO_ID')
                    ->where('e2200.TIPO_EVENTO', 'S-2200');
            })
            ->whereNull('e2200.EVENTO_ID')
            ->whereNull('f.FUNCIONARIO_DATA_FIM')
            ->select('f.FUNCIONARIO_ID', 'p.PESSOA_NOME', 'f.FUNCIONARIO_DATA_INICIO as FUNCIONARIO_DATA_ADMISSAO', DB::raw('"S-2200" as evento_faltante'))
            ->limit(50)->get();

        // Desligamentos sem S-2299 — LEFT JOIN (evita subquery O(n²))
        $demissoes = DB::table('FUNCIONARIO as f')
            ->join('PESSOA as p', 'p.PESSOA_ID', '=', 'f.PESSOA_ID')
            ->leftJoin('ESOCIAL_EVENTO as e2299', function ($j) {
                $j->on('e2299.FUNCIONARIO_ID', '=', 'f.FUNCIONARIO_ID')
                    ->where('e2299.TIPO_EVENTO', 'S-2299');
            })
            ->whereNull('e2299.EVENTO_ID')
            ->whereNotNull('f.FUNCIONARIO_DATA_FIM')
            ->select('f.FUNCIONARIO_ID', 'p.PESSOA_NOME', 'f.FUNCIONARIO_DATA_FIM', DB::raw('"S-2299" as evento_faltante'))
            ->limit(50)->get();

        // Eventos rejeitados
        $rejeitados = DB::table('ESOCIAL_EVENTO as e')
            ->join('PESSOA as p', 'p.PESSOA_ID', '=', DB::raw('(SELECT PESSOA_ID FROM FUNCIONARIO WHERE FUNCIONARIO_ID = e.FUNCIONARIO_ID)'))
            ->where('e.STATUS', 'REJEITADO')
            ->select('e.*', 'p.PESSOA_NOME as nome')
            ->limit(20)->get();

        return response()->json([
            'competencia' => $comp,
            'admissoes_sem_evento' => $admissoes,
            'demissoes_sem_evento' => $demissoes,
            'eventos_rejeitados' => $rejeitados,
            'total_pendencias' => $admissoes->count() + $demissoes->count() + $rejeitados->count(),
        ]);
    } catch (\Throwable $e) {
        return response()->json(['erro' => $e->getMessage()], 500);
    }
});

// ── POST: gerar S-2200 em lote para todos sem admissão ──────────
Route::post('/esocial/gerar-lote', function (Request $request) {
    try {
        $user = Auth::user();
        $tipo = $request->tipo_evento ?? 'S-2200';
        $ids = $request->funcionario_ids ?? [];

        if (empty($ids))
            return response()->json(['erro' => 'funcionario_ids é obrigatório.'], 422);

        $gerados = 0;
        foreach ($ids as $fid) {
            DB::table('ESOCIAL_EVENTO')->insert([
                'FUNCIONARIO_ID' => $fid,
                'TIPO_EVENTO' => $tipo,
                'DATA_REFERENCIA' => now()->format('Y-m-d'),
                'STATUS' => 'GERADO',
                'GERADO_POR' => $user?->USUARIO_ID ?? null,
                'created_at' => now(),
                'updated_at' => now(),
            ]);
            $gerados++;
        }

        return response()->json(['ok' => true, 'gerados' => $gerados]);
    } catch (\Throwable $e) {
        return response()->json(['erro' => $e->getMessage()], 500);
    }
});
