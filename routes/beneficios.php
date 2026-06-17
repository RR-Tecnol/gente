<?php

use Illuminate\Support\Facades\Route;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;
use Illuminate\Http\Request;

if (!function_exists('ensureBeneficioTablesFromRoutes')) {
    function ensureBeneficioTablesFromRoutes(): void
    {
        if (!Schema::hasTable('BENEFICIO') || !Schema::hasTable('FUNCIONARIO_BENEFICIO')) {
            throw new \RuntimeException('Tabelas de benefícios não encontradas. Execute migrations canônicas.');
        }
    }
}

// 2. Endpoints do Módulo de Benefícios
Route::prefix('beneficios')->group(function () {

    // ── Lista geral (GET /api/v3/beneficios) — dados básicos do catálogo
    Route::get('/', function () {
        try {
            ensureBeneficioTablesFromRoutes();
            $lista = DB::table('BENEFICIO')->orderBy('BENEFICIO_NOME')->get();
            return response()->json(['beneficios' => $lista, 'total' => $lista->count()]);
        } catch (\Throwable $e) {
            return response()->json(['beneficios' => [], 'total' => 0, 'aviso' => $e->getMessage()], 200);
        }
    });

    // ── Catálogo de Benefícios ──
    Route::get('/catalogo', function (Request $request) {
        ensureBeneficioTablesFromRoutes();
        try {
            $query = DB::table('BENEFICIO');
            
            if ($request->has('status') && $request->status !== 'todos') {
                $query->where('BENEFICIO_STATUS', $request->status);
            }

            $lista = $query->orderBy('BENEFICIO_NOME', 'asc')->get();
            return response()->json($lista);
        } catch (\Exception $e) {
            return response()->json(['error' => $e->getMessage()], 500);
        }
    });

    Route::post('/catalogo', function (Request $request) {
        ensureBeneficioTablesFromRoutes();
        try {
            $data = $request->validate([
                'BENEFICIO_NOME' => 'required|string|max:150',
                'BENEFICIO_TIPO' => 'nullable|string|max:50',
                'BENEFICIO_VALOR' => 'nullable|numeric|min:0',
                'BENEFICIO_STATUS' => 'nullable|string|max:20'
            ]);

            $id = $request->input('BENEFICIO_ID');

            if ($id) {
                $data['updated_at'] = now();
                DB::table('BENEFICIO')->where('BENEFICIO_ID', $id)->update($data);
                $beneficio = DB::table('BENEFICIO')->where('BENEFICIO_ID', $id)->first();
            } else {
                $data['created_at'] = now();
                $data['updated_at'] = now();
                if(!isset($data['BENEFICIO_STATUS'])) $data['BENEFICIO_STATUS'] = 'ativo';
                
                $id = DB::table('BENEFICIO')->insertGetId($data);
                $beneficio = DB::table('BENEFICIO')->where('BENEFICIO_ID', $id)->first();
            }

            return response()->json($beneficio);
        } catch (\Exception $e) {
            return response()->json(['error' => $e->getMessage()], 500);
        }
    })->middleware('perfil:ADMINISTRADOR,Administrador,GESTOR');

    // ── Vínculos por Servidor ──
    Route::get('/{funcionario_id}', function ($funcionario_id) {
        ensureBeneficioTablesFromRoutes();
        try {
            $vinculos = DB::table('FUNCIONARIO_BENEFICIO as fb')
                ->join('BENEFICIO as b', 'fb.BENEFICIO_ID', '=', 'b.BENEFICIO_ID')
                ->where('fb.FUNCIONARIO_ID', $funcionario_id)
                ->select(
                    'fb.*',
                    'b.BENEFICIO_NOME',
                    'b.BENEFICIO_TIPO',
                    'b.BENEFICIO_VALOR as VALOR_PADRAO'
                )
                ->orderBy('fb.DATA_INICIO', 'desc')
                ->get();

            return response()->json($vinculos);
        } catch (\Exception $e) {
            return response()->json(['error' => $e->getMessage()], 500);
        }
    })->where('funcionario_id', '[0-9]+');

    Route::post('/{funcionario_id}', function (Request $request, $funcionario_id) {
        ensureBeneficioTablesFromRoutes();
        try {
            $data = $request->validate([
                'BENEFICIO_ID' => 'required|integer',
                'DATA_INICIO' => 'required|date',
                'DATA_FIM' => 'nullable|date',
                'VALOR_ESPECIFICO' => 'nullable|numeric|min:0',
                'DEPENDENTES' => 'nullable|integer|min:0',
                'STATUS' => 'nullable|string|max:20',
                'OBSERVACAO' => 'nullable|string'
            ]);

            $id = $request->input('ID');

            if ($id) {
                $data['updated_at'] = now();
                DB::table('FUNCIONARIO_BENEFICIO')->where('ID', $id)->update($data);
            } else {
                $data['FUNCIONARIO_ID'] = $funcionario_id;
                $data['created_at'] = now();
                $data['updated_at'] = now();
                if(!isset($data['STATUS'])) $data['STATUS'] = 'ativo';
                
                $id = DB::table('FUNCIONARIO_BENEFICIO')->insertGetId($data);
            }

            $vinculo = DB::table('FUNCIONARIO_BENEFICIO as fb')
                ->join('BENEFICIO as b', 'fb.BENEFICIO_ID', '=', 'b.BENEFICIO_ID')
                ->where('fb.ID', $id)
                ->select('fb.*', 'b.BENEFICIO_NOME', 'b.BENEFICIO_TIPO', 'b.BENEFICIO_VALOR as VALOR_PADRAO')
                ->first();

            return response()->json($vinculo);
        } catch (\Exception $e) {
            return response()->json(['error' => $e->getMessage()], 500);
        }
    })->where('funcionario_id', '[0-9]+')->middleware('perfil:ADMINISTRADOR,Administrador,GESTOR');

    Route::delete('/{id}', function ($id) {
        ensureBeneficioTablesFromRoutes();
        try {
            // Em vez de hard delete, pode-se inativar se desejado,
            // mas o requesito foi explícito: DELETE /beneficios/{id}
            DB::table('FUNCIONARIO_BENEFICIO')->where('ID', $id)->delete();
            return response()->json(['success' => true]);
        } catch (\Exception $e) {
            return response()->json(['error' => $e->getMessage()], 500);
        }
    })->middleware('perfil:ADMINISTRADOR,Administrador,GESTOR');

    // ── Relatório de Relacionamentos e KPI ──
    Route::get('/relatorio/kpis', function () {
        ensureBeneficioTablesFromRoutes();
        try {
            // Conta totais distintos
            $totalServidoresComBeneficio = DB::table('FUNCIONARIO_BENEFICIO')
                ->where('STATUS', 'ativo')
                ->distinct('FUNCIONARIO_ID')
                ->count('FUNCIONARIO_ID');

            // Calcula Custo total mensal avaliando VALOR_ESPECIFICO fallback para VALOR_PADRAO
            $custoQuery = DB::table('FUNCIONARIO_BENEFICIO as fb')
                ->join('BENEFICIO as b', 'fb.BENEFICIO_ID', '=', 'b.BENEFICIO_ID')
                ->where('fb.STATUS', 'ativo')
                ->where('b.BENEFICIO_STATUS', 'ativo')
                ->select(DB::raw('SUM(COALESCE(fb.VALOR_ESPECIFICO, b.BENEFICIO_VALOR)) as total'));

            $custoTotal = $custoQuery->first()->total ?? 0;

            // Ficha por tipo de Benefício
            $porTipo = DB::table('FUNCIONARIO_BENEFICIO as fb')
                ->join('BENEFICIO as b', 'fb.BENEFICIO_ID', '=', 'b.BENEFICIO_ID')
                ->where('fb.STATUS', 'ativo')
                ->select('b.BENEFICIO_NOME', DB::raw('COUNT(fb.ID) as total_adesoes'), DB::raw('SUM(COALESCE(fb.VALOR_ESPECIFICO, b.BENEFICIO_VALOR)) as custo_relativo'))
                ->groupBy('b.BENEFICIO_ID', 'b.BENEFICIO_NOME')
                ->get();

            return response()->json([
                'total_servidores' => $totalServidoresComBeneficio,
                'custo_mensal' => (float) $custoTotal,
                'distribuicao' => $porTipo
            ]);
        } catch (\Exception $e) {
            return response()->json(['error' => $e->getMessage()], 500);
        }
    });

    // Rota fallback para servir busca de servidores e exibir benefícios 
    // para a aba 'Por Servidor' independentemente (Relatório/Agregado por pessoa)
    Route::get('/relatorio/servidores', function (Request $request) {
        ensureBeneficioTablesFromRoutes();
        try {
            $query = DB::table('FUNCIONARIO_BENEFICIO as fb')
                ->join('BENEFICIO as b', 'fb.BENEFICIO_ID', '=', 'b.BENEFICIO_ID')
                ->join('FUNCIONARIO as f', 'fb.FUNCIONARIO_ID', '=', 'f.FUNCIONARIO_ID')
                ->join('PESSOA as p', 'f.PESSOA_ID', '=', 'p.PESSOA_ID');
            
            if ($request->has('busca') && !empty(trim($request->busca))) {
                $termo = trim($request->busca);
                $query->where('p.PESSOA_NOME', 'like', "%{$termo}%")
                      ->orWhere('f.FUNCIONARIO_MATRICULA', 'like', "%{$termo}%");
            }
            
            // Agrupar via Collections depois do limit
            $raw = $query->select(
                'fb.ID as FB_ID', 'fb.FUNCIONARIO_ID', 'fb.DATA_INICIO', 'fb.STATUS as VINCULO_STATUS',
                'fb.VALOR_ESPECIFICO', 'b.BENEFICIO_NOME', 'b.BENEFICIO_VALOR',
                'p.PESSOA_NOME', 'f.FUNCIONARIO_MATRICULA'
            )->get();

            return response()->json($raw);
        } catch (\Exception $e) {
            return response()->json(['error' => $e->getMessage()], 500);
        }
    });
});
