<?php

use App\Models\RegistroPonto;
use App\Models\TerminalPonto;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Route;

/*
|--------------------------------------------------------------------------
| API Routes — GENTE
|--------------------------------------------------------------------------
*/

Route::middleware('auth:api')->get('/user', function (Request $request) {
    return $request->user();
});

/*
 * POST /api/ponto/bater
 * Autenticação por Bearer Token do terminal (header Authorization).
 * Usado por tablets/totens para registrar batidas sem sessão web.
 */
Route::post('/ponto/bater', function (Request $request) {
    // Valida Bearer token
    $token = $request->bearerToken();
    if (!$token) {
        return response()->json(['erro' => 'Token de terminal não fornecido.'], 401);
    }

    $terminal = TerminalPonto::where('TERMINAL_TOKEN', $token)
        ->where('TERMINAL_ATIVO', true)
        ->first();

    if (!$terminal) {
        return response()->json(['erro' => 'Terminal não autorizado ou inativo.'], 403);
    }

    // Valida IP se configurado
    if ($terminal->TERMINAL_IP && $request->ip() !== $terminal->TERMINAL_IP) {
        return response()->json(['erro' => 'IP não autorizado para este terminal.'], 403);
    }

    $request->validate([
        'funcionario_id' => 'required|integer',
        'registro_tipo' => 'required|in:ENTRADA,PAUSA,RETORNO,SAIDA',
        'registro_data_hora' => 'nullable|date',
    ]);

    $registro = RegistroPonto::create([
        'FUNCIONARIO_ID' => $request->funcionario_id,
        'TERMINAL_ID' => $terminal->TERMINAL_ID,
        'REGISTRO_DATA_HORA' => $request->registro_data_hora ?? now(),
        'REGISTRO_TIPO' => $request->registro_tipo,
        'REGISTRO_ORIGEM' => 'REP_A_SENHA',
        'REGISTRO_OBSERVACAO' => "Terminal: {$terminal->TERMINAL_NOME}",
    ]);

    return response()->json([
        'retorno' => $registro,
        'mensagem' => 'Ponto registrado com sucesso.',
    ], 201);
})->middleware('throttle:60,1'); // max 60 batidas por minuto por IP
