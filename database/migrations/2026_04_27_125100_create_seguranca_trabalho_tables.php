<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration {
    public function up(): void
    {
        if (!Schema::hasTable('EPI_REGISTRO')) {
            Schema::create('EPI_REGISTRO', function (Blueprint $table) {
                $table->increments('EPI_ID');
                $table->unsignedInteger('FUNCIONARIO_ID')->index();
                $table->string('EPI_NOME', 200);
                $table->string('EPI_OBS', 300)->nullable();
                $table->string('EPI_CA', 30)->nullable();
                $table->integer('EPI_QUANTIDADE')->default(1);
                $table->boolean('EPI_ENTREGUE')->default(false);
                $table->date('EPI_DATA_VENCIMENTO')->nullable();
                $table->timestamps();
            });
        }
        if (!Schema::hasTable('ACIDENTE_TRABALHO')) {
            Schema::create('ACIDENTE_TRABALHO', function (Blueprint $table) {
                $table->increments('ACIDENTE_ID');
                $table->unsignedInteger('FUNCIONARIO_ID')->index()->nullable();
                $table->string('ACIDENTE_TIPO', 30);
                $table->string('ACIDENTE_LOCAL', 150);
                $table->text('ACIDENTE_DESCRICAO')->nullable();
                $table->string('ACIDENTE_CAT', 50)->nullable();
                $table->boolean('ACIDENTE_CLOSED')->default(false);
                $table->timestamps();
            });
        }
        if (!Schema::hasTable('LAUDO_SST')) {
            Schema::create('LAUDO_SST', function (Blueprint $table) {
                $table->increments('LAUDO_ID');
                $table->string('LAUDO_TIPO', 50);
                $table->string('LAUDO_LOCAL', 150);
                $table->date('LAUDO_DATA_VALIDADE')->nullable();
                $table->string('LAUDO_STATUS', 20)->default('Vigente');
                $table->timestamps();
            });
        }
    }

    public function down(): void
    {
        if (Schema::hasTable('LAUDO_SST')) {
            Schema::drop('LAUDO_SST');
        }
        if (Schema::hasTable('ACIDENTE_TRABALHO')) {
            Schema::drop('ACIDENTE_TRABALHO');
        }
        if (Schema::hasTable('EPI_REGISTRO')) {
            Schema::drop('EPI_REGISTRO');
        }
    }
};
