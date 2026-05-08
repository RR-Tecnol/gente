<?php
// ORGANOGRAMA CRUD DE SETORES
// Extraido de web.php - herda prefix api/v3 + auth do grupo principal

if (!function_exists('_org_encerrar_lotacoes_ativas')) {
    function _org_encerrar_lotacoes_ativas(int $funcionarioId, ?string $observacao = null): void
    {
        $q = \Illuminate\Support\Facades\DB::table('LOTACAO')
            ->where('FUNCIONARIO_ID', $funcionarioId)
            ->whereNull('LOTACAO_DATA_FIM');

        $payload = ['LOTACAO_DATA_FIM' => now()->toDateString()];
        if ($observacao && \Illuminate\Support\Facades\Schema::hasColumn('LOTACAO', 'LOTACAO_OBSERVACAO')) {
            $payload['LOTACAO_OBSERVACAO'] = $observacao;
        }

        $q->update($payload);
    }
}

// GET /api/v3/organograma  Lista setores agrupados por unidade
Route::get('/organograma', function (\Illuminate\Http\Request $request) {
    try {
        $usuario = auth()->user();
        $permitidos = \App\Support\UnidadeEscopoUsuario::setorIdsPermitidos($usuario, $request);
        if ($permitidos === []) {
            return response()->json([
                'unidades' => [],
                'setores_flat' => [],
                'unidades_flat' => [],
                'stats' => ['servidores_lotados_ativos' => 0, 'servidores_ativos_total' => 0, 'servidores_em_limbo' => 0],
                'fallback' => false,
                'erro' => 'Sem unidade vinculada (USUARIO_UNIDADE) ou fora do escopo. Use visão global (Sudo) com cabeçalho '.\App\Support\GenteSudoGlobalView::headerName().' se estiver na whitelist.',
            ], 403);
        }
        $hoje = now()->toDateString();
        $setoresEmUso = \Illuminate\Support\Facades\DB::table('LOTACAO as l')
            ->join('FUNCIONARIO as f', 'f.FUNCIONARIO_ID', '=', 'l.FUNCIONARIO_ID')
            ->when(\Illuminate\Support\Facades\Schema::hasColumn('LOTACAO', 'LOTACAO_DATA_FIM'), function ($q) use ($hoje) {
                $q->where(function ($w) use ($hoje) {
                    $w->whereNull('l.LOTACAO_DATA_FIM')
                        ->orWhere('l.LOTACAO_DATA_FIM', '>', $hoje);
                });
            })
            ->when(\Illuminate\Support\Facades\Schema::hasColumn('FUNCIONARIO', 'FUNCIONARIO_DATA_FIM'), function ($q) use ($hoje) {
                $q->where(function ($w) use ($hoje) {
                    $w->whereNull('f.FUNCIONARIO_DATA_FIM')
                        ->orWhere('f.FUNCIONARIO_DATA_FIM', '>', $hoje);
                });
            })
            ->when($permitidos !== null, fn ($q) => $q->whereIn('l.SETOR_ID', $permitidos))
            ->distinct()
            ->pluck('l.SETOR_ID')
            ->map(fn ($v) => (int) $v)
            ->filter(fn ($id) => $id > 0)
            ->values()
            ->all();
        $setores = \Illuminate\Support\Facades\DB::table('SETOR')
            ->when(\Illuminate\Support\Facades\Schema::hasColumn('SETOR', 'SETOR_ATIVO'), function ($q) use ($setoresEmUso) {
                $q->where(function ($w) use ($setoresEmUso) {
                    $w->where('SETOR_ATIVO', 1);
                    if ($setoresEmUso !== []) {
                        $w->orWhereIn('SETOR_ID', $setoresEmUso);
                    }
                });
            })
            ->when($permitidos !== null, function ($q) use ($permitidos, $setoresEmUso) {
                $pool = array_values(array_unique(array_merge($permitidos, $setoresEmUso)));
                $q->whereIn('SETOR_ID', $pool);
            })
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
            ->when(\Illuminate\Support\Facades\Schema::hasColumn('LOTACAO', 'LOTACAO_DATA_FIM'), function ($q) use ($hoje) {
                $q->where(function ($w) use ($hoje) {
                    $w->whereNull('l.LOTACAO_DATA_FIM')
                        ->orWhere('l.LOTACAO_DATA_FIM', '>', $hoje);
                });
            })
            ->when(\Illuminate\Support\Facades\Schema::hasColumn('FUNCIONARIO', 'FUNCIONARIO_DATA_FIM'), function ($q) use ($hoje) {
                $q->where(function ($w) use ($hoje) {
                    $w->whereNull('f.FUNCIONARIO_DATA_FIM')
                        ->orWhere('f.FUNCIONARIO_DATA_FIM', '>', $hoje);
                });
            })
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
        $servidoresLotadosAtivos = (int) $funcRows->pluck('FUNCIONARIO_ID')->filter()->unique()->count();
        if ($permitidos === null) {
            try {
                $servidoresAtivosTotal = (int) \Illuminate\Support\Facades\DB::table('FUNCIONARIO')
                    ->whereNull('FUNCIONARIO_DATA_FIM')
                    ->count();
            } catch (\Throwable $e) {
                $servidoresAtivosTotal = $servidoresLotadosAtivos;
            }
            $servidoresEmLimbo = max(0, $servidoresAtivosTotal - $servidoresLotadosAtivos);
        } else {
            // Escopo hierárquico: totais alinhados ao que o gestor vê (sem inflar com o município inteiro)
            $servidoresAtivosTotal = $servidoresLotadosAtivos;
            $servidoresEmLimbo = 0;
        }

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
                    'setor_pai_id' => $s->SETOR_PAI_ID ?? null,
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
                    'setor_pai_id' => $s->SETOR_PAI_ID ?? null,
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
            'setor_pai_id' => $s->SETOR_PAI_ID ?? null,
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
            'stats' => [
                'servidores_lotados_ativos' => $servidoresLotadosAtivos,
                'servidores_ativos_total' => $servidoresAtivosTotal,
                'servidores_em_limbo' => $servidoresEmLimbo,
            ],
            'fallback' => false,
        ]);
    } catch (\Throwable $e) {
        \Illuminate\Support\Facades\Log::error('Organograma: ' . $e->getMessage());
        return response()->json(['unidades' => [], 'setores_flat' => [], 'stats' => ['servidores_lotados_ativos' => 0, 'servidores_ativos_total' => 0, 'servidores_em_limbo' => 0], 'fallback' => true]);
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

// GET /api/v3/organograma/historico  Timeline de movimentações por setor/unidade
Route::get('/organograma/historico', function (\Illuminate\Http\Request $request) {
    try {
        if (!\Illuminate\Support\Facades\Schema::hasTable('AUDIT_LOG')) {
            return response()->json(['events' => []]);
        }

        $setorId = (int) ($request->input('setor_id') ?? 0);
        $unidadeId = (int) ($request->input('unidade_id') ?? 0);
        $limit = max(10, min(150, (int) ($request->input('limit') ?? 40)));

        $auditCols = \Illuminate\Support\Facades\Schema::getColumnListing('AUDIT_LOG');
        $auditIdCol = in_array('AUDIT_LOG_ID', $auditCols, true)
            ? 'AUDIT_LOG_ID'
            : (in_array('id', $auditCols, true) ? 'id' : null);
        $auditCreatedCol = in_array('created_at', $auditCols, true)
            ? 'created_at'
            : (in_array('DATA_HORA', $auditCols, true) ? 'DATA_HORA' : null);

        $q = \Illuminate\Support\Facades\DB::table('AUDIT_LOG as a')
            ->leftJoin('USUARIO as u', 'u.USUARIO_ID', '=', 'a.USUARIO_ID')
            ->where('a.ACAO', 'MOVIMENTACAO_SETOR')
            ->limit(200);

        if ($auditCreatedCol) {
            $q->orderByDesc("a.{$auditCreatedCol}");
        } elseif ($auditIdCol) {
            $q->orderByDesc("a.{$auditIdCol}");
        }

        $selects = ['a.DADOS_NOVOS', 'u.USUARIO_LOGIN', 'u.USUARIO_NOME'];
        if ($auditIdCol) {
            $selects[] = "a.{$auditIdCol} as audit_id";
        } else {
            $selects[] = \Illuminate\Support\Facades\DB::raw('0 as audit_id');
        }
        if ($auditCreatedCol) {
            $selects[] = "a.{$auditCreatedCol} as audit_created_at";
        } else {
            $selects[] = \Illuminate\Support\Facades\DB::raw('NULL as audit_created_at');
        }

        $rows = $q->select($selects)->get();

        $events = [];
        foreach ($rows as $row) {
            $dados = json_decode((string) ($row->DADOS_NOVOS ?? '{}'), true);
            if (!is_array($dados)) {
                continue;
            }
            $movs = collect($dados['movimentacoes'] ?? [])->filter(fn ($m) => is_array($m))->values();
            if ($movs->isEmpty()) {
                continue;
            }

            $movsFiltrados = $movs->filter(function (array $m) use ($setorId, $unidadeId) {
                $origem = (int) ($m['setor_origem_id'] ?? 0);
                $destino = (int) ($m['setor_destino_id'] ?? 0);
                $setorOk = $setorId > 0 ? ($origem === $setorId || $destino === $setorId) : true;

                if (!$setorOk) {
                    return false;
                }
                if ($unidadeId <= 0) {
                    return true;
                }

                // Filtra por unidade via SETOR -> UNIDADE no momento da consulta.
                $setorIds = array_values(array_unique(array_filter([$origem, $destino])));
                if (empty($setorIds)) {
                    return false;
                }
                $exists = \Illuminate\Support\Facades\DB::table('SETOR')
                    ->whereIn('SETOR_ID', $setorIds)
                    ->where('UNIDADE_ID', $unidadeId)
                    ->exists();
                return (bool) $exists;
            })->values();

            if ($movsFiltrados->isEmpty()) {
                continue;
            }

            $events[] = [
                'id' => (int) ($row->audit_id ?? 0),
                'created_at' => $row->audit_created_at,
                'usuario_login' => $row->USUARIO_LOGIN,
                'usuario_nome' => $row->USUARIO_NOME,
                'justificativa' => (string) ($dados['justificativa'] ?? ''),
                'justificativa_tipo' => (string) ($dados['justificativa_tipo'] ?? ''),
                'movimentacoes' => $movsFiltrados->map(function (array $m) {
                    return [
                        'funcionario_id' => (int) ($m['funcionario_id'] ?? 0),
                        'setor_origem_id' => isset($m['setor_origem_id']) ? (int) $m['setor_origem_id'] : null,
                        'setor_destino_id' => isset($m['setor_destino_id']) ? (int) $m['setor_destino_id'] : null,
                        'movimentado_em' => $m['movimentado_em'] ?? null,
                    ];
                })->values()->all(),
            ];
        }

        return response()->json(['events' => array_slice($events, 0, $limit)]);
    } catch (\Throwable $e) {
        \Illuminate\Support\Facades\Log::warning('organograma_historico_unavailable', [
            'erro' => $e->getMessage(),
        ]);
        return response()->json(['events' => [], 'warning' => 'historico_indisponivel']);
    }
});

// POST /api/v3/organograma/setor  Criar setor
Route::post('/organograma/setor', function (\Illuminate\Http\Request $request) {
    try {
        $nome = trim($request->nome ?? '');
        $sigla = trim($request->sigla ?? '');
        $unidadeId = (int) ($request->unidade_id ?? 0);
        $unidadeNome = trim((string) ($request->unidade_nome ?? ''));
        $criarUnidade = filter_var($request->criar_unidade, FILTER_VALIDATE_BOOL);
        $funcionarioIds = collect($request->funcionario_ids ?? [])
            ->merge($request->funcionario_id ? [(int) $request->funcionario_id] : [])
            ->map(fn($id) => (int) $id)
            ->filter(fn($id) => $id > 0)
            ->unique()
            ->values()
            ->all();

        if (!$nome)
            return response()->json(['error' => 'Nome é obrigatório.'], 422);
        if ($unidadeId <= 0 && $unidadeNome === '') {
            return response()->json(['error' => 'Diretoria/Unidade é obrigatória.'], 422);
        }

        \Illuminate\Support\Facades\DB::beginTransaction();

        if ($unidadeId <= 0 && $unidadeNome !== '') {
            $unidadeNomeNorm = mb_strtoupper($unidadeNome, 'UTF-8');
            $existing = \Illuminate\Support\Facades\DB::table('UNIDADE')
                ->whereRaw("UPPER(LTRIM(RTRIM(UNIDADE_NOME))) = ?", [$unidadeNomeNorm])
                ->first(['UNIDADE_ID']);
            if ($existing) {
                $unidadeId = (int) $existing->UNIDADE_ID;
            } elseif ($criarUnidade) {
                $unidadeId = (int) \Illuminate\Support\Facades\DB::table('UNIDADE')->insertGetId([
                    'UNIDADE_NOME' => $unidadeNomeNorm,
                    'UNIDADE_SIGLA' => null,
                    'UNIDADE_ATIVA' => 1,
                    'UNIDADE_TIPO' => 0,
                ]);
            } else {
                \Illuminate\Support\Facades\DB::rollBack();
                return response()->json(['error' => 'Unidade não encontrada. Marque criação de nova unidade.'], 422);
            }
        }

        $id = \Illuminate\Support\Facades\DB::table('SETOR')->insertGetId([
            'SETOR_NOME' => $nome,
            'SETOR_SIGLA' => $sigla ?: null,
            'UNIDADE_ID' => $unidadeId,
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
            _org_encerrar_lotacoes_ativas($funcionarioId, 'ENCERRAMENTO AUTOMÁTICO POR NOVA LOTAÇÃO');
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
            'unidade_id' => $unidadeId,
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

        // Mantém unidade existente válida; não persiste UNIDADE_ID = 0 (órfão / "sem diretoria").
        $reqUnidade = (int) ($request->unidade_id ?? 0);
        $existente = (int) ($setor->UNIDADE_ID ?? 0);
        if ($reqUnidade > 0) {
            $unidadeId = $reqUnidade;
        } elseif ($existente > 0) {
            $unidadeId = $existente;
        } else {
            return response()->json(['error' => 'Diretoria/Unidade é obrigatória: informe unidade_id ou corrija o cadastro do setor.'], 422);
        }

        \Illuminate\Support\Facades\DB::beginTransaction();

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
        $movimentacaoMotivoTipo = trim((string) ($request->movimentacao_motivo_tipo ?? ''));
        $movimentacaoMotivoTexto = trim((string) ($request->movimentacao_motivo_texto ?? ''));

        $movimentacoes = [];
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
            $lotacaoAnterior = \Illuminate\Support\Facades\DB::table('LOTACAO')
                ->where('FUNCIONARIO_ID', $funcionarioId)
                ->whereNull('LOTACAO_DATA_FIM')
                ->orderByDesc('LOTACAO_ID')
                ->first(['LOTACAO_ID', 'SETOR_ID']);
            $updateAnterior = ['LOTACAO_DATA_FIM' => now()->toDateString()];
            if (\Illuminate\Support\Facades\Schema::hasColumn('LOTACAO', 'LOTACAO_OBSERVACAO')) {
                $obsAnterior = "ENCERRAMENTO POR MOVIMENTACAO_SETOR";
                if ($movimentacaoMotivoTexto !== '') {
                    $obsAnterior .= " | MOTIVO: {$movimentacaoMotivoTexto}";
                }
                $updateAnterior['LOTACAO_OBSERVACAO'] = $obsAnterior;
            }
            _org_encerrar_lotacoes_ativas($funcionarioId, $updateAnterior['LOTACAO_OBSERVACAO'] ?? null);
            $insertNova = [
                'FUNCIONARIO_ID' => $funcionarioId,
                'SETOR_ID' => $id,
                'LOTACAO_DATA_INICIO' => now()->toDateString(),
                'LOTACAO_DATA_FIM' => null,
                'VINCULO_ID' => null,
            ];
            if (\Illuminate\Support\Facades\Schema::hasColumn('LOTACAO', 'LOTACAO_OBSERVACAO')) {
                $obsNova = "INICIO POR MOVIMENTACAO_SETOR";
                if ($movimentacaoMotivoTexto !== '') {
                    $obsNova .= " | MOTIVO: {$movimentacaoMotivoTexto}";
                }
                $insertNova['LOTACAO_OBSERVACAO'] = $obsNova;
            }
            \Illuminate\Support\Facades\DB::table('LOTACAO')->insert($insertNova);
            $movimentacoes[] = [
                'funcionario_id' => (int) $funcionarioId,
                'setor_origem_id' => $lotacaoAnterior?->SETOR_ID ? (int) $lotacaoAnterior->SETOR_ID : null,
                'setor_destino_id' => (int) $id,
                'movimentado_em' => now()->toDateTimeString(),
                'justificativa' => $movimentacaoMotivoTexto !== '' ? $movimentacaoMotivoTexto : null,
            ];
        }

        if (!empty($movimentacoes) && \Illuminate\Support\Facades\Schema::hasTable('AUDIT_LOG')) {
            try {
                $auditCols = \Illuminate\Support\Facades\Schema::getColumnListing('AUDIT_LOG');
                $payload = [
                    'ACAO' => 'MOVIMENTACAO_SETOR',
                    'TABELA' => 'LOTACAO',
                    'DADOS_NOVOS' => json_encode([
                        'setor_id' => (int) $id,
                        'movimentacoes' => $movimentacoes,
                        'justificativa' => $movimentacaoMotivoTexto !== '' ? $movimentacaoMotivoTexto : null,
                        'justificativa_tipo' => $movimentacaoMotivoTipo !== '' ? $movimentacaoMotivoTipo : null,
                        '__audit' => [
                            'event_type' => 'MOVIMENTACAO_SETOR',
                            'priority' => 'alta',
                            'context' => 'Realocação de servidor entre setores',
                        ],
                    ], JSON_UNESCAPED_UNICODE),
                ];
                if (in_array('USUARIO_ID', $auditCols, true)) {
                    $payload['USUARIO_ID'] = auth()->id();
                }
                if (in_array('IP', $auditCols, true)) {
                    $payload['IP'] = $request->ip();
                }
                if (in_array('USER_AGENT', $auditCols, true)) {
                    $payload['USER_AGENT'] = substr((string) $request->userAgent(), 0, 255);
                }
                if (in_array('created_at', $auditCols, true)) {
                    $payload['created_at'] = now();
                }
                if (in_array('updated_at', $auditCols, true)) {
                    $payload['updated_at'] = now();
                }
                \App\Support\GenteAuditWriter::insertChainedRow($payload);
            } catch (\Throwable $e) {
                \Illuminate\Support\Facades\Log::warning('Falha ao registrar MOVIMENTACAO_SETOR no AUDIT_LOG', ['erro' => $e->getMessage()]);
            }
        }

        \Illuminate\Support\Facades\DB::commit();

        $delivery = [
            'generated' => 0,
            'emailed' => 0,
            'failed' => 0,
            'errors' => [],
        ];

        foreach ($movimentacoes as $mov) {
            $funcionarioId = (int) ($mov['funcionario_id'] ?? 0);
            if ($funcionarioId <= 0) {
                continue;
            }
            try {
                $dadosPortaria = \App\Support\PortariaLotacaoService::buildData($funcionarioId);
                if (! $dadosPortaria) {
                    throw new \RuntimeException('Dados da portaria não encontrados.');
                }
                $dadosPortaria = \App\Support\PortariaLotacaoService::decorateWithAuthenticity(
                    $dadosPortaria,
                    auth()->id() ? (int) auth()->id() : null
                );
                $pdfBinary = \App\Support\PortariaLotacaoService::renderPdfBinary($dadosPortaria);
                $path = \App\Support\PortariaLotacaoService::storePdf($funcionarioId, $pdfBinary);
                \App\Support\PortariaLotacaoService::persistDossie($funcionarioId, $path, $dadosPortaria, 'pendente', null);
                $delivery['generated']++;

                $email = \App\Support\PortariaLotacaoService::resolveEmailInstitucional($funcionarioId);
                if ($email) {
                    \Illuminate\Support\Facades\Mail::to($email)->send(
                        new \App\Mail\MoveServidorNotification(
                            $dadosPortaria,
                            $pdfBinary,
                            basename($path)
                        )
                    );
                    \App\Support\PortariaLotacaoService::updateDossieEmailStatusByPath($path, 'enviado', null);
                    $delivery['emailed']++;
                } else {
                    \App\Support\PortariaLotacaoService::updateDossieEmailStatusByPath($path, 'sem_email', 'Servidor sem e-mail institucional cadastrado.');
                }
            } catch (\Throwable $e) {
                $delivery['failed']++;
                $delivery['errors'][] = [
                    'funcionario_id' => $funcionarioId,
                    'erro' => $e->getMessage(),
                ];
                \Illuminate\Support\Facades\Log::warning('Falha ao gerar/enviar portaria automática', [
                    'funcionario_id' => $funcionarioId,
                    'erro' => $e->getMessage(),
                ]);
            }
        }

        return response()->json([
            'message' => 'Setor atualizado!',
            'id' => (int) $id,
            'funcionario_ids' => $funcionarioIds,
            'movimentacoes' => $movimentacoes,
            'justificativa' => $movimentacaoMotivoTexto !== '' ? $movimentacaoMotivoTexto : null,
            'justificativa_tipo' => $movimentacaoMotivoTipo !== '' ? $movimentacaoMotivoTipo : null,
            'document_delivery' => $delivery,
        ]);
    } catch (\Throwable $e) {
        \Illuminate\Support\Facades\DB::rollBack();
        return response()->json(['error' => 'Erro ao editar setor: ' . $e->getMessage()], 500);
    }
});

// GET /api/v3/servidor/portaria-lotacao/{id} — Gerar PDF da portaria de movimentação
Route::get('/servidor/portaria-lotacao/{id}', function ($id) {
    try {
        $funcionarioId = (int) $id;
        if ($funcionarioId <= 0) {
            return response()->json(['erro' => 'ID de servidor inválido.'], 422);
        }

        $dados = \App\Support\PortariaLotacaoService::buildData($funcionarioId);
        if (! $dados) {
            return response()->json(['erro' => 'Servidor não encontrado.'], 404);
        }
        $dados = \App\Support\PortariaLotacaoService::decorateWithAuthenticity(
            $dados,
            auth()->id() ? (int) auth()->id() : null
        );
        $pdfBinary = \App\Support\PortariaLotacaoService::renderPdfBinary($dados);
        return response($pdfBinary, 200, [
            'Content-Type' => 'application/pdf',
            'Content-Disposition' => 'attachment; filename="portaria-lotacao-' . $funcionarioId . '.pdf"',
        ]);
    } catch (\Throwable $e) {
        return response()->json(['erro' => $e->getMessage()], 500);
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
