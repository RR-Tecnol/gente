<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration {
    public function up(): void
    {
        if (!Schema::hasTable('DIFF_RECONCILIACAO')) {
            Schema::create('DIFF_RECONCILIACAO', function (Blueprint $table) {
                $table->increments('DIFF_ID');
                $table->string('RUN_ID', 80);
                $table->string('COMPETENCIA', 7);
                $table->string('CPF', 14)->nullable();
                $table->string('MATRICULA', 30)->nullable();
                $table->string('CHAVE_SERVIDOR', 120)->nullable();
                $table->decimal('VALOR_LEGADO', 14, 2)->default(0);
                $table->decimal('VALOR_NOVO', 14, 2)->default(0);
                $table->decimal('DELTA_ABSOLUTO', 14, 2)->default(0);
                $table->string('CLASSIFICACAO', 40); // APROVADO_EXATO|DIVERGENCIA_TOLERAVEL|DIVERGENCIA_JUSTIFICAVEL|FALHA_SISTEMICA_CRITICA
                $table->boolean('JUSTIFICADO')->default(false);
                $table->text('JUSTIFICATIVA')->nullable();
                $table->timestamps();
                $table->index(['RUN_ID', 'CLASSIFICACAO'], 'idx_diff_run_class');
            });
        }
    }

    public function down(): void
    {
        if (Schema::hasTable('DIFF_RECONCILIACAO')) {
            Schema::drop('DIFF_RECONCILIACAO');
        }
    }
};

