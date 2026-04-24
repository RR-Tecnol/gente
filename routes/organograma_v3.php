<?php
// ORGANOGRAMA CRUD DE SETORES
// Extraido de web.php - herda prefix api/v3 + auth do grupo principal

// GET /api/v3/organograma  Lista setores agrupados por unidade
Route::get('/organograma', function (\Illuminate\Http\Request $request) {
    try {
        $setores = \Illuminate\Support\Facades\DB::table('SETOR')
            ->where('SETOR_ATIVO', 1)
            ->orderBy('SETOR_NOME')
            ->get();

        if ($setores->isEmpty()) {
            return response()->json(['unidades' => [], 'setores_flat' => [], 'unidades_flat' => [], 'fallback' => false]);
        }

        $setorIds = $setores->pluck('SETOR_ID')->values()->all();

        // Buscar funcionários por setor via lotação ativa
        $funcRows = \Illuminate\Support\Facades\DB::table('LOTACAO as l')
            ->join('FUNCIONARIO as f', 'f.FUNCIONARIO_ID', '=', 'l.FUNCIONARIO_ID')
            ->join('PESSOA as p', 'p.PESSOA_ID', '=', 'f.PESSOA_ID')
            ->leftJoin('CARGO as c', 'c.CARGO_ID', '=', 'f.CARGO_ID')
            ->whereIn('l.SETOR_ID', $setorIds)
            ->whereNull('l.LOTACAO_DATA_FIM')
            ->whereNull('f.FUNCIONARIO_DATA_FIM')
            ->orderBy('p.PESSOA_NOME')
            ->select(
                'l.SETOR_ID',
                'f.FUNCIONARIO_ID',
                'p.PESSOA_NOME',
                'f.FUNCIONARIO_MATRICULA',
                'c.CARGO_NOME'
            )
            ->get();

        $funcionarios = [];
        foreach ($funcRows as $f) {
            $funcionarios[$f->SETOR_ID][] = [
                'id' => (int) $f->FUNCIONARIO_ID,
                'nome' => $f->PESSOA_NOME ?? '—',
                'cargo' => $f->CARGO_NOME ?? 'Servidor',
                'matricula' => $f->FUNCIONARIO_MATRICULA ?? null,
            ];
        }

        $contagens = collect($funcionarios)->map(fn($lista) => count($lista));

        // Tentar buscar unidades/diretorias
        $unidadesNomes = [];
        try {
            $unidades = \Illuminate\Support\Facades\DB::table('UNIDADE')
                ->where('UNIDADE_ATIVA', 1)
                ->orderBy('UNIDADE_NOME')
                ->get(['UNIDADE_ID', 'UNIDADE_NOME', 'UNIDADE_SIGLA']);
            foreach ($unidades as $u) {
                $unidadesNomes[$u->UNIDADE_ID] = ['nome' => $u->UNIDADE_NOME, 'sigla' => $u->UNIDADE_SIGLA ?? ''];
            }
        } catch (\Throwable $e) {
        }

        // Responsável: primeiro funcionário lotado no setor (fallback seguro)
        $responsaveis = collect($funcionarios)
            ->map(fn($lista) => $lista[0]['nome'] ?? '')
            ->toArray();

        // Agrupar setores por UNIDADE_ID
        $grupos = $setores->groupBy('UNIDADE_ID');
        $unidadesList = [];
        foreach ($unidadesNomes as $unidadeId => $unidadeInfo) {
            $setoresGrupo = $grupos->get($unidadeId, collect([]));
            $unidadesList[] = [
                'id' => $unidadeId,
                'nome' => $unidadeInfo['nome'],
                'sigla' => $unidadeInfo['sigla'] ?? '',
                'setores' => $setoresGrupo->map(fn($s) => [
                    'id' => $s->SETOR_ID,
                    'nome' => $s->SETOR_NOME ?? '',
                    'sigla' => $s->SETOR_SIGLA ?? null,
                    'unidade_id' => $s->UNIDADE_ID,
                    'responsavel' => $responsaveis[$s->SETOR_ID] ?? '',
                    'total_funcionarios' => $contagens[$s->SETOR_ID] ?? 0,
                    'funcionarios' => $funcionarios[$s->SETOR_ID] ?? [],
                ])->values()->toArray(),
            ];
        }
        
        // Unidades que não estão no cadastro (orphan sectors)
        $orphanGrupos = $grupos->except(array_keys($unidadesNomes));
        foreach ($orphanGrupos as $unidadeId => $setoresGrupo) {
             $unidadesList[] = [
                'id' => $unidadeId,
                'nome' => $unidadeId ? 'Unidade ' . $unidadeId : 'Sem Diretoria',
                'sigla' => '',
                'setores' => $setoresGrupo->map(fn($s) => [
                    'id' => $s->SETOR_ID,
                    'nome' => $s->SETOR_NOME ?? '',
                    'sigla' => $s->SETOR_SIGLA ?? null,
                    'unidade_id' => $s->UNIDADE_ID,
                    'responsavel' => $responsaveis[$s->SETOR_ID] ?? '',
                    'total_funcionarios' => $contagens[$s->SETOR_ID] ?? 0,
                    'funcionarios' => $funcionarios[$s->SETOR_ID] ?? [],
                ])->values()->toArray(),
            ];
        }

        // Setores flat para montar selects de edição
        $setoresFlat = $setores->map(fn($s) => [
            'id' => $s->SETOR_ID,
            'nome' => $s->SETOR_NOME,
            'sigla' => $s->SETOR_SIGLA ?? null,
            'unidade_id' => $s->UNIDADE_ID,
        ])->values()->toArray();

        // Unidades flat para selects
        $unidadesFlat = [];
        foreach ($unidadesNomes as $id => $u) {
            $unidadesFlat[] = ['id' => $id, 'nome' => $u['nome'], 'sigla' => $u['sigla']];
        }

        return response()->json([
            'unidades' => $unidadesList,
            'setores_flat' => $setoresFlat,
            'unidades_flat' => $unidadesFlat,
            'fallback' => false,
        ]);
    } catch (\Throwable $e) {
        \Illuminate\Support\Facades\Log::error('Organograma: ' . $e->getMessage());
        return response()->json(['unidades' => [], 'setores_flat' => [], 'fallback' => true]);
    }
});

// GET /api/v3/organograma/funcionarios  Lista de servidores para vincular no setor
Route::get('/organograma/funcionarios', function () {
    try {
        $rows = \Illuminate\Support\Facades\DB::table('FUNCIONARIO as f')
            ->join('PESSOA as p', 'p.PESSOA_ID', '=', 'f.PESSOA_ID')
            ->leftJoin('CARGO as c', 'c.CARGO_ID', '=', 'f.CARGO_ID')
            ->whereNull('f.FUNCIONARIO_DATA_FIM')
            ->orderBy('p.PESSOA_NOME')
            ->limit(800)
            ->get([
                'f.FUNCIONARIO_ID as id',
                'p.PESSOA_NOME as nome',
                'f.FUNCIONARIO_MATRICULA as matricula',
                'c.CARGO_NOME as cargo',
            ]);

        return response()->json(['funcionarios' => $rows, 'fallback' => false]);
    } catch (\Throwable $e) {
        return response()->json(['funcionarios' => [], 'fallback' => true, 'error' => $e->getMessage()]);
    }
});

// POST /api/v3/organograma/setor  Criar setor
Route::post('/organograma/setor', function (\Illuminate\Http\Request $request) {
    try {
        $nome = trim($request->nome ?? '');
        $sigla = trim($request->sigla ?? '');
        $unidade = $request->unidade_id ?? 0; // 0 é fallback seguro para NOT NULL
        $funcionarioIds = collect($request->funcionario_ids ?? [])
            ->merge($request->funcionario_id ? [(int) $request->funcionario_id] : [])
            ->map(fn($id) => (int) $id)
            ->filter(fn($id) => $id > 0)
            ->unique()
            ->values()
            ->all();

        if (!$nome)
            return response()->json(['error' => 'Nome é obrigatório.'], 422);

        \Illuminate\Support\Facades\DB::beginTransaction();

        $id = \Illuminate\Support\Facades\DB::table('SETOR')->insertGetId([
            'SETOR_NOME' => $nome,
            'SETOR_SIGLA' => $sigla ?: null,
            'UNIDADE_ID' => (int) $unidade,
            'SETOR_ATIVO' => 1,
        ]);

        // Vínculo opcional de funcionário ao setor recém-criado
        foreach ($funcionarioIds as $funcionarioId) {
            $func = \Illuminate\Support\Facades\DB::table('FUNCIONARIO')
                ->where('FUNCIONARIO_ID', $funcionarioId)
                ->whereNull('FUNCIONARIO_DATA_FIM')
                ->first();
            if (!$func) {
                continue;
            }
            // Encerra lotação ativa anterior para manter uma lotação principal ativa
            \Illuminate\Support\Facades\DB::table('LOTACAO')
                ->where('FUNCIONARIO_ID', $funcionarioId)
                ->whereNull('LOTACAO_DATA_FIM')
                ->update(['LOTACAO_DATA_FIM' => now()->toDateString()]);
            \Illuminate\Support\Facades\DB::table('LOTACAO')->insert([
                'FUNCIONARIO_ID' => $funcionarioId,
                'SETOR_ID' => $id,
                'LOTACAO_DATA_INICIO' => now()->toDateString(),
                'LOTACAO_DATA_FIM' => null,
                'VINCULO_ID' => null,
            ]);
        }

        \Illuminate\Support\Facades\DB::commit();

        return response()->json([
            'id' => $id,
            'nome' => $nome,
            'sigla' => $sigla ?: null,
            'unidade_id' => $unidade,
            'funcionario_ids' => $funcionarioIds,
            'message' => 'Setor criado com sucesso!',
        ], 201);
    } catch (\Throwable $e) {
        \Illuminate\Support\Facades\DB::rollBack();
        return response()->json(['error' => 'Erro ao criar setor: ' . $e->getMessage()], 500);
    }
});

// PUT /api/v3/organograma/setor/{id}  Editar setor
Route::put('/organograma/setor/{id}', function (\Illuminate\Http\Request $request, $id) {
    try {
        $nome = trim($request->nome ?? '');
        $sigla = trim($request->sigla ?? '');
        if (!$nome)
            return response()->json(['error' => 'Nome é obrigatório.'], 422);

        // Verifica se o setor existe antes de tentar atualizar
        $setor = \Illuminate\Support\Facades\DB::table('SETOR')->where('SETOR_ID', $id)->first();
        if (!$setor)
            return response()->json(['error' => 'Setor não encontrado.'], 404);

        // UNIDADE_ID não pode ser NULL (NOT NULL na tabela SETOR) — usa 0 como fallback
        $unidadeId = $request->unidade_id ? (int) $request->unidade_id : ($setor->UNIDADE_ID ?? 0);

        \Illuminate\Support\Facades\DB::table('SETOR')
            ->where('SETOR_ID', $id)
            ->update([
                'SETOR_NOME' => $nome,
                'SETOR_SIGLA' => $sigla ?: null,
                'UNIDADE_ID' => $unidadeId,
            ]);

        $funcionarioIds = collect($request->funcionario_ids ?? [])
            ->merge($request->funcionario_id ? [(int) $request->funcionario_id] : [])
            ->map(fn($fid) => (int) $fid)
            ->filter(fn($fid) => $fid > 0)
            ->unique()
            ->values()
            ->all();

        foreach ($funcionarioIds as $funcionarioId) {
            $func = \Illuminate\Support\Facades\DB::table('FUNCIONARIO')
                ->where('FUNCIONARIO_ID', $funcionarioId)
                ->whereNull('FUNCIONARIO_DATA_FIM')
                ->first();
            if (!$func) {
                continue;
            }
            // Evita recriar lotação se já estiver ativo no setor alvo
            $jaNoSetor = \Illuminate\Support\Facades\DB::table('LOTACAO')
                ->where('FUNCIONARIO_ID', $funcionarioId)
                ->where('SETOR_ID', $id)
                ->whereNull('LOTACAO_DATA_FIM')
                ->exists();
            if ($jaNoSetor) {
                continue;
            }
            \Illuminate\Support\Facades\DB::table('LOTACAO')
                ->where('FUNCIONARIO_ID', $funcionarioId)
                ->whereNull('LOTACAO_DATA_FIM')
                ->update(['LOTACAO_DATA_FIM' => now()->toDateString()]);
            \Illuminate\Support\Facades\DB::table('LOTACAO')->insert([
                'FUNCIONARIO_ID' => $funcionarioId,
                'SETOR_ID' => $id,
                'LOTACAO_DATA_INICIO' => now()->toDateString(),
                'LOTACAO_DATA_FIM' => null,
                'VINCULO_ID' => null,
            ]);
        }

        return response()->json(['message' => 'Setor atualizado!', 'id' => (int) $id, 'funcionario_ids' => $funcionarioIds]);
    } catch (\Throwable $e) {
        return response()->json(['error' => 'Erro ao editar setor: ' . $e->getMessage()], 500);
    }
});

// DELETE /api/v3/organograma/setor/{id}  Excluir setor (soft-delete)
Route::delete('/organograma/setor/{id}', function (\Illuminate\Http\Request $request, $id) {
    try {
        \Illuminate\Support\Facades\DB::table('SETOR')
            ->where('SETOR_ID', $id)
            ->update(['SETOR_ATIVO' => 0]);

        return response()->json(['message' => 'Setor removido!']);
    } catch (\Throwable $e) {
        return response()->json(['error' => 'Erro ao remover setor.'], 500);
    }
});

// â”€â”€ CRUD Diretorias (UNIDADE) â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€
// POST /api/v3/organograma/diretoria  â€” Criar nova diretoria
Route::post('/organograma/diretoria', function (\Illuminate\Http\Request $request) {
    try {
        $nome = trim($request->nome ?? '');
        if (!$nome)
            return response()->json(['error' => 'Nome é obrigatório.'], 422);

        $id = \Illuminate\Support\Facades\DB::table('UNIDADE')->insertGetId([
            'UNIDADE_NOME' => $nome,
            'UNIDADE_SIGLA' => trim($request->sigla ?? '') ?: null,
            'UNIDADE_ATIVA' => 1,
            'UNIDADE_TIPO' => 0,
        ]);

        return response()->json([
            'id' => $id,
            'nome' => $nome,
            'sigla' => trim($request->sigla ?? '') ?: null,
            'message' => 'Diretoria criada com sucesso!',
        ], 201);
    } catch (\Throwable $e) {
        return response()->json(['error' => 'Erro ao criar diretoria: ' . $e->getMessage()], 500);
    }
});

// PUT /api/v3/organograma/diretoria/{id}  â€” Editar diretoria
Route::put('/organograma/diretoria/{id}', function (\Illuminate\Http\Request $request, $id) {
    try {
        $nome = trim($request->nome ?? '');
        if (!$nome)
            return response()->json(['error' => 'Nome é obrigatório.'], 422);

        $diretoria = \Illuminate\Support\Facades\DB::table('UNIDADE')->where('UNIDADE_ID', $id)->first();
        if (!$diretoria)
            return response()->json(['error' => 'Diretoria não encontrada.'], 404);

        \Illuminate\Support\Facades\DB::table('UNIDADE')
            ->where('UNIDADE_ID', $id)
            ->update([
                'UNIDADE_NOME' => $nome,
                'UNIDADE_SIGLA' => trim($request->sigla ?? '') ?: null,
            ]);

        return response()->json(['message' => 'Diretoria atualizada!', 'id' => (int) $id]);
    } catch (\Throwable $e) {
        return response()->json(['error' => 'Erro ao editar diretoria: ' . $e->getMessage()], 500);
    }
});

// DELETE /api/v3/organograma/diretoria/{id}  â€” Excluir diretoria (soft-delete)
Route::delete('/organograma/diretoria/{id}', function ($id) {
    try {
        \Illuminate\Support\Facades\DB::table('UNIDADE')
            ->where('UNIDADE_ID', $id)
            ->update(['UNIDADE_ATIVA' => 0]);

        return response()->json(['message' => 'Diretoria removida!']);
    } catch (\Throwable $e) {
        return response()->json(['error' => 'Erro ao remover diretoria.'], 500);
    }
});
