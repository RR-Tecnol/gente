<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        // CONTA_BANCARIA — contas correntes/investimento do município
        if (!Schema::hasTable('CONTA_BANCARIA')) {
            Schema::create('CONTA_BANCARIA', function (Blueprint $table) {
                $table->increments('CONTA_ID');
                $table->string('CONTA_DESCRICAO', 150);         // ex: Conta Movimento BB
                $table->string('CONTA_BANCO', 3)->nullable();   // código COMPE ex: 001
                $table->string('CONTA_BANCO_NOME', 100)->nullable();
                $table->string('CONTA_AGENCIA', 10)->nullable();
                $table->string('CONTA_NUMERO', 20)->nullable();
                // CORRENTE | POUPANCA | INVESTIMENTO | VINCULADA
                $table->string('CONTA_TIPO', 20)->default('CORRENTE');
                $table->decimal('CONTA_SALDO_INICIAL', 15, 2)->default(0);
                $table->date('CONTA_SALDO_DATA')->nullable();   // data de referência do saldo inicial
                $table->boolean('CONTA_ATIVA')->default(true);
                $table->timestamps();
            });
        }

        // MOVIMENTACAO_BANCARIA — lançamentos de crédito e débito por conta
        if (!Schema::hasTable('MOVIMENTACAO_BANCARIA')) {
            Schema::create('MOVIMENTACAO_BANCARIA', function (Blueprint $table) {
                $table->increments('MOV_ID');
                $table->unsignedInteger('CONTA_ID');
                $table->date('MOV_DATA');
                // CREDITO = entrada de recursos | DEBITO = saída de recursos
                $table->string('MOV_TIPO', 10);
                $table->decimal('MOV_VALOR', 15, 2);
                $table->string('MOV_HISTORICO', 300)->nullable();
                // Rastreabilidade: de onde veio o movimento
                $table->string('MOV_ORIGEM', 30)->nullable(); // FOLHA|EMPENHO|RECEITA|MANUAL
                $table->unsignedInteger('MOV_ORIGEM_ID')->nullable();
                // PENDENTE → CONCILIADO | CANCELADO
                $table->string('MOV_STATUS', 20)->default('PENDENTE');
                $table->unsignedInteger('USUARIO_ID')->nullable();
                $table->timestamps();

                $table->index(['CONTA_ID', 'MOV_DATA']);
                $table->index(['MOV_STATUS']);
            });
        }
    }

    public function down(): void
    {
        Schema::dropIfExists('MOVIMENTACAO_BANCARIA');
        Schema::dropIfExists('CONTA_BANCARIA');
    }
};
