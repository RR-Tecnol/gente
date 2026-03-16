<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration {
    public function up(): void
    {
        // Tabela principal — um registro por ciclo de avaliação
        if (!Schema::hasTable('AVALIACAO_DESEMPENHO')) {
            Schema::create('AVALIACAO_DESEMPENHO', function (Blueprint $table) {
                $table->increments('AVALIACAO_ID');
                $table->unsignedInteger('FUNCIONARIO_ID')->nullable()->index();
                $table->string('AVALIACAO_CICLO', 20);          // ex: "2026.1"
                $table->decimal('AVALIACAO_NOTA_FINAL', 4, 1)->nullable();
                $table->string('AVALIACAO_STATUS', 30)->default('rascunho'); // rascunho|enviada|publicada
                $table->unsignedInteger('AVALIADOR_ID')->nullable();
                $table->text('AVALIACAO_OBS')->nullable();
                $table->timestamps();
            });
        }

        // Critérios individuais de cada avaliação
        if (!Schema::hasTable('AVALIACAO_CRITERIO')) {
            Schema::create('AVALIACAO_CRITERIO', function (Blueprint $table) {
                $table->increments('CRITERIO_ID');
                $table->unsignedInteger('AVALIACAO_ID')->index();
                $table->string('CRITERIO_NOME', 100);
                $table->tinyInteger('CRITERIO_PESO')->default(20);  // % do total
                $table->tinyInteger('CRITERIO_NOTA')->nullable();   // 1–10
                $table->text('CRITERIO_OBS')->nullable();
                $table->timestamps();
            });
        }
    }

    public function down(): void
    {
        Schema::dropIfExists('AVALIACAO_CRITERIO');
        Schema::dropIfExists('AVALIACAO_DESEMPENHO');
    }
};
