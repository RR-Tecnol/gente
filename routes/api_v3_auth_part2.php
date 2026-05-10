<?php
// gerado — não editar cegamente (regen_api_v3_fachada.py)


    // â”€â”€ Dashboard KPIs â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€
    Route::get('/configuracoes/api', [\App\Http\Controllers\ConfiguracaoSistemaController::class, 'api']);

    Route::get('/dashboard', function (\Illuminate\Http\Request $request) {
        $mes = now()->month;
        $ano = now()->year;
        $competencia = sprintf('%04d-%02d', $ano, $mes);
        $totalFuncionarios = 0;
        $abonosPendentes = 0;
        $folhaStatus = 'Aberta';
        $folhaCompetencia = $competencia;
        try {
            $user = auth()->user();
            $permitidos = \App\Support\UnidadeEscopoUsuario::setorIdsPermitidos($user, $request);

            $totalFuncionarios = (int) \App\Models\Funcionario::query()
                ->ativosNoEscopo($permitidos)
                ->count();

            // SQL Server é estrito em tipagem: não comparar INT com string literal.
            if (\Illuminate\Support\Facades\Schema::hasTable('ABONO_FALTA')) {
                try {
                    $statusPendente = (int) config('gente.abono.status_pendente', 0);
                    $abonosPendentes = (int) \Illuminate\Support\Facades\DB::table('ABONO_FALTA')
                        ->where('ABONO_FALTA_STATUS', $statusPendente)
                        ->count();
                } catch (\Throwable $abonoEx) {
                    $abonosPendentes = 0;
                    \Illuminate\Support\Facades\Log::warning('dashboard_abono_query_failed', [
                        'error' => $abonoEx->getMessage(),
                    ]);
                }
            }

            if (\Illuminate\Support\Facades\Schema::hasTable('FOLHA')) {
                try {
                    $folha = \Illuminate\Support\Facades\DB::table('FOLHA')->orderByDesc('FOLHA_ID')->first();
                    $folhaStatus = $folha->FOLHA_STATUS ?? $folhaStatus;
                    $folhaCompetencia = $folha->FOLHA_COMPETENCIA ?? $folhaCompetencia;
                } catch (\Throwable $folhaEx) {
                    \Illuminate\Support\Facades\Log::warning('dashboard_folha_query_failed', [
                        'error' => $folhaEx->getMessage(),
                    ]);
                }
            }
        } catch (\Throwable $e) {
            \Illuminate\Support\Facades\Log::warning('dashboard_kpi_failed', [
                'error' => $e->getMessage(),
            ]);
        }

        return response()->json([
            'total_funcionarios' => (int) $totalFuncionarios,
            'abonos_pendentes' => (int) $abonosPendentes,
            'folha_status' => $folhaStatus,
            'folha_competencia' => $folhaCompetencia,
        ]);
    });

    // Últimos acessos/mutações sensíveis para painel de TI.
    Route::get('/audit/sensitive-recent', function () {
        $headerWarning = null;
        try {
            $auditTable = null;
            foreach (['AUDIT_LOG', 'LOG_AUDITORIA'] as $candidate) {
                if (\Illuminate\Support\Facades\Schema::hasTable($candidate)) {
                    $auditTable = $candidate;
                    break;
                }
            }

            if (! $auditTable) {
                $headerWarning = 'AUDIT_TABLE_MISSING';
                return response()
                    ->json(['events' => []])
                    ->header('X-Audit-Warning', $headerWarning);
            }

            $rows = \Illuminate\Support\Facades\DB::table("{$auditTable} as a")
                ->leftJoin('USUARIO as u', 'u.USUARIO_ID', '=', 'a.USUARIO_ID')
                ->where(function ($q) {
                    $q->where('a.ACAO', '=', 'ACESSO_SENSIVEL')
                        ->orWhere('a.ACAO', 'like', '%/api/v3/funcionarios%')
                        ->orWhere('a.ACAO', 'like', '%/api/v3/folha%')
                        ->orWhere('a.ACAO', 'like', '%/api/v3/progressao%')
                        ->orWhere('a.ACAO', 'like', '%/api/v3/medicina%')
                        ->orWhere('a.ACAO', 'like', '%/api/v3/seguranca-trabalho%')
                        ->orWhere('a.ACAO', 'like', '%/api/v3/atestados%')
                        ->orWhere('a.ACAO', 'like', '%/api/v3/gestor%');
                })
                ->orderByDesc('a.created_at')
                ->limit(30)
                ->select(
                    'a.USUARIO_ID',
                    'u.USUARIO_LOGIN',
                    'u.USUARIO_NOME',
                    'a.ACAO',
                    'a.DADOS_NOVOS',
                    'a.TABELA',
                    'a.IP',
                    'a.created_at'
                )
                ->get();

            return response()->json([
                'events' => $rows->map(function ($r) {
                    $meta = [];
                    if (! empty($r->DADOS_NOVOS)) {
                        $parsed = json_decode((string) $r->DADOS_NOVOS, true);
                        if (is_array($parsed) && is_array($parsed['__audit'] ?? null)) {
                            $meta = $parsed['__audit'];
                        }
                    }

                    $eventType = (string) ($meta['event_type'] ?? ((string) $r->ACAO === 'ACESSO_SENSIVEL' ? 'LEITURA_SENSIVEL' : 'MUTACAO'));

                    return [
                        'USUARIO_ID' => $r->USUARIO_ID,
                        'USUARIO_LOGIN' => $r->USUARIO_LOGIN,
                        'USUARIO_NOME' => $r->USUARIO_NOME,
                        'ACAO' => $r->ACAO,
                        'event_type' => $eventType,
                        'priority' => (string) ($meta['priority'] ?? 'NORMAL'),
                        'context' => (string) ($meta['context'] ?? ((string) $r->ACAO)),
                        'target_id' => $meta['target_id'] ?? null,
                        'created_at' => $r->created_at,
                    ];
                })->values(),
            ]);
        } catch (\Throwable $e) {
            \Illuminate\Support\Facades\Log::warning('audit_sensitive_recent_unavailable', [
                'error' => $e->getMessage(),
            ]);
            $headerWarning = 'AUDIT_QUERY_FAILED';

            return response()
                ->json(['events' => []])
                ->header('X-Audit-Warning', $headerWarning);
        }
    });

    // â”€â”€ Holerites do usuÃ¡rio logado â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€
    Route::get('/meus-holerites', function () {
        $user = Auth::user();
        if (!$user) {
            return response()->json(['erro' => 'Não autenticado'], 401);
        }

        // Busca o funcionário vinculado ao usuário
        $funcionario = optional($user)->funcionario
            ?? \App\Models\Funcionario::where('USUARIO_ID', $user->USUARIO_ID)->first();

        if (!$funcionario) {
            return response()->json(['erro' => 'Nenhum funcionário vinculado a este usuário.'], 404);
        }

        // Busca detalhe das folhas do funcionÃ¡rio
        $detalhes = \App\Models\DetalheFolha::with('folha')
            ->where('FUNCIONARIO_ID', $funcionario->FUNCIONARIO_ID)
            ->orderByDesc('FOLHA_ID')
            ->take(24)
            ->get();

        $holerites = $detalhes->map(function ($d) {
            $prov = (float) ($d->DETALHE_FOLHA_PROVENTOS ?? 0);
            $desc = (float) ($d->DETALHE_FOLHA_DESCONTOS ?? 0);
            $liq = $d->DETALHE_FOLHA_LIQUIDO;
            $liq = $liq !== null && $liq !== '' ? (float) $liq : ($prov - $desc);

            return [
                'funcionario_id' => $d->FUNCIONARIO_ID,
                'detalhe_folha_id' => $d->DETALHE_FOLHA_ID,
                'folha_id' => $d->FOLHA_ID,
                'competencia' => optional($d->folha)->FOLHA_COMPETENCIA,
                'proventos' => $prov,
                'descontos' => $desc,
                'liquido' => $liq,
            ];
        })->values();

        return response()->json($holerites);
    });

    // â”€â”€ Admin: VÃ­nculos (configuraÃ§Ã£o do motor de folha) â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€
    Route::get('/admin/vinculos', function () {
        return response()->json(
            \App\Models\Vinculo::orderBy('VINCULO_NOME')
                ->get()
                ->map(fn($v) => [
                    'VINCULO_ID' => $v->VINCULO_ID,
                    'VINCULO_DESCRICAO' => $v->VINCULO_NOME,   // mapeia nome real â†’ alias front
                    'VINCULO_NOME' => $v->VINCULO_NOME,
                    'VINCULO_SIGLA' => $v->VINCULO_SIGLA,
                    'VINCULO_ATIVO' => $v->VINCULO_ATIVO,
                ])
        );
    });

    Route::put('/admin/vinculos/{id}', function (int $id, \Illuminate\Http\Request $request) {
        $vinculo = \App\Models\Vinculo::findOrFail($id);
        $data = [];
        if ($request->has('VINCULO_DESCRICAO'))
            $data['VINCULO_NOME'] = $request->VINCULO_DESCRICAO;
        if ($request->has('VINCULO_NOME'))
            $data['VINCULO_NOME'] = $request->VINCULO_NOME;
        if ($request->has('VINCULO_SIGLA'))
            $data['VINCULO_SIGLA'] = strtoupper(trim($request->VINCULO_SIGLA));
        if ($request->has('VINCULO_ATIVO'))
            $data['VINCULO_ATIVO'] = (int) $request->VINCULO_ATIVO;
        \Illuminate\Support\Facades\DB::table('VINCULO')
            ->where('VINCULO_ID', $id)
            ->update($data);
        return response()->json(\App\Models\Vinculo::find($id));
    });


    // â”€â”€ Escalas â€” listagem paginada de todas as escalas para o MatrizEscalaView â”€â”€â”€â”€â”€â”€â”€â”€â”€
    Route::get('/escalas', function (\Illuminate\Http\Request $request) {
        try {
            $perPage = max(10, min(200, (int) $request->input('per_page', 50)));
            $carregarTudo = filter_var((string) $request->input('carregar_tudo', '0'), FILTER_VALIDATE_BOOLEAN);
            $somenteSaude = filter_var((string) $request->input('somente_saude', '0'), FILTER_VALIDATE_BOOLEAN);
            $setorId = $request->filled('setor_id') ? (int) $request->input('setor_id') : null;
            $usuario = auth()->user();
            $permitidos = \App\Support\UnidadeEscopoUsuario::setorIdsPermitidos($usuario, $request);
            if ($permitidos === []) {
                return response()->json([
                    'escalas' => [],
                    'paginacao' => ['page' => 1, 'per_page' => $perPage, 'total' => 0, 'last_page' => 1],
                ]);
            }
            if (! $carregarTudo && ! $setorId) {
                return response()->json([
                    'escalas' => [],
                    'paginacao' => ['page' => 1, 'per_page' => $perPage, 'total' => 0, 'last_page' => 1],
                    'hint' => 'Selecione um setor para carregar a grade. Para visão macro use carregar_tudo=1 (sempre paginado).',
                ]);
            }
            if ($setorId && $permitidos !== null && ! in_array($setorId, $permitidos, true)) {
                return response()->json(['escalas' => [], 'erro' => 'Setor fora do escopo permitido.'], 403);
            }

            $q = \Illuminate\Support\Facades\DB::table('ESCALA as e')
                ->leftJoin('SETOR as s', 's.SETOR_ID', '=', 'e.SETOR_ID')
                ->leftJoin('DETALHE_ESCALA as de', 'de.ESCALA_ID', '=', 'e.ESCALA_ID')
                ->leftJoin('FUNCIONARIO as f', 'f.FUNCIONARIO_ID', '=', 'de.FUNCIONARIO_ID')
                ->leftJoin('CARGO as c', 'c.CARGO_ID', '=', 'f.CARGO_ID')
                ->when($permitidos !== null, fn($qq) => $qq->whereIn('e.SETOR_ID', $permitidos))
                ->when($setorId, fn($qq) => $qq->where('e.SETOR_ID', $setorId))
                ->when($somenteSaude, function ($qq) {
                    $qq->where(function ($w) {
                        if (\Illuminate\Support\Facades\Schema::hasColumn('CARGO', 'CARGO_AREA')) {
                            $w->orWhereRaw('UPPER(CAST(c.CARGO_AREA AS VARCHAR(255))) LIKE ?', ['%SAUDE%']);
                        }
                        if (\Illuminate\Support\Facades\Schema::hasColumn('CARGO', 'AREA')) {
                            $w->orWhereRaw('UPPER(CAST(c.AREA AS VARCHAR(255))) LIKE ?', ['%SAUDE%']);
                        }
                        if (\Illuminate\Support\Facades\Schema::hasColumn('CARGO', 'CARGO_TIPO')) {
                            $w->orWhereRaw('UPPER(CAST(c.CARGO_TIPO AS VARCHAR(255))) LIKE ?', ['%SAUDE%']);
                        }
                        if (\Illuminate\Support\Facades\Schema::hasColumn('CARGO', 'CBO')) {
                            $w->orWhereRaw('CAST(c.CBO AS VARCHAR(255)) LIKE ?', ['223%']);
                        }
                        if (\Illuminate\Support\Facades\Schema::hasColumn('CARGO', 'CARGO_CBO')) {
                            $w->orWhereRaw('CAST(c.CARGO_CBO AS VARCHAR(255)) LIKE ?', ['223%']);
                        }
                        $w->orWhereRaw('UPPER(COALESCE(c.CARGO_NOME, \'\')) LIKE ?', ['%MEDIC%']);
                        $w->orWhereRaw('UPPER(COALESCE(c.CARGO_NOME, \'\')) LIKE ?', ['%ENFERM%']);
                    });
                })
                ->select(
                    'e.ESCALA_ID',
                    'e.ESCALA_COMPETENCIA',
                    'e.ESCALA_DESCRICAO',
                    \Illuminate\Support\Facades\DB::raw('MAX(s.SETOR_NOME) as setor'),
                    \Illuminate\Support\Facades\DB::raw('COUNT(DISTINCT de.FUNCIONARIO_ID) as funcionarios')
                )
                ->groupBy('e.ESCALA_ID', 'e.ESCALA_COMPETENCIA', 'e.ESCALA_DESCRICAO')
                ->orderByDesc('e.ESCALA_ID');
            $page = $q->paginate($perPage);
            $escalas = collect($page->items())->map(fn($e) => [
                'ESCALA_ID' => $e->ESCALA_ID,
                'ESCALA_COMPETENCIA' => $e->ESCALA_COMPETENCIA,
                'ESCALA_DESCRICAO' => $e->ESCALA_DESCRICAO,
                'setor' => $e->setor ?? '—',
                'funcionarios' => (int) ($e->funcionarios ?? 0),
                'status' => 'CADASTRADA',
            ])->values();

            return response()->json([
                'escalas' => $escalas,
                'paginacao' => [
                    'page' => $page->currentPage(),
                    'per_page' => $page->perPage(),
                    'total' => $page->total(),
                    'last_page' => $page->lastPage(),
                ],
            ]);
        } catch (\Throwable $e) {
            return response()->json(['escalas' => [], 'erro' => $e->getMessage()], 500);
        }
    });

    // â”€â”€ Escalas â€” grade de uma escala especÃ­fica (funcionÃ¡rios + itens) â”€â”€â”€â”€â”€â”€â”€â”€â”€
    Route::get('/escalas/{id}', function (\Illuminate\Http\Request $request, $id) {
        try {
            $somenteSaude = filter_var((string) $request->input('somente_saude', '0'), FILTER_VALIDATE_BOOLEAN);
            $perPage = max(10, min(200, (int) $request->input('per_page', 50)));
            $usuario = auth()->user();
            $permitidos = \App\Support\UnidadeEscopoUsuario::setorIdsPermitidos($usuario, $request);
            if ($permitidos === []) {
                return response()->json(['erro' => 'Escopo vazio.'], 403);
            }

            $escala = \Illuminate\Support\Facades\DB::table('ESCALA')
                ->where('ESCALA_ID', $id)
                ->when($permitidos !== null, fn ($q) => $q->whereIn('SETOR_ID', $permitidos))
                ->first();
            if (!$escala) {
                return response()->json(['erro' => 'Escala não encontrada.'], 404);
            }

            $ano = (int) now()->year;
            $mes = (int) now()->month - 1;
            $comp = (string) ($escala->ESCALA_COMPETENCIA ?? '');
            if (preg_match('/^(\d{4})-(\d{2})$/', $comp, $m)) {
                $ano = (int) $m[1];
                $mes = (int) $m[2] - 1;
            } elseif (preg_match('/^(\d{2})\/(\d{4})$/', $comp, $m)) {
                $ano = (int) $m[2];
                $mes = (int) $m[1] - 1;
            }

            $detalhesQ = \Illuminate\Support\Facades\DB::table('DETALHE_ESCALA as de')
                ->leftJoin('FUNCIONARIO as f', 'f.FUNCIONARIO_ID', '=', 'de.FUNCIONARIO_ID')
                ->leftJoin('PESSOA as p', 'p.PESSOA_ID', '=', 'f.PESSOA_ID')
                ->leftJoin('CARGO as c', 'c.CARGO_ID', '=', 'f.CARGO_ID')
                ->leftJoin('LOTACAO as l', function ($j) {
                    $j->on('l.FUNCIONARIO_ID', '=', 'f.FUNCIONARIO_ID');
                    if (\Illuminate\Support\Facades\Schema::hasColumn('LOTACAO', 'LOTACAO_DATA_FIM')) {
                        $j->whereNull('l.LOTACAO_DATA_FIM');
                    }
                })
                ->leftJoin('SETOR as s', 's.SETOR_ID', '=', 'l.SETOR_ID')
                ->leftJoin('SETOR as sp', 'sp.SETOR_ID', '=', 's.SETOR_PAI_ID')
                ->leftJoin('UNIDADE as u', 'u.UNIDADE_ID', '=', 's.UNIDADE_ID')
                ->where('de.ESCALA_ID', $id)
                ->when($somenteSaude, function ($qq) {
                    $qq->where(function ($w) {
                        if (\Illuminate\Support\Facades\Schema::hasColumn('CARGO', 'CARGO_AREA')) {
                            $w->orWhereRaw('UPPER(CAST(c.CARGO_AREA AS VARCHAR(255))) LIKE ?', ['%SAUDE%']);
                        }
                        if (\Illuminate\Support\Facades\Schema::hasColumn('CARGO', 'AREA')) {
                            $w->orWhereRaw('UPPER(CAST(c.AREA AS VARCHAR(255))) LIKE ?', ['%SAUDE%']);
                        }
                        if (\Illuminate\Support\Facades\Schema::hasColumn('CARGO', 'CARGO_TIPO')) {
                            $w->orWhereRaw('UPPER(CAST(c.CARGO_TIPO AS VARCHAR(255))) LIKE ?', ['%SAUDE%']);
                        }
                        if (\Illuminate\Support\Facades\Schema::hasColumn('CARGO', 'CBO')) {
                            $w->orWhereRaw('CAST(c.CBO AS VARCHAR(255)) LIKE ?', ['223%']);
                        }
                        if (\Illuminate\Support\Facades\Schema::hasColumn('CARGO', 'CARGO_CBO')) {
                            $w->orWhereRaw('CAST(c.CARGO_CBO AS VARCHAR(255)) LIKE ?', ['223%']);
                        }
                        $w->orWhereRaw('UPPER(COALESCE(c.CARGO_NOME, \'\')) LIKE ?', ['%MEDIC%']);
                        $w->orWhereRaw('UPPER(COALESCE(c.CARGO_NOME, \'\')) LIKE ?', ['%ENFERM%']);
                    });
                })
                ->select(
                    'de.DETALHE_ESCALA_ID as detalhe_id',
                    'de.FUNCIONARIO_ID as funcionario_id',
                    'p.PESSOA_NOME as nome',
                    \Illuminate\Support\Facades\DB::raw('COALESCE(MAX(c.CARGO_NOME), \'\') as cargo'),
                    \Illuminate\Support\Facades\DB::raw('MAX(s.SETOR_NOME) as setor_nome'),
                    \Illuminate\Support\Facades\DB::raw('MAX(sp.SETOR_NOME) as setor_pai_nome'),
                    \Illuminate\Support\Facades\DB::raw('MAX(u.UNIDADE_NOME) as unidade_nome')
                )
                ->groupBy('de.DETALHE_ESCALA_ID', 'de.FUNCIONARIO_ID', 'p.PESSOA_NOME')
                ->orderBy('p.PESSOA_NOME');
            $detalhesPage = $detalhesQ->paginate($perPage);
            $detalhes = collect($detalhesPage->items());
            $detalheIds = $detalhes->pluck('detalhe_id')->map(fn ($v) => (int) $v)->all();

            $itensByDetalhe = [];
            if ($detalheIds !== []) {
                $itensRows = \Illuminate\Support\Facades\DB::table('DETALHE_ESCALA_ITEM as dei')
                    ->leftJoin('TURNO as t', 't.TURNO_ID', '=', 'dei.TURNO_ID')
                    ->whereIn('dei.DETALHE_ESCALA_ID', $detalheIds)
                    ->select(
                        'dei.DETALHE_ESCALA_ID as detalhe_id',
                        'dei.DETALHE_ESCALA_ITEM_ID as item_id',
                        'dei.DETALHE_ESCALA_ITEM_DATA as data',
                        'dei.TURNO_ID as turno_id',
                        't.TURNO_SIGLA as turno_sigla'
                    )
                    ->orderBy('dei.DETALHE_ESCALA_ITEM_DATA')
                    ->get();
                foreach ($itensRows as $it) {
                    $key = (int) $it->detalhe_id;
                    if (! isset($itensByDetalhe[$key])) {
                        $itensByDetalhe[$key] = [];
                    }
                    $itensByDetalhe[$key][] = $it;
                }
            }

            $funcionarios = $detalhes->map(function ($d) use ($itensByDetalhe) {
                $trilha = array_values(array_filter([
                    $d->unidade_nome ?? null,
                    $d->setor_pai_nome ?? null,
                    $d->setor_nome ?? null,
                ]));
                return [
                    'detalhe_id' => (int) $d->detalhe_id,
                    'funcionario_id' => (int) $d->funcionario_id,
                    'nome' => $d->nome ?? 'Funcionário',
                    'cargo' => $d->cargo ?? '',
                    'lotacao_trilha' => $trilha,
                    'lotacao_breadcrumb' => $trilha ? implode(' > ', $trilha) : 'Sem lotação',
                    'itens' => $itensByDetalhe[(int) $d->detalhe_id] ?? [],
                ];
            })->values();

            return response()->json([
                'escala' => [
                    'escala_id' => $escala->ESCALA_ID,
                    'competencia' => $escala->ESCALA_COMPETENCIA,
                    'ano' => $ano,
                    'mes' => $mes,
                ],
                'funcionarios' => $funcionarios,
                'paginacao' => [
                    'page' => $detalhesPage->currentPage(),
                    'per_page' => $detalhesPage->perPage(),
                    'total' => $detalhesPage->total(),
                    'last_page' => $detalhesPage->lastPage(),
                ],
                'feriados' => [],
            ]);
        } catch (\Throwable $e) {
            return response()->json(['erro' => $e->getMessage()], 500);
        }
    });

    // â”€â”€ Escalas â€” salvar grade (substitui /escala/salvar-matriz legado) â”€â”€â”€â”€â”€â”€â”€â”€â”€
    Route::post('/escalas/{id}/salvar', function ($id, \Illuminate\Http\Request $request) {
        try {
            $user = \Illuminate\Support\Facades\Auth::user();
            $detalheId = (int) ($request->detalhe_escala_id ?? 0);
            $itens = collect($request->itens ?? [])->map(fn($item) => [
                'turno_id' => (int) ($item['turno_id'] ?? 0),
                'data' => (string) ($item['data'] ?? ''),
            ])->filter(fn($i) => $i['turno_id'] > 0 && preg_match('/^\d{4}-\d{2}-\d{2}$/', $i['data']))->values();

            if ($detalheId <= 0) {
                return response()->json(['msg' => 'detalhe_escala_id inválido.'], 422);
            }

            $escala = \Illuminate\Support\Facades\DB::table('ESCALA')->where('ESCALA_ID', $id)->first();
            if (!$escala) {
                return response()->json(['msg' => 'Escala não encontrada.'], 404);
            }

            if (!\Illuminate\Support\Facades\Schema::hasTable('ESCALA_SNAPSHOT') || !\Illuminate\Support\Facades\Schema::hasTable('ESCALA_EVENTO')) {
                return response()->json(['msg' => 'Tabelas de snapshot/evento da escala não encontradas. Execute migrations canônicas.'], 422);
            }

            \Illuminate\Support\Facades\DB::transaction(function () use ($detalheId, $itens) {
                \Illuminate\Support\Facades\DB::table('DETALHE_ESCALA_ITEM')
                    ->where('DETALHE_ESCALA_ID', $detalheId)
                    ->delete();
                foreach ($itens as $item) {
                    \Illuminate\Support\Facades\DB::table('DETALHE_ESCALA_ITEM')->insert([
                        'DETALHE_ESCALA_ID' => $detalheId,
                        'TURNO_ID' => $item['turno_id'],
                        'DETALHE_ESCALA_ITEM_DATA' => $item['data'],
                    ]);
                }
            });

            $itensEscala = \Illuminate\Support\Facades\DB::table('DETALHE_ESCALA as de')
                ->join('DETALHE_ESCALA_ITEM as dei', 'dei.DETALHE_ESCALA_ID', '=', 'de.DETALHE_ESCALA_ID')
                ->join('TURNO as t', 't.TURNO_ID', '=', 'dei.TURNO_ID')
                ->where('de.ESCALA_ID', $id)
                ->select('de.FUNCIONARIO_ID as funcionario_id', 'dei.DETALHE_ESCALA_ITEM_DATA as data', 't.TURNO_SIGLA as turno')
                ->orderBy('de.FUNCIONARIO_ID')
                ->orderBy('dei.DETALHE_ESCALA_ITEM_DATA')
                ->orderBy('t.TURNO_SIGLA')
                ->get();

            $snapshotPayload = $itensEscala->map(fn($r) => [
                'funcionario_id' => (int) ($r->funcionario_id ?? 0),
                'data' => (string) ($r->data ?? ''),
                'turno' => (string) ($r->turno ?? ''),
            ])->values()->toArray();
            $snapshotHash = hash('sha256', json_encode($snapshotPayload));
            $versaoAtual = (int) (\Illuminate\Support\Facades\DB::table('ESCALA_SNAPSHOT')->where('ESCALA_ID', $id)->max('VERSAO') ?? 0);
            $novaVersao = $versaoAtual + 1;

            $snapshotId = \Illuminate\Support\Facades\DB::table('ESCALA_SNAPSHOT')->insertGetId([
                'ESCALA_ID' => $id,
                'VERSAO' => $novaVersao,
                'SNAPSHOT_HASH' => $snapshotHash,
                'PAYLOAD_JSON' => json_encode($snapshotPayload),
                'USUARIO_ID' => $user->USUARIO_ID ?? null,
                'created_at' => now(),
                'updated_at' => now(),
            ]);

            $itensPorDiaTurno = $itensEscala->groupBy(fn($r) => ($r->data ?? '') . '|' . ($r->turno ?? ''));
            $itensPorFuncDia = $itensEscala->groupBy(fn($r) => ($r->funcionario_id ?? 0) . '|' . ($r->data ?? ''));
            $lacunas = [];
            $conflitos = [];
            $noitesSeguidas = [];

            $diasEscala = $itensEscala->pluck('data')->filter()->unique()->values();
            foreach ($diasEscala as $dia) {
                try { $dow = \Carbon\Carbon::parse($dia)->dayOfWeek; } catch (\Throwable $e) { $dow = 1; }
                if (in_array($dow, [0, 6], true)) continue;
                foreach (['M', 'T', 'N'] as $siglaTurno) {
                    if (($itensPorDiaTurno[$dia . '|' . $siglaTurno] ?? collect())->count() === 0) {
                        $lacunas[] = ['data' => $dia, 'turno' => $siglaTurno];
                    }
                }
            }

            foreach ($itensPorFuncDia as $ch => $rows) {
                $turnosDistintos = $rows->pluck('turno')->filter()->unique()->values();
                if ($turnosDistintos->count() > 1) {
                    [$funcId, $data] = array_pad(explode('|', (string) $ch), 2, null);
                    $conflitos[] = ['funcionario_id' => (int) $funcId, 'data' => $data, 'turnos' => $turnosDistintos->all()];
                }
            }

            foreach ($itensEscala->groupBy('funcionario_id') as $funcId => $rowsFunc) {
                $noites = $rowsFunc->where('turno', 'N')->pluck('data')->filter()->sort()->values();
                $seq = 0; $prev = null;
                foreach ($noites as $dataN) {
                    if ($prev) {
                        try { $diff = \Carbon\Carbon::parse($prev)->diffInDays(\Carbon\Carbon::parse($dataN)); } catch (\Throwable $e) { $diff = 99; }
                        $seq = ($diff === 1) ? ($seq + 1) : 1;
                    } else { $seq = 1; }
                    $prev = $dataN;
                    if ($seq > 5) { $noitesSeguidas[] = ['funcionario_id' => (int) $funcId, 'ate_data' => $dataN, 'dias_seguidos' => $seq]; break; }
                }
            }

            $inconsistencias = count($lacunas) + count($conflitos) + count($noitesSeguidas);
            $statusCoerencia = $inconsistencias === 0 ? 'coerente' : 'inconsistente';
            $totalSlots = $itensEscala->count();
            $coberturaPct = $totalSlots > 0 ? round((($totalSlots - count($lacunas)) / $totalSlots) * 100, 1) : 100.0;
            $scoreRisco = min(100, (count($lacunas) * 8) + (count($conflitos) * 12) + (count($noitesSeguidas) * 10));
            $severidade = $scoreRisco >= 80 ? 'critico' : ($scoreRisco >= 60 ? 'alto' : ($scoreRisco >= 35 ? 'medio' : 'baixo'));
            $recomendacao = $severidade === 'critico'
                ? 'Acionar coordenação e redistribuir cobertura dos turnos críticos imediatamente.'
                : ($severidade === 'alto'
                    ? 'Revisar lacunas e conflitos nas próximas 24h com replanejamento.'
                    : ($severidade === 'medio'
                        ? 'Ajustar inconsistências e monitorar nova versão da escala.'
                        : 'Escala estável para execução. Manter monitoramento diário.'));

            $eventoPayload = [
                'snapshot_id' => $snapshotId,
                'versao' => $novaVersao,
                'hash' => $snapshotHash,
                'status' => $statusCoerencia,
                'inconsistencias' => ['lacunas' => count($lacunas), 'conflitos' => count($conflitos), 'noites_seguidas' => count($noitesSeguidas)],
                'impacto' => ['score_risco' => $scoreRisco, 'severidade' => $severidade, 'cobertura_pct' => $coberturaPct],
            ];
            \Illuminate\Support\Facades\DB::table('ESCALA_EVENTO')->insert([
                'ESCALA_ID' => $id,
                'EVENTO_TIPO' => 'escala_salva',
                'EVENTO_PAYLOAD' => json_encode($eventoPayload),
                'USUARIO_ID' => $user->USUARIO_ID ?? null,
                'EVENTO_EM' => now(),
                'created_at' => now(),
                'updated_at' => now(),
            ]);

            $notificacoesGeradas = 0;
            if ($severidade === 'critico' && \Illuminate\Support\Facades\Schema::hasTable('NOTIFICACAO')) {
                $tipo = 'escala_critica';
                $url = '/escala-matriz-v3?id=' . $id . '&versao=' . $novaVersao;
                $hashDia = hash('sha1', $snapshotHash . '|' . now()->toDateString());
                $jaExiste = \Illuminate\Support\Facades\DB::table('NOTIFICACAO')
                    ->where('NOTIFICACAO_TIPO', $tipo)
                    ->where('NOTIFICACAO_URL', 'like', '%' . $hashDia . '%')
                    ->exists();
                if (!$jaExiste) {
                    $destinatarios = \Illuminate\Support\Facades\DB::table('USUARIO')
                        ->whereIn('USUARIO_PERFIL', ['admin', 'gestor', 'rh'])
                        ->where('USUARIO_ATIVO', 1)
                        ->pluck('USUARIO_ID')
                        ->all();
                    foreach ($destinatarios as $uid) {
                        \Illuminate\Support\Facades\DB::table('NOTIFICACAO')->insert([
                            'USUARIO_ID' => (int) $uid,
                            'NOTIFICACAO_TITULO' => 'Escala com risco crítico',
                            'NOTIFICACAO_BODY' => 'Escala ' . ($escala->ESCALA_COMPETENCIA ?? '') . ' salva com cobertura crítica.',
                            'NOTIFICACAO_TIPO' => $tipo,
                            'NOTIFICACAO_ICONE' => '🚨',
                            'NOTIFICACAO_URL' => $url . '&hk=' . $hashDia,
                            'NOTIFICACAO_LIDA' => 0,
                            'NOTIFICACAO_DT_CRIACAO' => now(),
                        ]);
                        $notificacoesGeradas++;
                    }
                }
            }

            return response()->json([
                'message' => 'Grade salva com sucesso.',
                'governanca' => ['snapshot_id' => $snapshotId, 'versao' => $novaVersao, 'hash' => $snapshotHash],
                'validacao' => [
                    'status' => $statusCoerencia,
                    'lacunas' => $lacunas,
                    'conflitos' => $conflitos,
                    'noites_seguidas' => $noitesSeguidas,
                    'total_inconsistencias' => $inconsistencias,
                ],
                'impacto' => [
                    'total_profissionais' => $itensEscala->pluck('funcionario_id')->filter()->unique()->count(),
                    'total_slots' => $totalSlots,
                    'cobertura_pct' => $coberturaPct,
                    'score_risco' => $scoreRisco,
                    'severidade' => $severidade,
                    'recomendacao' => $recomendacao,
                ],
                'propagacao' => [
                    'eventos_emitidos' => 1,
                    'notificacoes_criticas_geradas' => $notificacoesGeradas,
                    'modulos_alvo' => ['ponto', 'banco_horas', 'heatmap', 'notificacoes'],
                ],
            ]);
        } catch (\Throwable $e) {
            return response()->json(['msg' => $e->getMessage()], 500);
        }
    })->middleware('perfil:ADMINISTRADOR,Administrador,GESTOR');

    Route::get('/escalas/{id}/diagnostico', function ($id) {
        try {
            if (!\Illuminate\Support\Facades\Schema::hasTable('ESCALA_EVENTO')) {
                return response()->json(['diagnostico' => null]);
            }
            $ev = \Illuminate\Support\Facades\DB::table('ESCALA_EVENTO')
                ->where('ESCALA_ID', $id)
                ->where('EVENTO_TIPO', 'escala_salva')
                ->orderByDesc('EVENTO_ID')
                ->first();
            if (!$ev) {
                return response()->json(['diagnostico' => null]);
            }
            $payload = json_decode((string) ($ev->EVENTO_PAYLOAD ?? '{}'), true) ?: [];
            return response()->json([
                'diagnostico' => $payload,
                'evento_em' => $ev->EVENTO_EM ?? $ev->created_at ?? null,
            ]);
        } catch (\Throwable $e) {
            return response()->json(['erro' => $e->getMessage()], 500);
        }
    });


    // Atestados médicos: definição canónica em routes/atestados_v3.php (api_v3_auth_part1) — evitar duplicar GET/POST/DELETE aqui.

    // Rotas de sobreaviso ficam exclusivamente no módulo routes/plantoes_sobreaviso.php
    // para evitar conflitos de definição duplicada.

    // â”€â”€ RelatÃ³rios â€” stats do hero â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€
    Route::get('/relatorios/stats', function () {
        try {
            $funcionarios = \Illuminate\Support\Facades\DB::table('FUNCIONARIO')
                ->where(function ($q) {
                    $q->whereNull('FUNCIONARIO_DATA_DEMISSAO')
                        ->orWhere('FUNCIONARIO_DATA_DEMISSAO', '>', now()->toDateString());
                })->count();

            $competencia = \Illuminate\Support\Facades\DB::table('APURACAO_FOLHA')
                ->orderByDesc('COMPETENCIA')->value('COMPETENCIA')
                ?? \Illuminate\Support\Facades\DB::table('FOLHA_PAGAMENTO')
                    ->orderByDesc('FOLHA_COMPETENCIA')->value('FOLHA_COMPETENCIA')
                ?? now()->format('Y-m');

            return response()->json([
                'fallback' => false,
                'funcionarios' => $funcionarios,
                'competencia' => $competencia,
            ]);
        } catch (\Throwable $e) {
            return response()->json(['fallback' => true, 'erro' => $e->getMessage()]);
        }
    });

    // â”€â”€ Agenda â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€
    // â”€â”€ Medicina do Trabalho â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€
    Route::get('/medicina', function () {
        try {
            $user = \Illuminate\Support\Facades\Auth::user();
            $funcionario = \App\Models\Funcionario::where('USUARIO_ID', $user->USUARIO_ID ?? 0)->first();
            if (!$funcionario)
                return response()->json(['fallback' => true, 'exames' => [], 'historico' => []]);

            $exames = collect();
            if (\Illuminate\Support\Facades\Schema::hasTable('EXAME_OCUPACIONAL')) {
                $exames = \Illuminate\Support\Facades\DB::table('EXAME_OCUPACIONAL')
                    ->where('FUNCIONARIO_ID', $funcionario->FUNCIONARIO_ID)
                    ->orderByDesc('EXAME_DATA_REALIZACAO')
                    ->get()->map(fn($e) => [
                        'EXAME_ID' => $e->EXAME_ID,
                        'EXAME_TIPO' => $e->EXAME_TIPO,
                        'EXAME_SUBTIPO' => $e->EXAME_SUBTIPO ?? null,
                        'EXAME_DATA_REALIZACAO' => $e->EXAME_DATA_REALIZACAO,
                        'EXAME_DATA_VENCIMENTO' => $e->EXAME_DATA_VENCIMENTO ?? null,
                        'EXAME_MEDICO' => $e->EXAME_MEDICO ?? null,
                        'apto' => (bool) ($e->EXAME_APTO ?? true),
                    ]);
            }

            $historico = collect();
            if (\Illuminate\Support\Facades\Schema::hasTable('HISTORICO_EXAME')) {
                $historico = \Illuminate\Support\Facades\DB::table('HISTORICO_EXAME')
                    ->where('FUNCIONARIO_ID', $funcionario->FUNCIONARIO_ID)
                    ->orderByDesc('HISTORICO_DATA')
                    ->take(10)->get()->map(fn($h) => [
                        'tipo' => $h->HISTORICO_TIPO,
                        'data' => $h->HISTORICO_DATA,
                        'apto' => (bool) ($h->HISTORICO_APTO ?? true),
                    ]);
            }

            $fallback = $exames->isEmpty();
            return response()->json(['exames' => $exames, 'historico' => $historico, 'fallback' => $fallback]);
        } catch (\Throwable $e) {
            return response()->json(['fallback' => true, 'exames' => [], 'historico' => [], 'erro' => $e->getMessage()]);
        }
    });

    Route::post('/medicina/agendar', function (\Illuminate\Http\Request $request) {
        try {
            $user = \Illuminate\Support\Facades\Auth::user();
            $funcionario = \App\Models\Funcionario::where('USUARIO_ID', $user->USUARIO_ID ?? 0)->first();
            $id = \Illuminate\Support\Facades\DB::table('AGENDAMENTO_EXAME')->insertGetId([
                'FUNCIONARIO_ID' => $funcionario->FUNCIONARIO_ID ?? null,
                'AGENDAMENTO_TIPO' => $request->tipo_exame ?? $request->tipo ?? 'Admissional',
                'AGENDAMENTO_DATA' => $request->data,
                'AGENDAMENTO_OBS' => $request->obs,
                'AGENDAMENTO_STATUS' => 'pendente',
                'AGENDAMENTO_DT_SOLICITACAO' => now()->toDateString(),
            ]);
            return response()->json(['id' => $id, 'message' => 'Agendamento registrado.'], 201);
        } catch (\Throwable $e) {
            return response()->json(['erro' => $e->getMessage()], 500);
        }
    });

    Route::post('/beneficios/solicitar', function (\Illuminate\Http\Request $request) {
        try {
            $user = \Illuminate\Support\Facades\Auth::user();
            $funcionario = \App\Models\Funcionario::where('USUARIO_ID', $user->USUARIO_ID ?? 0)->first();
            \Illuminate\Support\Facades\DB::table('BENEFICIO_SOLICITACAO')->insert([
                'FUNCIONARIO_ID' => $funcionario->FUNCIONARIO_ID ?? null,
                'BENEFICIO_ID' => $request->beneficio_id,
                'SOLICITACAO_DATA' => now()->toDateString(),
                'SOLICITACAO_STATUS' => 'pendente',
                'SOLICITACAO_OBS' => $request->nome,
            ]);
            return response()->json(['message' => 'Solicitação registrada.'], 201);
        } catch (\Throwable $e) {
            return response()->json(['erro' => $e->getMessage()], 500);
        }
    });

    Route::get('/agenda', function (\Illuminate\Http\Request $request) {
        try {
            $user = \Illuminate\Support\Facades\Auth::user();
            $funcionario = \App\Models\Funcionario::where('USUARIO_ID', $user->USUARIO_ID ?? 0)->first();
            if (!$funcionario)
                return response()->json(['fallback' => true, 'eventos' => []]);

            $comp = $request->competencia ?? now()->format('Y-m');
            [$ano, $mes] = explode('-', $comp);
            $inicio = sprintf('%04d-%02d-01', $ano, $mes);
            $fim = \Carbon\Carbon::createFromDate((int) $ano, (int) $mes, 1)->endOfMonth()->toDateString();

            // Setor atual do funcionÃ¡rio (lotaÃ§Ã£o sem data_fim)
            $lotacao = \Illuminate\Support\Facades\DB::table('LOTACAO')
                ->where('FUNCIONARIO_ID', $funcionario->FUNCIONARIO_ID)
                ->whereNull('LOTACAO_DATA_FIM')
                ->orderByDesc('LOTACAO_DATA_INICIO')
                ->first();
            $setorId = $lotacao->SETOR_ID ?? null;

            $rows = \Illuminate\Support\Facades\DB::table('AGENDA')
                ->whereBetween('AGENDA_DATA', [$inicio, $fim])
                ->where(function ($q) use ($funcionario, $setorId) {
                    // 1. Eventos globais (todos veem)
                    $q->where('AGENDA_ESCOPO', 'global')
                        // 2. Eventos do setor do funcionÃ¡rio
                        ->orWhere(function ($q2) use ($setorId) {
                        $q2->where('AGENDA_ESCOPO', 'setor')
                            ->where('AGENDA_SETOR_ID', $setorId);
                    })
                        // 3. Eventos pessoais prÃ³prios
                        ->orWhere(function ($q2) use ($funcionario) {
                        $q2->where('AGENDA_ESCOPO', 'pessoal')
                            ->where('FUNCIONARIO_ID', $funcionario->FUNCIONARIO_ID);
                    });
                })
                ->orderBy('AGENDA_DATA')->orderBy('AGENDA_HORA')
                ->get()->map(fn($e) => [
                    'AGENDA_ID' => $e->AGENDA_ID,
                    'AGENDA_TITULO' => $e->AGENDA_TITULO,
                    'AGENDA_TIPO' => $e->AGENDA_TIPO,
                    'AGENDA_DIA' => (int) \Carbon\Carbon::parse($e->AGENDA_DATA)->format('j'),
                    'AGENDA_HORA' => $e->AGENDA_HORA,
                    'AGENDA_LOCAL' => $e->AGENDA_LOCAL ?? null,
                    'AGENDA_DESC' => $e->AGENDA_DESC ?? null,
                    'AGENDA_ESCOPO' => $e->AGENDA_ESCOPO ?? 'pessoal',
                ]);

            return response()->json(['eventos' => $rows, 'fallback' => $rows->isEmpty()]);
        } catch (\Throwable $e) {
            return response()->json(['fallback' => true, 'eventos' => [], 'erro' => $e->getMessage()]);
        }
    });

    Route::post('/agenda', function (\Illuminate\Http\Request $request) {
        try {
            $user = \Illuminate\Support\Facades\Auth::user();
            $funcionario = \App\Models\Funcionario::where('USUARIO_ID', $user->USUARIO_ID ?? 0)->first();

            // Setor atual
            $lotacao = \Illuminate\Support\Facades\DB::table('LOTACAO')
                ->where('FUNCIONARIO_ID', $funcionario->FUNCIONARIO_ID ?? 0)
                ->whereNull('LOTACAO_DATA_FIM')
                ->orderByDesc('LOTACAO_DATA_INICIO')
                ->first();
            $setorId = $lotacao->SETOR_ID ?? null;

            // Verifica se Ã© gestor (CARGO_GESTAO = 1) ou admin (PERFIL_ID â‰¤ 2)
            $isGestor = \Illuminate\Support\Facades\DB::table('LOTACAO as l')
                ->join('ATRIBUICAO_LOTACAO as al', 'al.LOTACAO_ID', '=', 'l.LOTACAO_ID')
                ->join('ATRIBUICAO as a', 'a.ATRIBUICAO_ID', '=', 'al.ATRIBUICAO_ID')
                ->join('CARGO as c', 'c.CARGO_ID', '=', 'a.CARGO_ID')
                ->where('l.FUNCIONARIO_ID', $funcionario->FUNCIONARIO_ID ?? 0)
                ->whereNull('l.LOTACAO_DATA_FIM')
                ->where('c.CARGO_GESTAO', 1)
                ->exists();

            $isAdmin = \Illuminate\Support\Facades\DB::table('USUARIO_PERFIL')
                ->where('USUARIO_ID', $user->USUARIO_ID ?? 0)
                ->where('USUARIO_PERFIL_ATIVO', 1)
                ->where('PERFIL_ID', '<=', 2)
                ->exists();

            // Valida escopo solicitado
            $escopoSolicitado = $request->escopo ?? 'pessoal';
            if ($escopoSolicitado === 'global' && !$isAdmin)
                $escopoSolicitado = 'pessoal';
            if ($escopoSolicitado === 'setor' && !$isGestor && !$isAdmin)
                $escopoSolicitado = 'pessoal';

            $agendaSetorId = ($escopoSolicitado === 'setor') ? $setorId : null;

            $id = \Illuminate\Support\Facades\DB::table('AGENDA')->insertGetId([
                'FUNCIONARIO_ID' => $funcionario->FUNCIONARIO_ID ?? null,
                'AGENDA_TITULO' => $request->titulo,
                'AGENDA_TIPO' => $request->tipo,
                'AGENDA_DATA' => $request->data,
                'AGENDA_HORA' => $request->hora,
                'AGENDA_LOCAL' => $request->local,
                'AGENDA_DESC' => $request->desc,
                'AGENDA_ESCOPO' => $escopoSolicitado,
                'AGENDA_SETOR_ID' => $agendaSetorId,
            ]);
            return response()->json([
                'id' => $id,
                'escopo' => $escopoSolicitado,
                'gestor' => $isGestor,
                'admin' => $isAdmin,
            ], 201);
        } catch (\Throwable $e) {
            return response()->json(['erro' => $e->getMessage()], 500);
        }
    });


    // â”€â”€ Banco de Horas â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€
    Route::get('/banco-horas', function () {
        try {
            $user = \Illuminate\Support\Facades\Auth::user();
            $funcionario = \App\Models\Funcionario::where('USUARIO_ID', $user->USUARIO_ID ?? 0)->first();
            if (!$funcionario)
                return response()->json(['fallback' => true, 'apuracoes' => []]);

            $apuracoes = \Illuminate\Support\Facades\DB::table('APURACAO_PONTO')
                ->where('FUNCIONARIO_ID', $funcionario->FUNCIONARIO_ID)
                ->orderByDesc('APURACAO_COMPETENCIA')
                ->take(12)->get()
                ->map(fn($a) => [
                    'competencia' => $a->APURACAO_COMPETENCIA,
                    'saldo_acumulado' => $a->APURACAO_SALDO ?? $a->APURACAO_HORAS_EXTRA ?? 0,
                    'horas_trab' => $a->APURACAO_HORAS_TRAB ?? 0,
                    'horas_falta' => $a->APURACAO_HORAS_FALTA ?? 0,
                    'status' => $a->APURACAO_STATUS ?? 'aberta',
                ]);

            return response()->json(['apuracoes' => $apuracoes, 'fallback' => $apuracoes->isEmpty()]);
        } catch (\Throwable $e) {
            return response()->json(['fallback' => true, 'apuracoes' => [], 'erro' => $e->getMessage()]);
        }
    });

    // â”€â”€ Contratos / VÃ­nculos â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€
    Route::get('/contratos', function () {
        try {
            $user = \Illuminate\Support\Facades\Auth::user();
            $funcionario = \App\Models\Funcionario::where('USUARIO_ID', $user->USUARIO_ID ?? 0)->first();
            if (!$funcionario)
                return response()->json(['fallback' => true]);

            $pessoaCols = \Illuminate\Support\Facades\Schema::hasTable('PESSOA') ? \Illuminate\Support\Facades\Schema::getColumnListing('PESSOA') : [];
            $pisCol = in_array('PESSOA_PIS_PASEP', $pessoaCols, true)
                ? 'PESSOA_PIS_PASEP'
                : (in_array('PESSOA_PIS', $pessoaCols, true) ? 'PESSOA_PIS' : null);
            $admissaoCol = \Illuminate\Support\Facades\Schema::hasColumn('FUNCIONARIO', 'FUNCIONARIO_DATA_ADMISSAO')
                ? 'FUNCIONARIO_DATA_ADMISSAO'
                : 'FUNCIONARIO_DATA_INICIO';

            $f = \Illuminate\Support\Facades\DB::table('FUNCIONARIO as f')
                ->leftJoin('PESSOA as p', 'p.PESSOA_ID', '=', 'f.PESSOA_ID')
                ->leftJoin('CARGO as c', 'c.CARGO_ID', '=', 'f.CARGO_ID')
                ->select('f.*', 'p.PESSOA_NOME', 'p.PESSOA_CPF_NUMERO', 'c.CARGO_NOME')
                ->addSelect($pisCol ? \Illuminate\Support\Facades\DB::raw("p.$pisCol as PESSOA_PIS_MERGED") : \Illuminate\Support\Facades\DB::raw('NULL as PESSOA_PIS_MERGED'))
                ->where('f.FUNCIONARIO_ID', $funcionario->FUNCIONARIO_ID)
                ->first();

            if (!$f)
                return response()->json(['fallback' => true]);

            $lotacao = \Illuminate\Support\Facades\DB::table('LOTACAO as l')
                ->leftJoin('SETOR as s', 's.SETOR_ID', '=', 'l.SETOR_ID')
                ->leftJoin('UNIDADE as u', 'u.UNIDADE_ID', '=', 's.UNIDADE_ID')
                ->leftJoin('VINCULO as v', 'v.VINCULO_ID', '=', 'l.VINCULO_ID')
                ->where('l.FUNCIONARIO_ID', $funcionario->FUNCIONARIO_ID)
                ->whereNull('l.LOTACAO_DATA_FIM')
                ->select('s.SETOR_NOME', 'u.UNIDADE_NOME', 'v.VINCULO_NOME')
                ->first();

            $historico = collect();
            if (\Illuminate\Support\Facades\Schema::hasTable('HISTORICO_FUNCIONAL')) {
                $historico = \Illuminate\Support\Facades\DB::table('HISTORICO_FUNCIONAL')
                    ->where('FUNCIONARIO_ID', $funcionario->FUNCIONARIO_ID)
                    ->orderByDesc('HISTORICO_DATA_INICIO')->get()
                    ->map(fn($h) => [
                        'id' => $h->HISTORICO_ID,
                        'tipo' => $h->HISTORICO_TIPO ?? 'Servidor',
                        'regime' => $h->HISTORICO_REGIME ?? 'Estatutário',
                        'cargo' => $h->HISTORICO_CARGO ?? ($f->CARGO_NOME ?? '—'),
                        'setor' => $h->HISTORICO_SETOR ?? ($lotacao->SETOR_NOME ?? '—'),
                        'unidade' => $h->HISTORICO_UNIDADE ?? ($lotacao->UNIDADE_NOME ?? null),
                        'inicio' => $h->HISTORICO_DATA_INICIO,
                        'fim' => $h->HISTORICO_DATA_FIM,
                        'ativo' => is_null($h->HISTORICO_DATA_FIM),
                    ]);
            }

            if ($historico->isEmpty()) {
                $historicoLot = \Illuminate\Support\Facades\DB::table('LOTACAO as l')
                    ->leftJoin('SETOR as s', 's.SETOR_ID', '=', 'l.SETOR_ID')
                    ->leftJoin('UNIDADE as u', 'u.UNIDADE_ID', '=', 's.UNIDADE_ID')
                    ->leftJoin('VINCULO as v', 'v.VINCULO_ID', '=', 'l.VINCULO_ID')
                    ->where('l.FUNCIONARIO_ID', $funcionario->FUNCIONARIO_ID)
                    ->orderByDesc('l.LOTACAO_DATA_INICIO')
                    ->select('l.*', 's.SETOR_NOME', 'u.UNIDADE_NOME', 'v.VINCULO_NOME')
                    ->get();
                $historico = $historicoLot->map(fn($l, $idx) => [
                    'id' => $l->LOTACAO_ID ?? $idx,
                    'tipo' => $l->VINCULO_NOME ?? 'Servidor',
                    'regime' => $l->VINCULO_NOME ?? 'Estatutário',
                    'cargo' => $f->CARGO_NOME ?? '—',
                    'setor' => $l->SETOR_NOME ?? '—',
                    'unidade' => $l->UNIDADE_NOME ?? null,
                    'inicio' => $l->LOTACAO_DATA_INICIO ?? null,
                    'fim' => $l->LOTACAO_DATA_FIM ?? null,
                    'ativo' => empty($l->LOTACAO_DATA_FIM),
                ]);
            }

            return response()->json([
                'fallback' => false,
                'contrato' => [
                    'cargo' => $f->CARGO_NOME ?? '—',
                    'setor' => $lotacao->SETOR_NOME ?? '—',
                    'unidade' => $lotacao->UNIDADE_NOME ?? '—',
                    'admissao' => $f->{$admissaoCol} ?? null,
                    'matricula' => $f->FUNCIONARIO_MATRICULA ?? (string) $f->FUNCIONARIO_ID,
                    'vinculo' => $lotacao->VINCULO_NOME ?? 'Servidor Efetivo',
                    'regime_prev' => (str_contains(strtolower((string) ($lotacao->VINCULO_NOME ?? '')), 'pss') || str_contains(strtolower((string) ($lotacao->VINCULO_NOME ?? '')), 'clt')) ? 'RGPS' : 'RPPS',
                    'cpf' => $f->PESSOA_CPF_NUMERO ?? null,
                    'pis' => $f->PESSOA_PIS_MERGED ?? null,
                ],
                'historico' => $historico->values(),
            ]);
        } catch (\Throwable $e) {
            return response()->json(['fallback' => true, 'erro' => $e->getMessage()]);
        }
    });

    // â”€â”€ DeclaraÃ§Ãµes / Requerimentos â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€
    Route::get('/declaracoes', function () {
        try {
            $user = \Illuminate\Support\Facades\Auth::user();
            $funcionario = \App\Models\Funcionario::where('USUARIO_ID', $user->USUARIO_ID ?? 0)->first();
            if (!$funcionario)
                return response()->json(['fallback' => true, 'pedidos' => []]);

            $pedidos = \Illuminate\Support\Facades\DB::table('PEDIDO_DOCUMENTO')
                ->where('FUNCIONARIO_ID', $funcionario->FUNCIONARIO_ID)
                ->orderByDesc('PEDIDO_DATA')->take(20)->get();

            return response()->json(['pedidos' => $pedidos, 'fallback' => $pedidos->isEmpty()]);
        } catch (\Throwable $e) {
            return response()->json(['fallback' => true, 'pedidos' => [], 'erro' => $e->getMessage()]);
        }
    });

    // â”€â”€ Ouvidoria Admin (RH/Admin) â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€
    Route::get('/ouvidoria/admin', function () {
        try {
            $agora = now();
            $rows = \Illuminate\Support\Facades\DB::table('OUVIDORIA as o')
                ->leftJoin('FUNCIONARIO as f', 'f.FUNCIONARIO_ID', '=', 'o.FUNCIONARIO_ID')
                ->leftJoin('PESSOA as p', 'p.PESSOA_ID', '=', 'f.PESSOA_ID')
                ->leftJoin('LOTACAO as l', function ($j) {
                    $j->on('l.FUNCIONARIO_ID', '=', 'f.FUNCIONARIO_ID')
                        ->whereNull('l.LOTACAO_DATA_FIM');
                })
                ->leftJoin('SETOR as s', 's.SETOR_ID', '=', 'l.SETOR_ID')
                ->orderByDesc('o.OUVIDORIA_DATA')
                ->select(
                    'o.OUVIDORIA_ID as id',
                    'o.OUVIDORIA_TIPO as tipo',
                    'o.OUVIDORIA_AREA as area',
                    'o.OUVIDORIA_URGENCIA as urgencia',
                    'o.OUVIDORIA_DESC as descricao',
                    'o.OUVIDORIA_STATUS as status',
                    'o.OUVIDORIA_PROTOCOLO as protocolo',
                    'o.OUVIDORIA_DATA as data',
                    'o.OUVIDORIA_ANONIMO as anonimo',
                    'o.OUVIDORIA_RESPOSTA as resposta',
                    \Illuminate\Support\Facades\DB::raw("CASE WHEN o.OUVIDORIA_ANONIMO = 1 THEN NULL ELSE p.PESSOA_NOME END as autor"),
                    's.SETOR_NOME as setor'
                )
                ->get()
                ->map(fn($r) => [
                    'prazo_horas' => (($r->urgencia ?? 'normal') === 'alta' ? 24 : (($r->urgencia ?? 'normal') === 'media' ? 48 : 72)),
                    'id' => $r->id,
                    'tipo' => $r->tipo ?? 'outros',
                    'area' => $r->area,
                    'urgencia' => $r->urgencia ?? 'normal',
                    'descricao' => $r->descricao,
                    'status' => $r->status ?? 'recebida',
                    'protocolo' => $r->protocolo,
                    'data' => $r->data,
                    'anonimo' => (bool) $r->anonimo,
                    'autor' => $r->autor,
                    'setor' => $r->setor,
                    'resposta' => $r->resposta,
                ])
                ->map(function ($m) use ($agora) {
                    $prazo = (int) ($m['prazo_horas'] ?? 72);
                    $aberta = ($m['status'] ?? 'recebida') !== 'respondida' && ($m['status'] ?? 'recebida') !== 'arquivada';
                    $baseData = null;
                    try {
                        $baseData = \Carbon\Carbon::parse($m['data']);
                    } catch (\Throwable $e) {
                        $baseData = now();
                    }
                    $limite = $baseData->copy()->addHours($prazo);
                    $restante = $agora->diffInHours($limite, false);
                    $slaStatus = 'ok';
                    if ($aberta) {
                        if ($restante < 0) {
                            $slaStatus = 'vencido';
                        } elseif ($restante <= 8) {
                            $slaStatus = 'vencendo';
                        }
                    }
                    $m['sla_status'] = $slaStatus;
                    $m['sla_horas_restantes'] = (int) $restante;
                    return $m;
                });

            // Alerta automatico para urgencias altas vencidas sem resposta (idempotente por dia)
            foreach ($rows as $m) {
                if (($m['urgencia'] ?? 'normal') !== 'alta') {
                    continue;
                }
                if (($m['sla_status'] ?? 'ok') !== 'vencido') {
                    continue;
                }
                if (($m['status'] ?? 'recebida') === 'respondida' || ($m['status'] ?? 'recebida') === 'arquivada') {
                    continue;
                }

                $url = '/ouvidoria-admin?manifestacao=' . urlencode((string) ($m['id'] ?? ''));
                $titulo = 'SLA critico de Ouvidoria';
                $body = 'Manifestacao ' . ($m['protocolo'] ?? '#') . ' venceu SLA e segue sem resposta.';
                $tipo = 'ouvidoria_sla';
                $icone = '🚨';
                $hoje = $agora->toDateString();

                $jaExisteHoje = \Illuminate\Support\Facades\DB::table('NOTIFICACAO')
                    ->where('NOTIFICACAO_TIPO', $tipo)
                    ->where('NOTIFICACAO_URL', $url)
                    ->whereDate('NOTIFICACAO_DT_CRIACAO', $hoje)
                    ->exists();

                if (!$jaExisteHoje) {
                    $destinatarios = \Illuminate\Support\Facades\DB::table('USUARIO')
                        ->whereIn('USUARIO_PERFIL', ['admin', 'rh', 'gestor'])
                        ->where('USUARIO_ATIVO', 1)
                        ->pluck('USUARIO_ID')
                        ->all();
                    foreach ($destinatarios as $uid) {
                        \Illuminate\Support\Facades\DB::table('NOTIFICACAO')->insert([
                            'USUARIO_ID' => $uid,
                            'NOTIFICACAO_TITULO' => $titulo,
                            'NOTIFICACAO_BODY' => $body,
                            'NOTIFICACAO_TIPO' => $tipo,
                            'NOTIFICACAO_ICONE' => $icone,
                            'NOTIFICACAO_URL' => $url,
                            'NOTIFICACAO_LIDA' => 0,
                            'NOTIFICACAO_DT_CRIACAO' => $agora,
                        ]);
                    }
                }
            }
            return response()->json(['manifestacoes' => $rows]);
        } catch (\Throwable $e) {
            return response()->json(['manifestacoes' => [], 'erro' => $e->getMessage()]);
        }
    });

    Route::put('/ouvidoria/{id}', function (\Illuminate\Http\Request $request, $id) {
        try {
            $updated = \Illuminate\Support\Facades\DB::table('OUVIDORIA')
                ->where('OUVIDORIA_ID', $id)
                ->update([
                    'OUVIDORIA_STATUS' => $request->status ?? 'recebida',
                    'OUVIDORIA_RESPOSTA' => $request->resposta,
                ]);
            if (!$updated) {
                return response()->json(['erro' => 'Manifestação não encontrada.'], 404);
            }
            return response()->json(['ok' => true]);
        } catch (\Throwable $e) {
            return response()->json(['erro' => $e->getMessage()], 500);
        }
    });

    Route::post('/declaracoes', function (\Illuminate\Http\Request $request) {
        try {
            $user = \Illuminate\Support\Facades\Auth::user();
            $funcionario = \App\Models\Funcionario::where('USUARIO_ID', $user->USUARIO_ID ?? 0)->first();
            $ano = now()->format('Y');
            $seq = \Illuminate\Support\Facades\DB::table('PEDIDO_DOCUMENTO')->count() + 1;

            $proto = "REQ-{$ano}-" . str_pad($seq, 3, '0', STR_PAD_LEFT);
            $id = \Illuminate\Support\Facades\DB::table('PEDIDO_DOCUMENTO')->insertGetId([
                'FUNCIONARIO_ID' => $funcionario->FUNCIONARIO_ID ?? null,
                'PEDIDO_NOME' => $request->nome,
                'PEDIDO_DATA' => now()->toDateString(),
                'PEDIDO_STATUS' => $request->instantaneo ? 'pronto' : 'andamento',
                'PEDIDO_PROTOCOLO' => $proto,
            ]);
            return response()->json(['id' => $id, 'protocolo' => $proto], 201);
        } catch (\Throwable $e) {
            return response()->json(['erro' => $e->getMessage()], 500);
        }
    })->middleware('upload.safe');

    // â”€â”€ Ouvidoria â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€
    Route::get('/ouvidoria', function () {
        try {
            $user = \Illuminate\Support\Facades\Auth::user();
            $funcionario = \App\Models\Funcionario::where('USUARIO_ID', $user->USUARIO_ID ?? 0)->first();
            if (!$funcionario)
                return response()->json(['fallback' => true, 'manifestacoes' => []]);

            $rows = \Illuminate\Support\Facades\DB::table('OUVIDORIA')
                ->where(function ($q) use ($funcionario) {
                    $q->where('FUNCIONARIO_ID', $funcionario->FUNCIONARIO_ID)
                        ->orWhere('OUVIDORIA_ANONIMO', 0);
                })
                ->where('FUNCIONARIO_ID', $funcionario->FUNCIONARIO_ID)
                ->orderByDesc('OUVIDORIA_DATA')->take(20)->get();

            return response()->json(['manifestacoes' => $rows, 'fallback' => $rows->isEmpty()]);
        } catch (\Throwable $e) {
            return response()->json(['fallback' => true, 'manifestacoes' => [], 'erro' => $e->getMessage()]);
        }
    });

    Route::post('/ouvidoria', function (\Illuminate\Http\Request $request) {
        try {
            $user = \Illuminate\Support\Facades\Auth::user();
            $funcionario = \App\Models\Funcionario::where('USUARIO_ID', $user->USUARIO_ID ?? 0)->first();
            $ano = now()->format('Y');
            $seq = \Illuminate\Support\Facades\DB::table('OUVIDORIA')->count() + 1;
            $proto = "OUV-{$ano}-" . str_pad($seq, 3, '0', STR_PAD_LEFT);
            $id = \Illuminate\Support\Facades\DB::table('OUVIDORIA')->insertGetId([
                'FUNCIONARIO_ID' => $request->anonimo ? null : ($funcionario->FUNCIONARIO_ID ?? null),
                'OUVIDORIA_TIPO' => $request->tipo,
                'OUVIDORIA_AREA' => $request->area,
                'OUVIDORIA_URGENCIA' => $request->urgencia ?? 'normal',
                'OUVIDORIA_DESC' => $request->descricao,
                'OUVIDORIA_STATUS' => 'recebida',
                'OUVIDORIA_PROTOCOLO' => $proto,
                'OUVIDORIA_DATA' => now()->toDateString(),
                'OUVIDORIA_ANONIMO' => $request->anonimo ? 1 : 0,
            ]);
            return response()->json(['id' => $id, 'protocolo' => $proto], 201);
        } catch (\Throwable $e) {
            return response()->json(['erro' => $e->getMessage()], 500);
        }
    });

    // â”€â”€ Faltas e Atrasos (visÃ£o RH/gestor) â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€
    Route::get('/abonos-gestao', function (\Illuminate\Http\Request $request) {
        try {
            $user = \Illuminate\Support\Facades\Auth::user();
            if (!$user) {
                return response()->json(['erro' => 'Não autenticado.'], 401);
            }

            $perfilIds = \Illuminate\Support\Facades\DB::table('USUARIO_PERFIL')
                ->where('USUARIO_ID', $user->USUARIO_ID)
                ->where('USUARIO_PERFIL_ATIVO', 1)
                ->pluck('PERFIL_ID');
            $isAdmin = $perfilIds->contains(\App\MyLibs\PerfilEnum::DESENVOLVEDOR)
                || $perfilIds->contains(\App\MyLibs\PerfilEnum::ADMINISTRADOR);

            // Visão de toda a rede (SEMAD / RH central): não restringe por unidade; gestor de unidade sim.
            $isVisaoAmpla = $isAdmin || $perfilIds->contains(\App\MyLibs\PerfilEnum::RH_FOLHA)
                || $perfilIds->contains(\App\MyLibs\PerfilEnum::GESTAO)
                || $perfilIds->contains(\App\MyLibs\PerfilEnum::RH_UNIDADE)
                || $perfilIds->contains(\App\MyLibs\PerfilEnum::RH_APS)
                || $perfilIds->contains(\App\MyLibs\PerfilEnum::RH_REDE);

            $unidadesPermitidas = null;
            if (!$isVisaoAmpla) {
                $unidadesPermitidas = \Illuminate\Support\Facades\DB::table('USUARIO_UNIDADE')
                    ->where('USUARIO_ID', $user->USUARIO_ID)
                    ->where('USUARIO_UNIDADE_ATIVO', 1)
                    ->pluck('UNIDADE_ID');
                if ($unidadesPermitidas->isEmpty() && $user->FUNCIONARIO_ID) {
                    $uid = \Illuminate\Support\Facades\DB::table('LOTACAO as l')
                        ->join('SETOR as s', 's.SETOR_ID', '=', 'l.SETOR_ID')
                        ->where('l.FUNCIONARIO_ID', $user->FUNCIONARIO_ID)
                        ->whereNull('l.LOTACAO_DATA_FIM')
                        ->orderByDesc('l.LOTACAO_ID')
                        ->value('s.UNIDADE_ID');
                    if ($uid) {
                        $unidadesPermitidas = collect([(int) $uid]);
                    }
                }
            }

            $unidadeSql = '(SELECT s.UNIDADE_ID FROM LOTACAO l2 INNER JOIN SETOR s ON s.SETOR_ID = l2.SETOR_ID WHERE l2.FUNCIONARIO_ID = f.FUNCIONARIO_ID AND l2.LOTACAO_DATA_FIM IS NULL ORDER BY l2.LOTACAO_ID DESC LIMIT 1)';

            $query = \Illuminate\Support\Facades\DB::table('ABONO_FALTA as af')
                ->leftJoin('FUNCIONARIO as f', 'f.FUNCIONARIO_ID', '=', 'af.FUNCIONARIO_ID')
                ->leftJoin('PESSOA as p', 'p.PESSOA_ID', '=', 'f.PESSOA_ID')
                ->select(
                    'af.ABONO_FALTA_ID as id',
                    'p.PESSOA_NOME as funcionario',
                    'f.FUNCIONARIO_ID as funcionario_id',
                    'af.ABONO_FALTA_TIPO as tipo',
                    'af.ABONO_FALTA_DATA_INICIO as data',
                    'af.ABONO_FALTA_JUSTIFICATIVA as justificativa',
                    'af.ABONO_FALTA_STATUS as situacao',
                    'af.ABONO_FALTA_COMPROVANTE as comprovante',
                    \Illuminate\Support\Facades\DB::raw($unidadeSql . ' as unidade_id')
                )
                ->orderByDesc('af.ABONO_FALTA_DATA_INICIO');

            // Filtra pelo perÃ­odo, mas SEMPRE inclui pendentes de qualquer mÃªs
            if ($request->filled('mes') && $request->filled('ano')) {
                $mes = $request->mes;
                $ano = $request->ano;
                $query->where(function ($q) use ($mes, $ano) {
                    $q->where(function ($q2) use ($mes, $ano) {
                        $q2->whereMonth('af.ABONO_FALTA_DATA_INICIO', $mes)
                            ->whereYear('af.ABONO_FALTA_DATA_INICIO', $ano);
                    })->orWhere('af.ABONO_FALTA_STATUS', 'pendente');
                });
            }

            $raw = $query->take(200)->get();
            if (!$isVisaoAmpla) {
                if ($unidadesPermitidas === null) {
                    $unidadesPermitidas = collect();
                }
                $allowed = $unidadesPermitidas->map(fn ($x) => (int) $x)->all();
                if (count($allowed) === 0) {
                    $raw = collect();
                } else {
                    $raw = $raw->filter(function ($a) use ($allowed) {
                        $u = isset($a->unidade_id) ? (int) $a->unidade_id : null;

                        return $u !== null && in_array($u, $allowed, true);
                    });
                }
            }

            $abonos = $raw->map(fn ($a) => [
                'id' => $a->id,
                'funcionario' => $a->funcionario ?? 'FuncionÃ¡rio #' . ($a->funcionario_id ?? '?'),
                'funcionario_id' => $a->funcionario_id,
                'unidade_id' => isset($a->unidade_id) && $a->unidade_id !== null ? (int) $a->unidade_id : null,
                'cargo' => '',
                'setor' => '',
                'tipo' => 'falta', // Todos os abonos sÃ£o de falta; ABONO_FALTA_TIPO Ã© o subtipo
                'subtipo' => $a->tipo, // medico, declaracao, luto, etc.
                'data' => $a->data,
                'duracao' => '8h 00min',
                'situacao' => $a->situacao ?? 'pendente',
                'justificativa' => $a->justificativa,
                'comprovante_url' => ($a->comprovante ?? null)
                    ? asset('storage/abonos/' . $a->comprovante) : null,
            ])->values();

            return response()->json($abonos);
        } catch (\Throwable $e) {
            return response()->json(['erro' => $e->getMessage()], 500);
        }
    });

    Route::put('/abonos-gestao/{id}/status', function ($id, \Illuminate\Http\Request $request) {
        $status = $request->input('status');
        \Illuminate\Support\Facades\DB::table('ABONO_FALTA')
            ->where('ABONO_FALTA_ID', $id)
            ->update(['ABONO_FALTA_STATUS' => $status]);
        return response()->json(['message' => 'Status atualizado.']);
    });

    // â”€â”€ Abono de Faltas â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€
    Route::get('/abono-faltas', function () {
        $user = Auth::user();
        $funcionario = optional($user)->funcionario
            ?? \App\Models\Funcionario::where('FUNCIONARIO_ID', $user->FUNCIONARIO_ID ?? 0)->first();

        if (!$funcionario)
            return response()->json([]);

        $abonos = \Illuminate\Support\Facades\DB::table('ABONO_FALTA')
            ->where('FUNCIONARIO_ID', $funcionario->FUNCIONARIO_ID)
            ->orderByDesc('ABONO_FALTA_DATA_INICIO')
            ->get()
            ->map(fn($j) => [
                'id' => $j->ABONO_FALTA_ID,
                'ABONO_FALTA_DATA' => $j->ABONO_FALTA_DATA_INICIO,
                'ABONO_FALTA_JUSTIFICATIVA' => $j->ABONO_FALTA_JUSTIFICATIVA,
                'tipo' => $j->ABONO_FALTA_TIPO ?? null,
                'status' => $j->ABONO_FALTA_STATUS ?? 'pendente',
                'comprovante_url' => $j->ABONO_FALTA_COMPROVANTE
                    ? asset('storage/abonos/' . $j->ABONO_FALTA_COMPROVANTE)
                    : null,
                'criado_em' => $j->ABONO_FALTA_DATA_INICIO,
            ]);

        return response()->json($abonos);
    });

    Route::post('/abono-faltas', function (\Illuminate\Http\Request $request) {
        $user = Auth::user();
        $funcionario = optional($user)->funcionario
            ?? \App\Models\Funcionario::where('FUNCIONARIO_ID', $user->FUNCIONARIO_ID ?? 0)->first();

        if (!$funcionario)
            return response()->json(['erro' => 'Funcionário não encontrado.'], 404);

        $nomeArquivo = null;
        if ($request->hasFile('comprovante') && $request->file('comprovante')->isValid()) {
            $arquivo = $request->file('comprovante');
            $nomeArquivo = 'f' . $funcionario->FUNCIONARIO_ID . '_' . time() . '.' . $arquivo->getClientOriginalExtension();
            $arquivo->storeAs('public/abonos', $nomeArquivo);
        }

        $id = \Illuminate\Support\Facades\DB::table('ABONO_FALTA')->insertGetId([
            'FUNCIONARIO_ID' => $funcionario->FUNCIONARIO_ID,
            'ABONO_FALTA_DATA_INICIO' => $request->ABONO_FALTA_DATA,
            'ABONO_FALTA_DATA_FIM' => $request->ABONO_FALTA_DATA,
            'ABONO_FALTA_JUSTIFICATIVA' => $request->ABONO_FALTA_JUSTIFICATIVA,
            'ABONO_FALTA_TIPO' => $request->tipo,
            'ABONO_FALTA_STATUS' => 'pendente',
            'ABONO_FALTA_COMPROVANTE' => $nomeArquivo,
        ]);

        return response()->json(['abono_id' => $id, 'comprovante' => $nomeArquivo], 201);
    })->middleware('upload.safe');

    Route::put('/abono-faltas/{id}', function ($id, \Illuminate\Http\Request $request) {
        $data = [
            'JUSTIFICATIVA_DATA' => $request->ABONO_FALTA_DATA,
            'JUSTIFICATIVA_DESCRICAO' => $request->ABONO_FALTA_JUSTIFICATIVA,
            'JUSTIFICATIVA_TIPO' => $request->tipo,
        ];

        if ($request->hasFile('comprovante') && $request->file('comprovante')->isValid()) {
            $arquivo = $request->file('comprovante');
            $nomeArquivo = 'edit_' . $id . '_' . time() . '.' . $arquivo->getClientOriginalExtension();
            $arquivo->storeAs('public/abonos', $nomeArquivo);
            $data['ABONO_FALTA_COMPROVANTE'] = $nomeArquivo;
        }

        \Illuminate\Support\Facades\DB::table('ABONO_FALTA')
            ->where('ABONO_FALTA_ID', $id)
            ->update($data);

        return response()->json(['message' => 'Atualizado com sucesso.']);
    });

    Route::delete('/abono-faltas/{id}', function ($id) {
        \Illuminate\Support\Facades\DB::table('ABONO_FALTA')
            ->where('ABONO_FALTA_ID', $id)
            ->delete();
            return response()->json(['message' => 'Excluído com sucesso.']);
    });

    // â”€â”€ FÃ©rias e LicenÃ§as do usuÃ¡rio logado â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€
    Route::get('/ferias', function () {
        $user = Auth::user();
        $funcionario = optional($user)->funcionario
            ?? \App\Models\Funcionario::where('USUARIO_ID', $user->USUARIO_ID)->first();

        if (!$funcionario) {
            return response()->json(['ferias' => [], 'solicitacoes' => [], 'saldo' => 0, 'vencimento' => '']);
        }

        $ferias = \App\Models\Ferias::where('FUNCIONARIO_ID', $funcionario->FUNCIONARIO_ID)
            ->orderBy('FERIAS_DATA_INICIO', 'desc')
            ->get()
            ->map(fn($f) => [
                'FERIAS_ID' => $f->FERIAS_ID,
                'FERIAS_DATA_INICIO' => $f->FERIAS_DATA_INICIO,
                'FERIAS_DATA_FIM' => $f->FERIAS_DATA_FIM,
                'FERIAS_AQUISITIVO_INICIO' => $f->FERIAS_AQUISITIVO_INICIO,
                'FERIAS_AQUISITIVO_FIM' => $f->FERIAS_AQUISITIVO_FIM,
                'ferias_id' => $f->FERIAS_ID,
                'ferias_inicio' => $f->FERIAS_DATA_INICIO,
                'ferias_fim' => $f->FERIAS_DATA_FIM,
                'aquisitivo_inicio' => $f->FERIAS_AQUISITIVO_INICIO,
                'aquisitivo_fim' => $f->FERIAS_AQUISITIVO_FIM,
                'dias' => $f->FERIAS_DATA_INICIO && $f->FERIAS_DATA_FIM
                    ? \Carbon\Carbon::parse($f->FERIAS_DATA_INICIO)->diffInDays($f->FERIAS_DATA_FIM)
                    : null,
                'status' => $f->FERIAS_DATA_FIM && \Carbon\Carbon::parse($f->FERIAS_DATA_FIM)->isPast()
                    ? 'GOZADA' : ($f->FERIAS_DATA_INICIO ? 'PROGRAMADA' : 'PENDENTE'),
            ])
            ->values();

        $saldo = 30 - $ferias->sum(fn($f) => (int) ($f['dias'] ?? 0));
        if ($saldo < 0) {
            $saldo = 0;
        }

        return response()->json([
            'ferias' => $ferias,
            'solicitacoes' => $ferias,
            'saldo' => $saldo,
            'vencimento' => now()->addMonths(6)->format('m/Y'),
        ]);
    });

    // BUG-EST-14: GET /ferias/sobreposicao
    Route::get('/ferias/sobreposicao', function (\Illuminate\Http\Request $request) {
        $hoje = now()->toDateString();
        $inicio = $request->inicio;
        $fim    = $request->fim;
        if (!$inicio || !$fim) {
            return response()->json(['sobreposicao' => false, 'membros' => [], 'pct' => 0]);
        }
        $user = Auth::user();
        $func = \App\Models\Funcionario::where('USUARIO_ID', $user->USUARIO_ID)->first();
        if (!$func) return response()->json(['sobreposicao' => false, 'membros' => [], 'pct' => 0]);
        $setorId = DB::table('LOTACAO')->where('FUNCIONARIO_ID', $func->FUNCIONARIO_ID)->whereNull('LOTACAO_DATA_FIM')->value('SETOR_ID');
        if (!$setorId) return response()->json(['sobreposicao' => false, 'membros' => [], 'pct' => 0]);
        $totalSetor = DB::table('LOTACAO as l')->join('FUNCIONARIO as f', 'f.FUNCIONARIO_ID', '=', 'l.FUNCIONARIO_ID')->where('l.SETOR_ID', $setorId)->whereNull('l.LOTACAO_DATA_FIM')->where(function ($w) use ($hoje) { $w->whereNull('f.FUNCIONARIO_DATA_FIM')->orWhere('f.FUNCIONARIO_DATA_FIM', '>', $hoje); })->count();
        $emFerias = DB::table('FERIAS as frs')
            ->join('FUNCIONARIO as f', 'f.FUNCIONARIO_ID', '=', 'frs.FUNCIONARIO_ID')
            ->join('PESSOA as p', 'p.PESSOA_ID', '=', 'f.PESSOA_ID')
            ->join('LOTACAO as l', function ($j) { $j->on('l.FUNCIONARIO_ID', '=', 'f.FUNCIONARIO_ID')->whereNull('l.LOTACAO_DATA_FIM'); })
            ->where('l.SETOR_ID', $setorId)
            ->where('frs.FUNCIONARIO_ID', '<>', $func->FUNCIONARIO_ID)
            ->where('frs.FERIAS_DATA_INICIO', '<=', $fim)
            ->where('frs.FERIAS_DATA_FIM', '>=', $inicio)
            ->select('p.PESSOA_NOME as nome', 'frs.FERIAS_DATA_INICIO as inicio', 'frs.FERIAS_DATA_FIM as fim')
            ->get();
        $pct = $totalSetor > 0 ? round(($emFerias->count() / $totalSetor) * 100) : 0;
        return response()->json(['sobreposicao' => $emFerias->count() > 0, 'membros' => $emFerias, 'total_setor' => $totalSetor, 'pct' => $pct]);
    });

    // â”€â”€ Faltas e Atrasos do usuÃ¡rio logado â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€
    Route::get('/faltas-atrasos', function (\Illuminate\Http\Request $request) {
        $user = Auth::user();
        $funcionario = optional($user)->funcionario
            ?? \App\Models\Funcionario::where('USUARIO_ID', $user->USUARIO_ID)->first();

        if (!$funcionario) {
            return response()->json(['faltas' => [], 'apuracao' => null]);
        }

        $mes = $request->mes ?? now()->month;
        $ano = $request->ano ?? now()->year;
        $competencia = sprintf('%04d-%02d', $ano, $mes);

        $apuracao = \App\Models\ApuracaoPonto::where('FUNCIONARIO_ID', $funcionario->FUNCIONARIO_ID)
            ->where('APURACAO_COMPETENCIA', $competencia)
            ->first();

        $justificativas = $apuracao
            ? \App\Models\JustificativaPonto::where('APURACAO_ID', $apuracao->APURACAO_ID)
                ->get()
                ->map(fn($j) => [
                    'id' => $j->JUSTIFICATIVA_ID ?? $j->getKey(),
                    'data' => $j->JUSTIFICATIVA_DATA ?? null,
                    'tipo' => $j->JUSTIFICATIVA_TIPO ?? 'FALTA',
                    'motivo' => $j->JUSTIFICATIVA_MOTIVO ?? null,
                    'status' => $j->JUSTIFICATIVA_STATUS ?? 'PENDENTE',
                ])
            : [];

        return response()->json([
            'apuracao' => $apuracao ? [
                'competencia' => $apuracao->APURACAO_COMPETENCIA,
                'horas_trab' => $apuracao->APURACAO_HORAS_TRAB,
                'horas_extra' => $apuracao->APURACAO_HORAS_EXTRA,
                'horas_falta' => $apuracao->APURACAO_HORAS_FALTA,
                'status' => $apuracao->APURACAO_STATUS,
            ] : null,
            'faltas' => $justificativas,
        ]);
    });

    // â”€â”€ Banco de Horas do usuÃ¡rio logado â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€
    Route::get('/banco-horas', function (\Illuminate\Http\Request $request) {
        $user = Auth::user();
        $funcionario = optional($user)->funcionario
            ?? \App\Models\Funcionario::where('USUARIO_ID', $user->USUARIO_ID)->first();

        if (!$funcionario) {
            return response()->json(['saldo' => 0, 'extrato' => []]);
        }

        $apuracoes = \App\Models\ApuracaoPonto::where('FUNCIONARIO_ID', $funcionario->FUNCIONARIO_ID)
            ->orderBy('APURACAO_COMPETENCIA', 'desc')
            ->take(12)
            ->get();

        $saldoAcumulado = 0;
        $extrato = $apuracoes->reverse()->values()->map(function ($a) use (&$saldoAcumulado) {
            $saldo_mes = (float) ($a->APURACAO_HORAS_EXTRA ?? 0) - (float) ($a->APURACAO_HORAS_FALTA ?? 0);
            $saldoAcumulado += $saldo_mes;
            return [
                'competencia' => $a->APURACAO_COMPETENCIA,
                'horas_trab' => (float) ($a->APURACAO_HORAS_TRAB ?? 0),
                'horas_extra' => (float) ($a->APURACAO_HORAS_EXTRA ?? 0),
                'horas_falta' => (float) ($a->APURACAO_HORAS_FALTA ?? 0),
                'saldo_mes' => round($saldo_mes, 2),
                'saldo_total' => round($saldoAcumulado, 2),
                'status' => $a->APURACAO_STATUS,
            ];
        })->reverse()->values();

        return response()->json([
            'saldo' => round($saldoAcumulado, 2),
            'extrato' => $extrato,
        ]);
    });

    // GET /abono-faltas removido deste grupo (use /api/v3/abono-faltas).

    // â”€â”€ Folhas de Pagamento (listagem) â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€
    // ── Folhas de Pagamento (listagem) ─────────────────────────────────
    // PMSL go-live 11/05/2026: schema-defensive, sem Eloquent::with() para evitar
    // PDOException em relacionamentos que dependem de colunas inexistentes no
    // schema PMSL (ex.: HISTORICO_FOLHA_ULTIMO em FOLHA, statusEscala etc).
    // Frontend FolhaPagamentoView.vue espera: FOLHA_ID, FOLHA_COMPETENCIA,
    // FOLHA_SITUACAO, qtd_funcionarios, total_proventos, total_descontos,
    // total_liquido. Quaisquer falhas SQL retornam 500 com array vazio (sem mock).
    Route::get('/folhas', function (\Illuminate\Http\Request $request) {
        try {
            $folhasRaw = \Illuminate\Support\Facades\DB::table('FOLHA')
                ->orderByDesc('FOLHA_COMPETENCIA')
                ->take(24)
                ->get();

            $folhas = $folhasRaw->map(function ($f) {
                $valorTotal = (float) ($f->FOLHA_VALOR_TOTAL ?? 0);
                $totalProventos = isset($f->FOLHA_TOTAL_PROVENTOS) ? (float) $f->FOLHA_TOTAL_PROVENTOS : $valorTotal;
                $totalDescontos = isset($f->FOLHA_TOTAL_DESCONTOS) ? (float) $f->FOLHA_TOTAL_DESCONTOS : 0.0;
                $totalLiquido = isset($f->FOLHA_TOTAL_LIQUIDO) ? (float) $f->FOLHA_TOTAL_LIQUIDO : ($totalProventos - $totalDescontos);

                return [
                    'FOLHA_ID' => (int) $f->FOLHA_ID,
                    'FOLHA_COMPETENCIA' => $f->FOLHA_COMPETENCIA,
                    'FOLHA_DESCRICAO' => $f->FOLHA_DESCRICAO ?? '',
                    'FOLHA_SITUACAO' => $f->FOLHA_SITUACAO ?? ($f->FOLHA_STATUS ?? 'A'),
                    'qtd_funcionarios' => (int) ($f->FOLHA_QTD_SERVIDORES ?? 0),
                    'total_proventos' => $totalProventos,
                    'total_descontos' => $totalDescontos,
                    'total_liquido' => $totalLiquido,
                ];
            });

            return response()->json(['folhas' => $folhas]);
        } catch (\Throwable $e) {
            \Illuminate\Support\Facades\Log::error('folhas_listagem_falhou', [
                'error' => $e->getMessage(),
                'trace_top' => collect($e->getTrace())->take(3)->toArray(),
            ]);
            // PMSL go-live: NUNCA retornar dados falsos. Cliente recebe array vazio + erro 500.
            return response()->json([
                'folhas' => [],
                'erro' => 'Nao foi possivel carregar a listagem de folhas. Consulte o suporte tecnico.',
            ], 500);
        }
    });

    // ── 13º Salário (GAP-13) ──────────────────────────────────────────────
    Route::get('/decimo-terceiro/preview/{tipo}/{ano}', function (string $tipo, int $ano) {
        if (!in_array($tipo, ['DECIMO_TERCEIRO_1', 'DECIMO_TERCEIRO_2'])) {
            return response()->json(['erro' => 'Tipo inválido.'], 422);
        }
        try {
            $service = app(\App\Services\DecimoTerceiroService::class);
            $ids = \Illuminate\Support\Facades\DB::table('FUNCIONARIO')
                ->when(\Illuminate\Support\Facades\Schema::hasColumn('FUNCIONARIO', 'FUNCIONARIO_ATIVO'),
                    fn($q) => $q->where('FUNCIONARIO_ATIVO', 1))
                ->pluck('FUNCIONARIO_ID')->map(fn($v) => (int)$v)->take(10)->all();

            $resultados = [];
            $totalEst = 0.0;
            foreach ($ids as $id) {
                $calc = ($tipo === 'DECIMO_TERCEIRO_1')
                    ? $service->calcularPrimeiraParcela($id, $ano)
                    : $service->calcularSegundaParcela($id, $ano);
                if ($calc['ok'] ?? false) {
                    $resultados[] = $calc;
                    $totalEst += $calc['valor_liquido'];
                }
            }
            return response()->json([
                'tipo' => $tipo, 'ano' => $ano,
                'amostra' => $resultados,
                'total_estimado_amostra' => round($totalEst, 2),
            ]);
        } catch (\Throwable $e) {
            return response()->json(['erro' => $e->getMessage()], 500);
        }
    });

    Route::post('/decimo-terceiro/calcular', function (\Illuminate\Http\Request $request) {
        $tipo = $request->input('tipo');
        $ano = (int) $request->input('ano', now()->year);
        $ids = $request->input('funcionario_ids', []);

        if (!in_array($tipo, ['DECIMO_TERCEIRO_1', 'DECIMO_TERCEIRO_2'])) {
            return response()->json(['erro' => 'Tipo inválido.'], 422);
        }
        try {
            $service = app(\App\Services\DecimoTerceiroService::class);
            return response()->json($service->processarLote($tipo, $ano, $ids));
        } catch (\Throwable $e) {
            \Illuminate\Support\Facades\Log::error('decimo_terceiro_erro', ['erro' => $e->getMessage()]);
            return response()->json(['erro' => $e->getMessage()], 500);
        }
    });

    // â”€â”€ SubstituiÃ§Ãµes de escala â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€
    Route::get('/substituicoes', function (\Illuminate\Http\Request $request) {
        try {
            $permitidosSetor = \App\Support\UnidadeEscopoUsuario::setorIdsPermitidos(auth()->user(), $request);
            if ($permitidosSetor === []) {
                return response()->json(['substituicoes' => [], 'historico' => [], 'erro' => 'Sem unidade (USUARIO_UNIDADE) para listar substituições.'], 403);
            }
            $colsSub = \Illuminate\Support\Facades\Schema::getColumnListing('SUBSTITUICAO_ESCALA');
            $temJustificativa = in_array('SUBSTITUICAO_ESCALA_JUSTIFICATIVA', $colsSub, true);
            $statusCol = in_array('SUBSTITUICAO_ESCALA_STATUS', $colsSub, true)
                ? 'SUBSTITUICAO_ESCALA_STATUS'
                : (in_array('STATUS', $colsSub, true) ? 'STATUS' : null);
            $temStatus = $statusCol !== null;
            $temTipoConvocacao = in_array('TIPO_CONVOCACAO', $colsSub, true);
            $temHorarioInicio = in_array('HORARIO_INICIO', $colsSub, true);
            $temHorarioFim = in_array('HORARIO_FIM', $colsSub, true);
            $temUnidade = in_array('UNIDADE_ESCOLAR', $colsSub, true);
            $temDisciplina = in_array('DISCIPLINA_CARGO', $colsSub, true);
            $temHistorico = \Illuminate\Support\Facades\Schema::hasTable('SUBSTITUICAO_ESCALA_HISTORICO');

            $temUnidadeSigla = \Illuminate\Support\Facades\Schema::hasColumn('UNIDADE', 'UNIDADE_SIGLA');

            $subs = \Illuminate\Support\Facades\DB::table('SUBSTITUICAO_ESCALA as se')
                ->leftJoin('FUNCIONARIO as fs', 'fs.FUNCIONARIO_ID', '=', 'se.FUNCIONARIO_ID')
                ->leftJoin('PESSOA as ps', 'ps.PESSOA_ID', '=', 'fs.PESSOA_ID')
                ->leftJoin('FUNCIONARIO as fr', 'fr.FUNCIONARIO_ID', '=', 'se.FUNCIONARIO_SUBSTITUTO_ID')
                ->leftJoin('PESSOA as pr', 'pr.PESSOA_ID', '=', 'fr.PESSOA_ID')
                ->leftJoin('ESCALA as e', 'e.ESCALA_ID', '=', 'se.ESCALA_ID')
                ->leftJoin('SETOR as st', 'st.SETOR_ID', '=', 'e.SETOR_ID')
                ->when($permitidosSetor !== null, function ($q) use ($permitidosSetor) {
                    $q->whereIn('st.SETOR_ID', $permitidosSetor);
                })
                ->when($temUnidadeSigla, function ($q) {
                    $q->leftJoin('UNIDADE as un_esc', 'un_esc.UNIDADE_ID', '=', 'st.UNIDADE_ID');
                })
                ->leftJoin('DETALHE_ESCALA as de', function ($join) {
                    $join->on('de.ESCALA_ID', '=', 'se.ESCALA_ID')
                        ->on('de.FUNCIONARIO_ID', '=', 'se.FUNCIONARIO_ID');
                })
                ->leftJoin('DETALHE_ESCALA_ITEM as dei', function ($join) {
                    $join->on('dei.DETALHE_ESCALA_ID', '=', 'de.DETALHE_ESCALA_ID')
                        ->on('dei.DETALHE_ESCALA_ITEM_DATA', '=', 'se.SUBSTITUICAO_ESCALA_DATA');
                })
                ->leftJoin('TURNO as t', 't.TURNO_ID', '=', 'dei.TURNO_ID')
                ->orderByDesc('se.SUBSTITUICAO_ESCALA_DATA')
                ->orderByDesc('se.SUBSTITUICAO_ESCALA_ID')
                ->limit(120)
                ->select(
                    'se.SUBSTITUICAO_ESCALA_ID as id',
                    'se.ESCALA_ID as escala_id',
                    'e.ESCALA_COMPETENCIA as competencia',
                    'se.FUNCIONARIO_ID as solicitante_id',
                    'ps.PESSOA_NOME as solicitante',
                    'se.FUNCIONARIO_SUBSTITUTO_ID as substituto_id',
                    'pr.PESSOA_NOME as substituto',
                    'se.SUBSTITUICAO_ESCALA_DATA as data_plantao',
                    'st.SETOR_NOME as setor',
                    't.TURNO_SIGLA as turno_sigla',
                    $temJustificativa
                        ? \Illuminate\Support\Facades\DB::raw('se.SUBSTITUICAO_ESCALA_JUSTIFICATIVA as motivo')
                        : \Illuminate\Support\Facades\DB::raw('NULL as motivo'),
                    $temTipoConvocacao
                        ? \Illuminate\Support\Facades\DB::raw('se.TIPO_CONVOCACAO as tipo_convocacao')
                        : \Illuminate\Support\Facades\DB::raw("'OPTATIVA' as tipo_convocacao"),
                    $temHorarioInicio
                        ? \Illuminate\Support\Facades\DB::raw('se.HORARIO_INICIO as horario_inicio')
                        : \Illuminate\Support\Facades\DB::raw('NULL as horario_inicio'),
                    $temHorarioFim
                        ? \Illuminate\Support\Facades\DB::raw('se.HORARIO_FIM as horario_fim')
                        : \Illuminate\Support\Facades\DB::raw('NULL as horario_fim'),
                    $temUnidade
                        ? \Illuminate\Support\Facades\DB::raw('se.UNIDADE_ESCOLAR as unidade_escolar')
                        : \Illuminate\Support\Facades\DB::raw('NULL as unidade_escolar'),
                    $temDisciplina
                        ? \Illuminate\Support\Facades\DB::raw('se.DISCIPLINA_CARGO as disciplina_cargo')
                        : \Illuminate\Support\Facades\DB::raw('NULL as disciplina_cargo'),
                    $temStatus
                        ? \Illuminate\Support\Facades\DB::raw("se.{$statusCol} as status")
                        : \Illuminate\Support\Facades\DB::raw("'pendente_aceite' as status"),
                    $temUnidadeSigla
                        ? \Illuminate\Support\Facades\DB::raw('un_esc.UNIDADE_SIGLA as unidade_sigla')
                        : \Illuminate\Support\Facades\DB::raw('NULL as unidade_sigla')
                )
                ->get()
                ->map(function ($s) {
                    return [
                        'id' => $s->id,
                        'escala_id' => $s->escala_id,
                        'competencia' => $s->competencia,
                        'solicitante_id' => $s->solicitante_id,
                        'solicitante' => $s->solicitante ?? '—',
                        'substituto_id' => $s->substituto_id,
                        'substituto' => $s->substituto ?? '—',
                        'data_plantao' => $s->data_plantao,
                        'turno' => $s->turno_sigla ?? '—',
                        'tipo_convocacao' => strtoupper((string) ($s->tipo_convocacao ?? 'OPTATIVA')),
                        'horario_inicio' => $s->horario_inicio,
                        'horario_fim' => $s->horario_fim,
                        'unidade_escolar' => $s->unidade_escolar,
                        'disciplina_cargo' => $s->disciplina_cargo,
                        'setor' => $s->setor ?? '—',
                        'unidade_sigla' => $s->unidade_sigla
                            ? strtoupper(trim((string) $s->unidade_sigla))
                            : null,
                        'motivo' => $s->motivo,
                        'status' => strtolower((string) ($s->status ?? 'pendente_aceite')),
                        'criado_em' => $s->data_plantao,
                    ];
                });

            $subsIndex = $subs->keyBy('id');
            $historico = collect();
            $ultimoStatusPorSub = collect();
            if ($temHistorico) {
                $historico = \Illuminate\Support\Facades\DB::table('SUBSTITUICAO_ESCALA_HISTORICO')
                    ->orderByDesc('DECIDIDO_EM')
                    ->orderByDesc('ID')
                    ->limit(200)
                    ->get()
                    ->map(function ($h) use ($subsIndex) {
                        $sub = $subsIndex->get($h->SUBSTITUICAO_ESCALA_ID) ?? [];
                        return [
                            'id' => $h->ID,
                            'substituicao_id' => $h->SUBSTITUICAO_ESCALA_ID,
                            'status' => strtolower((string) $h->STATUS),
                            'justificativa' => $h->JUSTIFICATIVA,
                            'decidido_em' => $h->DECIDIDO_EM ?? $h->created_at,
                            'gestor_usuario_id' => $h->GESTOR_USUARIO_ID,
                            'solicitante' => $sub['solicitante'] ?? '—',
                            'substituto' => $sub['substituto'] ?? '—',
                            'data_plantao' => $sub['data_plantao'] ?? null,
                            'turno' => $sub['turno'] ?? '—',
                            'setor' => $sub['setor'] ?? '—',
                            'unidade_sigla' => $sub['unidade_sigla'] ?? null,
                            'unidade_escolar' => $sub['unidade_escolar'] ?? null,
                            'disciplina_cargo' => $sub['disciplina_cargo'] ?? null,
                        ];
                    });
                $ultimoStatusPorSub = $historico
                    ->groupBy('substituicao_id')
                    ->map(fn($g) => $g->first());
            }

            if ($temStatus) {
                $subs = $subs->map(function ($s) use ($ultimoStatusPorSub) {
                    if ($s['status'] === '' || $s['status'] === null) {
                        $hist = $ultimoStatusPorSub->get($s['id']);
                        if ($hist) {
                            $s['status'] = strtolower((string) ($hist['status'] ?? 'pendente'));
                            if (!$s['motivo']) {
                                $s['motivo'] = $hist['justificativa'] ?? null;
                            }
                        }
                    }
                    return $s;
                });
            } else {
                $subs = $subs->map(function ($s) use ($ultimoStatusPorSub) {
                    $hist = $ultimoStatusPorSub->get($s['id']);
                    if ($hist) {
                        $s['status'] = strtolower((string) ($hist['status'] ?? 'pendente'));
                        if (!$s['motivo']) {
                            $s['motivo'] = $hist['justificativa'] ?? null;
                        }
                    } else {
                        $s['status'] = 'pendente_aceite';
                    }
                    return $s;
                });
            }

            return response()->json(['substituicoes' => $subs, 'historico' => $historico]);
        } catch (\Throwable $e) {
            return response()->json(['substituicoes' => [], 'erro' => $e->getMessage()], 500);
        }
    });

    Route::get('/substituicoes/minhas', function (\Illuminate\Http\Request $request) {
        try {
            $usuarioId = auth()->id();
            if (!$usuarioId) {
                return response()->json(['pendentes' => [], 'historico' => []], 401);
            }
            $funcionarioId = \Illuminate\Support\Facades\DB::table('FUNCIONARIO')
                ->where('USUARIO_ID', (int) $usuarioId)
                ->value('FUNCIONARIO_ID');
            if (!$funcionarioId) {
                return response()->json(['pendentes' => [], 'historico' => []]);
            }

            $sub = \Illuminate\Http\Request::create(\Illuminate\Support\Facades\URL::to('/api/v3/substituicoes'), 'GET', $request->query->all());
            if ($request->hasSession()) {
                $sub->setLaravelSession($request->session());
            }
            $sub->setUserResolver($request->getUserResolver());
            if ($request->headers->has('Cookie')) {
                $sub->headers->set('Cookie', (string) $request->header('Cookie'));
            }
            $resp = app()->handle($sub);
            $data = json_decode($resp->getContent(), true);
            $subs = collect($data['substituicoes'] ?? [])->filter(
                fn($s) => (int) ($s['substituto_id'] ?? 0) === (int) $funcionarioId
            );

            $servidorUnidadeSigla = null;
            if (\Illuminate\Support\Facades\Schema::hasTable('LOTACAO')) {
                $la = \App\Models\Lotacao::query()
                    ->with(['setor.unidade'])
                    ->where('FUNCIONARIO_ID', (int) $funcionarioId)
                    ->whereNull('LOTACAO_DATA_FIM')
                    ->orderByDesc('LOTACAO_ID')
                    ->first();
                if ($la?->setor?->unidade) {
                    $raw = $la->setor->unidade->UNIDADE_SIGLA ?? null;
                    $servidorUnidadeSigla = $raw ? strtoupper(trim((string) $raw)) : null;
                }
            }

            return response()->json([
                'pendentes' => $subs->filter(fn($s) => in_array(strtolower((string) ($s['status'] ?? '')), ['pendente_aceite', 'pendente'], true))->values(),
                'historico' => $subs->filter(fn($s) => !in_array(strtolower((string) ($s['status'] ?? '')), ['pendente_aceite', 'pendente'], true))->values(),
                'servidor_unidade_sigla' => $servidorUnidadeSigla,
            ]);
        } catch (\Throwable $e) {
            return response()->json(['pendentes' => [], 'historico' => [], 'erro' => $e->getMessage()], 500);
        }
    });

    Route::post('/substituicoes', function (\Illuminate\Http\Request $request) {
        $request->validate([
            'escala_id' => 'required|integer',
            'solicitante_id' => 'required|integer',
            'substituto_id' => 'nullable|integer',
            'data_plantao' => 'required|date',
            'turno' => 'nullable|string|max:40',
            'motivo' => 'nullable|string|max:255',
            'tipo_convocacao' => 'nullable|string|in:COMPULSORIA,OPTATIVA,compulsoria,optativa',
            'horario_inicio' => 'nullable|string|max:20',
            'horario_fim' => 'nullable|string|max:20',
            'unidade_escolar' => 'nullable|string|max:150',
            'disciplina_cargo' => 'nullable|string|max:150',
        ]);

        try {
            \App\Support\GenteAuditWriter::requireAuthenticatedUserId();
            $escEscala = \Illuminate\Support\Facades\DB::table('ESCALA')
                ->where('ESCALA_ID', (int) $request->escala_id)
                ->first();
            if (! $escEscala) {
                return response()->json(['erro' => 'Escala não encontrada.'], 404);
            }
            $sidEscala = (int) ($escEscala->SETOR_ID ?? 0);
            if ($sidEscala > 0) {
                \App\Support\UnidadeEscopoUsuario::abortoSeSetorNaoAutorizado(auth()->user(), $sidEscala, $request);
            }
            $colsSub = \Illuminate\Support\Facades\Schema::getColumnListing('SUBSTITUICAO_ESCALA');
            $payload = [
                'ESCALA_ID' => (int) $request->escala_id,
                'FUNCIONARIO_ID' => (int) $request->solicitante_id,
                'FUNCIONARIO_SUBSTITUTO_ID' => $request->substituto_id ? (int) $request->substituto_id : null,
                'SUBSTITUICAO_ESCALA_DATA' => $request->data_plantao,
            ];
            if (in_array('SUBSTITUICAO_ESCALA_JUSTIFICATIVA', $colsSub, true)) {
                $payload['SUBSTITUICAO_ESCALA_JUSTIFICATIVA'] = $request->motivo;
            }
            $tipoConv = strtoupper((string) ($request->tipo_convocacao ?? 'OPTATIVA'));
            $statusInicial = $tipoConv === 'COMPULSORIA' ? 'confirmada' : 'pendente_aceite';
            if (in_array('SUBSTITUICAO_ESCALA_STATUS', $colsSub, true)) {
                $payload['SUBSTITUICAO_ESCALA_STATUS'] = $statusInicial;
            } elseif (in_array('STATUS', $colsSub, true)) {
                $payload['STATUS'] = $statusInicial;
            }
            if (in_array('SUBSTITUICAO_ESCALA_TURNO', $colsSub, true)) {
                $payload['SUBSTITUICAO_ESCALA_TURNO'] = $request->turno;
            }
            if (in_array('TIPO_CONVOCACAO', $colsSub, true)) {
                $payload['TIPO_CONVOCACAO'] = $tipoConv;
            }
            if (in_array('HORARIO_INICIO', $colsSub, true)) {
                $payload['HORARIO_INICIO'] = $request->horario_inicio;
            }
            if (in_array('HORARIO_FIM', $colsSub, true)) {
                $payload['HORARIO_FIM'] = $request->horario_fim;
            }
            if (in_array('UNIDADE_ESCOLAR', $colsSub, true)) {
                $payload['UNIDADE_ESCOLAR'] = $request->unidade_escolar;
            }
            if (in_array('DISCIPLINA_CARGO', $colsSub, true)) {
                $payload['DISCIPLINA_CARGO'] = $request->disciplina_cargo;
            }

            $id = \Illuminate\Support\Facades\DB::table('SUBSTITUICAO_ESCALA')->insertGetId($payload);
            if (\Illuminate\Support\Facades\Schema::hasTable('NOTIFICACAO')) {
                $solicitanteUserId = \Illuminate\Support\Facades\DB::table('FUNCIONARIO')
                    ->where('FUNCIONARIO_ID', (int) $request->solicitante_id)
                    ->value('USUARIO_ID');
                $substitutoUserId = $request->substituto_id
                    ? \Illuminate\Support\Facades\DB::table('FUNCIONARIO')
                        ->where('FUNCIONARIO_ID', (int) $request->substituto_id)
                        ->value('USUARIO_ID')
                    : null;
                $adminsUserIds = collect();
                if (
                    \Illuminate\Support\Facades\Schema::hasTable('USUARIO_PERFIL')
                    && \Illuminate\Support\Facades\Schema::hasTable('PERFIL')
                ) {
                    $adminsUserIds = \Illuminate\Support\Facades\DB::table('USUARIO_PERFIL as up')
                        ->join('PERFIL as p', 'p.PERFIL_ID', '=', 'up.PERFIL_ID')
                        ->where('up.USUARIO_PERFIL_ATIVO', 1)
                        ->where('p.PERFIL_ATIVO', 1)
                        ->whereRaw('UPPER(p.PERFIL_NOME) LIKE ?', ['%ADMIN%'])
                        ->pluck('up.USUARIO_ID');
                }
                $destinatarios = collect([$solicitanteUserId, $substitutoUserId])
                    ->merge($adminsUserIds)
                    ->filter(fn($u) => !empty($u))
                    ->unique()
                    ->values();
                foreach ($destinatarios as $uid) {
                    \Illuminate\Support\Facades\DB::table('NOTIFICACAO')->insert([
                        'USUARIO_ID' => (int) $uid,
                        'NOTIFICACAO_TITULO' => 'Cobertura de Turno (SEMED)',
                        'NOTIFICACAO_BODY' => $tipoConv === 'COMPULSORIA'
                            ? 'Convocação compulsória registrada. Sua escala foi atualizada.'
                            : 'Convite optativo de cobertura pendente de aceite.',
                        'NOTIFICACAO_TIPO' => 'substituicao',
                        'NOTIFICACAO_ICONE' => '🔄',
                        'NOTIFICACAO_URL' => '/substituicoes',
                        'NOTIFICACAO_LIDA' => 0,
                        'NOTIFICACAO_DT_CRIACAO' => now(),
                    ]);
                }
            }
            return response()->json(['id' => $id], 201);
        } catch (\Illuminate\Auth\AuthenticationException $e) {
            return response()->json(['erro' => $e->getMessage()], 401);
        } catch (\Illuminate\Http\Exceptions\HttpResponseException $e) {
            return $e->getResponse();
        } catch (\Throwable $e) {
            return response()->json(['erro' => $e->getMessage()], 500);
        }
    });

    Route::put('/substituicoes/{id}', function ($id, \Illuminate\Http\Request $request) {
        try {
            \App\Support\GenteAuditWriter::requireAuthenticatedUserId();
            $colsSub = \Illuminate\Support\Facades\Schema::getColumnListing('SUBSTITUICAO_ESCALA');
            $statusRaw = strtolower(trim((string) ($request->status ?? '')));
            $statusMap = [
                'pendente' => 'pendente_aceite',
                'pendente_aceite' => 'pendente_aceite',
                'confirmada' => 'confirmada',
                'confirmado' => 'confirmada',
                'aprovada' => 'confirmada',
                'aprovado' => 'confirmada',
                'aceita' => 'confirmada',
                'aceito' => 'confirmada',
                'recusada' => 'recusada',
                'reprovada' => 'recusada',
                'reprovado' => 'recusada',
                'falta_substituicao' => 'falta_substituicao',
            ];
            $status = $statusMap[$statusRaw] ?? null;
            if (!$status) {
                return response()->json(['erro' => 'Status inválido.'], 422);
            }
            $justificativa = trim((string) ($request->justificativa ?? ''));
            if ($status === 'recusada' && $justificativa === '') {
                return response()->json(['erro' => 'Informe a justificativa para recusar a substituição.'], 422);
            }
            $statusDbCol = in_array('SUBSTITUICAO_ESCALA_STATUS', $colsSub, true)
                ? 'SUBSTITUICAO_ESCALA_STATUS'
                : (in_array('STATUS', $colsSub, true) ? 'STATUS' : null);
            $temHistorico = \Illuminate\Support\Facades\Schema::hasTable('SUBSTITUICAO_ESCALA_HISTORICO');
            if (!$statusDbCol && !$temHistorico) {
                return response()->json(['erro' => 'Sem coluna de status e sem tabela de histórico para persistir decisão.'], 500);
            }
            $substituicao = \Illuminate\Support\Facades\DB::table('SUBSTITUICAO_ESCALA')
                ->where('SUBSTITUICAO_ESCALA_ID', (int) $id)
                ->first();
            if (!$substituicao) {
                return response()->json(['erro' => 'Substituição não encontrada.'], 404);
            }
            $escRef = \Illuminate\Support\Facades\DB::table('ESCALA')
                ->where('ESCALA_ID', (int) $substituicao->ESCALA_ID)
                ->first();
            if ($escRef) {
                $stId = (int) ($escRef->SETOR_ID ?? 0);
                if ($stId > 0) {
                    \App\Support\UnidadeEscopoUsuario::abortoSeSetorNaoAutorizado(auth()->user(), $stId, $request);
                }
            }

            $statusToPersist = $status;
            if ($statusDbCol) {
                $updated = 0;
                try {
                    $updated = \Illuminate\Support\Facades\DB::table('SUBSTITUICAO_ESCALA')
                        ->where('SUBSTITUICAO_ESCALA_ID', (int) $id)
                        ->update([$statusDbCol => $statusToPersist]);
                } catch (\Throwable $statusEx) {
                    // Compatibilidade com ambientes legados onde o status é numérico.
                    $statusLegacyMap = [
                        'pendente_aceite' => 0,
                        'confirmada' => 1,
                        'recusada' => 2,
                        'falta_substituicao' => 3,
                    ];
                    $statusToPersist = $statusLegacyMap[$status] ?? 0;
                    $updated = \Illuminate\Support\Facades\DB::table('SUBSTITUICAO_ESCALA')
                        ->where('SUBSTITUICAO_ESCALA_ID', (int) $id)
                        ->update([$statusDbCol => $statusToPersist]);
                }
                if (!$updated) {
                    return response()->json(['erro' => 'Substituição não encontrada ou status já aplicado.'], 404);
                }
            }

            if (in_array('SUBSTITUICAO_ESCALA_JUSTIFICATIVA', $colsSub, true) && $justificativa !== '') {
                \Illuminate\Support\Facades\DB::table('SUBSTITUICAO_ESCALA')
                    ->where('SUBSTITUICAO_ESCALA_ID', (int) $id)
                    ->update(['SUBSTITUICAO_ESCALA_JUSTIFICATIVA' => $justificativa]);
            }

            $user = \Illuminate\Support\Facades\Auth::user();
            $userId = is_object($user) ? ($user->USUARIO_ID ?? null) : null;
            $histId = null;
            $histErro = null;
            if ($temHistorico) {
                try {
                    $colsHist = \Illuminate\Support\Facades\Schema::getColumnListing('SUBSTITUICAO_ESCALA_HISTORICO');
                    $histPayload = [];
                    if (in_array('GESTOR_USUARIO_ID', $colsHist, true)) {
                        $histPayload['GESTOR_USUARIO_ID'] = $userId;
                    }
                    if (in_array('SUBSTITUICAO_ESCALA_ID', $colsHist, true)) {
                        $histPayload['SUBSTITUICAO_ESCALA_ID'] = (int) $id;
                    }
                    if (in_array('STATUS', $colsHist, true)) {
                        $histPayload['STATUS'] = (string) $status;
                    }
                    if (in_array('JUSTIFICATIVA', $colsHist, true)) {
                        $histPayload['JUSTIFICATIVA'] = $justificativa ?: null;
                    }
                    if (in_array('DECIDIDO_EM', $colsHist, true)) {
                        $histPayload['DECIDIDO_EM'] = now();
                    }
                    if (in_array('created_at', $colsHist, true)) {
                        $histPayload['created_at'] = now();
                    }
                    if (in_array('updated_at', $colsHist, true)) {
                        $histPayload['updated_at'] = now();
                    }
                    if (!empty($histPayload)) {
                        $histId = \Illuminate\Support\Facades\DB::table('SUBSTITUICAO_ESCALA_HISTORICO')->insertGetId($histPayload);
                    }
                } catch (\Throwable $histEx) {
                    $histErro = $histEx->getMessage();
                    \Illuminate\Support\Facades\Log::warning('Falha ao registrar histórico de substituição', [
                        'substituicao_id' => (int) $id,
                        'erro' => $histErro,
                    ]);
                }
            }
            if (\Illuminate\Support\Facades\Schema::hasTable('NOTIFICACAO')) {
                $solicitanteUserId = \Illuminate\Support\Facades\DB::table('FUNCIONARIO')
                    ->where('FUNCIONARIO_ID', (int) ($substituicao->FUNCIONARIO_ID ?? 0))
                    ->value('USUARIO_ID');
                $substitutoUserId = \Illuminate\Support\Facades\DB::table('FUNCIONARIO')
                    ->where('FUNCIONARIO_ID', (int) ($substituicao->FUNCIONARIO_SUBSTITUTO_ID ?? 0))
                    ->value('USUARIO_ID');
                $titulo = $status === 'confirmada' ? 'Cobertura confirmada' : ($status === 'recusada' ? 'Cobertura recusada' : 'Alerta de cobertura');
                $body = $status === 'confirmada'
                    ? 'A cobertura de turno/regência foi confirmada.'
                    : ($status === 'falta_substituicao'
                        ? 'Falta em substituição confirmada. Encaminhar para desconto em folha.'
                        : ('A cobertura de turno foi recusada.' . ($justificativa ? ' Motivo: ' . $justificativa : '')));
                $adminsUserIds = collect();
                if (
                    \Illuminate\Support\Facades\Schema::hasTable('USUARIO_PERFIL')
                    && \Illuminate\Support\Facades\Schema::hasTable('PERFIL')
                ) {
                    $adminsUserIds = \Illuminate\Support\Facades\DB::table('USUARIO_PERFIL as up')
                        ->join('PERFIL as p', 'p.PERFIL_ID', '=', 'up.PERFIL_ID')
                        ->where('up.USUARIO_PERFIL_ATIVO', 1)
                        ->where('p.PERFIL_ATIVO', 1)
                        ->whereRaw('UPPER(p.PERFIL_NOME) LIKE ?', ['%ADMIN%'])
                        ->pluck('up.USUARIO_ID');
                }
                $destinatarios = collect([$solicitanteUserId, $substitutoUserId])
                    ->merge($adminsUserIds)
                    ->filter(fn($u) => !empty($u))
                    ->unique()
                    ->values();
                foreach ($destinatarios as $uid) {
                    \Illuminate\Support\Facades\DB::table('NOTIFICACAO')->insert([
                        'USUARIO_ID' => (int) $uid,
                        'NOTIFICACAO_TITULO' => $titulo,
                        'NOTIFICACAO_BODY' => $body,
                        'NOTIFICACAO_TIPO' => 'substituicao',
                        'NOTIFICACAO_ICONE' => $status === 'confirmada' ? '✅' : ($status === 'falta_substituicao' ? '⚠️' : '❌'),
                        'NOTIFICACAO_URL' => '/substituicoes',
                        'NOTIFICACAO_LIDA' => 0,
                        'NOTIFICACAO_DT_CRIACAO' => now(),
                    ]);
                }
            }

            return response()->json([
                'ok' => true,
                'status' => $status,
                'historico_ok' => $histErro === null,
                'historico_erro' => $histErro,
                'historico_item' => [
                    'id' => $histId,
                    'substituicao_id' => (int) $id,
                    'status' => $status,
                    'justificativa' => $justificativa ?: null,
                    'decidido_em' => now()->toDateTimeString(),
                ],
            ]);
        } catch (\Illuminate\Auth\AuthenticationException $e) {
            return response()->json(['erro' => $e->getMessage()], 401);
        } catch (\Illuminate\Http\Exceptions\HttpResponseException $e) {
            return $e->getResponse();
        } catch (\Throwable $e) {
            return response()->json(['erro' => $e->getMessage()], 500);
        }
    });

    // â”€â”€ Escalas (listagem resumida) â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€
    Route::get('/escalas', function (\Illuminate\Http\Request $request) {
        try {
            $perPage = max(10, min(200, (int) $request->input('per_page', 50)));
            $carregarTudo = filter_var((string) $request->input('carregar_tudo', '0'), FILTER_VALIDATE_BOOLEAN);
            $somenteSaude = filter_var((string) $request->input('somente_saude', '0'), FILTER_VALIDATE_BOOLEAN);
            $setorId = $request->filled('setor_id') ? (int) $request->input('setor_id') : null;
            $usuario = auth()->user();
            $permitidos = \App\Support\UnidadeEscopoUsuario::setorIdsPermitidos($usuario, $request);
            if ($permitidos === []) {
                return response()->json([
                    'escalas' => [],
                    'paginacao' => ['page' => 1, 'per_page' => $perPage, 'total' => 0, 'last_page' => 1],
                ]);
            }
            if (! $carregarTudo && ! $setorId) {
                return response()->json([
                    'escalas' => [],
                    'paginacao' => ['page' => 1, 'per_page' => $perPage, 'total' => 0, 'last_page' => 1],
                    'hint' => 'Selecione um setor para carregar a grade. Para visão macro use carregar_tudo=1 (sempre paginado).',
                ]);
            }
            if ($setorId && $permitidos !== null && ! in_array($setorId, $permitidos, true)) {
                return response()->json(['escalas' => [], 'erro' => 'Setor fora do escopo permitido.'], 403);
            }

            $q = \Illuminate\Support\Facades\DB::table('ESCALA as e')
                ->leftJoin('SETOR as s', 's.SETOR_ID', '=', 'e.SETOR_ID')
                ->leftJoin('DETALHE_ESCALA as de', 'de.ESCALA_ID', '=', 'e.ESCALA_ID')
                ->leftJoin('FUNCIONARIO as f', 'f.FUNCIONARIO_ID', '=', 'de.FUNCIONARIO_ID')
                ->leftJoin('CARGO as c', 'c.CARGO_ID', '=', 'f.CARGO_ID')
                ->when($permitidos !== null, fn($qq) => $qq->whereIn('e.SETOR_ID', $permitidos))
                ->when($setorId, fn($qq) => $qq->where('e.SETOR_ID', $setorId))
                ->when($somenteSaude, function ($qq) {
                    $qq->where(function ($w) {
                        if (\Illuminate\Support\Facades\Schema::hasColumn('CARGO', 'CARGO_AREA')) {
                            $w->orWhereRaw('UPPER(CAST(c.CARGO_AREA AS VARCHAR(255))) LIKE ?', ['%SAUDE%']);
                        }
                        if (\Illuminate\Support\Facades\Schema::hasColumn('CARGO', 'AREA')) {
                            $w->orWhereRaw('UPPER(CAST(c.AREA AS VARCHAR(255))) LIKE ?', ['%SAUDE%']);
                        }
                        if (\Illuminate\Support\Facades\Schema::hasColumn('CARGO', 'CARGO_TIPO')) {
                            $w->orWhereRaw('UPPER(CAST(c.CARGO_TIPO AS VARCHAR(255))) LIKE ?', ['%SAUDE%']);
                        }
                        if (\Illuminate\Support\Facades\Schema::hasColumn('CARGO', 'CBO')) {
                            $w->orWhereRaw('CAST(c.CBO AS VARCHAR(255)) LIKE ?', ['223%']);
                        }
                        if (\Illuminate\Support\Facades\Schema::hasColumn('CARGO', 'CARGO_CBO')) {
                            $w->orWhereRaw('CAST(c.CARGO_CBO AS VARCHAR(255)) LIKE ?', ['223%']);
                        }
                        $w->orWhereRaw('UPPER(COALESCE(c.CARGO_NOME, \'\')) LIKE ?', ['%MEDIC%']);
                        $w->orWhereRaw('UPPER(COALESCE(c.CARGO_NOME, \'\')) LIKE ?', ['%ENFERM%']);
                    });
                })
                ->select(
                    'e.ESCALA_ID',
                    'e.ESCALA_COMPETENCIA',
                    'e.ESCALA_DESCRICAO',
                    \Illuminate\Support\Facades\DB::raw('MAX(s.SETOR_NOME) as setor'),
                    \Illuminate\Support\Facades\DB::raw('COUNT(DISTINCT de.FUNCIONARIO_ID) as funcionarios')
                )
                ->groupBy('e.ESCALA_ID', 'e.ESCALA_COMPETENCIA', 'e.ESCALA_DESCRICAO')
                ->orderByDesc('e.ESCALA_ID');

            $page = $q->paginate($perPage);
            $escalas = collect($page->items())->map(fn($e) => [
                'ESCALA_ID' => $e->ESCALA_ID,
                'ESCALA_COMPETENCIA' => $e->ESCALA_COMPETENCIA,
                'ESCALA_DESCRICAO' => $e->ESCALA_DESCRICAO,
                'setor' => $e->setor ?? '—',
                'funcionarios' => (int) ($e->funcionarios ?? 0),
                'status' => 'CADASTRADA',
            ])->values();

            return response()->json([
                'escalas' => $escalas,
                'paginacao' => [
                    'page' => $page->currentPage(),
                    'per_page' => $page->perPage(),
                    'total' => $page->total(),
                    'last_page' => $page->lastPage(),
                ],
            ]);
        } catch (\Throwable $e) {
            return response()->json(['escalas' => [], 'erro' => $e->getMessage()], 500);
        }
    });

    // â”€â”€ Escala individual (detalhada para o Vue) â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€
    Route::get('/escalas/{id}', function (\Illuminate\Http\Request $request, $id) {
        try {
            $somenteSaude = filter_var((string) $request->input('somente_saude', '0'), FILTER_VALIDATE_BOOLEAN);
            $perPage = max(10, min(200, (int) $request->input('per_page', 50)));
            $usuario = auth()->user();
            $permitidos = \App\Support\UnidadeEscopoUsuario::setorIdsPermitidos($usuario, $request);
            if ($permitidos === []) {
                return response()->json(['erro' => 'Escopo vazio.'], 403);
            }
            $escala = \Illuminate\Support\Facades\DB::table('ESCALA')
                ->where('ESCALA_ID', $id)
                ->when($permitidos !== null, fn ($q) => $q->whereIn('SETOR_ID', $permitidos))
                ->first();
            if (!$escala) {
                return response()->json(['erro' => 'Escala não encontrada.'], 404);
            }

            $ano = (int) now()->year;
            $mes = (int) now()->month - 1;
            $comp = (string) ($escala->ESCALA_COMPETENCIA ?? '');
            if (preg_match('/^(\d{4})-(\d{2})$/', $comp, $m)) {
                $ano = (int) $m[1];
                $mes = (int) $m[2] - 1;
            } elseif (preg_match('/^(\d{2})\/(\d{4})$/', $comp, $m)) {
                $ano = (int) $m[2];
                $mes = (int) $m[1] - 1;
            }

            $detalhesQ = \Illuminate\Support\Facades\DB::table('DETALHE_ESCALA as de')
                ->leftJoin('FUNCIONARIO as f', 'f.FUNCIONARIO_ID', '=', 'de.FUNCIONARIO_ID')
                ->leftJoin('PESSOA as p', 'p.PESSOA_ID', '=', 'f.PESSOA_ID')
                ->leftJoin('CARGO as c', 'c.CARGO_ID', '=', 'f.CARGO_ID')
                ->leftJoin('LOTACAO as l', function ($j) {
                    $j->on('l.FUNCIONARIO_ID', '=', 'f.FUNCIONARIO_ID');
                    if (\Illuminate\Support\Facades\Schema::hasColumn('LOTACAO', 'LOTACAO_DATA_FIM')) {
                        $j->whereNull('l.LOTACAO_DATA_FIM');
                    }
                })
                ->leftJoin('SETOR as s', 's.SETOR_ID', '=', 'l.SETOR_ID')
                ->leftJoin('SETOR as sp', 'sp.SETOR_ID', '=', 's.SETOR_PAI_ID')
                ->leftJoin('UNIDADE as u', 'u.UNIDADE_ID', '=', 's.UNIDADE_ID')
                ->where('de.ESCALA_ID', $id)
                ->when($somenteSaude, function ($qq) {
                    $qq->where(function ($w) {
                        if (\Illuminate\Support\Facades\Schema::hasColumn('CARGO', 'CARGO_AREA')) {
                            $w->orWhereRaw('UPPER(CAST(c.CARGO_AREA AS VARCHAR(255))) LIKE ?', ['%SAUDE%']);
                        }
                        if (\Illuminate\Support\Facades\Schema::hasColumn('CARGO', 'AREA')) {
                            $w->orWhereRaw('UPPER(CAST(c.AREA AS VARCHAR(255))) LIKE ?', ['%SAUDE%']);
                        }
                        if (\Illuminate\Support\Facades\Schema::hasColumn('CARGO', 'CARGO_TIPO')) {
                            $w->orWhereRaw('UPPER(CAST(c.CARGO_TIPO AS VARCHAR(255))) LIKE ?', ['%SAUDE%']);
                        }
                        if (\Illuminate\Support\Facades\Schema::hasColumn('CARGO', 'CBO')) {
                            $w->orWhereRaw('CAST(c.CBO AS VARCHAR(255)) LIKE ?', ['223%']);
                        }
                        if (\Illuminate\Support\Facades\Schema::hasColumn('CARGO', 'CARGO_CBO')) {
                            $w->orWhereRaw('CAST(c.CARGO_CBO AS VARCHAR(255)) LIKE ?', ['223%']);
                        }
                        $w->orWhereRaw('UPPER(COALESCE(c.CARGO_NOME, \'\')) LIKE ?', ['%MEDIC%']);
                        $w->orWhereRaw('UPPER(COALESCE(c.CARGO_NOME, \'\')) LIKE ?', ['%ENFERM%']);
                    });
                })
                ->select(
                    'de.DETALHE_ESCALA_ID as detalhe_id',
                    'de.FUNCIONARIO_ID as funcionario_id',
                    'p.PESSOA_NOME as nome',
                    \Illuminate\Support\Facades\DB::raw('COALESCE(MAX(c.CARGO_NOME), \'\') as cargo'),
                    \Illuminate\Support\Facades\DB::raw('MAX(s.SETOR_NOME) as setor_nome'),
                    \Illuminate\Support\Facades\DB::raw('MAX(sp.SETOR_NOME) as setor_pai_nome'),
                    \Illuminate\Support\Facades\DB::raw('MAX(u.UNIDADE_NOME) as unidade_nome')
                )
                ->groupBy('de.DETALHE_ESCALA_ID', 'de.FUNCIONARIO_ID', 'p.PESSOA_NOME')
                ->orderBy('p.PESSOA_NOME');
            $detalhesPage = $detalhesQ->paginate($perPage);
            $detalhes = collect($detalhesPage->items());
            $detalheIds = $detalhes->pluck('detalhe_id')->map(fn ($v) => (int) $v)->all();

            $itensByDetalhe = [];
            if ($detalheIds !== []) {
                $itensRows = \Illuminate\Support\Facades\DB::table('DETALHE_ESCALA_ITEM as dei')
                    ->leftJoin('TURNO as t', 't.TURNO_ID', '=', 'dei.TURNO_ID')
                    ->whereIn('dei.DETALHE_ESCALA_ID', $detalheIds)
                    ->select(
                        'dei.DETALHE_ESCALA_ID as detalhe_id',
                        'dei.DETALHE_ESCALA_ITEM_ID as item_id',
                        'dei.DETALHE_ESCALA_ITEM_DATA as data',
                        'dei.TURNO_ID as turno_id',
                        't.TURNO_SIGLA as turno_sigla'
                    )
                    ->orderBy('dei.DETALHE_ESCALA_ITEM_DATA')
                    ->get();
                foreach ($itensRows as $it) {
                    $key = (int) $it->detalhe_id;
                    if (! isset($itensByDetalhe[$key])) {
                        $itensByDetalhe[$key] = [];
                    }
                    $itensByDetalhe[$key][] = $it;
                }
            }

            $funcionarios = $detalhes->map(function ($d) use ($itensByDetalhe) {
                $trilha = array_values(array_filter([
                    $d->unidade_nome ?? null,
                    $d->setor_pai_nome ?? null,
                    $d->setor_nome ?? null,
                ]));
                return [
                    'detalhe_id' => (int) $d->detalhe_id,
                    'funcionario_id' => (int) $d->funcionario_id,
                    'nome' => $d->nome ?? 'Funcionário',
                    'cargo' => $d->cargo ?? '',
                    'lotacao_trilha' => $trilha,
                    'lotacao_breadcrumb' => $trilha ? implode(' > ', $trilha) : 'Sem lotação',
                    'itens' => $itensByDetalhe[(int) $d->detalhe_id] ?? [],
                ];
            })->values();

            return response()->json([
                'escala' => [
                    'escala_id' => $escala->ESCALA_ID,
                    'competencia' => $escala->ESCALA_COMPETENCIA,
                    'ano' => $ano,
                    'mes' => $mes,
                ],
                'funcionarios' => $funcionarios,
                'paginacao' => [
                    'page' => $detalhesPage->currentPage(),
                    'per_page' => $detalhesPage->perPage(),
                    'total' => $detalhesPage->total(),
                    'last_page' => $detalhesPage->lastPage(),
                ],
                'feriados' => [],
            ]);
        } catch (\Throwable $e) {
            return response()->json(['erro' => $e->getMessage()], 500);
        }
    });
