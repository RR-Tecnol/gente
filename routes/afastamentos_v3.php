<?php
use Illuminate\Support\Facades\Route;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Auth;

Route::get('/afastamentos', function () {
    $user = Auth::user();
    $func = DB::table('FUNCIONARIO')->where('USUARIO_ID', $user->USUARIO_ID)->first();
    if (!$func) return response()->json(['afastamentos' => []]);
    $rows = DB::table('AFASTAMENTO')
        ->where('FUNCIONARIO_ID', $func->FUNCIONARIO_ID)
        ->orderByDesc('AFASTAMENTO_DATA_INICIO')->get();
    return response()->json(['afastamentos' => $rows]);
});

Route::post('/afastamentos', function (\Illuminate\Http\Request $request) {
    $user = Auth::user();
    $func = DB::table('FUNCIONARIO')->where('USUARIO_ID', $user->USUARIO_ID)->first();
    if (!$func) return response()->json(['erro' => 'Funcionário não encontrado.'], 404);
    $id = DB::table('AFASTAMENTO')->insertGetId([
        'FUNCIONARIO_ID'          => $func->FUNCIONARIO_ID,
        'AFASTAMENTO_TIPO'        => $request->tipo,
        'AFASTAMENTO_DATA_INICIO' => $request->inicio,
        'AFASTAMENTO_DATA_FIM'    => $request->fim,
        'AFASTAMENTO_OBS'         => $request->obs,
        'AFASTAMENTO_STATUS'      => 'pendente',
        'created_at'              => now(),
        'updated_at'              => now(),
    ]);
    return response()->json([
        'ok'        => true,
        'id'        => $id,
        'protocolo' => 'AFT-' . str_pad($id, 5, '0', STR_PAD_LEFT),
    ], 201);
});
