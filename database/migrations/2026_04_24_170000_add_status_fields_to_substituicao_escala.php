<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        if (!Schema::hasTable('SUBSTITUICAO_ESCALA')) {
            return;
        }

        Schema::table('SUBSTITUICAO_ESCALA', function (Blueprint $table) {
            if (!Schema::hasColumn('SUBSTITUICAO_ESCALA', 'SUBSTITUICAO_ESCALA_STATUS')) {
                $table->string('SUBSTITUICAO_ESCALA_STATUS', 20)->default('pendente');
            }
            if (!Schema::hasColumn('SUBSTITUICAO_ESCALA', 'SUBSTITUICAO_ESCALA_JUSTIFICATIVA')) {
                $table->string('SUBSTITUICAO_ESCALA_JUSTIFICATIVA', 255)->nullable();
            }
            if (!Schema::hasColumn('SUBSTITUICAO_ESCALA', 'SUBSTITUICAO_ESCALA_TURNO')) {
                $table->string('SUBSTITUICAO_ESCALA_TURNO', 40)->nullable();
            }
        });
    }

    public function down(): void
    {
        if (!Schema::hasTable('SUBSTITUICAO_ESCALA')) {
            return;
        }

        Schema::table('SUBSTITUICAO_ESCALA', function (Blueprint $table) {
            if (Schema::hasColumn('SUBSTITUICAO_ESCALA', 'SUBSTITUICAO_ESCALA_TURNO')) {
                $table->dropColumn('SUBSTITUICAO_ESCALA_TURNO');
            }
            if (Schema::hasColumn('SUBSTITUICAO_ESCALA', 'SUBSTITUICAO_ESCALA_JUSTIFICATIVA')) {
                $table->dropColumn('SUBSTITUICAO_ESCALA_JUSTIFICATIVA');
            }
            if (Schema::hasColumn('SUBSTITUICAO_ESCALA', 'SUBSTITUICAO_ESCALA_STATUS')) {
                $table->dropColumn('SUBSTITUICAO_ESCALA_STATUS');
            }
        });
    }
};
