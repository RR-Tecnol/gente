<?php
// CARGOS E SALARIOS - /cargos /funcoes
// Extraido de web.php - herda prefix api/v3 + auth do grupo principal


//  Cargos  Listagem
Route::get('/cargos', function (\Illuminate\Http\Request $request) {
    $query = \App\Models\Cargo::query();
    if ($request->filled('q')) {
        $q = $request->q;
        $query->where(function ($sq) use ($q) {
            $sq->where('CARGO_NOME', 'like', "%$q%")->orWhere('CARGO_SIGLA', 'like', "%$q%");
        });
    }
    if ($request->filled('ativo')) {
        $query->where('CARGO_ATIVO', (int) $request->ativo);
    }
    $cargos = $query->orderBy('CARGO_NOME')->get()->map(fn($c) => [
        'cargo_id' => $c->CARGO_ID,
        'nome' => $c->CARGO_NOME,
        'sigla' => $c->CARGO_SIGLA ?? null,
        'cbo' => $c->CARGO_CODIGO_CBO ?? $c->CARGO_CBO ?? null,
        'descricao' => $c->CARGO_DESCRICAO ?? null,
        'escolaridade' => $c->CARGO_ESCOLARIDADE ?? null,
        'remuneracao' => (float) ($c->CARGO_REMUNERACAO ?? 0),
        'gestao' => (bool) ($c->CARGO_GESTAO ?? false),
        'ativo' => (bool) ($c->CARGO_ATIVO ?? true),
        'data_inicio' => $c->CARGO_DATA_INICIO ?? null,
        'data_fim' => $c->CARGO_DATA_FIM ?? null,
        // campos salariais — Sprint 3a TASK-13
        'carreira' => $c->CARGO_CARREIRA ?? null,
        'classe' => $c->CARGO_CLASSE ?? null,
        'referencia' => $c->CARGO_REFERENCIA ?? null,
        'nivel' => $c->CARGO_NIVEL ?? null,
        'salario_base' => isset($c->CARGO_SALARIO_BASE) ? (float) $c->CARGO_SALARIO_BASE : null,
        'carga_horaria' => $c->CARGO_CARGA_HORARIA ?? null,
    ]);
    return response()->json(['cargos' => $cargos, 'total' => $cargos->count()]);
});

//  Cargos  Criar
Route::post('/cargos', function (\Illuminate\Http\Request $request) {
    try {
        $cargo = new \App\Models\Cargo();
        $cargo->CARGO_NOME = $request->CARGO_NOME;
        $cargo->CARGO_SIGLA = $request->CARGO_SIGLA ?? null;
        $cargo->CARGO_ESCOLARIDADE = $request->CARGO_ESCOLARIDADE ?? null;
        $cargo->CARGO_GESTAO = $request->CARGO_GESTAO ?? 0;
        $cargo->CARGO_ATIVO = 1;
        try {
            $cargo->CARGO_CBO = $request->CARGO_CBO ?? null;
        } catch (\Throwable $e) {
        }
        try {
            $cargo->CARGO_DESCRICAO = $request->CARGO_DESCRICAO ?? null;
        } catch (\Throwable $e) {
        }
        try {
            $cargo->CARGO_DATA_INICIO = $request->CARGO_DATA_INICIO ?? now()->toDateString();
        } catch (\Throwable $e) {
        }
        try {
            $cargo->CARGO_REMUNERACAO = $request->CARGO_REMUNERACAO ?? null;
        } catch (\Throwable $e) {
        }
        $cargo->save();
        \Illuminate\Support\Facades\Log::channel('security')->info('cargo_criado', ['usuario' => \Illuminate\Support\Facades\Auth::id(), 'cargo' => $request->CARGO_NOME]);
        return response()->json(['message' => 'Cargo criado com sucesso.', 'cargo_id' => $cargo->CARGO_ID], 201);
    } catch (\Throwable $e) {
        return response()->json(['erro' => 'Erro ao criar cargo: ' . $e->getMessage()], 500);
    }
});

Route::put('/cargos/{id}', function ($id, \Illuminate\Http\Request $request) {
    try {
        $cargo = \App\Models\Cargo::findOrFail($id);
        $campos = [
            'CARGO_NOME',
            'CARGO_SIGLA',
            'CARGO_ESCOLARIDADE',
            'CARGO_GESTAO',
            'CARGO_CBO',
            'CARGO_DESCRICAO',
            'CARGO_DATA_INICIO',
            'CARGO_DATA_FIM',
            'CARGO_REMUNERACAO',
            // campos salariais — Sprint 3a TASK-13
            'CARGO_CARREIRA',
            'CARGO_CLASSE',
            'CARGO_REFERENCIA',
            'CARGO_NIVEL',
            'CARGO_SALARIO_BASE',
            'CARGO_CARGA_HORARIA',
            'CARGO_CODIGO_CBO',
        ];
        foreach ($campos as $campo) {
            if ($request->has($campo)) {
                try {
                    $cargo->$campo = $request->$campo;
                } catch (\Throwable $e) {
                }
            }
        }
        $cargo->save();
        \Illuminate\Support\Facades\Log::channel('security')->info('cargo_alterado', ['usuario' => \Illuminate\Support\Facades\Auth::id(), 'cargo_id' => $id]);
        return response()->json(['message' => 'Cargo atualizado com sucesso.']);
    } catch (\Throwable $e) {
        return response()->json(['erro' => 'Erro ao atualizar: ' . $e->getMessage()], 500);
    }
});

//  Cargos  Inativar
Route::delete('/cargos/{id}', function ($id, \Illuminate\Http\Request $request) {
    try {
        $cargo = \App\Models\Cargo::findOrFail($id);
        $cargo->CARGO_ATIVO = 0;
        try {
            $cargo->CARGO_DATA_FIM = $request->CARGO_DATA_FIM ?? now()->toDateString();
        } catch (\Throwable $e) {
        }
        $cargo->save();
        return response()->json(['message' => 'Cargo inativado com sucesso.']);
    } catch (\Throwable $e) {
        return response()->json(['erro' => 'Erro ao inativar: ' . $e->getMessage()], 500);
    }
});

//  FunÃ§Ãµes / Cargos em ComissÃ£o  Listagem
Route::get('/funcoes', function (\Illuminate\Http\Request $request) {
    try {
        $query = \App\Models\Atribuicao::query();
        if ($request->filled('q')) {
            $query->where('ATRIBUICAO_NOME', 'like', '%' . $request->q . '%');
        }
        $funcoes = $query->orderBy('ATRIBUICAO_NOME')->get()->map(fn($a) => [
            'funcao_id' => $a->ATRIBUICAO_ID,
            'nome' => $a->ATRIBUICAO_NOME,
            'cbo' => $a->ATRIBUICAO_CBO ?? null,
            'tipo' => $a->ATRIBUICAO_COMISSAO ?? null,
            'gratificacao' => (float) ($a->ATRIBUICAO_GRATIFICACAO ?? 0),
            'ativo' => (bool) ($a->ATRIBUICAO_ATIVO ?? true),
        ]);
        return response()->json(['funcoes' => $funcoes, 'total' => $funcoes->count()]);
    } catch (\Throwable $e) {
        return response()->json(['funcoes' => [], 'total' => 0]);
    }
});

//  FunÃ§Ãµes  Criar
Route::post('/funcoes', function (\Illuminate\Http\Request $request) {
    try {
        $funcao = new \App\Models\Atribuicao();
        $funcao->ATRIBUICAO_NOME = $request->nome ?? $request->ATRIBUICAO_NOME;
        try {
            $funcao->ATRIBUICAO_CBO = $request->cbo ?? null;
        } catch (\Throwable $e) {
        }
        try {
            $funcao->ATRIBUICAO_COMISSAO = $request->tipo ?? null;
        } catch (\Throwable $e) {
        }
        try {
            $funcao->ATRIBUICAO_GRATIFICACAO = $request->gratificacao ?? null;
        } catch (\Throwable $e) {
        }
        $funcao->save();
        return response()->json(['message' => 'FunÃ§Ã£o criada com sucesso.', 'funcao_id' => $funcao->ATRIBUICAO_ID], 201);
    } catch (\Throwable $e) {
        return response()->json(['erro' => 'Erro ao criar funÃ§Ã£o: ' . $e->getMessage()], 500);
    }
});

//  FunÃ§Ãµes  Atualizar
Route::put('/funcoes/{id}', function ($id, \Illuminate\Http\Request $request) {
    try {
        $funcao = \App\Models\Atribuicao::findOrFail($id);
        $funcao->ATRIBUICAO_NOME = $request->nome ?? $request->ATRIBUICAO_NOME ?? $funcao->ATRIBUICAO_NOME;
        try {
            if ($request->has('cbo'))
                $funcao->ATRIBUICAO_CBO = $request->cbo;
        } catch (\Throwable $e) {
        }
        try {
            if ($request->has('tipo'))
                $funcao->ATRIBUICAO_COMISSAO = $request->tipo;
        } catch (\Throwable $e) {
        }
        try {
            if ($request->has('gratificacao'))
                $funcao->ATRIBUICAO_GRATIFICACAO = $request->gratificacao;
        } catch (\Throwable $e) {
        }
        $funcao->save();
        return response()->json(['message' => 'FunÃ§Ã£o atualizada com sucesso.']);
    } catch (\Throwable $e) {
        return response()->json(['erro' => 'Erro ao atualizar: ' . $e->getMessage()], 500);
    }
});

//  FunÃ§Ãµes  Inativar
Route::delete('/funcoes/{id}', function ($id) {
    try {
        $funcao = \App\Models\Atribuicao::findOrFail($id);
        try {
            $funcao->ATRIBUICAO_ATIVO = 0;
            $funcao->save();
        } catch (\Throwable $e) {
            return response()->json(['message' => 'Soft delete nÃ£o suportado nesta tabela.']);
        }
        return response()->json(['message' => 'FunÃ§Ã£o inativada com sucesso.']);
    } catch (\Throwable $e) {
        return response()->json(['erro' => 'Erro ao inativar: ' . $e->getMessage()], 500);
    }
});
