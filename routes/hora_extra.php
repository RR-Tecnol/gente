<?php
// ══════════════════════════════════════════════════════════════════
// HORA EXTRA E PLANTÃO EXTRA — não usar use statements aqui
// ══════════════════════════════════════════════════════════════════

// ── GET: Listar registros de hora extra (com filtros) ─────────────
Route::get('/hora-extra', function (\Illuminate\Http\Request $request) {
    try {
        $query = DB::table('HORA_EXTRA as he')
            ->join('FUNCIONARIO as f', 'f.FUNCIONARIO_ID', '=', 'he.FUNCIONARIO_ID')
            ->join('PESSOA as p', 'p.PESSOA_ID', '=', 'f.PESSOA_ID')
            ->leftJoin('UNIDADE as u', 'u.UNIDADE_ID', '=', 'he.UNIDADE_ID')
            ->leftJoin('SETOR as s', 's.SETOR_ID', '=', 'he.SETOR_ID');

        if ($request->competencia)
            $query->where('he.COMPETENCIA', $request->competencia);
        if ($request->unidade_id)
            $query->where('he.UNIDADE_ID', $request->unidade_id);
        if ($request->funcionario_id)
            $query->where('he.FUNCIONARIO_ID', $request->funcionario_id);
        if ($request->status)
            $query->where('he.STATUS', $request->status);

        $lista = $query->select(
            'he.*',
            'p.PESSOA_NOME as nome',
            'f.FUNCIONARIO_MATRICULA as matricula',
            'u.UNIDADE_NOME as secretaria',
            's.SETOR_NOME as setor'
        )->orderBy('he.DATA_REALIZACAO', 'desc')->get();

        // Resumo por secretaria
        $resumo = $lista->groupBy('secretaria')->map(fn($g) => [
            'total_horas' => round($g->sum('TOTAL_HORAS'), 2),
            'total_valor' => round($g->sum('VALOR_CALCULADO'), 2),
            'qtd_servidores' => $g->pluck('FUNCIONARIO_ID')->unique()->count(),
        ]);

        // Lista de unidades para filtro
        $unidades = DB::table('UNIDADE')->select('UNIDADE_ID as id', 'UNIDADE_NOME as nome')->get();

        return response()->json([
            'lista' => $lista,
            'resumo' => $resumo,
            'unidades' => $unidades,
            'total_horas' => round($lista->sum('TOTAL_HORAS'), 2),
            'total_valor' => round($lista->sum('VALOR_CALCULADO'), 2),
        ]);
    } catch (\Throwable $e) {
        return response()->json(['erro' => $e->getMessage()], 500);
    }
});

// ── POST: Lançar hora extra ────────────────────────────────────────
Route::post('/hora-extra', function (\Illuminate\Http\Request $request) {
    try {
        $user = Auth::user();
        $funcId = $request->funcionario_id;
        $func = DB::table('FUNCIONARIO as f')
            ->join('PESSOA as p', 'p.PESSOA_ID', '=', 'f.PESSOA_ID')
            ->leftJoin('CARGO as c', 'c.CARGO_ID', '=', 'f.CARGO_ID')
            ->where('f.FUNCIONARIO_ID', $funcId)
            ->select('f.*', 'p.PESSOA_NOME', 'c.CARGO_SALARIO', 'c.CARGO_CARGA_HORARIA')
            ->first();
        if (!$func)
            return response()->json(['erro' => 'Servidor não encontrado.'], 404);

        // BUG-HE-01: usa CARGO_CARGA_HORARIA do cadastro (Sprint 3a) em vez de 220 fixo
        $salario = (float) ($request->valor_hora_base ?? ($func->CARGO_SALARIO ?? 0));
        $chMensal = (int) ($func->CARGO_CARGA_HORARIA ?? 220);  // fallback 220h
        $valorHora = ($salario > 0 && $chMensal > 0) ? $salario / $chMensal : 0;
        $percentual = $request->percentual ?? 50.0;
        $totalHoras = (float) ($request->total_horas ?? 0);
        $valorCalc = round($valorHora * (1 + $percentual / 100) * $totalHoras, 2);

        // Lotação atual para capturar secretaria
        $lot = DB::table('LOTACAO as l')
            ->leftJoin('SETOR as s', 's.SETOR_ID', '=', 'l.SETOR_ID')
            ->where('l.FUNCIONARIO_ID', $funcId)
            ->whereNull('l.LOTACAO_DATA_FIM')
            ->select('l.SETOR_ID', 's.UNIDADE_ID')
            ->first();

        $id = DB::table('HORA_EXTRA')->insertGetId([
            'FUNCIONARIO_ID' => $funcId,
            'UNIDADE_ID' => $request->unidade_id ?? $lot?->UNIDADE_ID,
            'SETOR_ID' => $request->setor_id ?? $lot?->SETOR_ID,
            'COMPETENCIA' => $request->competencia ?? now()->format('Y-m'),
            'DATA_REALIZACAO' => $request->data_realizacao,
            'HORA_INICIO' => $request->hora_inicio,
            'HORA_FIM' => $request->hora_fim,
            'TOTAL_HORAS' => $totalHoras,
            'TIPO_HORA_EXTRA' => $request->tipo_hora_extra ?? '50_PORCENTO',
            'PERCENTUAL' => $percentual,
            'VALOR_HORA_BASE' => round($valorHora, 4),
            'VALOR_CALCULADO' => $valorCalc,
            'AUTORIZADO_POR' => $user?->USUARIO_ID,
            'STATUS' => 'PENDENTE',
            'OBSERVACAO' => $request->observacao,
            'created_at' => now(),
            'updated_at' => now(),
        ]);
        return response()->json(['ok' => true, 'id' => $id, 'valor_calculado' => $valorCalc]);
    } catch (\Throwable $e) {
        return response()->json(['erro' => $e->getMessage()], 500);
    }
});

// ── PATCH: Aprovar/Rejeitar hora extra ────────────────────────────
Route::patch('/hora-extra/{id}/status', function (\Illuminate\Http\Request $request, $id) {
    try {
        $novoStatus = $request->status; // APROVADA | REJEITADA
        $updated = DB::table('HORA_EXTRA')->where('HORA_EXTRA_ID', $id)->update([
            'STATUS' => $novoStatus,
            'updated_at' => now(),
        ]);
        if (!$updated) {
            return response()->json(['erro' => 'Registro de hora extra não encontrado ou sem alterações.'], 404);
        }
        return response()->json(['ok' => true]);
    } catch (\Throwable $e) {
        return response()->json(['erro' => $e->getMessage()], 500);
    }
});

// ── GET: Relatório agregado de horas extras por secretaria ─────────
Route::get('/hora-extra/relatorio-secretaria', function (\Illuminate\Http\Request $request) {
    try {
        $competencia = $request->competencia ?? now()->format('Y-m');
        $dados = DB::table('HORA_EXTRA as he')
            ->join('UNIDADE as u', 'u.UNIDADE_ID', '=', 'he.UNIDADE_ID')
            ->where('he.COMPETENCIA', $competencia)
            ->whereIn('he.STATUS', ['APROVADA', 'INCLUIDA_FOLHA', 'PAGA'])
            ->groupBy('u.UNIDADE_ID', 'u.UNIDADE_NOME')
            ->select(
                'u.UNIDADE_ID',
                'u.UNIDADE_NOME as secretaria',
                DB::raw('COUNT(DISTINCT he.FUNCIONARIO_ID) as qtd_servidores'),
                DB::raw('SUM(he.TOTAL_HORAS) as total_horas'),
                DB::raw('SUM(he.VALOR_CALCULADO) as total_valor'),
                DB::raw("SUM(CASE WHEN he.TIPO_HORA_EXTRA = '50_PORCENTO' THEN he.TOTAL_HORAS ELSE 0 END) as horas_50"),
                DB::raw("SUM(CASE WHEN he.TIPO_HORA_EXTRA = '100_PORCENTO' THEN he.TOTAL_HORAS ELSE 0 END) as horas_100"),
                DB::raw("SUM(CASE WHEN he.TIPO_HORA_EXTRA = 'FERIADO' THEN he.TOTAL_HORAS ELSE 0 END) as horas_feriado")
            )->get();
        return response()->json(['competencia' => $competencia, 'dados' => $dados]);
    } catch (\Throwable $e) {
        return response()->json(['erro' => $e->getMessage()], 500);
    }
});

// ══ PLANTÃO EXTRA ══════════════════════════════════════════════════

Route::get('/plantao-extra', function (\Illuminate\Http\Request $request) {
    try {
        if (!Schema::hasTable('PLANTAO_EXTRA')) {
            return response()->json(['lista' => [], 'total_valor' => 0, 'fallback' => true]);
        }

        $colsPe = Schema::getColumnListing('PLANTAO_EXTRA');
        $hasUnidade = in_array('UNIDADE_ID', $colsPe, true);
        $hasComp = in_array('COMPETENCIA', $colsPe, true);
        $hasDataPlantaoLegacy = in_array('DATA_PLANTAO', $colsPe, true);
        $hasDataPlantao = in_array('PLANTAO_DATA', $colsPe, true);
        $colData = $hasDataPlantaoLegacy ? 'DATA_PLANTAO' : ($hasDataPlantao ? 'PLANTAO_DATA' : null);

        $query = DB::table('PLANTAO_EXTRA as pe')
            ->join('FUNCIONARIO as f', 'f.FUNCIONARIO_ID', '=', 'pe.FUNCIONARIO_ID')
            ->join('PESSOA as p', 'p.PESSOA_ID', '=', 'f.PESSOA_ID');
        if ($hasUnidade) {
            $query->leftJoin('UNIDADE as u', 'u.UNIDADE_ID', '=', 'pe.UNIDADE_ID');
        }

        if ($request->competencia) {
            if ($hasComp) {
                $query->where('pe.COMPETENCIA', $request->competencia);
            } elseif ($colData) {
                [$ano, $mes] = explode('-', $request->competencia);
                $query->whereYear("pe.{$colData}", (int) $ano)->whereMonth("pe.{$colData}", (int) $mes);
            }
        }
        if ($request->unidade_id && $hasUnidade) {
            $query->where('pe.UNIDADE_ID', $request->unidade_id);
        }

        $lista = $query->select(
            'pe.*',
            'p.PESSOA_NOME as nome',
            'f.FUNCIONARIO_MATRICULA as matricula',
            $hasUnidade ? 'u.UNIDADE_NOME as secretaria' : DB::raw("'' as secretaria")
        )
            ->orderBy("pe." . ($colData ?: 'PLANTAO_ID'), 'desc')
            ->get()
            ->map(function ($r) {
                $arr = (array) $r;
                $arr['DATA_PLANTAO'] = $arr['DATA_PLANTAO'] ?? ($arr['PLANTAO_DATA'] ?? null);
                $arr['TOTAL_HORAS'] = $arr['TOTAL_HORAS'] ?? ($arr['PLANTAO_HORAS'] ?? 0);
                $arr['STATUS'] = $arr['STATUS'] ?? ($arr['PLANTAO_STATUS'] ?? null);
                $arr['VALOR_CALCULADO'] = $arr['VALOR_CALCULADO'] ?? 0;
                return $arr;
            });

        return response()->json([
            'lista' => $lista,
            'total_valor' => round($lista->sum('VALOR_CALCULADO'), 2),
        ]);
    } catch (\Throwable $e) {
        return response()->json(['erro' => $e->getMessage()], 500);
    }
});

Route::post('/plantao-extra', function (\Illuminate\Http\Request $request) {
    try {
        if (!Schema::hasTable('PLANTAO_EXTRA')) {
            return response()->json(['erro' => 'Tabela PLANTAO_EXTRA não encontrada.'], 500);
        }

        $colsPe = Schema::getColumnListing('PLANTAO_EXTRA');
        $user = Auth::user();
        $totalHoras = (float) ($request->total_horas ?? 0);
        $horasNot = (float) ($request->horas_noturnas ?? 0);
        $valorHora = (float) ($request->valor_hora_plantao ?? 0);
        $valNot = round($horasNot * $valorHora * 0.20, 2);
        $valorCalc = round(($totalHoras * $valorHora) + $valNot, 2);

        $lot = DB::table('LOTACAO as l')
            ->leftJoin('SETOR as s', 's.SETOR_ID', '=', 'l.SETOR_ID')
            ->where('l.FUNCIONARIO_ID', $request->funcionario_id)
            ->whereNull('l.LOTACAO_DATA_FIM')
            ->select('l.SETOR_ID', 's.UNIDADE_ID')->first();

        $payload = [];
        if (in_array('FUNCIONARIO_ID', $colsPe, true))
            $payload['FUNCIONARIO_ID'] = $request->funcionario_id;
        if (in_array('UNIDADE_ID', $colsPe, true))
            $payload['UNIDADE_ID'] = $request->unidade_id ?? $lot?->UNIDADE_ID;
        if (in_array('SETOR_ID', $colsPe, true))
            $payload['SETOR_ID'] = $request->setor_id ?? $lot?->SETOR_ID;
        if (in_array('COMPETENCIA', $colsPe, true))
            $payload['COMPETENCIA'] = $request->competencia ?? now()->format('Y-m');

        $dataPlantao = $request->data_plantao ?? $request->data ?? now()->toDateString();
        if (in_array('DATA_PLANTAO', $colsPe, true))
            $payload['DATA_PLANTAO'] = $dataPlantao;
        if (in_array('PLANTAO_DATA', $colsPe, true))
            $payload['PLANTAO_DATA'] = $dataPlantao;
        if (in_array('HORA_INICIO', $colsPe, true))
            $payload['HORA_INICIO'] = $request->hora_inicio ?? $request->horaIni;
        if (in_array('HORA_FIM', $colsPe, true))
            $payload['HORA_FIM'] = $request->hora_fim ?? $request->horaFim;
        if (in_array('TOTAL_HORAS', $colsPe, true))
            $payload['TOTAL_HORAS'] = $totalHoras;
        if (in_array('PLANTAO_HORAS', $colsPe, true))
            $payload['PLANTAO_HORAS'] = $totalHoras;
        if (in_array('PLANTAO_TURNO', $colsPe, true))
            $payload['PLANTAO_TURNO'] = $request->turno ?? 'D';
        if (in_array('ESCALA_ID', $colsPe, true))
            $payload['ESCALA_ID'] = $request->escala_id;
        if (in_array('VALOR_HORA_PLANTAO', $colsPe, true))
            $payload['VALOR_HORA_PLANTAO'] = $valorHora;
        if (in_array('ADICIONAL_NOTURNO', $colsPe, true))
            $payload['ADICIONAL_NOTURNO'] = $horasNot > 0;
        if (in_array('HORAS_NOTURNAS', $colsPe, true))
            $payload['HORAS_NOTURNAS'] = $horasNot;
        if (in_array('VALOR_ADICIONAL_NOTURNO', $colsPe, true))
            $payload['VALOR_ADICIONAL_NOTURNO'] = $valNot;
        if (in_array('VALOR_CALCULADO', $colsPe, true))
            $payload['VALOR_CALCULADO'] = $valorCalc;
        if (in_array('AUTORIZADO_POR', $colsPe, true))
            $payload['AUTORIZADO_POR'] = $user?->USUARIO_ID;
        if (in_array('STATUS', $colsPe, true))
            $payload['STATUS'] = 'PENDENTE';
        if (in_array('PLANTAO_STATUS', $colsPe, true))
            $payload['PLANTAO_STATUS'] = 'PENDENTE';
        if (in_array('PLANTAO_MOTIVO', $colsPe, true))
            $payload['PLANTAO_MOTIVO'] = $request->observacao ?? $request->motivo;
        if (in_array('created_at', $colsPe, true))
            $payload['created_at'] = now();
        if (in_array('updated_at', $colsPe, true))
            $payload['updated_at'] = now();

        $id = DB::table('PLANTAO_EXTRA')->insertGetId($payload);
        return response()->json(['ok' => true, 'id' => $id, 'valor_calculado' => $valorCalc]);
    } catch (\Throwable $e) {
        return response()->json(['erro' => $e->getMessage()], 500);
    }
});

// ── Folha por secretaria (etapa 4) ────────────────────────────────
Route::get('/folha/por-secretaria', function (\Illuminate\Http\Request $request) {
    try {
        $query = DB::table('DETALHE_FOLHA as df')
            ->join('FUNCIONARIO as f', 'f.FUNCIONARIO_ID', '=', 'df.FUNCIONARIO_ID')
            ->join('PESSOA as p', 'p.PESSOA_ID', '=', 'f.PESSOA_ID')
            ->join('FOLHA as fl', 'fl.FOLHA_ID', '=', 'df.FOLHA_ID')
            ->leftJoin('UNIDADE as u', 'u.UNIDADE_ID', '=', 'df.UNIDADE_ID')
            ->leftJoin('SETOR as s', 's.SETOR_ID', '=', 'df.SETOR_ID');

        if ($request->competencia)
            $query->where('fl.FOLHA_COMPETENCIA', $request->competencia);
        if ($request->unidade_id)
            $query->where('df.UNIDADE_ID', $request->unidade_id);

        $lista = $query->select(
            'p.PESSOA_NOME as nome',
            'f.FUNCIONARIO_MATRICULA as matricula',
            'u.UNIDADE_NOME as secretaria',
            's.SETOR_NOME as setor',
            'df.DETALHE_FOLHA_PROVENTOS as proventos',
            'df.DETALHE_FOLHA_DESCONTOS as descontos',
            'df.DETALHE_FOLHA_LIQUIDO as liquido'
        )->get();

        return response()->json(['lista' => $lista, 'total_liquido' => round($lista->sum('liquido'), 2)]);
    } catch (\Throwable $e) {
        return response()->json(['erro' => $e->getMessage()], 500);
    }
});

Route::get('/folha/resumo-secretarias', function (\Illuminate\Http\Request $request) {
    try {
        $competencia = $request->competencia ?? now()->format('Y-m');
        $resumo = DB::table('DETALHE_FOLHA as df')
            ->join('FOLHA as fl', 'fl.FOLHA_ID', '=', 'df.FOLHA_ID')
            ->leftJoin('UNIDADE as u', 'u.UNIDADE_ID', '=', 'df.UNIDADE_ID')
            ->where('fl.FOLHA_COMPETENCIA', $competencia)
            ->groupBy('u.UNIDADE_ID', 'u.UNIDADE_NOME')
            ->select(
                'u.UNIDADE_ID',
                'u.UNIDADE_NOME as secretaria',
                DB::raw('COUNT(df.FUNCIONARIO_ID) as qtd_servidores'),
                DB::raw('SUM(df.DETALHE_FOLHA_PROVENTOS) as total_proventos'),
                DB::raw('SUM(df.DETALHE_FOLHA_DESCONTOS) as total_descontos'),
                DB::raw('SUM(df.DETALHE_FOLHA_LIQUIDO) as total_liquido')
            )->get();
        return response()->json(['competencia' => $competencia, 'resumo' => $resumo]);
    } catch (\Throwable $e) {
        return response()->json(['erro' => $e->getMessage()], 500);
    }
});
