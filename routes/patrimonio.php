<?php

use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Route;
use App\Services\DepreciacaoService;

// Catálogo de bens com filtros
Route::get('/patrimonio/bens', function () {
    try {
        $q = DB::table('BEM_PATRIMONIAL');
        if (request('uo_id'))      $q->where('UO_ID', request('uo_id'));
        if (request('categoria'))  $q->where('BEM_CATEGORIA', request('categoria'));
        if (request('status'))     $q->where('BEM_STATUS', request('status'));
        if (request('estado'))     $q->where('BEM_ESTADO', request('estado'));

        $bens = $q->orderBy('BEM_NUMERO')->limit(500)->get();
        return response()->json(['bens' => $bens, 'total' => $bens->count()]);
    } catch (\Throwable $e) {
        return response()->json(['erro' => $e->getMessage()], 500);
    }
});

// Tombar novo bem
Route::post('/patrimonio/bens', function () {
    try {
        $data = request()->validate([
            'bem_numero'        => 'required|string|max:30',
            'bem_descricao'     => 'required|string|max:300',
            'bem_categoria'     => 'required|string',
            'bem_valor_aquisicao' => 'required|numeric|min:0',
            'bem_data_aquisicao'  => 'required|date',
            'bem_estado'        => 'nullable|string',
            'uo_id'             => 'nullable|integer',
            'servidor_id'       => 'nullable|integer',
        ]);

        // Calcular parâmetros NBCASP automaticamente
        $service = new DepreciacaoService();
        $params  = $service->parametrosPorCategoria(
            $data['bem_categoria'],
            (float) $data['bem_valor_aquisicao']
        );

        $id = DB::table('BEM_PATRIMONIAL')->insertGetId([
            'BEM_NUMERO'           => strtoupper($data['bem_numero']),
            'BEM_DESCRICAO'        => $data['bem_descricao'],
            'BEM_CATEGORIA'        => strtoupper($data['bem_categoria']),
            'BEM_VALOR_AQUISICAO'  => $data['bem_valor_aquisicao'],
            'BEM_DATA_AQUISICAO'   => $data['bem_data_aquisicao'],
            'BEM_VALOR_ATUAL'      => $data['bem_valor_aquisicao'], // inicial = aquisição
            'BEM_ESTADO'           => strtoupper($data['bem_estado'] ?? 'BOM'),
            'BEM_STATUS'           => 'ATIVO',
            'UO_ID'                => $data['uo_id'] ?? null,
            'SERVIDOR_ID'          => $data['servidor_id'] ?? null,
            'BEM_VIDA_UTIL_ANOS'   => $params['vida_util_anos'],
            'BEM_VALOR_RESIDUAL'   => $params['valor_residual'],
            'BEM_DEPRECIACAO_ACUMULADA' => 0,
            'created_at'           => now(), 'updated_at' => now(),
        ]);

        return response()->json(['ok' => true, 'id' => $id, 'params_nbcasp' => $params], 201);
    } catch (\Throwable $e) {
        return response()->json(['erro' => $e->getMessage()], 422);
    }
});

// Atualizar bem
Route::put('/patrimonio/bens/{id}', function (int $id) {
    try {
        DB::table('BEM_PATRIMONIAL')->where('BEM_ID', $id)->update([
            'BEM_DESCRICAO' => request('bem_descricao'),
            'BEM_ESTADO'    => strtoupper(request('bem_estado', 'BOM')),
            'UO_ID'         => request('uo_id'),
            'SERVIDOR_ID'   => request('servidor_id'),
            'updated_at'    => now(),
        ]);
        return response()->json(['ok' => true]);
    } catch (\Throwable $e) {
        return response()->json(['erro' => $e->getMessage()], 422);
    }
});

// Transferir bem entre unidades
Route::post('/patrimonio/bens/{id}/transferir', function (int $id) {
    try {
        $bem = DB::table('BEM_PATRIMONIAL')->where('BEM_ID', $id)->first();
        if (!$bem) return response()->json(['erro' => 'Bem não encontrado.'], 404);

        $uoDestino = request()->validate(['uo_destino_id' => 'required|integer'])['uo_destino_id'];

        DB::transaction(function () use ($bem, $id, $uoDestino) {
            DB::table('MOVIMENTACAO_PATRIMONIAL')->insert([
                'BEM_ID'          => $id,
                'MOV_TIPO'        => 'TRANSFERENCIA',
                'UO_ORIGEM_ID'    => $bem->UO_ID,
                'UO_DESTINO_ID'   => $uoDestino,
                'MOV_MOTIVO'      => request('motivo'),
                'MOV_DATA'        => now()->toDateString(),
                'REGISTRADO_POR'  => Auth::id(),
                'created_at'      => now(), 'updated_at' => now(),
            ]);
            DB::table('BEM_PATRIMONIAL')->where('BEM_ID', $id)->update([
                'UO_ID'      => $uoDestino,
                'updated_at' => now(),
            ]);
        });

        return response()->json(['ok' => true]);
    } catch (\Throwable $e) {
        return response()->json(['erro' => $e->getMessage()], 500);
    }
});

// Dar baixa no bem
Route::post('/patrimonio/bens/{id}/baixar', function (int $id) {
    try {
        $bem = DB::table('BEM_PATRIMONIAL')->where('BEM_ID', $id)->first();
        if (!$bem) return response()->json(['erro' => 'Bem não encontrado.'], 404);

        DB::transaction(function () use ($bem, $id) {
            // Logically we use an update instead of insert since the request says insert logic from db is buggy on baixas in raw queries? No, the code has insert.
            DB::table('MOVIMENTACAO_PATRIMONIAL')->insert([
                'BEM_ID'         => $id,
                'MOV_TIPO'       => 'BAIXA',
                'UO_ORIGEM_ID'   => $bem->UO_ID,
                'UO_DESTINO_ID'  => null,
                'MOV_MOTIVO'     => request('motivo', 'Baixa patrimonial'),
                'MOV_DATA'       => now()->toDateString(),
                'REGISTRADO_POR' => Auth::id(),
                'created_at'     => now(), 'updated_at' => now(),
            ]);
            DB::table('BEM_PATRIMONIAL')->where('BEM_ID', $id)->update([
                'BEM_STATUS' => 'BAIXADO',
                'updated_at' => now(),
            ]);
        });

        return response()->json(['ok' => true]);
    } catch (\Throwable $e) {
        return response()->json(['erro' => $e->getMessage()], 500);
    }
});

// Movimentações list (not requested but required for tab)
Route::get('/patrimonio/movimentacoes', function () {
    try {
        $movs = DB::table('MOVIMENTACAO_PATRIMONIAL as m')
            ->join('BEM_PATRIMONIAL as b', 'b.BEM_ID', '=', 'm.BEM_ID')
            ->select('m.*', 'b.BEM_NUMERO', 'b.BEM_DESCRICAO')
            ->orderByDesc('m.MOV_DATA')
            ->limit(200)
            ->get();
        return response()->json(['movimentacoes' => $movs]);
    } catch (\Throwable $e) {
        return response()->json(['erro' => $e->getMessage()], 500);
    }
});

// Inventário por unidade
Route::get('/patrimonio/inventario/{uo_id}', function (int $uo_id) {
    try {
        $bens = DB::table('BEM_PATRIMONIAL')
            ->where('UO_ID', $uo_id)
            ->where('BEM_STATUS', 'ATIVO')
            ->orderBy('BEM_CATEGORIA')
            ->orderBy('BEM_NUMERO')
            ->get();
        return response()->json([
            'uo_id'          => $uo_id,
            'bens'           => $bens,
            'total'          => $bens->count(),
            'valor_total'    => $bens->sum('BEM_VALOR_ATUAL'),
        ]);
    } catch (\Throwable $e) {
        return response()->json(['erro' => $e->getMessage()], 500);
    }
});

// Relatório de depreciação
Route::get('/patrimonio/depreciacao', function () {
    try {
        $bens = DB::table('BEM_PATRIMONIAL')
            ->where('BEM_STATUS', 'ATIVO')
            ->select(
                'BEM_CATEGORIA',
                DB::raw('COUNT(*) as qtd'),
                DB::raw('SUM(BEM_VALOR_AQUISICAO) as valor_aquisicao'),
                DB::raw('SUM(BEM_DEPRECIACAO_ACUMULADA) as depreciacao_acumulada'),
                DB::raw('SUM(BEM_VALOR_ATUAL) as valor_atual')
            )
            ->groupBy('BEM_CATEGORIA')
            ->get();

        return response()->json([
            'por_categoria'          => $bens,
            'total_aquisicao'        => $bens->sum('valor_aquisicao'),
            'total_depreciado'       => $bens->sum('depreciacao_acumulada'),
            'total_valor_atual'      => $bens->sum('valor_atual'),
        ]);
    } catch (\Throwable $e) {
        return response()->json(['erro' => $e->getMessage()], 500);
    }
});

// Executar depreciação mensal
Route::post('/patrimonio/depreciar/{competencia}', function (string $competencia) {
    try {
        $service   = new DepreciacaoService();
        $resultado = $service->depreciarMes($competencia);
        return response()->json(['ok' => true] + $resultado);
    } catch (\Throwable $e) {
        return response()->json(['erro' => $e->getMessage()], 500);
    }
});
