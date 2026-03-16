<?php
// ══════════════════════════════════════════════════════════════════
// PSS / PROCESSO SELETIVO SIMPLIFICADO
// LAT-03 / GAP-11
// ⚠️ NÃO abrir Route::middleware()->prefix()->group() aqui
//    O contexto api/v3 + auth já é herdado do web.php
//
// REGRA §16 regras-gerais.md:
//   POST /pss/candidatos/{id}/nomear → cria PESSOA + FUNCIONARIO + LOTACAO
//   em uma única transaction para evitar dados inconsistentes.
// ══════════════════════════════════════════════════════════════════

// GET /pss/editais — lista editais com contadores
Route::get('/pss/editais', function (Request $request) {
    try {
        $editais = DB::table('PSS_EDITAL as e')
            ->select(
                'e.EDITAL_ID as id',
                'e.EDITAL_NUMERO as numero',
                'e.EDITAL_TITULO as titulo',
                'e.DATA_ABERTURA as data_abertura',
                'e.DATA_ENCERRAMENTO as data_encerramento',
                'e.STATUS as status',
                DB::raw('(SELECT COUNT(*) FROM PSS_CANDIDATO WHERE EDITAL_ID = e.EDITAL_ID) as total_candidatos'),
                DB::raw('(SELECT COUNT(*) FROM PSS_CANDIDATO WHERE EDITAL_ID = e.EDITAL_ID AND STATUS = \'NOMEADO\') as nomeados')
            )
            ->orderByDesc('e.created_at')
            ->get();

        return response()->json(['editais' => $editais]);
    } catch (\Throwable $e) {
        return response()->json(['erro' => $e->getMessage()], 500);
    }
});

// POST /pss/editais — criar edital
Route::post('/pss/editais', function (Request $request) {
    try {
        $user = Auth::user();
        $id = DB::table('PSS_EDITAL')->insertGetId([
            'EDITAL_NUMERO' => $request->numero,
            'EDITAL_TITULO' => $request->titulo,
            'EDITAL_DESCRICAO' => $request->descricao,
            'DATA_ABERTURA' => $request->data_abertura,
            'DATA_ENCERRAMENTO' => $request->data_encerramento,
            'VAGAS' => $request->vagas ?? 0,
            'CARGO_ID' => $request->cargo_id,
            'STATUS' => 'RASCUNHO',
            'CRIADO_POR' => $user->USUARIO_ID ?? null,
            'created_at' => now(),
            'updated_at' => now(),
        ]);
        return response()->json(['ok' => true, 'id' => $id], 201);
    } catch (\Throwable $e) {
        return response()->json(['erro' => $e->getMessage()], 500);
    }
});

// POST /pss/editais/{id}/publicar — publicar edital
Route::post('/pss/editais/{id}/publicar', function (Request $request, $id) {
    try {
        DB::table('PSS_EDITAL')->where('EDITAL_ID', $id)->update([
            'STATUS' => 'PUBLICADO',
            'DATA_PUBLICACAO' => now()->toDateString(),
            'updated_at' => now(),
        ]);
        return response()->json(['ok' => true]);
    } catch (\Throwable $e) {
        return response()->json(['erro' => $e->getMessage()], 500);
    }
});

// GET /pss/editais/{id}/candidatos — lista candidatos de um edital
Route::get('/pss/editais/{id}/candidatos', function ($id) {
    try {
        $candidatos = DB::table('PSS_CANDIDATO as c')
            ->leftJoin('CARGO as ca', 'ca.CARGO_ID', '=', 'c.CARGO_ID')
            ->where('c.EDITAL_ID', $id)
            ->select(
                'c.CANDIDATO_ID as id',
                'c.NOME as nome',
                'c.CPF as cpf',
                'c.EMAIL as email',
                'c.TELEFONE as telefone',
                'c.NOTA_FINAL as nota',
                'c.CLASSIFICACAO as classificacao',
                'c.STATUS as status',
                'ca.CARGO_NOME as cargo',
                'c.DATA_CONVOCACAO',
                'c.DATA_POSSE',
                'c.FUNCIONARIO_ID'
            )
            ->orderBy('c.CLASSIFICACAO')
            ->get();

        return response()->json(['candidatos' => $candidatos]);
    } catch (\Throwable $e) {
        return response()->json(['erro' => $e->getMessage()], 500);
    }
});

// POST /pss/candidatos — inscrever candidato
Route::post('/pss/candidatos', function (Request $request) {
    try {
        if (!$request->edital_id || !$request->nome || !$request->cpf) {
            return response()->json(['erro' => 'edital_id, nome e cpf são obrigatórios.'], 422);
        }

        // Verificar duplicidade por CPF no mesmo edital
        $jaInscrito = DB::table('PSS_CANDIDATO')
            ->where('EDITAL_ID', $request->edital_id)
            ->where('CPF', preg_replace('/\D/', '', $request->cpf))
            ->exists();

        if ($jaInscrito) {
            return response()->json(['erro' => 'Candidato já inscrito neste edital.'], 422);
        }

        $id = DB::table('PSS_CANDIDATO')->insertGetId([
            'EDITAL_ID' => $request->edital_id,
            'CARGO_ID' => $request->cargo_id,
            'NOME' => $request->nome,
            'CPF' => preg_replace('/\D/', '', $request->cpf),
            'EMAIL' => $request->email,
            'TELEFONE' => $request->telefone,
            'NOTA_FINAL' => $request->nota_final,
            'CLASSIFICACAO' => $request->classificacao,
            'STATUS' => 'INSCRITO',
            'created_at' => now(),
            'updated_at' => now(),
        ]);

        return response()->json(['ok' => true, 'id' => $id], 201);
    } catch (\Throwable $e) {
        return response()->json(['erro' => $e->getMessage()], 500);
    }
});

// PATCH /pss/candidatos/{id}/convocar — convocar candidato
Route::patch('/pss/candidatos/{id}/convocar', function (Request $request, $id) {
    try {
        DB::table('PSS_CANDIDATO')->where('CANDIDATO_ID', $id)->update([
            'STATUS' => 'CONVOCADO',
            'DATA_CONVOCACAO' => now()->toDateString(),
            'OBSERVACAO' => $request->observacao,
            'updated_at' => now(),
        ]);
        return response()->json(['ok' => true]);
    } catch (\Throwable $e) {
        return response()->json(['erro' => $e->getMessage()], 500);
    }
});

// POST /pss/candidatos/{id}/nomear — nomear: cria PESSOA + FUNCIONARIO + LOTACAO em transaction
// Segue OBRIGATORIAMENTE a regra §16 de regras-gerais.md
Route::post('/pss/candidatos/{id}/nomear', function (Request $request, $id) {
    try {
        if (!$request->cargo_id || !$request->setor_id || !$request->data_posse) {
            return response()->json(['erro' => 'cargo_id, setor_id e data_posse são obrigatórios.'], 422);
        }

        $candidato = DB::table('PSS_CANDIDATO')->where('CANDIDATO_ID', $id)->first();
        if (!$candidato) {
            return response()->json(['erro' => 'Candidato não encontrado.'], 404);
        }
        if ($candidato->STATUS === 'NOMEADO') {
            return response()->json(['erro' => 'Candidato já nomeado.'], 422);
        }

        $func_id = DB::transaction(function () use ($candidato, $request) {
            // 1. PESSOA — verificar duplicidade por CPF antes de criar (§16)
            $cpf = preg_replace('/\D/', '', $candidato->CPF ?? '');
            $pessoa_id = $cpf
                ? DB::table('PESSOA')->where('PESSOA_CPF_NUMERO', $cpf)->value('PESSOA_ID')
                : null;

            if (!$pessoa_id) {
                $pessoa_id = DB::table('PESSOA')->insertGetId([
                    'PESSOA_NOME' => $candidato->NOME,
                    'PESSOA_CPF_NUMERO' => $cpf,
                    'PESSOA_DATA_CADASTRO' => now()->toDateString(),
                    'created_at' => now(),
                    'updated_at' => now(),
                ]);
            }

            // 2. FUNCIONARIO — nunca criar sem verificar duplicidade (§16)
            if (DB::table('FUNCIONARIO')->where('PESSOA_ID', $pessoa_id)->exists()) {
                throw new \Exception('Servidor já cadastrado para este CPF.');
            }

            $matricula = $request->matricula ?? strtoupper(substr(md5($pessoa_id . time()), 0, 8));

            $func_id = DB::table('FUNCIONARIO')->insertGetId([
                'PESSOA_ID' => $pessoa_id,
                'CARGO_ID' => $request->cargo_id,
                'FUNCIONARIO_MATRICULA' => $matricula,
                'FUNCIONARIO_DATA_INICIO' => $request->data_posse,
                'FUNCIONARIO_REGIME_PREV' => $request->regime_prev ?? 'RPPS',
                'created_at' => now(),
                'updated_at' => now(),
            ]);

            // 3. LOTACAO — vinculação imediata (§16)
            DB::table('LOTACAO')->insert([
                'FUNCIONARIO_ID' => $func_id,
                'SETOR_ID' => $request->setor_id,
                'LOTACAO_DATA_INICIO' => $request->data_posse,
                'LOTACAO_DATA_FIM' => null,
                'created_at' => now(),
                'updated_at' => now(),
            ]);

            // 4. Atualizar status do candidato
            DB::table('PSS_CANDIDATO')->where('CANDIDATO_ID', $candidato->CANDIDATO_ID)
                ->update([
                    'STATUS' => 'NOMEADO',
                    'FUNCIONARIO_ID' => $func_id,
                    'DATA_POSSE' => $request->data_posse,
                    'updated_at' => now(),
                ]);

            return $func_id;
        });

        return response()->json([
            'ok' => true,
            'funcionario_id' => $func_id,
            'mensagem' => 'Candidato nomeado. FUNCIONARIO + PESSOA + LOTACAO criados com sucesso.',
        ], 201);
    } catch (\Throwable $e) {
        return response()->json(['erro' => $e->getMessage()], 422);
    }
});
