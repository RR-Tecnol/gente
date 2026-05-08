<?php

use Carbon\Carbon;

// ══════════════════════════════════════════════════════════════════
// PROGRESSÃO FUNCIONAL
// ══════════════════════════════════════════════════════════════════

$__pfEleg = app(\App\Services\Progressao\ProgressaoFuncionalElegibilidadeService::class);
$getProgConfig = fn ($carreiraId = null) => $__pfEleg->getProgConfig($carreiraId);
$getVencBase = fn ($func) => $__pfEleg->getVencBase($func);
$avaliarEleg = fn ($func, $cfg) => $__pfEleg->avaliarEleg($func, $cfg);

// ── Visão do servidor logado (rota canônica unificada) ─────────────────
Route::get('/servidor/progressao', [\App\Http\Controllers\Api\ServidorProgressaoController::class, 'show']);

Route::get('/progressao-funcional/admin', [\App\Http\Controllers\Api\V3\ProgressaoFuncionalAdminController::class, 'indexTodos']);
Route::get('/progressao-funcional/impacto', [\App\Http\Controllers\Api\V3\ProgressaoFuncionalAdminController::class, 'impacto']);
Route::get('/progressao-funcional/impacto/detalhes', [\App\Http\Controllers\Api\V3\ProgressaoFuncionalAdminController::class, 'impactoDetalhes']);

// ── S4.1: autorização nominal (detectar -> simular -> autorizar -> aplicar) ──
$resolverAutorizacaoProgressao = function (Request $request, int $funcionarioId, string $tipoOperacao) {
    $ato = trim((string) ($request->ato ?? ''));

    if (!\Illuminate\Support\Facades\Schema::hasTable('PROGRESSAO_AUTORIZACAO')) {
        return ['id' => null, 'ato' => $ato];
    }

    $cols = \Illuminate\Support\Facades\Schema::getColumnListing('PROGRESSAO_AUTORIZACAO');
    $agora = now();
    $authId = (int) ($request->autorizacao_id ?? 0);

    // Fluxo explícito: aplicação com autorização já aprovada
    if ($authId > 0) {
        $q = DB::table('PROGRESSAO_AUTORIZACAO')->where('PROGRESSAO_AUTORIZACAO_ID', $authId);
        if (in_array('FUNCIONARIO_ID', $cols, true)) $q->where('FUNCIONARIO_ID', $funcionarioId);
        if (in_array('TIPO_OPERACAO', $cols, true)) $q->where('TIPO_OPERACAO', $tipoOperacao);
        if (in_array('STATUS', $cols, true)) $q->where('STATUS', 'aprovada');
        if (in_array('UTILIZADA_EM', $cols, true)) $q->whereNull('UTILIZADA_EM');

        $row = $q->first();
        if (!$row) {
            return ['erro' => 'Autorização inválida, já utilizada, ou não aplicável para este servidor/operação.'];
        }
        if (in_array('EXPIRA_EM', $cols, true) && !empty($row->EXPIRA_EM) && (string) $row->EXPIRA_EM < $agora->toDateString()) {
            return ['erro' => 'Autorização expirada.'];
        }

        $atoRow = trim((string) ($row->ATO_ADMINISTRATIVO ?? ''));
        return ['id' => $authId, 'ato' => $atoRow !== '' ? $atoRow : $ato];
    }

    // Fluxo legado: ato informado no aplicar/promover gera autorização implícita
    if ($ato === '') {
        return ['erro' => 'Ato administrativo é obrigatório (ou informe autorizacao_id válida).'];
    }

    $user = Auth::user();
    $payload = [
        'FUNCIONARIO_ID' => $funcionarioId,
        'TIPO_OPERACAO' => $tipoOperacao,
        'ATO_ADMINISTRATIVO' => $ato,
        'STATUS' => 'aprovada',
        'AUTORIZADO_POR' => $user->USUARIO_ID ?? null,
        'AUTORIZADO_EM' => $agora->toDateString(),
        'created_at' => $agora,
        'updated_at' => $agora,
    ];
    $payload = array_intersect_key($payload, array_flip($cols));
    $id = DB::table('PROGRESSAO_AUTORIZACAO')->insertGetId($payload);

    return ['id' => $id, 'ato' => $ato];
};

$marcarAutorizacaoComoUtilizada = function (?int $authId, string $tipoOperacao) {
    if (!$authId || !\Illuminate\Support\Facades\Schema::hasTable('PROGRESSAO_AUTORIZACAO')) {
        return;
    }
    $cols = \Illuminate\Support\Facades\Schema::getColumnListing('PROGRESSAO_AUTORIZACAO');
    $payload = [
        'UTILIZADA_EM' => now(),
        'UTILIZADA_POR' => Auth::user()->USUARIO_ID ?? null,
        'USADA_OPERACAO' => $tipoOperacao,
        'updated_at' => now(),
    ];
    $payload = array_intersect_key($payload, array_flip($cols));
    DB::table('PROGRESSAO_AUTORIZACAO')
        ->where('PROGRESSAO_AUTORIZACAO_ID', $authId)
        ->update($payload);
};

Route::post('/progressao-funcional/autorizar/{id}', function (Request $request, $id) {
    try {
        if (!\Illuminate\Support\Facades\Schema::hasTable('PROGRESSAO_AUTORIZACAO')) {
            return response()->json(['erro' => 'Tabela de autorização não encontrada. Execute migrate.'], 500);
        }

        $func = DB::table('FUNCIONARIO')->where('FUNCIONARIO_ID', $id)->first();
        if (!$func) return response()->json(['erro' => 'Servidor não encontrado.'], 404);

        $tipo = strtolower(trim((string) ($request->tipo_operacao ?? 'progressao')));
        if (!in_array($tipo, ['progressao', 'promocao'], true)) {
            return response()->json(['erro' => 'tipo_operacao inválido. Use progressao ou promocao.'], 422);
        }

        $ato = trim((string) ($request->ato ?? ''));
        if ($ato === '') {
            return response()->json(['erro' => 'Ato administrativo é obrigatório.'], 422);
        }

        $validadeDias = max(0, (int) ($request->validade_dias ?? 30));
        $agora = now();
        $expira = $validadeDias > 0 ? $agora->copy()->addDays($validadeDias)->toDateString() : null;
        $cols = \Illuminate\Support\Facades\Schema::getColumnListing('PROGRESSAO_AUTORIZACAO');

        $payload = [
            'FUNCIONARIO_ID' => (int) $id,
            'TIPO_OPERACAO' => $tipo,
            'ATO_ADMINISTRATIVO' => $ato,
            'JUSTIFICATIVA' => $request->justificativa,
            'STATUS' => 'aprovada',
            'AUTORIZADO_POR' => Auth::user()->USUARIO_ID ?? null,
            'AUTORIZADO_EM' => $agora->toDateString(),
            'EXPIRA_EM' => $expira,
            'created_at' => $agora,
            'updated_at' => $agora,
        ];
        $payload = array_intersect_key($payload, array_flip($cols));
        $authId = DB::table('PROGRESSAO_AUTORIZACAO')->insertGetId($payload);

        return response()->json(['ok' => true, 'autorizacao_id' => $authId], 201);
    } catch (\Throwable $e) {
        return response()->json(['erro' => $e->getMessage()], 500);
    }
});

// ── Aplicar progressão horizontal (nova referência) ───────────────
Route::post('/progressao-funcional/aplicar/{id}', function (Request $request, $id) use ($getProgConfig, $avaliarEleg, $getVencBase, $resolverAutorizacaoProgressao, $marcarAutorizacaoComoUtilizada) {
    try {
        $user = Auth::user();
        $func = DB::table('FUNCIONARIO as f')->join('PESSOA as p', 'p.PESSOA_ID', '=', 'f.PESSOA_ID')
            ->leftJoin('CARREIRA as ca', 'ca.CARREIRA_ID', '=', 'f.CARREIRA_ID')
            ->where('f.FUNCIONARIO_ID', $id)->select('f.*', 'p.PESSOA_NOME', 'ca.CARREIRA_REGIME')->first();
        if (!$func)
            return response()->json(['erro' => 'Servidor não encontrado.'], 404);
        $cfg = $getProgConfig($func->CARREIRA_ID);
        $eleg = $avaliarEleg($func, $cfg);
        if ($eleg['elegivel_promocao'])
            return response()->json([
                'erro' => 'Servidor no teto da carreira.',
                'teto' => true,
                'bloqueios' => ['Servidor está na última referência da classe. É necessário promover de classe antes de progredir.'],
            ], 409);
        if (!$eleg['elegivel'])
            return response()->json(['erro' => 'Não elegível.', 'bloqueios' => $eleg['bloqueios']], 422);

        $auth = $resolverAutorizacaoProgressao($request, (int) $id, 'progressao');
        if (isset($auth['erro'])) {
            return response()->json(['erro' => $auth['erro']], 422);
        }

        $vencAt = $getVencBase($func);
        $funcCols = \Illuminate\Support\Facades\Schema::getColumnListing('FUNCIONARIO');
        $payloadFunc = [
            'FUNCIONARIO_REFERENCIA' => $eleg['proxima_referencia'],
            'FUNCIONARIO_DATA_ULTIMA_PROGRESSAO' => now()->toDateString(),
        ];
        if (in_array('updated_at', $funcCols, true)) {
            $payloadFunc['updated_at'] = now();
        }
        $updated = DB::table('FUNCIONARIO')->where('FUNCIONARIO_ID', $id)->update($payloadFunc);
        if (!$updated) {
            return response()->json(['erro' => 'Servidor não encontrado ou sem alterações.'], 404);
        }
        $histCols = \Illuminate\Support\Facades\Schema::getColumnListing('HISTORICO_FUNCIONAL');
        $payloadHist = [
            'FUNCIONARIO_ID' => $id,
            'HISTORICO_TIPO' => 'progressao',
            'HISTORICO_CLASSE_ANTES' => $func->FUNCIONARIO_CLASSE,
            'HISTORICO_REFERENCIA_ANTES' => $func->FUNCIONARIO_REFERENCIA,
            'HISTORICO_CLASSE_DEPOIS' => $func->FUNCIONARIO_CLASSE,
            'HISTORICO_REFERENCIA_DEPOIS' => $eleg['proxima_referencia'],
            'HISTORICO_SALARIO_ANTES' => $vencAt,
            'HISTORICO_SALARIO_DEPOIS' => $eleg['novo_vencimento'],
            'HISTORICO_ATO_ADMINISTRATIVO' => $auth['ato'] ?? null,
            'HISTORICO_DATA_EFEITO' => now()->toDateString(),
            'USUARIO_REGISTROU' => $user->USUARIO_ID ?? null,
            'created_at' => now(),
            'updated_at' => now(),
        ];
        $payloadHist = array_intersect_key($payloadHist, array_flip($histCols));
        DB::table('HISTORICO_FUNCIONAL')->insert($payloadHist);
        $marcarAutorizacaoComoUtilizada($auth['id'] ?? null, 'progressao');
        \App\Services\Progressao\ProgressaoFuncionalListagemService::invalidateElegiveisTotalCache();
        return response()->json([
            'ok' => true,
            'nome' => $func->PESSOA_NOME,
            'referencia_de' => $func->FUNCIONARIO_REFERENCIA,
            'referencia_para' => $eleg['proxima_referencia'],
            'salario_de' => $vencAt,
            'salario_para' => $eleg['novo_vencimento'],
            'aumento' => round($eleg['novo_vencimento'] - $vencAt, 2),
            'autorizacao_id' => $auth['id'] ?? null,
        ]);
    } catch (\Throwable $e) {
        return response()->json(['erro' => $e->getMessage()], 500);
    }
});

// ── Aplicar promoção de classe ─────────────────────────────────────
Route::post('/progressao-funcional/promover/{id}', function (Request $request, $id) use ($getVencBase, $resolverAutorizacaoProgressao, $marcarAutorizacaoComoUtilizada) {
    try {
        $user = Auth::user();
        $func = DB::table('FUNCIONARIO as f')->join('PESSOA as p', 'p.PESSOA_ID', '=', 'f.PESSOA_ID')
            ->where('f.FUNCIONARIO_ID', $id)->select('f.*', 'p.PESSOA_NOME')->first();
        if (!$func)
            return response()->json(['erro' => 'Servidor não encontrado.'], 404);
        if (!$request->nova_classe)
            return response()->json(['erro' => 'Nova classe obrigatória.'], 422);
        $auth = $resolverAutorizacaoProgressao($request, (int) $id, 'promocao');
        if (isset($auth['erro'])) {
            return response()->json(['erro' => $auth['erro']], 422);
        }

        $novaClasse = $request->nova_classe;
        $novaRef = $request->nova_referencia ?? '1';
        $vencAt = $getVencBase($func);
        $novoVenc = DB::table('TABELA_SALARIAL')
            ->where('CARREIRA_ID', $func->CARREIRA_ID)->where('TABELA_CLASSE', $novaClasse)->where('TABELA_REFERENCIA', $novaRef)
            ->value('TABELA_VENCIMENTO_BASE') ?? $vencAt;
        $funcCols = \Illuminate\Support\Facades\Schema::getColumnListing('FUNCIONARIO');
        $payloadFunc = [
            'FUNCIONARIO_CLASSE' => $novaClasse,
            'FUNCIONARIO_REFERENCIA' => $novaRef,
            'FUNCIONARIO_DATA_ULTIMA_PROGRESSAO' => now()->toDateString(),
        ];
        if (in_array('updated_at', $funcCols, true)) {
            $payloadFunc['updated_at'] = now();
        }
        $updated = DB::table('FUNCIONARIO')->where('FUNCIONARIO_ID', $id)->update($payloadFunc);
        if (!$updated) {
            return response()->json(['erro' => 'Servidor não encontrado ou sem alterações.'], 404);
        }
        $histCols = \Illuminate\Support\Facades\Schema::getColumnListing('HISTORICO_FUNCIONAL');
        $payloadHist = [
            'FUNCIONARIO_ID' => $id,
            'HISTORICO_TIPO' => 'promocao',
            'HISTORICO_CLASSE_ANTES' => $func->FUNCIONARIO_CLASSE,
            'HISTORICO_REFERENCIA_ANTES' => $func->FUNCIONARIO_REFERENCIA,
            'HISTORICO_CLASSE_DEPOIS' => $novaClasse,
            'HISTORICO_REFERENCIA_DEPOIS' => $novaRef,
            'HISTORICO_SALARIO_ANTES' => $vencAt,
            'HISTORICO_SALARIO_DEPOIS' => $novoVenc,
            'HISTORICO_ATO_ADMINISTRATIVO' => $auth['ato'] ?? null,
            'HISTORICO_DATA_EFEITO' => now()->toDateString(),
            'USUARIO_REGISTROU' => $user->USUARIO_ID ?? null,
            'created_at' => now(),
            'updated_at' => now(),
        ];
        $payloadHist = array_intersect_key($payloadHist, array_flip($histCols));
        DB::table('HISTORICO_FUNCIONAL')->insert($payloadHist);
        $marcarAutorizacaoComoUtilizada($auth['id'] ?? null, 'promocao');
        \App\Services\Progressao\ProgressaoFuncionalListagemService::invalidateElegiveisTotalCache();
        return response()->json([
            'ok' => true,
            'nome' => $func->PESSOA_NOME,
            'classe_de' => $func->FUNCIONARIO_CLASSE,
            'classe_para' => $novaClasse,
            'salario_de' => $vencAt,
            'salario_para' => $novoVenc,
            'autorizacao_id' => $auth['id'] ?? null,
        ]);
    } catch (\Throwable $e) {
        return response()->json(['erro' => $e->getMessage()], 500);
    }
});

// BUG-EST-13: GET historico de aprovacoes de progressao
Route::get('/progressao-funcional/historico', function () {
    try {
        $porPagina = min((int) request('per_page', 20), 100);
        $pagina = max((int) request('page', 1), 1);
        $setor = request('setor_id');
        $busca = request('busca');

        $query = DB::table('HISTORICO_FUNCIONAL as h')
            ->join('FUNCIONARIO as f', 'f.FUNCIONARIO_ID', '=', 'h.FUNCIONARIO_ID')
            ->join('PESSOA as p', 'p.PESSOA_ID', '=', 'f.PESSOA_ID')
            ->leftJoin('CARGO as c', 'c.CARGO_ID', '=', 'f.CARGO_ID')
            ->leftJoin('USUARIO as u', 'u.USUARIO_ID', '=', 'h.USUARIO_REGISTROU')
            ->whereIn('h.HISTORICO_TIPO', ['progressao', 'promocao'])
            ->select(
                'h.HISTORICO_ID as id',
                'h.HISTORICO_TIPO as tipo',
                'p.PESSOA_NOME as servidor',
                'c.CARGO_NOME as cargo',
                'h.HISTORICO_CLASSE_ANTES as classe_de',
                'h.HISTORICO_REFERENCIA_ANTES as ref_de',
                'h.HISTORICO_CLASSE_DEPOIS as classe_para',
                'h.HISTORICO_REFERENCIA_DEPOIS as ref_para',
                'h.HISTORICO_SALARIO_ANTES as salario_de',
                'h.HISTORICO_SALARIO_DEPOIS as salario_para',
                'h.HISTORICO_ATO_ADMINISTRATIVO as ato',
                'h.HISTORICO_DATA_EFEITO as data',
                'u.USUARIO_NOME as aprovador',
                'h.created_at'
            )
            ->orderByDesc('h.HISTORICO_DATA_EFEITO')
            ->orderByDesc('h.HISTORICO_ID');

        if ($busca) {
            $query->where(function ($q) use ($busca) {
                $q->whereRaw("LOWER(p.PESSOA_NOME) LIKE ?", ['%' . strtolower($busca) . '%'])
                    ->orWhereRaw("LOWER(h.HISTORICO_ATO_ADMINISTRATIVO) LIKE ?", ['%' . strtolower($busca) . '%']);
            });
        }

        if ($setor) {
            $query->join('LOTACAO as lot', function ($j) {
                $j->on('lot.FUNCIONARIO_ID', '=', 'f.FUNCIONARIO_ID')->whereNull('lot.LOTACAO_DATA_FIM');
            })->where('lot.SETOR_ID', $setor);
        }

        $total = $query->count();
        $itens = $query->skip(($pagina - 1) * $porPagina)->take($porPagina)->get();

        return response()->json([
            'itens' => $itens,
            'total' => $total,
            'pagina' => $pagina,
            'por_pagina' => $porPagina,
            'total_paginas' => (int) ceil($total / $porPagina),
        ]);
    } catch (\Throwable $e) {
        return response()->json(['erro' => $e->getMessage()], 500);
    }
});

Route::get('/progressao-funcional/lista-elegiveis', [\App\Http\Controllers\Api\V3\ProgressaoFuncionalAdminController::class, 'indexElegiveis']);

// ── CRUD Carreiras / Tabela Salarial / Config ──────────────────────
Route::get('/progressao-funcional/carreiras', function () {
    try {
        return response()->json([
            'carreiras' => DB::table('CARREIRA')->get()->map(fn($c) => ['id' => $c->CARREIRA_ID, 'nome' => $c->CARREIRA_NOME, 'regime' => $c->CARREIRA_REGIME, 'ativo' => (bool) $c->CARREIRA_ATIVO]),
            'tabela' => DB::table('TABELA_SALARIAL')->orderBy('CARREIRA_ID')->orderBy('TABELA_CLASSE')->orderBy('TABELA_REFERENCIA_ORDEM')->get(),
            'configs' => DB::table('PROGRESSAO_CONFIG')->get(),
            'receita' => DB::table('RECEITA_MUNICIPIO')->orderByDesc('RECEITA_ANO')->first(),
        ]);
    } catch (\Throwable $e) {
        return response()->json(['erro' => $e->getMessage()], 500);
    }
});

Route::post('/progressao-funcional/carreiras', function (Request $request) {
    try {
        $id = DB::table('CARREIRA')->insertGetId([
            'CARREIRA_NOME' => $request->nome,
            'CARREIRA_REGIME' => $request->regime ?? 'efetivo',
            'CARREIRA_DESCRICAO' => $request->descricao,
            'CARREIRA_ATIVO' => true,
            'created_at' => now(),
            'updated_at' => now()
        ]);
        return response()->json(['ok' => true, 'id' => $id]);
    } catch (\Throwable $e) {
        return response()->json(['erro' => $e->getMessage()], 500);
    }
});

Route::post('/progressao-funcional/tabela-salarial', function (Request $request) {
    try {
        $id = DB::table('TABELA_SALARIAL')->insertGetId([
            'CARREIRA_ID' => $request->carreira_id,
            'TABELA_CLASSE' => $request->classe,
            'TABELA_REFERENCIA' => $request->referencia,
            'TABELA_REFERENCIA_ORDEM' => (int) ($request->ordem ?? 0),
            'TABELA_VENCIMENTO_BASE' => $request->vencimento,
            'TABELA_TITULACAO' => $request->titulacao ?? null,
            'created_at' => now(),
            'updated_at' => now()
        ]);
        return response()->json(['ok' => true, 'id' => $id]);
    } catch (\Throwable $e) {
        return response()->json(['erro' => $e->getMessage()], 500);
    }
});

Route::delete('/progressao-funcional/tabela-salarial/{id}', function ($id) {
    try {
        $deleted = DB::table('TABELA_SALARIAL')->where('TABELA_ID', $id)->delete();
        if (!$deleted) {
            return response()->json(['erro' => 'Registro da tabela salarial não encontrado.'], 404);
        }
        return response()->json(['ok' => true]);
    } catch (\Throwable $e) {
        return response()->json(['erro' => $e->getMessage()], 500);
    }
});

Route::put('/progressao-funcional/receita', function (Request $request) {
    try {
        DB::table('RECEITA_MUNICIPIO')->updateOrInsert(
            ['RECEITA_ANO' => $request->ano ?? now()->year],
            ['RECEITA_CORRENTE_LIQUIDA' => $request->rcl, 'RECEITA_FOLHA_ATUAL' => $request->folha_mensal, 'updated_at' => now(), 'created_at' => now()]
        );
        return response()->json(['ok' => true]);
    } catch (\Throwable $e) {
        return response()->json(['erro' => $e->getMessage()], 500);
    }
});

Route::put('/progressao-funcional/config', function (Request $request) {
    try {
        DB::table('PROGRESSAO_CONFIG')->updateOrInsert(
            ['CARREIRA_ID' => $request->carreira_id ?? null],
            [
                'CONFIG_INTERSTICIO_MESES' => $request->intersticio ?? 24,
                'CONFIG_NOTA_MINIMA' => $request->nota_minima ?? 7.00,
                'CONFIG_ANUENIO_PCT' => $request->anuenio_pct ?? 1.00,
                'CONFIG_REFERENCIA_MAXIMA' => $request->referencia_maxima ?? null,
                'CONFIG_CLASSE_FINAL' => $request->classe_final ?? null,
                'updated_at' => now(),
                'created_at' => now()
            ]
        );
        return response()->json(['ok' => true]);
    } catch (\Throwable $e) {
        return response()->json(['erro' => $e->getMessage()], 500);
    }
});
