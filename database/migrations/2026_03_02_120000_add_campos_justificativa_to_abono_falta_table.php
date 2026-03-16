<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

class AddCamposJustificativaToAbonoFaltaTable extends Migration
{
    public function up()
    {
        Schema::table('ABONO_FALTA', function (Blueprint $table) {
            if (!Schema::hasColumn('ABONO_FALTA', 'ABONO_FALTA_JUSTIFICATIVA')) {
                $table->text('ABONO_FALTA_JUSTIFICATIVA')->nullable();
            }
            if (!Schema::hasColumn('ABONO_FALTA', 'ABONO_FALTA_TIPO')) {
                $table->string('ABONO_FALTA_TIPO', 30)->nullable();
            }
            if (!Schema::hasColumn('ABONO_FALTA', 'ABONO_FALTA_COMPROVANTE')) {
                $table->string('ABONO_FALTA_COMPROVANTE', 255)->nullable();
            }
        });
    }

    public function down()
    {
        Schema::table('ABONO_FALTA', function (Blueprint $table) {
            $table->dropColumn(['ABONO_FALTA_JUSTIFICATIVA', 'ABONO_FALTA_TIPO', 'ABONO_FALTA_COMPROVANTE']);
        });
    }
}
