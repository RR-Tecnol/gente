<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

// ERP Sprint 6 — Controle Externo (SICONFI, SAGRES avançado, RGF, RREO)
return new class extends Migration {
    public function up(): void
    {
        // Envios ao SAGRES/TCE-MA
        Schema::create('SICONFI_ENVIO', function (Blueprint $table) {
            $table->increments('ENVIO_ID');
            $table->enum('ENVIO_TIPO', ['SAGRES', 'SICONFI_RREO', 'SICONFI_RGF', 'DCA']);
            $table->smallInteger('ENVIO_ANO');
            $table->tinyInteger('ENVIO_MES')->nullable();   // para RREO/SAGRES
            $table->tinyInteger('ENVIO_BIMESTRE')->nullable(); // para RGF
            $table->enum('ENVIO_STATUS', ['GERADO', 'ENVIADO', 'ACEITO', 'REJEITADO', 'CANCELADO'])->default('GERADO');
            $table->string('ENVIO_ARQUIVO', 200)->nullable(); // nome do arquivo XML gerado
            $table->text('ENVIO_OBSERVACAO')->nullable();
            $table->unsignedInteger('USUARIO_ID')->nullable();
            $table->timestamp('ENVIO_DT_GERACAO')->nullable();
            $table->timestamp('ENVIO_DT_TRANSMISSAO')->nullable();
            $table->timestamps();
        });

        // Dados para o RGF (Relatório de Gestão Fiscal)
        Schema::create('RGF_DADOS', function (Blueprint $table) {
            $table->increments('RGF_ID');
            $table->smallInteger('RGF_ANO');
            $table->tinyInteger('RGF_QUADRIMESTRE'); // 1, 2 ou 3
            $table->decimal('RGF_RCL', 18, 2)->default(0);            // Receita Corrente Líquida
            $table->decimal('RGF_DESP_PESSOAL_TOTAL', 18, 2)->default(0);
            $table->decimal('RGF_DESP_PESSOAL_LIQUIDA', 18, 2)->default(0);
            $table->decimal('RGF_LIMITE_PRUDENCIAL', 18, 2)->default(0); // 95% do limite
            $table->decimal('RGF_LIMITE_LEGAL', 18, 2)->default(0);      // 54% do RCL (municípios)
            $table->decimal('RGF_DIVIDA_CONSOLIDADA', 18, 2)->default(0);
            $table->decimal('RGF_GARANTIAS', 18, 2)->default(0);
            $table->decimal('RGF_OPERACOES_CREDITO', 18, 2)->default(0);
            $table->timestamps();
        });

        // Dados para o RREO (Relatório Resumido da Execução Orçamentária)
        Schema::create('RREO_DADOS', function (Blueprint $table) {
            $table->increments('RREO_ID');
            $table->smallInteger('RREO_ANO');
            $table->tinyInteger('RREO_BIMESTRE'); // 1 a 6
            $table->decimal('RREO_RECEITA_PREVISTA', 18, 2)->default(0);
            $table->decimal('RREO_RECEITA_ARRECADADA', 18, 2)->default(0);
            $table->decimal('RREO_DESP_DOTACAO_INICIAL', 18, 2)->default(0);
            $table->decimal('RREO_DESP_DOTACAO_ATUALIZADA', 18, 2)->default(0);
            $table->decimal('RREO_DESP_EMPENHADA', 18, 2)->default(0);
            $table->decimal('RREO_DESP_LIQUIDADA', 18, 2)->default(0);
            $table->decimal('RREO_DESP_PAGA', 18, 2)->default(0);
            $table->timestamps();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('RREO_DADOS');
        Schema::dropIfExists('RGF_DADOS');
        Schema::dropIfExists('SICONFI_ENVIO');
    }
};
