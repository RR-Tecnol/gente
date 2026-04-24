<?php
use Illuminate\Support\Facades\Route;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Auth;

Route::get('/escala-trabalho', function (\Illuminate\Http\Request $request) {
    try {
        $mes = (int) ($request->mes ?? now()->month);
        $ano = (int) ($request->ano ?? now()->year);
        $comp = sprintf('%04d-%02d', $ano, $mes);
        $setorId = $request->filled('setor_id') ? (int) $request->setor_id : null;

        $temLotacaoFim = \Illuminate\Support\Facades\Schema::hasColumn('LOTACAO', 'LOTACAO_DATA_FIM');
        $temFuncionarioFim = \Illuminate\Support\Facades\Schema::hasColumn('FUNCIONARIO', 'FUNCIONARIO_DATA_FIM');
        $temObsItemEscala = \Illuminate\Support\Facades\Schema::hasColumn('DETALHE_ESCALA_ITEM', 'DETALHE_ESCALA_ITEM_OBS');

        $setores = DB::table('SETOR as s')
            ->select('s.SETOR_ID as id', 's.SETOR_NOME as nome')
            ->when(
                \Illuminate\Support\Facades\Schema::hasColumn('SETOR', 'SETOR_ATIVO'),
                fn($q) => $q->where('s.SETOR_ATIVO', 1)
            )
            ->orderBy('s.SETOR_NOME')
            ->get();

        $funcionarios = DB::table('FUNCIONARIO as f')
            ->join('PESSOA as p', 'p.PESSOA_ID', '=', 'f.PESSOA_ID')
            ->leftJoin('LOTACAO as l', function ($join) use ($temLotacaoFim) {
                $join->on('l.FUNCIONARIO_ID', '=', 'f.FUNCIONARIO_ID');
                if ($temLotacaoFim) {
                    $join->whereNull('l.LOTACAO_DATA_FIM');
                }
            })
            ->leftJoin('SETOR as s', 's.SETOR_ID', '=', 'l.SETOR_ID')
            ->when($temFuncionarioFim, fn($q) => $q->whereNull('f.FUNCIONARIO_DATA_FIM'))
            ->when($setorId, fn($q) => $q->where('l.SETOR_ID', $setorId))
            ->orderBy('p.PESSOA_NOME')
            ->select(
                'f.FUNCIONARIO_ID as id',
                'p.PESSOA_NOME as nome',
                DB::raw('MAX(s.SETOR_NOME) as setor')
            )
            ->groupBy('f.FUNCIONARIO_ID', 'p.PESSOA_NOME')
            ->limit(300)
            ->get();

        $rows = DB::table('ESCALA as e')
            ->join('DETALHE_ESCALA as de', 'de.ESCALA_ID', '=', 'e.ESCALA_ID')
            ->join('FUNCIONARIO as f', 'f.FUNCIONARIO_ID', '=', 'de.FUNCIONARIO_ID')
            ->join('PESSOA as p', 'p.PESSOA_ID', '=', 'f.PESSOA_ID')
            ->leftJoin('LOTACAO as l', function ($join) use ($temLotacaoFim) {
                $join->on('l.FUNCIONARIO_ID', '=', 'f.FUNCIONARIO_ID');
                if ($temLotacaoFim) {
                    $join->whereNull('l.LOTACAO_DATA_FIM');
                }
            })
            ->leftJoin('SETOR as s', 's.SETOR_ID', '=', 'l.SETOR_ID')
            ->leftJoin('DETALHE_ESCALA_ITEM as dei', 'dei.DETALHE_ESCALA_ID', '=', 'de.DETALHE_ESCALA_ID')
            ->leftJoin('TURNO as t', 't.TURNO_ID', '=', 'dei.TURNO_ID')
            ->where('e.ESCALA_COMPETENCIA', $comp)
            ->when($setorId, fn($q) => $q->where('e.SETOR_ID', $setorId))
            ->orderBy('p.PESSOA_NOME')
            ->select(
                'de.DETALHE_ESCALA_ID as detalhe_id',
                'de.FUNCIONARIO_ID as funcionario_id',
                'p.PESSOA_NOME as nome',
                DB::raw('COALESCE(MAX(s.SETOR_NOME), \'Sem setor\') as setor_nome'),
                'dei.DETALHE_ESCALA_ITEM_DATA as data_item',
                't.TURNO_SIGLA as turno_sigla',
                $temObsItemEscala ? DB::raw('MAX(dei.DETALHE_ESCALA_ITEM_OBS) as obs_item') : DB::raw('NULL as obs_item')
            )
            ->groupBy(
                'de.DETALHE_ESCALA_ID',
                'de.FUNCIONARIO_ID',
                'p.PESSOA_NOME',
                'dei.DETALHE_ESCALA_ITEM_DATA',
                't.TURNO_SIGLA'
            )
            ->get();

        $linhas = [];
        foreach ($rows as $r) {
            $funcId = (int) ($r->funcionario_id ?? 0);
            if (!isset($linhas[$funcId])) {
                $linhas[$funcId] = [
                    'funcionario_id' => $funcId,
                    'nome' => $r->nome ?? 'Funcionário',
                    'setor' => $r->setor_nome ?? 'Sem setor',
                    'dias' => [],
                ];
            }
            if (!empty($r->data_item)) {
                $dia = (int) date('d', strtotime($r->data_item));
                if ($dia > 0) {
                    $linhas[$funcId]['dias'][$dia] = [
                        'turno' => $r->turno_sigla ?? '',
                        'obs' => $r->obs_item ?? '',
                    ];
                }
            }
        }

        return response()->json([
            'competencia' => $comp,
            'escala' => array_values($linhas),
            'setores' => $setores,
            'funcionarios' => $funcionarios,
        ]);
    } catch (\Throwable $e) {
        return response()->json([
            'competencia' => sprintf('%04d-%02d', (int) ($request->ano ?? now()->year), (int) ($request->mes ?? now()->month)),
            'escala' => [],
            'setores' => [],
            'funcionarios' => [],
            'erro' => $e->getMessage(),
        ]);
    }
});

Route::post('/escala-trabalho', function (\Illuminate\Http\Request $request) {
    $request->validate([
        'funcionario_id' => 'required|integer',
        'data' => 'required|date',
        'turno' => 'required|string|max:10',
    ]);

    try {
        $payload = DB::transaction(function () use ($request) {
            $dataEscala = \Carbon\Carbon::parse($request->data)->toDateString();
            $competencia = \Carbon\Carbon::parse($dataEscala)->format('Y-m');
            $funcionarioId = (int) $request->funcionario_id;
            $turnoSigla = strtoupper(trim((string) $request->turno));

            $setorId = DB::table('LOTACAO as l')
                ->where('l.FUNCIONARIO_ID', $funcionarioId)
                ->when(
                    \Illuminate\Support\Facades\Schema::hasColumn('LOTACAO', 'LOTACAO_DATA_FIM'),
                    fn($q) => $q->whereNull('l.LOTACAO_DATA_FIM')
                )
                ->value('l.SETOR_ID');
            if (!$setorId) {
                $setorId = DB::table('SETOR')->value('SETOR_ID');
            }

            $escala = DB::table('ESCALA')
                ->where('ESCALA_COMPETENCIA', $competencia)
                ->when($setorId, fn($q) => $q->where('SETOR_ID', $setorId))
                ->first();

            if (!$escala) {
                $escalaInsert = [
                    'ESCALA_COMPETENCIA' => $competencia,
                    'SETOR_ID' => $setorId ?: 1,
                ];
                if (\Illuminate\Support\Facades\Schema::hasColumn('ESCALA', 'ESCALA_ATIVO')) {
                    $escalaInsert['ESCALA_ATIVO'] = 1;
                }
                if (\Illuminate\Support\Facades\Schema::hasColumn('ESCALA', 'ESCALA_OBSERVACAO')) {
                    $escalaInsert['ESCALA_OBSERVACAO'] = 'Criada via Escala de Trabalho';
                }
                $escalaId = DB::table('ESCALA')->insertGetId($escalaInsert);
            } else {
                $escalaId = $escala->ESCALA_ID;
            }

            $detalhe = DB::table('DETALHE_ESCALA')
                ->where('ESCALA_ID', $escalaId)
                ->where('FUNCIONARIO_ID', $funcionarioId)
                ->first();
            if (!$detalhe) {
                $detalheId = DB::table('DETALHE_ESCALA')->insertGetId([
                    'ESCALA_ID' => $escalaId,
                    'FUNCIONARIO_ID' => $funcionarioId,
                ]);
            } else {
                $detalheId = $detalhe->DETALHE_ESCALA_ID;
            }

            $turnoId = DB::table('TURNO')->where('TURNO_SIGLA', $turnoSigla)->value('TURNO_ID');
            if (!$turnoId) {
                throw new \RuntimeException("Turno '{$turnoSigla}' não encontrado.");
            }

            DB::table('DETALHE_ESCALA_ITEM')->updateOrInsert(
                [
                    'DETALHE_ESCALA_ID' => $detalheId,
                    'DETALHE_ESCALA_ITEM_DATA' => $dataEscala,
                ],
                ['TURNO_ID' => $turnoId]
            );

            return ['escala_id' => $escalaId, 'detalhe_id' => $detalheId];
        });

        return response()->json(['ok' => true] + $payload, 201);
    } catch (\Throwable $e) {
        return response()->json(['ok' => false, 'erro' => $e->getMessage()], 422);
    }
});
