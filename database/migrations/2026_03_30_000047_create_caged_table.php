<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        if (!Schema::hasTable('CAGED_ENVIO')) {
            Schema::create('CAGED_ENVIO', function (Blueprint $table) {
                $table->increments('CAGED_ID');
                $table->string('CAGED_COMPETENCIA', 6); // AAAAMM
                // GERADO | ENVIADO | RETIFICADO
                $table->string('CAGED_STATUS', 20)->default('GERADO');
                $table->integer('CAGED_ADMISSOES')->default(0);
                $table->integer('CAGED_DEMISSOES')->default(0);
                $table->string('CAGED_ARQUIVO_NOME', 200)->nullable();
                $table->text('CAGED_CONTEUDO')->nullable(); // arquivo gerado
                $table->unsignedInteger('GERADO_POR')->nullable();
                $table->timestamps();

                $table->unique(['CAGED_COMPETENCIA'], 'uq_caged_competencia');
            });
        }
    }

    public function down(): void
    {
        Schema::dropIfExists('CAGED_ENVIO');
    }
};
