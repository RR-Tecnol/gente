<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration {
    public function up(): void
    {
        if (!Schema::hasTable('RPPS_PROVA_VIDA')) {
            Schema::create('RPPS_PROVA_VIDA', function (Blueprint $t) {
                $t->increments('RPPS_PROVA_VIDA_ID');
                $t->integer('FUNCIONARIO_ID');
                $t->string('COMPETENCIA', 7); // YYYY-MM
                $t->string('STATUS', 25)->default('pendente'); // pendente|regular|bloqueio_iminente|bloqueado
                $t->string('TIPO_PROCEDIMENTO', 20)->nullable(); // ordinaria|extraordinaria
                $t->string('CANAL', 30)->nullable(); // presencial|govbr|domiciliar|procurador
                $t->date('DATA_REFERENCIA')->nullable();
                $t->date('PRAZO_FINAL')->nullable();
                $t->date('DATA_REGISTRO')->nullable();
                $t->string('MOTIVO', 255)->nullable();
                $t->integer('VALIDADO_POR')->nullable();
                $t->timestamps();
                $t->unique(['FUNCIONARIO_ID', 'COMPETENCIA'], 'ux_rpps_prova_vida_func_comp');
            });
        }

        if (!Schema::hasTable('RPPS_BLOQUEIO_EVENTO')) {
            Schema::create('RPPS_BLOQUEIO_EVENTO', function (Blueprint $t) {
                $t->increments('RPPS_BLOQUEIO_EVENTO_ID');
                $t->integer('FUNCIONARIO_ID');
                $t->string('COMPETENCIA', 7);
                $t->string('EVENTO', 30); // bloqueio_iminente|bloqueado|desbloqueado
                $t->string('ORIGEM', 30)->nullable(); // scheduler|manual
                $t->string('MOTIVO', 255)->nullable();
                $t->integer('USUARIO_ID')->nullable();
                $t->timestamps();
            });
        }
    }

    public function down(): void
    {
        if (Schema::hasTable('RPPS_BLOQUEIO_EVENTO')) {
            Schema::drop('RPPS_BLOQUEIO_EVENTO');
        }
        if (Schema::hasTable('RPPS_PROVA_VIDA')) {
            Schema::drop('RPPS_PROVA_VIDA');
        }
    }
};
