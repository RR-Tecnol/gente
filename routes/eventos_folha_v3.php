<?php
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
Route::get('/eventos', function () {
    try { return response()->json(['eventos'=>DB::table('EVENTO')->where('EVENTO_ATIVO',1)->orderBy('EVENTO_CODIGO')->get()->map(fn($e)=>['id'=>$e->EVENTO_ID,'codigo'=>$e->EVENTO_CODIGO,'nome'=>$e->EVENTO_DESCRICAO??$e->EVENTO_NOME??'','tipo'=>$e->EVENTO_TIPO??'P','inss'=>(bool)($e->EVENTO_INCIDE_INSS??false),'irrf'=>(bool)($e->EVENTO_INCIDE_IRRF??false),'fgts'=>(bool)($e->EVENTO_INCIDE_FGTS??false),'ativo'=>(bool)($e->EVENTO_ATIVO??true)])]); }
    catch (\Throwable $e) { return response()->json(['eventos'=>[]]); }
});
Route::post('/eventos', function (Request $request) {
    try { $id=DB::table('EVENTO')->insertGetId(['EVENTO_CODIGO'=>strtoupper($request->EVENTO_CODIGO),'EVENTO_DESCRICAO'=>$request->EVENTO_NOME,'EVENTO_TIPO'=>strtoupper($request->EVENTO_TIPO??'P'),'EVENTO_INCIDE_INSS'=>$request->EVENTO_INSS?1:0,'EVENTO_INCIDE_IRRF'=>$request->EVENTO_IRRF?1:0,'EVENTO_INCIDE_FGTS'=>$request->EVENTO_FGTS?1:0,'EVENTO_ATIVO'=>1,'created_at'=>now(),'updated_at'=>now()]); return response()->json(['ok'=>true,'id'=>$id],201); }
    catch (\Throwable $e) { return response()->json(['erro'=>$e->getMessage()],422); }
});
Route::put('/eventos/{id}', function (int $id, Request $request) {
    try { DB::table('EVENTO')->where('EVENTO_ID',$id)->update(['EVENTO_CODIGO'=>strtoupper($request->EVENTO_CODIGO),'EVENTO_DESCRICAO'=>$request->EVENTO_NOME,'EVENTO_TIPO'=>strtoupper($request->EVENTO_TIPO??'P'),'EVENTO_INCIDE_INSS'=>$request->EVENTO_INSS?1:0,'EVENTO_INCIDE_IRRF'=>$request->EVENTO_IRRF?1:0,'EVENTO_INCIDE_FGTS'=>$request->EVENTO_FGTS?1:0,'updated_at'=>now()]); return response()->json(['ok'=>true]); }
    catch (\Throwable $e) { return response()->json(['erro'=>$e->getMessage()],500); }
});
Route::delete('/eventos/{id}', function (int $id) {
    try { DB::table('EVENTO')->where('EVENTO_ID',$id)->update(['EVENTO_ATIVO'=>0,'updated_at'=>now()]); return response()->json(['ok'=>true]); }
    catch (\Throwable $e) { return response()->json(['erro'=>$e->getMessage()],500); }
});
