<?php

use Illuminate\Support\Facades\Route;

// ──────────────────────────────────────────────────────────────────
// AVALIAÇÃO DE DESEMPENHO  /api/v3/avaliacoes
// Herdado do grupo Route::prefix('api/v3')->middleware([...]) do web.php
// ──────────────────────────────────────────────────────────────────

if (!function_exists('resolveFuncionarioIdParaAvaliacao')) {
    function resolveFuncionarioIdParaAvaliacao(\Illuminate\Http\Request $request): ?int
    {
        $funcId = $request->query('funcionario_id') ?? $request->input('funcionario_id');
        if ($funcId) {
            return (int) $funcId;
        }

        $user = \Illuminate\Support\Facades\Auth::user();
        if (!$user) {
            return null;
        }

        $funcId = \Illuminate\Support\Facades\DB::table('FUNCIONARIO')
            ->where('USUARIO_ID', $user->USUARIO_ID)
            ->value('FUNCIONARIO_ID');

        if (!$funcId && !app()->isProduction() && strtolower((string) ($user->USUARIO_LOGIN ?? '')) === 'admin') {
            $livre = \Illuminate\Support\Facades\DB::table('FUNCIONARIO')->whereNull('USUARIO_ID')->orderBy('FUNCIONARIO_ID')->first();
            if ($livre) {
                \Illuminate\Support\Facades\DB::table('FUNCIONARIO')
                    ->where('FUNCIONARIO_ID', $livre->FUNCIONARIO_ID)
                    ->update(['USUARIO_ID' => $user->USUARIO_ID]);
                $funcId = $livre->FUNCIONARIO_ID;
            }
        }

        return $funcId ? (int) $funcId : null;
    }
}

if (!function_exists('ensureTabelasAvaliacao')) {
    function ensureTabelasAvaliacao(): void
    {
        if (!\Illuminate\Support\Facades\Schema::hasTable('AVALIACAO_DESEMPENHO')) {
            \Illuminate\Support\Facades\Schema::create('AVALIACAO_DESEMPENHO', function ($table) {
                $table->increments('AVALIACAO_ID');
                $table->integer('FUNCIONARIO_ID');
                $table->string('AVALIACAO_CICLO', 20)->nullable();
                $table->decimal('AVALIACAO_NOTA_FINAL', 5, 2)->default(0);
                $table->string('AVALIACAO_STATUS', 30)->default('enviada');
                $table->integer('AVALIADOR_ID')->nullable();
                $table->text('AVALIACAO_OBS')->nullable();
                $table->timestamps();
            });
        }

        if (!\Illuminate\Support\Facades\Schema::hasTable('AVALIACAO_CRITERIO')) {
            \Illuminate\Support\Facades\Schema::create('AVALIACAO_CRITERIO', function ($table) {
                $table->increments('CRITERIO_ID');
                $table->integer('AVALIACAO_ID');
                $table->string('CRITERIO_NOME', 150);
                $table->integer('CRITERIO_PESO')->default(20);
                $table->integer('CRITERIO_NOTA')->default(0);
                $table->text('CRITERIO_OBS')->nullable();
                $table->timestamps();
            });
        }
    }
}

// GET — histórico de avaliações (do servidor logado OU de um funcionário específico quando gestor avalia)
Route::get('/avaliacoes', function (\Illuminate\Http\Request $request) {
    try {
        ensureTabelasAvaliacao();
        $funcId = resolveFuncionarioIdParaAvaliacao($request);

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
                'id'        => $av->AVALIACAO_ID,
                'ciclo'     => $av->ciclo,
                'nota'      => (float) $av->nota,
                'status'    => $av->status,
                'avaliador' => $av->avaliador ?? 'Gestor',
                'criado_em' => $av->created_at,
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
        ensureTabelasAvaliacao();
        $user = \Illuminate\Support\Facades\Auth::user();
        $avaliadorId = $user->USUARIO_ID ?? null;
        $funcId = resolveFuncionarioIdParaAvaliacao($request);

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
            'AVALIACAO_OBS'         => $request->input('AVALIACAO_OBS'),
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
