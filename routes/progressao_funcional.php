<?php
use Carbon\Carbon;

// ══════════════════════════════════════════════════════════════════
// PROGRESSÃO FUNCIONAL
// ══════════════════════════════════════════════════════════════════

// ── Helper: config vigente (carreira específica ou padrão global) ──
$getProgConfig = function ($carreiraId = null) {
    $cfg = DB::table('PROGRESSAO_CONFIG')->where('CARREIRA_ID', $carreiraId)->first();
    if (!$cfg)
        $cfg = DB::table('PROGRESSAO_CONFIG')->whereNull('CARREIRA_ID')->first();
    return $cfg ?? (object) [
        'CONFIG_INTERSTICIO_MESES' => 24,
        'CONFIG_NOTA_MINIMA' => 7.00,
        'CONFIG_ANUENIO_PCT' => 1.00,
        'CONFIG_REFERENCIA_MAXIMA' => null,
        'CONFIG_CLASSE_FINAL' => null,
        'CONFIG_TEMPO_CLASSE_PROMOCAO_MESES' => 60,
        'CONFIG_ESTAGIO_PROBATORIO_MESES' => 36,
    ];
};

// ── Helper: vencimento base ────────────────────────────────────────
$getVencBase = function ($func) {
    if (!empty($func->CARREIRA_ID) && !empty($func->FUNCIONARIO_CLASSE) && !empty($func->FUNCIONARIO_REFERENCIA)) {
        $v = DB::table('TABELA_SALARIAL')
            ->where('CARREIRA_ID', $func->CARREIRA_ID)
            ->where('TABELA_CLASSE', $func->FUNCIONARIO_CLASSE)
            ->where('TABELA_REFERENCIA', $func->FUNCIONARIO_REFERENCIA)
            ->value('TABELA_VENCIMENTO_BASE');
        if ($v)
            return (float) $v;
    }
    return (float) (DB::table('CARGO')->where('CARGO_ID', $func->CARGO_ID ?? 0)->value('CARGO_SALARIO') ?? 0);
};

// —— Helper: avaliação de elegibilidade ———————————————————————————————————
// $func pode conter _avaliacao e _com_penalidade pré-cachados (pré-fetch PERF-01)
$avaliarEleg = function ($func, $cfg) use ($getVencBase) {
    $bloqueios = [];

    if (isset($func->CARREIRA_REGIME) && $func->CARREIRA_REGIME === 'comissionado')
        $bloqueios[] = 'Cargo comissionado não tem progressão funcional.';

    if (!empty($func->FUNCIONARIO_ESTAGIO_PROBATORIO))
        $bloqueios[] = 'Servidor em estágio probatório.';

    $ultima = $func->FUNCIONARIO_DATA_ULTIMA_PROGRESSAO ?? $func->FUNCIONARIO_DATA_INICIO ?? null;
    $mesesNaRef = $ultima ? (int) Carbon::now()->diffInMonths(Carbon::parse($ultima)) : 0;
    $intersticio = (int) ($cfg->CONFIG_INTERSTICIO_MESES ?? 24);
    if ($mesesNaRef < $intersticio)
        $bloqueios[] = 'Interstício não cumprido. Faltam ' . ($intersticio - $mesesNaRef) . ' meses.';

    $notaMin = (float) ($cfg->CONFIG_NOTA_MINIMA ?? 7.00);
    // PERF-01: usar _avaliacao pré-carregado se disponível, caso contrário buscar do BD
    $aval = $func->_avaliacao ?? DB::table('AVALIACAO_DESEMPENHO')
        ->where('FUNCIONARIO_ID', $func->FUNCIONARIO_ID)
        ->orderByDesc('created_at')->first();
    $nota = $aval ? ($aval->AVALIACAO_NOTA ?? $aval->NOTA_FINAL ?? null) : null;
    if ($nota !== null && (float) $nota < $notaMin)
        $bloqueios[] = "Nota de avaliação ({$nota}) abaixo do mínimo ({$notaMin}).";

    // PERF-01: usar _com_penalidade pré-carregado se disponível, caso contrário buscar do BD
    $pen = isset($func->_com_penalidade)
        ? (bool) $func->_com_penalidade
        : DB::table('AFASTAMENTO')->where('FUNCIONARIO_ID', $func->FUNCIONARIO_ID)
            ->whereRaw("LOWER(AFASTAMENTO_TIPO) LIKE '%disciplinar%' OR LOWER(AFASTAMENTO_TIPO) LIKE '%suspen%'")
            ->where(fn($q) => $q->whereNull('AFASTAMENTO_DATA_FIM')->orWhere('AFASTAMENTO_DATA_FIM', '>=', now()))
            ->exists();
    if ($pen)
        $bloqueios[] = 'Penalidade administrativa ativa.';

    // Próxima referência
    $proxRef = null;
    $novoVenc = null;
    $elegProm = false;
    if (!empty($func->CARREIRA_ID) && !empty($func->FUNCIONARIO_CLASSE) && !empty($func->FUNCIONARIO_REFERENCIA)) {
        $ords = DB::table('TABELA_SALARIAL')
            ->where('CARREIRA_ID', $func->CARREIRA_ID)
            ->where('TABELA_CLASSE', $func->FUNCIONARIO_CLASSE)
            ->orderBy('TABELA_REFERENCIA_ORDEM')->get();
        $idx = $ords->search(fn($r) => $r->TABELA_REFERENCIA === $func->FUNCIONARIO_REFERENCIA);
        if ($idx !== false && isset($ords[$idx + 1])) {
            $proxRef = $ords[$idx + 1]->TABELA_REFERENCIA;
            $novoVenc = (float) $ords[$idx + 1]->TABELA_VENCIMENTO_BASE;
        } else {
            $elegProm = true;
        }
    }

    return [
        'elegivel' => count($bloqueios) === 0 && !$elegProm,
        'elegivel_promocao' => $elegProm,
        'bloqueios' => $bloqueios,
        'meses_na_referencia' => $mesesNaRef,
        'intersticio_exigido' => $intersticio,
        'nota_obtida' => $nota,
        'nota_minima' => $notaMin,
        'proxima_referencia' => $proxRef,
        'novo_vencimento' => $novoVenc,
    ];
};

// ── Visão do servidor logado ───────────────────────────────────────
Route::get('/progressao-funcional', function () use ($getProgConfig, $avaliarEleg, $getVencBase) {
    try {
        $user = Auth::user();
        $func = DB::table('FUNCIONARIO as f')
            ->join('PESSOA as p', 'p.PESSOA_ID', '=', 'f.PESSOA_ID')
            ->leftJoin('CARGO as c', 'c.CARGO_ID', '=', 'f.CARGO_ID')
            ->leftJoin('CARREIRA as ca', 'ca.CARREIRA_ID', '=', 'f.CARREIRA_ID')
            ->where('f.USUARIO_ID', $user->USUARIO_ID ?? 0)
            ->whereNull('f.FUNCIONARIO_DATA_FIM')
            ->select('f.*', 'p.PESSOA_NOME', 'c.CARGO_NOME', 'c.CARGO_SALARIO', 'ca.CARREIRA_NOME', 'ca.CARREIRA_REGIME')
            ->first();

        if (!$func)
            return response()->json(['fallback' => true]);

        $cfg = $getProgConfig($func->CARREIRA_ID);
        $eleg = $avaliarEleg($func, $cfg);
        $venc = $getVencBase($func);
        $admissao = $func->FUNCIONARIO_DATA_INICIO ?? null;
        $anos = $admissao ? (int) Carbon::now()->diffInYears(Carbon::parse($admissao)) : 0;
        $anuenio = $venc * (($cfg->CONFIG_ANUENIO_PCT / 100) * $anos);

        $historico = DB::table('HISTORICO_FUNCIONAL')
            ->where('FUNCIONARIO_ID', $func->FUNCIONARIO_ID)
            ->orderByDesc('HISTORICO_DATA_EFEITO')->get()
            ->map(fn($h) => [
                'id' => $h->HISTORICO_ID,
                'tipo' => $h->HISTORICO_TIPO,
                'nivel' => ($h->HISTORICO_CLASSE_DEPOIS ?? '—') . ' — Ref. ' . ($h->HISTORICO_REFERENCIA_DEPOIS ?? '—'),
                'referencia' => $h->HISTORICO_REFERENCIA_DEPOIS ?? '—',
                'classe_de' => $h->HISTORICO_CLASSE_ANTES,
                'ref_de' => $h->HISTORICO_REFERENCIA_ANTES,
                'classe_para' => $h->HISTORICO_CLASSE_DEPOIS,
                'ref_para' => $h->HISTORICO_REFERENCIA_DEPOIS,
                'salario' => $h->HISTORICO_SALARIO_DEPOIS,
                'salario_de' => $h->HISTORICO_SALARIO_ANTES,
                'reajuste' => ($h->HISTORICO_SALARIO_ANTES && $h->HISTORICO_SALARIO_DEPOIS && $h->HISTORICO_SALARIO_ANTES > 0)
                    ? round(($h->HISTORICO_SALARIO_DEPOIS - $h->HISTORICO_SALARIO_ANTES) / $h->HISTORICO_SALARIO_ANTES * 100, 1)
                    : 0,
                'ato' => $h->HISTORICO_ATO_ADMINISTRATIVO,
                'data' => $h->HISTORICO_DATA_EFEITO,
                'obs' => $h->HISTORICO_OBSERVACAO,
                'ativa' => false,  // histórico = passado
                'futura' => false,
            ]);

        $ultima = $func->FUNCIONARIO_DATA_ULTIMA_PROGRESSAO ?? $admissao;
        $proxData = $ultima ? Carbon::parse($ultima)->addMonths($cfg->CONFIG_INTERSTICIO_MESES ?? 24)->toDateString() : null;
        $pct = 0;
        if ($ultima && $proxData) {
            $ini = Carbon::parse($ultima);
            $fim = Carbon::parse($proxData);
            $total = $ini->diffInDays($fim);
            $pct = $total > 0 ? min(100, (int) round($ini->diffInDays(now()) / $total * 100)) : 0;
        }

        // Monta lista de progressões para o componente (passadas + atual + futura estimada)
        $progressoes = $historico->toArray();
        // Adiciona ponto atual
        $progressoes[] = [
            'id' => 0,
            'tipo' => 'Posição Atual',
            'nivel' => ($func->FUNCIONARIO_CLASSE ?? '—') . ' — Ref. ' . ($func->FUNCIONARIO_REFERENCIA ?? '—'),
            'referencia' => $func->FUNCIONARIO_REFERENCIA ?? '—',
            'salario' => round($venc + $anuenio, 2),
            'data' => $func->FUNCIONARIO_DATA_ULTIMA_PROGRESSAO ?? $admissao,
            'ativa' => true,
            'futura' => false,
            'reajuste' => 0,
            'obs' => null,
        ];
        // Adiciona próxima estimada se elegível
        if ($eleg['proxima_referencia']) {
            $progressoes[] = [
                'id' => -1,
                'tipo' => 'Progressão Prevista',
                'nivel' => ($func->FUNCIONARIO_CLASSE ?? '—') . ' — Ref. ' . $eleg['proxima_referencia'],
                'referencia' => $eleg['proxima_referencia'],
                'salario' => $eleg['novo_vencimento'],
                'data' => $proxData,
                'ativa' => false,
                'futura' => true,
                'reajuste' => ($venc > 0 ? round(($eleg['novo_vencimento'] - $venc) / $venc * 100, 1) : 0),
                'obs' => 'Progressão horizontal automática (interstício de ' . ($cfg->CONFIG_INTERSTICIO_MESES ?? 24) . ' meses)',
            ];
        }

        return response()->json([
            'fallback' => false,
            'nome' => $func->PESSOA_NOME,
            'cargo' => $func->CARGO_NOME ?? '—',
            'carreira' => $func->CARREIRA_NOME ?? null,
            'classe' => $func->FUNCIONARIO_CLASSE ?? '—',
            'referencia' => $func->FUNCIONARIO_REFERENCIA ?? '—',
            'estavel' => (bool) ($func->FUNCIONARIO_ESTAVEL ?? false),
            'estagio' => (bool) ($func->FUNCIONARIO_ESTAGIO_PROBATORIO ?? false),
            'admissao' => $admissao,
            'anos_servico' => $anos,
            'salario_base' => $venc,
            'vencimento_base' => $venc,
            'anuenio' => round($anuenio, 2),
            'salario_total' => round($venc + $anuenio, 2),
            'elegibilidade' => $eleg,
            'proxima_data' => $proxData,
            'pct_para_proxima' => $pct,
            'historico' => $historico,
            'progressoes' => $progressoes,
            'config' => [
                'intersticio' => $cfg->CONFIG_INTERSTICIO_MESES ?? 24,
                'nota_minima' => $cfg->CONFIG_NOTA_MINIMA ?? 7.00,
                'anuenio_pct' => $cfg->CONFIG_ANUENIO_PCT ?? 1.00,
            ],
        ]);
    } catch (\Throwable $e) {
        return response()->json(['fallback' => true, 'erro' => $e->getMessage()]);
    }
});

// -- Lista admin com status de elegibilidade -----------------------------------------------
// PERF-01: pré-busca AVALIACAO_DESEMPENHO e AFASTAMENTO com whereIn — elimina N+1
Route::get('/progressao-funcional/admin', function () use ($getProgConfig, $avaliarEleg, $getVencBase) {
    try {
        $lista = DB::table('FUNCIONARIO as f')
            ->join('PESSOA as p', 'p.PESSOA_ID', '=', 'f.PESSOA_ID')
            ->leftJoin('CARGO as c', 'c.CARGO_ID', '=', 'f.CARGO_ID')
            ->leftJoin('CARREIRA as ca', 'ca.CARREIRA_ID', '=', 'f.CARREIRA_ID')
            ->whereNull('f.FUNCIONARIO_DATA_FIM')
            ->select('f.*', 'p.PESSOA_NOME', 'c.CARGO_NOME', 'c.CARGO_SALARIO', 'ca.CARREIRA_NOME', 'ca.CARREIRA_REGIME')
            ->get();

        $funcIds = $lista->pluck('FUNCIONARIO_ID');

        // PERF-01: 1 query para todas as avaliações (última por FUNCIONARIO_ID)
        $avaliacoes = DB::table('AVALIACAO_DESEMPENHO')
            ->whereIn('FUNCIONARIO_ID', $funcIds)
            ->orderByDesc('created_at')->get()
            ->groupBy('FUNCIONARIO_ID')
            ->map(fn($g) => $g->first());

        // PERF-01: 1 query para todas as penalidades ativas
        $comPenalidade = DB::table('AFASTAMENTO')
            ->whereIn('FUNCIONARIO_ID', $funcIds)
            ->whereRaw("LOWER(AFASTAMENTO_TIPO) LIKE '%disciplinar%' OR LOWER(AFASTAMENTO_TIPO) LIKE '%suspen%'")
            ->where(fn($q) => $q->whereNull('AFASTAMENTO_DATA_FIM')->orWhere('AFASTAMENTO_DATA_FIM', '>=', now()))
            ->pluck('FUNCIONARIO_ID')->flip()->toArray();

        $resultado = $lista->map(function ($func) use ($getProgConfig, $avaliarEleg, $getVencBase, $avaliacoes, $comPenalidade) {
            // Injeta dados pré-carregados — avaliarEleg usa se disponíveis
            $func->_avaliacao = $avaliacoes->get($func->FUNCIONARIO_ID);
            $func->_com_penalidade = isset($comPenalidade[$func->FUNCIONARIO_ID]);

            $cfg = $getProgConfig($func->CARREIRA_ID);
            $eleg = $avaliarEleg($func, $cfg);
            $venc = $getVencBase($func);
            $anos = $func->FUNCIONARIO_DATA_INICIO ? (int) Carbon::now()->diffInYears(Carbon::parse($func->FUNCIONARIO_DATA_INICIO)) : 0;
            $anu = $venc * (($cfg->CONFIG_ANUENIO_PCT / 100) * $anos);
            return [
                'id' => $func->FUNCIONARIO_ID,
                'nome' => $func->PESSOA_NOME,
                'cargo' => $func->CARGO_NOME ?? '—',
                'carreira' => $func->CARREIRA_NOME ?? null,
                'classe' => $func->FUNCIONARIO_CLASSE ?? '—',
                'referencia' => $func->FUNCIONARIO_REFERENCIA ?? '—',
                'salario_atual' => round($venc + $anu, 2),
                'novo_vencimento' => $eleg['novo_vencimento'],
                'proxima_ref' => $eleg['proxima_referencia'],
                'aumento' => $eleg['novo_vencimento'] ? round($eleg['novo_vencimento'] - $venc, 2) : 0,
                'elegivel' => $eleg['elegivel'],
                'elegivel_promocao' => $eleg['elegivel_promocao'],
                'bloqueios' => $eleg['bloqueios'],
                'meses_na_ref' => $eleg['meses_na_referencia'],
                'nota' => $eleg['nota_obtida'],
                'ultima_progressao' => $func->FUNCIONARIO_DATA_ULTIMA_PROGRESSAO,
            ];
        });
        return response()->json(['servidores' => $resultado]);
    } catch (\Throwable $e) {
        return response()->json(['erro' => $e->getMessage()], 500);
    }
});

// ── Simulador LRF / Impacto Financeiro ───────────────────────────
Route::get('/progressao-funcional/impacto', function () use ($getProgConfig, $avaliarEleg, $getVencBase) {
    try {
        $rec = DB::table('RECEITA_MUNICIPIO')->orderByDesc('RECEITA_ANO')->first();
        $rcl = (float) ($rec->RECEITA_CORRENTE_LIQUIDA ?? 50000000);
        $folhaMes = (float) ($rec->RECEITA_FOLHA_ATUAL ?? 2000000);

        $detalhes = [];
        $impTotal = 0;
        $impactados = 0;

        DB::table('FUNCIONARIO as f')
            ->join('PESSOA as p', 'p.PESSOA_ID', '=', 'f.PESSOA_ID')
            ->leftJoin('CARGO as c', 'c.CARGO_ID', '=', 'f.CARGO_ID')
            ->leftJoin('CARREIRA as ca', 'ca.CARREIRA_ID', '=', 'f.CARREIRA_ID')
            ->whereNull('f.FUNCIONARIO_DATA_FIM')
            ->select('f.*', 'p.PESSOA_NOME', 'c.CARGO_NOME', 'ca.CARREIRA_REGIME')
            ->get()->each(function ($func) use ($getProgConfig, $avaliarEleg, $getVencBase, &$detalhes, &$impTotal, &$impactados) {
                $cfg = $getProgConfig($func->CARREIRA_ID);
                $eleg = $avaliarEleg($func, $cfg);
                if (!$eleg['elegivel'] || !$eleg['novo_vencimento'])
                    return;
                $venc = $getVencBase($func);
                $anos = $func->FUNCIONARIO_DATA_INICIO ? (int) Carbon::now()->diffInYears(Carbon::parse($func->FUNCIONARIO_DATA_INICIO)) : 0;
                $anu = $cfg->CONFIG_ANUENIO_PCT / 100 * $anos;
                $salAt = $venc * (1 + $anu);
                $salNv = $eleg['novo_vencimento'] * (1 + $anu);
                $dif = round($salNv - $salAt, 2);
                $detalhes[] = [
                    'id' => $func->FUNCIONARIO_ID,
                    'nome' => $func->PESSOA_NOME,
                    'cargo' => $func->CARGO_NOME ?? '—',
                    'classe' => $func->FUNCIONARIO_CLASSE ?? '—',
                    'ref_atual' => $func->FUNCIONARIO_REFERENCIA ?? '—',
                    'ref_nova' => $eleg['proxima_referencia'],
                    'salario_atual' => round($salAt, 2),
                    'novo_salario' => round($salNv, 2),
                    'diferenca' => $dif
                ];
                $impTotal += $dif;
                $impactados++;
            });

        $novaFolha = $folhaMes + $impTotal;
        $impAnual = $impTotal * 12;
        $despAnual = $novaFolha * 12;
        $pctLRF = $rcl > 0 ? round($despAnual / $rcl * 100, 2) : 0;
        $pctFolha = $folhaMes > 0 ? round($impTotal / $folhaMes * 100, 2) : 0;
        $statusLRF = $pctLRF >= 54 ? 'limite_excedido' : ($pctLRF >= 51.3 ? 'limite_prudencial' : ($pctLRF >= 48.6 ? 'alerta' : 'seguro'));

        return response()->json([
            'detalhes' => $detalhes,
            'servidores_impactados' => $impactados,
            'impacto_mensal' => round($impTotal, 2),
            'impacto_anual' => round($impAnual, 2),
            'folha_atual' => round($folhaMes, 2),
            'nova_folha' => round($novaFolha, 2),
            'percentual_impacto_folha' => $pctFolha,
            'despesa_anual' => round($despAnual, 2),
            'rcl' => $rcl,
            'percentual_lrf' => $pctLRF,
            'status_lrf' => $statusLRF,
            'lrf_limites' => ['seguro' => 48.6, 'alerta' => 48.6, 'prudencial' => 51.3, 'maximo' => 54.0],
        ]);
    } catch (\Throwable $e) {
        return response()->json(['erro' => $e->getMessage()], 500);
    }
});

// ── Aplicar progressão horizontal (nova referência) ───────────────
Route::post('/progressao-funcional/aplicar/{id}', function (Request $request, $id) use ($getProgConfig, $avaliarEleg, $getVencBase) {
    try {
        $user = Auth::user();
        $func = DB::table('FUNCIONARIO as f')->join('PESSOA as p', 'p.PESSOA_ID', '=', 'f.PESSOA_ID')
            ->leftJoin('CARREIRA as ca', 'ca.CARREIRA_ID', '=', 'f.CARREIRA_ID')
            ->where('f.FUNCIONARIO_ID', $id)->select('f.*', 'p.PESSOA_NOME', 'ca.CARREIRA_REGIME')->first();
        if (!$func)
            return response()->json(['erro' => 'Servidor não encontrado.'], 404);
        $cfg = $getProgConfig($func->CARREIRA_ID);
        $eleg = $avaliarEleg($func, $cfg);
        if (!$eleg['elegivel'])
            return response()->json(['erro' => 'Não elegível.', 'bloqueios' => $eleg['bloqueios']], 422);
        $vencAt = $getVencBase($func);
        DB::table('FUNCIONARIO')->where('FUNCIONARIO_ID', $id)->update([
            'FUNCIONARIO_REFERENCIA' => $eleg['proxima_referencia'],
            'FUNCIONARIO_DATA_ULTIMA_PROGRESSAO' => now()->toDateString(),
            'updated_at' => now(),
        ]);
        DB::table('HISTORICO_FUNCIONAL')->insert([
            'FUNCIONARIO_ID' => $id,
            'HISTORICO_TIPO' => 'progressao',
            'HISTORICO_CLASSE_ANTES' => $func->FUNCIONARIO_CLASSE,
            'HISTORICO_REFERENCIA_ANTES' => $func->FUNCIONARIO_REFERENCIA,
            'HISTORICO_CLASSE_DEPOIS' => $func->FUNCIONARIO_CLASSE,
            'HISTORICO_REFERENCIA_DEPOIS' => $eleg['proxima_referencia'],
            'HISTORICO_SALARIO_ANTES' => $vencAt,
            'HISTORICO_SALARIO_DEPOIS' => $eleg['novo_vencimento'],
            'HISTORICO_ATO_ADMINISTRATIVO' => $request->ato ?? null,
            'HISTORICO_DATA_EFEITO' => now()->toDateString(),
            'USUARIO_REGISTROU' => $user->USUARIO_ID ?? null,
            'created_at' => now(),
            'updated_at' => now(),
        ]);
        return response()->json([
            'ok' => true,
            'nome' => $func->PESSOA_NOME,
            'referencia_de' => $func->FUNCIONARIO_REFERENCIA,
            'referencia_para' => $eleg['proxima_referencia'],
            'salario_de' => $vencAt,
            'salario_para' => $eleg['novo_vencimento'],
            'aumento' => round($eleg['novo_vencimento'] - $vencAt, 2)
        ]);
    } catch (\Throwable $e) {
        return response()->json(['erro' => $e->getMessage()], 500);
    }
});

// ── Aplicar promoção de classe ─────────────────────────────────────
Route::post('/progressao-funcional/promover/{id}', function (Request $request, $id) use ($getVencBase) {
    try {
        $user = Auth::user();
        $func = DB::table('FUNCIONARIO as f')->join('PESSOA as p', 'p.PESSOA_ID', '=', 'f.PESSOA_ID')
            ->where('f.FUNCIONARIO_ID', $id)->select('f.*', 'p.PESSOA_NOME')->first();
        if (!$func)
            return response()->json(['erro' => 'Servidor não encontrado.'], 404);
        if (!$request->nova_classe)
            return response()->json(['erro' => 'Nova classe obrigatória.'], 422);
        $novaClasse = $request->nova_classe;
        $novaRef = $request->nova_referencia ?? '1';
        $vencAt = $getVencBase($func);
        $novoVenc = DB::table('TABELA_SALARIAL')
            ->where('CARREIRA_ID', $func->CARREIRA_ID)->where('TABELA_CLASSE', $novaClasse)->where('TABELA_REFERENCIA', $novaRef)
            ->value('TABELA_VENCIMENTO_BASE') ?? $vencAt;
        DB::table('FUNCIONARIO')->where('FUNCIONARIO_ID', $id)->update([
            'FUNCIONARIO_CLASSE' => $novaClasse,
            'FUNCIONARIO_REFERENCIA' => $novaRef,
            'FUNCIONARIO_DATA_ULTIMA_PROGRESSAO' => now()->toDateString(),
            'updated_at' => now(),
        ]);
        DB::table('HISTORICO_FUNCIONAL')->insert([
            'FUNCIONARIO_ID' => $id,
            'HISTORICO_TIPO' => 'promocao',
            'HISTORICO_CLASSE_ANTES' => $func->FUNCIONARIO_CLASSE,
            'HISTORICO_REFERENCIA_ANTES' => $func->FUNCIONARIO_REFERENCIA,
            'HISTORICO_CLASSE_DEPOIS' => $novaClasse,
            'HISTORICO_REFERENCIA_DEPOIS' => $novaRef,
            'HISTORICO_SALARIO_ANTES' => $vencAt,
            'HISTORICO_SALARIO_DEPOIS' => $novoVenc,
            'HISTORICO_ATO_ADMINISTRATIVO' => $request->ato ?? null,
            'HISTORICO_DATA_EFEITO' => now()->toDateString(),
            'USUARIO_REGISTROU' => $user->USUARIO_ID ?? null,
            'created_at' => now(),
            'updated_at' => now(),
        ]);
        return response()->json([
            'ok' => true,
            'nome' => $func->PESSOA_NOME,
            'classe_de' => $func->FUNCIONARIO_CLASSE,
            'classe_para' => $novaClasse,
            'salario_de' => $vencAt,
            'salario_para' => $novoVenc
        ]);
    } catch (\Throwable $e) {
        return response()->json(['erro' => $e->getMessage()], 500);
    }
});

// -- Lista mensal de elegíveis (PERF-01: pré-fetch whereIn) --
Route::get('/progressao-funcional/lista-elegiveis', function () use ($getProgConfig, $avaliarEleg, $getVencBase) {
    try {
        $todos = DB::table('FUNCIONARIO as f')
            ->join('PESSOA as p', 'p.PESSOA_ID', '=', 'f.PESSOA_ID')
            ->leftJoin('CARGO as c', 'c.CARGO_ID', '=', 'f.CARGO_ID')
            ->leftJoin('CARREIRA as ca', 'ca.CARREIRA_ID', '=', 'f.CARREIRA_ID')
            ->whereNull('f.FUNCIONARIO_DATA_FIM')
            ->select('f.*', 'p.PESSOA_NOME', 'c.CARGO_NOME', 'ca.CARREIRA_NOME', 'ca.CARREIRA_REGIME')
            ->get();

        $funcIds = $todos->pluck('FUNCIONARIO_ID');

        // PERF-01: pré-busca em batch — elimina N+1 no filter+map
        $avaliacoes = DB::table('AVALIACAO_DESEMPENHO')
            ->whereIn('FUNCIONARIO_ID', $funcIds)
            ->orderByDesc('created_at')->get()
            ->groupBy('FUNCIONARIO_ID')->map(fn($g) => $g->first());

        $comPenalidade = DB::table('AFASTAMENTO')
            ->whereIn('FUNCIONARIO_ID', $funcIds)
            ->whereRaw("LOWER(AFASTAMENTO_TIPO) LIKE '%disciplinar%' OR LOWER(AFASTAMENTO_TIPO) LIKE '%suspen%'")
            ->where(fn($q) => $q->whereNull('AFASTAMENTO_DATA_FIM')->orWhere('AFASTAMENTO_DATA_FIM', '>=', now()))
            ->pluck('FUNCIONARIO_ID')->flip()->toArray();

        $elegiveis = $todos->map(function ($func) use ($getProgConfig, $avaliarEleg, $getVencBase, $avaliacoes, $comPenalidade) {
            $func->_avaliacao = $avaliacoes->get($func->FUNCIONARIO_ID);
            $func->_com_penalidade = isset($comPenalidade[$func->FUNCIONARIO_ID]);
            $cfg = $getProgConfig($func->CARREIRA_ID);
            $eleg = $avaliarEleg($func, $cfg);
            if (!$eleg['elegivel'])
                return null;
            $venc = $getVencBase($func);
            return [
                'id' => $func->FUNCIONARIO_ID,
                'nome' => $func->PESSOA_NOME,
                'cargo' => $func->CARGO_NOME ?? '—',
                'carreira' => $func->CARREIRA_NOME ?? '—',
                'classe' => $func->FUNCIONARIO_CLASSE ?? '—',
                'ref_atual' => $func->FUNCIONARIO_REFERENCIA ?? '—',
                'ref_nova' => $eleg['proxima_referencia'],
                'vencimento_atual' => $venc,
                'vencimento_novo' => $eleg['novo_vencimento'],
                'aumento' => round(($eleg['novo_vencimento'] ?? $venc) - $venc, 2),
                'meses_na_ref' => $eleg['meses_na_referencia'],
                'nota' => $eleg['nota_obtida'],
            ];
        })->filter()->values();

        return response()->json([
            'mes' => now()->format('m/Y'),
            'total' => $elegiveis->count(),
            'elegiveis' => $elegiveis,
            'gerado_em' => now()->toDateTimeString(),
        ]);
    } catch (\Throwable $e) {
        return response()->json(['erro' => $e->getMessage()], 500);
    }
});

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
        DB::table('TABELA_SALARIAL')->where('TABELA_ID', $id)->delete();
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
