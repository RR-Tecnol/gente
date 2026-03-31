<?php

use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Route;
use App\Services\DecimoTerceiroService;

// Preview de um funcionário específico
Route::get('/13salario/preview/{funcionario_id}', function (int $funcId) {
    try {
        $ano  = (int) request('ano', date('Y'));
        $tipo = request('tipo', 'SEGUNDA_PARCELA');
        $service = new DecimoTerceiroService();
        return response()->json($service->calcularLote($ano, $tipo, date('Ym')));
    } catch (\Throwable $e) {
        return response()->json(['erro' => $e->getMessage()], 422);
    }
});

// Gerar 13º em lote para todos os funcionários ativos
Route::post('/13salario/gerar', function () {
    try {
        $data = request()->validate([
            'ano'         => 'required|integer|min:2020',
            'tipo'        => 'required|in:PRIMEIRA_PARCELA,SEGUNDA_PARCELA,RESCISORIO',
            'competencia' => 'required|string|size:6',
        ]);
        $service   = new DecimoTerceiroService();
        $resultado = $service->calcularLote($data['ano'], $data['tipo'], $data['competencia']);
        return response()->json(['ok' => true] + $resultado);
    } catch (\Throwable $e) {
        return response()->json(['erro' => $e->getMessage()], 500);
    }
});

// Listar 13º calculado por ano e tipo
Route::get('/13salario', function () {
    try {
        $ano  = (int) request('ano', date('Y'));
        $tipo = request('tipo');

        $q = DB::table('DECIMO_TERCEIRO as dt')
            ->join('FUNCIONARIO as f', 'f.FUNCIONARIO_ID', '=', 'dt.FUNCIONARIO_ID')
            ->join('PESSOA as p', 'p.PESSOA_ID', '=', 'f.PESSOA_ID')
            ->where('dt.DT_ANO', $ano)
            ->select('dt.*', 'p.PESSOA_NOME as nome', 'f.FUNCIONARIO_MATRICULA as matricula');

        if ($tipo) $q->where('dt.DT_TIPO', $tipo);

        $registros = $q->orderBy('p.PESSOA_NOME')->get();

        return response()->json([
            'ano'           => $ano,
            'tipo'          => $tipo,
            'registros'     => $registros,
            'total'         => $registros->count(),
            'total_liquido' => $registros->sum('DT_VALOR_LIQUIDO'),
        ]);
    } catch (\Throwable $e) {
        return response()->json(['erro' => $e->getMessage()], 500);
    }
});
