<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        if (!Schema::hasTable('DIRF_ENVIO')) {
            Schema::create('DIRF_ENVIO', function (Blueprint $table) {
                $table->increments('DIRF_ID');
                $table->integer('DIRF_ANO');
                $table->string('DIRF_STATUS', 20)->default('GERADO');
                $table->integer('DIRF_TOTAL_BENEFICIARIOS')->default(0);
                $table->decimal('DIRF_TOTAL_RENDIMENTOS', 15, 2)->default(0);
                $table->decimal('DIRF_TOTAL_IRRF', 15, 2)->default(0);
                $table->string('DIRF_ARQUIVO_NOME', 200)->nullable();
                $table->text('DIRF_CONTEUDO')->nullable();
                $table->unsignedInteger('GERADO_POR')->nullable();
                $table->timestamps();

                $table->unique(['DIRF_ANO'], 'uq_dirf_ano');
            });
        }
    }

    public function down(): void
    {
        Schema::dropIfExists('DIRF_ENVIO');
    }
};
