<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

class CreateRegistroPontoTable extends Migration
{
    public function up()
    {
        Schema::create('REGISTRO_PONTO', function (Blueprint $table) {
            $table->id('REGISTRO_PONTO_ID');
            $table->unsignedBigInteger('FUNCIONARIO_ID');
            $table->unsignedBigInteger('TERMINAL_ID')->nullable();
            $table->dateTime('REGISTRO_DATA_HORA');
            $table->string('REGISTRO_TIPO', 10);       // ENTRADA|PAUSA|RETORNO|SAIDA
            $table->string('REGISTRO_ORIGEM', 25);     // REP_P|REP_A_SENHA|MANUAL
            $table->string('REGISTRO_NSR', 9)->nullable();  // Número Sequencial (AFD)
            $table->string('REGISTRO_OBSERVACAO', 255)->nullable();
        });
    }

    public function down()
    {
        Schema::dropIfExists('REGISTRO_PONTO');
    }
}
