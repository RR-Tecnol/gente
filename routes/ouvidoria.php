<?php
// OUVIDORIA - GET/POST /ouvidoria
// Extraido de web.php - herda prefix api/v3 + auth do grupo principal

Route::get('/ouvidoria', function (\Illuminate\Http\Request $request) {
    try {
        $user = \Illuminate\Support\Facades\Auth::user();
        $func = \App\Models\Funcionario::where('USUARIO_ID', $user->USUARIO_ID)->first();
        if (!$func)
            return response()->json(['manifestacoes' => [], 'fallback' => true]);

        $manifestacoes = [];
        try {
            $manifestacoes = \Illuminate\Support\Facades\DB::table('OUVIDORIA')
                ->where('FUNCIONARIO_ID', $func->FUNCIONARIO_ID)
                ->orderByDesc('OUVIDORIA_DATA')
                ->take(30)
                ->get()
                ->map(fn($m) => [
                    'id' => $m->OUVIDORIA_ID,
                    'tipo' => $m->OUVIDORIA_TIPO ?? 'outros',
                    'area' => $m->OUVIDORIA_AREA ?? '',
                    'urgencia' => $m->OUVIDORIA_URGENCIA ?? 'normal',
                    'descricao' => $m->OUVIDORIA_DESC ?? '',
                    'status' => $m->OUVIDORIA_STATUS ?? 'recebida',
                    'protocolo' => $m->OUVIDORIA_PROTOCOLO ?? '--',
                    'data' => $m->OUVIDORIA_DATA,
                    'anonimo' => (bool) ($m->OUVIDORIA_ANONIMO ?? false),
                    'resposta' => $m->OUVIDORIA_RESPOSTA ?? null,
                ])
                ->toArray();
        } catch (\Throwable $e) {
        }

        return response()->json([
            'manifestacoes' => $manifestacoes,
            'fallback' => empty($manifestacoes),
        ]);
    } catch (\Throwable $e) {
        return response()->json(['manifestacoes' => [], 'fallback' => true]);
    }
});

// POST /api/v3/ouvidoria  Registrar manifestacao
Route::post('/ouvidoria', function (\Illuminate\Http\Request $request) {
    try {
        $user = \Illuminate\Support\Facades\Auth::user();
        $func = \App\Models\Funcionario::where('USUARIO_ID', $user->USUARIO_ID)->first();
        $proto = 'OUV-' . date('Y') . '-' . str_pad(rand(1, 999), 3, '0', STR_PAD_LEFT);

        if ($func) {
            try {
                \Illuminate\Support\Facades\DB::table('OUVIDORIA')->insert([
                    'FUNCIONARIO_ID' => $func->FUNCIONARIO_ID,
                    'OUVIDORIA_TIPO' => $request->tipo,
                    'OUVIDORIA_AREA' => $request->area,
                    'OUVIDORIA_URGENCIA' => $request->urgencia ?? 'normal',
                    'OUVIDORIA_DESC' => $request->descricao,
                    'OUVIDORIA_STATUS' => 'recebida',
                    'OUVIDORIA_PROTOCOLO' => $proto,
                    'OUVIDORIA_DATA' => date('Y-m-d'),
                    'OUVIDORIA_ANONIMO' => $request->anonimo ? 1 : 0,
                ]);
            } catch (\Throwable $e) {
            }
        }

        return response()->json(['protocolo' => $proto, 'status' => 'recebida'], 201);
    } catch (\Throwable $e) {
        return response()->json(['protocolo' => 'OUV-' . date('Y') . '-' . rand(100, 999), 'status' => 'demo'], 201);
    }
});
