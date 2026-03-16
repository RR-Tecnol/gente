<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

// ERP Sprint 1 — Orçamento Público (PPA/LOA)
return new class extends Migration {
    public function up(): void
    {
        // Plano Plurianual
        Schema::create('ORCAMENTO_PPA', function (Blueprint $table) {
            $table->increments('PPA_ID');
            $table->string('PPA_DESCRICAO', 200);
            $table->smallInteger('PPA_ANO_INICIO');
            $table->smallInteger('PPA_ANO_FIM');
            $table->enum('PPA_STATUS', ['ATIVO', 'INATIVO'])->default('ATIVO');
            $table->timestamps();
        });

        // Programa de governo (dentro de um PPA)
        Schema::create('ORCAMENTO_PROGRAMA', function (Blueprint $table) {
            $table->increments('PROGRAMA_ID');
            $table->unsignedInteger('PPA_ID');
            $table->string('PROGRAMA_CODIGO', 20);
            $table->string('PROGRAMA_NOME', 200);
            $table->text('PROGRAMA_OBJETIVO')->nullable();
            $table->decimal('PROGRAMA_VALOR_TOTAL', 18, 2)->default(0);
            $table->timestamps();
            $table->foreign('PPA_ID')->references('PPA_ID')->on('ORCAMENTO_PPA');
        });

        // Ação orçamentária (dentro de um programa)
        Schema::create('ORCAMENTO_ACAO', function (Blueprint $table) {
            $table->increments('ACAO_ID');
            $table->unsignedInteger('PROGRAMA_ID');
            $table->string('ACAO_CODIGO', 20);
            $table->string('ACAO_NOME', 200);
            $table->enum('ACAO_TIPO', ['ATIVIDADE', 'PROJETO', 'OPERACAO_ESPECIAL'])->default('ATIVIDADE');
            $table->decimal('ACAO_VALOR_PREVISTO', 18, 2)->default(0);
            $table->timestamps();
            $table->foreign('PROGRAMA_ID')->references('PROGRAMA_ID')->on('ORCAMENTO_PROGRAMA');
        });

        // Lei Orçamentária Anual
        Schema::create('ORCAMENTO_LOA', function (Blueprint $table) {
            $table->increments('LOA_ID');
            $table->unsignedInteger('ACAO_ID');
            $table->smallInteger('LOA_ANO');
            $table->string('LOA_FONTE_RECURSO', 60)->nullable(); // ex: "TESOURO MUNICIPAL", "CONVÊNIO FEDERAL"
            $table->string('LOA_NATUREZA_DESPESA', 30)->nullable(); // ex: "3.1.90.11.00" — Pessoal
            $table->decimal('LOA_VALOR_APROVADO', 18, 2)->default(0);
            $table->decimal('LOA_VALOR_ADICIONADO', 18, 2)->default(0);  // créditos adicionais
            $table->decimal('LOA_VALOR_REDUZIDO', 18, 2)->default(0);
            $table->timestamps();
            $table->foreign('ACAO_ID')->references('ACAO_ID')->on('ORCAMENTO_ACAO');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('ORCAMENTO_LOA');
        Schema::dropIfExists('ORCAMENTO_ACAO');
        Schema::dropIfExists('ORCAMENTO_PROGRAMA');
        Schema::dropIfExists('ORCAMENTO_PPA');
    }
};
