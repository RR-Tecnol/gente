<?php
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
Route::get('/feriados', function () {
    try { return response()->json(['feriados' => DB::table('FERIADO')->orderBy('FERIADO_DATA')->get()->map(fn($f)=>['id'=>$f->FERIADO_ID,'nome'=>$f->FERIADO_NOME??$f->FERIADO_DESCRICAO??'','data'=>$f->FERIADO_DATA,'tipo'=>$f->FERIADO_TIPO??'N','recorrente'=>(bool)($f->FERIADO_RECORRENTE??false)])]); }
    catch (\Throwable $e) { return response()->json(['feriados'=>[]]); }
});
Route::post('/feriados', function (Request $request) {
    try { $id = DB::table('FERIADO')->insertGetId(['FERIADO_NOME'=>$request->FERIADO_NOME,'FERIADO_DATA'=>$request->FERIADO_DATA,'FERIADO_TIPO'=>$request->FERIADO_TIPO??'N','FERIADO_RECORRENTE'=>$request->FERIADO_RECORRENTE?1:0]); return response()->json(['ok'=>true,'id'=>$id],201); }
    catch (\Throwable $e) { return response()->json(['erro'=>$e->getMessage()],422); }
});
Route::put('/feriados/{id}', function (int $id, Request $request) {
    try { DB::table('FERIADO')->where('FERIADO_ID',$id)->update(['FERIADO_NOME'=>$request->FERIADO_NOME,'FERIADO_DATA'=>$request->FERIADO_DATA,'FERIADO_TIPO'=>$request->FERIADO_TIPO??'N','FERIADO_RECORRENTE'=>$request->FERIADO_RECORRENTE?1:0]); return response()->json(['ok'=>true]); }
    catch (\Throwable $e) { return response()->json(['erro'=>$e->getMessage()],500); }
});
Route::delete('/feriados/{id}', function (int $id) {
    try { DB::table('FERIADO')->where('FERIADO_ID',$id)->delete(); return response()->json(['ok'=>true]); }
    catch (\Throwable $e) { return response()->json(['erro'=>$e->getMessage()],500); }
});
