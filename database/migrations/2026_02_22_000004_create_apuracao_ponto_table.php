<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

class CreateApuracaoPontoTable extends Migration
{
    public function up()
    {
        Schema::create('APURACAO_PONTO', function (Blueprint $table) {
            $table->id('APURACAO_ID');
            $table->unsignedBigInteger('FUNCIONARIO_ID');
            $table->string('APURACAO_COMPETENCIA', 7);  // "2025-03"
            $table->decimal('APURACAO_HORAS_TRAB', 6, 2)->default(0);
            $table->decimal('APURACAO_HORAS_EXTRA', 6, 2)->default(0);
            $table->decimal('APURACAO_HORAS_FALTA', 6, 2)->default(0);
            $table->string('APURACAO_STATUS', 10)->default('ABERTA'); // ABERTA|FECHADA|AJUSTADA
            $table->dateTime('APURACAO_FECHADA_EM')->nullable();
            $table->unsignedBigInteger('APURACAO_FECHADA_POR')->nullable();

            $table->unique(['FUNCIONARIO_ID', 'APURACAO_COMPETENCIA']);
        });
    }

    public function down()
    {
        Schema::dropIfExists('APURACAO_PONTO');
    }
}
