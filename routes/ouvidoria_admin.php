<?php

use Illuminate\Support\Facades\Route;
use Illuminate\Support\Facades\DB;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;

// Reutiliza tabelas: OUVIDORIA
Route::get('/ouvidoria/admin', function () {
    $manifestacoes = DB::table('OUVIDORIA')
        ->leftJoin('FUNCIONARIO', 'OUVIDORIA.FUNCIONARIO_ID', '=', 'FUNCIONARIO.FUNCIONARIO_ID')
        ->leftJoin('PESSOA', 'FUNCIONARIO.PESSOA_ID', '=', 'PESSOA.PESSOA_ID')
        ->select('OUVIDORIA.*', 'PESSOA.PESSOA_NOME as solicitante_nome')
        ->orderBy('OUVIDORIA.created_at', 'desc')
        ->get();
    return response()->json($manifestacoes);
});

Route::patch('/ouvidoria/{id}/responder', function (Request $request, $id) {
    $request->validate(['resposta' => 'required|string']);
    
    DB::table('OUVIDORIA')
        ->where('OUVIDORIA_ID', $id)
        ->update([
            'OUVIDORIA_RESPOSTA' => $request->input('resposta'),
            'OUVIDORIA_STATUS' => 'respondida',
            'updated_at' => now()
        ]);
        
    return response()->json(['ok' => true]);
});

Route::get('/ouvidoria/protocolo/{num}', function ($num) {
    $manifestacao = DB::table('OUVIDORIA')
        ->leftJoin('FUNCIONARIO', 'OUVIDORIA.FUNCIONARIO_ID', '=', 'FUNCIONARIO.FUNCIONARIO_ID')
        ->leftJoin('PESSOA', 'FUNCIONARIO.PESSOA_ID', '=', 'PESSOA.PESSOA_ID')
        ->select('OUVIDORIA.*', 'PESSOA.PESSOA_NOME as solicitante_nome')
        ->where('OUVIDORIA.OUVIDORIA_PROTOCOLO', $num)
        ->first();
        
    if (!$manifestacao) {
        return response()->json(['erro' => 'Protocolo não encontrado'], 404);
    }
    
    return response()->json(['manifestacao' => $manifestacao]);
});
