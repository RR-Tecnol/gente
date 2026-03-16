<?php
// routes/diarias.php
use Illuminate\Support\Facades\Route;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;

// ⚠️ BUG-07 corrigido: NÃO reabrir Route::middleware()->prefix()->group() aqui.
// O contexto api/v3 + auth é herdado do web.php via require.

Route::get('/diarias', function (Request $req) {
    try {
        $q = DB::table('DIARIA_SOLICITACAO as ds')
            ->join('FUNCIONARIO as f', 'f.FUNCIONARIO_ID', '=', 'ds.FUNCIONARIO_ID')
            ->join('PESSOA as p', 'p.PESSOA_ID', '=', 'f.PESSOA_ID')
            ->select('ds.*', 'p.PESSOA_NOME as nome', 'f.FUNCIONARIO_MATRICULA as matricula')
            ->orderByDesc('ds.created_at');
        if ($req->status)
            $q->where('ds.STATUS', $req->status);
        if ($req->funcionario_id)
            $q->where('ds.FUNCIONARIO_ID', $req->funcionario_id);
        return response()->json(['diarias' => $q->paginate(30)]);
    } catch (\Throwable $e) {
        return response()->json(['fallback' => true, 'diarias' => ['data' => []]]);
    }
});

Route::post('/diarias', function (Request $req) {
    $req->validate(['funcionario_id' => 'required', 'destino' => 'required', 'objetivo' => 'required', 'data_ida' => 'required|date', 'data_volta' => 'required|date']);
    try {
        $dias = (new \DateTime($req->data_ida))->diff(new \DateTime($req->data_volta))->days + 1;
        $tabela = DB::table('DIARIA_TABELA')->where('DESTINO_TIPO', strtoupper($req->destino_tipo ?? 'CAPITAL_MA'))->orderByDesc('VIGENCIA_INICIO')->first();
        $valor = $tabela ? floatval($tabela->VALOR_DIARIA) * $dias : 0;
        $id = DB::table('DIARIA_SOLICITACAO')->insertGetId([
            'FUNCIONARIO_ID' => $req->funcionario_id,
            'DESTINO' => $req->destino,
            'DESTINO_TIPO' => strtoupper($req->destino_tipo ?? 'CAPITAL_MA'),
            'OBJETIVO' => $req->objetivo,
            'DATA_IDA' => $req->data_ida,
            'DATA_VOLTA' => $req->data_volta,
            'QTDE_DIARIAS' => $dias,
            'VALOR_TOTAL' => $valor,
            'STATUS' => 'PENDENTE',
            'created_at' => now(),
            'updated_at' => now(),
        ]);
        return response()->json(['mensagem' => 'Solicitação registrada.', 'id' => $id, 'qtde_diarias' => $dias, 'valor_total' => $valor]);
    } catch (\Throwable $e) {
        return response()->json(['erro' => $e->getMessage()], 500);
    }
});

Route::patch('/diarias/{id}/status', function (Request $req, $id) {
    $req->validate(['status' => 'required|string']);
    try {
        DB::table('DIARIA_SOLICITACAO')->where('SOLICITACAO_ID', $id)->update(['STATUS' => strtoupper($req->status), 'updated_at' => now()]);
        return response()->json(['mensagem' => 'Status atualizado.']);
    } catch (\Throwable $e) {
        return response()->json(['erro' => $e->getMessage()], 500);
    }
});

Route::post('/diarias/{id}/prestacao', function (Request $req, $id) {
    try {
        DB::table('DIARIA_PRESTACAO')->insert([
            'SOLICITACAO_ID' => $id,
            'VALOR_GASTO' => $req->valor_gasto ?? 0,
            'SALDO_DEVOLVIDO' => $req->saldo_devolvido ?? 0,
            'DATA_PRESTACAO' => now()->toDateString(),
            'OBSERVACAO' => $req->observacao,
            'created_at' => now(),
            'updated_at' => now(),
        ]);
        DB::table('DIARIA_SOLICITACAO')->where('SOLICITACAO_ID', $id)->update(['STATUS' => 'CONCLUIDA', 'updated_at' => now()]);
        return response()->json(['mensagem' => 'Prestação registrada.']);
    } catch (\Throwable $e) {
        return response()->json(['erro' => $e->getMessage()], 500);
    }
});

Route::get('/diarias/tabela-valores', function () {
    try {
        return response()->json(['tabela' => DB::table('DIARIA_TABELA')->orderByDesc('VIGENCIA_INICIO')->get()]);
    } catch (\Throwable $e) {
        return response()->json(['fallback' => true, 'tabela' => []]);
    }
});

