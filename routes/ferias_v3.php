<?php
// FERIAS CRUD - POST/PUT/DELETE /ferias
// Extraido de web.php - herda prefix api/v3 + auth do grupo principal

//  Criar agendamento de fÃ©rias
Route::post('/ferias', function (\Illuminate\Http\Request $request) {
    try {
        $user = \Illuminate\Support\Facades\Auth::user();
        $funcionario = \App\Models\Funcionario::where('USUARIO_ID', $user->USUARIO_ID)->first();
        if (!$funcionario) {
            return response()->json(['erro' => 'FuncionÃ¡rio nÃ£o vinculado ao seu usuÃ¡rio.'], 422);
        }

        $ferias = \App\Models\Ferias::create([
            'FUNCIONARIO_ID' => $funcionario->FUNCIONARIO_ID,
            'FERIAS_DATA_INICIO' => $request->FERIAS_DATA_INICIO,
            'FERIAS_DATA_FIM' => $request->FERIAS_DATA_FIM,
            'FERIAS_AQUISITIVO_INICIO' => $request->FERIAS_AQUISITIVO_INICIO ?? null,
            'FERIAS_AQUISITIVO_FIM' => $request->FERIAS_AQUISITIVO_FIM ?? null,
        ]);

        return response()->json(['message' => 'FÃ©rias agendadas com sucesso.', 'ferias_id' => $ferias->FERIAS_ID], 201);
    } catch (\Throwable $e) {
        \Illuminate\Support\Facades\Log::error('Erro ao criar fÃ©rias: ' . $e->getMessage());
        return response()->json(['erro' => 'Erro ao registrar fÃ©rias: ' . $e->getMessage()], 500);
    }
});

//  Atualizar perÃ­odo de fÃ©rias
Route::put('/ferias/{id}', function ($id, \Illuminate\Http\Request $request) {
    try {
        $user = \Illuminate\Support\Facades\Auth::user();
        $funcionario = \App\Models\Funcionario::where('USUARIO_ID', $user->USUARIO_ID)->first();

        $ferias = \App\Models\Ferias::findOrFail($id);

        // Verifica se a fÃ©rias pertence ao funcionÃ¡rio logado (ou Ã© admin)
        if ($funcionario && $ferias->FUNCIONARIO_ID !== $funcionario->FUNCIONARIO_ID) {
            return response()->json(['erro' => 'Sem permissÃ£o para editar esta fÃ©rias.'], 403);
        }

        if ($request->has('FERIAS_DATA_INICIO'))
            $ferias->FERIAS_DATA_INICIO = $request->FERIAS_DATA_INICIO;
        if ($request->has('FERIAS_DATA_FIM'))
            $ferias->FERIAS_DATA_FIM = $request->FERIAS_DATA_FIM;
        if ($request->has('FERIAS_AQUISITIVO_INICIO'))
            $ferias->FERIAS_AQUISITIVO_INICIO = $request->FERIAS_AQUISITIVO_INICIO;
        if ($request->has('FERIAS_AQUISITIVO_FIM'))
            $ferias->FERIAS_AQUISITIVO_FIM = $request->FERIAS_AQUISITIVO_FIM;
        $ferias->save();

        return response()->json(['message' => 'FÃ©rias atualizadas com sucesso.']);
    } catch (\Throwable $e) {
        return response()->json(['erro' => 'Erro ao atualizar: ' . $e->getMessage()], 500);
    }
});

//  Cancelar / excluir fÃ©rias
Route::delete('/ferias/{id}', function ($id) {
    try {
        $user = \Illuminate\Support\Facades\Auth::user();
        $funcionario = \App\Models\Funcionario::where('USUARIO_ID', $user->USUARIO_ID)->first();

        $ferias = \App\Models\Ferias::findOrFail($id);

        if ($funcionario && $ferias->FUNCIONARIO_ID !== $funcionario->FUNCIONARIO_ID) {
            return response()->json(['erro' => 'Sem permissÃ£o para cancelar esta fÃ©rias.'], 403);
        }

        $ferias->delete();
        return response()->json(['message' => 'FÃ©rias canceladas com sucesso.']);
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
