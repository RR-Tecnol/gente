<?php

use App\Domain\Escala\EscalaWorkflowStatus;
use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        if (! Schema::hasTable('ESCALA')) {
            return;
        }

        $driver = Schema::getConnection()->getDriverName();
        if ($driver === 'mysql') {
            try {
                DB::statement('ALTER TABLE ESCALA MODIFY ESCALA_STATUS VARCHAR(64) NULL');
            } catch (\Throwable) {
                // ignora se o driver não suportar MODIFY (ambiente legado)
            }
        }

        Schema::table('ESCALA', function (Blueprint $table) {
            if (! Schema::hasColumn('ESCALA', 'ESCALA_ENVIADA_EM')) {
                $table->dateTime('ESCALA_ENVIADA_EM')->nullable();
            }
            if (! Schema::hasColumn('ESCALA', 'ESCALA_ENVIADA_POR')) {
                $table->unsignedInteger('ESCALA_ENVIADA_POR')->nullable();
            }
            if (! Schema::hasColumn('ESCALA', 'ESCALA_HOMOLOGADA_EM')) {
                $table->dateTime('ESCALA_HOMOLOGADA_EM')->nullable();
            }
            if (! Schema::hasColumn('ESCALA', 'ESCALA_HOMOLOGADA_POR')) {
                $table->unsignedInteger('ESCALA_HOMOLOGADA_POR')->nullable();
            }
            if (! Schema::hasColumn('ESCALA', 'ESCALA_MOTIVO_DEVOLUCAO')) {
                $table->text('ESCALA_MOTIVO_DEVOLUCAO')->nullable();
            }
            if (! Schema::hasColumn('ESCALA', 'ESCALA_DEVOLVIDA_EM')) {
                $table->dateTime('ESCALA_DEVOLVIDA_EM')->nullable();
            }
            if (! Schema::hasColumn('ESCALA', 'ESCALA_DEVOLVIDA_POR')) {
                $table->unsignedInteger('ESCALA_DEVOLVIDA_POR')->nullable();
            }
        });

        // Normaliza status legado para o workflow v3
        if (Schema::hasColumn('ESCALA', 'ESCALA_STATUS')) {
            DB::table('ESCALA')
                ->where(function ($q) {
                    $q->whereNull('ESCALA_STATUS')
                        ->orWhereRaw('TRIM(ESCALA_STATUS) = ?', [''])
                        ->orWhereIn(DB::raw('UPPER(TRIM(ESCALA_STATUS))'), ['ABERTA', 'RASCUNHO', 'ABERTO']);
                })
                ->update(['ESCALA_STATUS' => EscalaWorkflowStatus::RASCUNHO]);

            DB::table('ESCALA')
                ->whereIn(DB::raw('UPPER(TRIM(ESCALA_STATUS))'), ['PUBLICADA', 'PUBLICADO', 'HOMOLOGADA'])
                ->update(['ESCALA_STATUS' => EscalaWorkflowStatus::HOMOLOGADO_SAGEP]);
        }
    }

    public function down(): void
    {
        if (! Schema::hasTable('ESCALA')) {
            return;
        }
        Schema::table('ESCALA', function (Blueprint $table) {
            foreach ([
                'ESCALA_ENVIADA_EM',
                'ESCALA_ENVIADA_POR',
                'ESCALA_HOMOLOGADA_EM',
                'ESCALA_HOMOLOGADA_POR',
                'ESCALA_MOTIVO_DEVOLUCAO',
                'ESCALA_DEVOLVIDA_EM',
                'ESCALA_DEVOLVIDA_POR',
            ] as $col) {
                if (Schema::hasColumn('ESCALA', $col)) {
                    $table->dropColumn($col);
                }
            }
        });
    }
};
