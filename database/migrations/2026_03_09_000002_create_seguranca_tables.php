<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration {
    public function up(): void
    {
        // EPIs por funcionário
        if (!Schema::hasTable('SEGURANCA_EPI')) {
            Schema::create('SEGURANCA_EPI', function (Blueprint $table) {
                $table->increments('EPI_ID');
                $table->unsignedInteger('FUNCIONARIO_ID')->nullable()->index();
                $table->string('EPI_NOME', 150);
                $table->string('EPI_CA', 30)->nullable();          // certificado de aprovação
                $table->string('EPI_ICONE', 10)->nullable();       // emoji
                $table->date('EPI_VALIDADE')->nullable();
                $table->tinyInteger('EPI_QUANTIDADE')->default(1);
                $table->timestamps();
            });
        }

        // Incidentes / quase-acidentes
        if (!Schema::hasTable('SEGURANCA_INCIDENTE')) {
            Schema::create('SEGURANCA_INCIDENTE', function (Blueprint $table) {
                $table->increments('INCIDENTE_ID');
                $table->unsignedInteger('FUNCIONARIO_ID')->nullable()->index();
                $table->string('INCIDENTE_TIPO', 30)->default('quase'); // quase | acidente
                $table->date('INCIDENTE_DATA');
                $table->string('INCIDENTE_LOCAL', 200);
                $table->text('INCIDENTE_DESCRICAO');
                $table->string('INCIDENTE_CAT', 30)->nullable();   // número do CAT
                $table->boolean('INCIDENTE_FECHADO')->default(false);
                $table->timestamps();
            });
        }
    }

    public function down(): void
    {
        Schema::dropIfExists('SEGURANCA_INCIDENTE');
        Schema::dropIfExists('SEGURANCA_EPI');
    }
};
