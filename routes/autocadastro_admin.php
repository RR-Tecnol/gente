<?php
use Illuminate\Support\Facades\Route;
use Illuminate\Support\Facades\DB;

Route::get('/autocadastro/pendentes', function () {
    $pendentes = DB::table('AUTOCADASTRO_TOKEN')
        ->where('TOKEN_STATUS', 'pendente')
        ->orderByDesc('created_at')
        ->get();
    return response()->json(['pendentes' => $pendentes]);
});

Route::post('/autocadastro/gerar-link', function (\Illuminate\Http\Request $request) {
    $token = \Illuminate\Support\Str::uuid();
    DB::table('AUTOCADASTRO_TOKEN')->insert([
        'TOKEN'        => (string) $token,
        'TOKEN_STATUS' => 'pendente',
        'TOKEN_EMAIL'  => $request->email,
        'expira_em'    => now()->addDays(7),
        'created_at'   => now(),
        'updated_at'   => now(),
    ]);
    return response()->json(['link' => url("/autocadastro/{$token}"), 'token' => $token]);
});
