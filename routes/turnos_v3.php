<?php
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
Route::get('/turnos', function () {
    try { return response()->json(['turnos' => DB::table('TURNO')->orderBy('TURNO_NOME')->get()->map(fn($t)=>['id'=>$t->TURNO_ID,'nome'=>$t->TURNO_NOME,'codigo'=>$t->TURNO_SIGLA??null,'hora_entrada'=>$t->TURNO_HORA_ENTRADA??null,'hora_saida'=>$t->TURNO_HORA_SAIDA??null,'carga_horaria'=>$t->TURNO_CARGA_HORARIA??null,'ativo'=>(bool)($t->TURNO_ATIVO??1)])]); }
    catch (\Throwable $e) { return response()->json(['turnos'=>[]]); }
});
Route::post('/turnos', function (Request $request) {
    try { $id = DB::table('TURNO')->insertGetId(['TURNO_NOME'=>$request->TURNO_NOME,'TURNO_SIGLA'=>$request->TURNO_CODIGO,'TURNO_HORA_ENTRADA'=>$request->TURNO_HORA_ENTRADA??null,'TURNO_HORA_SAIDA'=>$request->TURNO_HORA_SAIDA??null,'TURNO_CARGA_HORARIA'=>$request->TURNO_CARGA_HORARIA??null,'TURNO_ATIVO'=>1]); return response()->json(['ok'=>true,'id'=>$id],201); }
    catch (\Throwable $e) { return response()->json(['erro'=>$e->getMessage()],422); }
});
Route::put('/turnos/{id}', function (int $id, Request $request) {
    try { DB::table('TURNO')->where('TURNO_ID',$id)->update(['TURNO_NOME'=>$request->TURNO_NOME,'TURNO_SIGLA'=>$request->TURNO_CODIGO,'TURNO_HORA_ENTRADA'=>$request->TURNO_HORA_ENTRADA??null,'TURNO_HORA_SAIDA'=>$request->TURNO_HORA_SAIDA??null]); return response()->json(['ok'=>true]); }
    catch (\Throwable $e) { return response()->json(['erro'=>$e->getMessage()],500); }
});
Route::delete('/turnos/{id}', function (int $id) {
    try { DB::table('TURNO')->where('TURNO_ID',$id)->update(['TURNO_ATIVO'=>0]); return response()->json(['ok'=>true]); }
    catch (\Throwable $e) { return response()->json(['erro'=>$e->getMessage()],500); }
});
