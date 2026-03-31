<?php

use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Auth;

// ── Helper: calcula vagas ocupadas por cargo ──────────────────────────────
$vagasOcupadas = function (int $cargoId, ?int $unidadeId = null): int {
    $q = DB::table('FUNCIONARIO as f')
        ->join('LOTACAO as l', function ($j) {
            $j->on('l.FUNCIONARIO_ID', '=', 'f.FUNCIONARIO_ID')
              ->whereNull('l.LOTACAO_DATA_FIM');
        })
        ->where('l.CARGO_ID', $cargoId)
        ->whereNull('f.FUNCIONARIO_DATA_FIM');
    if ($unidadeId) $q->where('l.UNIDADE_ID', $unidadeId);
    return (int) $q->count();
};

// Listar quadro de vagas com ocupação em tempo real
Route::get('/quadro-vagas', function () use ($vagasOcupadas) {
    try {
        $quadros = DB::table('QUADRO_VAGAS as qv')
            ->join('CARGO as c', 'c.CARGO_ID', '=', 'qv.CARGO_ID')
            ->leftJoin('SETOR as s', 's.SETOR_ID', '=', 'qv.UNIDADE_ID')
            ->where('qv.QUADRO_ATIVO', true)
            ->select('qv.*', 'c.CARGO_NOME', 'c.CARGO_CODIGO', 's.SETOR_NOME as unidade_nome')
            ->orderBy('c.CARGO_NOME')
            ->get()
            ->map(function ($q) use ($vagasOcupadas) {
                $ocupadas = $vagasOcupadas($q->CARGO_ID, $q->UNIDADE_ID);
                $q->VAGAS_OCUPADAS    = $ocupadas;
                $q->VAGAS_DISPONIVEIS = max(0, $q->VAGAS_AUTORIZADAS - $ocupadas);
                $q->SITUACAO = $q->VAGAS_DISPONIVEIS > 0 ? 'COM_VAGA' : 'SEM_VAGA';
                return $q;
            });

        return response()->json([
            'quadros'            => $quadros,
            'total_autorizadas'  => $quadros->sum('VAGAS_AUTORIZADAS'),
            'total_ocupadas'     => $quadros->sum('VAGAS_OCUPADAS'),
            'total_disponiveis'  => $quadros->sum('VAGAS_DISPONIVEIS'),
        ]);
    } catch (\Throwable $e) {
        return response()->json(['erro' => $e->getMessage()], 500);
    }
});

// Verificar disponibilidade de vaga para um cargo (usado na nomeação PSS)
Route::get('/quadro-vagas/verificar/{cargo_id}', function (int $cargoId) use ($vagasOcupadas) {
    try {
        $quadro = DB::table('QUADRO_VAGAS')
            ->where('CARGO_ID', $cargoId)
            ->where('QUADRO_ATIVO', true)
            ->first();

        if (!$quadro) {
            return response()->json([
                'tem_vaga'   => null,
                'aviso'      => 'Cargo sem quadro de vagas cadastrado — verifique com o RH.',
                'bloqueado'  => false,
            ]);
        }

        $ocupadas    = $vagasOcupadas($cargoId, null);
        $disponiveis = max(0, $quadro->VAGAS_AUTORIZADAS - $ocupadas);

        return response()->json([
            'cargo_id'        => $cargoId,
            'vagas_autorizadas' => $quadro->VAGAS_AUTORIZADAS,
            'vagas_ocupadas'  => $ocupadas,
            'vagas_disponiveis' => $disponiveis,
            'tem_vaga'        => $disponiveis > 0,
            'bloqueado'       => $disponiveis === 0,
            'lei_criacao'     => $quadro->LEI_CRIACAO,
        ]);
    } catch (\Throwable $e) {
        return response()->json(['erro' => $e->getMessage()], 500);
    }
});

// Criar/atualizar entrada no quadro de vagas
Route::post('/quadro-vagas', function () {
    try {
        $data = request()->validate([
            'cargo_id'         => 'required|integer',
            'vagas_autorizadas'=> 'required|integer|min:0',
            'lei_criacao'      => 'nullable|string|max:100',
            'data_vigencia'    => 'nullable|date',
            'unidade_id'       => 'nullable|integer',
        ]);

        DB::table('QUADRO_VAGAS')->updateOrInsert(
            ['CARGO_ID' => $data['cargo_id'], 'UNIDADE_ID' => $data['unidade_id'] ?? null],
            [
                'VAGAS_AUTORIZADAS' => $data['vagas_autorizadas'],
                'LEI_CRIACAO'       => $data['lei_criacao'] ?? null,
                'DATA_VIGENCIA'     => $data['data_vigencia'] ?? null,
                'QUADRO_ATIVO'      => true,
                'CRIADO_POR'        => Auth::id(),
                'updated_at'        => now(),
                'created_at'        => now(),
            ]
        );

        return response()->json(['ok' => true]);
    } catch (\Throwable $e) {
        return response()->json(['erro' => $e->getMessage()], 422);
    }
});
