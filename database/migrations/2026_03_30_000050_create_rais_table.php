<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        if (!Schema::hasTable('RAIS_ENVIO')) {
            Schema::create('RAIS_ENVIO', function (Blueprint $table) {
                $table->increments('RAIS_ID');
                $table->integer('RAIS_ANO');
                $table->string('RAIS_STATUS', 20)->default('GERADO');
                $table->integer('RAIS_TOTAL_VINCULOS')->default(0);
                $table->string('RAIS_ARQUIVO_NOME', 200)->nullable();
                $table->text('RAIS_CONTEUDO')->nullable();
                $table->unsignedInteger('GERADO_POR')->nullable();
                $table->timestamps();

                $table->unique(['RAIS_ANO'], 'uq_rais_ano');
            });
        }
    }

    public function down(): void
    {
        Schema::dropIfExists('RAIS_ENVIO');
    }
};
