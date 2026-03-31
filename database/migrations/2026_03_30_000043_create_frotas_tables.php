<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        // VEICULO — cadastro da frota municipal
        if (!Schema::hasTable('VEICULO')) {
            Schema::create('VEICULO', function (Blueprint $table) {
                $table->increments('VEICULO_ID');
                $table->string('VEICULO_PLACA', 10)->unique();
                $table->string('VEICULO_MODELO', 100);
                $table->string('VEICULO_MARCA', 50);
                $table->integer('VEICULO_ANO');
                // CARRO | VAN | ONIBUS | CAMINHAO | MOTO | AMBULANCIA
                $table->string('VEICULO_TIPO', 30);
                // DISPONIVEL | EM_USO | EM_MANUTENCAO | INATIVO
                $table->string('VEICULO_STATUS', 20)->default('DISPONIVEL');
                $table->unsignedInteger('UO_ID')->nullable();
                $table->integer('VEICULO_KM_ATUAL')->default(0);
                $table->date('VEICULO_PROX_MANUTENCAO')->nullable();
                $table->string('VEICULO_COR', 30)->nullable();
                $table->string('VEICULO_RENAVAM', 20)->nullable();
                $table->timestamps();

                $table->index(['VEICULO_STATUS']);
            });
        }

        // SAIDA_VEICULO — controle de saídas e retornos
        if (!Schema::hasTable('SAIDA_VEICULO')) {
            Schema::create('SAIDA_VEICULO', function (Blueprint $table) {
                $table->increments('SAIDA_ID');
                $table->unsignedInteger('VEICULO_ID');
                $table->unsignedInteger('MOTORISTA_ID');         // FUNCIONARIO_ID
                $table->unsignedInteger('UO_SOLICITANTE_ID')->nullable();
                $table->string('SAIDA_DESTINO', 200);
                $table->string('SAIDA_FINALIDADE', 200);
                $table->dateTime('SAIDA_DATA_HORA');
                $table->dateTime('RETORNO_DATA_HORA')->nullable();
                $table->integer('KM_SAIDA');
                $table->integer('KM_RETORNO')->nullable();
                // KM_PERCORRIDO calculado ao registrar retorno
                $table->integer('KM_PERCORRIDO')->nullable();
                $table->unsignedInteger('REGISTRADO_POR')->nullable();
                $table->timestamps();

                $table->index(['VEICULO_ID', 'SAIDA_DATA_HORA']);
            });
        }

        // MANUTENCAO_VEICULO — histórico de manutenções preventivas e corretivas
        if (!Schema::hasTable('MANUTENCAO_VEICULO')) {
            Schema::create('MANUTENCAO_VEICULO', function (Blueprint $table) {
                $table->increments('MANUT_ID');
                $table->unsignedInteger('VEICULO_ID');
                // PREVENTIVA | CORRETIVA
                $table->string('MANUT_TIPO', 30);
                $table->string('MANUT_DESCRICAO', 300);
                $table->decimal('MANUT_VALOR', 10, 2)->nullable();
                $table->date('MANUT_DATA');
                $table->date('MANUT_PROXIMA')->nullable();
                $table->string('MANUT_FORNECEDOR', 150)->nullable();
                $table->unsignedInteger('REGISTRADO_POR')->nullable();
                $table->timestamps();
            });
        }
    }

    public function down(): void
    {
        Schema::dropIfExists('MANUTENCAO_VEICULO');
        Schema::dropIfExists('SAIDA_VEICULO');
        Schema::dropIfExists('VEICULO');
    }
};
