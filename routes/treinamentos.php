<?php
use Illuminate\Support\Facades\Route;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;
use Illuminate\Http\Request;

if (!function_exists('ensureTreinamentoTablesFromRoutes')) {
    function ensureTreinamentoTablesFromRoutes(): void
    {
        if (!Schema::hasTable('TREINAMENTO') || !Schema::hasTable('TREINAMENTO_INSCRICAO')) {
            throw new \RuntimeException('Tabelas de treinamento não encontradas. Execute migrations canônicas.');
        }
    }
}

// ==== Área do Servidor (TreinamentosView.vue) ====
Route::prefix('treinamentos')->group(function () {
    
    Route::get('/meus', function () {
        ensureTreinamentoTablesFromRoutes();
        try {
            $user = \Illuminate\Support\Facades\Auth::user();
            $func = DB::table('FUNCIONARIO')->where('USUARIO_ID', $user->USUARIO_ID ?? 0)->first();
            if (!$func) return response()->json(['cursos' => [], 'fallback' => true]);

            $cursos = DB::table('TREINAMENTO_INSCRICAO as I')
                ->join('TREINAMENTO as T', 'I.TREINAMENTO_ID', '=', 'T.TREINAMENTO_ID')
                ->where('I.FUNCIONARIO_ID', $func->FUNCIONARIO_ID)
                ->select('T.*', 'I.*')
                ->get()
                ->map(function ($c) {
                    return [
                        'id' => $c->TREINAMENTO_ID,
                        'titulo' => $c->TREINAMENTO_TITULO,
                        'desc' => $c->TREINAMENTO_DESC,
                        'area' => $c->TREINAMENTO_AREA,
                        'carga' => $c->TREINAMENTO_CARGA,
                        'modalidade' => $c->TREINAMENTO_MODALIDADE,
                        'status' => $c->INSCRICAO_STATUS,
                        'progresso' => $c->INSCRICAO_PROGRESSO,
                        'certificado' => (bool)$c->INSCRICAO_CERTIFICADO,
                        'data' => $c->INSCRICAO_DATA_CONCLUSAO,
                    ];
                });
            return response()->json(['cursos' => $cursos, 'fallback' => count($cursos) === 0]);
        } catch (\Exception $e) {
            return response()->json(['cursos' => [], 'fallback' => true]);
        }
    });

    Route::get('/catalogo', function () {
        ensureTreinamentoTablesFromRoutes();
        try {
            $catalogo = DB::table('TREINAMENTO')
                ->where('TREINAMENTO_ATIVO', true)
                ->get()
                ->map(function ($t) {
                    return [
                        'id' => $t->TREINAMENTO_ID,
                        'titulo' => $t->TREINAMENTO_TITULO,
                        'desc' => $t->TREINAMENTO_DESC,
                        'area' => $t->TREINAMENTO_AREA,
                        'carga' => $t->TREINAMENTO_CARGA,
                        'modalidade' => $t->TREINAMENTO_MODALIDADE,
                        'proxima' => $t->TREINAMENTO_PROXIMA,
                        'vagas' => $t->TREINAMENTO_VAGAS,
                        'custo' => 0, // Mock: na GENTE v3, assumiremos capacitações internas gratuitas.
                    ];
                });
            return response()->json(['catalogo' => $catalogo, 'fallback' => count($catalogo) === 0]);
        } catch (\Exception $e) {
            return response()->json(['catalogo' => [], 'fallback' => true]);
        }
    });

    Route::post('/{id}/inscrever', function (Request $request, $id) {
        ensureTreinamentoTablesFromRoutes();
        try {
            $user = \Illuminate\Support\Facades\Auth::user();
            $func = DB::table('FUNCIONARIO')->where('USUARIO_ID', $user->USUARIO_ID ?? 0)->first();
            if (!$func) return response()->json(['error' => 'Servidor não encontrado'], 404);

            $existe = DB::table('TREINAMENTO_INSCRICAO')->where('TREINAMENTO_ID', $id)->where('FUNCIONARIO_ID', $func->FUNCIONARIO_ID)->exists();
            if ($existe) return response()->json(['success' => true, 'msg' => 'Já inscrito.']);

            DB::table('TREINAMENTO_INSCRICAO')->insert([
                'TREINAMENTO_ID' => $id,
                'FUNCIONARIO_ID' => $func->FUNCIONARIO_ID,
                'INSCRICAO_STATUS' => 'inscrito',
                'INSCRICAO_PROGRESSO' => 0,
                'INSCRICAO_CERTIFICADO' => false,
                'created_at' => now(),
                'updated_at' => now(),
            ]);
            return response()->json(['success' => true]);
        } catch (\Exception $e) {
            return response()->json(['error' => $e->getMessage()], 500);
        }
    });

});

// ==== Área do RH / Gestor de Treinamentos ====
Route::prefix('treinamentos-admin')->group(function () {
    
    Route::get('/kpis', function () {
        ensureTreinamentoTablesFromRoutes();
        try {
            return response()->json([
                'cursos_ativos' => DB::table('TREINAMENTO')->where('TREINAMENTO_ATIVO', true)->count(),
                'total_inscricoes' => DB::table('TREINAMENTO_INSCRICAO')->count(),
                'total_concluidos' => DB::table('TREINAMENTO_INSCRICAO')->where('INSCRICAO_STATUS', 'concluido')->count(),
            ]);
        } catch (\Exception $e) {
            return response()->json(['cursos_ativos' => 0, 'total_inscricoes' => 0, 'total_concluidos' => 0]);
        }
    });

    // CRUD de Cursos
    Route::get('/cursos', function () {
        ensureTreinamentoTablesFromRoutes();
        try { return response()->json(DB::table('TREINAMENTO')->orderByDesc('created_at')->get()); } 
        catch (\Exception $e) { return response()->json([], 500); }
    });

    Route::post('/cursos', function (Request $request) {
        ensureTreinamentoTablesFromRoutes();
        try {
            $id = DB::table('TREINAMENTO')->insertGetId([
                'TREINAMENTO_TITULO' => $request->titulo,
                'TREINAMENTO_DESC' => $request->desc,
                'TREINAMENTO_AREA' => $request->area ?? 'Geral',
                'TREINAMENTO_CARGA' => $request->carga ?? 0,
                'TREINAMENTO_MODALIDADE' => $request->modalidade ?? 'EAD',
                'TREINAMENTO_PROXIMA' => $request->proxima,
                'TREINAMENTO_VAGAS' => $request->vagas ?? 0,
                'TREINAMENTO_ATIVO' => true,
                'created_at' => now(),
                'updated_at' => now()
            ]);
            return response()->json(['success' => true, 'id' => $id]);
        } catch (\Exception $e) { return response()->json(['error' => $e->getMessage()], 500); }
    })->middleware('perfil:ADMINISTRADOR,Administrador,GESTOR');

    Route::put('/cursos/{id}', function (Request $request, $id) {
        ensureTreinamentoTablesFromRoutes();
        try {
            DB::table('TREINAMENTO')->where('TREINAMENTO_ID', $id)->update([
                'TREINAMENTO_TITULO' => $request->titulo,
                'TREINAMENTO_DESC' => $request->desc,
                'TREINAMENTO_AREA' => $request->area,
                'TREINAMENTO_CARGA' => $request->carga,
                'TREINAMENTO_MODALIDADE' => $request->modalidade,
                'TREINAMENTO_PROXIMA' => $request->proxima,
                'TREINAMENTO_VAGAS' => $request->vagas,
                'TREINAMENTO_ATIVO' => filter_var($request->ativo, FILTER_VALIDATE_BOOLEAN),
                'updated_at' => now()
            ]);
            return response()->json(['success' => true]);
        } catch (\Exception $e) { return response()->json(['error' => $e->getMessage()], 500); }
    })->middleware('perfil:ADMINISTRADOR,Administrador,GESTOR');

    // Inscrições gerais
    Route::get('/inscricoes', function () {
        ensureTreinamentoTablesFromRoutes();
        try {
            $data = DB::table('TREINAMENTO_INSCRICAO as I')
                ->join('TREINAMENTO as T', 'I.TREINAMENTO_ID', '=', 'T.TREINAMENTO_ID')
                ->join('FUNCIONARIO as F', 'I.FUNCIONARIO_ID', '=', 'F.FUNCIONARIO_ID')
                ->join('PESSOA as P', 'F.PESSOA_ID', '=', 'P.PESSOA_ID')
                ->select(
                    'I.INSCRICAO_ID', 'I.INSCRICAO_STATUS', 'I.INSCRICAO_PROGRESSO', 'I.created_at',
                    'T.TREINAMENTO_TITULO', 'T.TREINAMENTO_MODALIDADE',
                    'P.PESSOA_NOME as FUNCIONARIO_NOME', 'F.FUNCIONARIO_MATRICULA'
                )
                ->orderBy('I.created_at', 'desc')
                ->get();
            return response()->json($data);
        } catch (\Exception $e) {
            return response()->json([], 500);
        }
    });
});
