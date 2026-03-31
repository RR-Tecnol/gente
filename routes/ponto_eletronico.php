<?php
// PONTO ELETRONICO - GET /ponto POST /ponto/justificativa
// Extraido de web.php - herda prefix api/v3 + auth do grupo principal

//  GET: apuraÃ§Ã£o do mÃªs para o usuÃ¡rio logado
Route::get('/ponto', function (\Illuminate\Http\Request $request) {
    try {
        $user = \Illuminate\Support\Facades\Auth::user();
        $funcionario = \App\Models\Funcionario::where('USUARIO_ID', $user->USUARIO_ID)->first();
        if (!$funcionario) {
            return response()->json(['erro' => 'FuncionÃ¡rio nÃ£o encontrado.'], 404);
        }

        $competencia = $request->competencia
            ?? now()->format('Y-m');

        // Busca apuraÃ§Ã£o do perÃ­odo
        $apuracao = \App\Models\ApuracaoPonto::with(['justificativas'])
            ->where('FUNCIONARIO_ID', $funcionario->FUNCIONARIO_ID)
            ->where('APURACAO_COMPETENCIA', $competencia)
            ->first();

        // Busca registros de ponto do perÃ­odo (tabela REGISTRO_PONTO se existir)
        $registros = [];
        try {
            $anoMes = str_replace('-', '', $competencia); // ex: 202503
            $registros = \Illuminate\Support\Facades\DB::table('REGISTRO_PONTO')
                ->where('FUNCIONARIO_ID', $funcionario->FUNCIONARIO_ID)
                ->where('REGISTRO_PONTO_COMPETENCIA', $anoMes)
                ->get()
                ->map(fn($r) => (array) $r)
                ->toArray();
        } catch (\Throwable $e) {
        }

        // Busca justificativas de ponto
        $justificativas = [];
        try {
            $justificativas = \App\Models\JustificativaPonto::where('FUNCIONARIO_ID', $funcionario->FUNCIONARIO_ID)
                ->where(function ($q) use ($competencia) {
                    [$ano, $mes] = explode('-', $competencia);
                    $q->whereYear('JUSTIFICATIVA_DATA', $ano)
                        ->whereMonth('JUSTIFICATIVA_DATA', $mes);
                })
                ->get()
                ->toArray();
        } catch (\Throwable $e) {
        }

        return response()->json([
            'competencia' => $competencia,
            'apuracao' => $apuracao ? [
                'APURACAO_ID' => $apuracao->APURACAO_ID,
                'APURACAO_HORAS_TRAB' => $apuracao->APURACAO_HORAS_TRAB,
                'APURACAO_HORAS_EXTRA' => $apuracao->APURACAO_HORAS_EXTRA,
                'APURACAO_HORAS_FALTA' => $apuracao->APURACAO_HORAS_FALTA,
                'APURACAO_STATUS' => $apuracao->APURACAO_STATUS,
            ] : null,
            'registros' => $registros,
            'justificativas' => $justificativas,
        ]);
    } catch (\Throwable $e) {
        \Illuminate\Support\Facades\Log::error('Ponto: ' . $e->getMessage());
        return response()->json(['erro' => $e->getMessage()], 500);
    }
});

//  POST: registrar justificativa de ponto
Route::post('/ponto/justificativa', function (\Illuminate\Http\Request $request) {
    try {
        $user = \Illuminate\Support\Facades\Auth::user();
        $funcionario = \App\Models\Funcionario::where('USUARIO_ID', $user->USUARIO_ID)->first();
        if (!$funcionario)
            return response()->json(['erro' => 'FuncionÃ¡rio nÃ£o encontrado.'], 404);

        $justificativa = new \App\Models\JustificativaPonto();
        $justificativa->FUNCIONARIO_ID = $funcionario->FUNCIONARIO_ID;
        try {
            $justificativa->JUSTIFICATIVA_DATA = $request->data;
        } catch (\Throwable $e) {
        }
        try {
            $justificativa->JUSTIFICATIVA_MOTIVO = $request->motivo;
        } catch (\Throwable $e) {
        }
        try {
            $justificativa->JUSTIFICATIVA_OBS = $request->obs;
        } catch (\Throwable $e) {
        }
        try {
            $justificativa->JUSTIFICATIVA_STATUS = 'PENDENTE';
        } catch (\Throwable $e) {
        }
        $justificativa->save();

        return response()->json(['message' => 'Justificativa registrada.', 'id' => $justificativa->getKey()], 201);
    } catch (\Throwable $e) {
        return response()->json(['erro' => $e->getMessage()], 500);
    }
});
