<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration {
    public function up(): void
    {
        if (!Schema::hasTable('AVALIACAO_DESEMPENHO')) {
            Schema::create('AVALIACAO_DESEMPENHO', function (Blueprint $table) {
                $table->increments('AVALIACAO_ID');
                $table->integer('FUNCIONARIO_ID');
                $table->string('AVALIACAO_CICLO', 20)->nullable();
                $table->decimal('AVALIACAO_NOTA_FINAL', 5, 2)->default(0);
                $table->string('AVALIACAO_STATUS', 30)->default('enviada');
                $table->integer('AVALIADOR_ID')->nullable();
                $table->text('AVALIACAO_OBS')->nullable();
                $table->timestamps();
            });
        }

        if (!Schema::hasTable('AVALIACAO_CRITERIO')) {
            Schema::create('AVALIACAO_CRITERIO', function (Blueprint $table) {
                $table->increments('CRITERIO_ID');
                $table->integer('AVALIACAO_ID');
                $table->string('CRITERIO_NOME', 150);
                $table->integer('CRITERIO_PESO')->default(20);
                $table->integer('CRITERIO_NOTA')->default(0);
                $table->text('CRITERIO_OBS')->nullable();
                $table->timestamps();
            });
        }
    }

    public function down(): void
    {
        if (Schema::hasTable('AVALIACAO_CRITERIO')) {
            Schema::drop('AVALIACAO_CRITERIO');
        }
        if (Schema::hasTable('AVALIACAO_DESEMPENHO')) {
            Schema::drop('AVALIACAO_DESEMPENHO');
        }
    }
};
