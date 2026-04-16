<?php

use Illuminate\Support\Facades\Route;

// ──────────────────────────────────────────────────────────────────
// AVALIAÇÃO DE DESEMPENHO  /api/v3/avaliacoes
// Herdado do grupo Route::prefix('api/v3')->middleware([...]) do web.php
// ──────────────────────────────────────────────────────────────────

// GET — histórico de avaliações (do servidor logado OU de um funcionário específico quando gestor avalia)
Route::get('/avaliacoes', function (\Illuminate\Http\Request $request) {
    try {
        $usuario = session('usuario');
        $funcId = $request->query('funcionario_id')
            ?? $usuario['FUNCIONARIO_ID']
            ?? $usuario['id']
            ?? null;

        if (!$funcId) {
            return response()->json(['fallback' => true, 'avaliacoes' => []]);
        }

        $avaliacoes = \Illuminate\Support\Facades\DB::table('AVALIACAO_DESEMPENHO as AD')
            ->leftJoin('USUARIO as U', 'U.USUARIO_ID', '=', 'AD.AVALIADOR_ID')
            ->where('AD.FUNCIONARIO_ID', $funcId)
            ->orderByDesc('AD.created_at')
            ->select(
                'AD.AVALIACAO_ID',
                'AD.AVALIACAO_CICLO as ciclo',
                'AD.AVALIACAO_NOTA_FINAL as nota',
                'AD.AVALIACAO_STATUS as status',
                'U.USUARIO_NOME as avaliador',
                'AD.created_at'
            )
            ->get();

        $result = $avaliacoes->map(function ($av) {
            $criterios = \Illuminate\Support\Facades\DB::table('AVALIACAO_CRITERIO')
                ->where('AVALIACAO_ID', $av->AVALIACAO_ID)
                ->select('CRITERIO_NOME as nome', 'CRITERIO_PESO as peso', 'CRITERIO_NOTA as nota', 'CRITERIO_OBS as obs')
                ->get();
            return [
                'ciclo'     => $av->ciclo,
                'nota'      => (float) $av->nota,
                'status'    => $av->status,
                'avaliador' => $av->avaliador ?? 'Gestor',
                'criterios' => $criterios,
            ];
        });

        return response()->json(['fallback' => false, 'avaliacoes' => $result]);
    } catch (\Throwable $e) {
        return response()->json(['fallback' => true, 'avaliacoes' => [], 'debug' => $e->getMessage()]);
    }
});

// POST — salvar nova avaliação (gestor avalia servidor)
Route::post('/avaliacoes', function (\Illuminate\Http\Request $request) {
    try {
        $usuario     = session('usuario');
        $avaliadorId = $usuario['USUARIO_ID'] ?? $usuario['id'] ?? null;

        $funcId = $request->input('funcionario_id')
            ?? $usuario['FUNCIONARIO_ID']
            ?? $usuario['id']
            ?? null;

        $ciclo     = $request->input('ciclo', date('Y') . '.1');
        $criterios = $request->input('criterios', []);

        if (!$funcId || empty($criterios)) {
            return response()->json(['erro' => 'funcionario_id e criterios são obrigatórios.'], 422);
        }

        $notaFinal  = 0;
        $pesosTotal = 0;
        foreach ($criterios as $c) {
            $peso        = (int) ($c['peso'] ?? 20);
            $nota        = (int) ($c['nota'] ?? 0);
            $notaFinal  += $nota * $peso;
            $pesosTotal += $peso;
        }
        $notaFinal = $pesosTotal > 0 ? round($notaFinal / $pesosTotal, 1) : 0;

        $avaliacaoId = \Illuminate\Support\Facades\DB::table('AVALIACAO_DESEMPENHO')->insertGetId([
            'FUNCIONARIO_ID'        => $funcId,
            'AVALIACAO_CICLO'       => $ciclo,
            'AVALIACAO_NOTA_FINAL'  => $notaFinal,
            'AVALIACAO_STATUS'      => 'enviada',
            'AVALIADOR_ID'          => $avaliadorId,
            'created_at'            => now(),
            'updated_at'            => now(),
        ]);

        foreach ($criterios as $c) {
            \Illuminate\Support\Facades\DB::table('AVALIACAO_CRITERIO')->insert([
                'AVALIACAO_ID'   => $avaliacaoId,
                'CRITERIO_NOME'  => $c['nome'] ?? '—',
                'CRITERIO_PESO'  => (int) ($c['peso'] ?? 20),
                'CRITERIO_NOTA'  => (int) ($c['nota'] ?? 0),
                'CRITERIO_OBS'   => $c['obs'] ?? null,
                'created_at'     => now(),
                'updated_at'     => now(),
            ]);
        }

        return response()->json([
            'ok'          => true,
            'avaliacao_id' => $avaliacaoId,
            'nota_final'  => $notaFinal,
            'ciclo'       => $ciclo,
        ], 201);
    } catch (\Throwable $e) {
        return response()->json(['erro' => $e->getMessage()], 500);
    }
});
