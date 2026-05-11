<?php
// CARGOS E SALÁRIOS — /cargos /funcoes
// Incluído no grupo /api/v3 com web + auth + audit (web.php)
// CRUD de cargo com CARGO_DATA_INICIO obrigatório e verificação de sobreposição (CargoVigencia).

use App\Support\CargoVigencia;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Schema;
use Illuminate\Support\Facades\Validator;

// ── Listagem (GET) ─────────────────────────────────────────────
Route::get('/cargos', function (\Illuminate\Http\Request $request) {
    try {
        $q = $request->q ?? '';
        $query = DB::table('CARGO');
        $cargoCols = Schema::getColumnListing('CARGO');
        $temValorHoraDesconto = in_array('CARGO_VALOR_HORA_DESCONTO', $cargoCols, true);
        $colCbo = in_array('CARGO_CBO', $cargoCols, true)
            ? 'CARGO_CBO'
            : (in_array('CARGO_CODIGO_CBO', $cargoCols, true) ? 'CARGO_CODIGO_CBO' : null);
        if ($q) {
            $query->where(function ($x) use ($q, $colCbo) {
                $x->where('CARGO_NOME', 'like', "%$q%")
                    ->orWhere('CARGO_SIGLA', 'like', "%$q%");
                if ($colCbo) {
                    $x->orWhere($colCbo, 'like', "%$q%");
                }
            });
        }
        if ($request->filled('ativo')) {
            $query->where('CARGO_ATIVO', (int) $request->ativo);
        }

        $cargos = $query->orderBy('CARGO_NOME')->get()->map(function ($c) use ($cargoCols, $temValorHoraDesconto, $colCbo) {
            $row = [
                'cargo_id' => $c->CARGO_ID,
                'nome' => $c->CARGO_NOME,
                'sigla' => $c->CARGO_SIGLA ?? null,
                'cbo' => $colCbo ? ($c->{$colCbo} ?? null) : null,
                'descricao' => $c->CARGO_DESCRICAO ?? null,
                // PMSL usa CARGO_SALARIO (legado) — fallback schema-defensive
                'remuneracao' => (float) ($c->CARGO_REMUNERACAO
                    ?? $c->CARGO_SALARIO
                    ?? 0) ?: null,
                'valor_hora_desconto' => $temValorHoraDesconto ? ((float) ($c->CARGO_VALOR_HORA_DESCONTO ?? 0) ?: null) : null,
                'escolaridade' => $c->CARGO_ESCOLARIDADE ?? null,
                'gestao' => (bool) ($c->CARGO_GESTAO ?? false),
                'ativo' => (bool) ($c->CARGO_ATIVO ?? true),
                'data_inicio' => $c->CARGO_DATA_INICIO ?? null,
                'data_fim' => in_array('CARGO_DATA_FIM', $cargoCols, true) ? ($c->CARGO_DATA_FIM ?? null) : null,
            ];
            if (in_array('CARGO_CARREIRA', $cargoCols, true)) {
                $row['carreira'] = $c->CARGO_CARREIRA ?? null;
            }
            if (in_array('CARGO_CLASSE', $cargoCols, true)) {
                $row['classe'] = $c->CARGO_CLASSE ?? null;
            }
            if (in_array('CARGO_REFERENCIA', $cargoCols, true)) {
                $row['referencia'] = $c->CARGO_REFERENCIA ?? null;
            }
            if (in_array('CARGO_NIVEL', $cargoCols, true)) {
                $row['nivel'] = $c->CARGO_NIVEL ?? null;
            }
            if (in_array('CARGO_SALARIO_BASE', $cargoCols, true)) {
                $row['salario_base'] = isset($c->CARGO_SALARIO_BASE) ? (float) $c->CARGO_SALARIO_BASE : null;
            }
            if (in_array('CARGO_CARGA_HORARIA', $cargoCols, true)) {
                $row['carga_horaria'] = $c->CARGO_CARGA_HORARIA ?? null;
            }

            return $row;
        });

        return response()->json(['cargos' => $cargos, 'total' => $cargos->count()]);
    } catch (\Throwable $e) {
        return response()->json(['cargos' => [], 'total' => 0, 'erro' => $e->getMessage()]);
    }
});

// ── Criar (POST) ────────────────────────────────────────────
Route::post('/cargos', function (\Illuminate\Http\Request $request) {
    try {
        $cargoCols = Schema::getColumnListing('CARGO');
        $temDataInicio = in_array('CARGO_DATA_INICIO', $cargoCols, true);
        $temDataFim = in_array('CARGO_DATA_FIM', $cargoCols, true);
        $regras = [
            'CARGO_NOME' => 'required|string|min:3|max:120',
            'CARGO_SIGLA' => 'nullable|string|max:20',
            'CARGO_CBO' => ['nullable', 'regex:/^\d{6}$/'],
            'CARGO_CODIGO_CBO' => ['nullable', 'regex:/^\d{6}$/'],
            'CARGO_REMUNERACAO' => 'nullable|numeric|min:0',
            'CARGO_VALOR_HORA_DESCONTO' => 'nullable|numeric|min:0',
            // CARGO_DATA_INICIO: obrigatório só se a coluna existir no schema.
            // No schema PMSL não existe — vigência pertence ao vínculo, não ao cargo.
            'CARGO_DATA_INICIO' => $temDataInicio ? 'required|date' : 'nullable|date',
            'CARGO_DATA_FIM' => 'nullable|date',
        ];
        if (in_array('CARGO_DATA_FIM', $cargoCols, true)) {
            $regras['CARGO_DATA_FIM'] = 'nullable|date|after_or_equal:CARGO_DATA_INICIO';
        }
        $validator = Validator::make($request->all(), $regras, [
            'CARGO_NOME.required' => 'Nome obrigatório.',
            'CARGO_DATA_INICIO.required' => 'Data de início de vigência (CARGO_DATA_INICIO) é obrigatória, conforme histórico imutável (eSocial).',
            'CARGO_CBO.regex' => 'CBO deve conter 6 dígitos.',
            'CARGO_CODIGO_CBO.regex' => 'CBO deve conter 6 dígitos.',
        ]);
        if ($validator->fails()) {
            return response()->json(['erro' => $validator->errors()->first()], 422);
        }
        $inicio = $temDataInicio && $request->filled('CARGO_DATA_INICIO')
            ? substr((string) $request->CARGO_DATA_INICIO, 0, 10)
            : now()->format('Y-m-d'); // fallback neutro quando coluna não existe
        $fim = $temDataFim && $request->filled('CARGO_DATA_FIM')
            ? substr((string) $request->CARGO_DATA_FIM, 0, 10) : null;
        // assertSemSobreposicao retorna null imediatamente se CARGO_DATA_INICIO
        // não existir no schema (ver CargoVigencia.php)
        $vig = \App\Support\CargoVigencia::assertSemSobreposicao(
            (string) $request->CARGO_NOME,
            $request->CARGO_SIGLA,
            $inicio,
            $fim,
            null
        );
        if ($vig) {
            return response()->json(['erro' => $vig], 422);
        }
        $payloadBase = [
            'CARGO_NOME' => $request->CARGO_NOME,
            'CARGO_SIGLA' => $request->CARGO_SIGLA ?? null,
            'CARGO_CBO' => $request->CARGO_CBO ?? null,
            'CARGO_CODIGO_CBO' => $request->CARGO_CBO ?? $request->CARGO_CODIGO_CBO ?? null,
            'CARGO_DESCRICAO' => $request->CARGO_DESCRICAO ?? null,
            'CARGO_REMUNERACAO' => $request->CARGO_REMUNERACAO ?? null,
            // PMSL usa CARGO_SALARIO (campo legado) — mapear do valor enviado pelo formulário
            'CARGO_SALARIO' => $request->CARGO_REMUNERACAO ?? $request->CARGO_SALARIO ?? null,
            'CARGO_ESCOLARIDADE' => $request->CARGO_ESCOLARIDADE ?? null,
            'CARGO_GESTAO' => (int) ($request->CARGO_GESTAO ?? 0),
            'CARGO_ATIVO' => 1,
            'CARGO_DATA_INICIO' => $inicio,
            'CARGO_DATA_FIM' => in_array('CARGO_DATA_FIM', $cargoCols, true) ? $fim : null,
            'CARGO_VALOR_HORA_DESCONTO' => $request->CARGO_VALOR_HORA_DESCONTO ?? null,
            'CARGO_CARREIRA' => $request->CARGO_CARREIRA ?? null,
            'CARGO_CLASSE' => $request->CARGO_CLASSE ?? null,
            'CARGO_REFERENCIA' => $request->CARGO_REFERENCIA ?? null,
            'CARGO_NIVEL' => $request->CARGO_NIVEL ?? null,
            'CARGO_SALARIO_BASE' => $request->CARGO_SALARIO_BASE ?? $request->CARGO_REMUNERACAO ?? null,
            'CARGO_CARGA_HORARIA' => $request->CARGO_CARGA_HORARIA ?? null,
        ];
        if (in_array('created_at', $cargoCols, true)) {
            $payloadBase['created_at'] = now();
        }
        if (in_array('updated_at', $cargoCols, true)) {
            $payloadBase['updated_at'] = now();
        }
        $payload = array_intersect_key($payloadBase, array_flip($cargoCols));
        $id = DB::table('CARGO')->insertGetId($payload);
        try {
            Log::channel('security')->info('cargo_criado', [
                'usuario' => \Illuminate\Support\Facades\Auth::id(),
                'cargo_id' => $id,
                'nome' => $request->CARGO_NOME,
            ]);
        } catch (\Throwable $e) {
        }

        return response()->json(['ok' => true, 'cargo_id' => $id, 'message' => 'Cargo criado com sucesso.'], 201);
    } catch (\Throwable $e) {
        Log::error('Erro ao criar cargo', ['erro' => $e->getMessage()]);

        return response()->json(['erro' => 'Erro ao criar cargo. Verifique os campos obrigatórios e tente novamente.'], 500);
    }
});

// ── Atualizar (PUT) ─────────────────────────────────────────
Route::put('/cargos/{id}', function (\Illuminate\Http\Request $request, $id) {
    try {
        $atual = DB::table('CARGO')->where('CARGO_ID', $id)->first();
        if (!$atual) {
            return response()->json(['erro' => 'Cargo não encontrado.'], 404);
        }
        $cargoCols = Schema::getColumnListing('CARGO');
        $temDataInicio = in_array('CARGO_DATA_INICIO', $cargoCols, true);
        $temDataFim = in_array('CARGO_DATA_FIM', $cargoCols, true);
        $regras = [
            'CARGO_NOME' => 'required|string|min:3|max:120',
            'CARGO_SIGLA' => 'nullable|string|max:20',
            'CARGO_CBO' => ['nullable', 'regex:/^\d{6}$/'],
            'CARGO_CODIGO_CBO' => ['nullable', 'regex:/^\d{6}$/'],
            'CARGO_REMUNERACAO' => 'nullable|numeric|min:0',
            'CARGO_VALOR_HORA_DESCONTO' => 'nullable|numeric|min:0',
            // CARGO_DATA_INICIO: obrigatório só se a coluna existir no schema.
            'CARGO_DATA_INICIO' => $temDataInicio ? 'required|date' : 'nullable|date',
            'CARGO_DATA_FIM' => 'nullable|date',
        ];
        if (in_array('CARGO_DATA_FIM', $cargoCols, true)) {
            $regras['CARGO_DATA_FIM'] = 'nullable|date|after_or_equal:CARGO_DATA_INICIO';
        }
        $validator = Validator::make($request->all(), $regras, [
            'CARGO_NOME.required' => 'Nome obrigatório.',
            'CARGO_DATA_INICIO.required' => 'Data de início de vigência (CARGO_DATA_INICIO) é obrigatória; alterações de cargo devem manter rastreabilidade por período.',
            'CARGO_CBO.regex' => 'CBO deve conter 6 dígitos.',
            'CARGO_CODIGO_CBO.regex' => 'CBO deve conter 6 dígitos.',
        ]);
        if ($validator->fails()) {
            return response()->json(['erro' => $validator->errors()->first()], 422);
        }
        $inicio = $temDataInicio && $request->filled('CARGO_DATA_INICIO')
            ? substr((string) $request->CARGO_DATA_INICIO, 0, 10)
            : (isset($atual->CARGO_DATA_INICIO) && $atual->CARGO_DATA_INICIO
                ? substr((string) $atual->CARGO_DATA_INICIO, 0, 10)
                : now()->format('Y-m-d'));
        
        $fim = null;
        if ($temDataFim) {
            $in = $request->all();
            if (array_key_exists('CARGO_DATA_FIM', $in)) {
                $rawF = $in['CARGO_DATA_FIM'];
                $fim = ($rawF === null || $rawF === '') ? null : substr((string) $rawF, 0, 10);
            } else {
                $fim = $atual->CARGO_DATA_FIM
                    ? substr((string) $atual->CARGO_DATA_FIM, 0, 10) : null;
            }
        }
        // assertSemSobreposicao retorna null imediatamente se CARGO_DATA_INICIO não existir
        $vig = \App\Support\CargoVigencia::assertSemSobreposicao(
            (string) $request->CARGO_NOME,
            $request->CARGO_SIGLA ?? $atual->CARGO_SIGLA ?? null,
            $inicio,
            $fim,
            (int) $id
        );
        if ($vig) {
            return response()->json(['erro' => $vig], 422);
        }
        $payloadBase = [
            'CARGO_NOME' => $request->CARGO_NOME,
            'CARGO_SIGLA' => $request->CARGO_SIGLA ?? null,
            'CARGO_CBO' => $request->CARGO_CBO ?? null,
            'CARGO_CODIGO_CBO' => $request->CARGO_CBO ?? $request->CARGO_CODIGO_CBO ?? null,
            'CARGO_DESCRICAO' => $request->CARGO_DESCRICAO ?? null,
            'CARGO_REMUNERACAO' => $request->CARGO_REMUNERACAO ?? null,
            // PMSL usa CARGO_SALARIO (campo legado) — mapear do valor enviado pelo formulário
            'CARGO_SALARIO' => $request->CARGO_REMUNERACAO ?? $request->CARGO_SALARIO ?? $atual->CARGO_SALARIO ?? null,
            'CARGO_ESCOLARIDADE' => $request->CARGO_ESCOLARIDADE ?? null,
            'CARGO_GESTAO' => (int) ($request->CARGO_GESTAO ?? 0),
            'CARGO_DATA_INICIO' => $inicio,
            'CARGO_DATA_FIM' => in_array('CARGO_DATA_FIM', $cargoCols, true) ? $fim : null,
            'CARGO_VALOR_HORA_DESCONTO' => $request->CARGO_VALOR_HORA_DESCONTO ?? null,
            'CARGO_CARREIRA' => $request->CARGO_CARREIRA ?? $atual->CARGO_CARREIRA ?? null,
            'CARGO_CLASSE' => $request->CARGO_CLASSE ?? $atual->CARGO_CLASSE ?? null,
            'CARGO_REFERENCIA' => $request->CARGO_REFERENCIA ?? $atual->CARGO_REFERENCIA ?? null,
            'CARGO_NIVEL' => $request->CARGO_NIVEL ?? $atual->CARGO_NIVEL ?? null,
            'CARGO_SALARIO_BASE' => $request->CARGO_SALARIO_BASE ?? $request->CARGO_REMUNERACAO ?? $atual->CARGO_SALARIO_BASE ?? null,
            'CARGO_CARGA_HORARIA' => $request->CARGO_CARGA_HORARIA ?? $atual->CARGO_CARGA_HORARIA ?? null,
        ];
        if (in_array('updated_at', $cargoCols, true)) {
            $payloadBase['updated_at'] = now();
        }
        $payload = array_intersect_key($payloadBase, array_flip($cargoCols));
        $updated = DB::table('CARGO')->where('CARGO_ID', $id)->update($payload);
        if (!$updated) {
            return response()->json(['erro' => 'Cargo não encontrado ou sem alterações.'], 404);
        }
        try {
            Log::channel('security')->info('cargo_alterado', ['usuario' => \Illuminate\Support\Facades\Auth::id(), 'cargo_id' => $id]);
        } catch (\Throwable $e) {
        }

        return response()->json(['ok' => true, 'message' => 'Cargo atualizado com sucesso.']);
    } catch (\Throwable $e) {
        Log::error('Erro ao atualizar cargo', ['cargo_id' => $id, 'erro' => $e->getMessage()]);

        return response()->json(['erro' => 'Erro ao atualizar cargo. Verifique os dados e tente novamente.'], 500);
    }
});

// ── Inativar (soft: DELETE) ─────────────────────────────────
Route::delete('/cargos/{id}', function ($id) {
    try {
        $cargoCols = Schema::getColumnListing('CARGO');
        $payloadInativacao = ['CARGO_ATIVO' => 0];
        if (in_array('CARGO_DATA_FIM', $cargoCols, true)) {
            $payloadInativacao['CARGO_DATA_FIM'] = now()->toDateString();
        }
        if (in_array('updated_at', $cargoCols, true)) {
            $payloadInativacao['updated_at'] = now();
        }
        $updated = DB::table('CARGO')->where('CARGO_ID', $id)->update($payloadInativacao);
        if (!$updated) {
            return response()->json(['erro' => 'Cargo não encontrado.'], 404);
        }

        return response()->json(['ok' => true, 'message' => 'Cargo inativado com sucesso.']);
    } catch (\Throwable $e) {
        Log::error('Erro ao inativar cargo', ['cargo_id' => $id, 'erro' => $e->getMessage()]);

        return response()->json(['erro' => 'Erro ao inativar cargo.'], 500);
    }
});

// ── Reativar (PATCH) — exige que não haja conflito de vigência com outro registro
Route::patch('/cargos/{id}/reativar', function ($id) {
    try {
        $row = DB::table('CARGO')->where('CARGO_ID', $id)->first();
        if (!$row) {
            return response()->json(['erro' => 'Cargo não encontrado.'], 404);
        }
        $inicio = $row->CARGO_DATA_INICIO ?? null;
        if (!$inicio || trim((string) $inicio) === '') {
            return response()->json([
                'erro' => 'CARGO_DATA_INICIO é obrigatório no cadastro para reativar com rastreabilidade (eSocial). Atualize o cargo com data de início de vigência.',
            ], 422);
        }
        $inicio = substr((string) $inicio, 0, 10);
        $vig = CargoVigencia::assertSemSobreposicao(
            (string) $row->CARGO_NOME,
            $row->CARGO_SIGLA,
            $inicio,
            null,
            (int) $id
        );
        if ($vig) {
            return response()->json(['erro' => $vig], 422);
        }
        $cargoCols = Schema::getColumnListing('CARGO');
        $payload = ['CARGO_ATIVO' => 1];
        if (in_array('CARGO_DATA_FIM', $cargoCols, true)) {
            $payload['CARGO_DATA_FIM'] = null;
        }
        if (in_array('updated_at', $cargoCols, true)) {
            $payload['updated_at'] = now();
        }
        $updated = DB::table('CARGO')->where('CARGO_ID', $id)->update($payload);
        if (!$updated) {
            return response()->json(['erro' => 'Cargo não encontrado.'], 404);
        }

        return response()->json(['ok' => true, 'message' => 'Cargo reativado com sucesso.']);
    } catch (\Throwable $e) {
        Log::error('Erro ao reativar cargo', ['cargo_id' => $id, 'erro' => $e->getMessage()]);

        return response()->json(['erro' => 'Erro ao reativar cargo.'], 500);
    }
});

//  Funções / Cargos em Comissão  Listagem
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

//  Funções  Criar
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

        return response()->json(['message' => 'Função criada com sucesso.', 'funcao_id' => $funcao->ATRIBUICAO_ID], 201);
    } catch (\Throwable $e) {
        return response()->json(['erro' => 'Erro ao criar função: ' . $e->getMessage()], 500);
    }
});

//  Funções  Atualizar
Route::put('/funcoes/{id}', function ($id, \Illuminate\Http\Request $request) {
    try {
        $funcao = \App\Models\Atribuicao::findOrFail($id);
        $funcao->ATRIBUICAO_NOME = $request->nome ?? $request->ATRIBUICAO_NOME ?? $funcao->ATRIBUICAO_NOME;
        try {
            if ($request->has('cbo')) {
                $funcao->ATRIBUICAO_CBO = $request->cbo;
            }
        } catch (\Throwable $e) {
        }
        try {
            if ($request->has('tipo')) {
                $funcao->ATRIBUICAO_COMISSAO = $request->tipo;
            }
        } catch (\Throwable $e) {
        }
        try {
            if ($request->has('gratificacao')) {
                $funcao->ATRIBUICAO_GRATIFICACAO = $request->gratificacao;
            }
        } catch (\Throwable $e) {
        }
        $funcao->save();

        return response()->json(['message' => 'Função atualizada com sucesso.']);
    } catch (\Throwable $e) {
        return response()->json(['erro' => 'Erro ao atualizar: ' . $e->getMessage()], 500);
    }
});

//  Funções  Inativar
Route::delete('/funcoes/{id}', function ($id) {
    try {
        $funcao = \App\Models\Atribuicao::findOrFail($id);
        try {
            $funcao->ATRIBUICAO_ATIVO = 0;
            $funcao->save();
        } catch (\Throwable $e) {
            return response()->json(['message' => 'Soft delete não suportado nesta tabela.']);
        }

        return response()->json(['message' => 'Função inativada com sucesso.']);
    } catch (\Throwable $e) {
        return response()->json(['erro' => 'Erro ao inativar: ' . $e->getMessage()], 500);
    }
});
