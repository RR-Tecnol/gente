<?php
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;
Route::get('/tabelas/cartorio', function () {
    try {
        if (!Schema::hasTable('CARTORIO')) {
            return response()->json(['itens' => []]);
        }
        $q = DB::table('CARTORIO as c')
            ->orderBy('c.CARTORIO_NOME');
        if (Schema::hasColumn('CARTORIO', 'CIDADE_ID')) {
            $q->leftJoin('CIDADE as ci', 'ci.CIDADE_ID', '=', 'c.CIDADE_ID')
                ->select('c.CARTORIO_ID as id', 'c.CARTORIO_NOME as nome', 'c.CARTORIO_NUMERO as numero', 'ci.CIDADE_NOME as cidade_nome');
        } else {
            $q->select('c.CARTORIO_ID as id', 'c.CARTORIO_NOME as nome', 'c.CARTORIO_NUMERO as numero');
        }
        return response()->json(['itens' => $q->get()->map(fn ($r) => (array) $r)]);
    } catch (\Throwable $e) {
        return response()->json(['itens' => []]);
    }
});
Route::get('/tabelas/banco', function () {
    try { return response()->json(['itens'=>DB::table('BANCO')->orderBy('BANCO_NOME')->get()->map(fn($r)=>['id'=>$r->BANCO_ID,'codigo'=>$r->BANCO_CODIGO??'','nome'=>$r->BANCO_NOME])]); } catch (\Throwable $e) { return response()->json(['itens'=>[]]); }
});
Route::post('/tabelas/banco', function (Request $r) {
    try { $id=DB::table('BANCO')->insertGetId(['BANCO_CODIGO'=>$r->codigo,'BANCO_NOME'=>$r->nome]); return response()->json(['ok'=>true,'id'=>$id],201); } catch (\Throwable $e) { return response()->json(['erro'=>$e->getMessage()],422); }
});
Route::get('/tabelas/uf', function () {
    try { return response()->json(['itens'=>DB::table('UF')->orderBy('UF_SIGLA')->get()->map(fn($r)=>['id'=>$r->UF_ID,'sigla'=>$r->UF_SIGLA,'nome'=>$r->UF_NOME])]); } catch (\Throwable $e) { return response()->json(['itens'=>[]]); }
});
Route::get('/tabelas/cidade', function () {
    try { return response()->json(['itens'=>DB::table('CIDADE as c')->leftJoin('UF as u','u.UF_ID','=','c.UF_ID')->orderBy('c.CIDADE_NOME')->select('c.CIDADE_ID as id','c.CIDADE_NOME as nome','u.UF_SIGLA as uf_sigla')->get()->map(fn($r)=>(array)$r)]); } catch (\Throwable $e) { return response()->json(['itens'=>[]]); }
});
Route::get('/tabelas/bairro', function () {
    try { return response()->json(['itens'=>DB::table('BAIRRO as b')->leftJoin('CIDADE as c','c.CIDADE_ID','=','b.CIDADE_ID')->orderBy('b.BAIRRO_NOME')->select('b.BAIRRO_ID as id','b.BAIRRO_NOME as nome','c.CIDADE_NOME as cidade_nome')->get()->map(fn($r)=>(array)$r)]); } catch (\Throwable $e) { return response()->json(['itens'=>[]]); }
});
Route::get('/tabelas/conselho', function () {
    try { return response()->json(['itens'=>DB::table('CONSELHO')->orderBy('CONSELHO_NOME')->get()->map(fn($r)=>['id'=>$r->CONSELHO_ID,'sigla'=>$r->CONSELHO_SIGLA,'nome'=>$r->CONSELHO_NOME])]); } catch (\Throwable $e) { return response()->json(['itens'=>[]]); }
});
Route::get('/tabelas/tipo-documento', function () {
    try { return response()->json(['itens'=>DB::table('TIPO_DOCUMENTO')->orderBy('TIPO_DOCUMENTO_NOME')->get()->map(fn($r)=>['id'=>$r->TIPO_DOCUMENTO_ID,'nome'=>$r->TIPO_DOCUMENTO_NOME])]); } catch (\Throwable $e) { return response()->json(['itens'=>[]]); }
});
