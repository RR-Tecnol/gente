<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Migration de setup para banco SQLite local.
 * Cria as tabelas essenciais do sistema de RH que não foram migradas
 * porque as migrations originais são SQL Server (stored procedures).
 */
return new class extends Migration {
    public function up(): void
    {
        // PESSOA
        if (!Schema::hasTable('PESSOA')) {
            Schema::create('PESSOA', function (Blueprint $table) {
                $table->increments('PESSOA_ID');
                $table->string('PESSOA_NOME', 200)->nullable();
                $table->string('PESSOA_CPF_NUMERO', 20)->nullable();
                $table->integer('USUARIO_ID')->nullable();
                $table->date('PESSOA_DATA_CADASTRO')->nullable();
                $table->integer('PESSOA_STATUS')->nullable()->default(1);
                $table->integer('PESSOA_PRE_CADASTRO')->nullable()->default(0);
                $table->integer('BAIRRO_ID')->nullable();
                $table->string('PESSOA_ENDERECO', 200)->nullable();
                $table->string('PESSOA_COMPLEMENTO', 100)->nullable();
                $table->string('PESSOA_CEP', 10)->nullable();
                $table->date('PESSOA_DATA_NASCIMENTO')->nullable();
                $table->integer('PESSOA_ESCOLARIDADE')->nullable();
                $table->integer('PESSOA_SEXO')->nullable();
                $table->integer('PESSOA_ESTADO_CIVIL')->nullable();
                $table->integer('PESSOA_TIPO_SANGUE')->nullable();
                $table->integer('PESSOA_RH_MAIS')->nullable();
                $table->integer('CIDADE_ID')->nullable();
                $table->integer('CIDADE_ID_NATURAL')->nullable();
                $table->integer('PESSOA_NACIONALIDADE')->nullable();
                $table->integer('PESSOA_RACA')->nullable();
                $table->integer('PESSOA_GENERO')->nullable();
                $table->integer('PESSOA_PCD')->nullable();
            });
        }

        // FUNCIONARIO
        if (!Schema::hasTable('FUNCIONARIO')) {
            Schema::create('FUNCIONARIO', function (Blueprint $table) {
                $table->increments('FUNCIONARIO_ID');
                $table->integer('PESSOA_ID');
                $table->string('FUNCIONARIO_MATRICULA', 30)->nullable();
                $table->date('FUNCIONARIO_DATA_INICIO')->nullable();
                $table->date('FUNCIONARIO_DATA_FIM')->nullable();
                $table->integer('FUNCIONARIO_TIPO_ENTRADA')->nullable();
                $table->integer('FUNCIONARIO_TIPO_SAIDA')->nullable();
                $table->text('FUNCIONARIO_OBSERVACAO')->nullable();
                $table->date('FUNCIONARIO_DATA_CADASTRO')->nullable();
                $table->date('FUNCIONARIO_DATA_ATUALIZACAO')->nullable();
                $table->integer('USUARIO_ID')->nullable();
            });
        }

        // VINCULO (necessário para FOLHA)
        if (!Schema::hasTable('VINCULO')) {
            Schema::create('VINCULO', function (Blueprint $table) {
                $table->increments('VINCULO_ID');
                $table->string('VINCULO_NOME', 100)->nullable();
            });
        }

        // FOLHA
        if (!Schema::hasTable('FOLHA')) {
            Schema::create('FOLHA', function (Blueprint $table) {
                $table->increments('FOLHA_ID');
                $table->string('FOLHA_DESCRICAO', 200)->nullable();
                $table->integer('FOLHA_TIPO')->nullable();
                $table->integer('VINCULO_ID')->nullable();
                $table->string('FOLHA_COMPETENCIA', 10)->nullable();
                $table->integer('FOLHA_QTD_SERVIDORES')->nullable();
                $table->decimal('FOLHA_VALOR_TOTAL', 15, 2)->nullable();
                $table->string('FOLHA_ARQUIVO', 200)->nullable();
                $table->string('FOLHA_CHECKSUM', 64)->nullable();
                $table->string('FOLHA_EMAIL_NOTIFICACAO', 200)->nullable();
                $table->timestamp('FOLHA_CRIACAO')->nullable();
            });
        }

        // DETALHE_FOLHA
        if (!Schema::hasTable('DETALHE_FOLHA')) {
            Schema::create('DETALHE_FOLHA', function (Blueprint $table) {
                $table->increments('DETALHE_FOLHA_ID');
                $table->integer('FOLHA_ID');
                $table->integer('FUNCIONARIO_ID')->nullable();
                $table->integer('PENSIONISTA_ID')->nullable();
                $table->decimal('DETALHE_FOLHA_PROVENTOS', 15, 2)->nullable()->default(0);
                $table->decimal('DETALHE_FOLHA_DESCONTOS', 15, 2)->nullable()->default(0);
                $table->text('DETALHE_FOLHA_ERRO')->nullable();
            });
        }

        // EVENTO (necessário para EventosDetalhesFolhas)
        if (!Schema::hasTable('EVENTO')) {
            Schema::create('EVENTO', function (Blueprint $table) {
                $table->increments('EVENTO_ID');
                $table->string('EVENTO_NOME', 100)->nullable();
                $table->string('EVENTO_TIPO', 1)->nullable(); // P=Provento, D=Desconto
            });
        }

        // EVENTO_DETALHE_FOLHA (EventoDetalheFolha)
        if (!Schema::hasTable('EVENTO_DETALHE_FOLHA')) {
            Schema::create('EVENTO_DETALHE_FOLHA', function (Blueprint $table) {
                $table->increments('EVENTO_DETALHE_FOLHA_ID');
                $table->integer('DETALHE_FOLHA_ID');
                $table->integer('EVENTO_ID')->nullable();
                $table->decimal('EVENTO_DETALHE_FOLHA_VALOR', 15, 2)->nullable()->default(0);
                $table->decimal('EVENTO_DETALHE_FOLHA_REFERENCIA', 15, 4)->nullable()->default(0);
            });
        }
    }

    public function down(): void
    {
        Schema::dropIfExists('EVENTO_DETALHE_FOLHA');
        Schema::dropIfExists('EVENTO');
        Schema::dropIfExists('DETALHE_FOLHA');
        Schema::dropIfExists('FOLHA');
        Schema::dropIfExists('VINCULO');
        Schema::dropIfExists('FUNCIONARIO');
        Schema::dropIfExists('PESSOA');
    }
};
