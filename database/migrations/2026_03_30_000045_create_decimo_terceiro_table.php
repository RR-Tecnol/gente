<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        // DECIMO_TERCEIRO — controle por funcionário × ano
        if (!Schema::hasTable('DECIMO_TERCEIRO')) {
            Schema::create('DECIMO_TERCEIRO', function (Blueprint $table) {
                $table->increments('DT_ID');
                $table->unsignedInteger('FUNCIONARIO_ID');
                $table->integer('DT_ANO');
                // PRIMEIRA_PARCELA | SEGUNDA_PARCELA | RESCISORIO
                $table->string('DT_TIPO', 20);
                // CALCULADO | PAGO | CANCELADO
                $table->string('DT_STATUS', 20)->default('CALCULADO');
                $table->integer('DT_MESES_TRABALHADOS')->default(12);
                $table->decimal('DT_SALARIO_BASE', 15, 2)->default(0);
                $table->decimal('DT_VALOR_BRUTO', 15, 2)->default(0);
                // 1ª parcela: sem INSS/IRRF | 2ª parcela: com INSS e IRRF
                $table->decimal('DT_VALOR_INSS', 15, 2)->default(0);
                $table->decimal('DT_VALOR_IRRF', 15, 2)->default(0);
                $table->decimal('DT_ADIANTAMENTO', 15, 2)->default(0); // desconto da 1ª parcela na 2ª
                $table->decimal('DT_VALOR_LIQUIDO', 15, 2)->default(0);
                $table->string('DT_COMPETENCIA', 6)->nullable(); // AAAAMM em que foi pago
                $table->unsignedInteger('GERADO_POR')->nullable();
                $table->timestamps();

                $table->unique(['FUNCIONARIO_ID', 'DT_ANO', 'DT_TIPO'], 'uq_dt_func_ano_tipo');
                $table->index(['DT_ANO', 'DT_TIPO']);
            });
        }
    }

    public function down(): void
    {
        Schema::dropIfExists('DECIMO_TERCEIRO');
    }
};
