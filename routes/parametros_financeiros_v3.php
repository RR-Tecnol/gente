<?php
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

if (!Schema::hasTable('PARAMETRO_FINANCEIRO')) {
    Schema::create('PARAMETRO_FINANCEIRO', function ($t) {
        $t->increments('PARAM_ID');
        $t->string('PARAM_TIPO', 30);
        $t->string('PARAM_DESCRICAO', 200);
        $t->string('PARAM_COMPETENCIA', 6)->nullable();
        $t->decimal('PARAM_VALOR', 12, 4);
        $t->string('PARAM_TIPO_VALOR', 20)->default('ALIQUOTA');
        $t->date('PARAM_VIGENCIA_INICIO')->nullable();
        $t->date('PARAM_VIGENCIA_FIM')->nullable();
        $t->timestamps();
    });
}
Route::get('/parametros-financeiros', function () {
    try { return response()->json(['parametros' => DB::table('PARAMETRO_FINANCEIRO')->orderBy('PARAM_TIPO')->get()]); }
    catch (\Throwable $e) { return response()->json(['parametros' => []]); }
});
Route::post('/parametros-financeiros', function (Request $request) {
    try { $id = DB::table('PARAMETRO_FINANCEIRO')->insertGetId(['PARAM_TIPO'=>strtoupper($request->tipo),'PARAM_DESCRICAO'=>$request->descricao,'PARAM_VALOR'=>(float)$request->valor,'PARAM_TIPO_VALOR'=>strtoupper($request->tipo_valor??'ALIQUOTA'),'PARAM_COMPETENCIA'=>$request->competencia??null,'PARAM_VIGENCIA_INICIO'=>$request->vigencia_inicio??null,'PARAM_VIGENCIA_FIM'=>$request->vigencia_fim??null,'created_at'=>now(),'updated_at'=>now()]); return response()->json(['ok'=>true,'id'=>$id],201); }
    catch (\Throwable $e) { return response()->json(['erro'=>$e->getMessage()],422); }
});
Route::put('/parametros-financeiros/{id}', function (int $id, Request $request) {
    try { DB::table('PARAMETRO_FINANCEIRO')->where('PARAM_ID',$id)->update(['PARAM_TIPO'=>strtoupper($request->tipo),'PARAM_DESCRICAO'=>$request->descricao,'PARAM_VALOR'=>(float)$request->valor,'PARAM_TIPO_VALOR'=>strtoupper($request->tipo_valor??'ALIQUOTA'),'updated_at'=>now()]); return response()->json(['ok'=>true]); }
    catch (\Throwable $e) { return response()->json(['erro'=>$e->getMessage()],500); }
});
Route::delete('/parametros-financeiros/{id}', function (int $id) {
    try { DB::table('PARAMETRO_FINANCEIRO')->where('PARAM_ID',$id)->delete(); return response()->json(['ok'=>true]); }
    catch (\Throwable $e) { return response()->json(['erro'=>$e->getMessage()],500); }
});
