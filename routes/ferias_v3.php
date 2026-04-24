<?php
// FERIAS CRUD - POST/PUT/DELETE /ferias
// Extraido de web.php - herda prefix api/v3 + auth do grupo principal

if (!function_exists('resolveFuncionarioComFallbackDev')) {
    function resolveFuncionarioComFallbackDev($user)
    {
        if (!$user)
            return null;
        $func = \App\Models\Funcionario::where('USUARIO_ID', $user->USUARIO_ID)->first();
        if ($func)
            return $func;

        if (!app()->isProduction() && strtolower((string) ($user->USUARIO_LOGIN ?? '')) === 'admin') {
            $livre = \App\Models\Funcionario::whereNull('USUARIO_ID')->orderBy('FUNCIONARIO_ID')->first();
            if ($livre) {
                \Illuminate\Support\Facades\DB::table('FUNCIONARIO')
                    ->where('FUNCIONARIO_ID', $livre->FUNCIONARIO_ID)
                    ->update(['USUARIO_ID' => $user->USUARIO_ID]);
                return \App\Models\Funcionario::where('FUNCIONARIO_ID', $livre->FUNCIONARIO_ID)->first();
            }
        }

        return null;
    }
}

//  Criar agendamento de férias
Route::post('/ferias', function (\Illuminate\Http\Request $request) {
    try {
        $user = \Illuminate\Support\Facades\Auth::user();
        $funcionario = resolveFuncionarioComFallbackDev($user);
        if (!$funcionario) {
            return response()->json(['erro' => 'Funcionário não vinculado ao seu usuário.'], 422);
        }

        $ferias = \App\Models\Ferias::create([
            'FUNCIONARIO_ID' => $funcionario->FUNCIONARIO_ID,
            'FERIAS_DATA_INICIO' => $request->FERIAS_DATA_INICIO,
            'FERIAS_DATA_FIM' => $request->FERIAS_DATA_FIM,
            'FERIAS_AQUISITIVO_INICIO' => $request->FERIAS_AQUISITIVO_INICIO ?? null,
            'FERIAS_AQUISITIVO_FIM' => $request->FERIAS_AQUISITIVO_FIM ?? null,
        ]);

        return response()->json(['message' => 'Férias agendadas com sucesso.', 'ferias_id' => $ferias->FERIAS_ID], 201);
    } catch (\Throwable $e) {
        \Illuminate\Support\Facades\Log::error('Erro ao criar férias: ' . $e->getMessage());
        return response()->json(['erro' => 'Erro ao registrar férias: ' . $e->getMessage()], 500);
    }
});

//  Atualizar período de férias
Route::put('/ferias/{id}', function ($id, \Illuminate\Http\Request $request) {
    try {
        $user = \Illuminate\Support\Facades\Auth::user();
        $funcionario = resolveFuncionarioComFallbackDev($user);
        $isAdminDev = !app()->isProduction() && strtolower((string) ($user->USUARIO_LOGIN ?? '')) === 'admin';

        $ferias = \App\Models\Ferias::find($id);
        if (!$ferias) {
            return response()->json(['erro' => 'Férias não encontradas.'], 404);
        }

        // Verifica se as férias pertencem ao funcionário logado (ou é admin)
        if (!$isAdminDev && $funcionario && $ferias->FUNCIONARIO_ID !== $funcionario->FUNCIONARIO_ID) {
            return response()->json(['erro' => 'Sem permissão para editar estas férias.'], 403);
        }

        if ($request->has('FERIAS_DATA_INICIO'))
            $ferias->FERIAS_DATA_INICIO = $request->FERIAS_DATA_INICIO;
        if ($request->has('FERIAS_DATA_FIM'))
            $ferias->FERIAS_DATA_FIM = $request->FERIAS_DATA_FIM;
        if ($request->has('FERIAS_AQUISITIVO_INICIO'))
            $ferias->FERIAS_AQUISITIVO_INICIO = $request->FERIAS_AQUISITIVO_INICIO;
        if ($request->has('FERIAS_AQUISITIVO_FIM'))
            $ferias->FERIAS_AQUISITIVO_FIM = $request->FERIAS_AQUISITIVO_FIM;
        if (!$ferias->isDirty()) {
            return response()->json(['erro' => 'Nenhuma alteração informada para as férias.'], 422);
        }
        $ferias->save();

        return response()->json(['message' => 'Férias atualizadas com sucesso.']);
    } catch (\Throwable $e) {
        return response()->json(['erro' => 'Erro ao atualizar: ' . $e->getMessage()], 500);
    }
});

//  Cancelar / excluir férias
Route::delete('/ferias/{id}', function ($id) {
    try {
        $user = \Illuminate\Support\Facades\Auth::user();
        $funcionario = resolveFuncionarioComFallbackDev($user);
        $isAdminDev = !app()->isProduction() && strtolower((string) ($user->USUARIO_LOGIN ?? '')) === 'admin';

        $ferias = \App\Models\Ferias::find($id);
        if (!$ferias) {
            return response()->json(['erro' => 'Férias não encontradas.'], 404);
        }

        if (!$isAdminDev && $funcionario && $ferias->FUNCIONARIO_ID !== $funcionario->FUNCIONARIO_ID) {
            return response()->json(['erro' => 'Sem permissão para cancelar estas férias.'], 403);
        }

        if (!$ferias->delete()) {
            return response()->json(['erro' => 'Não foi possível cancelar as férias.'], 500);
        }
        return response()->json(['message' => 'Férias canceladas com sucesso.']);
    } catch (\Throwable $e) {
        return response()->json(['erro' => 'Erro ao cancelar: ' . $e->getMessage()], 500);
    }
});

// ── GAP-FER: Calcular prévia de férias ─────────────────────────────────────
Route::get('/ferias/calcular/{funcionario_id}', function (int $funcionarioId) {
    try {
        $dias = (int) request('dias', 30);
        $service = new \App\Services\FeriasService();
        $calc = $service->calcular($funcionarioId, $dias);
        return response()->json($calc);
    } catch (\Throwable $e) {
        return response()->json(['erro' => $e->getMessage()], 422);
    }
});

// ── GAP-FER: Listar férias com status e valores ─────────────────────────────
Route::get('/ferias/admin', function () {
    try {
        $ferias = \Illuminate\Support\Facades\DB::table('FERIAS as fe')
            ->join('FUNCIONARIO as f', 'f.FUNCIONARIO_ID', '=', 'fe.FUNCIONARIO_ID')
            ->join('PESSOA as p', 'p.PESSOA_ID', '=', 'f.PESSOA_ID')
            ->select(
                'fe.*',
                'p.PESSOA_NOME as nome',
                'f.FUNCIONARIO_MATRICULA as matricula'
            )
            ->orderByDesc('fe.FERIAS_DATA_INICIO')
            ->limit(200)
            ->get();
        return response()->json(['ferias' => $ferias]);
    } catch (\Throwable $e) {
        return response()->json(['erro' => $e->getMessage()], 500);
    }
});

// ── GAP-FER: Aprovar férias e calcular valores ─────────────────────────────
Route::post('/ferias/{id}/aprovar', function (int $id) {
    try {
        $user = \Illuminate\Support\Facades\Auth::user();
        $competencia = request('competencia'); // AAAAMM
        if (!$competencia) {
            return response()->json(['erro' => 'competencia é obrigatório (AAAAMM).'], 422);
        }
        $service = new \App\Services\FeriasService();
        $calc = $service->aprovar($id, $user->USUARIO_ID, $competencia);
        return response()->json(['ok' => true, 'calculo' => $calc]);
    } catch (\Throwable $e) {
        return response()->json(['erro' => $e->getMessage()], 422);
    }
});
