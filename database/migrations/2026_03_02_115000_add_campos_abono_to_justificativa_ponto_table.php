<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

class AddCamposAbonoToJustificativaPontoTable extends Migration
{
    public function up()
    {
        Schema::table('JUSTIFICATIVA_PONTO', function (Blueprint $table) {
            if (!Schema::hasColumn('JUSTIFICATIVA_PONTO', 'JUSTIFICATIVA_TIPO')) {
                $table->string('JUSTIFICATIVA_TIPO', 30)->nullable();
            }
            if (!Schema::hasColumn('JUSTIFICATIVA_PONTO', 'JUSTIFICATIVA_COMPROVANTE')) {
                $table->string('JUSTIFICATIVA_COMPROVANTE', 255)->nullable();
            }
        });
    }

    public function down()
    {
        Schema::table('JUSTIFICATIVA_PONTO', function (Blueprint $table) {
            $table->dropColumn(['JUSTIFICATIVA_TIPO', 'JUSTIFICATIVA_COMPROVANTE']);
        });
    }
}
