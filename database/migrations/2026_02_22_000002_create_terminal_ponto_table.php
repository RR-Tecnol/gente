<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

class CreateTerminalPontoTable extends Migration
{
    public function up()
    {
        Schema::create('TERMINAL_PONTO', function (Blueprint $table) {
            $table->id('TERMINAL_ID');
            $table->unsignedBigInteger('UNIDADE_ID');
            $table->string('TERMINAL_NOME', 100);
            $table->string('TERMINAL_TOKEN', 64)->unique();
            $table->string('TERMINAL_IP', 45)->nullable();
            $table->boolean('TERMINAL_ATIVO')->default(true);
            $table->string('TERMINAL_METODOS', 50)->default('SENHA'); // SENHA,FACIAL,AFD
        });
    }

    public function down()
    {
        Schema::dropIfExists('TERMINAL_PONTO');
    }
}
