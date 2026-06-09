<?php

use App\Models\PessoaDependente;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Validator;

// Herda o grupo api/v3 + web + auth de web.php

// ── GET — listar dependentes do funcionário ───────────────────────
Route::get('/funcionarios/{id}/dependentes', function ($id) {
    try {
        $deps = PessoaDependente::where('FUNCIONARIO_ID', $id)
            ->orderBy('PESSOA_DEPENDENTE_ID')
            ->get()
            ->map(fn($d) => [
                'id'          => $d->PESSOA_DEPENDENTE_ID,
                'nome'        => $d->PESSOA_DEPENDENTE_NOME,
                'cpf'         => $d->PESSOA_DEPENDENTE_CPF,
                'data_nasc'   => $d->PESSOA_DEPENDENTE_NASCIMENTO?->format('Y-m-d'),
                'parentesco'  => $d->PESSOA_DEPENDENTE_PARENTESCO,
                'deducao_irrf'=> $d->PESSOA_DEPENDENTE_DEDUCAO_IRRF,
                'dt_inicio'   => $d->PESSOA_DEPENDENTE_DT_INICIO?->format('Y-m-d'),
                'dt_fim'      => $d->PESSOA_DEPENDENTE_DT_FIM?->format('Y-m-d'),
                'motivo_fim'  => $d->PESSOA_DEPENDENTE_MOTIVO_FIM,
            ]);
        return response()->json(['dependentes' => $deps]);
    } catch (\Exception $e) {
        \Illuminate\Support\Facades\Log::error('Erro ao listar dependentes', [
            'funcionario_id' => $id,
            'erro'           => $e->getMessage(),
        ]);
        return response()->json(['dependentes' => []], 200);
    }
});

// ── POST — criar dependente ───────────────────────────────────────
Route::post('/funcionarios/{id}/dependentes', function ($id, Request $request) {
    $validator = Validator::make($request->all(), [
        'PESSOA_DEPENDENTE_NOME'         => 'required|string|max:200',
        'PESSOA_DEPENDENTE_PARENTESCO'   => 'required|string|max:10',
        'PESSOA_DEPENDENTE_CPF'          => ['nullable', 'cpf'],
        'PESSOA_DEPENDENTE_NASCIMENTO'   => 'nullable|date',
        'PESSOA_DEPENDENTE_DEDUCAO_IRRF' => 'required|integer|in:0,1,2',
        'PESSOA_DEPENDENTE_SEXO'         => 'nullable|integer|in:1,2',
        'PESSOA_DEPENDENTE_DT_INICIO'    => 'nullable|date',
    ]);
    if ($validator->fails()) {
        return response()->json(['erro' => $validator->errors()->first()], 422);
    }
    try {
        $dep = PessoaDependente::create(
            array_merge($validator->validated(), ['FUNCIONARIO_ID' => (int) $id])
        );
        return response()->json([
            'ok'         => true,
            'dependente' => [
                'id'           => $dep->PESSOA_DEPENDENTE_ID,
                'nome'         => $dep->PESSOA_DEPENDENTE_NOME,
                'cpf'          => $dep->PESSOA_DEPENDENTE_CPF,
                'data_nasc'    => $dep->PESSOA_DEPENDENTE_NASCIMENTO?->format('Y-m-d'),
                'parentesco'   => $dep->PESSOA_DEPENDENTE_PARENTESCO,
                'deducao_irrf' => $dep->PESSOA_DEPENDENTE_DEDUCAO_IRRF,
                'dt_inicio'    => $dep->PESSOA_DEPENDENTE_DT_INICIO?->format('Y-m-d'),
                'dt_fim'       => null,
                'motivo_fim'   => null,
            ],
        ], 201);
    } catch (\Exception $e) {
        \Illuminate\Support\Facades\Log::error('Erro ao criar dependente', [
            'funcionario_id' => $id,
            'erro'           => $e->getMessage(),
        ]);
        return response()->json(['erro' => 'Erro ao salvar dependente.'], 500);
    }
});

// ── PUT — editar dependente ───────────────────────────────────────
Route::put('/funcionarios/{id}/dependentes/{depId}', function ($id, $depId, Request $request) {
    $dep = PessoaDependente::where('FUNCIONARIO_ID', $id)
        ->where('PESSOA_DEPENDENTE_ID', $depId)
        ->first();
    if (!$dep) {
        return response()->json(['erro' => 'Dependente não encontrado.'], 404);
    }
    $validator = Validator::make($request->all(), [
        'PESSOA_DEPENDENTE_NOME'         => 'required|string|max:200',
        'PESSOA_DEPENDENTE_PARENTESCO'   => 'required|string|max:10',
        'PESSOA_DEPENDENTE_CPF'          => ['nullable', 'cpf'],
        'PESSOA_DEPENDENTE_NASCIMENTO'   => 'nullable|date',
        'PESSOA_DEPENDENTE_DEDUCAO_IRRF' => 'required|integer|in:0,1,2',
        'PESSOA_DEPENDENTE_SEXO'         => 'nullable|integer|in:1,2',
        'PESSOA_DEPENDENTE_DT_INICIO'    => 'nullable|date',
        'PESSOA_DEPENDENTE_DT_FIM'       => 'nullable|date|after:PESSOA_DEPENDENTE_DT_INICIO',
        'PESSOA_DEPENDENTE_MOTIVO_FIM'   => 'nullable|string|max:50',
    ]);
    if ($validator->fails()) {
        return response()->json(['erro' => $validator->errors()->first()], 422);
    }
    try {
        $dep->update($validator->validated());
        return response()->json([
            'ok'         => true,
            'dependente' => [
                'id'           => $dep->PESSOA_DEPENDENTE_ID,
                'nome'         => $dep->PESSOA_DEPENDENTE_NOME,
                'cpf'          => $dep->PESSOA_DEPENDENTE_CPF,
                'data_nasc'    => $dep->PESSOA_DEPENDENTE_NASCIMENTO?->format('Y-m-d'),
                'parentesco'   => $dep->PESSOA_DEPENDENTE_PARENTESCO,
                'deducao_irrf' => $dep->PESSOA_DEPENDENTE_DEDUCAO_IRRF,
                'dt_inicio'    => $dep->PESSOA_DEPENDENTE_DT_INICIO?->format('Y-m-d'),
                'dt_fim'       => $dep->PESSOA_DEPENDENTE_DT_FIM?->format('Y-m-d'),
                'motivo_fim'   => $dep->PESSOA_DEPENDENTE_MOTIVO_FIM,
            ],
        ]);
    } catch (\Exception $e) {
        \Illuminate\Support\Facades\Log::error('Erro ao editar dependente', [
            'funcionario_id' => $id,
            'dependente_id'  => $depId,
            'erro'           => $e->getMessage(),
        ]);
        return response()->json(['erro' => 'Erro ao atualizar dependente.'], 500);
    }
});

// ── DELETE — remover dependente ───────────────────────────────────
Route::delete('/funcionarios/{id}/dependentes/{depId}', function ($id, $depId) {
    try {
        $deleted = PessoaDependente::where('FUNCIONARIO_ID', $id)
            ->where('PESSOA_DEPENDENTE_ID', $depId)
            ->delete();
        if (!$deleted) {
            return response()->json(['erro' => 'Dependente não encontrado.'], 404);
        }
        return response()->json(['ok' => true]);
    } catch (\Exception $e) {
        \Illuminate\Support\Facades\Log::error('Erro ao excluir dependente', [
            'funcionario_id' => $id,
            'dependente_id'  => $depId,
            'erro'           => $e->getMessage(),
        ]);
        return response()->json(['erro' => 'Erro ao excluir dependente.'], 500);
    }
});
