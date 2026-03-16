<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

// ERP Sprint 4 — Tesouraria
return new class extends Migration {
    public function up(): void
    {
        Schema::create('CONTA_BANCARIA', function (Blueprint $table) {
            $table->increments('CONTA_ID');
            $table->string('CONTA_BANCO', 60);      // ex: "Caixa Econômica Federal"
            $table->string('CONTA_AGENCIA', 20);
            $table->string('CONTA_NUMERO', 30);
            $table->string('CONTA_DESCRICAO', 120);
            $table->enum('CONTA_TIPO', ['CORRENTE', 'POUPANCA', 'VINCULADA'])->default('CORRENTE');
            $table->boolean('CONTA_ATIVA')->default(true);
            $table->decimal('CONTA_SALDO_INICIAL', 18, 2)->default(0);
            $table->timestamps();
        });

        Schema::create('MOVIMENTACAO_BANCARIA', function (Blueprint $table) {
            $table->increments('MOV_ID');
            $table->unsignedInteger('CONTA_ID');
            $table->date('MOV_DATA');
            $table->enum('MOV_TIPO', ['CREDITO', 'DEBITO']);
            $table->decimal('MOV_VALOR', 18, 2);
            $table->string('MOV_HISTORICO', 250)->nullable();
            $table->string('MOV_DOCUMENTO', 60)->nullable(); // nº cheque, OB, etc.
            $table->enum('MOV_STATUS', ['PENDENTE', 'CONCILIADO', 'CANCELADO'])->default('PENDENTE');
            // Referência cruzada (qual pagamento/receita gerou isso)
            $table->string('MOV_ORIGEM_TIPO', 30)->nullable(); // 'PAGAMENTO_DESPESA', 'RECEITA', 'TRANSFERENCIA'
            $table->unsignedInteger('MOV_ORIGEM_ID')->nullable();
            $table->unsignedInteger('USUARIO_ID')->nullable();
            $table->timestamps();
            $table->foreign('CONTA_ID')->references('CONTA_ID')->on('CONTA_BANCARIA');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('MOVIMENTACAO_BANCARIA');
        Schema::dropIfExists('CONTA_BANCARIA');
    }
};
