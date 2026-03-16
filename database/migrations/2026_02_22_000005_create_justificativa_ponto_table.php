<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

class CreateJustificativaPontoTable extends Migration
{
    public function up()
    {
        Schema::create('JUSTIFICATIVA_PONTO', function (Blueprint $table) {
            $table->id('JUSTIFICATIVA_ID');
            $table->unsignedBigInteger('APURACAO_ID');
            $table->date('JUSTIFICATIVA_DATA');
            $table->string('JUSTIFICATIVA_MOTIVO', 255);
            $table->string('JUSTIFICATIVA_STATUS', 10)->default('PENDENTE'); // PENDENTE|APROVADA|REJEITADA
            $table->unsignedBigInteger('GESTOR_ID')->nullable();
            $table->string('GESTOR_OBS', 255)->nullable();
            $table->dateTime('GESTOR_DECISAO_EM')->nullable();
        });
    }

    public function down()
    {
        Schema::dropIfExists('JUSTIFICATIVA_PONTO');
    }
}
