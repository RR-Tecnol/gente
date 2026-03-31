<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        // RECEITA_LANCAMENTO — previsão e arrecadação de receitas por natureza
        if (!Schema::hasTable('RECEITA_LANCAMENTO')) {
            Schema::create('RECEITA_LANCAMENTO', function (Blueprint $table) {
                $table->increments('RECEITA_ID');
                $table->date('RECEITA_DATA');
                $table->unsignedSmallInteger('RECEITA_ANO');
                $table->unsignedTinyInteger('RECEITA_MES');
                // Código da natureza da receita conforme MCASP ex: 1.1.1.2.00.1.1
                $table->string('RECEITA_CODIGO_NATUREZA', 30);
                $table->string('RECEITA_DESCRICAO', 200);
                // Classificação por origem (Lei 4.320/64 + MCASP)
                $table->string('RECEITA_TIPO', 40);
                // TRIBUTARIA | CONTRIBUICOES | PATRIMONIAL
                // TRANSFERENCIAS_CORRENTES | OUTRAS_CORRENTES | CAPITAL
                $table->decimal('RECEITA_VALOR_PREVISTO', 15, 2)->default(0);
                $table->decimal('RECEITA_VALOR_ARRECADADO', 15, 2)->default(0);
                // Fonte de recursos (ex: Tesouro, FUNDEB, SUS, Convênio)
                $table->string('RECEITA_FONTE', 100)->nullable();
                // Conta bancária onde foi creditada
                $table->unsignedInteger('CONTA_ID')->nullable();
                $table->unsignedInteger('USUARIO_ID')->nullable();
                $table->timestamps();

                $table->index(['RECEITA_ANO', 'RECEITA_MES']);
                $table->index(['RECEITA_TIPO']);
            });
        }

        // RECEITA_DIVIDA_ATIVA — créditos inscritos em dívida ativa
        if (!Schema::hasTable('RECEITA_DIVIDA_ATIVA')) {
            Schema::create('RECEITA_DIVIDA_ATIVA', function (Blueprint $table) {
                $table->increments('DA_ID');
                $table->string('DA_DEVEDOR', 150);
                $table->string('DA_CPF_CNPJ', 18)->nullable();
                $table->string('DA_NUMERO_INSCRICAO', 30)->nullable();
                $table->date('DA_DATA_INSCRICAO');
                $table->string('DA_DESCRICAO', 300)->nullable();
                $table->decimal('DA_VALOR_PRINCIPAL', 15, 2)->default(0);
                $table->decimal('DA_MULTA', 15, 2)->default(0);
                $table->decimal('DA_JUROS', 15, 2)->default(0);
                $table->decimal('DA_HONORARIO', 15, 2)->default(0);
                // ATIVA | PARCELADA | QUITADA | SUSPENSA | PRESCRITA
                $table->string('DA_STATUS', 20)->default('ATIVA');
                $table->date('DA_DATA_QUITACAO')->nullable();
                $table->unsignedInteger('USUARIO_ID')->nullable();
                $table->timestamps();

                $table->index(['DA_STATUS']);
                $table->index(['DA_CPF_CNPJ']);
            });
        }
    }

    public function down(): void
    {
        Schema::dropIfExists('RECEITA_DIVIDA_ATIVA');
        Schema::dropIfExists('RECEITA_LANCAMENTO');
    }
};
