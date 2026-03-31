<?php
// ORGANOGRAMA CRUD DE SETORES
// Extraido de web.php - herda prefix api/v3 + auth do grupo principal

// GET /api/v3/organograma  Lista setores agrupados por unidade
Route::get('/organograma', function (\Illuminate\Http\Request $request) {
    try {
        // Buscar setores ativos
        $setores = \Illuminate\Support\Facades\DB::table('SETOR')
            ->where('SETOR_ATIVO', 1)
            ->orderBy('SETOR_NOME')
            ->get();

        if ($setores->isEmpty()) {
            return response()->json(['unidades' => [], 'setores_flat' => [], 'fallback' => true]);
        }

        // Contar funcionÃ¡rios por setor
        $contagens = \Illuminate\Support\Facades\DB::table('FUNCIONARIO')
            ->whereIn('SETOR_ID', $setores->pluck('SETOR_ID'))
            ->whereNull('FUNCIONARIO_DATA_DEMISSAO')
            ->select('SETOR_ID', \Illuminate\Support\Facades\DB::raw('COUNT(*) as total'))
            ->groupBy('SETOR_ID')
            ->pluck('total', 'SETOR_ID');

        // Buscar funcionÃ¡rios por setor (nome + cargo)
        $funcionarios = [];
        $funcRows = \App\Models\Funcionario::whereIn('SETOR_ID', $setores->pluck('SETOR_ID'))
            ->whereNull('FUNCIONARIO_DATA_DEMISSAO')
            ->with('cargo')
            ->get();
        foreach ($funcRows as $f) {
            $funcionarios[$f->SETOR_ID][] = [
                'nome' => trim(($f->FUNCIONARIO_NOME ?? '') . ' ' . ($f->FUNCIONARIO_SOBRENOME ?? '')),
                'cargo' => $f->cargo?->CARGO_NOME ?? $f->CARGO_NOME ?? '',
            ];
        }

        // Tentar buscar unidades/diretorias
        $unidadesNomes = [];
        try {
            $unidades = \Illuminate\Support\Facades\DB::table('UNIDADE')
                ->orderBy('UNIDADE_NOME')
                ->get(['UNIDADE_ID', 'UNIDADE_NOME', 'UNIDADE_SIGLA']);
            foreach ($unidades as $u) {
                $unidadesNomes[$u->UNIDADE_ID] = ['nome' => $u->UNIDADE_NOME, 'sigla' => $u->UNIDADE_SIGLA ?? ''];
            }
        } catch (\Throwable $e) {
        }

        // ResponsÃ¡vel: funcionÃ¡rio com cargo de chefia no setor
        $responsaveis = \Illuminate\Support\Facades\DB::table('FUNCIONARIO')
            ->whereIn('SETOR_ID', $setores->pluck('SETOR_ID'))
            ->whereNull('FUNCIONARIO_DATA_DEMISSAO')
            ->orderBy('FUNCIONARIO_ID')
            ->take(200)
            ->get(['SETOR_ID', 'FUNCIONARIO_NOME', 'FUNCIONARIO_SOBRENOME'])
            ->groupBy('SETOR_ID')
            ->map(fn($g) => trim(($g->first()->FUNCIONARIO_NOME ?? '') . ' ' . ($g->first()->FUNCIONARIO_SOBRENOME ?? '')))
            ->toArray();

        // Agrupar setores por UNIDADE_ID
        $grupos = $setores->groupBy('UNIDADE_ID');
        $unidadesList = [];
        foreach ($grupos as $unidadeId => $setoresGrupo) {
            $nomeUnidade = $unidadesNomes[$unidadeId]['nome']
                ?? ($unidadeId ? 'Unidade ' . $unidadeId : 'Sem Diretoria');
            $unidadesList[] = [
                'id' => $unidadeId,
                'nome' => $nomeUnidade,
                'sigla' => $unidadesNomes[$unidadeId]['sigla'] ?? '',
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

        // Setores flat para montar selects de ediÃ§Ã£o
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

// POST /api/v3/organograma/setor  Criar setor
Route::post('/organograma/setor', function (\Illuminate\Http\Request $request) {
    try {
        $nome = trim($request->nome ?? '');
        $sigla = trim($request->sigla ?? '');
        $unidade = $request->unidade_id ?? 0; // 0 Ã© fallback seguro para NOT NULL

        if (!$nome)
            return response()->json(['error' => 'Nome Ã© obrigatÃ³rio.'], 422);

        $id = \Illuminate\Support\Facades\DB::table('SETOR')->insertGetId([
            'SETOR_NOME' => $nome,
            'SETOR_SIGLA' => $sigla ?: null,
            'UNIDADE_ID' => (int) $unidade,
            'SETOR_ATIVO' => 1,
        ]);

        return response()->json([
            'id' => $id,
            'nome' => $nome,
            'sigla' => $sigla ?: null,
            'unidade_id' => $unidade,
            'message' => 'Setor criado com sucesso!',
        ], 201);
    } catch (\Throwable $e) {
        return response()->json(['error' => 'Erro ao criar setor.'], 500);
    }
});

// PUT /api/v3/organograma/setor/{id}  Editar setor
Route::put('/organograma/setor/{id}', function (\Illuminate\Http\Request $request, $id) {
    try {
        $nome = trim($request->nome ?? '');
        $sigla = trim($request->sigla ?? '');
        if (!$nome)
            return response()->json(['error' => 'Nome Ã© obrigatÃ³rio.'], 422);

        // Verifica se o setor existe antes de tentar atualizar
        $setor = \Illuminate\Support\Facades\DB::table('SETOR')->where('SETOR_ID', $id)->first();
        if (!$setor)
            return response()->json(['error' => 'Setor nÃ£o encontrado.'], 404);

        // UNIDADE_ID nÃ£o pode ser NULL (NOT NULL na tabela SETOR) â€” usa 0 como fallback
        $unidadeId = $request->unidade_id ? (int) $request->unidade_id : ($setor->UNIDADE_ID ?? 0);

        \Illuminate\Support\Facades\DB::table('SETOR')
            ->where('SETOR_ID', $id)
            ->update([
                'SETOR_NOME' => $nome,
                'SETOR_SIGLA' => $sigla ?: null,
                'UNIDADE_ID' => $unidadeId,
            ]);

        return response()->json(['message' => 'Setor atualizado!', 'id' => (int) $id]);
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
            return response()->json(['error' => 'Nome Ã© obrigatÃ³rio.'], 422);

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
            return response()->json(['error' => 'Nome Ã© obrigatÃ³rio.'], 422);

        $diretoria = \Illuminate\Support\Facades\DB::table('UNIDADE')->where('UNIDADE_ID', $id)->first();
        if (!$diretoria)
            return response()->json(['error' => 'Diretoria nÃ£o encontrada.'], 404);

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
