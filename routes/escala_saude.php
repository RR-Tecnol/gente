<?php

use Illuminate\Support\Facades\DB;

// ══════════════════════════════════════════════════════════════════
// ESCALA SAÚDE — Detecção de furos de cobertura para profissionais
// Herda prefix api/v3 + auth do web.php — NÃO abrir Route::group aqui
//
// "Furo" = slot de data/turno onde o profissional escalado está
// ausente (atestado/afastamento validado) sem substituto registrado.
// ══════════════════════════════════════════════════════════════════

/**
 * GET /api/v3/escala-saude/furos
 * Params: competencia (MM/YYYY, obrigatório), setor_id, data_inicio, data_fim
 */
Route::get('/escala-saude/furos', function () {
    try {
        $competencia = request('competencia');
        if (!$competencia) {
            return response()->json(['erro' => 'Parâmetro competencia é obrigatório (MM/YYYY).'], 422);
        }

        // Aceitar tanto MM/YYYY (padrão escala) quanto AAAAMM (padrão motor folha)
        if (preg_match('/^(\d{6})$/', $competencia)) {
            // Converter AAAAMM → MM/YYYY
            $competencia = substr($competencia, 4, 2) . '/' . substr($competencia, 0, 4);
        } elseif (!preg_match('/^\d{2}\/\d{4}$/', $competencia)) {
            return response()->json(['erro' => 'Formato inválido. Use MM/YYYY ou AAAAMM.'], 422);
        }
        $setorId    = request('setor_id');
        $dataInicio = request('data_inicio');
        $dataFim    = request('data_fim');

        // 1. Buscar itens de escala da competência
        $escalasQuery = DB::table('ESCALA as e')
            ->join('DETALHE_ESCALA as de', 'de.ESCALA_ID', '=', 'e.ESCALA_ID')
            ->join('DETALHE_ESCALA_ITEM as dei', 'dei.DETALHE_ESCALA_ID', '=', 'de.DETALHE_ESCALA_ID')
            ->join('FUNCIONARIO as f', 'f.FUNCIONARIO_ID', '=', 'de.FUNCIONARIO_ID')
            ->join('PESSOA as p', 'p.PESSOA_ID', '=', 'f.PESSOA_ID')
            ->leftJoin('LOTACAO as l', function ($j) {
                $j->on('l.FUNCIONARIO_ID', '=', 'f.FUNCIONARIO_ID')
                  ->whereNull('l.LOTACAO_DATA_FIM');
            })
            ->leftJoin('SETOR as s', 's.SETOR_ID', '=', 'l.SETOR_ID')
            ->where('e.ESCALA_COMPETENCIA', $competencia);

        if ($setorId)    $escalasQuery->where('l.SETOR_ID', $setorId);
        if ($dataInicio) $escalasQuery->where('dei.DETALHE_ESCALA_ITEM_DATA', '>=', $dataInicio);
        if ($dataFim)    $escalasQuery->where('dei.DETALHE_ESCALA_ITEM_DATA', '<=', $dataFim);

        $itensEscala = $escalasQuery->select(
            'dei.DETALHE_ESCALA_ITEM_ID as item_id',
            'dei.DETALHE_ESCALA_ITEM_DATA as data',
            'dei.TURNO_SIGLA as turno',
            'de.FUNCIONARIO_ID as funcionario_id',
            'p.PESSOA_NOME as nome',
            'f.FUNCIONARIO_MATRICULA as matricula',
            'de.DETALHE_ESCALA_CARGO as cargo',
            'l.SETOR_ID as setor_id',
            's.SETOR_NOME as setor'
        )->get();


        if ($itensEscala->isEmpty()) {
            return response()->json([
                'furos' => [], 'total_furos' => 0, 'competencia' => $competencia,
                'aviso' => 'Nenhum item de escala encontrado para esta competência.',
            ]);
        }

        $funcionarioIds = $itensEscala->pluck('funcionario_id')->unique()->values();
        $datas = $itensEscala->pluck('data')->unique()->sort()->values();
        $periodoInicio = $datas->first();
        $periodoFim    = $datas->last();

        // 2. Atestados validados no período
        $atestados = collect();
        try {
            $atestados = DB::table('ATESTADO_MEDICO')
                ->whereIn('FUNCIONARIO_ID', $funcionarioIds)
                ->where('STATUS', 'VALIDADO')
                ->where('ATESTADO_DATA', '<=', $periodoFim)
                ->whereRaw("date(ATESTADO_DATA, '+' || ATESTADO_DIAS || ' days') >= ?", [$periodoInicio])
                ->select('FUNCIONARIO_ID', 'ATESTADO_DATA', 'ATESTADO_DIAS')
                ->get();
        } catch (\Throwable $e) {}

        // 3. Afastamentos no período (fallback)
        $afastamentos = collect();
        try {
            $afastamentos = DB::table('AFASTAMENTO')
                ->whereIn('FUNCIONARIO_ID', $funcionarioIds)
                ->whereIn('AFASTAMENTO_STATUS', ['APROVADO', 'VALIDADO', 'aprovado'])
                ->where('AFASTAMENTO_DATA_INICIO', '<=', $periodoFim)
                ->where('AFASTAMENTO_DATA_FIM', '>=', $periodoInicio)
                ->select('FUNCIONARIO_ID', 'AFASTAMENTO_DATA_INICIO', 'AFASTAMENTO_DATA_FIM')
                ->get();
        } catch (\Throwable $e) {}


        // 4. Substituições registradas
        $substituicoes = collect();
        try {
            $substituicoes = DB::table('SUBSTITUICAO_ESCALA')
                ->where('ESCALA_COMPETENCIA', $competencia)
                ->whereNotNull('SUBSTITUTO_ID')
                ->pluck('DETALHE_ESCALA_ITEM_ID')
                ->flip(); // usar como hash set
        } catch (\Throwable $e) {}

        // 5. Cruzar: ausente SEM substituto = FURO
        $furos = [];
        foreach ($itensEscala as $item) {
            if (isset($substituicoes[$item->item_id])) continue;

            $dataItem = $item->data;
            $funcId   = $item->funcionario_id;
            $ausente  = false;
            $motivo   = null;

            foreach ($atestados->where('FUNCIONARIO_ID', $funcId) as $at) {
                $fim = date('Y-m-d', strtotime($at->ATESTADO_DATA . ' +' . ($at->ATESTADO_DIAS - 1) . ' days'));
                if ($dataItem >= $at->ATESTADO_DATA && $dataItem <= $fim) {
                    $ausente = true; $motivo = 'Atestado médico validado'; break;
                }
            }
            if (!$ausente) {
                foreach ($afastamentos->where('FUNCIONARIO_ID', $funcId) as $af) {
                    if ($dataItem >= $af->AFASTAMENTO_DATA_INICIO && $dataItem <= $af->AFASTAMENTO_DATA_FIM) {
                        $ausente = true; $motivo = 'Afastamento registrado'; break;
                    }
                }
            }
            if ($ausente) {
                $furos[] = [
                    'item_id'        => $item->item_id,
                    'data'           => $dataItem,
                    'turno'          => $item->turno ?? '—',
                    'setor_id'       => $item->setor_id,
                    'setor'          => $item->setor ?? 'Setor não informado',
                    'funcionario_id' => $funcId,
                    'nome'           => $item->nome,
                    'matricula'      => $item->matricula,
                    'cargo'          => $item->cargo ?? '—',
                    'motivo_ausencia'=> $motivo,
                    'tem_substituto' => false,
                ];
            }
        }

        usort($furos, fn($a, $b) => strcmp($a['data'] . $a['setor'], $b['data'] . $b['setor']));

        return response()->json([
            'furos'              => $furos,
            'total_furos'        => count($furos),
            'total_slots_escala' => $itensEscala->count(),
            'competencia'        => $competencia,
            'periodo'            => ['inicio' => $periodoInicio, 'fim' => $periodoFim],
        ]);

    } catch (\Throwable $e) {
        return response()->json(['erro' => $e->getMessage()], 500);
    }
});


/**
 * GET /api/v3/escala-saude/cobertura/{setor_id}/{data}
 * Retorna profissionais por turno em um setor numa data específica.
 */
Route::get('/escala-saude/cobertura/{setor_id}/{data}', function (int $setorId, string $data) {
    try {
        $competencia = date('m/Y', strtotime($data));

        $slots = DB::table('ESCALA as e')
            ->join('DETALHE_ESCALA as de', 'de.ESCALA_ID', '=', 'e.ESCALA_ID')
            ->join('DETALHE_ESCALA_ITEM as dei', 'dei.DETALHE_ESCALA_ID', '=', 'de.DETALHE_ESCALA_ID')
            ->join('FUNCIONARIO as f', 'f.FUNCIONARIO_ID', '=', 'de.FUNCIONARIO_ID')
            ->join('PESSOA as p', 'p.PESSOA_ID', '=', 'f.PESSOA_ID')
            ->leftJoin('LOTACAO as l', function ($j) {
                $j->on('l.FUNCIONARIO_ID', '=', 'f.FUNCIONARIO_ID')
                  ->whereNull('l.LOTACAO_DATA_FIM');
            })
            ->where('e.ESCALA_COMPETENCIA', $competencia)
            ->where('dei.DETALHE_ESCALA_ITEM_DATA', $data)
            ->where('l.SETOR_ID', $setorId)
            ->select('dei.TURNO_SIGLA as turno', 'de.FUNCIONARIO_ID as funcionario_id',
                     'p.PESSOA_NOME as nome', 'f.FUNCIONARIO_MATRICULA as matricula',
                     'de.DETALHE_ESCALA_CARGO as cargo')
            ->get();

        $porTurno = $slots->groupBy('turno')->map(fn($profs, $turno) => [
            'turno' => $turno, 'total' => $profs->count(), 'profissionais' => $profs->values(),
        ])->values();

        return response()->json([
            'data' => $data, 'setor_id' => $setorId,
            'por_turno' => $porTurno, 'total_geral' => $slots->count(),
        ]);
    } catch (\Throwable $e) {
        return response()->json(['erro' => $e->getMessage()], 500);
    }
});
