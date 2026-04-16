<?php
use Illuminate\Support\Facades\Route;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Auth;

Route::get('/escala-trabalho', function (\Illuminate\Http\Request $request) {
    $user = Auth::user();
    $func = DB::table('FUNCIONARIO')->where('USUARIO_ID', $user->USUARIO_ID)->first();
    if (!$func) return response()->json(['escala' => []]);
    $mes = $request->mes ?? now()->month;
    $ano = $request->ano ?? now()->year;
    $comp = sprintf('%04d-%02d', $ano, $mes);
    $escala = DB::table('ESCALA as e')
        ->join('DETALHE_ESCALA as de', 'de.ESCALA_ID', '=', 'e.ESCALA_ID')
        ->where('de.FUNCIONARIO_ID', $func->FUNCIONARIO_ID)
        ->where('e.ESCALA_COMPETENCIA', $comp)
        ->select('e.*', 'de.*')
        ->get();
    return response()->json(['escala' => $escala, 'competencia' => $comp]);
});

Route::post('/escala-trabalho', function (\Illuminate\Http\Request $request) {
    return response()->json(['ok' => true]);
});
