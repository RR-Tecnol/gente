<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration {
    public function up(): void
    {
        if (!Schema::hasTable('SUBSTITUICAO_ESCALA')) {
            return;
        }

        Schema::table('SUBSTITUICAO_ESCALA', function (Blueprint $table) {
            if (!Schema::hasColumn('SUBSTITUICAO_ESCALA', 'TIPO_CONVOCACAO')) {
                $table->string('TIPO_CONVOCACAO', 20)->default('OPTATIVA');
            }
            if (!Schema::hasColumn('SUBSTITUICAO_ESCALA', 'HORARIO_INICIO')) {
                $table->time('HORARIO_INICIO')->nullable();
            }
            if (!Schema::hasColumn('SUBSTITUICAO_ESCALA', 'HORARIO_FIM')) {
                $table->time('HORARIO_FIM')->nullable();
            }
            if (!Schema::hasColumn('SUBSTITUICAO_ESCALA', 'UNIDADE_ESCOLAR')) {
                $table->string('UNIDADE_ESCOLAR', 150)->nullable();
            }
            if (!Schema::hasColumn('SUBSTITUICAO_ESCALA', 'DISCIPLINA_CARGO')) {
                $table->string('DISCIPLINA_CARGO', 150)->nullable();
            }
        });
    }

    public function down(): void
    {
        if (!Schema::hasTable('SUBSTITUICAO_ESCALA')) {
            return;
        }

        Schema::table('SUBSTITUICAO_ESCALA', function (Blueprint $table) {
            if (Schema::hasColumn('SUBSTITUICAO_ESCALA', 'DISCIPLINA_CARGO')) {
                $table->dropColumn('DISCIPLINA_CARGO');
            }
            if (Schema::hasColumn('SUBSTITUICAO_ESCALA', 'UNIDADE_ESCOLAR')) {
                $table->dropColumn('UNIDADE_ESCOLAR');
            }
            if (Schema::hasColumn('SUBSTITUICAO_ESCALA', 'HORARIO_FIM')) {
                $table->dropColumn('HORARIO_FIM');
            }
            if (Schema::hasColumn('SUBSTITUICAO_ESCALA', 'HORARIO_INICIO')) {
                $table->dropColumn('HORARIO_INICIO');
            }
            if (Schema::hasColumn('SUBSTITUICAO_ESCALA', 'TIPO_CONVOCACAO')) {
                $table->dropColumn('TIPO_CONVOCACAO');
            }
        });
    }
};

