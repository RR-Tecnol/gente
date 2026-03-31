<?php

use Illuminate\Support\Facades\Route;
use Illuminate\Support\Facades\DB;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;

// Reutiliza tabelas: OUVIDORIA_MANIFESTACAO, OUVIDORIA_RESPOSTA

Route::get('/ouvidoria/admin', function () {
    $manifestacoes = DB::table('OUVIDORIA_MANIFESTACAO')
        ->orderBy('created_at', 'desc')
        ->get();
    return response()->json($manifestacoes);
});

Route::patch('/ouvidoria/{id}/responder', function (Request $request, $id) {
    $request->validate(['resposta' => 'required|string']);
    
    DB::table('OUVIDORIA_RESPOSTA')->insert([
        'MANIFESTACAO_ID' => $id,
        'RESPOSTA_TEXTO' => $request->input('resposta'),
        'USUARIO_ID' => Auth::id() ?: 1, // Fallback safe
        'created_at' => now(),
        'updated_at' => now()
    ]);
    
    DB::table('OUVIDORIA_MANIFESTACAO')
        ->where('MANIFESTACAO_ID', $id)
        ->update(['STATUS' => 'RESPONDIDO', 'updated_at' => now()]);
        
    return response()->json(['ok' => true]);
});

Route::get('/ouvidoria/protocolo/{num}', function ($num) {
    $manifestacao = DB::table('OUVIDORIA_MANIFESTACAO')->where('PROTOCOLO', $num)->first();
    if (!$manifestacao) {
        return response()->json(['erro' => 'Protocolo não encontrado'], 404);
    }
    $respostas = DB::table('OUVIDORIA_RESPOSTA')->where('MANIFESTACAO_ID', $manifestacao->MANIFESTACAO_ID)->get();
    return response()->json(['manifestacao' => $manifestacao, 'respostas' => $respostas]);
});
