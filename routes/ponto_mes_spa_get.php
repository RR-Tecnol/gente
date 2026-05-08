<?php
// GET /api/v3/ponto — visão mês: batidas, feriado, metas de escala (teia) — ficheiro canónico Fase F
// Herda o grupo: prefix api/v3, middleware [web] (auth verificado no closure)

Route::get('/ponto', function (\Illuminate\Http\Request $request) {
    try {
        $user = \Illuminate\Support\Facades\Auth::user();
        if (!$user) {
            return response()->json(['erro' => 'Não autenticado.'], 401);
        }

        $func = \App\Models\Funcionario::where('USUARIO_ID', $user->USUARIO_ID ?? 0)->first();
        $funcionarioIdParam = $request->filled('funcionario_id') ? (int) $request->funcionario_id : null;
        if ($funcionarioIdParam && $funcionarioIdParam > 0) {
            $alvo = \App\Models\Funcionario::where('FUNCIONARIO_ID', $funcionarioIdParam)->first();
            if (! $alvo) {
                return response()->json(['erro' => 'Funcionário não encontrado.'], 404);
            }
            if (! $func || (int) $func->FUNCIONARIO_ID !== $funcionarioIdParam) {
                $temLotacaoFim = \Illuminate\Support\Facades\Schema::hasColumn('LOTACAO', 'LOTACAO_DATA_FIM');
                $setorAlvo = \Illuminate\Support\Facades\DB::table('LOTACAO')
                    ->where('FUNCIONARIO_ID', $funcionarioIdParam)
                    ->when($temLotacaoFim, fn ($q) => $q->whereNull('LOTACAO_DATA_FIM'))
                    ->orderByDesc('LOTACAO_ID')
                    ->value('SETOR_ID');
                if (! $setorAlvo) {
                    return response()->json(['erro' => 'Servidor sem lotação ativa para verificação de escopo.'], 403);
                }
                \App\Support\UnidadeEscopoUsuario::abortoSeSetorNaoAutorizado($user, (int) $setorAlvo, $request);
            }
            $func = $alvo;
        }
        if (! $func) {
            return response()->json(['registros' => []]);
        }

        // competencia = YYYY-MM
        $comp = $request->competencia ?? now()->format('Y-m');
        [$ano, $mes] = explode('-', $comp);
        $inicio = "{$ano}-{$mes}-01";
        $fim = date('Y-m-t', strtotime($inicio));

        $rows = \Illuminate\Support\Facades\DB::table('REGISTRO_PONTO')
            ->where('FUNCIONARIO_ID', $func->FUNCIONARIO_ID)
            ->whereBetween(\Illuminate\Support\Facades\DB::raw("CAST(REGISTRO_DATA_HORA AS DATE)"), [$inicio, $fim])
            ->orderBy('REGISTRO_DATA_HORA')
            ->get();

        // Agrupa por dia
        $porDia = [];
        foreach ($rows as $r) {
            $tipoRaw = strtolower(trim((string) ($r->REGISTRO_TIPO ?? '')));
            $tipoApi = match ($tipoRaw) {
                'saida_alm', 'saida_almoco' => 'saida_almoco',
                'ret_alm', 'retorno_almoco' => 'retorno_almoco',
                'entrada' => 'entrada',
                'saida' => 'saida',
                default => $r->REGISTRO_TIPO,
            };
            $dia = (int) date('j', strtotime($r->REGISTRO_DATA_HORA));
            $porDia[$dia][] = [
                'hora' => date('H:i', strtotime($r->REGISTRO_DATA_HORA)),
                'tipo' => $tipoApi,
                'id' => $r->REGISTRO_PONTO_ID,
            ];
        }

        $registros = [];
        foreach ($porDia as $dia => $batidas) {
            $registros[] = ['dia' => $dia, 'batidas' => $batidas];
        }

        $diasFeriado = [];
        try {
            $setorId = \Illuminate\Support\Facades\DB::table('LOTACAO')
                ->where('FUNCIONARIO_ID', $func->FUNCIONARIO_ID)
                ->whereNull('LOTACAO_DATA_FIM')
                ->value('SETOR_ID');
            /** @var \App\Services\HolidayCalendarService $holidayService */
            $holidayService = app(\App\Services\HolidayCalendarService::class);
            $datasFeriado = $holidayService->getEffectiveHolidayDatesForMonth(
                (int) $ano,
                (int) $mes,
                (int) $func->FUNCIONARIO_ID,
                $setorId ? (int) $setorId : null
            );

            $diasFeriado = collect($datasFeriado)
                ->map(fn($data) => (int) date('j', strtotime((string) $data)))
                ->filter(fn($d) => $d > 0)
                ->unique()
                ->values()
                ->all();
        } catch (\Throwable $e) {
            $diasFeriado = [];
        }

        $diasEscalaPrevista = [];
        $metaMinutosEscalaPorDia = [];
        try {
            if (
                \Illuminate\Support\Facades\Schema::hasTable('DETALHE_ESCALA') &&
                \Illuminate\Support\Facades\Schema::hasTable('DETALHE_ESCALA_ITEM') &&
                \Illuminate\Support\Facades\Schema::hasTable('TURNO')
            ) {
                $itensEscalaMes = \Illuminate\Support\Facades\DB::table('DETALHE_ESCALA as de')
                    ->join('DETALHE_ESCALA_ITEM as dei', 'dei.DETALHE_ESCALA_ID', '=', 'de.DETALHE_ESCALA_ID')
                    ->leftJoin('TURNO as t', 't.TURNO_ID', '=', 'dei.TURNO_ID')
                    ->where('de.FUNCIONARIO_ID', $func->FUNCIONARIO_ID)
                    ->whereBetween(\Illuminate\Support\Facades\DB::raw("CAST(dei.DETALHE_ESCALA_ITEM_DATA AS DATE)"), [$inicio, $fim])
                    ->selectRaw("
                        DAY(CAST(dei.DETALHE_ESCALA_ITEM_DATA AS DATE)) as dia,
                        t.TURNO_HORA_INICIO as turno_inicio,
                        t.TURNO_HORA_FIM as turno_fim
                    ")
                    ->get();

                $diasEscalaPrevista = $itensEscalaMes
                    ->map(fn($r) => (int) ($r->dia ?? 0))
                    ->filter(fn($d) => $d > 0)
                    ->unique()
                    ->values()
                    ->all();

                foreach ($itensEscalaMes as $itemEscala) {
                    $diaEscala = (int) ($itemEscala->dia ?? 0);
                    if ($diaEscala <= 0)
                        continue;
                    $ini = substr((string) ($itemEscala->turno_inicio ?? ''), 0, 5);
                    $fimTurno = substr((string) ($itemEscala->turno_fim ?? ''), 0, 5);
                    if (!preg_match('/^\d{2}:\d{2}$/', $ini) || !preg_match('/^\d{2}:\d{2}$/', $fimTurno)) {
                        continue;
                    }
                    [$hi, $mi] = array_map('intval', explode(':', $ini));
                    [$hf, $mf] = array_map('intval', explode(':', $fimTurno));
                    $iniMin = ($hi * 60) + $mi;
                    $fimMin = ($hf * 60) + $mf;
                    $duracao = $fimMin - $iniMin;
                    if ($duracao <= 0) {
                        $duracao += (24 * 60); // turno atravessa meia-noite
                    }
                    if ($duracao > 0) {
                        $metaMinutosEscalaPorDia[$diaEscala] = $duracao;
                    }
                }
            }
        } catch (\Throwable $e) {
            $diasEscalaPrevista = [];
            $metaMinutosEscalaPorDia = [];
        }

        return response()->json([
            'registros' => $registros,
            'dias_feriado' => $diasFeriado,
            'dias_escala_prevista' => $diasEscalaPrevista,
            'meta_minutos_escala_por_dia' => $metaMinutosEscalaPorDia,
            'funcionario_id' => (int) $func->FUNCIONARIO_ID,
        ]);
    } catch (\Illuminate\Http\Exceptions\HttpResponseException $e) {
        throw $e;
    } catch (\Throwable $e) {
        return response()->json(['registros' => [], 'erro' => $e->getMessage()]);
    }
})->name('api.v3.ponto.mes');
