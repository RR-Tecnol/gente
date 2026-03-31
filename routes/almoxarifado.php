<?php

use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Route;

// Catálogo de itens com saldo atual
Route::get('/almoxarifado/itens', function () {
    try {
        $itens = DB::table('ITEM_ESTOQUE as i')
            ->leftJoin('SALDO_ESTOQUE as s', 's.ITEM_ID', '=', 'i.ITEM_ID')
            ->where('i.ITEM_ATIVO', true)
            ->select(
                'i.*',
                DB::raw('COALESCE(SUM(s.SALDO_QUANTIDADE), 0) as saldo_total'),
                DB::raw('COALESCE(SUM(s.SALDO_VALOR_MEDIO * s.SALDO_QUANTIDADE), 0) as valor_estoque')
            )
            ->groupBy('i.ITEM_ID', 'i.ITEM_CODIGO', 'i.ITEM_DESCRICAO',
                      'i.ITEM_UNIDADE', 'i.ITEM_CATEGORIA',
                      'i.ITEM_ESTOQUE_MINIMO', 'i.ITEM_ATIVO',
                      'i.created_at', 'i.updated_at')
            ->orderBy('i.ITEM_DESCRICAO')
            ->get();
        return response()->json(['itens' => $itens]);
    } catch (\Throwable $e) {
        return response()->json(['erro' => $e->getMessage()], 500);
    }
});

Route::post('/almoxarifado/itens', function () {
    try {
        $data = request()->validate([
            'item_codigo'    => 'required|string|max:20',
            'item_descricao' => 'required|string|max:300',
            'item_unidade'   => 'required|string|max:10',
            'item_categoria' => 'nullable|string|max:50',
            'item_estoque_minimo' => 'nullable|integer|min:0',
        ]);
        $id = DB::table('ITEM_ESTOQUE')->insertGetId([
            'ITEM_CODIGO'          => strtoupper($data['item_codigo']),
            'ITEM_DESCRICAO'       => $data['item_descricao'],
            'ITEM_UNIDADE'         => strtoupper($data['item_unidade']),
            'ITEM_CATEGORIA'       => $data['item_categoria'] ?? null,
            'ITEM_ESTOQUE_MINIMO'  => $data['item_estoque_minimo'] ?? 0,
            'ITEM_ATIVO'           => true,
            'created_at'           => now(), 'updated_at' => now(),
        ]);
        return response()->json(['ok' => true, 'id' => $id], 201);
    } catch (\Throwable $e) {
        return response()->json(['erro' => $e->getMessage()], 422);
    }
});

// Itens abaixo do estoque mínimo
Route::get('/almoxarifado/abaixo-minimo', function () {
    try {
        $itens = DB::table('ITEM_ESTOQUE as i')
            ->leftJoin('SALDO_ESTOQUE as s', 's.ITEM_ID', '=', 'i.ITEM_ID')
            ->where('i.ITEM_ATIVO', true)
            ->groupBy('i.ITEM_ID', 'i.ITEM_CODIGO', 'i.ITEM_DESCRICAO',
                      'i.ITEM_UNIDADE', 'i.ITEM_ESTOQUE_MINIMO',
                      'i.ITEM_CATEGORIA', 'i.ITEM_ATIVO',
                      'i.created_at', 'i.updated_at')
            ->havingRaw('COALESCE(SUM(s.SALDO_QUANTIDADE), 0) < i.ITEM_ESTOQUE_MINIMO')
            ->select('i.*', DB::raw('COALESCE(SUM(s.SALDO_QUANTIDADE), 0) as saldo_total'))
            ->get();
        return response()->json(['itens' => $itens, 'total' => $itens->count()]);
    } catch (\Throwable $e) {
        return response()->json(['erro' => $e->getMessage()], 500);
    }
});

// Entrada de material
Route::post('/almoxarifado/entrada', function () {
    try {
        $data = request()->validate([
            'almox_id'          => 'required|integer',
            'item_id'           => 'required|integer',
            'mov_quantidade'    => 'required|integer|min:1',
            'mov_valor_unitario'=> 'nullable|numeric|min:0',
            'mov_documento'     => 'nullable|string|max:50',
            'pedido_compra_id'  => 'nullable|integer',
        ]);

        DB::transaction(function () use ($data) {
            // Registrar movimentação
            DB::table('MOVIMENTACAO_ESTOQUE')->insert([
                'ALMOX_ID'          => $data['almox_id'],
                'ITEM_ID'           => $data['item_id'],
                'MOV_TIPO'          => 'ENTRADA',
                'MOV_QUANTIDADE'    => $data['mov_quantidade'],
                'MOV_VALOR_UNITARIO'=> $data['mov_valor_unitario'] ?? null,
                'MOV_DOCUMENTO'     => $data['mov_documento'] ?? null,
                'PEDIDO_COMPRA_ID'  => $data['pedido_compra_id'] ?? null,
                'REGISTRADO_POR'    => Auth::id(),
                'created_at'        => now(), 'updated_at' => now(),
            ]);

            // Atualizar saldo (upsert)
            $saldo = DB::table('SALDO_ESTOQUE')
                ->where('ALMOX_ID', $data['almox_id'])
                ->where('ITEM_ID', $data['item_id'])
                ->first();

            if ($saldo) {
                // calcular novo custo medio simples: (preco_antigo*qtd_antiga + preco_novo*qtd_nova) / (qtd_nova+qtd_antiga)
                // Porem, aqui so somamos qtd pra simplificar se for zero
                DB::table('SALDO_ESTOQUE')
                    ->where('SALDO_ID', $saldo->SALDO_ID)
                    ->update([
                        'SALDO_QUANTIDADE' => $saldo->SALDO_QUANTIDADE + $data['mov_quantidade'],
                        'SALDO_VALOR_MEDIO' => $data['mov_valor_unitario'] ?? $saldo->SALDO_VALOR_MEDIO,
                        'updated_at'       => now(),
                    ]);
            } else {
                DB::table('SALDO_ESTOQUE')->insert([
                    'ALMOX_ID'         => $data['almox_id'],
                    'ITEM_ID'          => $data['item_id'],
                    'SALDO_QUANTIDADE' => $data['mov_quantidade'],
                    'SALDO_VALOR_MEDIO'=> $data['mov_valor_unitario'] ?? 0,
                    'created_at'       => now(), 'updated_at' => now(),
                ]);
            }
        });

        return response()->json(['ok' => true]);
    } catch (\Throwable $e) {
        return response()->json(['erro' => $e->getMessage()], 422);
    }
});

// Saída de material
Route::post('/almoxarifado/saida', function () {
    try {
        $data = request()->validate([
            'almox_id'       => 'required|integer',
            'item_id'        => 'required|integer',
            'mov_quantidade' => 'required|integer|min:1',
            'uo_destino_id'  => 'nullable|integer',
            'mov_documento'  => 'nullable|string|max:50',
            'mov_obs'        => 'nullable|string',
        ]);

        DB::transaction(function () use ($data) {
            $saldo = DB::table('SALDO_ESTOQUE')
                ->where('ALMOX_ID', $data['almox_id'])
                ->where('ITEM_ID', $data['item_id'])
                ->first();

            if (!$saldo || $saldo->SALDO_QUANTIDADE < $data['mov_quantidade']) {
                throw new \RuntimeException('Saldo insuficiente para esta saída.');
            }

            DB::table('MOVIMENTACAO_ESTOQUE')->insert([
                'ALMOX_ID'       => $data['almox_id'],
                'ITEM_ID'        => $data['item_id'],
                'MOV_TIPO'       => 'SAIDA',
                'MOV_QUANTIDADE' => $data['mov_quantidade'],
                'UO_DESTINO_ID'  => $data['uo_destino_id'] ?? null,
                'MOV_DOCUMENTO'  => $data['mov_documento'] ?? null,
                'MOV_OBS'        => $data['mov_obs'] ?? null,
                'REGISTRADO_POR' => Auth::id(),
                'created_at'     => now(), 'updated_at' => now(),
            ]);

            DB::table('SALDO_ESTOQUE')
                ->where('SALDO_ID', $saldo->SALDO_ID)
                ->update([
                    'SALDO_QUANTIDADE' => $saldo->SALDO_QUANTIDADE - $data['mov_quantidade'],
                    'updated_at'       => now(),
                ]);
        });

        return response()->json(['ok' => true]);
    } catch (\RuntimeException $e) {
        return response()->json(['erro' => $e->getMessage()], 422);
    } catch (\Throwable $e) {
        return response()->json(['erro' => $e->getMessage()], 500);
    }
});

// Histórico de movimentações
Route::get('/almoxarifado/movimentacoes', function () {
    try {
        $q = DB::table('MOVIMENTACAO_ESTOQUE as m')
            ->join('ITEM_ESTOQUE as i', 'i.ITEM_ID', '=', 'm.ITEM_ID')
            ->join('ALMOXARIFADO as a', 'a.ALMOX_ID', '=', 'm.ALMOX_ID')
            ->select('m.*', 'i.ITEM_DESCRICAO', 'i.ITEM_UNIDADE', 'a.ALMOX_NOME');

        if (request('item_id')) $q->where('m.ITEM_ID', request('item_id'));
        if (request('tipo'))    $q->where('m.MOV_TIPO', request('tipo'));

        $movs = $q->orderByDesc('m.created_at')->limit(200)->get();
        return response()->json(['movimentacoes' => $movs]);
    } catch (\Throwable $e) {
        return response()->json(['erro' => $e->getMessage()], 500);
    }
});

// Lista de almoxarifados (para selects)
Route::get('/almoxarifado/lista', function () {
    try {
        $almox = DB::table('ALMOXARIFADO')->where('ALMOX_ATIVO', true)->get();
        return response()->json(['almoxarifados' => $almox]);
    } catch (\Throwable $e) {
        return response()->json(['erro' => $e->getMessage()], 500);
    }
});
