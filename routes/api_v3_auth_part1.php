<?php
// gerado — não editar cegamente (regen_api_v3_fachada.py)

    // â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€
    // ARQ-01: mÃ³dulos extraÃ­dos do web.php (rotas /funcionarios, /ponto, /folhas)
    // â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€
    require __DIR__ . '/funcionarios.php';
    require __DIR__ . '/folha.php';

// Autocadastro gestão
Route::get('/autocadastro/pendentes', function () {
    $pendentes = \Illuminate\Support\Facades\DB::table('AUTOCADASTRO_TOKEN')
        ->orderByDesc('created_at')->get()
        ->map(function ($t) {
            if (is_string($t->TOKEN_DADOS) && $t->TOKEN_DADOS !== '') {
                $json = json_decode($t->TOKEN_DADOS, true);
                $t->TOKEN_DADOS = is_array($json) ? $json : null;
            }
            return $t;
        });
    return response()->json(['pendentes' => $pendentes]);
});
Route::post('/autocadastro/gerar-link', function (\Illuminate\Http\Request $request) {
    $token = \Illuminate\Support\Str::uuid();
    $validadeDias = max(1, min((int) ($request->validade_dias ?? 7), 30));
    \Illuminate\Support\Facades\DB::table('AUTOCADASTRO_TOKEN')->insert([
        'TOKEN' => (string) $token,
        'TOKEN_STATUS' => 'pendente',
        'TOKEN_NOME' => $request->nome,
        'TOKEN_EMAIL' => $request->email,
        'expira_em' => now()->addDays($validadeDias),
        'created_at' => now(),
        'updated_at' => now(),
    ]);
    return response()->json([
        'url' => url("/autocadastro/{$token}"),
        'token' => (string) $token,
        'expira_em' => now()->addDays($validadeDias)->toDateString(),
    ]);
});
// Escalas médicas
Route::post('/escalas', function (\Illuminate\Http\Request $request) {
    try {
        $hoje = now()->toDateString();
        $mes = (int) ($request->mes ?? 0);
        $ano = (int) ($request->ano ?? 0);
        $competencia = $request->competencia;
        if (!$competencia && $mes >= 1 && $mes <= 12 && $ano >= 2000) {
            $competencia = sprintf('%04d-%02d', $ano, $mes);
        }
        if (!$competencia) {
            return response()->json(['erro' => 'Competência inválida.'], 422);
        }

        $setorId = $request->setor_id ?: \Illuminate\Support\Facades\DB::table('SETOR')
            ->when(
                \Illuminate\Support\Facades\Schema::hasColumn('SETOR', 'SETOR_ATIVO'),
                fn($q) => $q->where('SETOR_ATIVO', 1)
            )
            ->value('SETOR_ID');
        if (!$setorId) {
            return response()->json(['erro' => 'Nenhum setor disponível para criar escala.'], 422);
        }

        $escalaInsert = [
            'SETOR_ID' => $setorId,
            'ESCALA_COMPETENCIA' => $competencia,
        ];
        if (\Illuminate\Support\Facades\Schema::hasColumn('ESCALA', 'ESCALA_SITUACAO')) {
            $escalaInsert['ESCALA_SITUACAO'] = 'rascunho';
        }
        if (\Illuminate\Support\Facades\Schema::hasColumn('ESCALA', 'ESCALA_STATUS')) {
            $escalaInsert['ESCALA_STATUS'] = \App\Domain\Escala\EscalaWorkflowStatus::RASCUNHO;
        }
        if (\Illuminate\Support\Facades\Schema::hasColumn('ESCALA', 'ESCALA_ATIVO')) {
            $escalaInsert['ESCALA_ATIVO'] = 1;
        }
        if (\Illuminate\Support\Facades\Schema::hasColumn('ESCALA', 'ESCALA_DESCRICAO')) {
            $escalaInsert['ESCALA_DESCRICAO'] = "Escala {$competencia}";
        }
        if (\Illuminate\Support\Facades\Schema::hasColumn('ESCALA', 'created_at')) {
            $escalaInsert['created_at'] = now();
        }
        if (\Illuminate\Support\Facades\Schema::hasColumn('ESCALA', 'updated_at')) {
            $escalaInsert['updated_at'] = now();
        }

        $id = \Illuminate\Support\Facades\DB::table('ESCALA')->insertGetId($escalaInsert);

        $funcionariosSetor = \Illuminate\Support\Facades\DB::table('LOTACAO as l')
            ->join('FUNCIONARIO as f', 'f.FUNCIONARIO_ID', '=', 'l.FUNCIONARIO_ID')
            ->when(
                \Illuminate\Support\Facades\Schema::hasColumn('LOTACAO', 'LOTACAO_DATA_FIM'),
                fn($q) => $q->whereNull('l.LOTACAO_DATA_FIM')
            )
            ->when(
                \Illuminate\Support\Facades\Schema::hasColumn('FUNCIONARIO', 'FUNCIONARIO_DATA_FIM'),
                fn($q) => $q->where(function ($w) use ($hoje) {
                    $w->whereNull('f.FUNCIONARIO_DATA_FIM')
                        ->orWhere('f.FUNCIONARIO_DATA_FIM', '>', $hoje);
                })
            )
            ->where('l.SETOR_ID', $setorId)
            ->select('f.FUNCIONARIO_ID')
            ->distinct()
            ->limit(40)
            ->pluck('f.FUNCIONARIO_ID');

        if ($funcionariosSetor->isEmpty()) {
            $funcionariosSetor = \Illuminate\Support\Facades\DB::table('FUNCIONARIO as f')
                ->when(
                    \Illuminate\Support\Facades\Schema::hasColumn('FUNCIONARIO', 'FUNCIONARIO_DATA_FIM'),
                    fn($q) => $q->where(function ($w) use ($hoje) {
                        $w->whereNull('f.FUNCIONARIO_DATA_FIM')
                            ->orWhere('f.FUNCIONARIO_DATA_FIM', '>', $hoje);
                    })
                )
                ->orderBy('f.FUNCIONARIO_ID')
                ->limit(20)
                ->pluck('f.FUNCIONARIO_ID');
        }

        foreach ($funcionariosSetor as $funcionarioId) {
            \Illuminate\Support\Facades\DB::table('DETALHE_ESCALA')->updateOrInsert(
                ['ESCALA_ID' => $id, 'FUNCIONARIO_ID' => $funcionarioId],
                \Illuminate\Support\Facades\Schema::hasColumn('DETALHE_ESCALA', 'updated_at') ? ['updated_at' => now()] : []
            );
        }

        return response()->json(['ok' => true, 'escala_id' => $id, 'competencia' => $competencia], 201);
    } catch (\Throwable $e) { return response()->json(['erro' => $e->getMessage()], 500); }
});
// Setores
Route::get('/setores', function () {
    try {
        $setores = \Illuminate\Support\Facades\DB::table('SETOR')->where('SETOR_ATIVO', 1)
            ->orderBy('SETOR_NOME')->select('SETOR_ID as id', 'SETOR_NOME as nome')->get();
        return response()->json(['setores' => $setores]);
    } catch (\Throwable $e) { return response()->json(['setores' => []]); }
});

    require __DIR__ . '/motor.php'; // Sprint 3 — endpoints Motor de Folha (vínculos, rubricas, adicionais)

    // GET /api/v3/escalas â€” Lista escalas para o seletor da MatrizEscalaView
    Route::get('/escalas', function (\Illuminate\Http\Request $request) {
        $escalas = App\Models\Escala::with(['setor'])
            ->orderBy('ESCALA_COMPETENCIA', 'desc')
            ->limit(60)
            ->get()
            ->map(fn($e) => [
                'ESCALA_ID' => $e->ESCALA_ID,
                'ESCALA_COMPETENCIA' => $e->ESCALA_COMPETENCIA,
                'setor' => $e->setor?->SETOR_NOME ?? 'â€”',
                'situacao' => $e->ESCALA_SITUACAO ?? null,
            ]);
        return response()->json($escalas);
    });

    // GET /api/v3/escalas/{id} â€” Grade completa de uma escala
    Route::get('/escalas/{id}', function ($id) {
        if (!is_numeric($id)) return response()->json(['erro' => 'ID inválido'], 422); $id = (int) $id;
        $escala = App\Models\Escala::with([
            'setor',
            'detalheEscalas.funcionario.pessoa',
            'detalheEscalas.detalheEscalaItens.turno',
            'detalheEscalas.atribuicao',
        ])->findOrFail($id);

        // Calcula ano/mÃªs da competÃªncia (formato "MM/YYYY")
        [$mes, $ano] = explode('/', $escala->ESCALA_COMPETENCIA . '/2026');
        $ano = (int) ($ano ?? 2026);
        $mes = (int) ($mes ?? 1) - 1; // 0-index para o Vue

        // Feriados do mÃªs
        $mesAno = \Carbon\Carbon::createFromDate($ano, $mes + 1, 1)->format('Y-m-d');
        $feriados = App\Models\Feriado::buscarFeriadoMesAno($mesAno)
            ->map(fn($f) => ['data' => $f->FERIADO_DATA])->values();

        $funcionarios = $escala->detalheEscalas->map(function ($de) {
            return [
                'detalhe_id' => $de->DETALHE_ESCALA_ID,
                'funcionario_id' => $de->FUNCIONARIO_ID,
                'nome' => $de->funcionario?->pessoa?->PESSOA_NOME ?? 'FuncionÃ¡rio',
                'cargo' => $de->atribuicao?->ATRIBUICAO_NOME ?? 'Servidor',
                'itens' => $de->detalheEscalaItens->map(fn($i) => [
                    'turno_id' => $i->TURNO_ID,
                    'turno_sigla' => $i->turno?->TURNO_SIGLA,
                    'data' => $i->DETALHE_ESCALA_ITEM_DATA,
                ])->values(),
            ];
        })->values();

        return response()->json([
            'escala' => [
                'id' => $escala->ESCALA_ID,
                'competencia' => $escala->ESCALA_COMPETENCIA,
                'setor' => $escala->setor?->SETOR_NOME,
                'ano' => $ano,
                'mes' => $mes,
            ],
            'funcionarios' => $funcionarios,
            'feriados' => $feriados,
        ]);
    });

    // â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€
    // MÃ³dulos externos (cada arquivo herda prefix/middleware do grupo)
    // â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€
    require __DIR__ . '/esocial.php';
    require __DIR__ . '/consignacao.php';
    require __DIR__ . '/diarias.php';
    require __DIR__ . '/rpps.php';
    require __DIR__ . '/exoneracao.php';
    require __DIR__ . '/hora_extra.php';
    require __DIR__ . '/verba_indenizatoria.php';
    require __DIR__ . '/pesquisa.php';
    require __DIR__ . '/ouvidoria_admin.php';
    require __DIR__ . '/relatorios.php';
    require __DIR__ . '/estagiarios.php';
    // Sprint 6 â€” novos mÃ³dulos
    require __DIR__ . '/acumulacao.php';
    require __DIR__ . '/transparencia.php';
    require __DIR__ . '/pss.php';
    require __DIR__ . '/terceirizados.php';
    require __DIR__ . '/sagres.php';
    // Sprint 5 â€” banco de horas e atestados
    require __DIR__ . '/banco_horas.php';
    // require __DIR__ . '/atestados.php';
    require __DIR__ . '/progressao_funcional.php';
    require __DIR__ . '/afastamentos_v3.php';
    require __DIR__ . '/parametros_financeiros_v3.php';
    require __DIR__ . '/turnos_v3.php';
    require __DIR__ . '/feriados_v3.php';
    require __DIR__ . '/tabelas_auxiliares.php';
    require __DIR__ . '/eventos_folha_v3.php';
    // Refatoracao 30/03/2026 - blocos extraidos do web.php
    require __DIR__ . '/cargos_salarios.php';
    require __DIR__ . '/ferias_v3.php';
    require __DIR__ . '/comunicados.php';
    require __DIR__ . '/meu_perfil.php';
    require __DIR__ . '/ponto_eletronico.php';
    require __DIR__ . '/plantoes_sobreaviso.php';
    require __DIR__ . '/atestados_v3.php';
    require __DIR__ . '/contratos_v3.php';
    require __DIR__ . '/medicina.php';
    require __DIR__ . '/declaracoes.php';
    require __DIR__ . '/ouvidoria.php';
    require __DIR__ . '/gestor.php';
    require __DIR__ . '/organograma_v3.php';
    require __DIR__ . '/beneficios.php';
    require __DIR__ . '/medicina_admin.php';
    require __DIR__ . '/seguranca_trabalho.php';
    require __DIR__ . '/treinamentos.php';
    require __DIR__ . '/consignatarias.php'; // Bloco B — Gestão de Consignatárias
    require __DIR__ . '/compras.php'; // Bloco D — ERP Administrativo
    require __DIR__ . '/almoxarifado.php';
    require __DIR__ . '/patrimonio.php';
    require __DIR__ . '/contratos_admin.php'; // Bloco D4
    require __DIR__ . '/frotas.php'; // Bloco D5
    require __DIR__ . '/escala_saude.php'; // GAP-ESCALA-SAUDE — furos de cobertura
    require __DIR__ . '/dashboard_operacional_v3.php'; // Fase 9A — painel executivo (KPIs)
    require __DIR__ . '/decimo_terceiro.php'; // GAP-13 — 13º Salário
    require __DIR__ . '/quadro_vagas.php'; // GAP-QV — Quadro de Vagas
    require __DIR__ . '/simulador_folha.php'; // GAP-SIM + GAP-LRF
    require __DIR__ . '/caged.php'; // GAP-CAG — CAGED MTE
    require __DIR__ . '/sefip.php'; // GAP-GFP — SEFIP/GFIP CEF
    require __DIR__ . '/dirf.php'; // GAP-DIR — DIRF Receita Federal
    require __DIR__ . '/rais.php'; // GAP-RAS — RAIS MTE
    require __DIR__ . '/siconfi.php'; // GAP-SIC — SICONFI STN/LRF
    require __DIR__ . '/ponto_terceirizado.php'; // GAP-PONT — Ponto Terceirizados
    require __DIR__ . '/escala_trabalho.php';       // Escala de Trabalho
    require __DIR__ . '/autocadastro_admin.php';    // Autocadastro Gestão
    require __DIR__ . '/avaliacao_desempenho.php'; // Avaliação de Desempenho
    require __DIR__ . '/orcamento.php';          // ERP-1 — Orçamento Público
    require __DIR__ . '/execucao_despesa.php';   // ERP-2 — Execução da Despesa
    require __DIR__ . '/contabilidade.php';      // ERP-3 — Contabilidade PCASP
    require __DIR__ . '/tesouraria.php';         // ERP-4 — Tesouraria
    require __DIR__ . '/receita_municipal.php';  // ERP-5 — Receita Municipal
    require __DIR__ . '/controle_externo.php';   // ERP-6 — Controle Externo SAGRES/SICONFI
    require __DIR__ . '/cnab.php';               // Integração Remessa CNAB 240

    // GAP-OSS — Monitor OSS (admin-only, mock PoC)
    Route::middleware('perfil:Administrador')->group(function () {
        require __DIR__ . '/oss.php';
    });



    // â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€
    // Notificações (persistentes via tabela NOTIFICACAO quando disponível)
    // â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€
    Route::get('/notificacoes', function () {
        $user = \Illuminate\Support\Facades\Auth::user();
        if (!\Illuminate\Support\Facades\Schema::hasTable('NOTIFICACAO')) {
            return response()->json(['notificacoes' => [], 'nao_lidas' => 0]);
        }
        // Gatilho de domínio: se houver horas de falta na competência atual, cria alerta in-app
        // (idempotente por usuário + competência).
        try {
            $funcionarioId = \Illuminate\Support\Facades\DB::table('FUNCIONARIO')
                ->where('USUARIO_ID', $user->USUARIO_ID ?? 0)
                ->value('FUNCIONARIO_ID');
            if ($funcionarioId && \Illuminate\Support\Facades\Schema::hasTable('APURACAO_PONTO')) {
                $comp = now()->format('Y-m');
                $apuracaoMes = \Illuminate\Support\Facades\DB::table('APURACAO_PONTO')
                    ->where('FUNCIONARIO_ID', (int) $funcionarioId)
                    ->where('APURACAO_COMPETENCIA', $comp)
                    ->first();
                $horasFalta = (float) ($apuracaoMes->APURACAO_HORAS_FALTA ?? 0);
                if ($horasFalta > 0) {
                    $url = '/banco-horas?competencia=' . $comp;
                    $jaExiste = \Illuminate\Support\Facades\DB::table('NOTIFICACAO')
                        ->where('USUARIO_ID', $user->USUARIO_ID ?? 0)
                        ->where('NOTIFICACAO_TIPO', 'ponto_horas_negativas')
                        ->where('NOTIFICACAO_URL', $url)
                        ->exists();
                    if (!$jaExiste) {
                        \Illuminate\Support\Facades\DB::table('NOTIFICACAO')->insert([
                            'USUARIO_ID' => (int) ($user->USUARIO_ID ?? 0),
                            'NOTIFICACAO_TITULO' => 'Horas negativas detectadas',
                            'NOTIFICACAO_BODY' => 'Você possui ' . number_format($horasFalta, 2, ',', '.') . 'h de falta na competência ' . $comp . '. Verifique banco de horas e opções de compensação.',
                            'NOTIFICACAO_TIPO' => 'ponto_horas_negativas',
                            'NOTIFICACAO_ICONE' => '⚠️',
                            'NOTIFICACAO_URL' => $url,
                            'NOTIFICACAO_LIDA' => 0,
                            'NOTIFICACAO_DT_CRIACAO' => now(),
                        ]);
                    }
                }
            }
        } catch (\Throwable $e) {
            \Illuminate\Support\Facades\Log::warning('Falha ao gerar notificação automática de horas negativas: ' . $e->getMessage());
        }

        $rows = \Illuminate\Support\Facades\DB::table('NOTIFICACAO')
            ->where('USUARIO_ID', $user->USUARIO_ID ?? 0)
            ->orderByDesc('NOTIFICACAO_DT_CRIACAO')
            ->limit(50)
            ->get();
        $notificacoes = $rows->map(fn($n) => [
            'id' => $n->NOTIFICACAO_ID,
            'titulo' => $n->NOTIFICACAO_TITULO,
            'body' => $n->NOTIFICACAO_BODY,
            'tipo' => $n->NOTIFICACAO_TIPO ?? 'info',
            'icone' => $n->NOTIFICACAO_ICONE ?? '🔔',
            'url' => $n->NOTIFICACAO_URL,
            'lida' => (int) ($n->NOTIFICACAO_LIDA ?? 0) === 1,
            'criada_em' => $n->NOTIFICACAO_DT_CRIACAO,
        ]);
        return response()->json([
            'notificacoes' => $notificacoes,
            'nao_lidas' => $notificacoes->where('lida', false)->count(),
        ]);
    });
    $marcarNotifLida = function ($id) {
        $user = \Illuminate\Support\Facades\Auth::user();
        if (!\Illuminate\Support\Facades\Schema::hasTable('NOTIFICACAO')) {
            return response()->json(['ok' => true, 'id' => (int) $id, 'fallback' => true]);
        }
        $updated = \Illuminate\Support\Facades\DB::table('NOTIFICACAO')
            ->where('NOTIFICACAO_ID', (int) $id)
            ->where('USUARIO_ID', $user->USUARIO_ID ?? 0)
            ->update([
                'NOTIFICACAO_LIDA' => 1,
                'NOTIFICACAO_DT_LEITURA' => now(),
            ]);
        if (!$updated) {
            return response()->json(['erro' => 'Notificação não encontrada.'], 404);
        }
        return response()->json(['ok' => true, 'id' => (int) $id]);
    };
    $marcarNotifTodasLidas = function () {
        $user = \Illuminate\Support\Facades\Auth::user();
        if (!\Illuminate\Support\Facades\Schema::hasTable('NOTIFICACAO')) {
            return response()->json(['ok' => true, 'fallback' => true]);
        }
        \Illuminate\Support\Facades\DB::table('NOTIFICACAO')
            ->where('USUARIO_ID', $user->USUARIO_ID ?? 0)
            ->where(function ($q) {
                $q->whereNull('NOTIFICACAO_LIDA')->orWhere('NOTIFICACAO_LIDA', 0);
            })
            ->update([
                'NOTIFICACAO_LIDA' => 1,
                'NOTIFICACAO_DT_LEITURA' => now(),
            ]);
        return response()->json(['ok' => true]);
    };
    Route::post('/notificacoes/{id}/ler', $marcarNotifLida);
    Route::put('/notificacoes/{id}/lida', $marcarNotifLida);
    Route::post('/notificacoes/ler-todas', $marcarNotifTodasLidas);
    Route::put('/notificacoes/lidas', $marcarNotifTodasLidas);

    // â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€
    // GAP-03: Endpoint centralizado de busca de servidor
    // Todas as views devem usar /servidores/buscar (nÃ£o /exoneracao/buscar)
    // â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€
    Route::get('/servidores/buscar', function (\Illuminate\Http\Request $request) {
        $hoje = now()->toDateString();
        $q = $request->q ?? '';
        return response()->json([
            'servidores' => \Illuminate\Support\Facades\DB::table('FUNCIONARIO as f')
                ->join('PESSOA as p', 'p.PESSOA_ID', '=', 'f.PESSOA_ID')
                ->leftJoin('CARGO as c', 'c.CARGO_ID', '=', 'f.CARGO_ID')
                ->leftJoin('LOTACAO as l', function ($j) {
                    $j->on('l.FUNCIONARIO_ID', '=', 'f.FUNCIONARIO_ID')
                        ->whereNull('l.LOTACAO_DATA_FIM');
                })
                ->leftJoin('SETOR as s', 's.SETOR_ID', '=', 'l.SETOR_ID')
                ->leftJoin('UNIDADE as u', 'u.UNIDADE_ID', '=', 's.UNIDADE_ID')
                ->where(function ($w) use ($hoje) {
                    $w->whereNull('f.FUNCIONARIO_DATA_FIM')
                        ->orWhere('f.FUNCIONARIO_DATA_FIM', '>', $hoje);
                })
                ->where(function ($w) use ($q) {
                    $w->where('p.PESSOA_NOME', 'like', "%$q%")
                        ->orWhere('f.FUNCIONARIO_MATRICULA', 'like', "%$q%");
                })
                ->select(
                    'f.FUNCIONARIO_ID as id',
                    'p.PESSOA_NOME as nome',
                    'f.FUNCIONARIO_MATRICULA as matricula',
                    'f.FUNCIONARIO_DATA_INICIO as admissao',
                    'f.FUNCIONARIO_REGIME_PREV as regime_prev',
                    'c.CARGO_NOME as cargo',
                    'c.CARGO_SALARIO',
                    'f.CARGO_ID',
                    'f.CARREIRA_ID',
                    'f.FUNCIONARIO_CLASSE',
                    'f.FUNCIONARIO_REFERENCIA',
                    's.SETOR_NOME as setor',
                    'u.UNIDADE_NOME as secretaria',
                    'u.UNIDADE_ID as unidade_id'
                )
                ->limit(15)->get()
        ]);
    });

    // â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€
    // GAP-04: Lookup de secretarias (unidades ativas)
    // Usado por FolhaPagamentoView e outros mÃ³dulos
    // â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€
    Route::get('/secretarias', function () {
        return response()->json([
            'unidades' => \Illuminate\Support\Facades\DB::table('UNIDADE')
                ->where('UNIDADE_ATIVA', 1)
                ->orderBy('UNIDADE_NOME')
                ->get(['UNIDADE_ID', 'UNIDADE_NOME'])
        ]);
    });

    // â”€â”€ GAP-07: Holerite em PDF (print-view HTML â€” DomPDF bloqueado por conflito PHP 8.1)
    // Rota pÃºblica dentro do grupo auth que retorna HTML com @media print otimizado
    // O frontend Vue chama window.open('/api/v3/holerite-pdf/{id}') e aciona window.print()
    Route::get('/holerite-pdf/{detalheId}', function ($detalheId) {
        try {
            $detalhe = DB::table('DETALHE_FOLHA as df')
                ->join('FOLHA as fl', 'fl.FOLHA_ID', '=', 'df.FOLHA_ID')
                ->join('FUNCIONARIO as f', 'f.FUNCIONARIO_ID', '=', 'df.FUNCIONARIO_ID')
                ->join('PESSOA as p', 'p.PESSOA_ID', '=', 'f.PESSOA_ID')
                ->leftJoin('CARGO as c', 'c.CARGO_ID', '=', 'f.CARGO_ID')
                ->leftJoin('LOTACAO as l', function ($j) {
                    $j->on('l.FUNCIONARIO_ID', '=', 'f.FUNCIONARIO_ID')->whereNull('l.LOTACAO_DATA_FIM');
                })
                ->leftJoin('SETOR as s', 's.SETOR_ID', '=', 'l.SETOR_ID')
                ->leftJoin('UNIDADE as u', 'u.UNIDADE_ID', '=', 's.UNIDADE_ID')
                ->where('df.DETALHE_FOLHA_ID', $detalheId)
                ->select(
                    'df.*',
                    'fl.FOLHA_COMPETENCIA as competencia',
                    'p.PESSOA_NOME as nome',
                    'p.PESSOA_CPF_NUMERO as cpf',
                    'f.FUNCIONARIO_MATRICULA as matricula',
                    'f.FUNCIONARIO_REGIME_PREV as regime_prev',
                    'c.CARGO_NOME as cargo',
                    's.SETOR_NOME as lotacao',
                    'u.UNIDADE_NOME as unidade'
                )
                ->first();

            if (!$detalhe) {
                abort(404, 'Holerite não encontrado.');
            }

            // Segurança: servidor só vê o próprio holerite, salvo acesso ampliado
            $user = Auth::user();
            $funcId = optional(DB::table('FUNCIONARIO')->where('USUARIO_ID', $user->USUARIO_ID)->first())->FUNCIONARIO_ID;
            if ($funcId && $detalhe->FUNCIONARIO_ID !== $funcId) {
                // Permite se tiver acesso a unidade (regra simplificada de gestor)
                $isAdmin = false;
                try {
                    $isAdmin = DB::table('USUARIO_UNIDADE_ACESSO')->where('USUARIO_ID', $user->USUARIO_ID)->exists();
                } catch (\Throwable $ex) {
                    return response()->json(['error' => 'Erro ao verificar permissões'], 500);
                }
                if (!$isAdmin) {
                    \Illuminate\Support\Facades\Log::channel('security')->warning('acesso_negado', ['usuario' => Auth::id(), 'rota' => request()->path(), 'ip' => request()->ip()]);
                    abort(403, 'Acesso não autorizado.');
                }
            }

            $comp = $detalhe->competencia;
            $compFormatado = strlen($comp) === 6
                ? date('m/Y', mktime(0, 0, 0, substr($comp, 4, 2), 1, substr($comp, 0, 4)))
                : $comp;

            $servidor = [
                'nome' => $detalhe->nome,
                'matricula' => $detalhe->matricula,
                'cpf' => $detalhe->cpf ? substr_replace(preg_replace('/\D/', '', $detalhe->cpf), '***', 3, 6) : '—',
                'cargo' => $detalhe->cargo,
                'lotacao' => $detalhe->lotacao . ($detalhe->unidade ? ' / ' . $detalhe->unidade : ''),
                'regime_prev' => $detalhe->regime_prev,
                'banco' => '—',
                'agencia' => '—',
                'conta' => '—',
            ];

            $totalProventos = floatval($detalhe->DETALHE_FOLHA_PROVENTOS ?? 0);
            $totalDescontos = floatval($detalhe->DETALHE_FOLHA_DESCONTOS ?? 0);
            $liquido = floatval($detalhe->DETALHE_FOLHA_LIQUIDO) ?: ($totalProventos - $totalDescontos);

            // Rubricas — se houver tabela DETALHE_FOLHA_RUBRICA (opcional)
            $rubricas = [];
            try {
                $rubricas = DB::table('DETALHE_FOLHA_RUBRICA')
                    ->where('DETALHE_FOLHA_ID', $detalheId)
                    ->get()
                    ->map(fn($r) => [
                        'codigo' => $r->RUBRICA_CODIGO ?? '',
                        'descricao' => $r->RUBRICA_DESCRICAO ?? 'Rubrica',
                        'referencia' => $r->REFERENCIA ?? '',
                        'tipo' => $r->TIPO ?? 'P',
                        'valor' => floatval($r->VALOR ?? 0),
                    ])->toArray();
            } catch (\Throwable $ex) {
                // Fallback: linhas sintéticas com os totais
                if ($totalProventos > 0)
                    $rubricas[] = ['codigo' => '001', 'descricao' => 'Vencimento Base', 'referencia' => '', 'tipo' => 'P', 'valor' => $totalProventos];
                if ($totalDescontos > 0)
                    $rubricas[] = ['codigo' => '900', 'descricao' => 'Total Descontos', 'referencia' => '', 'tipo' => 'D', 'valor' => $totalDescontos];
            }

            return response()->view('v3.holerite-pdf', [
                'competencia' => $compFormatado,
                'emitido_em' => now()->format('d/m/Y H:i'),
                'servidor' => $servidor,
                'rubricas' => $rubricas,
                'total_proventos' => $totalProventos,
                'total_descontos' => $totalDescontos,
                'liquido' => $liquido,
                'bases' => [],
            ]);
        } catch (\Throwable $e) {
            return response()->json(['erro' => $e->getMessage()], 500);
        }
    });

    // Alias do endpoint usado no frontend Vue para download do contracheque em PDF
    Route::get('/contra-cheque/{funcionarioId}/{competencia}/pdf', function (\Illuminate\Http\Request $request, $funcionarioId, $competencia) {
        return app(\App\Http\Controllers\ContraChequeController::class)->emitirPdf($request, $funcionarioId, $competencia);
    });

    // â”€â”€ GAP-08: Totais da folha por secretaria (SEC-08 aware) â”€â”€â”€â”€â”€
    Route::get('/folhas/por-secretaria', function (\Illuminate\Http\Request $request) {
        try {
            $comp = $request->competencia ?? now()->format('Y-m');
            $compDb = str_replace('-', '', $comp);
            $user = Auth::user();

            $unidadesPermitidas = null;
            try {
                $isAdmin = DB::table('USUARIO_UNIDADE_ACESSO')
                    ->where('USUARIO_ID', $user->USUARIO_ID)
                    ->where('NIVEL_ACESSO', 'TOTAL')->exists();
                if (!$isAdmin) {
                    $unidadesPermitidas = DB::table('USUARIO_UNIDADE_ACESSO')
                        ->where('USUARIO_ID', $user->USUARIO_ID)
                        ->pluck('UNIDADE_ID')->toArray();
                }
            } catch (\Throwable $ex) { /* tabela nÃ£o existe */
            }

            $q = DB::table('DETALHE_FOLHA as df')
                ->join('FOLHA as fl', 'fl.FOLHA_ID', '=', 'df.FOLHA_ID')
                ->join('FUNCIONARIO as f', 'f.FUNCIONARIO_ID', '=', 'df.FUNCIONARIO_ID')
                ->leftJoin('LOTACAO as l', function ($j) {
                    $j->on('l.FUNCIONARIO_ID', '=', 'f.FUNCIONARIO_ID')->whereNull('l.LOTACAO_DATA_FIM');
                })
                ->leftJoin('SETOR as s', 's.SETOR_ID', '=', 'l.SETOR_ID')
                ->leftJoin('UNIDADE as u', 'u.UNIDADE_ID', '=', 's.UNIDADE_ID')
                ->where('fl.FOLHA_COMPETENCIA', $compDb);

            if ($unidadesPermitidas !== null && count($unidadesPermitidas)) {
                $q->whereIn('u.UNIDADE_ID', $unidadesPermitidas);
            }

            $resultado = $q->groupBy('u.UNIDADE_ID', 'u.UNIDADE_NOME')
                ->select(
                    'u.UNIDADE_ID as secretaria_id',
                    'u.UNIDADE_NOME as secretaria',
                    DB::raw('COUNT(DISTINCT df.FUNCIONARIO_ID) as qtd_servidores'),
                    DB::raw('SUM(COALESCE(df.DETALHE_FOLHA_PROVENTOS,0)) as total_proventos'),
                    DB::raw('SUM(COALESCE(df.DETALHE_FOLHA_DESCONTOS,0)) as total_descontos'),
                    DB::raw('SUM(COALESCE(df.DETALHE_FOLHA_LIQUIDO,0)) as total_liquido')
                )->orderBy('u.UNIDADE_NOME')->get();

            return response()->json([
                'competencia' => $comp,
                'por_secretaria' => $resultado,
                'total_proventos' => round($resultado->sum('total_proventos'), 2),
                'total_liquido' => round($resultado->sum('total_liquido'), 2),
                'qtd_secretarias' => $resultado->count(),
            ]);
        } catch (\Throwable $e) {
            return response()->json(['erro' => $e->getMessage()], 500);
        }
    });
