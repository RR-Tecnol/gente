<?php
// gerado — não editar cegamente (regen_api_v3_fachada.py)


    // Valida token e retorna dados prÃ©-preenchidos
    Route::get('/autocadastro/{token}', function ($token) {
        try {
            $reg = \Illuminate\Support\Facades\DB::table('AUTOCADASTRO_TOKEN')
                ->where('TOKEN', $token)
                ->first();

            if (!$reg) {
                return response()->json(['status' => 'invalido', 'erro' => 'Token não encontrado'], 404);
            }

            // Verifica expiraÃ§Ã£o
            if ($reg->expira_em && now()->gt($reg->expira_em)) {
                \Illuminate\Support\Facades\DB::table('AUTOCADASTRO_TOKEN')
                    ->where('TOKEN', $token)->update(['TOKEN_STATUS' => 'expirado']);
                return response()->json(['status' => 'invalido', 'erro' => 'Token expirado'], 410);
            }

            if (in_array($reg->TOKEN_STATUS, ['expirado', 'revogado'])) {
                return response()->json(['status' => 'invalido'], 200);
            }

            return response()->json([
                'status' => $reg->TOKEN_STATUS,
                'nome' => $reg->TOKEN_NOME,
                'email' => $reg->TOKEN_EMAIL,
            ]);
        } catch (\Throwable $e) {
            return response()->json(['status' => 'invalido', 'erro' => $e->getMessage()], 500);
        }
    });

    // Recebe os dados do formulÃ¡rio de autocadastro (multipart/form-data)
    Route::post('/autocadastro/{token}', function (\Illuminate\Http\Request $request, $token) {
        try {
            $reg = \Illuminate\Support\Facades\DB::table('AUTOCADASTRO_TOKEN')
                ->where('TOKEN', $token)
                ->whereIn('TOKEN_STATUS', ['pendente'])
                ->first();

            if (!$reg) {
                return response()->json(['erro' => 'Token inválido ou já utilizado'], 422);
            }

            if ($reg->expira_em && now()->gt($reg->expira_em)) {
                return response()->json(['erro' => 'Token expirado'], 410);
            }

            // Valida e normaliza campos (evita contaminaÃ§Ã£o multipart no TOKEN_DADOS)
            $sanitize = static function ($v) {
                if (is_array($v) || is_object($v)) return null;
                $v = trim((string) $v);
                return $v === '' ? null : $v;
            };
            $digits = static fn($v) => preg_replace('/\D+/', '', (string) ($v ?? ''));
            $nome = (string) ($sanitize($request->input('nome')) ?? '');
            $email = strtolower((string) ($sanitize($request->input('email')) ?? ''));
            $senha = $request->senha ?? '';
            if (!$nome || !$email || strlen($senha) < 6) {
                return response()->json(['erro' => 'Nome, e-mail e senha (mín. 6 chars) são obrigatórios'], 422);
            }

            // â”€â”€ Salva arquivos de documentos â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€
            $docCampos = ['doc_identidade', 'doc_cpf', 'doc_residencia', 'doc_pis', 'doc_foto', 'doc_dependentes'];
            $docPaths = [];
            $dir = "autocadastro/{$token}";
            foreach ($docCampos as $campo) {
                if ($request->hasFile($campo)) {
                    $file = $request->file($campo);
                    // Valida tamanho (5MB) e tipo
                    if ($file->getSize() > 5 * 1024 * 1024)
                        continue;
                    $allowed = ['jpeg', 'jpg', 'png', 'gif', 'webp', 'pdf'];
                    if (!in_array(strtolower($file->getClientOriginalExtension()), $allowed))
                        continue;
                    $path = $file->store($dir, 'local');
                    $docPaths[$campo] = $path;
                }
            }

            // â”€â”€ Dependentes â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€
            $dependentes = [];
            if ($request->has('dependentes')) {
                $raw = $request->input('dependentes');
                $parsed = is_string($raw) ? json_decode($raw, true) : $raw;
                if (is_array($parsed)) {
                    foreach ($parsed as $dep) {
                        if (!empty($dep['nome'])) {
                            $dependentes[] = [
                                'nome' => trim($dep['nome']),
                                'cpf' => $dep['cpf'] ?? null,
                                'data_nasc' => $dep['data_nasc'] ?? null,
                                'parentesco' => $dep['parentesco'] ?? null,
                                'deducao_irrf' => $dep['deducao_irrf'] ?? '1',
                            ];
                        }
                    }
                }
            }

            // â”€â”€ Persiste os dados do token â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€
            $payloadSeguro = [
                'nome' => $nome,
                'nome_social' => $sanitize($request->input('nome_social')),
                'cpf' => $digits($request->input('cpf')),
                'data_nasc' => $sanitize($request->input('data_nasc')),
                'sexo' => $sanitize($request->input('sexo')),
                'rg' => $sanitize($request->input('rg')),
                'org_emissor' => $sanitize($request->input('org_emissor')),
                'pis' => $digits($request->input('pis')),
                'estado_civil' => $sanitize($request->input('estado_civil')),
                'grau_instrucao' => $sanitize($request->input('grau_instrucao')),
                'raca_cor' => $sanitize($request->input('raca_cor')),
                'email' => $email,
                'telefone' => $digits($request->input('telefone')),
                'cep' => $digits($request->input('cep')),
                'logradouro' => $sanitize($request->input('logradouro')),
                'numero' => $sanitize($request->input('numero')),
                'bairro' => $sanitize($request->input('bairro')),
                'cidade' => $sanitize($request->input('cidade')),
                'uf' => strtoupper((string) ($sanitize($request->input('uf')) ?? '')),
                'senha_hash' => \Illuminate\Support\Facades\Hash::make($senha),
                'dependentes' => $dependentes,
                'documentos' => $docPaths,
            ];

            \Illuminate\Support\Facades\DB::table('AUTOCADASTRO_TOKEN')
                ->where('TOKEN', $token)
                ->update([
                    'TOKEN_STATUS' => 'preenchido',
                    'TOKEN_DADOS' => json_encode($payloadSeguro, JSON_UNESCAPED_UNICODE),
                    'usado_em' => now(),
                    'updated_at' => now(),
                ]);

            return response()->json(['ok' => true, 'msg' => 'Cadastro recebido! Aguarde a aprovação do RH.']);
        } catch (\Throwable $e) {
            return response()->json(['erro' => $e->getMessage()], 500);
        }
    });

    // â•?â•?â•?â•?â•?â•?â•?â•?â•?â•?â•?â•?â•?â•?â•?â•?â•?â•?â•?â•?â•?â•?â•?â•?â•?â•?â•?â•?â•?â•?â•?â•?â•?â•?â•?â•?â•?â•?â•?â•?â•?â•?â•?â•?â•?â•?â•?â•?â•?â•?â•?â•?â•?â•?â•?â•?â•?â•?â•?â•?â•?â•?â•?â•?â•?â•?â•?
    // SPRINT D â€” SEGURANÃ‡A DO TRABALHO
    // â•?â•?â•?â•?â•?â•?â•?â•?â•?â•?â•?â•?â•?â•?â•?â•?â•?â•?â•?â•?â•?â•?â•?â•?â•?â•?â•?â•?â•?â•?â•?â•?â•?â•?â•?â•?â•?â•?â•?â•?â•?â•?â•?â•?â•?â•?â•?â•?â•?â•?â•?â•?â•?â•?â•?â•?â•?â•?â•?â•?â•?â•?â•?â•?â•?â•?â•?





    // â•?â•?â•?â•?â•?â•?â•?â•?â•?â•?â•?â•?â•?â•?â•?â•?â•?â•?â•?â•?â•?â•?â•?â•?â•?â•?â•?â•?â•?â•?â•?â•?â•?â•?â•?â•?â•?â•?â•?â•?â•?â•?â•?â•?â•?â•?â•?â•?â•?â•?â•?â•?â•?â•?â•?â•?â•?â•?â•?â•?â•?â•?â•?â•?â•?â•?â•?
    // SPRINT D â€” TREINAMENTOS
    // â•?â•?â•?â•?â•?â•?â•?â•?â•?â•?â•?â•?â•?â•?â•?â•?â•?â•?â•?â•?â•?â•?â•?â•?â•?â•?â•?â•?â•?â•?â•?â•?â•?â•?â•?â•?â•?â•?â•?â•?â•?â•?â•?â•?â•?â•?â•?â•?â•?â•?â•?â•?â•?â•?â•?â•?â•?â•?â•?â•?â•?â•?â•?â•?â•?â•?â•?




    // â•?â•?â•?â•?â•?â•?â•?â•?â•?â•?â•?â•?â•?â•?â•?â•?â•?â•?â•?â•?â•?â•?â•?â•?â•?â•?â•?â•?â•?â•?â•?â•?â•?â•?â•?â•?â•?â•?â•?â•?â•?â•?â•?â•?â•?â•?â•?â•?â•?â•?â•?â•?â•?â•?â•?â•?â•?â•?â•?â•?â•?â•?â•?â•?â•?â•?â•?
    // SPRINT D â€” PESQUISA DE SATISFAÃ‡ÃƒO (CRUD + RESULTADOS)
    // â•?â•?â•?â•?â•?â•?â•?â•?â•?â•?â•?â•?â•?â•?â•?â•?â•?â•?â•?â•?â•?â•?â•?â•?â•?â•?â•?â•?â•?â•?â•?â•?â•?â•?â•?â•?â•?â•?â•?â•?â•?â•?â•?â•?â•?â•?â•?â•?â•?â•?â•?â•?â•?â•?â•?â•?â•?â•?â•?â•?â•?â•?â•?â•?â•?â•?â•?

    // â”€â”€ FuncionÃ¡rio: lista pesquisas abertas â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€
    Route::get('/pesquisas', function () {
        try {
            $pesquisas = \Illuminate\Support\Facades\DB::table('PESQUISA_SATISFACAO')
                ->where('PESQUISA_STATUS', 'aberta')
                ->orderByDesc('created_at')
                ->get()
                ->map(function ($p) {
                    $perguntas = \Illuminate\Support\Facades\DB::table('PESQUISA_PERGUNTA')
                        ->where('PESQUISA_ID', $p->PESQUISA_ID)
                        ->orderBy('PERGUNTA_ORDEM')
                        ->get()
                        ->map(fn($q) => [
                            'id' => $q->PERGUNTA_ID,
                            'texto' => $q->PERGUNTA_TEXTO,
                            'tipo' => $q->PERGUNTA_TIPO,
                            'opcoes' => $q->PERGUNTA_OPCOES ? json_decode($q->PERGUNTA_OPCOES, true) : [],
                        ]);
                    return [
                        'id' => $p->PESQUISA_ID,
                        'titulo' => $p->PESQUISA_TITULO,
                        'desc' => $p->PESQUISA_DESC,
                        'status' => $p->PESQUISA_STATUS,
                        'inicio' => $p->PESQUISA_INICIO,
                        'fim' => $p->PESQUISA_FIM,
                        'perguntas' => $perguntas,
                    ];
                });
            return response()->json(['pesquisas' => $pesquisas]);
        } catch (\Throwable $e) {
            return response()->json(['pesquisas' => [], 'erro' => $e->getMessage()]);
        }
    });

    // â”€â”€ FuncionÃ¡rio: responder pesquisa â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€
    Route::post('/pesquisas/{id}/responder', function (\Illuminate\Http\Request $request, $id) {
        try {
            $user = \Illuminate\Support\Facades\Auth::user();
            $func = \App\Models\Funcionario::where('USUARIO_ID', $user->USUARIO_ID ?? 0)->first();
            $respostas = $request->respostas ?? [];
            foreach ($respostas as $r) {
                \Illuminate\Support\Facades\DB::table('PESQUISA_RESPOSTA')->insert([
                    'PESQUISA_ID' => $id,
                    'PERGUNTA_ID' => $r['pergunta_id'],
                    'FUNCIONARIO_ID' => $request->anonimo ? null : ($func->FUNCIONARIO_ID ?? null),
                    'RESPOSTA_NOTA' => $r['nota'] ?? null,
                    'RESPOSTA_TEXTO' => $r['texto'] ?? null,
                    'created_at' => now(),
                    'updated_at' => now(),
                ]);
            }
            $ano = now()->format('Y');
            $seq = \Illuminate\Support\Facades\DB::table('PESQUISA_RESPOSTA')->count();
            return response()->json(['ok' => true, 'protocolo' => "PSQ-{$ano}-" . str_pad($seq, 4, '0', STR_PAD_LEFT)]);
        } catch (\Throwable $e) {
            return response()->json(['erro' => $e->getMessage()], 500);
        }
    });

    // â”€â”€ Admin: listar todas as pesquisas â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€
    Route::get('/pesquisas/admin', function () {
        try {
            $pesquisas = \Illuminate\Support\Facades\DB::table('PESQUISA_SATISFACAO')
                ->orderByDesc('created_at')
                ->get()
                ->map(function ($p) {
                    $perguntas = \Illuminate\Support\Facades\DB::table('PESQUISA_PERGUNTA')
                        ->where('PESQUISA_ID', $p->PESQUISA_ID)
                        ->orderBy('PERGUNTA_ORDEM')
                        ->get()
                        ->map(fn($q) => [
                            'id' => $q->PERGUNTA_ID,
                            'texto' => $q->PERGUNTA_TEXTO,
                            'tipo' => $q->PERGUNTA_TIPO,
                            'ordem' => $q->PERGUNTA_ORDEM,
                            'opcoes' => $q->PERGUNTA_OPCOES ? json_decode($q->PERGUNTA_OPCOES, true) : [],
                        ]);
                    $totalRes = \Illuminate\Support\Facades\DB::table('PESQUISA_RESPOSTA')
                        ->where('PESQUISA_ID', $p->PESQUISA_ID)
                        ->distinct('FUNCIONARIO_ID')->count();
                    return [
                        'id' => $p->PESQUISA_ID,
                        'titulo' => $p->PESQUISA_TITULO,
                        'desc' => $p->PESQUISA_DESC,
                        'status' => $p->PESQUISA_STATUS,
                        'inicio' => $p->PESQUISA_INICIO,
                        'fim' => $p->PESQUISA_FIM,
                        'total_perguntas' => $perguntas->count(),
                        'total_respostas' => $totalRes,
                        'perguntas' => $perguntas,
                    ];
                });
            return response()->json(['pesquisas' => $pesquisas]);
        } catch (\Throwable $e) {
            return response()->json(['pesquisas' => [], 'erro' => $e->getMessage()]);
        }
    });

    // â”€â”€ Admin: criar pesquisa â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€
    Route::post('/pesquisas', function (\Illuminate\Http\Request $request) {
        try {
            $user = \Illuminate\Support\Facades\Auth::user();
            $id = \Illuminate\Support\Facades\DB::table('PESQUISA_SATISFACAO')->insertGetId([
                'PESQUISA_TITULO' => $request->titulo,
                'PESQUISA_DESC' => $request->desc,
                'PESQUISA_STATUS' => 'rascunho',
                'PESQUISA_INICIO' => $request->inicio ?: null,
                'PESQUISA_FIM' => $request->fim ?: null,
                'CRIADO_POR' => $user->USUARIO_ID ?? null,
                'created_at' => now(),
                'updated_at' => now(),
            ]);
            foreach (($request->perguntas ?? []) as $pq) {
                \Illuminate\Support\Facades\DB::table('PESQUISA_PERGUNTA')->insert([
                    'PESQUISA_ID' => $id,
                    'PERGUNTA_TEXTO' => $pq['texto'],
                    'PERGUNTA_TIPO' => $pq['tipo'],
                    'PERGUNTA_ORDEM' => $pq['ordem'] ?? 0,
                    'PERGUNTA_OPCOES' => isset($pq['opcoes']) ? json_encode($pq['opcoes']) : null,
                    'created_at' => now(),
                    'updated_at' => now(),
                ]);
            }
            return response()->json(['id' => $id], 201);
        } catch (\Throwable $e) {
            return response()->json(['erro' => $e->getMessage()], 500);
        }
    });

    // â”€â”€ Admin: editar pesquisa â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€
    Route::put('/pesquisas/{id}', function (\Illuminate\Http\Request $request, $id) {
        try {
            $pesquisaExiste = \Illuminate\Support\Facades\DB::table('PESQUISA_SATISFACAO')
                ->where('PESQUISA_ID', $id)
                ->exists();
            if (!$pesquisaExiste) {
                return response()->json(['erro' => 'Pesquisa não encontrada.'], 404);
            }

            \Illuminate\Support\Facades\DB::table('PESQUISA_SATISFACAO')
                ->where('PESQUISA_ID', $id)
                ->update([
                    'PESQUISA_TITULO' => $request->titulo,
                    'PESQUISA_DESC' => $request->desc,
                    'PESQUISA_INICIO' => $request->inicio ?: null,
                    'PESQUISA_FIM' => $request->fim ?: null,
                    'updated_at' => now(),
                ]);
            // Sincroniza perguntas: remove as antigas e recria
            $keepIds = collect($request->perguntas ?? [])->pluck('id')->filter()->values();
            \Illuminate\Support\Facades\DB::table('PESQUISA_PERGUNTA')
                ->where('PESQUISA_ID', $id)
                ->whereNotIn('PERGUNTA_ID', $keepIds->toArray())
                ->delete();
            foreach (($request->perguntas ?? []) as $pq) {
                if (!empty($pq['id'])) {
                    \Illuminate\Support\Facades\DB::table('PESQUISA_PERGUNTA')
                        ->where('PERGUNTA_ID', $pq['id'])
                        ->update([
                            'PERGUNTA_TEXTO' => $pq['texto'],
                            'PERGUNTA_TIPO' => $pq['tipo'],
                            'PERGUNTA_ORDEM' => $pq['ordem'] ?? 0,
                            'PERGUNTA_OPCOES' => isset($pq['opcoes']) ? json_encode($pq['opcoes']) : null,
                            'updated_at' => now(),
                        ]);
                } else {
                    \Illuminate\Support\Facades\DB::table('PESQUISA_PERGUNTA')->insert([
                        'PESQUISA_ID' => $id,
                        'PERGUNTA_TEXTO' => $pq['texto'],
                        'PERGUNTA_TIPO' => $pq['tipo'],
                        'PERGUNTA_ORDEM' => $pq['ordem'] ?? 0,
                        'PERGUNTA_OPCOES' => isset($pq['opcoes']) ? json_encode($pq['opcoes']) : null,
                        'created_at' => now(),
                        'updated_at' => now(),
                    ]);
                }
            }
            return response()->json(['ok' => true]);
        } catch (\Throwable $e) {
            return response()->json(['erro' => $e->getMessage()], 500);
        }
    });

    // â”€â”€ Admin: mudar status â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€
    Route::patch('/pesquisas/{id}/status', function (\Illuminate\Http\Request $request, $id) {
        try {
            $status = trim((string) ($request->status ?? ''));
            if ($status === '') {
                return response()->json(['erro' => 'Status é obrigatório.'], 422);
            }
            $updated = \Illuminate\Support\Facades\DB::table('PESQUISA_SATISFACAO')
                ->where('PESQUISA_ID', $id)
                ->update(['PESQUISA_STATUS' => $status, 'updated_at' => now()]);
            if (!$updated) {
                return response()->json(['erro' => 'Pesquisa não encontrada.'], 404);
            }
            return response()->json(['ok' => true]);
        } catch (\Throwable $e) {
            return response()->json(['erro' => $e->getMessage()], 500);
        }
    });

    // â”€â”€ Admin: excluir pesquisa â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€
    Route::delete('/pesquisas/{id}', function ($id) {
        try {
            $existe = \Illuminate\Support\Facades\DB::table('PESQUISA_SATISFACAO')
                ->where('PESQUISA_ID', $id)
                ->exists();
            if (!$existe) {
                return response()->json(['erro' => 'Pesquisa não encontrada.'], 404);
            }
            \Illuminate\Support\Facades\DB::table('PESQUISA_RESPOSTA')->where('PESQUISA_ID', $id)->delete();
            \Illuminate\Support\Facades\DB::table('PESQUISA_PERGUNTA')->where('PESQUISA_ID', $id)->delete();
            \Illuminate\Support\Facades\DB::table('PESQUISA_SATISFACAO')->where('PESQUISA_ID', $id)->delete();
            return response()->json(['ok' => true]);
        } catch (\Throwable $e) {
            return response()->json(['erro' => $e->getMessage()], 500);
        }
    });

    // â”€â”€ Admin: resultados detalhados â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€
    Route::get('/pesquisas/{id}/resultados', function ($id) {
        try {
            // Respostas totais
            $totalRespondentes = \Illuminate\Support\Facades\DB::table('PESQUISA_RESPOSTA')
                ->where('PESQUISA_ID', $id)
                ->whereNotNull('RESPOSTA_NOTA')
                ->distinct('FUNCIONARIO_ID')
                ->count('FUNCIONARIO_ID');

            // Calcula NPS da primeira pergunta NPS
            $pergNps = \Illuminate\Support\Facades\DB::table('PESQUISA_PERGUNTA')
                ->where('PESQUISA_ID', $id)
                ->where('PERGUNTA_TIPO', 'nps')
                ->orderBy('PERGUNTA_ORDEM')
                ->first();

            $nps = 0;
            $promotores = 0;
            $neutros = 0;
            $detratores = 0;
            if ($pergNps) {
                $notas = \Illuminate\Support\Facades\DB::table('PESQUISA_RESPOSTA')
                    ->where('PESQUISA_ID', $id)
                    ->where('PERGUNTA_ID', $pergNps->PERGUNTA_ID)
                    ->whereNotNull('RESPOSTA_NOTA')
                    ->pluck('RESPOSTA_NOTA');
                $total = $notas->count();
                if ($total > 0) {
                    $prom = $notas->filter(fn($n) => $n >= 9)->count();
                    $neut = $notas->filter(fn($n) => $n >= 7 && $n < 9)->count();
                    $detr = $notas->filter(fn($n) => $n < 7)->count();
                    $promotores = round($prom / $total * 100);
                    $neutros = round($neut / $total * 100);
                    $detratores = round($detr / $total * 100);
                    $nps = $promotores - $detratores;
                }
            }

            // Resultado por pergunta
            $perguntas = \Illuminate\Support\Facades\DB::table('PESQUISA_PERGUNTA')
                ->where('PESQUISA_ID', $id)
                ->orderBy('PERGUNTA_ORDEM')
                ->get()
                ->map(function ($pq) use ($id) {
                    $respostas = \Illuminate\Support\Facades\DB::table('PESQUISA_RESPOSTA')
                        ->where('PESQUISA_ID', $id)
                        ->where('PERGUNTA_ID', $pq->PERGUNTA_ID)
                        ->get();

                    $result = [
                        'id' => $pq->PERGUNTA_ID,
                        'texto' => $pq->PERGUNTA_TEXTO,
                        'tipo' => $pq->PERGUNTA_TIPO,
                    ];

                    if (in_array($pq->PERGUNTA_TIPO, ['nps', 'estrelas'])) {
                        $notas = $respostas->pluck('RESPOSTA_NOTA')->filter();
                        $result['media'] = $notas->count() ? round($notas->avg(), 2) : null;
                        $result['total'] = $notas->count();
                    } elseif ($pq->PERGUNTA_TIPO === 'opcoes') {
                        $textos = $respostas->pluck('RESPOSTA_TEXTO')->filter();
                        $counts = $textos->countBy()->sortDesc();
                        $ttl = $textos->count();
                        $result['ranking'] = $counts->map(fn($c, $v) => [
                            'valor' => $v,
                            'count' => $c,
                            'pct' => $ttl ? round($c / $ttl * 100) : 0,
                        ])->values();
                    } else {
                        $result['textos'] = $respostas->pluck('RESPOSTA_TEXTO')->filter()->values();
                    }
                    return $result;
                });

            return response()->json([
                'total' => $totalRespondentes,
                'nps' => $nps,
                'promotores' => $promotores,
                'neutros' => $neutros,
                'detratores' => $detratores,
                'perguntas' => $perguntas,
            ]);
        } catch (\Throwable $e) {
            return response()->json(['erro' => $e->getMessage()], 500);
        }
    });

    Route::post('/beneficios/solicitar', function (\Illuminate\Http\Request $request) {
        try {
            $user = \Illuminate\Support\Facades\Auth::user();
            $func = \App\Models\Funcionario::where('USUARIO_ID', $user->USUARIO_ID ?? 0)->first();
            if (!$func) {
                return response()->json(['erro' => 'Funcionário não encontrado para o usuário autenticado.'], 404);
            }
            $inserted = \Illuminate\Support\Facades\DB::table('BENEFICIO_SOLICITACAO')->insertOrIgnore([
                'FUNCIONARIO_ID' => $func->FUNCIONARIO_ID ?? null,
                'BENEFICIO_ID' => $request->beneficio_id,
                'NOME' => $request->nome,
                'STATUS' => 'pendente',
                'created_at' => now(),
                'updated_at' => now(),
            ]);
            return response()->json(['ok' => true, 'created' => $inserted > 0]);
        } catch (\Throwable $e) {
            return response()->json(['erro' => $e->getMessage()], 500);
        }
    });

    // CRUD de funções (Cargos e Salários) — requer sessão autenticada (SPA + audit)
    Route::middleware(['auth', 'alterar.senha', 'audit'])->group(function () {
        Route::get('/funcoes', function (\Illuminate\Http\Request $request) {
            try {
                $q = $request->q ?? '';
                $funcoes = \Illuminate\Support\Facades\DB::table('FUNCAO')
                    ->when($q, fn($x) => $x->where('FUNCAO_NOME', 'like', "%$q%"))
                    ->orderBy('FUNCAO_NOME')->get()
                    ->map(fn($f) => [
                        'funcao_id' => $f->FUNCAO_ID,
                        'nome' => $f->FUNCAO_NOME,
                        'cbo' => $f->FUNCAO_CBO ?? null,
                        'tipo' => $f->FUNCAO_TIPO ?? null,
                        'gratificacao' => (float) ($f->FUNCAO_GRATIFICACAO ?? 0) ?: null,
                        'ativo' => (bool) ($f->FUNCAO_ATIVO ?? true),
                    ]);
                return response()->json(['funcoes' => $funcoes]);
            } catch (\Throwable $e) {
                return response()->json(['funcoes' => [], 'erro' => $e->getMessage()]);
            }
        });

        Route::post('/funcoes', function (\Illuminate\Http\Request $request) {
            try {
                if (!$request->nome)
                return response()->json(['erro' => 'Nome obrigatório.'], 422);
                $funcaoCols = \Illuminate\Support\Facades\Schema::getColumnListing('FUNCAO');
                $payloadF = ['FUNCAO_NOME' => $request->nome];
                if (in_array('FUNCAO_CBO', $funcaoCols)) $payloadF['FUNCAO_CBO'] = $request->cbo ?? null;
                if (in_array('FUNCAO_TIPO', $funcaoCols)) $payloadF['FUNCAO_TIPO'] = $request->tipo ?? null;
                if (in_array('FUNCAO_GRATIFICACAO', $funcaoCols)) $payloadF['FUNCAO_GRATIFICACAO'] = $request->gratificacao ?? null;
                if (in_array('FUNCAO_ATIVO', $funcaoCols)) $payloadF['FUNCAO_ATIVO'] = 1;
                if (in_array('created_at', $funcaoCols)) $payloadF['created_at'] = now();
                if (in_array('updated_at', $funcaoCols)) $payloadF['updated_at'] = now();
                $id = \Illuminate\Support\Facades\DB::table('FUNCAO')->insertGetId($payloadF);
                return response()->json(['ok' => true, 'funcao_id' => $id]);
            } catch (\Throwable $e) {
                return response()->json(['erro' => $e->getMessage()], 500);
            }
        });

        Route::put('/funcoes/{id}', function (\Illuminate\Http\Request $request, $id) {
            try {
                $funcaoCols = \Illuminate\Support\Facades\Schema::getColumnListing('FUNCAO');
                $payloadF = ['FUNCAO_NOME' => $request->nome];
                if (in_array('FUNCAO_CBO', $funcaoCols)) $payloadF['FUNCAO_CBO'] = $request->cbo ?? null;
                if (in_array('FUNCAO_TIPO', $funcaoCols)) $payloadF['FUNCAO_TIPO'] = $request->tipo ?? null;
                if (in_array('FUNCAO_GRATIFICACAO', $funcaoCols)) $payloadF['FUNCAO_GRATIFICACAO'] = $request->gratificacao ?? null;
                if (in_array('updated_at', $funcaoCols)) $payloadF['updated_at'] = now();
                $updated = \Illuminate\Support\Facades\DB::table('FUNCAO')->where('FUNCAO_ID', $id)->update($payloadF);
                if (!$updated) {
                    return response()->json(['erro' => 'Função não encontrada ou sem alterações.'], 404);
                }
                return response()->json(['ok' => true]);
            } catch (\Throwable $e) {
                return response()->json(['erro' => $e->getMessage()], 500);
            }
        });

        Route::delete('/funcoes/{id}', function ($id) {
            try {
                $updated = \Illuminate\Support\Facades\DB::table('FUNCAO')->where('FUNCAO_ID', $id)->update(['FUNCAO_ATIVO' => 0, 'updated_at' => now()]);
                if (!$updated) {
                    return response()->json(['erro' => 'Função não encontrada.'], 404);
                }
                return response()->json(['ok' => true]);
            } catch (\Throwable $e) {
                return response()->json(['erro' => $e->getMessage()], 500);
            }
        });
    });

    // â•?â•?â•?â•?â•?â•?â•?â•?â•?â•?â•?â•?â•?â•?â•?â•?â•?â•?â•?â•?â•?â•?â•?â•?â•?â•?â•?â•?â•?â•?â•?â•?â•?â•?â•?â•?â•?â•?â•?â•?â•?â•?â•?â•?â•?â•?â•?â•?â•?â•?â•?â•?â•?â•?â•?â•?â•?â•?â•?â•?â•?â•?â•?â•?â•?â•?â•?
    // PONTO ELETRÃ”NICO â€” config, registros do mÃªs e bater ponto
    // â•?â•?â•?â•?â•?â•?â•?â•?â•?â•?â•?â•?â•?â•?â•?â•?â•?â•?â•?â•?â•?â•?â•?â•?â•?â•?â•?â•?â•?â•?â•?â•?â•?â•?â•?â•?â•?â•?â•?â•?â•?â•?â•?â•?â•?â•?â•?â•?â•?â•?â•?â•?â•?â•?â•?â•?â•?â•?â•?â•?â•?â•?â•?â•?â•?â•?â•?

    Route::get('/ponto/config', function () {
        try {
            $user = \Illuminate\Support\Facades\Auth::user();
            $func = \App\Models\Funcionario::where('USUARIO_ID', $user->USUARIO_ID ?? 0)->first();

            $cfg = $func
                ? \Illuminate\Support\Facades\DB::table('PONTO_CONFIG_FUNCIONARIO')
                    ->where('FUNCIONARIO_ID', $func->FUNCIONARIO_ID)
                    ->first()
                : null;

            return response()->json([
                'regime' => $cfg->REGIME ?? '4_batidas',
                'hora_entrada' => $cfg->HORA_ENTRADA ?? '08:00',
                'hora_saida' => $cfg->HORA_SAIDA ?? '18:00',
                'tolerancia' => $cfg->TOLERANCIA ?? 15,
                'intervalo_almoco' => isset($cfg->INTERVALO_ALMOCO) ? (int) $cfg->INTERVALO_ALMOCO : null,
                'jornada_financeira_horas' => isset($cfg->JORNADA_FINANCEIRA_HORAS) ? (float) $cfg->JORNADA_FINANCEIRA_HORAS : null,
            ]);
        } catch (\Throwable $e) {
            return response()->json(['erro' => $e->getMessage()], 500);
        }
    });

    Route::middleware(['auth', 'alterar.senha', 'audit'])->group(function () {
        Route::put('/ponto/config', function (\Illuminate\Http\Request $request) {
            try {
                $user = \Illuminate\Support\Facades\Auth::user();
                $func = \App\Models\Funcionario::where('USUARIO_ID', $user->USUARIO_ID ?? 0)->first();
                if (!$func) return response()->json(['erro' => 'Sem funcionario'], 404);

                $validator = \Illuminate\Support\Facades\Validator::make($request->all(), [
                    'regime' => 'nullable|in:2_batidas,4_batidas',
                    'hora_entrada' => ['nullable', 'regex:/^\d{2}:\d{2}$/'],
                    'hora_saida' => ['nullable', 'regex:/^\d{2}:\d{2}$/'],
                    'tolerancia' => 'nullable|integer|min:0|max:120',
                ], [
                    'regime.in' => 'Regime inválido.',
                    'hora_entrada.regex' => 'Hora de entrada inválida. Use HH:MM.',
                    'hora_saida.regex' => 'Hora de saída inválida. Use HH:MM.',
                    'tolerancia.integer' => 'Tolerância deve ser numérica.',
                ]);
                if ($validator->fails()) {
                    return response()->json(['erro' => $validator->errors()->first()], 422);
                }

                $id = $func->FUNCIONARIO_ID;
                $dados = $request->only(['regime', 'hora_entrada', 'hora_saida', 'tolerancia']);

                $update = [
                    'REGIME' => $dados['regime'] ?? '4_batidas',
                    'HORA_ENTRADA' => $dados['hora_entrada'] ?? '08:00',
                    'HORA_SAIDA' => $dados['hora_saida'] ?? '18:00',
                    'TOLERANCIA' => $dados['tolerancia'] ?? 15,
                    'updated_at' => now(),
                ];

                \Illuminate\Support\Facades\DB::table('PONTO_CONFIG_FUNCIONARIO')->updateOrInsert(
                    ['FUNCIONARIO_ID' => $id],
                    $update
                );
                return response()->json(['ok' => true]);
            } catch (\Throwable $e) {
                return response()->json(['erro' => $e->getMessage()], 500);
            }
        });

    // Endpoints Admin
    Route::get('/ponto/config/funcionarios', function () {
        try {
            $user = \Illuminate\Support\Facades\Auth::user();
            $perfilAtual = strtolower(trim((string) ($user->PERFIL ?? '')));
            if (!$perfilAtual) {
                $perfilAtual = strtolower(trim((string) (optional($user->usuarioPerfis()->with('perfil')->first())->perfil->PERFIL_NOME ?? '')));
            }
            if (!in_array($perfilAtual, ['admin', 'rh', 'administrador'], true)) {
                return response()->json(['erro' => 'Nao autorizado'], 403);
            }

            $rows = \Illuminate\Support\Facades\DB::table('PONTO_CONFIG_FUNCIONARIO as C')
                ->join('FUNCIONARIO as F', 'C.FUNCIONARIO_ID', '=', 'F.FUNCIONARIO_ID')
                ->join('PESSOA as P', 'F.PESSOA_ID', '=', 'P.PESSOA_ID')
                ->select(
                    'C.FUNCIONARIO_ID',
                    'P.PESSOA_NOME as NOME',
                    'C.REGIME',
                    'C.HORA_ENTRADA',
                    'C.HORA_SAIDA',
                    'C.TOLERANCIA',
                    'C.INTERVALO_ALMOCO',
                    'C.JORNADA_FINANCEIRA_HORAS',
                    'C.JORNADA_FINANCEIRA_OBS'
                )
                ->get();

            return response()->json(['configs' => $rows]);
        } catch (\Throwable $e) {
            return response()->json(['erro' => $e->getMessage()], 500);
        }
    });

    Route::put('/ponto/config/funcionarios/{id}', function (\Illuminate\Http\Request $request, $id) {
        try {
            $user = \Illuminate\Support\Facades\Auth::user();
            $perfilAtual = strtolower(trim((string) ($user->PERFIL ?? '')));
            if (!$perfilAtual) {
                $perfilAtual = strtolower(trim((string) (optional($user->usuarioPerfis()->with('perfil')->first())->perfil->PERFIL_NOME ?? '')));
            }
            if (!in_array($perfilAtual, ['admin', 'rh', 'administrador'], true)) {
                return response()->json(['erro' => 'Nao autorizado'], 403);
            }

            $funcionarioExiste = \Illuminate\Support\Facades\DB::table('FUNCIONARIO')
                ->where('FUNCIONARIO_ID', $id)
                ->exists();
            if (!$funcionarioExiste) {
                return response()->json(['erro' => 'Funcionário não encontrado.'], 404);
            }

            $dados = $request->all();
            $validator = \Illuminate\Support\Facades\Validator::make($dados, [
                'REGIME' => 'nullable|in:2_batidas,4_batidas',
                'HORA_ENTRADA' => ['nullable', 'regex:/^\d{2}:\d{2}$/'],
                'HORA_SAIDA' => ['nullable', 'regex:/^\d{2}:\d{2}$/'],
                'TOLERANCIA' => 'nullable|integer|min:0|max:120',
                'INTERVALO_ALMOCO' => 'nullable|integer|min:0|max:240',
                'JORNADA_FINANCEIRA_HORAS' => 'nullable|numeric|min:0|max:24',
                'JORNADA_FINANCEIRA_OBS' => 'nullable|string|max:1000',
            ], [
                'REGIME.in' => 'Regime inválido.',
                'HORA_ENTRADA.regex' => 'Hora de entrada inválida. Use HH:MM.',
                'HORA_SAIDA.regex' => 'Hora de saída inválida. Use HH:MM.',
            ]);
            if ($validator->fails()) {
                return response()->json(['erro' => $validator->errors()->first()], 422);
            }
            
            // Regra 4: JORNADA_FINANCEIRA_HORAS = s贸 admin
            if (array_key_exists('JORNADA_FINANCEIRA_HORAS', $dados)) {
                if (!in_array($perfilAtual, ['admin', 'administrador'], true)) {
                    return response()->json(['erro' => 'Apenas admins podem configurar jornada financeira.'], 403);
                }
                if ($dados['JORNADA_FINANCEIRA_HORAS'] !== null && empty($dados['JORNADA_FINANCEIRA_OBS'])) {
                    return response()->json(['erro' => 'Observação é obrigatória ao definir jornada financeira.'], 422);
                }
            }

            $update = [];
            if (isset($dados['REGIME'])) $update['REGIME'] = $dados['REGIME'];
            if (isset($dados['HORA_ENTRADA'])) $update['HORA_ENTRADA'] = $dados['HORA_ENTRADA'];
            if (isset($dados['HORA_SAIDA'])) $update['HORA_SAIDA'] = $dados['HORA_SAIDA'];
            if (isset($dados['TOLERANCIA'])) $update['TOLERANCIA'] = $dados['TOLERANCIA'];
            
            if (array_key_exists('INTERVALO_ALMOCO', $dados)) {
                $update['INTERVALO_ALMOCO'] = $dados['INTERVALO_ALMOCO'];
            }
            if (array_key_exists('JORNADA_FINANCEIRA_HORAS', $dados)) {
                $update['JORNADA_FINANCEIRA_HORAS'] = $dados['JORNADA_FINANCEIRA_HORAS'];
            }
            if (array_key_exists('JORNADA_FINANCEIRA_OBS', $dados)) {
                $update['JORNADA_FINANCEIRA_OBS'] = $dados['JORNADA_FINANCEIRA_OBS'];
            }
            $update['updated_at'] = now();

            \Illuminate\Support\Facades\DB::table('PONTO_CONFIG_FUNCIONARIO')->updateOrInsert(
                ['FUNCIONARIO_ID' => $id],
                $update
            );

            return response()->json(['ok' => true]);
        } catch (\Throwable $e) {
            return response()->json(['erro' => $e->getMessage()], 500);
        }
    });

    }); // fim auth+audit: ponto config (PUT) + admin listagem + PUT por funcionário

    require __DIR__ . '/ponto_mes_spa_get.php';


    Route::middleware(['auth', 'alterar.senha', 'audit'])->group(function () {
    Route::post('/ponto/registro', function (\Illuminate\Http\Request $request) {
        try {
            $user = \Illuminate\Support\Facades\Auth::user();
            if (!$user) {
                return response()->json(['erro' => 'Não autenticado.'], 401);
            }

            $func = \App\Models\Funcionario::where('USUARIO_ID', $user->USUARIO_ID ?? 0)->first();
            if (!$func)
                return response()->json(['erro' => 'Funcionário não encontrado para o usuário logado.'], 404);

            $data = $request->data ?? now()->toDateString(); // YYYY-MM-DD
            $hora = $request->hora ?? now()->format('H:i:s');
            $tipoInformado = strtolower(trim((string) ($request->tipo ?? 'entrada')));
            $confirmacaoSaidaAntecipada = (bool) $request->boolean('confirmacao_saida_antecipada');
            $aceiteDescontoSalarial = (bool) $request->boolean('aceite_desconto_salarial');

            $tipoParaBanco = [
                'entrada' => 'entrada',
                'saida' => 'saida',
                'saida_almoco' => 'saida_alm',
                'saida_alm' => 'saida_alm',
                'retorno_almoco' => 'ret_alm',
                'ret_alm' => 'ret_alm',
            ];
            $tipoApiPorBanco = [
                'entrada' => 'entrada',
                'saida' => 'saida',
                'saida_alm' => 'saida_almoco',
                'ret_alm' => 'retorno_almoco',
            ];

            if (!isset($tipoParaBanco[$tipoInformado])) {
                return response()->json([
                    'erro' => 'Tipo de batida inválido. Use: entrada, saida_almoco, retorno_almoco ou saida.',
                ], 422);
            }

            if (!preg_match('/^\d{4}-\d{2}-\d{2}$/', (string) $data)) {
                return response()->json(['erro' => 'Data inválida. Use o formato YYYY-MM-DD.'], 422);
            }

            // Normaliza hora para HH:MM:SS
            if (strlen($hora) === 5)
                $hora .= ':00';
            if (!preg_match('/^\d{2}:\d{2}:\d{2}$/', (string) $hora)) {
                return response()->json(['erro' => 'Hora inválida. Use o formato HH:MM:SS.'], 422);
            }

            $cfg = \Illuminate\Support\Facades\DB::table('PONTO_CONFIG_FUNCIONARIO')
                ->where('FUNCIONARIO_ID', $func->FUNCIONARIO_ID)
                ->first();
            $regimeCfg = $cfg->REGIME ?? '4_batidas';

            $dataHora = $data . ' ' . $hora;
            $tipoBanco = $tipoParaBanco[$tipoInformado];

            // Inteligência de fluxo: valida sequência real no backend
            // para impedir combinações inválidas (ex.: fechar sem ciclo correto).
            $normalizarTipo = function ($tipo) {
                $t = strtolower(trim((string) $tipo));
                return match ($t) {
                    'saida_almoco', 'saida_alm' => 'saida_alm',
                    'retorno_almoco', 'ret_alm' => 'ret_alm',
                    default => $t,
                };
            };
            $sequenciaEsperada = $regimeCfg === '2_batidas'
                ? ['entrada', 'saida']
                : ['entrada', 'saida_alm', 'ret_alm', 'saida'];

            $inicioDia = "{$data} 00:00:00";
            $fimDia = "{$data} 23:59:59";
            $rowsDia = \Illuminate\Support\Facades\DB::table('REGISTRO_PONTO')
                ->where('FUNCIONARIO_ID', $func->FUNCIONARIO_ID)
                ->whereBetween('REGISTRO_DATA_HORA', [$inicioDia, $fimDia])
                ->orderBy('REGISTRO_DATA_HORA')
                ->get(['REGISTRO_TIPO', 'REGISTRO_DATA_HORA']);
            $tiposDia = $rowsDia->map(fn($r) => $normalizarTipo($r->REGISTRO_TIPO))->values()->all();

            if (count($tiposDia) >= count($sequenciaEsperada)) {
                return response()->json([
                    'erro' => 'Expediente já encerrado hoje.',
                    'codigo' => 'EXPEDIENTE_JA_ENCERRADO',
                ], 409);
            }

            $proximoEsperado = $sequenciaEsperada[count($tiposDia)] ?? end($sequenciaEsperada);
            if ($tipoBanco !== $proximoEsperado) {
                return response()->json([
                    'erro' => 'Sequência de batidas inválida para o regime atual.',
                    'codigo' => 'SEQUENCIA_BATIDA_INVALIDA',
                    'tipo_esperado' => $tipoApiPorBanco[$proximoEsperado] ?? $proximoEsperado,
                ], 422);
            }

            // Regra de saída antecipada: exige confirmação explícita de impacto salarial
            // e permite notificação administrativa para tratamento em cascata.
            $saidaAntecipada = false;
            if ($tipoBanco === 'saida') {
                $horaSaidaCfg = $cfg->HORA_SAIDA ?? '18:00';
                $toleranciaCfg = (int) ($cfg->TOLERANCIA ?? 15);

                [$hhSaida, $mmSaida] = array_map('intval', explode(':', $horaSaidaCfg));
                [$hhAtual, $mmAtual] = array_map('intval', explode(':', substr($hora, 0, 5)));
                $minSaidaCfg = ($hhSaida * 60 + $mmSaida);
                $minAtual = ($hhAtual * 60 + $mmAtual);
                $limiteMin = $minSaidaCfg - $toleranciaCfg;

                $saidaAntecipada = $minAtual < $limiteMin;
                if ($saidaAntecipada && (!$confirmacaoSaidaAntecipada || !$aceiteDescontoSalarial)) {
                    return response()->json([
                        'erro' => 'Saída antecipada detectada. Confirme novamente ciente de possível desconto salarial.',
                        'codigo' => 'SAIDA_ANTECIPADA_REQUER_CONFIRMACAO',
                        'requer_confirmacao' => true,
                        'horario_saida_configurado' => $horaSaidaCfg,
                        'tolerancia_minutos' => $toleranciaCfg,
                    ], 409);
                }

                $calcularJornadaDia = function ($rows) use ($normalizarTipo, $cfg) {
                    $inicioAberto = null;
                    $minTrabalhados = 0;
                    foreach ($rows as $r) {
                        $tipo = $normalizarTipo($r->REGISTRO_TIPO ?? null);
                        $dt = \Carbon\Carbon::parse($r->REGISTRO_DATA_HORA ?? null);
                        if (in_array($tipo, ['entrada', 'ret_alm'], true) && $inicioAberto === null) {
                            $inicioAberto = $dt;
                            continue;
                        }
                        if (in_array($tipo, ['saida_alm', 'saida'], true) && $inicioAberto !== null) {
                            $minTrabalhados += $inicioAberto->diffInMinutes($dt);
                            $inicioAberto = null;
                        }
                    }

                    $horaEntradaCfg = $cfg->HORA_ENTRADA ?? '08:00';
                    $horaSaidaCfgCalc = $cfg->HORA_SAIDA ?? '18:00';
                    $intervaloCfg = (int) ($cfg->INTERVALO_ALMOCO ?? 120);
                    [$he, $me] = array_map('intval', explode(':', $horaEntradaCfg));
                    [$hs, $ms] = array_map('intval', explode(':', $horaSaidaCfgCalc));
                    $brutoMin = (($hs * 60 + $ms) - ($he * 60 + $me));
                    $metaMin = max(0, $brutoMin - $intervaloCfg);

                    return [
                        'min_trabalhados' => $minTrabalhados,
                        'meta_min' => $metaMin,
                        'deficit_min' => max(0, $metaMin - $minTrabalhados),
                        'extra_min' => max(0, $minTrabalhados - $metaMin),
                    ];
                };

                // Regra obrigatória: se a saída fechar o dia com déficit, exige confirmação explícita.
                // Isso evita "encerrar em 1 minuto" sem ciência de impacto no saldo.
                $rowsPrevistos = $rowsDia->map(fn($r) => (object) [
                    'REGISTRO_TIPO' => $r->REGISTRO_TIPO,
                    'REGISTRO_DATA_HORA' => $r->REGISTRO_DATA_HORA,
                ]);
                $rowsPrevistos->push((object) [
                    'REGISTRO_TIPO' => $tipoBanco,
                    'REGISTRO_DATA_HORA' => $dataHora,
                ]);
                $jornadaPrevista = $calcularJornadaDia($rowsPrevistos);
                if ($jornadaPrevista['deficit_min'] > 0 && (!$confirmacaoSaidaAntecipada || !$aceiteDescontoSalarial)) {
                    return response()->json([
                        'erro' => 'Fechamento com déficit de jornada. Confirme novamente ciente do impacto no banco de horas/salário.',
                        'codigo' => 'SAIDA_COM_DEFICIT_REQUER_CONFIRMACAO',
                        'requer_confirmacao' => true,
                        'minutos_trabalhados_dia' => $jornadaPrevista['min_trabalhados'],
                        'meta_minutos_dia' => $jornadaPrevista['meta_min'],
                        'deficit_minutos_dia' => $jornadaPrevista['deficit_min'],
                    ], 409);
                }
            }

            $insert = [
                'FUNCIONARIO_ID' => $func->FUNCIONARIO_ID,
                'REGISTRO_DATA_HORA' => $dataHora,
                'REGISTRO_TIPO' => $tipoBanco,
            ];
            if (\Illuminate\Support\Facades\Schema::hasColumn('REGISTRO_PONTO', 'REGISTRO_ORIGEM')) {
                $insert['REGISTRO_ORIGEM'] = 'WEB';
            }
            if (\Illuminate\Support\Facades\Schema::hasColumn('REGISTRO_PONTO', 'REGISTRO_OBSERVACAO') && $saidaAntecipada) {
                $insert['REGISTRO_OBSERVACAO'] = 'Saída antecipada confirmada pelo servidor com aceite de possível desconto salarial.';
            }

            $id = \Illuminate\Support\Facades\DB::table('REGISTRO_PONTO')->insertGetId($insert);

            $minutosTrabalhadosDia = null;
            $metaMinutosDia = null;
            $deficitMinutosDia = null;
            $extraMinutosDia = null;
            $saidaValidaHorario = null;
            $anomaliaJornadaCurta = false;
            $slaDeficitEstourado = false;
            if ($tipoBanco === 'saida') {
                $rowsDiaAtualizados = \Illuminate\Support\Facades\DB::table('REGISTRO_PONTO')
                    ->where('FUNCIONARIO_ID', $func->FUNCIONARIO_ID)
                    ->whereBetween('REGISTRO_DATA_HORA', [$inicioDia, $fimDia])
                    ->orderBy('REGISTRO_DATA_HORA')
                    ->get(['REGISTRO_TIPO', 'REGISTRO_DATA_HORA']);

                // Soma períodos trabalhados a partir de pares start/end do dia.
                $inicioAberto = null;
                $minutosTrabalhadosDia = 0;
                foreach ($rowsDiaAtualizados as $r) {
                    $tipo = $normalizarTipo($r->REGISTRO_TIPO);
                    $dt = \Carbon\Carbon::parse($r->REGISTRO_DATA_HORA);
                    if (in_array($tipo, ['entrada', 'ret_alm'], true) && $inicioAberto === null) {
                        $inicioAberto = $dt;
                        continue;
                    }
                    if (in_array($tipo, ['saida_alm', 'saida'], true) && $inicioAberto !== null) {
                        $minutosTrabalhadosDia += $inicioAberto->diffInMinutes($dt);
                        $inicioAberto = null;
                    }
                }

                $horaEntradaCfg = $cfg->HORA_ENTRADA ?? '08:00';
                $horaSaidaCfg = $cfg->HORA_SAIDA ?? '18:00';
                $intervaloCfg = (int) ($cfg->INTERVALO_ALMOCO ?? 120);
                [$he, $me] = array_map('intval', explode(':', $horaEntradaCfg));
                [$hs, $ms] = array_map('intval', explode(':', $horaSaidaCfg));
                $brutoMin = (($hs * 60 + $ms) - ($he * 60 + $me));
                $metaMinutosDia = max(0, $brutoMin - $intervaloCfg);

                $deficitMinutosDia = max(0, $metaMinutosDia - $minutosTrabalhadosDia);
                $extraMinutosDia = max(0, $minutosTrabalhadosDia - $metaMinutosDia);
                $saidaValidaHorario = $minutosTrabalhadosDia >= $metaMinutosDia;
                $anomaliaJornadaCurta = $minutosTrabalhadosDia <= 2;
                $slaDeficitEstourado = $deficitMinutosDia >= 120;

                if (\Illuminate\Support\Facades\Schema::hasTable('NOTIFICACAO') && $deficitMinutosDia > 0) {
                    $urlDia = '/banco-horas?data=' . $data;
                    $jaTemDeficitDia = \Illuminate\Support\Facades\DB::table('NOTIFICACAO')
                        ->where('USUARIO_ID', (int) ($user->USUARIO_ID ?? 0))
                        ->where('NOTIFICACAO_TIPO', 'ponto_deficit_diario')
                        ->where('NOTIFICACAO_URL', $urlDia)
                        ->exists();
                    if (!$jaTemDeficitDia) {
                        $fmt = fn($min) => floor($min / 60) . 'h' . str_pad((string) ($min % 60), 2, '0', STR_PAD_LEFT);
                        \Illuminate\Support\Facades\DB::table('NOTIFICACAO')->insert([
                            'USUARIO_ID' => (int) ($user->USUARIO_ID ?? 0),
                            'NOTIFICACAO_TITULO' => 'Déficit de jornada no dia',
                            'NOTIFICACAO_BODY' => 'Hoje você trabalhou ' . $fmt($minutosTrabalhadosDia) . ' de ' . $fmt($metaMinutosDia) . ' previstos. Déficit: ' . $fmt($deficitMinutosDia) . '.',
                            'NOTIFICACAO_TIPO' => 'ponto_deficit_diario',
                            'NOTIFICACAO_ICONE' => '📉',
                            'NOTIFICACAO_URL' => $urlDia,
                            'NOTIFICACAO_LIDA' => 0,
                            'NOTIFICACAO_DT_CRIACAO' => now(),
                        ]);
                    }
                }

                // Ledger diário imutável/versionado para auditoria e consistência entre telas.
                if (\Illuminate\Support\Facades\Schema::hasTable('JORNADA_LEDGER')) {
                    $competenciaDia = substr($data, 0, 7);
                    $maxVersao = (int) (\Illuminate\Support\Facades\DB::table('JORNADA_LEDGER')
                        ->where('FUNCIONARIO_ID', $func->FUNCIONARIO_ID)
                        ->where('JORNADA_DATA', $data)
                        ->max('VERSAO') ?? 0);
                    $versao = $maxVersao + 1;
                    $hashAnterior = (string) (\Illuminate\Support\Facades\DB::table('JORNADA_LEDGER')
                        ->where('FUNCIONARIO_ID', $func->FUNCIONARIO_ID)
                        ->where('JORNADA_DATA', $data)
                        ->orderByDesc('VERSAO')
                        ->value('HASH_AUDITORIA') ?? '');

                    $saldoHorasDia = round(($extraMinutosDia - $deficitMinutosDia) / 60, 2);
                    $horasCreditadas = round($extraMinutosDia / 60, 2);
                    $horasDebitada = round($deficitMinutosDia / 60, 2);
                    $tipoLancamento = $saldoHorasDia > 0 ? 'credito' : ($saldoHorasDia < 0 ? 'debito' : 'ajuste');
                    $payloadAuditoria = [
                        'funcionario_id' => (int) $func->FUNCIONARIO_ID,
                        'data' => $data,
                        'hora_saida' => substr($hora, 0, 5),
                        'regime' => $regimeCfg,
                        'min_trabalhados' => (int) $minutosTrabalhadosDia,
                        'min_meta' => (int) $metaMinutosDia,
                        'min_delta' => (int) (($extraMinutosDia ?? 0) - ($deficitMinutosDia ?? 0)),
                        'saida_antecipada' => (bool) $saidaAntecipada,
                        'anomalia_jornada_curta' => (bool) $anomaliaJornadaCurta,
                        'sla_deficit_estourado' => (bool) $slaDeficitEstourado,
                    ];
                    $hashAuditoria = hash('sha256', json_encode($payloadAuditoria, JSON_UNESCAPED_UNICODE) . '|' . $versao . '|' . $hashAnterior);

                    \Illuminate\Support\Facades\DB::table('JORNADA_LEDGER')->insert([
                        'FUNCIONARIO_ID' => (int) $func->FUNCIONARIO_ID,
                        'COMPETENCIA' => $competenciaDia,
                        'JORNADA_DATA' => $data,
                        'LANCAMENTO_TIPO' => $tipoLancamento,
                        'MINUTOS_TRABALHADOS' => (int) $minutosTrabalhadosDia,
                        'MINUTOS_META' => (int) $metaMinutosDia,
                        'MINUTOS_DELTA' => (int) (($extraMinutosDia ?? 0) - ($deficitMinutosDia ?? 0)),
                        'HORAS_CREDITADAS' => $horasCreditadas,
                        'HORAS_DEBITADAS' => $horasDebitada,
                        'SALDO_HORAS' => $saldoHorasDia,
                        'VERSAO' => $versao,
                        'ORIGEM' => 'ponto_web',
                        'MOTIVO' => $saidaAntecipada ? 'fechamento_saida_antecipada' : 'fechamento_expediente',
                        'DETALHE' => json_encode($payloadAuditoria, JSON_UNESCAPED_UNICODE),
                        'HASH_AUDITORIA' => $hashAuditoria,
                        'GERADO_POR_USUARIO_ID' => (int) ($user->USUARIO_ID ?? 0),
                        'GERADO_EM' => now(),
                        'created_at' => now(),
                        'updated_at' => now(),
                    ]);
                }
            }

            if ($saidaAntecipada && \Illuminate\Support\Facades\Schema::hasTable('COMUNICADO')) {
                try {
                    $pessoa = \Illuminate\Support\Facades\DB::table('PESSOA')
                        ->join('FUNCIONARIO', 'FUNCIONARIO.PESSOA_ID', '=', 'PESSOA.PESSOA_ID')
                        ->where('FUNCIONARIO.FUNCIONARIO_ID', $func->FUNCIONARIO_ID)
                        ->select('PESSOA.PESSOA_NOME', 'FUNCIONARIO.FUNCIONARIO_MATRICULA')
                        ->first();

                    $comunicado = [
                        'USUARIO_ID' => $user->USUARIO_ID ?? null,
                    ];

                    if (\Illuminate\Support\Facades\Schema::hasColumn('COMUNICADO', 'COMUNICADO_TITULO')) {
                        $comunicado['COMUNICADO_TITULO'] = 'Saída antecipada registrada';
                    } elseif (\Illuminate\Support\Facades\Schema::hasColumn('COMUNICADO', 'TITULO')) {
                        $comunicado['TITULO'] = 'Saída antecipada registrada';
                    }

                    $conteudo = 'Servidor: ' . ($pessoa->PESSOA_NOME ?? 'N/I')
                        . ' | Matrícula: ' . ($pessoa->FUNCIONARIO_MATRICULA ?? 'N/I')
                        . ' | Data/Hora: ' . $dataHora
                        . ' | Ação: saída antecipada confirmada com aceite de possível desconto salarial.';

                    if (\Illuminate\Support\Facades\Schema::hasColumn('COMUNICADO', 'COMUNICADO_CONTEUDO')) {
                        $comunicado['COMUNICADO_CONTEUDO'] = $conteudo;
                    } elseif (\Illuminate\Support\Facades\Schema::hasColumn('COMUNICADO', 'CONTEUDO')) {
                        $comunicado['CONTEUDO'] = $conteudo;
                    }

                    if (\Illuminate\Support\Facades\Schema::hasColumn('COMUNICADO', 'COMUNICADO_CATEGORIA')) {
                        $comunicado['COMUNICADO_CATEGORIA'] = 'ponto';
                    } elseif (\Illuminate\Support\Facades\Schema::hasColumn('COMUNICADO', 'CATEGORIA')) {
                        $comunicado['CATEGORIA'] = 'ponto';
                    }

                    if (\Illuminate\Support\Facades\Schema::hasColumn('COMUNICADO', 'COMUNICADO_PRIORIDADE')) {
                        $comunicado['COMUNICADO_PRIORIDADE'] = 'alta';
                    } elseif (\Illuminate\Support\Facades\Schema::hasColumn('COMUNICADO', 'PRIORIDADE')) {
                        $comunicado['PRIORIDADE'] = 'alta';
                    }

                    if (\Illuminate\Support\Facades\Schema::hasColumn('COMUNICADO', 'COMUNICADO_DATA')) {
                        $comunicado['COMUNICADO_DATA'] = $data;
                    }
                    if (\Illuminate\Support\Facades\Schema::hasColumn('COMUNICADO', 'COMUNICADO_AUTOR')) {
                        $comunicado['COMUNICADO_AUTOR'] = 'Sistema Ponto';
                    } elseif (\Illuminate\Support\Facades\Schema::hasColumn('COMUNICADO', 'AUTOR_NOME')) {
                        $comunicado['AUTOR_NOME'] = 'Sistema Ponto';
                    }
                    if (\Illuminate\Support\Facades\Schema::hasColumn('COMUNICADO', 'COMUNICADO_SETOR')) {
                        $comunicado['COMUNICADO_SETOR'] = 'RH';
                    } elseif (\Illuminate\Support\Facades\Schema::hasColumn('COMUNICADO', 'AUTOR_SETOR')) {
                        $comunicado['AUTOR_SETOR'] = 'RH';
                    }
                    if (\Illuminate\Support\Facades\Schema::hasColumn('COMUNICADO', 'COMUNICADO_FIXADO')) {
                        $comunicado['COMUNICADO_FIXADO'] = 0;
                    } elseif (\Illuminate\Support\Facades\Schema::hasColumn('COMUNICADO', 'FIXADO')) {
                        $comunicado['FIXADO'] = 0;
                    }
                    if (\Illuminate\Support\Facades\Schema::hasColumn('COMUNICADO', 'ATIVO')) {
                        $comunicado['ATIVO'] = 1;
                    }
                    if (\Illuminate\Support\Facades\Schema::hasColumn('COMUNICADO', 'created_at')) {
                        $comunicado['created_at'] = now();
                    }
                    if (\Illuminate\Support\Facades\Schema::hasColumn('COMUNICADO', 'updated_at')) {
                        $comunicado['updated_at'] = now();
                    }

                    \Illuminate\Support\Facades\DB::table('COMUNICADO')->insert($comunicado);
                } catch (\Throwable $e) {
                    \Illuminate\Support\Facades\Log::warning('Falha ao notificar saída antecipada: ' . $e->getMessage());
                }
            }

            if (\Illuminate\Support\Facades\Schema::hasTable('NOTIFICACAO') && $tipoBanco === 'saida') {
                try {
                    $nomeServidor = \Illuminate\Support\Facades\DB::table('PESSOA')
                        ->join('FUNCIONARIO', 'FUNCIONARIO.PESSOA_ID', '=', 'PESSOA.PESSOA_ID')
                        ->where('FUNCIONARIO.FUNCIONARIO_ID', $func->FUNCIONARIO_ID)
                        ->value('PESSOA.PESSOA_NOME');

                    // 1) Notificação para o próprio servidor (sempre que encerrar expediente)
                    $tituloServidor = $saidaAntecipada ? 'Saída antecipada registrada' : 'Expediente encerrado';
                    $bodyServidor = $saidaAntecipada
                        ? 'Sua saída antecipada foi registrada. Isso pode gerar horas negativas e impacto salarial. Confira o Banco de Horas.'
                        : 'Seu expediente foi encerrado com sucesso. Confira seu Banco de Horas para validar o saldo do dia.';
                    \Illuminate\Support\Facades\DB::table('NOTIFICACAO')->insert([
                        'USUARIO_ID' => (int) ($user->USUARIO_ID ?? 0),
                        'NOTIFICACAO_TITULO' => $tituloServidor,
                        'NOTIFICACAO_BODY' => $bodyServidor,
                        'NOTIFICACAO_TIPO' => $saidaAntecipada ? 'ponto_saida_antecipada' : 'ponto_saida',
                        'NOTIFICACAO_ICONE' => $saidaAntecipada ? '⚠️' : '✅',
                        'NOTIFICACAO_URL' => '/banco-horas',
                        'NOTIFICACAO_LIDA' => 0,
                        'NOTIFICACAO_DT_CRIACAO' => now(),
                    ]);

                    // 2) Notificação para administração/RH (controle de auditoria)
                    if ($saidaAntecipada || $anomaliaJornadaCurta || $slaDeficitEstourado) {
                        $gestaoUserIds = collect();
                        if (
                            \Illuminate\Support\Facades\Schema::hasTable('USUARIO_PERFIL')
                            && \Illuminate\Support\Facades\Schema::hasTable('PERFIL')
                        ) {
                            $gestaoUserIds = \Illuminate\Support\Facades\DB::table('USUARIO_PERFIL as up')
                                ->join('PERFIL as p', 'p.PERFIL_ID', '=', 'up.PERFIL_ID')
                                ->where('up.USUARIO_PERFIL_ATIVO', 1)
                                ->where('p.PERFIL_ATIVO', 1)
                                ->where(function ($q) {
                                    $q->whereRaw('UPPER(p.PERFIL_NOME) LIKE ?', ['%ADMIN%'])
                                        ->orWhereRaw('UPPER(p.PERFIL_NOME) LIKE ?', ['%RH%'])
                                        ->orWhereRaw('UPPER(p.PERFIL_NOME) LIKE ?', ['%GESTOR%']);
                                })
                                ->pluck('up.USUARIO_ID')
                                ->filter(fn($id) => !empty($id))
                                ->unique()
                                ->values();
                        }

                        $eventosGestao = [];
                        if ($saidaAntecipada) $eventosGestao[] = 'saída antecipada';
                        if ($anomaliaJornadaCurta) $eventosGestao[] = 'jornada curta suspeita';
                        if ($slaDeficitEstourado) $eventosGestao[] = 'déficit acima do SLA';

                        $tituloGestao = count($eventosGestao) > 1
                            ? 'Alerta crítico no fechamento de ponto'
                            : (
                                $anomaliaJornadaCurta
                                    ? 'Alerta: anomalia de jornada'
                                    : ($slaDeficitEstourado ? 'Alerta: SLA de déficit estourado' : 'Alerta: saída antecipada')
                            );
                        $bodyGestao = 'Servidor ' . ($nomeServidor ?: 'N/I')
                            . ' fechou ponto em ' . $dataHora
                            . ' | Eventos: ' . implode(', ', $eventosGestao)
                            . ' | Déficit: ' . (int) ($deficitMinutosDia ?? 0) . ' min.';

                        foreach ($gestaoUserIds as $uid) {
                            $urlAlerta = '/ponto?alerta_data=' . $data;
                            $jaTemAlertaGestao = \Illuminate\Support\Facades\DB::table('NOTIFICACAO')
                                ->where('USUARIO_ID', (int) $uid)
                                ->where('NOTIFICACAO_TIPO', 'ponto_alerta_gestao')
                                ->where('NOTIFICACAO_URL', $urlAlerta)
                                ->whereBetween('NOTIFICACAO_DT_CRIACAO', [now()->startOfDay(), now()->endOfDay()])
                                ->exists();
                            if ($jaTemAlertaGestao) {
                                continue;
                            }
                            \Illuminate\Support\Facades\DB::table('NOTIFICACAO')->insert([
                                'USUARIO_ID' => (int) $uid,
                                'NOTIFICACAO_TITULO' => $tituloGestao,
                                'NOTIFICACAO_BODY' => $bodyGestao,
                                'NOTIFICACAO_TIPO' => 'ponto_alerta_gestao',
                                'NOTIFICACAO_ICONE' => '🚨',
                                'NOTIFICACAO_URL' => $urlAlerta,
                                'NOTIFICACAO_LIDA' => 0,
                                'NOTIFICACAO_DT_CRIACAO' => now(),
                            ]);
                        }
                    }
                } catch (\Throwable $e) {
                    \Illuminate\Support\Facades\Log::warning('Falha ao registrar notificação in-app de saída antecipada: ' . $e->getMessage());
                }
            }

            return response()->json([
                'ok' => true,
                'id' => $id,
                'hora' => substr($hora, 0, 5),
                'tipo' => $tipoApiPorBanco[$tipoBanco] ?? $tipoBanco,
                'protocolo' => 'REG-' . str_pad($id, 6, '0', STR_PAD_LEFT),
                'saida_antecipada' => $saidaAntecipada,
                'saida_valida_horaria' => $saidaValidaHorario,
                'minutos_trabalhados_dia' => $minutosTrabalhadosDia,
                'meta_minutos_dia' => $metaMinutosDia,
                'deficit_minutos_dia' => $deficitMinutosDia,
                'extra_minutos_dia' => $extraMinutosDia,
                'anomalia_jornada_curta' => $anomaliaJornadaCurta,
                'sla_deficit_estourado' => $slaDeficitEstourado,
            ]);
        } catch (\Throwable $e) {
            return response()->json(['erro' => $e->getMessage()], 500);
        }
    });

    }); // fim auth+audit: POST /ponto/registro

    // Endpoint temporário para QA local: reinicia as batidas do dia atual.
    // Segurança: bloqueado em produção + exige confirmação explícita no payload.
    Route::middleware(['auth', 'alterar.senha', 'audit'])->group(function () {
    Route::post('/ponto/reset-dia-teste', function (\Illuminate\Http\Request $request) {
        try {
            if (app()->isProduction()) {
                return response()->json(['erro' => 'Operação indisponível em produção.'], 403);
            }
            if (!$request->boolean('confirmar_reset_teste')) {
                return response()->json(['erro' => 'Confirmação obrigatória para reset de teste.'], 422);
            }

            $user = \Illuminate\Support\Facades\Auth::user();
            if (!$user) {
                return response()->json(['erro' => 'Não autenticado.'], 401);
            }

            $func = \App\Models\Funcionario::where('USUARIO_ID', $user->USUARIO_ID ?? 0)->first();
            if (!$func) {
                return response()->json(['erro' => 'Funcionário não encontrado para o usuário logado.'], 404);
            }

            $hoje = now()->toDateString();
            $deletados = \Illuminate\Support\Facades\DB::table('REGISTRO_PONTO')
                ->where('FUNCIONARIO_ID', $func->FUNCIONARIO_ID)
                ->whereBetween('REGISTRO_DATA_HORA', ["{$hoje} 00:00:00", "{$hoje} 23:59:59"])
                ->delete();

            $ledgersRemovidos = 0;
            if (\Illuminate\Support\Facades\Schema::hasTable('JORNADA_LEDGER')) {
                $ledgersRemovidos = \Illuminate\Support\Facades\DB::table('JORNADA_LEDGER')
                    ->where('FUNCIONARIO_ID', $func->FUNCIONARIO_ID)
                    ->where('JORNADA_DATA', $hoje)
                    ->delete();
            }

            return response()->json([
                'ok' => true,
                'mensagem' => 'Batidas do dia reiniciadas para teste.',
                'registros_removidos' => $deletados,
                'ledger_removidos' => $ledgersRemovidos,
            ]);
        } catch (\Throwable $e) {
            return response()->json(['erro' => $e->getMessage()], 500);
        }
    });

    Route::get('/ponto/ledger/verificar-integridade', function (\Illuminate\Http\Request $request) {
        try {
            if (!\Illuminate\Support\Facades\Schema::hasTable('JORNADA_LEDGER')) {
                return response()->json(['ok' => false, 'erro' => 'JORNADA_LEDGER indisponível.'], 422);
            }
            $user = \Illuminate\Support\Facades\Auth::user();
            if (!$user) {
                return response()->json(['erro' => 'Não autenticado.'], 401);
            }
            $func = \App\Models\Funcionario::where('USUARIO_ID', $user->USUARIO_ID ?? 0)->first();
            if (!$func) {
                return response()->json(['erro' => 'Funcionário não encontrado.'], 404);
            }

            $competencia = (string) ($request->competencia ?? now()->format('Y-m'));
            $rows = \Illuminate\Support\Facades\DB::table('JORNADA_LEDGER')
                ->where('FUNCIONARIO_ID', $func->FUNCIONARIO_ID)
                ->where('COMPETENCIA', $competencia)
                ->orderBy('JORNADA_DATA')
                ->orderBy('VERSAO')
                ->get();

            $hashAnterior = '';
            $inconsistencias = [];
            foreach ($rows as $r) {
                $detalhe = json_decode((string) ($r->DETALHE ?? '{}'), true);
                if (!is_array($detalhe)) {
                    $detalhe = [];
                }
                $hashEsperado = hash(
                    'sha256',
                    json_encode($detalhe, JSON_UNESCAPED_UNICODE) . '|' . (int) ($r->VERSAO ?? 0) . '|' . $hashAnterior
                );
                $hashAtual = (string) ($r->HASH_AUDITORIA ?? '');
                if ($hashEsperado !== $hashAtual) {
                    $inconsistencias[] = [
                        'jornada_data' => $r->JORNADA_DATA,
                        'versao' => (int) ($r->VERSAO ?? 0),
                        'hash_esperado' => $hashEsperado,
                        'hash_atual' => $hashAtual,
                    ];
                }
                $hashAnterior = $hashAtual;
            }

            return response()->json([
                'ok' => count($inconsistencias) === 0,
                'competencia' => $competencia,
                'total_registros' => count($rows),
                'inconsistencias' => $inconsistencias,
            ]);
        } catch (\Throwable $e) {
            return response()->json(['ok' => false, 'erro' => $e->getMessage()], 500);
        }
    });

    Route::post('/ponto/reconciliacao/sugerir', function (\Illuminate\Http\Request $request) {
        try {
            $user = \Illuminate\Support\Facades\Auth::user();
            if (!$user) {
                return response()->json(['erro' => 'Não autenticado.'], 401);
            }
            $func = \App\Models\Funcionario::where('USUARIO_ID', $user->USUARIO_ID ?? 0)->first();
            if (!$func) {
                return response()->json(['erro' => 'Funcionário não encontrado.'], 404);
            }
            $dados = $request->validate([
                'data' => ['required', 'regex:/^\d{4}-\d{2}-\d{2}$/'],
                'motivo' => ['nullable', 'string', 'max:500'],
                'sugestao' => ['nullable', 'string', 'max:500'],
            ]);

            $nomeServidor = \Illuminate\Support\Facades\DB::table('PESSOA')
                ->join('FUNCIONARIO', 'FUNCIONARIO.PESSOA_ID', '=', 'PESSOA.PESSOA_ID')
                ->where('FUNCIONARIO.FUNCIONARIO_ID', $func->FUNCIONARIO_ID)
                ->value('PESSOA.PESSOA_NOME');
            $titulo = 'Reconciliação de ponto pendente de aprovação';
            $body = 'Servidor ' . ($nomeServidor ?: 'N/I')
                . ' solicitou reconciliação para ' . $dados['data']
                . '. Sugestão: ' . ($dados['sugestao'] ?? 'ajuste automático')
                . '. Motivo: ' . ($dados['motivo'] ?? 'não informado') . '.';

            if (\Illuminate\Support\Facades\Schema::hasTable('NOTIFICACAO')) {
                $gestaoUserIds = collect();
                if (
                    \Illuminate\Support\Facades\Schema::hasTable('USUARIO_PERFIL')
                    && \Illuminate\Support\Facades\Schema::hasTable('PERFIL')
                ) {
                    $gestaoUserIds = \Illuminate\Support\Facades\DB::table('USUARIO_PERFIL as up')
                        ->join('PERFIL as p', 'p.PERFIL_ID', '=', 'up.PERFIL_ID')
                        ->where('up.USUARIO_PERFIL_ATIVO', 1)
                        ->where('p.PERFIL_ATIVO', 1)
                        ->where(function ($q) {
                            $q->whereRaw('UPPER(p.PERFIL_NOME) LIKE ?', ['%ADMIN%'])
                                ->orWhereRaw('UPPER(p.PERFIL_NOME) LIKE ?', ['%RH%'])
                                ->orWhereRaw('UPPER(p.PERFIL_NOME) LIKE ?', ['%GESTOR%']);
                        })
                        ->pluck('up.USUARIO_ID')
                        ->filter(fn($id) => !empty($id))
                        ->unique()
                        ->values();
                }
                foreach ($gestaoUserIds as $uid) {
                    \Illuminate\Support\Facades\DB::table('NOTIFICACAO')->insert([
                        'USUARIO_ID' => (int) $uid,
                        'NOTIFICACAO_TITULO' => $titulo,
                        'NOTIFICACAO_BODY' => $body,
                        'NOTIFICACAO_TIPO' => 'ponto_reconciliacao_pendente',
                        'NOTIFICACAO_ICONE' => '🛠️',
                        'NOTIFICACAO_URL' => '/ponto',
                        'NOTIFICACAO_LIDA' => 0,
                        'NOTIFICACAO_DT_CRIACAO' => now(),
                    ]);
                }
            }

            return response()->json(['ok' => true, 'status' => 'pendente_aprovacao']);
        } catch (\Throwable $e) {
            return response()->json(['erro' => $e->getMessage()], 500);
        }
    });

    Route::get('/ponto/heatmap-risco/config', function () {
        try {
            $defaultConfig = [
                'global' => ['medio' => 3.0, 'alto' => 6.0, 'critico' => 9.0],
                'perfis' => [
                    'UTI' => ['medio' => 2.5, 'alto' => 5.0, 'critico' => 7.5],
                    'EMERGENCIA' => ['medio' => 2.5, 'alto' => 5.0, 'critico' => 7.5],
                    'CENTRO_CIRURGICO' => ['medio' => 2.8, 'alto' => 5.5, 'critico' => 8.0],
                ],
                'setores' => [],
            ];
            $disk = \Illuminate\Support\Facades\Storage::disk('local');
            $path = 'ponto_heatmap_risco_config.json';
            if (!$disk->exists($path)) {
                return response()->json(['config' => $defaultConfig, 'fonte' => 'default']);
            }
            $raw = (string) $disk->get($path);
            $cfg = json_decode($raw, true);
            if (!is_array($cfg)) {
                return response()->json(['config' => $defaultConfig, 'fonte' => 'default_corrompido']);
            }
            $cfg = array_replace_recursive($defaultConfig, $cfg);
            return response()->json(['config' => $cfg, 'fonte' => 'persistido']);
        } catch (\Throwable $e) {
            return response()->json(['erro' => $e->getMessage()], 500);
        }
    });

    Route::put('/ponto/heatmap-risco/config', function (\Illuminate\Http\Request $request) {
        try {
            $user = \Illuminate\Support\Facades\Auth::user();
            $perfilAtual = strtolower(trim((string) ($user->PERFIL ?? '')));
            if (!$perfilAtual) {
                $perfilAtual = strtolower(trim((string) (optional($user->usuarioPerfis()->with('perfil')->first())->perfil->PERFIL_NOME ?? '')));
            }
            if (!in_array($perfilAtual, ['admin', 'rh', 'administrador'], true)) {
                return response()->json(['erro' => 'Nao autorizado'], 403);
            }

            $dados = $request->validate([
                'global' => 'nullable|array',
                'global.medio' => 'nullable|numeric|min:0|max:999',
                'global.alto' => 'nullable|numeric|min:0|max:999',
                'global.critico' => 'nullable|numeric|min:0|max:999',
                'perfis' => 'nullable|array',
                'setores' => 'nullable|array',
            ]);

            $defaultConfig = [
                'global' => ['medio' => 3.0, 'alto' => 6.0, 'critico' => 9.0],
                'perfis' => [
                    'UTI' => ['medio' => 2.5, 'alto' => 5.0, 'critico' => 7.5],
                    'EMERGENCIA' => ['medio' => 2.5, 'alto' => 5.0, 'critico' => 7.5],
                    'CENTRO_CIRURGICO' => ['medio' => 2.8, 'alto' => 5.5, 'critico' => 8.0],
                ],
                'setores' => [],
            ];
            $disk = \Illuminate\Support\Facades\Storage::disk('local');
            $path = 'ponto_heatmap_risco_config.json';
            $atual = $defaultConfig;
            if ($disk->exists($path)) {
                $raw = (string) $disk->get($path);
                $cfgAtual = json_decode($raw, true);
                if (is_array($cfgAtual)) {
                    $atual = array_replace_recursive($defaultConfig, $cfgAtual);
                }
            }
            $novo = array_replace_recursive($atual, $dados);
            $g = $novo['global'];
            if (($g['medio'] ?? 0) > ($g['alto'] ?? 0) || ($g['alto'] ?? 0) > ($g['critico'] ?? 0)) {
                return response()->json(['erro' => 'Ordem inválida de limiares: medio <= alto <= critico.'], 422);
            }
            $disk->put($path, json_encode($novo, JSON_PRETTY_PRINT | JSON_UNESCAPED_UNICODE));
            return response()->json(['ok' => true, 'config' => $novo]);
        } catch (\Throwable $e) {
            return response()->json(['erro' => $e->getMessage()], 500);
        }
    });

    }); // fim auth+audit: reset-dia-teste, ledger integrity, reconciliacao, heatmap config GET/PUT

    Route::get('/ponto/heatmap-risco', function (\Illuminate\Http\Request $request) {
        try {
            $comp = (string) ($request->competencia ?? now()->format('Y-m'));
            [$ano, $mes] = explode('-', $comp);
            $inicio = \Carbon\Carbon::createFromDate((int) $ano, (int) $mes, 1)->startOfDay();
            $fim = (clone $inicio)->endOfMonth()->endOfDay();
            $hojeFim = now()->endOfDay();
            if ($fim->gt($hojeFim)) {
                $fim = $hojeFim;
            }

            $defaultConfig = [
                'global' => ['medio' => 3.0, 'alto' => 6.0, 'critico' => 9.0],
                'perfis' => [
                    'UTI' => ['medio' => 2.5, 'alto' => 5.0, 'critico' => 7.5],
                    'EMERGENCIA' => ['medio' => 2.5, 'alto' => 5.0, 'critico' => 7.5],
                    'CENTRO_CIRURGICO' => ['medio' => 2.8, 'alto' => 5.5, 'critico' => 8.0],
                ],
                'setores' => [],
            ];
            $disk = \Illuminate\Support\Facades\Storage::disk('local');
            $pathCfg = 'ponto_heatmap_risco_config.json';
            $cfgRisco = $defaultConfig;
            if ($disk->exists($pathCfg)) {
                $raw = (string) $disk->get($pathCfg);
                $cfgPersist = json_decode($raw, true);
                if (is_array($cfgPersist)) {
                    $cfgRisco = array_replace_recursive($defaultConfig, $cfgPersist);
                }
            }

            $perfilPorNomeSetor = function (string $nomeSetor): string {
                $u = strtoupper($nomeSetor);
                if (str_contains($u, 'UTI')) return 'UTI';
                if (str_contains($u, 'EMERGENCIA') || str_contains($u, 'EMERGÊNCIA') || str_contains($u, 'PRONTO SOCORRO')) return 'EMERGENCIA';
                if (str_contains($u, 'CENTRO CIRURG') || str_contains($u, 'CENTRO_CIRURG') || str_contains($u, 'CC')) return 'CENTRO_CIRURGICO';
                return 'GERAL';
            };
            $limiaresDoSetor = function (string $setorNome) use ($cfgRisco, $perfilPorNomeSetor) {
                $upperSetor = strtoupper(trim($setorNome));
                $base = $cfgRisco['global'] ?? ['medio' => 3.0, 'alto' => 6.0, 'critico' => 9.0];
                $perfil = $perfilPorNomeSetor($setorNome);
                if (isset($cfgRisco['perfis'][$perfil]) && is_array($cfgRisco['perfis'][$perfil])) {
                    $base = array_merge($base, $cfgRisco['perfis'][$perfil]);
                }
                if (isset($cfgRisco['setores'][$upperSetor]) && is_array($cfgRisco['setores'][$upperSetor])) {
                    $base = array_merge($base, $cfgRisco['setores'][$upperSetor]);
                }
                return [
                    'medio' => (float) ($base['medio'] ?? 3.0),
                    'alto' => (float) ($base['alto'] ?? 6.0),
                    'critico' => (float) ($base['critico'] ?? 9.0),
                    'perfil' => $perfil,
                ];
            };
            $classificarSeveridade = function (float $indice, array $limiares) {
                if ($indice >= (float) $limiares['critico']) return 'critico';
                if ($indice >= (float) $limiares['alto']) return 'alto';
                if ($indice >= (float) $limiares['medio']) return 'medio';
                return 'baixo';
            };

            $funcionarios = \Illuminate\Support\Facades\DB::table('FUNCIONARIO as f')
                ->leftJoin('LOTACAO as l', function ($join) {
                    $join->on('l.FUNCIONARIO_ID', '=', 'f.FUNCIONARIO_ID')
                        ->whereNull('l.LOTACAO_DATA_FIM');
                })
                ->leftJoin('SETOR as s', 's.SETOR_ID', '=', 'l.SETOR_ID')
                ->select(
                    'f.FUNCIONARIO_ID',
                    \Illuminate\Support\Facades\DB::raw("COALESCE(s.SETOR_NOME, 'Sem setor') as setor_nome")
                )
                ->get();

            $porSetor = [];
            foreach ($funcionarios as $fRow) {
                $cfg = \Illuminate\Support\Facades\DB::table('PONTO_CONFIG_FUNCIONARIO')
                    ->where('FUNCIONARIO_ID', $fRow->FUNCIONARIO_ID)
                    ->first();
                $entrada = substr((string) ($cfg->HORA_ENTRADA ?? '08:00'), 0, 5);
                $saida = substr((string) ($cfg->HORA_SAIDA ?? '18:00'), 0, 5);
                [$he, $me] = array_map('intval', explode(':', $entrada));
                [$hs, $ms] = array_map('intval', explode(':', $saida));
                $intervalo = (int) ($cfg->INTERVALO_ALMOCO ?? 120);
                $metaDia = max(0, (($hs * 60 + $ms) - ($he * 60 + $me)) - $intervalo);
                if ($metaDia <= 0) $metaDia = 480;

                $rows = \Illuminate\Support\Facades\DB::table('REGISTRO_PONTO')
                    ->where('FUNCIONARIO_ID', $fRow->FUNCIONARIO_ID)
                    ->whereBetween('REGISTRO_DATA_HORA', [$inicio->format('Y-m-d H:i:s'), $fim->format('Y-m-d H:i:s')])
                    ->orderBy('REGISTRO_DATA_HORA')
                    ->get(['REGISTRO_TIPO', 'REGISTRO_DATA_HORA']);

                $diasBatida = [];
                $minTrabTotal = 0;
                $porDia = [];
                foreach ($rows as $r) {
                    $d = substr((string) $r->REGISTRO_DATA_HORA, 0, 10);
                    if (!isset($porDia[$d])) $porDia[$d] = [];
                    $porDia[$d][] = $r;
                    $diasBatida[$d] = true;
                }
                $inconsistentes = 0;
                foreach ($porDia as $listaDia) {
                    $iniciado = null;
                    $batidasCount = count($listaDia);
                    if ($batidasCount % 2 !== 0) $inconsistentes++;
                    foreach ($listaDia as $r) {
                        $tipo = strtolower(trim((string) $r->REGISTRO_TIPO));
                        $tipo = $tipo === 'ret_alm' ? 'ret_alm' : ($tipo === 'saida_alm' ? 'saida_alm' : $tipo);
                        $dt = \Carbon\Carbon::parse($r->REGISTRO_DATA_HORA);
                        if (in_array($tipo, ['entrada', 'ret_alm'], true) && $iniciado === null) $iniciado = $dt;
                        if (in_array($tipo, ['saida_alm', 'saida'], true) && $iniciado !== null) {
                            $minTrabTotal += $iniciado->diffInMinutes($dt);
                            $iniciado = null;
                        }
                    }
                }

                $faltas = 0;
                $cursor = $inicio->copy();
                $diasUteis = 0;
                while ($cursor->lte($fim)) {
                    if (!in_array($cursor->dayOfWeek, [0, 6], true)) {
                        $diasUteis++;
                        $key = $cursor->format('Y-m-d');
                        if (!isset($diasBatida[$key])) $faltas++;
                    }
                    $cursor->addDay();
                }
                $esperadoMin = $diasUteis * $metaDia;
                $deficitMin = max(0, $esperadoMin - $minTrabTotal);

                $setor = (string) ($fRow->setor_nome ?: 'Sem setor');
                if (!isset($porSetor[$setor])) {
                    $porSetor[$setor] = [
                        'setor' => $setor,
                        'funcionarios' => 0,
                        'deficit_total_horas' => 0.0,
                        'faltas_total' => 0,
                        'jornadas_inconsistentes' => 0,
                        'indice_risco' => 0,
                    ];
                }
                $porSetor[$setor]['funcionarios']++;
                $porSetor[$setor]['deficit_total_horas'] += round($deficitMin / 60, 2);
                $porSetor[$setor]['faltas_total'] += $faltas;
                $porSetor[$setor]['jornadas_inconsistentes'] += $inconsistentes;
            }

            $heatmap = array_values(array_map(function ($s) {
                $s['indice_risco'] = round(
                    (($s['deficit_total_horas'] * 1.5) + ($s['faltas_total'] * 2) + ($s['jornadas_inconsistentes'] * 1.2))
                    / max(1, $s['funcionarios']),
                    2
                );
                return $s;
            }, $porSetor));

            $heatmap = array_values(array_map(function ($s) use ($limiaresDoSetor, $classificarSeveridade) {
                $limiares = $limiaresDoSetor((string) ($s['setor'] ?? ''));
                $s['perfil_assistencial'] = $limiares['perfil'];
                $s['limiares'] = [
                    'medio' => $limiares['medio'],
                    'alto' => $limiares['alto'],
                    'critico' => $limiares['critico'],
                ];
                $s['severidade'] = $classificarSeveridade((float) ($s['indice_risco'] ?? 0), $limiares);
                return $s;
            }, $heatmap));

            usort($heatmap, fn($a, $b) => $b['indice_risco'] <=> $a['indice_risco']);

            return response()->json([
                'competencia' => $comp,
                'config_risco' => $cfgRisco,
                'setores' => $heatmap,
            ]);
        } catch (\Throwable $e) {
            return response()->json(['erro' => $e->getMessage()], 500);
        }
    });

    // â•?â•?â•?â•?â•?â•?â•?â•?â•?â•?â•?â•?â•?â•?â•?â•?â•?â•?â•?â•?â•?â•?â•?â•?â•?â•?â•?â•?â•?â•?â•?â•?â•?â•?â•?â•?â•?â•?â•?â•?â•?â•?â•?â•?â•?â•?â•?â•?â•?â•?â•?â•?â•?â•?â•?â•?â•?â•?â•?â•?â•?â•?â•?â•?â•?â•?â•?
    // DECLARAÃ‡Ã•ES E REQUERIMENTOS
    // â•?â•?â•?â•?â•?â•?â•?â•?â•?â•?â•?â•?â•?â•?â•?â•?â•?â•?â•?â•?â•?â•?â•?â•?â•?â•?â•?â•?â•?â•?â•?â•?â•?â•?â•?â•?â•?â•?â•?â•?â•?â•?â•?â•?â•?â•?â•?â•?â•?â•?â•?â•?â•?â•?â•?â•?â•?â•?â•?â•?â•?â•?â•?â•?â•?â•?â•?

    // Declarações (servidor) + gestão RH — sessão autenticada (sobrescreve rotas homónimas de api_v3_auth* para a mesma URI)
    Route::middleware(['auth', 'alterar.senha', 'audit'])->group(function () {
    Route::get('/declaracoes', function () {
        try {
            $user = \Illuminate\Support\Facades\Auth::user();
            $func = \App\Models\Funcionario::where('USUARIO_ID', $user->USUARIO_ID ?? 0)->first();

            if (!$func)
                return response()->json(['pedidos' => [], 'fallback' => true]);

            $pedidos = \Illuminate\Support\Facades\DB::table('DECLARACAO')
                ->where('FUNCIONARIO_ID', $func->FUNCIONARIO_ID)
                ->orderByDesc('DECLARACAO_DT_SOLICITACAO')
                ->get()
                ->map(fn($d) => [
                    'id' => $d->DECLARACAO_ID,
                    'nome' => $d->DECLARACAO_TIPO,
                    'data' => $d->DECLARACAO_DT_SOLICITACAO,
                    'status' => $d->DECLARACAO_STATUS ?? 'andamento',
                    'protocolo' => 'REQ-' . date('Y') . '-' . str_pad($d->DECLARACAO_ID, 3, '0', STR_PAD_LEFT),
                    'arquivo' => $d->DECLARACAO_ARQUIVO ?? null,
                ]);

            return response()->json(['pedidos' => $pedidos]);
        } catch (\Throwable $e) {
            return response()->json(['pedidos' => [], 'fallback' => true, 'erro' => $e->getMessage()]);
        }
    });

    Route::post('/declaracoes', function (\Illuminate\Http\Request $request) {
        try {
            $user = \Illuminate\Support\Facades\Auth::user();
            $func = \App\Models\Funcionario::where('USUARIO_ID', $user->USUARIO_ID ?? 0)->first();

            $nome = $request->nome ?? 'Documento';
            $instantaneo = (bool) ($request->instantaneo ?? false);
            $status = $instantaneo ? 'pronto' : 'andamento';

            $id = \Illuminate\Support\Facades\DB::table('DECLARACAO')->insertGetId([
                'FUNCIONARIO_ID' => $func->FUNCIONARIO_ID ?? null,
                'DECLARACAO_TIPO' => $nome,
                'DECLARACAO_STATUS' => $status,
                'DECLARACAO_DT_SOLICITACAO' => now()->toDateString(),
                'DECLARACAO_DT_CONCLUSAO' => $instantaneo ? now()->toDateString() : null,
            ]);

            $protocolo = 'REQ-' . date('Y') . '-' . str_pad($id, 3, '0', STR_PAD_LEFT);

            return response()->json([
                'ok' => true,
                'id' => $id,
                'protocolo' => $protocolo,
                'status' => $status,
            ]);
        } catch (\Throwable $e) {
            return response()->json(['erro' => $e->getMessage()], 500);
        }
    })->middleware('upload.safe');

    Route::get('/declaracoes/{id}/download', function ($id) {
        try {
            $user = \Illuminate\Support\Facades\Auth::user();
            $func = \App\Models\Funcionario::where('USUARIO_ID', $user->USUARIO_ID ?? 0)->first();

            $decl = \Illuminate\Support\Facades\DB::table('DECLARACAO')->where('DECLARACAO_ID', $id)->first();
            if (!$decl)
            return response()->json(['erro' => 'Não encontrado.'], 404);

            // Busca dados do funcionário
            $funcNome = 'Servidor(a)';
            $funcMatricula = '—';
            $cargo = '—';
            $setor = '—';
            $cpf = '—';
            $dtAdmissao = '—';
            if ($func) {
                $pessoa = \Illuminate\Support\Facades\DB::table('PESSOA')
                    ->join('FUNCIONARIO', 'FUNCIONARIO.PESSOA_ID', '=', 'PESSOA.PESSOA_ID')
                    ->where('FUNCIONARIO.FUNCIONARIO_ID', $func->FUNCIONARIO_ID)
                    ->select('PESSOA.PESSOA_NOME', 'PESSOA.PESSOA_CPF_NUMERO')
                    ->first();
                $funcNome = $pessoa->PESSOA_NOME ?? 'Servidor(a)';
                $cpfRaw = preg_replace('/\D/', '', (string) ($pessoa->PESSOA_CPF_NUMERO ?? ''));
                $cpf = strlen($cpfRaw) === 11
                    ? preg_replace('/^(\d{3})(\d{3})(\d{3})(\d{2})$/', '$1.$2.$3-$4', $cpfRaw)
                    : '—';
                $funcMatricula = $func->FUNCIONARIO_MATRICULA ?? '—';
                $dtAdmissao = $func->FUNCIONARIO_DATA_INICIO ? date('d/m/Y', strtotime($func->FUNCIONARIO_DATA_INICIO)) : '—';
                // Cargo e setor via lotação
                $lot = \Illuminate\Support\Facades\DB::table('LOTACAO')
                    ->where('FUNCIONARIO_ID', $func->FUNCIONARIO_ID)
                    ->orderByDesc('LOTACAO_ID')
                    ->first();
                if ($lot) {
                    $cargo = $lot->CARGO ?? $lot->FUNCAO ?? '—';
                    $setor = $lot->SETOR ?? $lot->UNIDADE ?? '—';
                }
            }

            $tipo = $decl->DECLARACAO_TIPO;
            $data = $decl->DECLARACAO_DT_SOLICITACAO ? date('d/m/Y', strtotime($decl->DECLARACAO_DT_SOLICITACAO)) : now()->format('d/m/Y');
            $proto = 'REQ-' . date('Y') . '-' . str_pad($id, 3, '0', STR_PAD_LEFT);
            $hoje = now()->format('d/m/Y');
            $ano = date('Y');

            // Mapa de variáveis para substituição no template
            $vars = [
                '{{NOME}}' => $funcNome,
                '{{MATRICULA}}' => $funcMatricula,
                '{{CARGO}}' => $cargo,
                '{{SETOR}}' => $setor,
                '{{CPF}}' => $cpf,
                '{{DATA_ADMISSAO}}' => $dtAdmissao,
                '{{DATA_HOJE}}' => $hoje,
                '{{PROTOCOLO}}' => $proto,
                '{{TIPO}}' => $tipo,
                '{{ANO}}' => $ano,
            ];

            // Tenta usar modelo personalizado do banco
            $modelo = \Illuminate\Support\Facades\DB::table('DECLARACAO_MODELO')
                ->where('MODELO_TIPO', $tipo)
                ->first();

            if ($modelo && !empty($modelo->MODELO_HTML)) {
                $html = str_replace(array_keys($vars), array_values($vars), $modelo->MODELO_HTML);
            } else {
                // Fallback: HTML padrÃ£o gerado pelo sistema
                $html = <<<HTML
<!DOCTYPE html><html lang="pt-BR"><head><meta charset="UTF-8"><title>{$tipo}</title>
<style>
  body{font-family:Arial,sans-serif;font-size:13px;color:#1e293b;margin:70px auto;max-width:700px;line-height:1.8}
  .topo{text-align:center;border-bottom:3px solid #1e3a8a;padding-bottom:18px;margin-bottom:28px}
  .topo h1{font-size:16px;color:#1e3a8a;margin:0 0 4px}.topo p{font-size:11px;color:#64748b;margin:0}
  .titulo{text-align:center;font-size:15px;font-weight:bold;text-transform:uppercase;letter-spacing:.06em;margin:28px 0 22px}
  .corpo{text-align:justify}
  .tabela{margin:20px 0;border:1px solid #e2e8f0;border-radius:8px;padding:14px 18px;background:#f8fafc}
  .tabela table{width:100%;border-collapse:collapse}
  .tabela td{padding:4px 6px;font-size:12px}.tabela td:first-child{font-weight:bold;color:#475569;width:150px}
  .assinatura{margin-top:60px;text-align:center}
  .linha{border-top:1px solid #1e293b;width:260px;margin:0 auto 6px}
  .assinatura p{font-size:12px;color:#475569;margin:3px 0}
  .rodape{margin-top:36px;text-align:center;font-size:10px;color:#94a3b8;border-top:1px solid #e2e8f0;padding-top:10px}
</style></head><body>
<div class="topo"><h1>PREFEITURA MUNICIPAL — GENTE</h1><p>Departamento de Gestão de Pessoas · Sistema GENTE v3</p></div>
<div class="titulo">{$tipo}</div>
<div class="corpo">
<p>Declaramos, para os devidos fins de direito, que <strong>{$funcNome}</strong>, servidor(a) com matrícula <strong>{$funcMatricula}</strong>, encontra-se regularmente vinculado(a) ao quadro de pessoal desta instituição, na forma da legislação vigente.</p>
<p>Esta declaração é emitida a pedido do(a) interessado(a) e tem validade de <strong>90 (noventa) dias</strong> a contar da data de emissão.</p>
</div>
<div class="tabela"><table>
<tr><td>Servidor(a):</td><td>{$funcNome}</td></tr>
<tr><td>Matrícula:</td><td>{$funcMatricula}</td></tr>
<tr><td>CPF:</td><td>{$cpf}</td></tr>
<tr><td>Cargo:</td><td>{$cargo}</td></tr>
<tr><td>Setor:</td><td>{$setor}</td></tr>
<tr><td>Tipo Documento:</td><td>{$tipo}</td></tr>
<tr><td>Solicitado em:</td><td>{$data}</td></tr>
<tr><td>Protocolo:</td><td>{$proto}</td></tr>
<tr><td>Emitido em:</td><td>{$hoje}</td></tr>
</table></div>
<div class="assinatura"><div class="linha"></div>
<p><strong>Departamento de Gestão de Pessoas</strong></p>
<p>Assinado digitalmente · {$hoje}</p></div>
<div class="rodape">Documento gerado eletronicamente · Protocolo {$proto} · Sistema GENTE v3 · {$hoje}</div>
</body></html>
HTML;
            }

            $pdf = \Barryvdh\DomPDF\Facade\Pdf::loadHTML($html)->setPaper('a4', 'portrait');
            return $pdf->download('declaracao-' . $proto . '.pdf');
        } catch (\Throwable $e) {
            return response()->json(['erro' => $e->getMessage()], 500);
        }
    });

    // â•?â•?â•?â•?â•?â•?â•?â•?â•?â•?â•?â•?â•?â•?â•?â•?â•?â•?â•?â•?â•?â•?â•?â•?â•?â•?â•?â•?â•?â•?â•?â•?â•?â•?â•?â•?â•?â•?â•?â•?â•?â•?â•?â•?â•?â•?â•?â•?â•?â•?â•?â•?â•?â•?â•?â•?â•?â•?â•?â•?â•?â•?â•?â•?â•?â•?â•?
    // RH â€” GESTÃƒO DE DECLARAÃ‡Ã•ES (admin/rh)
    // â•?â•?â•?â•?â•?â•?â•?â•?â•?â•?â•?â•?â•?â•?â•?â•?â•?â•?â•?â•?â•?â•?â•?â•?â•?â•?â•?â•?â•?â•?â•?â•?â•?â•?â•?â•?â•?â•?â•?â•?â•?â•?â•?â•?â•?â•?â•?â•?â•?â•?â•?â•?â•?â•?â•?â•?â•?â•?â•?â•?â•?â•?â•?â•?â•?â•?â•?

    // Lista TODAS as declaraÃ§Ãµes (todos os funcionÃ¡rios) para o RH
    Route::get('/rh/declaracoes', function () {
        try {
            $rows = \Illuminate\Support\Facades\DB::table('DECLARACAO as D')
                ->leftJoin('FUNCIONARIO as F', 'F.FUNCIONARIO_ID', '=', 'D.FUNCIONARIO_ID')
                ->leftJoin('PESSOA as P', 'P.PESSOA_ID', '=', 'F.PESSOA_ID')
                ->orderByRaw("CASE D.DECLARACAO_STATUS WHEN 'pendente' THEN 0 WHEN 'andamento' THEN 1 ELSE 2 END")
                ->orderByDesc('D.DECLARACAO_DT_SOLICITACAO')
                ->select(
                    'D.DECLARACAO_ID as id',
                    'D.DECLARACAO_TIPO as nome',
                    'D.DECLARACAO_STATUS as status',
                    'D.DECLARACAO_OBS as obs',
                    'D.DECLARACAO_DT_SOLICITACAO as data',
                    'D.DECLARACAO_DT_CONCLUSAO as data_conclusao',
                    'P.PESSOA_NOME as servidor',
                    'F.FUNCIONARIO_MATRICULA as matricula'
                )
                ->get()
                ->map(fn($d) => [
                    'id' => $d->id,
                    'nome' => $d->nome,
                    'status' => $d->status ?? 'pendente',
                    'obs' => $d->obs,
                    'data' => $d->data,
                    'servidor' => $d->servidor ?? 'Servidor nÃ£o identificado',
                    'matricula' => $d->matricula ?? 'â€”',
                    'protocolo' => 'REQ-' . date('Y') . '-' . str_pad($d->id, 3, '0', STR_PAD_LEFT),
                ]);

            return response()->json(['itens' => $rows]);
        } catch (\Throwable $e) {
            return response()->json(['itens' => [], 'erro' => $e->getMessage()]);
        }
    });

    // Atualiza status de uma declaraÃ§Ã£o (aprovar ou indeferir)
    Route::patch('/rh/declaracoes/{id}', function (\Illuminate\Http\Request $request, $id) {
        try {
            $status = $request->status;
            $obs = $request->obs ?? null;

            if (!in_array($status, ['pronto', 'indeferido', 'andamento', 'pendente'])) {
            return response()->json(['erro' => 'Status inválido.'], 422);
            }

            \Illuminate\Support\Facades\DB::table('DECLARACAO')
                ->where('DECLARACAO_ID', $id)
                ->update([
                    'DECLARACAO_STATUS' => $status,
                    'DECLARACAO_OBS' => $obs,
                    'DECLARACAO_DT_CONCLUSAO' => in_array($status, ['pronto', 'indeferido']) ? now()->toDateString() : null,
                ]);

            return response()->json(['ok' => true, 'status' => $status]);
        } catch (\Throwable $e) {
            return response()->json(['erro' => $e->getMessage()], 500);
        }
    });

    // â•?â•?â•?â•?â•?â•?â•?â•?â•?â•?â•?â•?â•?â•?â•?â•?â•?â•?â•?â•?â•?â•?â•?â•?â•?â•?â•?â•?â•?â•?â•?â•?â•?â•?â•?â•?â•?â•?â•?â•?â•?â•?â•?â•?â•?â•?â•?â•?â•?â•?â•?â•?â•?â•?â•?â•?â•?â•?â•?â•?â•?â•?â•?â•?â•?â•?â•?
    // RH â€” GESTÃƒO DE MODELOS DE DECLARAÃ‡ÃƒO
    // â•?â•?â•?â•?â•?â•?â•?â•?â•?â•?â•?â•?â•?â•?â•?â•?â•?â•?â•?â•?â•?â•?â•?â•?â•?â•?â•?â•?â•?â•?â•?â•?â•?â•?â•?â•?â•?â•?â•?â•?â•?â•?â•?â•?â•?â•?â•?â•?â•?â•?â•?â•?â•?â•?â•?â•?â•?â•?â•?â•?â•?â•?â•?â•?â•?â•?â•?

    // Lista todos os tipos com info se tÃªm modelo
    Route::get('/rh/modelos', function () {
        try {
            $tipos = [
                'Declaração de Vínculo Empregêtício',
                'Declaração para Financiamento Imobiliário',
                'Declaração de Renda',
                'Certidão de Tempo de Serviço',
                'Declaração de Não Acumulação de Cargos',
                'Ficha Cadastral Completa',
                'Declaração para Bolsas de Estudo',
                'Declaração de Estágio Probatório',
                'Contracheque / Hollerith',
            ];
            // Busca TODOS os modelos do banco (evita problema de encoding no whereIn)
            $todosModelos = \Illuminate\Support\Facades\DB::table('DECLARACAO_MODELO')
                ->select('MODELO_TIPO', 'MODELO_ATUALIZADO_EM')
                ->get();

            // Indexa por tipo usando PHP (string comparison nativa, sem risco de collation)
            $modelosMap = [];
            foreach ($todosModelos as $m) {
                $modelosMap[$m->MODELO_TIPO] = $m;
            }

            $lista = array_map(fn($t) => [
                'tipo' => $t,
                'temModelo' => array_key_exists($t, $modelosMap),
                'atualizadoEm' => $modelosMap[$t]->MODELO_ATUALIZADO_EM ?? null,
            ], $tipos);

            return response()->json(['modelos' => $lista]);
        } catch (\Throwable $e) {
            return response()->json(['erros' => $e->getMessage()], 500);
        }
    });

    // Retorna o HTML de um modelo especÃ­fico
    Route::get('/rh/modelos/{tipo}', function ($tipo) {
        try {
            $tipo = urldecode($tipo);
            $m = \Illuminate\Support\Facades\DB::table('DECLARACAO_MODELO')
                ->where('MODELO_TIPO', $tipo)->first();
            if (!$m)
                return response()->json(['html' => '', 'existe' => false]);
            return response()->json(['html' => $m->MODELO_HTML, 'existe' => true, 'atualizadoEm' => $m->MODELO_ATUALIZADO_EM]);
        } catch (\Throwable $e) {
            return response()->json(['erro' => $e->getMessage()], 500);
        }
    });

    // Cria ou atualiza modelo
    Route::post('/rh/modelos', function (\Illuminate\Http\Request $request) {
        try {
            $tipo = $request->tipo;
            $html = $request->html ?? '';
            if (!$tipo || !$html)
            return response()->json(['erro' => 'tipo e html são obrigatórios'], 422);

            $agora = now()->toDateTimeString();
            $existe = \Illuminate\Support\Facades\DB::table('DECLARACAO_MODELO')
                ->where('MODELO_TIPO', $tipo)->count();

            if ($existe) {
                \Illuminate\Support\Facades\DB::table('DECLARACAO_MODELO')
                    ->where('MODELO_TIPO', $tipo)
                    ->update(['MODELO_HTML' => $html, 'MODELO_ATUALIZADO_EM' => $agora]);
            } else {
                \Illuminate\Support\Facades\DB::table('DECLARACAO_MODELO')->insert([
                    'MODELO_TIPO' => $tipo,
                    'MODELO_HTML' => $html,
                    'MODELO_ATUALIZADO_EM' => $agora,
                ]);
            }
            return response()->json(['ok' => true, 'atualizadoEm' => $agora]);
        } catch (\Throwable $e) {
            return response()->json(['erro' => $e->getMessage()], 500);
        }
    });

    // Remove modelo (volta ao padrÃ£o do sistema)
    Route::delete('/rh/modelos/{tipo}', function ($tipo) {
        try {
            $tipo = urldecode($tipo);
            $deleted = \Illuminate\Support\Facades\DB::table('DECLARACAO_MODELO')->where('MODELO_TIPO', $tipo)->delete();
            if (!$deleted) {
                return response()->json(['erro' => 'Modelo não encontrado.'], 404);
            }
            return response()->json(['ok' => true]);
        } catch (\Throwable $e) {
            return response()->json(['erro' => $e->getMessage()], 500);
        }
    });

    }); // fim auth+audit: declarações + rh/modelos
