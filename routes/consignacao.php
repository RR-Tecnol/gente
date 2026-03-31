<?php
// â•â•â•â•â•â•â•â•â•â•â•â•â•â•â•â•â•â•â•â•â•â•â•â•â•â•â•â•â•â•â•â•â•â•â•â•â•â•â•â•â•â•â•â•â•â•â•â•â•â•â•â•â•â•â•â•â•â•â•â•â•â•â•â•â•â•
// CONSIGNAÃ‡Ã•ES EM FOLHA â€” rotas sem use statements
// â•â•â•â•â•â•â•â•â•â•â•â•â•â•â•â•â•â•â•â•â•â•â•â•â•â•â•â•â•â•â•â•â•â•â•â•â•â•â•â•â•â•â•â•â•â•â•â•â•â•â•â•â•â•â•â•â•â•â•â•â•â•â•â•â•â•

// â”€â”€ GET: lista de convÃªnios â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€
Route::get('/consignacao/convenios', function () {
    try {
        $convenios = DB::table('CONSIG_CONVENIO')->orderBy('CONVENIO_TIPO')->orderBy('CONVENIO_NOME')->get();
        return response()->json(['convenios' => $convenios]);
    } catch (\Throwable $e) {
        return response()->json(['erro' => $e->getMessage()], 500);
    }
});

// â”€â”€ POST: criar convÃªnio â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€
Route::post('/consignacao/convenios', function (\Illuminate\Http\Request $request) {
    try {
        $id = DB::table('CONSIG_CONVENIO')->insertGetId([
            'CONVENIO_NOME' => $request->convenio_nome,
            'CONVENIO_TIPO' => $request->convenio_tipo ?? 'BANCO',
            'BANCO_NOME' => $request->banco_nome,
            'BANCO_CODIGO' => $request->banco_codigo,
            'TAXA_JUROS_MAX' => $request->taxa_juros_max ?? 0,
            'ATIVO' => true,
            'created_at' => now(),
            'updated_at' => now(),
        ]);
        return response()->json(['ok' => true, 'id' => $id]);
    } catch (\Throwable $e) {
        return response()->json(['erro' => $e->getMessage()], 500);
    }
});

// â”€â”€ GET: contratos de um ou todos os servidores â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€
Route::get('/consignacao', function (\Illuminate\Http\Request $request) {
    try {
        $query = DB::table('CONSIG_CONTRATO as c')
            ->join('FUNCIONARIO as f', 'f.FUNCIONARIO_ID', '=', 'c.FUNCIONARIO_ID')
            ->join('PESSOA as p', 'p.PESSOA_ID', '=', 'f.PESSOA_ID')
            ->join('CONSIG_CONVENIO as cv', 'cv.CONVENIO_ID', '=', 'c.CONVENIO_ID');

        if ($request->funcionario_id)
            $query->where('c.FUNCIONARIO_ID', $request->funcionario_id);
        if ($request->convenio_id)
            $query->where('c.CONVENIO_ID', $request->convenio_id);
        if ($request->status)
            $query->where('c.STATUS', $request->status);

        $contratos = $query->select(
            'c.*',
            'p.PESSOA_NOME as nome',
            'f.FUNCIONARIO_MATRICULA as matricula',
            'cv.CONVENIO_NOME as convenio_nome',
            'cv.CONVENIO_TIPO as convenio_tipo'
        )->orderBy('p.PESSOA_NOME')->get();

        $totais = [
            'total_contratos' => $contratos->count(),
            'total_descontos' => round($contratos->where('STATUS', 'ATIVO')->sum('VALOR_PARCELA'), 2),
            'total_saldo' => round($contratos->sum('SALDO_DEVEDOR'), 2),
            'contratos_ativos' => $contratos->where('STATUS', 'ATIVO')->count(),
        ];

        $convenios = DB::table('CONSIG_CONVENIO')->where('ATIVO', true)->get();

        return response()->json([
            'contratos' => $contratos,
            'totais' => $totais,
            'convenios' => $convenios,
        ]);
    } catch (\Throwable $e) {
        return response()->json(['erro' => $e->getMessage()], 500);
    }
});

// â”€â”€ POST: registrar contrato â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€
Route::post('/consignacao', function (\Illuminate\Http\Request $request) {
    try {
        $user = Auth::user();
        if (!$request->funcionario_id || !$request->convenio_id || !$request->valor_parcela)
            return response()->json(['erro' => 'Campos obrigatÃ³rios faltando.'], 422);

        // Verificar margem consignÃ¡vel â€” BUG-01 corrigido: usar DETALHE_FOLHA_LIQUIDO
        // Â§12 das regras: margem separada 30% emprÃ©stimo / 10% cartÃ£o
        $folha = DB::table('DETALHE_FOLHA as df')
            ->join('FOLHA as fo', 'fo.FOLHA_ID', '=', 'df.FOLHA_ID')
            ->where('df.FUNCIONARIO_ID', $request->funcionario_id)
            ->orderBy('fo.FOLHA_COMPETENCIA', 'desc')
            ->select(DB::raw('COALESCE(df.DETALHE_FOLHA_LIQUIDO, df.DETALHE_FOLHA_PROVENTOS - df.DETALHE_FOLHA_DESCONTOS, 0) as liquido'))
            ->first();

        $liquido = $folha ? max(0, (float) $folha->liquido) : 0;

        // Buscar tipo do convÃªnio para aplicar margem correta
        $convenio = DB::table('CONSIG_CONVENIO')->where('CONVENIO_ID', $request->convenio_id)->first();
        $tipos_emp = ['BANCO', 'SINDICATO', 'COOPERATIVA'];
        $isCartao = $convenio && $convenio->CONVENIO_TIPO === 'CARTAO';

        $usado_emp = DB::table('CONSIG_CONTRATO as c')
            ->join('CONSIG_CONVENIO as cv', 'cv.CONVENIO_ID', '=', 'c.CONVENIO_ID')
            ->where('c.FUNCIONARIO_ID', $request->funcionario_id)
            ->where('c.STATUS', 'ATIVO')
            ->whereIn('cv.CONVENIO_TIPO', $tipos_emp)
            ->sum('c.VALOR_PARCELA');

        $usado_cartao = DB::table('CONSIG_CONTRATO as c')
            ->join('CONSIG_CONVENIO as cv', 'cv.CONVENIO_ID', '=', 'c.CONVENIO_ID')
            ->where('c.FUNCIONARIO_ID', $request->funcionario_id)
            ->where('c.STATUS', 'ATIVO')
            ->where('cv.CONVENIO_TIPO', 'CARTAO')
            ->sum('c.VALOR_PARCELA');

        $margem_emp = $liquido * 0.30;
        $margem_cartao = $liquido * 0.10;

        if ($isCartao) {
            if ($request->valor_parcela > ($margem_cartao - $usado_cartao) && $liquido > 0) {
                return response()->json([
                    'aviso' => 'Margem cartão insuficiente (10%)',
                    'margem_disponivel' => round($margem_cartao - $usado_cartao, 2),
                    'margem_total' => round($margem_cartao, 2),
                    'liquido' => round($liquido, 2),
                ], 422);
            }
        } else {
            if ($request->valor_parcela > ($margem_emp - $usado_emp) && $liquido > 0) {
                return response()->json([
                    'aviso' => 'Margem empréstimo insuficiente (30%)',
                    'margem_disponivel' => round($margem_emp - $usado_emp, 2),
                    'margem_total' => round($margem_emp, 2),
                    'liquido' => round($liquido, 2),
                ], 422);
            }
        }

        $prazo = (int) ($request->prazo_meses ?? 1);
        $saldo = $request->valor_total ?? ($request->valor_parcela * $prazo);
        $inicio = $request->data_inicio ?? now()->format('Y-m-d');
        $fim = date('Y-m-d', strtotime("+{$prazo} months", strtotime($inicio)));

        $contratoId = DB::table('CONSIG_CONTRATO')->insertGetId([
            'FUNCIONARIO_ID' => $request->funcionario_id,
            'CONVENIO_ID' => $request->convenio_id,
            'NUMERO_CONTRATO' => $request->numero_contrato,
            'DATA_INICIO' => $inicio,
            'DATA_FIM' => $fim,
            'VALOR_TOTAL' => $saldo,
            'VALOR_PARCELA' => $request->valor_parcela,
            'PRAZO_MESES' => $prazo,
            'PARCELAS_PAGAS' => 0,
            'SALDO_DEVEDOR' => $saldo,
            'TAXA_JUROS' => $request->taxa_juros ?? 0,
            'STATUS' => 'ATIVO',
            'OBSERVACAO' => $request->observacao,
            'CADASTRADO_POR' => $user?->USUARIO_ID ?? null,
            'created_at' => now(),
            'updated_at' => now(),
        ]);

        // Gerar parcelas futuras
        for ($i = 1; $i <= $prazo; $i++) {
            $comp = date('Y-m', strtotime("+{$i} months", strtotime($inicio)));
            DB::table('CONSIG_PARCELA')->insert([
                'CONTRATO_ID' => $contratoId,
                'COMPETENCIA' => $comp,
                'NUMERO_PARCELA' => $i,
                'VALOR_PARCELA' => $request->valor_parcela,
                'STATUS' => 'PENDENTE',
                'created_at' => now(),
                'updated_at' => now(),
            ]);
        }

        return response()->json(['ok' => true, 'contrato_id' => $contratoId]);
    } catch (\Throwable $e) {
        return response()->json(['erro' => $e->getMessage()], 500);
    }
});

// â”€â”€ PATCH: alterar status do contrato (CONSIG-04 â€” com rastreabilidade) â”€
Route::patch('/consignacao/{id}/status', function (Request $request, $id) {
    try {
        $user = Auth::user();
        $novoStatus = $request->status;
        $statusValidos = ['ATIVO', 'SUSPENSO', 'CANCELADO', 'QUITADO'];

        if (!in_array($novoStatus, $statusValidos)) {
            return response()->json(['erro' => 'Status invÃ¡lido. Use: ' . implode(', ', $statusValidos)], 422);
        }

        DB::table('CONSIG_CONTRATO')->where('CONTRATO_ID', $id)->update([
            'STATUS' => $novoStatus,
            'updated_at' => now(),
        ]);

        // Suspender parcelas pendentes
        if ($novoStatus === 'SUSPENSO') {
            DB::table('CONSIG_PARCELA')
                ->where('CONTRATO_ID', $id)
                ->where('STATUS', 'PENDENTE')
                ->update(['STATUS' => 'SUSPENSA', 'updated_at' => now()]);
        }
        // Reativar parcelas suspensas
        if ($novoStatus === 'ATIVO') {
            DB::table('CONSIG_PARCELA')
                ->where('CONTRATO_ID', $id)
                ->where('STATUS', 'SUSPENSA')
                ->update(['STATUS' => 'PENDENTE', 'updated_at' => now()]);
        }

        // Registrar ocorrÃªncia
        try {
            DB::table('CONSIG_OCORRENCIA')->insert([
                'CONTRATO_ID' => $id,
                'TIPO' => strtoupper($novoStatus),
                'DESCRICAO' => $request->descricao,
                'MOTIVO' => $request->motivo,
                'DATA_INICIO_EFEITO' => $request->data_inicio_efeito ?? now()->toDateString(),
                'DATA_FIM_EFEITO' => $request->data_fim_efeito,
                'USUARIO_ID' => $user->USUARIO_ID ?? null,
                'created_at' => now(),
                'updated_at' => now(),
            ]);
        } catch (\Throwable $oe) { /* tabela pode nÃ£o existir ainda â€” silenciar */
        }

        return response()->json(['ok' => true]);
    } catch (\Throwable $e) {
        return response()->json(['erro' => $e->getMessage()], 500);
    }
});

// â”€â”€ PATCH: autorizar contrato (CONSIG-02) â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€
Route::patch('/consignacao/{id}/autorizar', function ($id) {
    try {
        $user = Auth::user();
        DB::table('CONSIG_CONTRATO')->where('CONTRATO_ID', $id)->update([
            'STATUS' => 'ATIVO',
            'STATUS_AUTORIZACAO' => 'AUTORIZADO',
            'AUTORIZADO_POR' => $user->USUARIO_ID ?? null,
            'AUTORIZADO_EM' => now(),
            'updated_at' => now(),
        ]);
        try {
            DB::table('CONSIG_OCORRENCIA')->insert([
                'CONTRATO_ID' => $id,
                'TIPO' => 'AUTORIZACAO',
                'DESCRICAO' => 'Contrato autorizado pelo RH',
                'USUARIO_ID' => $user->USUARIO_ID ?? null,
                'created_at' => now(),
                'updated_at' => now(),
            ]);
        } catch (\Throwable $oe) {
        }
        return response()->json(['ok' => true]);
    } catch (\Throwable $e) {
        return response()->json(['erro' => $e->getMessage()], 500);
    }
});

// â”€â”€ PATCH: rejeitar contrato (CONSIG-02) â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€
Route::patch('/consignacao/{id}/rejeitar', function (Request $request, $id) {
    try {
        $user = Auth::user();
        DB::table('CONSIG_CONTRATO')->where('CONTRATO_ID', $id)->update([
            'STATUS' => 'CANCELADO',
            'STATUS_AUTORIZACAO' => 'REJEITADO',
            'MOTIVO_REJEICAO' => $request->motivo,
            'updated_at' => now(),
        ]);
        try {
            DB::table('CONSIG_OCORRENCIA')->insert([
                'CONTRATO_ID' => $id,
                'TIPO' => 'REJEICAO',
                'MOTIVO' => $request->motivo,
                'DESCRICAO' => 'Contrato rejeitado pelo RH',
                'USUARIO_ID' => $user->USUARIO_ID ?? null,
                'created_at' => now(),
                'updated_at' => now(),
            ]);
        } catch (\Throwable $oe) {
        }
        return response()->json(['ok' => true]);
    } catch (\Throwable $e) {
        return response()->json(['erro' => $e->getMessage()], 500);
    }
});

// â”€â”€ GET: margem consignÃ¡vel de um servidor â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€
Route::get('/consignacao/margem/{funcionario_id}', function ($funcionario_id) {
    // BUG-01 corrigido: campos reais + margem separada 30%/5%
    try {

        $folha = DB::table('DETALHE_FOLHA as df')
            ->join('FOLHA as fo', 'fo.FOLHA_ID', '=', 'df.FOLHA_ID')
            ->where('df.FUNCIONARIO_ID', $funcionario_id)
            ->orderBy('fo.FOLHA_COMPETENCIA', 'desc')
            ->select(
                DB::raw('COALESCE(df.DETALHE_FOLHA_PROVENTOS, 0) as bruto'),
                DB::raw('COALESCE(df.DETALHE_FOLHA_LIQUIDO, df.DETALHE_FOLHA_PROVENTOS - df.DETALHE_FOLHA_DESCONTOS, 0) as liquido')
            )
            ->first();

        $bruto = $folha ? max(0, (float) $folha->bruto) : 0;
        $liquido = $folha ? max(0, (float) $folha->liquido) : 0;

        $tipos_emp = ['BANCO', 'SINDICATO', 'COOPERATIVA'];

        $usado_emp = (float) DB::table('CONSIG_CONTRATO as c')
            ->join('CONSIG_CONVENIO as cv', 'cv.CONVENIO_ID', '=', 'c.CONVENIO_ID')
            ->where('c.FUNCIONARIO_ID', $funcionario_id)
            ->where('c.STATUS', 'ATIVO')
            ->whereIn('cv.CONVENIO_TIPO', $tipos_emp)
            ->sum('c.VALOR_PARCELA');

        $usado_cartao = (float) DB::table('CONSIG_CONTRATO as c')
            ->join('CONSIG_CONVENIO as cv', 'cv.CONVENIO_ID', '=', 'c.CONVENIO_ID')
            ->where('c.FUNCIONARIO_ID', $funcionario_id)
            ->where('c.STATUS', 'ATIVO')
            ->where('cv.CONVENIO_TIPO', 'CARTAO')
            ->sum('c.VALOR_PARCELA');

        $margem_emp = round($liquido * 0.30, 2);
        $margem_cartao = round($liquido * 0.10, 2);

        $ativos = DB::table('CONSIG_CONTRATO')
            ->where('FUNCIONARIO_ID', $funcionario_id)
            ->where('STATUS', 'ATIVO')
            ->select('CONVENIO_ID', 'NUMERO_CONTRATO', 'VALOR_PARCELA', 'PARCELAS_PAGAS', 'PRAZO_MESES', 'SALDO_DEVEDOR')
            ->get();

        return response()->json([
            'bruto' => round($bruto, 2),
            'liquido' => round($liquido, 2),
            'margem_emp_total' => $margem_emp,
            'margem_emp_usada' => round($usado_emp, 2),
            'margem_emp_disponivel' => round($margem_emp - $usado_emp, 2),
            'margem_cartao_total' => $margem_cartao,
            'margem_cartao_usada' => round($usado_cartao, 2),
            'margem_cartao_disponivel' => round($margem_cartao - $usado_cartao, 2),
            // compat retro
            'margem_total' => round($margem_emp + $margem_cartao, 2),
            'margem_usada' => round($usado_emp + $usado_cartao, 2),
            'margem_disponivel' => round(($margem_emp - $usado_emp) + ($margem_cartao - $usado_cartao), 2),
            'contratos_ativos' => $ativos,
        ]);
    } catch (\Throwable $e) {
        return response()->json(['erro' => $e->getMessage()], 500);
    }
});


// â”€â”€ GET: parcelas de um contrato â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€
Route::get('/consignacao/{id}/parcelas', function ($id) {
    try {
        $parcelas = DB::table('CONSIG_PARCELA')->where('CONTRATO_ID', $id)->orderBy('NUMERO_PARCELA')->get();
        return response()->json(['parcelas' => $parcelas]);
    } catch (\Throwable $e) {
        return response()->json(['erro' => $e->getMessage()], 500);
    }
});

// â”€â”€ GET: relatÃ³rio de descontos por competÃªncia â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€
Route::get('/consignacao/relatorio', function (\Illuminate\Http\Request $request) {
    try {
        $comp = $request->competencia ?? now()->format('Y-m');
        $por_convenio = DB::table('CONSIG_PARCELA as cp')
            ->join('CONSIG_CONTRATO as cc', 'cc.CONTRATO_ID', '=', 'cp.CONTRATO_ID')
            ->join('CONSIG_CONVENIO as cv', 'cv.CONVENIO_ID', '=', 'cc.CONVENIO_ID')
            ->where('cp.COMPETENCIA', $comp)
            ->groupBy('cv.CONVENIO_ID', 'cv.CONVENIO_NOME', 'cv.CONVENIO_TIPO')
            ->select(
                'cv.CONVENIO_ID',
                'cv.CONVENIO_NOME',
                'cv.CONVENIO_TIPO',
                DB::raw('COUNT(DISTINCT cc.FUNCIONARIO_ID) as qtd_servidores'),
                DB::raw('SUM(cp.VALOR_PARCELA) as total')
            )->get();

        return response()->json([
            'competencia' => $comp,
            'por_convenio' => $por_convenio,
            'total_geral' => round($por_convenio->sum('total'), 2),
        ]);
    } catch (\Throwable $e) {
        return response()->json(['erro' => $e->getMessage()], 500);
    }
});

// â”€â”€ GET: histÃ³rico de ocorrÃªncias de um contrato â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€
Route::get('/consignacao/{id}/ocorrencias', function ($id) {
    try {
        $ocs = DB::table('CONSIG_OCORRENCIA as o')
            ->leftJoin('USUARIO as u', 'u.USUARIO_ID', '=', 'o.USUARIO_ID')
            ->where('o.CONTRATO_ID', $id)
            ->select('o.*', 'u.USUARIO_NOME as usuario_nome')
            ->orderByDesc('o.created_at')
            ->get();
        return response()->json(['ocorrencias' => $ocs]);
    } catch (\Throwable $e) {
        return response()->json(['ocorrencias' => [], 'aviso' => $e->getMessage()]);
    }
});

// â”€â”€ GET: relatÃ³rio analÃ­tico por servidor/parcela (CONSIG-05) â”€â”€â”€â”€
// Granularidade exigida TCE-MA e CGM â€” permite exportaÃ§Ã£o CSV no frontend
Route::get('/consignacao/relatorio-analitico', function (\Illuminate\Http\Request $request) {
    try {
        $comp = $request->competencia ?? now()->format('Y-m');

        $dados = DB::table('CONSIG_PARCELA as cp')
            ->join('CONSIG_CONTRATO as cc', 'cc.CONTRATO_ID', '=', 'cp.CONTRATO_ID')
            ->join('CONSIG_CONVENIO as cv', 'cv.CONVENIO_ID', '=', 'cc.CONVENIO_ID')
            ->join('FUNCIONARIO as f', 'f.FUNCIONARIO_ID', '=', 'cc.FUNCIONARIO_ID')
            ->join('PESSOA as p', 'p.PESSOA_ID', '=', 'f.PESSOA_ID')
            ->where('cp.COMPETENCIA', $comp)
            ->select(
                'p.PESSOA_NOME as nome',
                'f.FUNCIONARIO_MATRICULA as matricula',
                'p.PESSOA_CPF_NUMERO as cpf',
                'cv.CONVENIO_NOME as credor',
                'cv.CONVENIO_TIPO as tipo',
                'cc.NUMERO_CONTRATO',
                'cp.NUMERO_PARCELA',
                'cc.PRAZO_MESES',
                'cp.VALOR_PARCELA as valor_desconto',
                'cc.SALDO_DEVEDOR',
                'cp.STATUS'
            )
            ->orderBy('p.PESSOA_NOME')
            ->get();

        return response()->json([
            'competencia' => $comp,
            'servidores' => $dados,
            'totais' => [
                'total_descontado' => round($dados->where('STATUS', 'DESCONTADA')->sum('valor_desconto'), 2),
                'total_pendente' => round($dados->where('STATUS', 'PENDENTE')->sum('valor_desconto'), 2),
                'qtd_servidores' => $dados->unique('matricula')->count(),
                'qtd_contratos' => $dados->unique('NUMERO_CONTRATO')->count(),
            ],
            'gerado_em' => now()->toDateTimeString(),
        ]);
    } catch (\Throwable $e) {
        return response()->json(['erro' => $e->getMessage()], 500);
    }
});
