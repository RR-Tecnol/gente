<?php

use Illuminate\Support\Facades\Route;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;
use Illuminate\Http\Request;

if (!function_exists('outputCsv')) {
    function outputCsv($data) {
        if (empty($data)) return response("Nenhum dado encontrado", 404);
        
        $headers = array_keys((array) $data[0]);
        
        $callback = function() use($data, $headers) {
            $file = fopen('php://output', 'w');
            fputs($file, "\xEF\xBB\xBF"); // BOM UTF-8
            fputcsv($file, $headers, ';');
            foreach ($data as $row) {
                fputcsv($file, array_values((array) $row), ';');
            }
            fclose($file);
        };

        return response()->stream($callback, 200, [
            "Content-type"        => "text/csv; charset=UTF-8",
            "Content-Disposition" => "attachment; filename=relatorio_" . date('Ymd_His') . ".csv",
            "Pragma"              => "no-cache",
            "Cache-Control"       => "must-revalidate, post-check=0, pre-check=0",
            "Expires"             => "0"
        ]);
    }
}

Route::prefix('relatorios')->group(function () {

    // GET /api/v3/relatorios/folha — ?competencia=YYYY-MM → RelatoriosView (data[], totais{})
    Route::get('/folha', function (Request $request) {
        $comp = $request->query('competencia');
        if ($comp !== null && $comp !== '') {
            $compNorm = str_replace('-', '', substr((string) $comp, 0, 7));
            if (strlen($compNorm) === 6 && Schema::hasTable('FOLHA') && Schema::hasTable('DETALHE_FOLHA')) {
                try {
                    $folha = DB::table('FOLHA')->where('FOLHA_COMPETENCIA', $compNorm)->orderByDesc('FOLHA_ID')->first();
                    if (!$folha) {
                        return response()->json([
                            'data' => [],
                            'totais' => ['servidores' => 0, 'bruto' => 0, 'descontos' => 0, 'liquido' => 0],
                            'current_page' => 1,
                            'last_page' => 1,
                            'total' => 0,
                        ]);
                    }
                    $q = DB::table('DETALHE_FOLHA as df')
                        ->join('FUNCIONARIO as f', 'f.FUNCIONARIO_ID', '=', 'df.FUNCIONARIO_ID')
                        ->join('PESSOA as p', 'p.PESSOA_ID', '=', 'f.PESSOA_ID')
                        ->leftJoin('CARGO as c', 'c.CARGO_ID', '=', 'f.CARGO_ID')
                        ->where('df.FOLHA_ID', $folha->FOLHA_ID);
                    $hasSetorDf = Schema::hasTable('SETOR') && Schema::hasColumn('DETALHE_FOLHA', 'SETOR_ID');
                    $hasSetorF = Schema::hasTable('SETOR') && Schema::hasColumn('FUNCIONARIO', 'SETOR_ID');
                    if ($hasSetorDf) {
                        $q->leftJoin('SETOR as s_df', 's_df.SETOR_ID', '=', 'df.SETOR_ID');
                    }
                    if ($hasSetorF) {
                        $q->leftJoin('SETOR as s_f', 's_f.SETOR_ID', '=', 'f.SETOR_ID');
                    }
                    if ($request->filled('busca')) {
                        $termo = '%' . substr((string) $request->input('busca'), 0, 120) . '%';
                        $q->where(function ($w) use ($termo) {
                            $w->where('p.PESSOA_NOME', 'like', $termo)
                                ->orWhere('f.FUNCIONARIO_MATRICULA', 'like', $termo);
                        });
                    }
                    if ($hasSetorDf && $hasSetorF) {
                        $setorSel = DB::raw('COALESCE(s_df.SETOR_NOME, s_f.SETOR_NOME) as setor');
                    } elseif ($hasSetorF) {
                        $setorSel = 's_f.SETOR_NOME as setor';
                    } else {
                        $setorSel = DB::raw("'' as setor");
                    }
                    $q->select(
                        'f.FUNCIONARIO_MATRICULA as matricula',
                        'p.PESSOA_NOME as nome',
                        'c.CARGO_NOME as cargo',
                        $setorSel,
                        'df.DETALHE_FOLHA_PROVENTOS as bruto',
                        'df.DETALHE_FOLHA_DESCONTOS as descontos',
                        DB::raw('COALESCE(df.DETALHE_FOLHA_LIQUIDO, df.DETALHE_FOLHA_PROVENTOS - df.DETALHE_FOLHA_DESCONTOS, 0) as liquido')
                    );
                    $rows = $q->orderBy('p.PESSOA_NOME')->get()->map(function ($r) {
                        return [
                            'matricula' => $r->matricula,
                            'nome' => $r->nome,
                            'cargo' => $r->cargo,
                            'setor' => $r->setor,
                            'bruto' => (float) ($r->bruto ?? 0),
                            'descontos' => (float) ($r->descontos ?? 0),
                            'liquido' => (float) ($r->liquido ?? 0),
                        ];
                    })->values();
                    $n = $rows->count();
                    $brutoT = (float) $rows->sum('bruto');
                    $descT = (float) $rows->sum('descontos');
                    $liqT = (float) $rows->sum('liquido');

                    return response()->json([
                        'ok' => true,
                        'data' => $rows,
                        'totais' => [
                            'servidores' => $n,
                            'bruto' => $brutoT,
                            'descontos' => $descT,
                            'liquido' => $liqT,
                        ],
                        'current_page' => 1,
                        'last_page' => 1,
                        'total' => $n,
                    ]);
                } catch (\Throwable $e) {
                    return response()->json([
                        'data' => [],
                        'totais' => ['servidores' => 0, 'bruto' => 0, 'descontos' => 0, 'liquido' => 0],
                        'erro' => $e->getMessage(),
                    ]);
                }
            }
        }
        try {
            $ult = DB::table('FOLHA')->orderByDesc('FOLHA_ID')->first();
            $comps = DB::table('FOLHA')->orderByDesc('FOLHA_COMPETENCIA')->limit(24)->pluck('FOLHA_COMPETENCIA');
            return response()->json([
                'ok' => true,
                'ultima_folha' => $ult,
                'competencias' => $comps,
                'resumo' => [
                    'total_folhas' => DB::table('FOLHA')->count(),
                ],
            ]);
        } catch (\Throwable $e) {
            return response()->json(['ok' => true, 'ultima_folha' => null, 'competencias' => [], 'resumo' => [], 'aviso' => $e->getMessage()]);
        }
    });

    Route::get('/quadro-servidores', function (Request $request) {
        $dados = DB::table('FUNCIONARIO')
            ->join('PESSOA', 'FUNCIONARIO.PESSOA_ID', '=', 'PESSOA.PESSOA_ID')
            ->leftJoin('CARGO', 'FUNCIONARIO.CARGO_ID', '=', 'CARGO.CARGO_ID')
            ->leftJoin('SETOR', 'FUNCIONARIO.SETOR_ID', '=', 'SETOR.SETOR_ID')
            ->leftJoin('VINCULO', 'FUNCIONARIO.VINCULO_ID', '=', 'VINCULO.VINCULO_ID')
            ->whereNull('FUNCIONARIO.FUNCIONARIO_DATA_FIM')
            ->select(
                'PESSOA.PESSOA_NOME as Servidor',
                'PESSOA.PESSOA_CPF_NUMERO as CPF',
                'FUNCIONARIO.FUNCIONARIO_MATRICULA as Matricula',
                'CARGO.CARGO_NOME as Cargo',
                'SETOR.SETOR_NOME as Setor',
                'VINCULO.VINCULO_DESCRICAO as Vinculo',
                'FUNCIONARIO.FUNCIONARIO_DATA_INICIO as Admissao'
            )->get();
            
        if ($request->query('formato') === 'csv') return outputCsv($dados->toArray());
        return response()->json($dados);
    });

    Route::get('/folha/{competencia}', function (Request $request, $competencia) {
        $dados = DB::table('FOLHA')
            ->join('FUNCIONARIO', 'FOLHA.FUNCIONARIO_ID', '=', 'FUNCIONARIO.FUNCIONARIO_ID')
            ->join('PESSOA', 'FUNCIONARIO.PESSOA_ID', '=', 'PESSOA.PESSOA_ID')
            ->where('FOLHA.FOLHA_COMPETENCIA', $competencia)
            ->select(
                'PESSOA.PESSOA_NOME as Servidor',
                'PESSOA.PESSOA_CPF_NUMERO as CPF',
                'FOLHA.FOLHA_TIPO as Tipo',
                'FOLHA.FOLHA_BRUTO as Bruto',
                'FOLHA.FOLHA_DESCONTO as Desconto',
                'FOLHA.FOLHA_LIQUIDO as Liquido'
            )->get();
            
        if ($request->query('formato') === 'csv') return outputCsv($dados->toArray());
        return response()->json($dados);
    });

    Route::get('/atestados/{periodo}', function (Request $request, $periodo) {
        $dados = DB::table('ATESTADO')
            ->join('FUNCIONARIO', 'ATESTADO.FUNCIONARIO_ID', '=', 'FUNCIONARIO.FUNCIONARIO_ID')
            ->join('PESSOA', 'FUNCIONARIO.PESSOA_ID', '=', 'PESSOA.PESSOA_ID')
            ->where('ATESTADO.ATESTADO_DATA_INICIO', 'like', $periodo . '%')
            ->select(
                'PESSOA.PESSOA_NOME as Servidor',
                'ATESTADO.ATESTADO_DATA_INICIO as DataInicio',
                'ATESTADO.ATESTADO_DIAS as Dias',
                'ATESTADO.CID as CID'
            )->get();
            
        if ($request->query('formato') === 'csv') return outputCsv($dados->toArray());
        return response()->json($dados);
    });

    Route::get('/banco-horas', function (Request $request) {
        $dados = DB::table('BANCO_HORAS_SALDO')
            ->join('FUNCIONARIO', 'BANCO_HORAS_SALDO.FUNCIONARIO_ID', '=', 'FUNCIONARIO.FUNCIONARIO_ID')
            ->join('PESSOA', 'FUNCIONARIO.PESSOA_ID', '=', 'PESSOA.PESSOA_ID')
            ->leftJoin('SETOR', 'FUNCIONARIO.SETOR_ID', '=', 'SETOR.SETOR_ID')
            ->select(
                'PESSOA.PESSOA_NOME as Servidor',
                'SETOR.SETOR_NOME as Secretaria',
                'BANCO_HORAS_SALDO.SALDO_MINUTOS as SaldoMinutos'
            )->get();
            
        if ($request->query('formato') === 'csv') return outputCsv($dados->toArray());
        return response()->json($dados);
    });

    Route::get('/progressao-elegiveis', function (Request $request) {
        $dados = DB::table('FUNCIONARIO')
            ->join('PESSOA', 'FUNCIONARIO.PESSOA_ID', '=', 'PESSOA.PESSOA_ID')
            ->leftJoin('TITULACAO', 'PESSOA.PESSOA_ID', '=', 'TITULACAO.PESSOA_ID')
            ->select(
                'PESSOA.PESSOA_NOME as Servidor',
                'FUNCIONARIO.FUNCIONARIO_DATA_INICIO as Admissao',
                'TITULACAO.NIVEL as Titulacao'
            )
            ->whereNotNull('TITULACAO.TITULACAO_ID')
            ->get();
            
        if ($request->query('formato') === 'csv') return outputCsv($dados->toArray());
        return response()->json($dados);
    });

    Route::get('/custo-secretaria', function (Request $request) {
        $dados = DB::table('FOLHA')
            ->join('FUNCIONARIO', 'FOLHA.FUNCIONARIO_ID', '=', 'FUNCIONARIO.FUNCIONARIO_ID')
            ->leftJoin('SETOR', 'FUNCIONARIO.SETOR_ID', '=', 'SETOR.SETOR_ID')
            ->select(
                'SETOR.SETOR_NOME as Secretaria',
                DB::raw('SUM(FOLHA.FOLHA_BRUTO) as CustoTotal'),
                DB::raw('COUNT(FOLHA.FOLHA_ID) as TotalServidores')
            )
            ->groupBy('SETOR.SETOR_NOME')
            ->get();
            
        if ($request->query('formato') === 'csv') return outputCsv($dados->toArray());
        return response()->json($dados);
    });

    Route::get('/lrf-pessoal/{ano}', function (Request $request, $ano) {
        $dados = DB::table('FOLHA')
            ->where('FOLHA_COMPETENCIA', 'like', $ano . '%')
            ->select(
                DB::raw('SUM(FOLHA_BRUTO) as DespesaPessoal')
            )->get();
            
        if ($request->query('formato') === 'csv') return outputCsv($dados->toArray());
        return response()->json($dados);
    });
});

Route::get('/relatorios/stats', function () {
    try {
        $ultFolha = \Illuminate\Support\Facades\DB::table('FOLHA')->orderByDesc('FOLHA_COMPETENCIA')->first();
        $qFunc = \Illuminate\Support\Facades\DB::table('FUNCIONARIO');
        if (\Illuminate\Support\Facades\Schema::hasColumn('FUNCIONARIO', 'FUNCIONARIO_DATA_FIM')) {
            $qFunc->whereNull('FUNCIONARIO_DATA_FIM');
        }
        $qtdAtivos = $qFunc->count();
        return response()->json(['funcionarios' => $qtdAtivos, 'competencia' => $ultFolha?->FOLHA_COMPETENCIA ?? null]);
    } catch (\Throwable $e) { return response()->json(['funcionarios' => 0, 'fallback' => true]); }
});
Route::get('/relatorios/funcionarios', function (\Illuminate\Http\Request $request) {
    try {
        $query = \Illuminate\Support\Facades\DB::table('FUNCIONARIO as f')
            ->join('PESSOA as p', 'p.PESSOA_ID', '=', 'f.PESSOA_ID')
            ->leftJoin('CARGO as c', 'c.CARGO_ID', '=', 'f.CARGO_ID')
            ->leftJoin('LOTACAO as l', function ($join) {
                $join->on('l.FUNCIONARIO_ID', '=', 'f.FUNCIONARIO_ID');
                if (\Illuminate\Support\Facades\Schema::hasColumn('LOTACAO', 'LOTACAO_DATA_FIM')) {
                    $join->whereNull('l.LOTACAO_DATA_FIM');
                }
            })
            ->leftJoin('SETOR as s', 's.SETOR_ID', '=', 'l.SETOR_ID')
            ->select('f.FUNCIONARIO_MATRICULA as matricula','p.PESSOA_NOME as nome','c.CARGO_NOME as cargo','s.SETOR_NOME as setor','f.FUNCIONARIO_DATA_INICIO as admissao');
        if (\Illuminate\Support\Facades\Schema::hasColumn('FUNCIONARIO', 'FUNCIONARIO_DATA_FIM')) {
            $query->whereNull('f.FUNCIONARIO_DATA_FIM');
        }
        if ($request->busca) $query->where('p.PESSOA_NOME', 'like', '%' . substr($request->busca, 0, 100) . '%');
        return response()->json($query->orderBy('p.PESSOA_NOME')->paginate(20));
    } catch (\Throwable $e) { return response()->json(['data' => [], 'total' => 0]); }
});
Route::get('/relatorios/admissoes', function (\Illuminate\Http\Request $request) {
    try {
        $inicio = $request->data_inicio ?? date('Y-01-01');
        $fim = $request->data_fim ?? date('Y-m-d');
        $dados = \Illuminate\Support\Facades\DB::table('FUNCIONARIO as f')
            ->join('PESSOA as p', 'p.PESSOA_ID', '=', 'f.PESSOA_ID')
            ->leftJoin('CARGO as c', 'c.CARGO_ID', '=', 'f.CARGO_ID')
            ->leftJoin('LOTACAO as l', function ($join) {
                $join->on('l.FUNCIONARIO_ID', '=', 'f.FUNCIONARIO_ID');
                if (\Illuminate\Support\Facades\Schema::hasColumn('LOTACAO', 'LOTACAO_DATA_FIM')) {
                    $join->whereNull('l.LOTACAO_DATA_FIM');
                }
            })
            ->leftJoin('SETOR as s', 's.SETOR_ID', '=', 'l.SETOR_ID')
            ->whereBetween('f.FUNCIONARIO_DATA_INICIO', [$inicio, $fim])
            ->select('f.FUNCIONARIO_MATRICULA as matricula','p.PESSOA_NOME as nome','c.CARGO_NOME as cargo','s.SETOR_NOME as setor','f.FUNCIONARIO_DATA_INICIO as admissao')
            ->orderByDesc('f.FUNCIONARIO_DATA_INICIO')->get()->map(fn($r) => (array)$r);
        return response()->json($dados);
    } catch (\Throwable $e) { return response()->json([]); }
});
Route::get('/relatorios/frequencia', function (\Illuminate\Http\Request $request) {
    try {
        $inicio = $request->data_inicio ?? date('Y-m-01');
        $fim = $request->data_fim ?? date('Y-m-t');
        $dados = \Illuminate\Support\Facades\DB::table('REGISTRO_PONTO as rp')
            ->join('FUNCIONARIO as f', 'f.FUNCIONARIO_ID', '=', 'rp.FUNCIONARIO_ID')
            ->join('PESSOA as p', 'p.PESSOA_ID', '=', 'f.PESSOA_ID')
            ->leftJoin('LOTACAO as l', function ($join) {
                $join->on('l.FUNCIONARIO_ID', '=', 'f.FUNCIONARIO_ID');
                if (\Illuminate\Support\Facades\Schema::hasColumn('LOTACAO', 'LOTACAO_DATA_FIM')) {
                    $join->whereNull('l.LOTACAO_DATA_FIM');
                }
            })
            ->leftJoin('SETOR as s', 's.SETOR_ID', '=', 'l.SETOR_ID')
            ->whereBetween(\Illuminate\Support\Facades\DB::raw('CAST(rp.REGISTRO_DATA_HORA AS DATE)'), [$inicio, $fim])
            ->select('p.PESSOA_NOME as nome','s.SETOR_NOME as setor',\Illuminate\Support\Facades\DB::raw('COUNT(DISTINCT CAST(rp.REGISTRO_DATA_HORA AS DATE)) as presencas'))
            ->groupBy('f.FUNCIONARIO_ID','p.PESSOA_NOME','s.SETOR_NOME')->orderBy('p.PESSOA_NOME')
            ->get()->map(fn($r) => (array)$r);
        return response()->json($dados);
    } catch (\Throwable $e) { return response()->json([]); }
});
