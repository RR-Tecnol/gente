<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Run the migrations.
     *
     * @return void
     */
    public function up(): void
    {
        // CONSIGNATARIA — cadastro das operadoras parceiras
        if (!Schema::hasTable('CONSIGNATARIA')) {
            Schema::create('CONSIGNATARIA', function (Blueprint $table) {
                $table->increments('CONSIGNATARIA_ID');
                $table->string('CONSIGNATARIA_NOME', 100);
                $table->string('CONSIGNATARIA_CNPJ', 14)->nullable();
                $table->string('CONSIGNATARIA_CODIGO', 30)->nullable();
                $table->string('CONSIGNATARIA_TIPO', 50)->nullable(); // banco/cartao/seguro
                $table->boolean('CONSIGNATARIA_ATIVA')->default(true);
                $table->decimal('CONSIGNATARIA_MARGEM_MAX_PCT', 5, 2)->nullable();
                $table->string('CONSIGNATARIA_CONTATO', 255)->nullable();
                $table->timestamps();
            });
        }

        // LAYOUT_CONSIGNATARIA — parametrização do layout de remessa de cada operadora
        if (!Schema::hasTable('LAYOUT_CONSIGNATARIA')) {
            Schema::create('LAYOUT_CONSIGNATARIA', function (Blueprint $table) {
                $table->increments('LAYOUT_ID');
                $table->unsignedInteger('CONSIGNATARIA_ID');
                $table->string('LAYOUT_TIPO', 50); // remessa/retorno
                $table->string('LAYOUT_FORMATO', 20); // txt/csv/xml
                $table->string('LAYOUT_VERSAO', 50)->nullable();
                $table->json('LAYOUT_MAPEAMENTO')->nullable(); // mapa de campos/posições
                $table->boolean('LAYOUT_ATIVO')->default(true);
                $table->timestamps();
            });
        }

        // CONSIG_REMESSA — histórico de arquivos gerados/recebidos
        if (!Schema::hasTable('CONSIG_REMESSA')) {
            Schema::create('CONSIG_REMESSA', function (Blueprint $table) {
                $table->increments('REMESSA_ID');
                $table->unsignedInteger('CONSIGNATARIA_ID');
                $table->unsignedInteger('LAYOUT_ID'); // FK
                $table->string('REMESSA_COMPETENCIA', 10)->nullable();
                $table->string('REMESSA_TIPO', 50); // envio/retorno
                $table->string('REMESSA_STATUS', 50)->default('gerado'); // gerado/enviado/processado/erro
                $table->string('REMESSA_ARQUIVO_PATH', 255)->nullable();
                $table->integer('REMESSA_TOTAL_REGISTROS')->default(0);
                $table->decimal('REMESSA_TOTAL_VALOR', 15, 2)->default(0);
                $table->text('REMESSA_OBS')->nullable();
                $table->timestamps();
            });
        }
    }

    /**
     * Reverse the migrations.
     *
     * @return void
     */
    public function down(): void
    {
        Schema::dropIfExists('CONSIG_REMESSA');
        Schema::dropIfExists('LAYOUT_CONSIGNATARIA');
        Schema::dropIfExists('CONSIGNATARIA');
    }
};
