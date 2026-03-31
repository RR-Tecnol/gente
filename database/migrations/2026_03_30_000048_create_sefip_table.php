<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        if (!Schema::hasTable('SEFIP_ENVIO')) {
            Schema::create('SEFIP_ENVIO', function (Blueprint $table) {
                $table->increments('SEFIP_ID');
                $table->string('SEFIP_COMPETENCIA', 6); // AAAAMM
                // GERADO | TRANSMITIDO | RETIFICADO
                $table->string('SEFIP_STATUS', 20)->default('GERADO');
                $table->integer('SEFIP_TOTAL_TRABALHADORES')->default(0);
                $table->decimal('SEFIP_TOTAL_FGTS', 15, 2)->default(0);
                $table->decimal('SEFIP_TOTAL_INSS', 15, 2)->default(0);
                $table->string('SEFIP_ARQUIVO_NOME', 200)->nullable();
                $table->text('SEFIP_CONTEUDO')->nullable();
                $table->unsignedInteger('GERADO_POR')->nullable();
                $table->timestamps();

                $table->unique(['SEFIP_COMPETENCIA'], 'uq_sefip_competencia');
            });
        }
    }

    public function down(): void
    {
        Schema::dropIfExists('SEFIP_ENVIO');
    }
};
