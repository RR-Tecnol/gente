<?php
// PONTO ELETRONICO — POST /ponto/justificativa (GET /ponto canónico: ponto_mes_spa_get.php)
// Extraido de web.php - herda prefix api/v3 + auth do grupo principal

if (!function_exists('resolveFuncionarioComFallbackDev')) {
    function resolveFuncionarioComFallbackDev($user)
    {
        if (!$user)
            return null;
        return \App\Models\Funcionario::where('USUARIO_ID', $user->USUARIO_ID)->first();
    }
}

//  POST: registrar justificativa de ponto
Route::post('/ponto/justificativa', function (\Illuminate\Http\Request $request) {
    try {
        $user = \Illuminate\Support\Facades\Auth::user();
        $funcionario = resolveFuncionarioComFallbackDev($user);
        if (!$funcionario)
            return response()->json(['erro' => 'Funcionário não encontrado.'], 404);

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
