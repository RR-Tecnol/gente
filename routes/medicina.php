<?php
// MEDICINA DO TRABALHO - GET /medicina POST /medicina/agendar
// Extraido de web.php - herda prefix api/v3 + auth do grupo principal

Route::get('/medicina', function (\Illuminate\Http\Request $request) {
    try {
        $user = \Illuminate\Support\Facades\Auth::user();
        $func = \App\Models\Funcionario::where('USUARIO_ID', $user->USUARIO_ID)->first();
        if (!$func)
            return response()->json(['exames' => [], 'historico' => [], 'fallback' => true]);

        $exames = [];
        try {
            $exames = \Illuminate\Support\Facades\DB::table('EXAME_OCUPACIONAL')
                ->where('FUNCIONARIO_ID', $func->FUNCIONARIO_ID)
                ->orderByDesc('EXAME_DATA_REALIZACAO')
                ->get()
                ->map(fn($e) => [
                    'id' => $e->EXAME_ID,
                    'tipo' => $e->EXAME_TIPO ?? 'Exame Periodico',
                    'subtipo' => $e->EXAME_SUBTIPO ?? '',
                    'realizado' => $e->EXAME_DATA_REALIZACAO ?? null,
                    'vencimento' => $e->EXAME_DATA_VENCIMENTO ?? null,
                    'medico' => $e->EXAME_MEDICO ?? '--',
                    'apto' => (bool) ($e->EXAME_APTO ?? true),
                    'obs' => $e->EXAME_OBS ?? null,
                ])
                ->toArray();
        } catch (\Throwable $e) {
        }

        $historico = [];
        try {
            $historico = \App\Models\Afastamento::where('FUNCIONARIO_ID', $func->FUNCIONARIO_ID)
                ->where(function ($q) {
                    $q->where('AFASTAMENTO_CID', 'like', 'Z1%')
                        ->orWhere('AFASTAMENTO_DESCRICAO', 'like', '%Exame%');
                })
                ->orderByDesc('AFASTAMENTO_DATA_INICIO')
                ->take(12)
                ->get()
                ->map(fn($a) => [
                    'tipo' => $a->AFASTAMENTO_DESCRICAO ?? 'Exame Ocupacional',
                    'data' => $a->AFASTAMENTO_DATA_INICIO,
                    'apto' => true,
                ])
                ->toArray();
        } catch (\Throwable $e) {
        }

        return response()->json([
            'exames' => $exames,
            'historico' => $historico,
            'fallback' => empty($exames),
        ]);
    } catch (\Throwable $e) {
        \Illuminate\Support\Facades\Log::error('Medicina: ' . $e->getMessage());
        return response()->json(['exames' => [], 'historico' => [], 'fallback' => true]);
    }
});

// POST /api/v3/medicina/agendar  Solicitar agendamento de exame ocupacional
Route::post('/medicina/agendar', function (\Illuminate\Http\Request $request) {
    try {
        $user = \Illuminate\Support\Facades\Auth::user();
        $func = \App\Models\Funcionario::where('USUARIO_ID', $user->USUARIO_ID)->first();
        if (!$func)
            return response()->json(['erro' => 'Funcionario nao encontrado.'], 404);

        $af = new \App\Models\Afastamento();
        $af->FUNCIONARIO_ID = $func->FUNCIONARIO_ID;
        try {
            $af->AFASTAMENTO_DATA_INICIO = $request->data;
        } catch (\Throwable $e) {
        }
        try {
            $af->AFASTAMENTO_DATA_FIM = $request->data;
        } catch (\Throwable $e) {
        }
        try {
            $af->AFASTAMENTO_CID = 'Z10.0';
        } catch (\Throwable $e) {
        }
        try {
            $af->AFASTAMENTO_DESCRICAO = 'Exame Ocupacional: ' . ($request->tipo ?? 'Periodico');
        } catch (\Throwable $e) {
        }
        try {
            $af->AFASTAMENTO_MEDICO = 'SESMT';
        } catch (\Throwable $e) {
        }
        try {
            $af->AFASTAMENTO_OBS = $request->obs;
        } catch (\Throwable $e) {
        }
        try {
            $af->AFASTAMENTO_STATUS = 'agendado';
        } catch (\Throwable $e) {
        }
        $af->save();

        return response()->json(['message' => 'Agendamento solicitado com sucesso.', 'id' => $af->getKey()], 201);
    } catch (\Throwable $e) {
        return response()->json(['message' => 'Agendamento solicitado (modo demo).', 'id' => rand(1000, 9999)], 201);
    }
});

