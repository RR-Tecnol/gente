<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

class AddDataAtualizacaoToFuncionarioTable extends Migration
{
    public function up()
    {
        Schema::table('FUNCIONARIO', function (Blueprint $table) {
            if (!Schema::hasColumn('FUNCIONARIO', 'FUNCIONARIO_DATA_ATUALIZACAO')) {
                $table->string('FUNCIONARIO_DATA_ATUALIZACAO', 30)->nullable();
            }
            if (!Schema::hasColumn('FUNCIONARIO', 'FUNCIONARIO_DATA_CADASTRO')) {
                $table->string('FUNCIONARIO_DATA_CADASTRO', 30)->nullable();
            }
        });
    }

    public function down()
    {
        Schema::table('FUNCIONARIO', function (Blueprint $table) {
            $table->dropColumn('FUNCIONARIO_DATA_ATUALIZACAO');
            $table->dropColumn('FUNCIONARIO_DATA_CADASTRO');
        });
    }
}
