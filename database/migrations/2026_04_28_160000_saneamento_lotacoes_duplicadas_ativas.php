<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration {
    public function up(): void
    {
        if (!Schema::hasTable('LOTACAO')) {
            return;
        }
        if (!Schema::hasColumn('LOTACAO', 'FUNCIONARIO_ID') || !Schema::hasColumn('LOTACAO', 'LOTACAO_DATA_FIM')) {
            return;
        }

        $duplicados = DB::table('LOTACAO')
            ->select('FUNCIONARIO_ID')
            ->whereNull('LOTACAO_DATA_FIM')
            ->groupBy('FUNCIONARIO_ID')
            ->havingRaw('COUNT(*) > 1')
            ->pluck('FUNCIONARIO_ID')
            ->map(fn ($id) => (int) $id)
            ->all();

        foreach ($duplicados as $funcionarioId) {
            $lotacoesAtivas = DB::table('LOTACAO')
                ->where('FUNCIONARIO_ID', $funcionarioId)
                ->whereNull('LOTACAO_DATA_FIM')
                ->orderByDesc('LOTACAO_DATA_INICIO')
                ->orderByDesc('LOTACAO_ID')
                ->get(['LOTACAO_ID']);

            if ($lotacoesAtivas->count() <= 1) {
                continue;
            }

            $keeperId = (int) ($lotacoesAtivas->first()->LOTACAO_ID ?? 0);
            if ($keeperId <= 0) {
                continue;
            }

            $payload = ['LOTACAO_DATA_FIM' => now()->toDateString()];
            if (Schema::hasColumn('LOTACAO', 'LOTACAO_OBSERVACAO')) {
                $payload['LOTACAO_OBSERVACAO'] = 'SANEAMENTO AUTOMÁTICO: DUPLICIDADE DE LOTAÇÃO ATIVA';
            }

            DB::table('LOTACAO')
                ->where('FUNCIONARIO_ID', $funcionarioId)
                ->whereNull('LOTACAO_DATA_FIM')
                ->where('LOTACAO_ID', '<>', $keeperId)
                ->update($payload);
        }
    }

    public function down(): void
    {
        // Não reabre lotações encerradas por saneamento.
    }
};

