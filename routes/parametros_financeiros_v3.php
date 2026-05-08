<?php
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

if (!function_exists('ensureParametroFinanceiroTableFromRoutes')) {
    function ensureParametroFinanceiroTableFromRoutes(): void
    {
        if (!Schema::hasTable('PARAMETRO_FINANCEIRO')) {
            throw new \RuntimeException('Tabela PARAMETRO_FINANCEIRO não encontrada. Execute migrations canônicas.');
        }
    }
}
Route::get('/parametros-financeiros', function () {
    ensureParametroFinanceiroTableFromRoutes();
    try { return response()->json(['parametros' => DB::table('PARAMETRO_FINANCEIRO')->orderBy('PARAM_TIPO')->get()]); }
    catch (\Throwable $e) { return response()->json(['parametros' => []]); }
});
Route::post('/parametros-financeiros', function (Request $request) {
    ensureParametroFinanceiroTableFromRoutes();
    try { $id = DB::table('PARAMETRO_FINANCEIRO')->insertGetId(['PARAM_TIPO'=>strtoupper($request->tipo),'PARAM_DESCRICAO'=>$request->descricao,'PARAM_VALOR'=>(float)$request->valor,'PARAM_TIPO_VALOR'=>strtoupper($request->tipo_valor??'ALIQUOTA'),'PARAM_COMPETENCIA'=>$request->competencia??null,'PARAM_VIGENCIA_INICIO'=>$request->vigencia_inicio??null,'PARAM_VIGENCIA_FIM'=>$request->vigencia_fim??null,'created_at'=>now(),'updated_at'=>now()]); return response()->json(['ok'=>true,'id'=>$id],201); }
    catch (\Throwable $e) { return response()->json(['erro'=>$e->getMessage()],422); }
})->middleware('perfil:ADMINISTRADOR,Administrador,GESTOR');
Route::put('/parametros-financeiros/{id}', function (int $id, Request $request) {
    ensureParametroFinanceiroTableFromRoutes();
    try { DB::table('PARAMETRO_FINANCEIRO')->where('PARAM_ID',$id)->update(['PARAM_TIPO'=>strtoupper($request->tipo),'PARAM_DESCRICAO'=>$request->descricao,'PARAM_VALOR'=>(float)$request->valor,'PARAM_TIPO_VALOR'=>strtoupper($request->tipo_valor??'ALIQUOTA'),'updated_at'=>now()]); return response()->json(['ok'=>true]); }
    catch (\Throwable $e) { return response()->json(['erro'=>$e->getMessage()],500); }
})->middleware('perfil:ADMINISTRADOR,Administrador,GESTOR');
Route::delete('/parametros-financeiros/{id}', function (int $id) {
    ensureParametroFinanceiroTableFromRoutes();
    try { DB::table('PARAMETRO_FINANCEIRO')->where('PARAM_ID',$id)->delete(); return response()->json(['ok'=>true]); }
    catch (\Throwable $e) { return response()->json(['erro'=>$e->getMessage()],500); }
})->middleware('perfil:ADMINISTRADOR,Administrador,GESTOR');
