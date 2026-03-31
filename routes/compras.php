<?php

use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Auth;

// Processos licitatórios
Route::get('/compras/processos', function () {
    try {
        $processos = DB::table('PROCESSO_LICITATORIO')
            ->orderByDesc('PROCESSO_DATA_ABERTURA')
            ->limit(200)
            ->get();
        return response()->json(['processos' => $processos, 'total' => $processos->count()]);
    } catch (\Throwable $e) {
        return response()->json(['erro' => $e->getMessage()], 500);
    }
});

Route::post('/compras/processos', function () {
    try {
        $data = request()->validate([
            'processo_numero'    => 'required|string|max:30',
            'processo_modalidade'=> 'required|string',
            'processo_objeto'    => 'required|string',
            'processo_data_abertura' => 'required|date',
            'processo_valor_estimado' => 'nullable|numeric|min:0',
        ]);
        $id = DB::table('PROCESSO_LICITATORIO')->insertGetId([
            'PROCESSO_NUMERO'         => strtoupper($data['processo_numero']),
            'PROCESSO_MODALIDADE'     => $data['processo_modalidade'],
            'PROCESSO_OBJETO'         => $data['processo_objeto'],
            'PROCESSO_DATA_ABERTURA'  => $data['processo_data_abertura'],
            'PROCESSO_VALOR_ESTIMADO' => $data['processo_valor_estimado'] ?? null,
            'PROCESSO_STATUS'         => 'ABERTO',
            'USUARIO_ID'              => Auth::id(),
            'created_at'              => now(), 'updated_at' => now(),
        ]);
        return response()->json(['ok' => true, 'id' => $id], 201);
    } catch (\Throwable $e) {
        return response()->json(['erro' => $e->getMessage()], 422);
    }
});

// Contratos
Route::get('/compras/contratos', function () {
    try {
        $contratos = DB::table('CONTRATO_ADMINISTRATIVO')
            ->orderByDesc('CONTRATO_INICIO')
            ->limit(200)
            ->get();
        return response()->json(['contratos' => $contratos]);
    } catch (\Throwable $e) {
        return response()->json(['erro' => $e->getMessage()], 500);
    }
});

Route::get('/compras/contratos/vencendo', function () {
    try {
        $limite = now()->addDays(60)->toDateString();
        $contratos = DB::table('CONTRATO_ADMINISTRATIVO')
            ->where('CONTRATO_STATUS', 'VIGENTE')
            ->where('CONTRATO_FIM', '<=', $limite)
            ->orderBy('CONTRATO_FIM')
            ->get();
        return response()->json(['contratos' => $contratos, 'total' => $contratos->count()]);
    } catch (\Throwable $e) {
        return response()->json(['erro' => $e->getMessage()], 500);
    }
});

Route::post('/compras/contratos', function () {
    try {
        $data = request()->validate([
            'contrato_numero'    => 'required|string|max:30',
            'contrato_objeto'    => 'required|string',
            'contrato_valor'     => 'required|numeric|min:0',
            'contrato_inicio'    => 'required|date',
            'contrato_fim'       => 'required|date|after:contrato_inicio',
            'contrato_fornecedor'=> 'nullable|string|max:150',
            'processo_id'        => 'nullable|integer',
        ]);
        $id = DB::table('CONTRATO_ADMINISTRATIVO')->insertGetId([
            'CONTRATO_NUMERO'     => strtoupper($data['contrato_numero']),
            'CONTRATO_OBJETO'     => $data['contrato_objeto'],
            'CONTRATO_VALOR'      => $data['contrato_valor'],
            'CONTRATO_INICIO'     => $data['contrato_inicio'],
            'CONTRATO_FIM'        => $data['contrato_fim'],
            'CONTRATO_FORNECEDOR' => $data['contrato_fornecedor'] ?? null,
            'PROCESSO_ID'         => $data['processo_id'] ?? null,
            'CONTRATO_STATUS'     => 'VIGENTE',
            'USUARIO_ID'          => Auth::id(),
            'created_at'          => now(), 'updated_at' => now(),
        ]);
        return response()->json(['ok' => true, 'id' => $id], 201);
    } catch (\Throwable $e) {
        return response()->json(['erro' => $e->getMessage()], 422);
    }
});

// Pedidos de compra
Route::get('/compras/pedidos', function () {
    try {
        $pedidos = DB::table('PEDIDO_COMPRA')
            ->orderByDesc('created_at')
            ->limit(200)
            ->get();
        return response()->json(['pedidos' => $pedidos]);
    } catch (\Throwable $e) {
        return response()->json(['erro' => $e->getMessage()], 500);
    }
});

Route::post('/compras/pedidos', function () {
    try {
        $data = request()->validate([
            'pedido_descricao'       => 'required|string',
            'pedido_valor_estimado'  => 'nullable|numeric|min:0',
            'uo_id'                  => 'nullable|integer',
        ]);
        $id = DB::table('PEDIDO_COMPRA')->insertGetId([
            'PEDIDO_DESCRICAO'       => $data['pedido_descricao'],
            'PEDIDO_VALOR_ESTIMADO'  => $data['pedido_valor_estimado'] ?? null,
            'UO_ID'                  => $data['uo_id'] ?? null,
            'PEDIDO_STATUS'          => 'SOLICITADO',
            'SOLICITANTE_ID'         => Auth::id(),
            'created_at'             => now(), 'updated_at' => now(),
        ]);
        return response()->json(['ok' => true, 'id' => $id], 201);
    } catch (\Throwable $e) {
        return response()->json(['erro' => $e->getMessage()], 422);
    }
});

Route::patch('/compras/pedidos/{id}/vincular', function (int $id) {
    try {
        $processoId = request('processo_id');
        DB::table('PEDIDO_COMPRA')->where('PEDIDO_ID', $id)->update([
            'PROCESSO_ID'    => $processoId,
            'PEDIDO_STATUS'  => 'VINCULADO',
            'updated_at'     => now(),
        ]);
        return response()->json(['ok' => true]);
    } catch (\Throwable $e) {
        return response()->json(['erro' => $e->getMessage()], 500);
    }
});
