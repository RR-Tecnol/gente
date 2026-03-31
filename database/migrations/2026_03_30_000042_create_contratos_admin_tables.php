<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        // CONTRATO_ADITIVO — aditamentos de prazo e/ou valor
        if (!Schema::hasTable('CONTRATO_ADITIVO')) {
            Schema::create('CONTRATO_ADITIVO', function (Blueprint $table) {
                $table->increments('ADITIVO_ID');
                $table->unsignedInteger('CONTRATO_ID');
                $table->integer('ADITIVO_NUMERO');           // 1º, 2º, 3º aditivo
                // PRAZO | VALOR | PRAZO_VALOR | RESCISAO | OUTROS
                $table->string('ADITIVO_TIPO', 20);
                $table->integer('ADITIVO_PRAZO_DIAS')->nullable();     // dias adicionados
                $table->decimal('ADITIVO_VALOR', 15, 2)->nullable();   // valor adicionado/reduzido
                $table->date('ADITIVO_DATA');
                $table->date('CONTRATO_FIM_NOVO')->nullable();          // nova data de vencimento
                $table->text('ADITIVO_OBJETO')->nullable();
                $table->unsignedInteger('REGISTRADO_POR')->nullable();
                $table->timestamps();

                $table->index(['CONTRATO_ID']);
            });
        }

        // CONTRATO_FISCALIZACAO — registros mensais de acompanhamento
        if (!Schema::hasTable('CONTRATO_FISCALIZACAO')) {
            Schema::create('CONTRATO_FISCALIZACAO', function (Blueprint $table) {
                $table->increments('FISCAL_ID');
                $table->unsignedInteger('CONTRATO_ID');
                $table->date('FISCAL_DATA');
                $table->string('FISCAL_COMPETENCIA', 7)->nullable(); // MM/YYYY
                // REGULAR | IRREGULAR | PENDENCIA | SUSPENSO
                $table->string('FISCAL_STATUS', 20)->default('REGULAR');
                $table->text('FISCAL_OBSERVACAO')->nullable();
                $table->string('FISCAL_RESPONSAVEL', 150)->nullable();
                $table->unsignedInteger('REGISTRADO_POR')->nullable();
                $table->timestamps();

                $table->index(['CONTRATO_ID', 'FISCAL_DATA']);
            });
        }
    }

    public function down(): void
    {
        Schema::dropIfExists('CONTRATO_FISCALIZACAO');
        Schema::dropIfExists('CONTRATO_ADITIVO');
    }
};
