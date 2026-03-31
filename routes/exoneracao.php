<?php
// ══════════════════════════════════════════════════════════════════
// EXONERAÇÃO E VERBAS RESCISÓRIAS — não usar use statements aqui (herdados do web.php)
// ══════════════════════════════════════════════════════════════════

// ── Helper: busca salário base do servidor ─────────────────────────
$getSalarioBase = function ($func) {
    // Tenta tabela salarial estruturada primeiro
    if (!empty($func->CARREIRA_ID) && !empty($func->FUNCIONARIO_CLASSE) && !empty($func->FUNCIONARIO_REFERENCIA)) {
        $v = DB::table('TABELA_SALARIAL')
            ->where('CARREIRA_ID', $func->CARREIRA_ID)
            ->where('TABELA_CLASSE', $func->FUNCIONARIO_CLASSE)
            ->where('TABELA_REFERENCIA', $func->FUNCIONARIO_REFERENCIA)
            ->value('TABELA_VENCIMENTO_BASE');
        if ($v)
            return (float) $v;
    }
    // Fallback: salário do cargo
    if (!empty($func->CARGO_ID)) {
        $cs = DB::table('CARGO')->where('CARGO_ID', $func->CARGO_ID)->value('CARGO_SALARIO');
        if ($cs)
            return (float) $cs;
    }
    return 0.0;
};

// ── Helper: calcula verbas rescisórias ────────────────────────────
$calcularRescisao = function ($func, $dataExoneracao, $salarioBase) {
    $dataAdmissao = $func->FUNCIONARIO_DATA_INICIO ?? null;
    $dataExon = \Carbon\Carbon::parse($dataExoneracao);
    $hoje = $dataExon; // data base = data exoneração

    // ── Dias trabalhados no período aquisitivo atual ──────────────
    // Período aquisitivo começa no aniversário de admissão mais recente
    $admissao = $dataAdmissao ? \Carbon\Carbon::parse($dataAdmissao) : $hoje;
    $anosCompletos = (int) $admissao->diffInYears($hoje);
    $inicioAquisitivo = $admissao->copy()->addYears($anosCompletos);
    $diasPeriodo = (int) $inicioAquisitivo->diffInDays($hoje);
    $diasPeriodo = min($diasPeriodo, 365); // máximo 1 período

    // ── Aviso prévio (apenas para RGPS/CLT — estatutários não têm) ─
    $regime = $func->FUNCIONARIO_REGIME_PREV ?? 'RPPS';
    $anosServico = (int) $admissao->diffInYears($hoje);

    // ── Saldo de salário (dias do mês atual) ─────────────────────
    $diaExon = (int) $hoje->format('d');
    $diasMes = (int) $hoje->daysInMonth;
    $saldoSalario = round($salarioBase / 30 * $diaExon, 2);

    // ── Férias proporcionais ─────────────────────────────────────
    $mesesAquisitivo = (int) $inicioAquisitivo->diffInMonths($hoje);
    $mesesAquisitivo = min($mesesAquisitivo, 12);
    $feriasProp = round($salarioBase / 12 * $mesesAquisitivo, 2);
    $feriasPropTercio = round($feriasProp / 3, 2);

    // ── Férias vencidas (períodos completos não gozados) ─────────
    // Simplificação: verificar afastamentos; aqui conta 0 por padrão (a ajustar por gestor)
    $feriasVencidas = 0.0;
    $feriasVencidasTercio = 0.0;
    try {
        // Conta períodos aquisitivos com 12+ meses sem férias registradas
        $gozadas = DB::table('FERIAS_PERIODO')
            ->where('FUNCIONARIO_ID', $func->FUNCIONARIO_ID)
            ->whereNotNull('FERIAS_DATA_INICIO')
            ->count();
        $periodosTotais = max(0, $anosCompletos - $gozadas);
        if ($periodosTotais > 0) {
            $feriasVencidas = round($salarioBase * $periodosTotais, 2);
            $feriasVencidasTercio = round($feriasVencidas / 3, 2);
        }
    } catch (\Throwable $e) {
        // Tabela pode não existir — mantém zero
    }

    // ── 13º salário proporcional ────────────────────────────────
    $mesAtual = (int) $hoje->format('n'); // 1–12
    $decimoProporcional = round($salarioBase / 12 * $mesAtual, 2);

    // ── Licença-prêmio em pecúnia (se aposentadoria e estatuto prever) ─
    $licencaPremio = 0.0; // a ser preenchido manualmente pelo RH

    // ── FGTS + multa 40% (apenas RGPS/CLT) ───────────────────────
    $fgtsMulta = 0.0;
    if ($regime === 'RGPS') {
        // Estimativa: 8% × salário × meses trabalhados + multa 40%
        $depositosFgts = $salarioBase * 0.08 * ($anosServico * 12 + $mesAtual);
        $fgtsMulta = round($depositosFgts * 0.40, 2);
    }

    // ── Totais ────────────────────────────────────────────────────
    $totalBruto = $saldoSalario + $feriasProp + $feriasPropTercio
        + $feriasVencidas + $feriasVencidasTercio + $decimoProporcional + $licencaPremio;

    // IRRF simplificado sobre férias vencidas e 13º (as demais são isentas)
    $baseIrrf = $feriasVencidas + $feriasVencidasTercio + $decimoProporcional;
    $descontoIrrf = $baseIrrf > 4664.68 ? round($baseIrrf * 0.275 - 896.00, 2)
        : ($baseIrrf > 3751.05 ? round($baseIrrf * 0.225 - 662.77, 2)
            : ($baseIrrf > 2826.65 ? round($baseIrrf * 0.15 - 381.44, 2)
                : ($baseIrrf > 2112.00 ? round($baseIrrf * 0.075 - 158.40, 2) : 0)));
    $descontoIrrf = max(0, $descontoIrrf);

    $totalLiquido = round($totalBruto - $descontoIrrf, 2);

    return [
        'salario_base' => $salarioBase,
        'anos_servico' => $anosServico,
        'meses_periodo_aquisitivo' => $mesesAquisitivo,
        'dias_saldo' => $diaExon,
        'saldo_salario' => $saldoSalario,
        'ferias_prop' => $feriasProp,
        'ferias_prop_tercio' => $feriasPropTercio,
        'ferias_vencidas' => $feriasVencidas,
        'ferias_vencidas_tercio' => $feriasVencidasTercio,
        'decimo_terceiro_prop' => $decimoProporcional,
        'licenca_premio' => $licencaPremio,
        'fgts_multa' => $fgtsMulta,
        'total_bruto' => round($totalBruto, 2),
        'desconto_irrf' => $descontoIrrf,
        'total_liquido' => $totalLiquido,
        'regime' => $regime,
    ];
};

// ══════════════════════════════════════════════════════════════════
// ROTAS
// ══════════════════════════════════════════════════════════════════

// ── Buscar servidor para exoneração (autocomplete) ────────────────
Route::get('/exoneracao/buscar', function (Request $request) {
    try {
        $q = $request->q ?? '';
        $servidores = DB::table('FUNCIONARIO as f')
            ->join('PESSOA as p', 'p.PESSOA_ID', '=', 'f.PESSOA_ID')
            ->leftJoin('CARGO as c', 'c.CARGO_ID', '=', 'f.CARGO_ID')
            ->whereNull('f.FUNCIONARIO_DATA_FIM')
            ->where(fn($w) => $w
                ->where('p.PESSOA_NOME', 'like', "%$q%")
                ->orWhere('f.FUNCIONARIO_MATRICULA', 'like', "%$q%"))
            ->select(
                'f.FUNCIONARIO_ID as id',
                'p.PESSOA_NOME as nome',
                'f.FUNCIONARIO_MATRICULA as matricula',
                'f.FUNCIONARIO_DATA_INICIO as admissao',
                'f.FUNCIONARIO_REGIME_PREV as regime_prev',
                'c.CARGO_NOME as cargo',
                'f.CARGO_ID',
                'f.CARREIRA_ID',
                'f.FUNCIONARIO_CLASSE',
                'f.FUNCIONARIO_REFERENCIA'
            )
            ->limit(10)->get();
        return response()->json(['servidores' => $servidores]);
    } catch (\Throwable $e) {
        return response()->json(['erro' => $e->getMessage()], 500);
    }
});

// ── Preview do cálculo rescisório (sem salvar) ────────────────────
Route::post('/exoneracao/preview', function (Request $request) use ($getSalarioBase, $calcularRescisao) {
    try {
        $funcId = $request->funcionario_id;
        $dataEx = $request->data_exoneracao;
        if (!$funcId || !$dataEx)
            return response()->json(['erro' => 'funcionario_id e data_exoneracao são obrigatórios.'], 422);

        $func = DB::table('FUNCIONARIO as f')
            ->join('PESSOA as p', 'p.PESSOA_ID', '=', 'f.PESSOA_ID')
            ->leftJoin('CARGO as c', 'c.CARGO_ID', '=', 'f.CARGO_ID')
            ->where('f.FUNCIONARIO_ID', $funcId)
            ->select('f.*', 'p.PESSOA_NOME', 'c.CARGO_NOME', 'c.CARGO_SALARIO')
            ->first();
        if (!$func)
            return response()->json(['erro' => 'Servidor não encontrado.'], 404);

        $salario = $getSalarioBase($func);
        $calculo = $calcularRescisao($func, $dataEx, $salario);

        return response()->json([
            'servidor' => ['nome' => $func->PESSOA_NOME, 'matricula' => $func->FUNCIONARIO_MATRICULA, 'cargo' => $func->CARGO_NOME],
            'calculo' => $calculo,
        ]);
    } catch (\Throwable $e) {
        return response()->json(['erro' => $e->getMessage()], 500);
    }
});

// ── Registrar exoneração e salvar cálculo ────────────────────────
Route::post('/exoneracao/registrar', function (Request $request) use ($getSalarioBase, $calcularRescisao) {
    try {
        $user = Auth::user();
        $funcId = $request->funcionario_id;
        $dataEx = $request->data_exoneracao;
        $motivo = $request->motivo_saida ?? 'EXONERACAO';
        $portaria = $request->portaria_num;

        if (!$funcId || !$dataEx)
            return response()->json(['erro' => 'funcionario_id e data_exoneracao são obrigatórios.'], 422);

        $func = DB::table('FUNCIONARIO as f')
            ->join('PESSOA as p', 'p.PESSOA_ID', '=', 'f.PESSOA_ID')
            ->leftJoin('CARGO as c', 'c.CARGO_ID', '=', 'f.CARGO_ID')
            ->where('f.FUNCIONARIO_ID', $funcId)
            ->select('f.*', 'p.PESSOA_NOME', 'c.CARGO_NOME', 'c.CARGO_SALARIO')
            ->first();
        if (!$func)
            return response()->json(['erro' => 'Servidor não encontrado.'], 404);

        $salario = $getSalarioBase($func);
        $calculo = $calcularRescisao($func, $dataEx, $salario);

        // Salva/atualiza cálculo de rescisão
        $rescisaoId = DB::table('RESCISAO_CALCULO')->insertGetId([
            'FUNCIONARIO_ID' => $funcId,
            'DATA_EXONERACAO' => $dataEx,
            'MOTIVO_SAIDA' => $motivo,
            'PORTARIA_NUM' => $portaria,
            'DATA_CALCULO' => now(),
            'CALCULADO_POR' => $user?->USUARIO_ID ?? null,
            'STATUS' => 'VALIDADO',
            'SALDO_SALARIO' => $calculo['saldo_salario'],
            'FERIAS_PROP' => $calculo['ferias_prop'],
            'FERIAS_PROP_TERCIO' => $calculo['ferias_prop_tercio'],
            'FERIAS_VENCIDAS' => $calculo['ferias_vencidas'],
            'FERIAS_VENCIDAS_TERCIO' => $calculo['ferias_vencidas_tercio'],
            'DECIMO_TERCEIRO_PROP' => $calculo['decimo_terceiro_prop'],
            'LICENCA_PREMIO' => $calculo['licenca_premio'],
            'FGTS_MULTA' => $calculo['fgts_multa'],
            'TOTAL_BRUTO' => $calculo['total_bruto'],
            'DESCONTO_IRRF' => $calculo['desconto_irrf'],
            'TOTAL_LIQUIDO' => $calculo['total_liquido'],
            'REGIME_PREV' => $calculo['regime'],
            'created_at' => now(),
            'updated_at' => now(),
        ]);

        // Atualiza o funcionário
        DB::table('FUNCIONARIO')->where('FUNCIONARIO_ID', $funcId)->update([
            'FUNCIONARIO_DATA_FIM' => $dataEx,
            'FUNCIONARIO_MOTIVO_SAIDA' => $motivo,
            'FUNCIONARIO_DATA_EXONERACAO' => $dataEx,
            'FUNCIONARIO_PORTARIA_SAIDA' => $portaria,
            'FUNCIONARIO_STATUS_RESCISORIO' => 'PENDENTE',
            'updated_at' => now(),
        ]);
        \Illuminate\Support\Facades\Log::channel('security')->info('exoneracao_registrada', ['usuario' => $user?->USUARIO_ID, 'funcionario' => $funcId, 'rescisao_id' => $rescisaoId]);

        return response()->json([
            'ok' => true,
            'rescisao_id' => $rescisaoId,
            'calculo' => $calculo,
            'servidor' => $func->PESSOA_NOME,
        ]);
    } catch (\Throwable $e) {
        \Illuminate\Support\Facades\Log::error('Exoneração: ' . $e->getMessage());
        return response()->json(['erro' => $e->getMessage()], 500);
    }
});

// ── Lista de elegíveis para folha rescisória (com filtro por secretaria) ─
Route::get('/exoneracao/elegiveis', function (Request $request) {
    try {
        $unidadeId = $request->unidade_id;

        $query = DB::table('RESCISAO_CALCULO as rc')
            ->join('FUNCIONARIO as f', 'f.FUNCIONARIO_ID', '=', 'rc.FUNCIONARIO_ID')
            ->join('PESSOA as p', 'p.PESSOA_ID', '=', 'f.PESSOA_ID')
            ->leftJoin('CARGO as c', 'c.CARGO_ID', '=', 'f.CARGO_ID')
            ->leftJoin('LOTACAO as l', function ($j) {
                $j->on('l.FUNCIONARIO_ID', '=', 'f.FUNCIONARIO_ID')
                    ->whereNull('l.LOTACAO_DATA_FIM');
            })
            ->leftJoin('SETOR as s', 's.SETOR_ID', '=', 'l.SETOR_ID')
            ->leftJoin('UNIDADE as u', 'u.UNIDADE_ID', '=', 's.UNIDADE_ID')
            ->whereIn('rc.STATUS', ['VALIDADO', 'CALCULADO'])
            ->whereNull('rc.FOLHA_ID');

        if ($unidadeId) {
            // Filtro hierárquico: secretaria selecionada + todos os seus setores filhos
            $setoresIds = DB::table('SETOR')->where('UNIDADE_ID', $unidadeId)->pluck('SETOR_ID');
            $query->whereIn('l.SETOR_ID', $setoresIds);
        }

        $elegiveis = $query->select(
            'rc.RESCISAO_ID as rescisao_id',
            'f.FUNCIONARIO_ID as funcionario_id',
            'p.PESSOA_NOME as nome',
            'f.FUNCIONARIO_MATRICULA as matricula',
            'c.CARGO_NOME as cargo',
            's.SETOR_NOME as setor',
            'u.UNIDADE_NOME as secretaria',
            'u.UNIDADE_ID as unidade_id',
            'rc.DATA_EXONERACAO as data_exoneracao',
            'rc.MOTIVO_SAIDA as motivo',
            'rc.PORTARIA_NUM as portaria',
            'rc.TOTAL_BRUTO as total_bruto',
            'rc.DESCONTO_IRRF as desconto_irrf',
            'rc.TOTAL_LIQUIDO as total_liquido',
            'rc.REGIME_PREV as regime',
            'rc.STATUS as status'
        )->orderBy('rc.DATA_EXONERACAO')->get();

        // Agrupa por unidade para exibição no frontend
        $porSecretaria = $elegiveis->groupBy('secretaria');

        // Lista de unidades disponíveis para o filtro
        $unidades = DB::table('UNIDADE')->select('UNIDADE_ID as id', 'UNIDADE_NOME as nome')->get();

        return response()->json([
            'total' => $elegiveis->count(),
            'elegiveis' => $elegiveis,
            'por_secretaria' => $porSecretaria,
            'unidades' => $unidades,
        ]);
    } catch (\Throwable $e) {
        return response()->json(['erro' => $e->getMessage()], 500);
    }
});

// ── Incluir selecionados em folha rescisória (lote) ───────────────
Route::post('/exoneracao/incluir-folha', function (Request $request) {
    try {
        $user = Auth::user();
        $rescisaoIds = $request->rescisao_ids ?? [];
        $competencia = $request->competencia ?? now()->format('Y-m');

        if (empty($rescisaoIds))
            return response()->json(['erro' => 'Nenhum servidor selecionado.'], 422);

        // Cria ou recupera a folha rescisória da competência
        $folhaId = DB::table('FOLHA')->insertGetId([
            'FOLHA_DESCRICAO' => "Folha Rescisória — $competencia",
            'FOLHA_TIPO' => 2,
            'FOLHA_TIPO_ESPECIAL' => 'RESCISORIA',
            'FOLHA_COMPETENCIA' => $competencia,
            'FOLHA_SITUACAO' => 'ABERTA',
            'FOLHA_CRIACAO' => now(),
        ]);

        $incluidos = 0;
        foreach ($rescisaoIds as $rescId) {
            $rc = DB::table('RESCISAO_CALCULO')->where('RESCISAO_ID', $rescId)->first();
            if (!$rc || $rc->FOLHA_ID)
                continue;

            // Cria detalhe de folha para o servidor
            $detalheId = DB::table('DETALHE_FOLHA')->insertGetId([
                'FOLHA_ID' => $folhaId,
                'FUNCIONARIO_ID' => $rc->FUNCIONARIO_ID,
                'DETALHE_FOLHA_PROVENTOS' => $rc->TOTAL_BRUTO,
                'DETALHE_FOLHA_DESCONTOS' => $rc->DESCONTO_IRRF,
                'DETALHE_FOLHA_LIQUIDO' => $rc->TOTAL_LIQUIDO,
            ]);

            // Eventos da rescisão
            $eventos = [
                ['nome' => 'Saldo de Salário', 'val' => $rc->SALDO_SALARIO, 'tipo' => 'P'],
                ['nome' => 'Férias Proporcionais', 'val' => $rc->FERIAS_PROP, 'tipo' => 'P'],
                ['nome' => '1/3 s/ Férias Prop.', 'val' => $rc->FERIAS_PROP_TERCIO, 'tipo' => 'P'],
                ['nome' => 'Férias Vencidas', 'val' => $rc->FERIAS_VENCIDAS, 'tipo' => 'P'],
                ['nome' => '1/3 s/ Férias Vencidas', 'val' => $rc->FERIAS_VENCIDAS_TERCIO, 'tipo' => 'P'],
                ['nome' => '13º Salário Proporcional', 'val' => $rc->DECIMO_TERCEIRO_PROP, 'tipo' => 'P'],
                ['nome' => 'Licença-Prêmio Pecúnia', 'val' => $rc->LICENCA_PREMIO, 'tipo' => 'P'],
                ['nome' => 'FGTS + Multa 40%', 'val' => $rc->FGTS_MULTA, 'tipo' => 'P'],
                ['nome' => 'IRRF s/ Rescisão', 'val' => $rc->DESCONTO_IRRF, 'tipo' => 'D'],
            ];
            foreach ($eventos as $ev) {
                if ((float) $ev['val'] <= 0)
                    continue;
                $evId = DB::table('EVENTO')
                    ->where('EVENTO_NOME', $ev['nome'])
                    ->where('EVENTO_TIPO', $ev['tipo'])
                    ->value('EVENTO_ID');
                if (!$evId) {
                    $evId = DB::table('EVENTO')->insertGetId([
                        'EVENTO_NOME' => $ev['nome'],
                        'EVENTO_TIPO' => $ev['tipo'],
                        'EVENTO_CATEGORIA' => 'RESCISAO',
                        'EVENTO_INCIDE_INSS' => false,
                        'EVENTO_ATIVO' => true,
                    ]);
                }
                DB::table('EVENTO_DETALHE_FOLHA')->insert([
                    'DETALHE_FOLHA_ID' => $detalheId,
                    'EVENTO_ID' => $evId,
                    'EVENTO_DETALHE_FOLHA_VALOR' => $ev['val'],
                ]);
            }

            // Marca rescisão como incluída
            DB::table('RESCISAO_CALCULO')->where('RESCISAO_ID', $rescId)->update([
                'STATUS' => 'INCLUIDO_FOLHA',
                'FOLHA_ID' => $folhaId,
                'updated_at' => now(),
            ]);

            // Atualiza funcionário
            DB::table('FUNCIONARIO')->where('FUNCIONARIO_ID', $rc->FUNCIONARIO_ID)->update([
                'FUNCIONARIO_STATUS_RESCISORIO' => 'INCLUIDO_FOLHA',
                'updated_at' => now(),
            ]);

            $incluidos++;
        }

        // Atualiza totais da folha
        $totais = DB::table('DETALHE_FOLHA')->where('FOLHA_ID', $folhaId)->select(
            DB::raw('COUNT(*) as qtd'),
            DB::raw('SUM(DETALHE_FOLHA_PROVENTOS) as proventos'),
            DB::raw('SUM(DETALHE_FOLHA_LIQUIDO) as liquido')
        )->first();
        DB::table('FOLHA')->where('FOLHA_ID', $folhaId)->update([
            'FOLHA_QTD_SERVIDORES' => $totais->qtd,
            'FOLHA_VALOR_TOTAL' => $totais->liquido,
        ]);

        return response()->json([
            'ok' => true,
            'folha_id' => $folhaId,
            'incluidos' => $incluidos,
            'competencia' => $competencia,
        ]);
    } catch (\Throwable $e) {
        \Illuminate\Support\Facades\Log::error('Incluir folha rescisória: ' . $e->getMessage());
        return response()->json(['erro' => $e->getMessage()], 500);
    }
});

// ── Detalhe de uma rescisão específica ───────────────────────────
Route::get('/exoneracao/{id}', function ($id) {
    try {
        $rc = DB::table('RESCISAO_CALCULO as rc')
            ->join('FUNCIONARIO as f', 'f.FUNCIONARIO_ID', '=', 'rc.FUNCIONARIO_ID')
            ->join('PESSOA as p', 'p.PESSOA_ID', '=', 'f.PESSOA_ID')
            ->leftJoin('CARGO as c', 'c.CARGO_ID', '=', 'f.CARGO_ID')
            ->where('rc.RESCISAO_ID', $id)
            ->select(
                'rc.*',
                'p.PESSOA_NOME as nome',
                'f.FUNCIONARIO_MATRICULA as matricula',
                'c.CARGO_NOME as cargo',
                'f.FUNCIONARIO_DATA_INICIO as admissao'
            )
            ->first();
        if (!$rc)
            return response()->json(['erro' => 'Rescisão não encontrada.'], 404);
        return response()->json(['rescisao' => $rc]);
    } catch (\Throwable $e) {
        return response()->json(['erro' => $e->getMessage()], 500);
    }
});

// ── GAP-RES: Preview via RescisaoService ───────────────────────────────────
Route::get('/rescisao/preview/{funcionario_id}', function (int $funcId) {
    try {
        $dataEx  = request('data_exoneracao', now()->format('Y-m-d'));
        $motivo  = request('motivo', 'EXONERACAO');
        $service = new \App\Services\RescisaoService();
        return response()->json($service->calcular($funcId, $dataEx, $motivo));
    } catch (\Throwable $e) {
        return response()->json(['erro' => $e->getMessage()], 422);
    }
});

// ── GAP-RES: Salvar cálculo rescisório ────────────────────────────────────
Route::post('/rescisao/salvar', function () {
    try {
        $user   = \Illuminate\Support\Facades\Auth::user();
        $data   = request()->validate([
            'funcionario_id'  => 'required|integer',
            'data_exoneracao' => 'required|date',
            'motivo_saida'    => 'required|string',
        ]);
        $service    = new \App\Services\RescisaoService();
        $calc       = $service->calcular($data['funcionario_id'], $data['data_exoneracao'], $data['motivo_saida']);
        $rescisaoId = $service->salvar($calc, $user->USUARIO_ID);
        return response()->json(['ok' => true, 'rescisao_id' => $rescisaoId, 'calculo' => $calc]);
    } catch (\Throwable $e) {
        return response()->json(['erro' => $e->getMessage()], 422);
    }
});

// ── GAP-RES: Listar rescisões calculadas ──────────────────────────────────
Route::get('/rescisao', function () {
    try {
        $rescisoes = \Illuminate\Support\Facades\DB::table('RESCISAO_CALCULO as r')
            ->join('FUNCIONARIO as f', 'f.FUNCIONARIO_ID', '=', 'r.FUNCIONARIO_ID')
            ->join('PESSOA as p', 'p.PESSOA_ID', '=', 'f.PESSOA_ID')
            ->select('r.*', 'p.PESSOA_NOME as nome', 'f.FUNCIONARIO_MATRICULA as matricula')
            ->orderByDesc('r.DATA_EXONERACAO')
            ->limit(100)
            ->get();
        return response()->json(['rescisoes' => $rescisoes]);
    } catch (\Throwable $e) {
        return response()->json(['erro' => $e->getMessage()], 500);
    }
});
