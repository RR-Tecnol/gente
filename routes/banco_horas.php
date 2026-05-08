<?php
// ══════════════════════════════════════════════════════════════════
// BANCO DE HORAS — GAP-05 / Sprint 5
// ⚠️ NÃO abrir Route::middleware()->prefix()->group() aqui
//    O contexto api/v3 + auth já é herdado do web.php
// ══════════════════════════════════════════════════════════════════

// GET /banco-horas — saldo atual + histórico de 12 meses
Route::get('/banco-horas', function (\Illuminate\Http\Request $request) {
    try {
        $funcionario_id = $request->funcionario_id;
        $user = Auth::user();

        if (!$funcionario_id) {
            $funcionario_id = DB::table('FUNCIONARIO')
                ->where('USUARIO_ID', $user->USUARIO_ID)
                ->value('FUNCIONARIO_ID');
        }

        if (!$funcionario_id) {
            return response()->json(['saldo' => null, 'historico' => [], 'aviso' => 'Vínculo funcional não encontrado.']);
        }

        $temLedger = \Illuminate\Support\Facades\Schema::hasTable('JORNADA_LEDGER');
        if ($temLedger) {
            $ledgerRows = DB::table('JORNADA_LEDGER')
                ->where('FUNCIONARIO_ID', $funcionario_id)
                ->orderBy('JORNADA_DATA')
                ->orderBy('VERSAO')
                ->get();

            if ($ledgerRows->isNotEmpty()) {
                $porCompetencia = $ledgerRows
                    ->groupBy('COMPETENCIA')
                    ->map(function ($rows, $comp) {
                        $cred = (float) $rows->sum('HORAS_CREDITADAS');
                        $deb = (float) $rows->sum('HORAS_DEBITADAS');
                        return [
                            'competencia' => $comp,
                            'horas_creditadas' => round($cred, 2),
                            'horas_debitadas' => round($deb, 2),
                            'saldo_mes' => round($cred - $deb, 2),
                        ];
                    })
                    ->sortBy('competencia')
                    ->values();

                $acumulado = 0.0;
                $apuracoes = collect();
                foreach ($porCompetencia as $row) {
                    $acumulado += (float) $row['saldo_mes'];
                    $apuracoes->push([
                        'competencia' => $row['competencia'],
                        'horas_creditadas' => $row['horas_creditadas'],
                        'horas_debitadas' => $row['horas_debitadas'],
                        'saldo_mes' => $row['saldo_mes'],
                        'saldo_acumulado' => round($acumulado, 2),
                        'status' => 'apurada_ledger',
                    ]);
                }

                $ultimo = $ledgerRows->last();
                $historico = $ledgerRows
                    ->sortByDesc('JORNADA_DATA')
                    ->take(31)
                    ->values()
                    ->map(fn($r) => [
                        'data' => $r->JORNADA_DATA,
                        'competencia' => $r->COMPETENCIA,
                        'versao' => (int) ($r->VERSAO ?? 1),
                        'tipo' => $r->LANCAMENTO_TIPO,
                        'horas_creditadas' => (float) ($r->HORAS_CREDITADAS ?? 0),
                        'horas_debitadas' => (float) ($r->HORAS_DEBITADAS ?? 0),
                        'saldo_horas' => (float) ($r->SALDO_HORAS ?? 0),
                        'motivo' => $r->MOTIVO,
                        'origem' => $r->ORIGEM,
                        'gerado_em' => $r->GERADO_EM,
                    ]);

                return response()->json([
                    'fonte' => 'JORNADA_LEDGER',
                    'saldo_acumulado' => round($acumulado, 2),
                    'ultimo_registro' => [
                        'competencia' => $ultimo->COMPETENCIA,
                        'data' => $ultimo->JORNADA_DATA,
                        'saldo_horas' => (float) ($ultimo->SALDO_HORAS ?? 0),
                        'horas_creditadas' => (float) ($ultimo->HORAS_CREDITADAS ?? 0),
                        'horas_debitadas' => (float) ($ultimo->HORAS_DEBITADAS ?? 0),
                        'versao' => (int) ($ultimo->VERSAO ?? 1),
                    ],
                    'historico' => $historico,
                    'apuracoes' => $apuracoes->sortByDesc('competencia')->values(),
                ]);
            }
        }

        $saldo = DB::table('BANCO_HORAS')
            ->where('FUNCIONARIO_ID', $funcionario_id)
            ->orderByDesc('COMPETENCIA')
            ->first();

        // Saldo acumulado: soma de créditos - débitos
        $saldoAcumulado = DB::table('BANCO_HORAS')
            ->where('FUNCIONARIO_ID', $funcionario_id)
            ->selectRaw('SUM(COALESCE(HORAS_CREDITADAS,0)) - SUM(COALESCE(HORAS_DEBITADAS,0)) as saldo_total')
            ->value('saldo_total') ?? 0;

        $historico = DB::table('BANCO_HORAS as bh')
            ->leftJoin('USUARIO as u', 'u.USUARIO_ID', '=', 'bh.REGISTRADO_POR')
            ->where('bh.FUNCIONARIO_ID', $funcionario_id)
            ->select('bh.*', 'u.USUARIO_NOME as registrado_por_nome')
            ->orderByDesc('bh.COMPETENCIA')
            ->limit(24)
            ->get();

        return response()->json([
            'fonte' => 'BANCO_HORAS',
            'saldo_acumulado' => round($saldoAcumulado, 2),
            'ultimo_registro' => $saldo,
            'historico' => $historico,
            'apuracoes' => [],
        ]);
    } catch (\Throwable $e) {
        return response()->json(['erro' => $e->getMessage()], 500);
    }
});

// POST /banco-horas/lancar — registrar crédito ou débito
Route::post('/banco-horas/lancar', function (\Illuminate\Http\Request $request) {
    try {
        $user = Auth::user();

        if (!$request->funcionario_id || !$request->competencia || !$request->tipo) {
            return response()->json(['erro' => 'funcionario_id, competencia e tipo são obrigatórios.'], 422);
        }

        $tiposValidos = ['CREDITO', 'COMPENSACAO', 'PAGAMENTO', 'EXPIRADO'];
        if (!in_array($request->tipo, $tiposValidos)) {
            return response()->json(['erro' => 'Tipo inválido. Use: ' . implode(', ', $tiposValidos)], 422);
        }

        $id = DB::table('BANCO_HORAS')->insertGetId([
            'FUNCIONARIO_ID' => $request->funcionario_id,
            'COMPETENCIA' => $request->competencia,
            'HORAS_CREDITADAS' => $request->tipo === 'CREDITO' ? ($request->horas ?? 0) : 0,
            'HORAS_DEBITADAS' => in_array($request->tipo, ['COMPENSACAO', 'PAGAMENTO', 'EXPIRADO']) ? ($request->horas ?? 0) : 0,
            'TIPO' => $request->tipo,
            'OBSERVACAO' => $request->observacao,
            'REGISTRADO_POR' => $user->USUARIO_ID ?? null,
            'created_at' => now(),
            'updated_at' => now(),
        ]);

        return response()->json(['ok' => true, 'id' => $id], 201);
    } catch (\Throwable $e) {
        return response()->json(['erro' => $e->getMessage()], 500);
    }
});

// POST /banco-horas/compensar — solicitar compensação de horas
Route::post('/banco-horas/compensar', function (\Illuminate\Http\Request $request) {
    try {
        $user = Auth::user();
        $func_id = $request->funcionario_id ?? DB::table('FUNCIONARIO')
            ->where('USUARIO_ID', $user->USUARIO_ID)
            ->value('FUNCIONARIO_ID');

        if (!$func_id) {
            return response()->json(['erro' => 'Vínculo funcional não encontrado.'], 422);
        }

        // Verificar saldo disponível
        $saldo = DB::table('BANCO_HORAS')
            ->where('FUNCIONARIO_ID', $func_id)
            ->selectRaw('SUM(COALESCE(HORAS_CREDITADAS,0)) - SUM(COALESCE(HORAS_DEBITADAS,0)) as saldo')
            ->value('saldo') ?? 0;

        if ((float) $saldo < (float) ($request->horas ?? 0)) {
            return response()->json([
                'erro' => 'Saldo insuficiente de banco de horas.',
                'saldo' => round($saldo, 2),
            ], 422);
        }

        $id = DB::table('BANCO_HORAS')->insertGetId([
            'FUNCIONARIO_ID' => $func_id,
            'COMPETENCIA' => $request->competencia ?? now()->format('Y-m'),
            'HORAS_CREDITADAS' => 0,
            'HORAS_DEBITADAS' => $request->horas ?? 0,
            'TIPO' => 'COMPENSACAO',
            'OBSERVACAO' => $request->observacao ?? 'Compensação solicitada pelo servidor',
            'REGISTRADO_POR' => $user->USUARIO_ID ?? null,
            'created_at' => now(),
            'updated_at' => now(),
        ]);

        return response()->json(['ok' => true, 'id' => $id, 'saldo_restante' => round($saldo - ($request->horas ?? 0), 2)], 201);
    } catch (\Throwable $e) {
        return response()->json(['erro' => $e->getMessage()], 500);
    }
});

// GET /banco-horas/relatorio — consolidado por setor/secretaria
Route::get('/banco-horas/relatorio', function (\Illuminate\Http\Request $request) {
    try {
        $comp = $request->competencia ?? now()->format('Y-m');

        $consolidado = DB::table('BANCO_HORAS as bh')
            ->join('FUNCIONARIO as f', 'f.FUNCIONARIO_ID', '=', 'bh.FUNCIONARIO_ID')
            ->join('PESSOA as p', 'p.PESSOA_ID', '=', 'f.PESSOA_ID')
            ->leftJoin('LOTACAO as l', function ($j) {
                $j->on('l.FUNCIONARIO_ID', '=', 'f.FUNCIONARIO_ID')->whereNull('l.LOTACAO_DATA_FIM');
            })
            ->leftJoin('SETOR as s', 's.SETOR_ID', '=', 'l.SETOR_ID')
            ->leftJoin('UNIDADE as u', 'u.UNIDADE_ID', '=', 's.UNIDADE_ID')
            ->where('bh.COMPETENCIA', $comp)
            ->groupBy('f.FUNCIONARIO_ID', 'p.PESSOA_NOME', 'f.FUNCIONARIO_MATRICULA', 's.SETOR_NOME', 'u.UNIDADE_NOME')
            ->select(
                'p.PESSOA_NOME as nome',
                'f.FUNCIONARIO_MATRICULA as matricula',
                's.SETOR_NOME as setor',
                'u.UNIDADE_NOME as secretaria',
                DB::raw('SUM(bh.HORAS_CREDITADAS) as total_creditado'),
                DB::raw('SUM(bh.HORAS_DEBITADAS) as total_debitado'),
                DB::raw('SUM(bh.HORAS_CREDITADAS) - SUM(bh.HORAS_DEBITADAS) as saldo_periodo')
            )
            ->orderBy('u.UNIDADE_NOME')
            ->get();

        return response()->json([
            'competencia' => $comp,
            'servidores' => $consolidado,
            'total_creditado' => round($consolidado->sum('total_creditado'), 2),
            'total_debitado' => round($consolidado->sum('total_debitado'), 2),
        ]);
    } catch (\Throwable $e) {
        return response()->json(['erro' => $e->getMessage()], 500);
    }
});

// BUG-EST-15: GET /banco-horas/equipe — visão consolidada da equipe para gestor
Route::get('/banco-horas/equipe', function (\Illuminate\Http\Request $request) {
    try {
        $user = Auth::user();
        $comp = $request->competencia ?? now()->format('Y-m');
        $escopo = (string) ($request->escopo ?? 'meu_setor'); // meu_setor | setor | todos
        $setorIdFiltro = $request->filled('setor_id') ? (int) $request->setor_id : null;
        $temLotacaoFim = \Illuminate\Support\Facades\Schema::hasColumn('LOTACAO', 'LOTACAO_DATA_FIM');
        $temFuncionarioFim = \Illuminate\Support\Facades\Schema::hasColumn('FUNCIONARIO', 'FUNCIONARIO_DATA_FIM');
        $isAdmin = DB::table('USUARIO_PERFIL')
            ->where('USUARIO_ID', $user->USUARIO_ID ?? 0)
            ->where('USUARIO_PERFIL_ATIVO', 1)
            ->where('PERFIL_ID', '<=', 2)
            ->exists();

        $lotacaoAtiva = DB::table('LOTACAO as l')
            ->selectRaw('l.FUNCIONARIO_ID, MIN(l.SETOR_ID) as SETOR_ID')
            ->when($temLotacaoFim, fn($q) => $q->whereNull('l.LOTACAO_DATA_FIM'))
            ->groupBy('l.FUNCIONARIO_ID');

        $func = DB::table('FUNCIONARIO as f')
            ->joinSub($lotacaoAtiva, 'la', function ($j) {
                $j->on('la.FUNCIONARIO_ID', '=', 'f.FUNCIONARIO_ID');
            })
            ->where('f.USUARIO_ID', $user->USUARIO_ID)
            ->select('f.FUNCIONARIO_ID', 'la.SETOR_ID')
            ->first();

        if (!$func || !$func->SETOR_ID) {
            return response()->json(['membros' => [], 'aviso' => 'Setor não encontrado.']);
        }

        if (!$isAdmin) {
            $escopo = 'meu_setor';
            $setorIdFiltro = (int) $func->SETOR_ID;
        }
        if ($escopo === 'meu_setor') {
            $setorIdFiltro = (int) $func->SETOR_ID;
        }

        $membrosQuery = DB::table('FUNCIONARIO as f')
            ->join('PESSOA as p', 'p.PESSOA_ID', '=', 'f.PESSOA_ID')
            ->joinSub($lotacaoAtiva, 'la', function ($j) {
                $j->on('la.FUNCIONARIO_ID', '=', 'f.FUNCIONARIO_ID');
            })
            ->leftJoin('CARGO as c', 'c.CARGO_ID', '=', 'f.CARGO_ID')
            ->leftJoin('SETOR as s', 's.SETOR_ID', '=', 'la.SETOR_ID')
            ->when($setorIdFiltro, fn($q) => $q->where('la.SETOR_ID', $setorIdFiltro))
            ->when($escopo !== 'todos', fn($q) => $q->where('f.FUNCIONARIO_ID', '<>', $func->FUNCIONARIO_ID))
            ->when($temFuncionarioFim, fn($q) => $q->whereNull('f.FUNCIONARIO_DATA_FIM'))
            ->select(
                'f.FUNCIONARIO_ID',
                'p.PESSOA_NOME as nome',
                'c.CARGO_NOME as cargo',
                's.SETOR_ID as setor_id',
                's.SETOR_NOME as setor',
                'f.FUNCIONARIO_MATRICULA as matricula'
            )
            ->orderBy('s.SETOR_NOME')
            ->orderBy('p.PESSOA_NOME');
        $membros = $membrosQuery
            ->get();

        $funcIds = $membros->pluck('FUNCIONARIO_ID');

        if ($funcIds->isEmpty()) {
            return response()->json([
                'competencia' => $comp,
                'setor' => $membros->first()?->setor,
                'membros' => [],
            ]);
        }

        $temLedger = \Illuminate\Support\Facades\Schema::hasTable('JORNADA_LEDGER');
        $saldos = collect();
        $compAtual = collect();
        $fonte = 'BANCO_HORAS';

        if ($temLedger) {
            $saldosLedger = DB::table('JORNADA_LEDGER')
                ->whereIn('FUNCIONARIO_ID', $funcIds)
                ->groupBy('FUNCIONARIO_ID')
                ->selectRaw('FUNCIONARIO_ID, SUM(COALESCE(HORAS_CREDITADAS,0)) as total_cred, SUM(COALESCE(HORAS_DEBITADAS,0)) as total_deb')
                ->get()->keyBy('FUNCIONARIO_ID');

            $compAtualLedger = DB::table('JORNADA_LEDGER')
                ->whereIn('FUNCIONARIO_ID', $funcIds)
                ->where('COMPETENCIA', $comp)
                ->groupBy('FUNCIONARIO_ID')
                ->selectRaw('FUNCIONARIO_ID, SUM(COALESCE(HORAS_CREDITADAS,0)) as cred_mes, SUM(COALESCE(HORAS_DEBITADAS,0)) as deb_mes')
                ->get()->keyBy('FUNCIONARIO_ID');

            // Se ledger existir mas não tiver saldo de competência útil, usa fallback BANCO_HORAS.
            $temDadoCompLedger = $compAtualLedger->count() > 0;
            if ($temDadoCompLedger) {
                $saldos = $saldosLedger;
                $compAtual = $compAtualLedger;
                $fonte = 'JORNADA_LEDGER';
            }
        }

        if ($saldos->isEmpty() || $compAtual->isEmpty()) {
            $saldos = DB::table('BANCO_HORAS')
                ->whereIn('FUNCIONARIO_ID', $funcIds)
                ->groupBy('FUNCIONARIO_ID')
                ->selectRaw('FUNCIONARIO_ID, SUM(COALESCE(HORAS_CREDITADAS,0)) as total_cred, SUM(COALESCE(HORAS_DEBITADAS,0)) as total_deb')
                ->get()->keyBy('FUNCIONARIO_ID');

            $compAtual = DB::table('BANCO_HORAS')
                ->whereIn('FUNCIONARIO_ID', $funcIds)
                ->where('COMPETENCIA', $comp)
                ->groupBy('FUNCIONARIO_ID')
                ->selectRaw('FUNCIONARIO_ID, SUM(COALESCE(HORAS_CREDITADAS,0)) as cred_mes, SUM(COALESCE(HORAS_DEBITADAS,0)) as deb_mes')
                ->get()->keyBy('FUNCIONARIO_ID');
            $fonte = 'BANCO_HORAS';
        }

        $resultado = $membros->map(function ($m) use ($saldos, $compAtual) {
            $s = $saldos->get($m->FUNCIONARIO_ID);
            $mc = $compAtual->get($m->FUNCIONARIO_ID);
            return [
                'funcionario_id' => $m->FUNCIONARIO_ID,
                'nome' => $m->nome,
                'cargo' => $m->cargo ?? '—',
                'matricula' => $m->matricula,
                'setor_id' => $m->setor_id,
                'setor' => $m->setor,
                'saldo_acumulado' => round(($s->total_cred ?? 0) - ($s->total_deb ?? 0), 2),
                'cred_mes' => round($mc->cred_mes ?? 0, 2),
                'deb_mes' => round($mc->deb_mes ?? 0, 2),
                'saldo_mes' => round(($mc->cred_mes ?? 0) - ($mc->deb_mes ?? 0), 2),
            ];
        });

        $setoresDisponiveis = $resultado
            ->groupBy('setor_id')
            ->map(fn($itens, $setorId) => [
                'setor_id' => (int) $setorId,
                'setor' => (string) ($itens->first()['setor'] ?? '—'),
                'quantidade' => $itens->count(),
            ])
            ->values()
            ->sortBy('setor');

        $comparativoSetores = $resultado
            ->groupBy('setor_id')
            ->map(function ($itens, $setorId) {
                $saldoTotal = round((float) $itens->sum('saldo_mes'), 2);
                $qtd = $itens->count();
                $negativos = $itens->filter(fn($i) => (float) $i['saldo_mes'] < 0)->count();
                $positivos = $itens->filter(fn($i) => (float) $i['saldo_mes'] > 0)->count();
                return [
                    'setor_id' => (int) $setorId,
                    'setor' => (string) ($itens->first()['setor'] ?? '—'),
                    'quantidade' => $qtd,
                    'saldo_total' => $saldoTotal,
                    'saldo_medio' => $qtd > 0 ? round($saldoTotal / $qtd, 2) : 0,
                    'negativos' => $negativos,
                    'positivos' => $positivos,
                ];
            })
            ->values()
            ->sortBy('setor');

        return response()->json([
            'competencia' => $comp,
            'escopo' => $escopo,
            'is_admin' => $isAdmin,
            'setor_id' => $setorIdFiltro,
            'setor' => $membros->first()?->setor,
            'fonte' => $fonte,
            'setores_disponiveis' => $setoresDisponiveis,
            'comparativo_setores' => $comparativoSetores,
            'membros' => $resultado,
        ]);
    } catch (\Throwable $e) {
        return response()->json(['erro' => $e->getMessage()], 500);
    }
});

// GET /banco-horas/equipe/impacto-escala — impacto operacional com estimativa de desconto
Route::get('/banco-horas/equipe/impacto-escala', function (\Illuminate\Http\Request $request) {
    try {
        $user = Auth::user();
        $comp = $request->competencia ?? now()->format('Y-m');
        $setorNomeFiltro = trim((string) ($request->setor ?? ''));

        $isAdmin = DB::table('USUARIO_PERFIL')
            ->where('USUARIO_ID', $user->USUARIO_ID ?? 0)
            ->where('USUARIO_PERFIL_ATIVO', 1)
            ->where('PERFIL_ID', '<=', 2)
            ->exists();

        $temLotacaoFim = \Illuminate\Support\Facades\Schema::hasColumn('LOTACAO', 'LOTACAO_DATA_FIM');
        $temFuncionarioFim = \Illuminate\Support\Facades\Schema::hasColumn('FUNCIONARIO', 'FUNCIONARIO_DATA_FIM');
        $temValorHoraDesconto = \Illuminate\Support\Facades\Schema::hasColumn('CARGO', 'CARGO_VALOR_HORA_DESCONTO');
        $temRemuneracaoCargo = \Illuminate\Support\Facades\Schema::hasColumn('CARGO', 'CARGO_REMUNERACAO');
        $temSalarioCargo = \Illuminate\Support\Facades\Schema::hasColumn('CARGO', 'CARGO_SALARIO');

        $queryMembros = DB::table('FUNCIONARIO as f')
            ->join('PESSOA as p', 'p.PESSOA_ID', '=', 'f.PESSOA_ID')
            ->join('LOTACAO as l', 'l.FUNCIONARIO_ID', '=', 'f.FUNCIONARIO_ID')
            ->leftJoin('CARGO as c', 'c.CARGO_ID', '=', 'f.CARGO_ID')
            ->leftJoin('SETOR as s', 's.SETOR_ID', '=', 'l.SETOR_ID')
            ->when($temLotacaoFim, fn($q) => $q->whereNull('l.LOTACAO_DATA_FIM'))
            ->when($temFuncionarioFim, fn($q) => $q->whereNull('f.FUNCIONARIO_DATA_FIM'));

        if (!$isAdmin) {
            $funcLogado = DB::table('FUNCIONARIO as f')
                ->join('LOTACAO as l', 'l.FUNCIONARIO_ID', '=', 'f.FUNCIONARIO_ID')
                ->when($temLotacaoFim, fn($q) => $q->whereNull('l.LOTACAO_DATA_FIM'))
                ->where('f.USUARIO_ID', $user->USUARIO_ID)
                ->select('f.FUNCIONARIO_ID', 'l.SETOR_ID')
                ->first();
            if (!$funcLogado || !$funcLogado->SETOR_ID) {
                return response()->json(['resumo' => ['total_funcionarios' => 0, 'total_impactados' => 0, 'desconto_total_estimado' => 0], 'membros_impacto' => []]);
            }
            $queryMembros->where('l.SETOR_ID', $funcLogado->SETOR_ID)
                ->where('f.FUNCIONARIO_ID', '<>', $funcLogado->FUNCIONARIO_ID);
        }

        if ($setorNomeFiltro !== '') {
            $queryMembros->where('s.SETOR_NOME', 'like', '%' . $setorNomeFiltro . '%');
        }

        $selectRemuneracao = $temRemuneracaoCargo
            ? 'c.CARGO_REMUNERACAO as remuneracao'
            : ($temSalarioCargo ? 'c.CARGO_SALARIO as remuneracao' : 'NULL as remuneracao');

        $membros = $queryMembros->select(
            'f.FUNCIONARIO_ID as funcionario_id',
            'p.PESSOA_NOME as nome',
            'f.FUNCIONARIO_MATRICULA as matricula',
            's.SETOR_NOME as setor',
            'c.CARGO_NOME as cargo',
            DB::raw($selectRemuneracao),
            DB::raw($temValorHoraDesconto ? 'c.CARGO_VALOR_HORA_DESCONTO as valor_hora_desconto' : 'NULL as valor_hora_desconto')
        )->get();

        if ($membros->isEmpty()) {
            return response()->json(['resumo' => ['total_funcionarios' => 0, 'total_impactados' => 0, 'desconto_total_estimado' => 0], 'membros_impacto' => []]);
        }

        $funcIds = $membros->pluck('funcionario_id')->values();
        $temLedger = \Illuminate\Support\Facades\Schema::hasTable('JORNADA_LEDGER');
        $saldosMes = collect();

        if ($temLedger) {
            $saldosMes = DB::table('JORNADA_LEDGER')
                ->whereIn('FUNCIONARIO_ID', $funcIds)
                ->where('COMPETENCIA', $comp)
                ->groupBy('FUNCIONARIO_ID')
                ->selectRaw('FUNCIONARIO_ID, SUM(COALESCE(HORAS_CREDITADAS,0)) as cred_mes, SUM(COALESCE(HORAS_DEBITADAS,0)) as deb_mes')
                ->get()
                ->keyBy('FUNCIONARIO_ID');
        }

        if ($saldosMes->isEmpty()) {
            $saldosMes = DB::table('BANCO_HORAS')
                ->whereIn('FUNCIONARIO_ID', $funcIds)
                ->where('COMPETENCIA', $comp)
                ->groupBy('FUNCIONARIO_ID')
                ->selectRaw('FUNCIONARIO_ID, SUM(COALESCE(HORAS_CREDITADAS,0)) as cred_mes, SUM(COALESCE(HORAS_DEBITADAS,0)) as deb_mes')
                ->get()
                ->keyBy('FUNCIONARIO_ID');
        }

        $impactados = $membros->map(function ($m) use ($saldosMes) {
            $saldoObj = $saldosMes->get($m->funcionario_id);
            $saldoMes = round((float) ($saldoObj->cred_mes ?? 0) - (float) ($saldoObj->deb_mes ?? 0), 2);
            $deficitHoras = $saldoMes < 0 ? round(abs($saldoMes), 2) : 0.0;

            $valorHoraCfg = (float) ($m->valor_hora_desconto ?? 0);
            $remuneracao = (float) ($m->remuneracao ?? 0);
            $valorHoraBase = $valorHoraCfg > 0 ? $valorHoraCfg : ($remuneracao > 0 ? round($remuneracao / 220, 2) : 0);
            $descontoEstimado = round($deficitHoras * $valorHoraBase, 2);

            return [
                'funcionario_id' => (int) $m->funcionario_id,
                'nome' => $m->nome,
                'matricula' => $m->matricula,
                'setor' => $m->setor,
                'cargo' => $m->cargo ?? '—',
                'saldo_mes' => $saldoMes,
                'deficit_horas' => $deficitHoras,
                'valor_hora_desconto' => $valorHoraBase,
                'desconto_estimado' => $descontoEstimado,
            ];
        })->filter(fn($m) => $m['deficit_horas'] > 0)
            ->sortByDesc('desconto_estimado')
            ->values();

        return response()->json([
            'competencia' => $comp,
            'escopo' => $isAdmin ? 'global' : 'setor',
            'resumo' => [
                'total_funcionarios' => $membros->count(),
                'total_impactados' => $impactados->count(),
                'desconto_total_estimado' => round((float) $impactados->sum('desconto_estimado'), 2),
            ],
            'membros_impacto' => $impactados,
        ]);
    } catch (\Throwable $e) {
        return response()->json(['erro' => $e->getMessage()], 500);
    }
});

// POST /banco-horas/equipe/notificacao-operacional — histórico por setor/competência
Route::post('/banco-horas/equipe/notificacao-operacional', function (\Illuminate\Http\Request $request) {
    try {
        $user = Auth::user();
        $dados = $request->validate([
            'competencia' => ['required', 'regex:/^\d{4}-\d{2}$/'],
            'setor' => ['required', 'string', 'max:180'],
            'tipo' => ['required', 'in:criticos,escala,notificar'],
            'alvo' => ['nullable', 'in:eu,gestores,todos_funcionarios'],
            'mensagem' => ['nullable', 'string', 'max:1000'],
        ]);

        $tipoMap = [
            'criticos' => 'Ação sobre funcionários críticos',
            'escala' => 'Ajuste de cobertura de escala',
            'notificar' => 'Acionamento de coordenação',
        ];
        $titulo = $tipoMap[$dados['tipo']] ?? 'Ação operacional';
        $body = $dados['mensagem'] ?: ('Setor ' . $dados['setor'] . ' | Competência ' . $dados['competencia'] . ' | ' . $titulo);
        $alvo = $dados['alvo'] ?? 'eu';

        if (!\Illuminate\Support\Facades\Schema::hasTable('NOTIFICACAO')) {
            return response()->json(['erro' => 'Tabela NOTIFICACAO indisponível.'], 422);
        }

        $isAdmin = DB::table('USUARIO_PERFIL')
            ->where('USUARIO_ID', $user->USUARIO_ID ?? 0)
            ->where('USUARIO_PERFIL_ATIVO', 1)
            ->where('PERFIL_ID', '<=', 2)
            ->exists();
        if (!$isAdmin && $alvo !== 'eu') {
            $alvo = 'eu';
        }

        $destinatarios = collect([(int) ($user->USUARIO_ID ?? 0)])->filter(fn($id) => $id > 0);
        if ($isAdmin && $alvo === 'gestores' && \Illuminate\Support\Facades\Schema::hasTable('USUARIO_PERFIL') && \Illuminate\Support\Facades\Schema::hasTable('PERFIL')) {
            $destinatarios = DB::table('USUARIO_PERFIL as up')
                ->join('PERFIL as p', 'p.PERFIL_ID', '=', 'up.PERFIL_ID')
                ->where('up.USUARIO_PERFIL_ATIVO', 1)
                ->where(function ($q) {
                    $q->where('p.PERFIL_NOME', 'like', '%Gestor%')
                        ->orWhere('p.PERFIL_NOME', 'like', '%Coordenador%')
                        ->orWhere('p.PERFIL_NOME', 'like', '%Diretor%');
                })
                ->pluck('up.USUARIO_ID')
                ->unique()
                ->values();
            if ($destinatarios->isEmpty()) {
                $destinatarios = collect([(int) ($user->USUARIO_ID ?? 0)])->filter(fn($id) => $id > 0);
            }
        } elseif ($isAdmin && $alvo === 'todos_funcionarios' && \Illuminate\Support\Facades\Schema::hasTable('FUNCIONARIO')) {
            $destinatarios = DB::table('FUNCIONARIO')
                ->whereNotNull('USUARIO_ID')
                ->pluck('USUARIO_ID')
                ->unique()
                ->values();
            if ($destinatarios->isEmpty()) {
                $destinatarios = collect([(int) ($user->USUARIO_ID ?? 0)])->filter(fn($id) => $id > 0);
            }
        }

        $url = '/banco-horas?modo=equipe&setor=' . urlencode($dados['setor']) . '&competencia=' . $dados['competencia'];
        foreach ($destinatarios as $uid) {
            DB::table('NOTIFICACAO')->insert([
                'USUARIO_ID' => (int) $uid,
                'NOTIFICACAO_TITULO' => 'Governança operacional — ' . $titulo,
                'NOTIFICACAO_BODY' => $body,
                'NOTIFICACAO_TIPO' => 'bh_operacional',
                'NOTIFICACAO_ICONE' => '🧭',
                'NOTIFICACAO_URL' => $url,
                'NOTIFICACAO_LIDA' => 0,
                'NOTIFICACAO_DT_CRIACAO' => now(),
            ]);
        }

        return response()->json(['ok' => true, 'alvo' => $alvo, 'destinatarios' => $destinatarios->count()]);
    } catch (\Throwable $e) {
        return response()->json(['erro' => $e->getMessage()], 500);
    }
});

Route::get('/banco-horas/equipe/notificacao-operacional', function (\Illuminate\Http\Request $request) {
    try {
        $user = Auth::user();
        $comp = $request->competencia ?? now()->format('Y-m');
        $setor = (string) ($request->setor ?? '');
        if (!\Illuminate\Support\Facades\Schema::hasTable('NOTIFICACAO')) {
            return response()->json(['historico' => []]);
        }

        $q = DB::table('NOTIFICACAO')
            ->where('USUARIO_ID', (int) ($user->USUARIO_ID ?? 0))
            ->whereIn('NOTIFICACAO_TIPO', ['bh_operacional', 'banco_horas_operacional'])
            ->where('NOTIFICACAO_URL', 'like', '%competencia=' . $comp . '%');
        if ($setor !== '') {
            $q->where('NOTIFICACAO_URL', 'like', '%setor=' . urlencode($setor) . '%');
        }
        $rows = $q->orderByDesc('NOTIFICACAO_ID')
            ->limit(12)
            ->get([
                'NOTIFICACAO_ID as id',
                'NOTIFICACAO_TITULO as titulo',
                'NOTIFICACAO_BODY as body',
                'NOTIFICACAO_DT_CRIACAO as data',
            ]);

        return response()->json(['historico' => $rows]);
    } catch (\Throwable $e) {
        return response()->json(['erro' => $e->getMessage()], 500);
    }
});

