<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

class AddColunasPontoConfigFuncionario extends Migration
{
    /**
     * Run the migrations.
     *
     * @return void
     */
    public function up()
    {
        Schema::table('PONTO_CONFIG_FUNCIONARIO', function (Blueprint $table) {
            if (!Schema::hasColumn('PONTO_CONFIG_FUNCIONARIO', 'INTERVALO_ALMOCO')) {
                $table->unsignedSmallInteger('INTERVALO_ALMOCO')->nullable(); // minutos, null = usar turno
            }
            if (!Schema::hasColumn('PONTO_CONFIG_FUNCIONARIO', 'JORNADA_FINANCEIRA_HORAS')) {
                // Jornada para fins de folha (acordo informal)
                $table->decimal('JORNADA_FINANCEIRA_HORAS', 4, 2)->nullable();
            }
            if (!Schema::hasColumn('PONTO_CONFIG_FUNCIONARIO', 'JORNADA_FINANCEIRA_OBS')) {
                $table->string('JORNADA_FINANCEIRA_OBS', 500)->nullable();
            }
        });
    }

    /**
     * Reverse the migrations.
     *
     * @return void
     */
    public function down()
    {
        Schema::table('PONTO_CONFIG_FUNCIONARIO', function (Blueprint $table) {
            if (Schema::hasColumn('PONTO_CONFIG_FUNCIONARIO', 'INTERVALO_ALMOCO')) {
                $table->dropColumn('INTERVALO_ALMOCO');
            }
            if (Schema::hasColumn('PONTO_CONFIG_FUNCIONARIO', 'JORNADA_FINANCEIRA_HORAS')) {
                $table->dropColumn('JORNADA_FINANCEIRA_HORAS');
            }
            if (Schema::hasColumn('PONTO_CONFIG_FUNCIONARIO', 'JORNADA_FINANCEIRA_OBS')) {
                $table->dropColumn('JORNADA_FINANCEIRA_OBS');
            }
        });
    }
}
