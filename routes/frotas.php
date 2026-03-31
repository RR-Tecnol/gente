<?php

use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Route;

// Frota completa com status e KM
Route::get('/frotas/veiculos', function () {
    try {
        $q = DB::table('VEICULO');
        if (request('status')) $q->where('VEICULO_STATUS', request('status'));
        if (request('tipo'))   $q->where('VEICULO_TIPO', request('tipo'));
        $veiculos = $q->orderBy('VEICULO_PLACA')->get();
        return response()->json(['veiculos' => $veiculos, 'total' => $veiculos->count()]);
    } catch (\Throwable $e) {
        return response()->json(['erro' => $e->getMessage()], 500);
    }
});

// Disponíveis para saída
Route::get('/frotas/veiculos/disponiveis', function () {
    try {
        $veiculos = DB::table('VEICULO')
            ->where('VEICULO_STATUS', 'DISPONIVEL')
            ->orderBy('VEICULO_PLACA')
            ->get();
        return response()->json(['veiculos' => $veiculos]);
    } catch (\Throwable $e) {
        return response()->json(['erro' => $e->getMessage()], 500);
    }
});

// Histórico por veículo
Route::get('/frotas/veiculos/{id}/historico', function (int $id) {
    try {
        $saidas = DB::table('SAIDA_VEICULO as s')
            ->join('FUNCIONARIO as f', 'f.FUNCIONARIO_ID', '=', 's.MOTORISTA_ID')
            ->join('PESSOA as p', 'p.PESSOA_ID', '=', 'f.PESSOA_ID')
            ->where('s.VEICULO_ID', $id)
            ->select('s.*', 'p.PESSOA_NOME as motorista')
            ->orderByDesc('s.SAIDA_DATA_HORA')
            ->limit(50)
            ->get();

        $manutencoes = DB::table('MANUTENCAO_VEICULO')
            ->where('VEICULO_ID', $id)
            ->orderByDesc('MANUT_DATA')
            ->get();

        return response()->json(['saidas' => $saidas, 'manutencoes' => $manutencoes]);
    } catch (\Throwable $e) {
        return response()->json(['erro' => $e->getMessage()], 500);
    }
});

// Manutenções próximas (alerta 30 dias)
Route::get('/frotas/manutencao/proximas', function () {
    try {
        $limite = now()->addDays(30)->toDateString();
        $veiculos = DB::table('VEICULO')
            ->whereNotNull('VEICULO_PROX_MANUTENCAO')
            ->where('VEICULO_PROX_MANUTENCAO', '<=', $limite)
            ->where('VEICULO_STATUS', '!=', 'INATIVO')
            ->orderBy('VEICULO_PROX_MANUTENCAO')
            ->get()
            ->map(function ($v) {
                $v->dias_restantes = (int) now()->diffInDays($v->VEICULO_PROX_MANUTENCAO, false);
                $v->urgencia = $v->dias_restantes <= 7 ? 'CRITICO' : 'ATENCAO';
                return $v;
            });
        return response()->json(['veiculos' => $veiculos, 'total' => $veiculos->count()]);
    } catch (\Throwable $e) {
        return response()->json(['erro' => $e->getMessage()], 500);
    }
});

// Cadastrar veículo
Route::post('/frotas/veiculos', function () {
    try {
        $data = request()->validate([
            'veiculo_placa'   => 'required|string|max:10',
            'veiculo_modelo'  => 'required|string|max:100',
            'veiculo_marca'   => 'required|string|max:50',
            'veiculo_ano'     => 'required|integer|min:1900',
            'veiculo_tipo'    => 'required|string',
            'veiculo_km_atual'=> 'nullable|integer|min:0',
            'uo_id'           => 'nullable|integer',
            'veiculo_cor'     => 'nullable|string|max:30',
            'veiculo_renavam' => 'nullable|string|max:20',
        ]);
        $id = DB::table('VEICULO')->insertGetId([
            'VEICULO_PLACA'    => strtoupper($data['veiculo_placa']),
            'VEICULO_MODELO'   => $data['veiculo_modelo'],
            'VEICULO_MARCA'    => $data['veiculo_marca'],
            'VEICULO_ANO'      => $data['veiculo_ano'],
            'VEICULO_TIPO'     => strtoupper($data['veiculo_tipo']),
            'VEICULO_STATUS'   => 'DISPONIVEL',
            'VEICULO_KM_ATUAL' => $data['veiculo_km_atual'] ?? 0,
            'UO_ID'            => $data['uo_id'] ?? null,
            'VEICULO_COR'      => $data['veiculo_cor'] ?? null,
            'VEICULO_RENAVAM'  => $data['veiculo_renavam'] ?? null,
            'created_at'       => now(), 'updated_at' => now(),
        ]);
        return response()->json(['ok' => true, 'id' => $id], 201);
    } catch (\Throwable $e) {
        return response()->json(['erro' => $e->getMessage()], 422);
    }
});

// Saídas abertas (sem retorno)
Route::get('/frotas/saidas/abertas', function () {
    try {
        $saidas = DB::table('SAIDA_VEICULO as s')
            ->join('VEICULO as v', 'v.VEICULO_ID', '=', 's.VEICULO_ID')
            ->join('FUNCIONARIO as f', 'f.FUNCIONARIO_ID', '=', 's.MOTORISTA_ID')
            ->join('PESSOA as p', 'p.PESSOA_ID', '=', 'f.PESSOA_ID')
            ->whereNull('s.RETORNO_DATA_HORA')
            ->select('s.*', 'v.VEICULO_PLACA', 'v.VEICULO_MODELO', 'p.PESSOA_NOME as motorista')
            ->orderBy('s.SAIDA_DATA_HORA')
            ->get();
        return response()->json(['saidas' => $saidas]);
    } catch (\Throwable $e) {
        return response()->json(['erro' => $e->getMessage()], 500);
    }
});

// Registrar saída — muda status para EM_USO
Route::post('/frotas/saidas', function () {
    try {
        $data = request()->validate([
            'veiculo_id'       => 'required|integer',
            'motorista_id'     => 'required|integer',
            'saida_destino'    => 'required|string|max:200',
            'saida_finalidade' => 'required|string|max:200',
            'saida_data_hora'  => 'required|date',
            'km_saida'         => 'required|integer|min:0',
            'uo_solicitante_id'=> 'nullable|integer',
        ]);

        $veiculo = DB::table('VEICULO')->where('VEICULO_ID', $data['veiculo_id'])->first();
        if (!$veiculo) return response()->json(['erro' => 'Veículo não encontrado.'], 404);
        if ($veiculo->VEICULO_STATUS !== 'DISPONIVEL') {
            return response()->json(['erro' => "Veículo não disponível — status: {$veiculo->VEICULO_STATUS}."], 422);
        }

        DB::transaction(function () use ($data) {
            DB::table('SAIDA_VEICULO')->insert([
                'VEICULO_ID'         => $data['veiculo_id'],
                'MOTORISTA_ID'       => $data['motorista_id'],
                'UO_SOLICITANTE_ID'  => $data['uo_solicitante_id'] ?? null,
                'SAIDA_DESTINO'      => $data['saida_destino'],
                'SAIDA_FINALIDADE'   => $data['saida_finalidade'],
                'SAIDA_DATA_HORA'    => $data['saida_data_hora'],
                'KM_SAIDA'           => $data['km_saida'],
                'REGISTRADO_POR'     => Auth::id(),
                'created_at'         => now(), 'updated_at' => now(),
            ]);
            DB::table('VEICULO')->where('VEICULO_ID', $data['veiculo_id'])->update([
                'VEICULO_STATUS' => 'EM_USO', 'updated_at' => now(),
            ]);
        });

        return response()->json(['ok' => true]);
    } catch (\Throwable $e) {
        return response()->json(['erro' => $e->getMessage()], 422);
    }
});

// Registrar retorno — calcula KM percorrido e libera veículo
Route::patch('/frotas/saidas/{id}/retorno', function (int $id) {
    try {
        $saida = DB::table('SAIDA_VEICULO')->where('SAIDA_ID', $id)->first();
        if (!$saida) return response()->json(['erro' => 'Saída não encontrada.'], 404);
        if ($saida->RETORNO_DATA_HORA) return response()->json(['erro' => 'Retorno já registrado.'], 422);

        $kmRetorno = (int) request('km_retorno');
        if ($kmRetorno < $saida->KM_SAIDA) {
            return response()->json(['erro' => 'KM de retorno não pode ser menor que KM de saída.'], 422);
        }

        DB::transaction(function () use ($saida, $id, $kmRetorno) {
            $kmPercorrido = $kmRetorno - $saida->KM_SAIDA;
            DB::table('SAIDA_VEICULO')->where('SAIDA_ID', $id)->update([
                'RETORNO_DATA_HORA' => request('retorno_data_hora', now()),
                'KM_RETORNO'        => $kmRetorno,
                'KM_PERCORRIDO'     => $kmPercorrido,
                'updated_at'        => now(),
            ]);
            DB::table('VEICULO')->where('VEICULO_ID', $saida->VEICULO_ID)->update([
                'VEICULO_KM_ATUAL' => $kmRetorno,
                'VEICULO_STATUS'   => 'DISPONIVEL',
                'updated_at'       => now(),
            ]);
        });

        return response()->json(['ok' => true, 'km_percorrido' => $kmRetorno - $saida->KM_SAIDA]);
    } catch (\Throwable $e) {
        return response()->json(['erro' => $e->getMessage()], 422);
    }
});

// Registrar manutenção
Route::post('/frotas/manutencao', function () {
    try {
        $data = request()->validate([
            'veiculo_id'       => 'required|integer',
            'manut_tipo'       => 'required|in:PREVENTIVA,CORRETIVA',
            'manut_descricao'  => 'required|string|max:300',
            'manut_data'       => 'required|date',
            'manut_valor'      => 'nullable|numeric|min:0',
            'manut_proxima'    => 'nullable|date',
            'manut_fornecedor' => 'nullable|string|max:150',
        ]);

        DB::transaction(function () use ($data) {
            DB::table('MANUTENCAO_VEICULO')->insert([
                'VEICULO_ID'       => $data['veiculo_id'],
                'MANUT_TIPO'       => $data['manut_tipo'],
                'MANUT_DESCRICAO'  => $data['manut_descricao'],
                'MANUT_DATA'       => $data['manut_data'],
                'MANUT_VALOR'      => $data['manut_valor'] ?? null,
                'MANUT_PROXIMA'    => $data['manut_proxima'] ?? null,
                'MANUT_FORNECEDOR' => $data['manut_fornecedor'] ?? null,
                'REGISTRADO_POR'   => Auth::id(),
                'created_at'       => now(), 'updated_at' => now(),
            ]);
            // Atualizar próxima manutenção no veículo
            if (!empty($data['manut_proxima'])) {
                DB::table('VEICULO')->where('VEICULO_ID', $data['veiculo_id'])->update([
                    'VEICULO_PROX_MANUTENCAO' => $data['manut_proxima'],
                    'updated_at'              => now(),
                ]);
            }
        });

        return response()->json(['ok' => true]);
    } catch (\Throwable $e) {
        return response()->json(['erro' => $e->getMessage()], 422);
    }
});
