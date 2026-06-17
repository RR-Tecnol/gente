<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration {
    public function up(): void
    {
        if (!Schema::hasTable('SHADOW_RESULTADO_CALC')) {
            Schema::create('SHADOW_RESULTADO_CALC', function (Blueprint $table) {
                $table->increments('SHADOW_RESULTADO_CALC_ID');
                $table->string('RUN_ID', 80);
                $table->string('COMPETENCIA', 7);
                $table->integer('FOLHA_ID')->nullable();
                $table->integer('FUNCIONARIO_ID')->nullable();
                $table->string('CPF', 14)->nullable();
                $table->string('MATRICULA', 30)->nullable();
                $table->decimal('VALOR_LIQUIDO', 14, 2)->default(0);
                $table->decimal('VALOR_PROVENTOS', 14, 2)->default(0);
                $table->decimal('VALOR_DESCONTOS', 14, 2)->default(0);
                $table->string('FONTE', 20)->default('motorfolha');
                $table->timestamps();
                $table->unique(['RUN_ID', 'COMPETENCIA', 'CPF'], 'ux_shadow_resultado_run_comp_cpf');
                $table->index(['RUN_ID', 'FOLHA_ID'], 'idx_shadow_resultado_run_folha');
            });
        }
    }

    public function down(): void
    {
        if (Schema::hasTable('SHADOW_RESULTADO_CALC')) {
            Schema::drop('SHADOW_RESULTADO_CALC');
        }
    }
};

