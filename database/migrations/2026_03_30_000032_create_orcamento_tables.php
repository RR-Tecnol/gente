<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        // PPA — Plano Plurianual (ciclo 4 anos)
        if (!Schema::hasTable('ORCAMENTO_PPA')) {
            Schema::create('ORCAMENTO_PPA', function (Blueprint $table) {
                $table->increments('PPA_ID');
                $table->string('PPA_DESCRICAO', 200);
                $table->unsignedSmallInteger('PPA_ANO_INICIO');
                $table->unsignedSmallInteger('PPA_ANO_FIM');
                $table->boolean('PPA_ATIVO')->default(true);
                $table->timestamps();
            });
        }

        // PROGRAMA — programas orçamentários vinculados ao PPA
        if (!Schema::hasTable('ORCAMENTO_PROGRAMA')) {
            Schema::create('ORCAMENTO_PROGRAMA', function (Blueprint $table) {
                $table->increments('PROGRAMA_ID');
                $table->unsignedInteger('PPA_ID');
                $table->string('PROGRAMA_CODIGO', 20);   // ex: 0001
                $table->string('PROGRAMA_NOME', 200);
                $table->text('PROGRAMA_OBJETIVO')->nullable();
                $table->timestamps();
            });
        }

        // ACAO — ações orçamentárias vinculadas a programas
        if (!Schema::hasTable('ORCAMENTO_ACAO')) {
            Schema::create('ORCAMENTO_ACAO', function (Blueprint $table) {
                $table->increments('ACAO_ID');
                $table->unsignedInteger('PROGRAMA_ID');
                $table->string('ACAO_CODIGO', 20);        // ex: 2001
                $table->string('ACAO_NOME', 200);
                // ATIVIDADE = recorrente | PROJETO = temporário | OPERACAO_ESPECIAL = não gera produto
                $table->string('ACAO_TIPO', 30)->default('ATIVIDADE');
                $table->decimal('ACAO_VALOR_PREVISTO', 15, 2)->default(0);
                $table->timestamps();
            });
        }

        // LOA — Lei Orçamentária Anual — dotações por ação e ano
        if (!Schema::hasTable('ORCAMENTO_LOA')) {
            Schema::create('ORCAMENTO_LOA', function (Blueprint $table) {
                $table->increments('LOA_ID');
                $table->unsignedInteger('ACAO_ID');
                $table->unsignedSmallInteger('LOA_ANO');
                $table->decimal('LOA_VALOR_APROVADO', 15, 2)->default(0);    // dotação inicial (Lei)
                $table->decimal('LOA_VALOR_ADICIONADO', 15, 2)->default(0);  // créditos adicionais
                $table->decimal('LOA_VALOR_REDUZIDO', 15, 2)->default(0);    // reduções/cancelamentos
                // LOA_DOTACAO_ATUAL = APROVADO + ADICIONADO - REDUZIDO (calculado na query)
                $table->timestamps();
            });
        }
    }

    public function down(): void
    {
        // Ordem inversa das FKs
        Schema::dropIfExists('ORCAMENTO_LOA');
        Schema::dropIfExists('ORCAMENTO_ACAO');
        Schema::dropIfExists('ORCAMENTO_PROGRAMA');
        Schema::dropIfExists('ORCAMENTO_PPA');
    }
};
