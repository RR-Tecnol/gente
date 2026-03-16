<?php
// ══════════════════════════════════════════════════════════════════
// TERCEIRIZADOS — checklist obrigatório para CGM
// LAT-05 / GAP-12
// ⚠️ NÃO abrir Route::middleware()->prefix()->group() aqui
//    O contexto api/v3 + auth já é herdado do web.php
// ══════════════════════════════════════════════════════════════════

// GET /terceirizados/empresas — lista empresas cadastradas
Route::get('/terceirizados/empresas', function (Request $request) {
    try {
        $empresas = DB::table('TERC_EMPRESA as e')
            ->select(
                'e.EMPRESA_ID as id',
                'e.EMPRESA_RAZAO_SOCIAL as razao_social',
                'e.EMPRESA_CNPJ as cnpj',
                'e.EMPRESA_ATIVIDADE as atividade',
                'e.EMPRESA_CONTATO as contato',
                'e.EMPRESA_EMAIL as email',
                'e.EMPRESA_TELEFONE as telefone',
                'e.CONTRATO_NUMERO as contrato',
                'e.CONTRATO_INICIO as contrato_inicio',
                'e.CONTRATO_FIM as contrato_fim',
                'e.STATUS as status',
                DB::raw('(SELECT COUNT(*) FROM TERC_POSTO WHERE EMPRESA_ID = e.EMPRESA_ID AND POSTO_ATIVO = 1) as postos_ativos')
            )
            ->orderBy('e.EMPRESA_RAZAO_SOCIAL')
            ->get();

        return response()->json(['empresas' => $empresas]);
    } catch (\Throwable $e) {
        return response()->json(['erro' => $e->getMessage()], 500);
    }
});

// POST /terceirizados/empresas — cadastrar empresa terceirizada
Route::post('/terceirizados/empresas', function (Request $request) {
    try {
        $cnpj = preg_replace('/\D/', '', $request->cnpj ?? '');
        if (!$cnpj) {
            return response()->json(['erro' => 'CNPJ é obrigatório.'], 422);
        }

        if (DB::table('TERC_EMPRESA')->where('EMPRESA_CNPJ', $cnpj)->exists()) {
            return response()->json(['erro' => 'Empresa já cadastrada com este CNPJ.'], 422);
        }

        $id = DB::table('TERC_EMPRESA')->insertGetId([
            'EMPRESA_RAZAO_SOCIAL' => $request->razao_social,
            'EMPRESA_CNPJ' => $cnpj,
            'EMPRESA_ATIVIDADE' => $request->atividade,
            'EMPRESA_CONTATO' => $request->contato,
            'EMPRESA_EMAIL' => $request->email,
            'EMPRESA_TELEFONE' => $request->telefone,
            'CONTRATO_NUMERO' => $request->contrato_numero,
            'CONTRATO_INICIO' => $request->contrato_inicio,
            'CONTRATO_FIM' => $request->contrato_fim,
            'STATUS' => 'ATIVO',
            'created_at' => now(),
            'updated_at' => now(),
        ]);

        return response()->json(['ok' => true, 'id' => $id], 201);
    } catch (\Throwable $e) {
        return response()->json(['erro' => $e->getMessage()], 500);
    }
});

// GET /terceirizados/postos — lista postos de trabalho
Route::get('/terceirizados/postos', function (Request $request) {
    try {
        $query = DB::table('TERC_POSTO as p')
            ->join('TERC_EMPRESA as e', 'e.EMPRESA_ID', '=', 'p.EMPRESA_ID')
            ->leftJoin('SETOR as s', 's.SETOR_ID', '=', 'p.SETOR_ID')
            ->leftJoin('UNIDADE as u', 'u.UNIDADE_ID', '=', 's.UNIDADE_ID');

        if ($request->empresa_id) {
            $query->where('p.EMPRESA_ID', $request->empresa_id);
        }

        $postos = $query->select(
            'p.POSTO_ID as id',
            'p.POSTO_DESCRICAO as descricao',
            'p.QUANTIDADE_VAGAS as vagas',
            'p.POSTO_ATIVO as ativo',
            'e.EMPRESA_RAZAO_SOCIAL as empresa',
            's.SETOR_NOME as setor',
            'u.UNIDADE_NOME as secretaria',
            DB::raw('(SELECT MAX(created_at) FROM TERC_CHECKLIST WHERE POSTO_ID = p.POSTO_ID) as ultimo_checklist')
        )
            ->where('p.POSTO_ATIVO', 1)
            ->orderBy('e.EMPRESA_RAZAO_SOCIAL')
            ->get();

        return response()->json(['postos' => $postos]);
    } catch (\Throwable $e) {
        return response()->json(['erro' => $e->getMessage()], 500);
    }
});

// POST /terceirizados/postos/{id}/checklist — registrar checklist mensal
Route::post('/terceirizados/postos/{id}/checklist', function (Request $request, $id) {
    try {
        $user = Auth::user();
        $competencia = $request->competencia ?? now()->format('Y-m');

        $checklistId = DB::table('TERC_CHECKLIST')->insertGetId([
            'POSTO_ID' => $id,
            'COMPETENCIA' => $competencia,
            'FARDAMENTO_OK' => (bool) ($request->fardamento_ok ?? false),
            'EPI_OK' => (bool) ($request->epi_ok ?? false),
            'FOLHA_PONTO_OK' => (bool) ($request->folha_ponto_ok ?? false),
            'HOLERITE_OK' => (bool) ($request->holerite_ok ?? false),
            'FGTS_OK' => (bool) ($request->fgts_ok ?? false),
            'FERIAS_OK' => (bool) ($request->ferias_ok ?? false),
            'OBSERVACOES' => $request->observacoes,
            'CONFERIDO_POR' => $user->USUARIO_ID ?? null,
            'created_at' => now(),
            'updated_at' => now(),
        ]);

        return response()->json(['ok' => true, 'checklist_id' => $checklistId], 201);
    } catch (\Throwable $e) {
        return response()->json(['erro' => $e->getMessage()], 500);
    }
});

// GET /terceirizados/inadimplentes — empresas com checklist atrasado (>35 dias)
Route::get('/terceirizados/inadimplentes', function () {
    try {
        $postos = DB::table('TERC_POSTO as p')
            ->join('TERC_EMPRESA as e', 'e.EMPRESA_ID', '=', 'p.EMPRESA_ID')
            ->leftJoin('SETOR as s', 's.SETOR_ID', '=', 'p.SETOR_ID')
            ->leftJoin('UNIDADE as u', 'u.UNIDADE_ID', '=', 's.UNIDADE_ID')
            ->where('p.POSTO_ATIVO', 1)
            ->select(
                'p.POSTO_ID as id',
                'p.POSTO_DESCRICAO as posto',
                'e.EMPRESA_RAZAO_SOCIAL as empresa',
                'e.EMPRESA_CNPJ as cnpj',
                'e.EMPRESA_EMAIL as email',
                's.SETOR_NOME as setor',
                'u.UNIDADE_NOME as secretaria',
                DB::raw('(SELECT MAX(created_at) FROM TERC_CHECKLIST WHERE POSTO_ID = p.POSTO_ID) as ultimo_checklist')
            )
            ->get()
            ->filter(function ($p) {
                if (!$p->ultimo_checklist)
                    return true;
                return now()->diffInDays($p->ultimo_checklist) > 35;
            })
            ->values();

        return response()->json([
            'inadimplentes' => $postos,
            'total' => $postos->count(),
            'referencia' => now()->toDateString(),
        ]);
    } catch (\Throwable $e) {
        return response()->json(['erro' => $e->getMessage()], 500);
    }
});
