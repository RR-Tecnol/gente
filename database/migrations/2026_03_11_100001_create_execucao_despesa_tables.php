<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

// ERP Sprint 2 — Execução da Despesa (Empenho → Liquidação → Pagamento)
return new class extends Migration {
    public function up(): void
    {
        Schema::create('EMPENHO', function (Blueprint $table) {
            $table->increments('EMPENHO_ID');
            $table->unsignedInteger('LOA_ID');
            $table->string('EMPENHO_NUMERO', 30)->unique();
            $table->date('EMPENHO_DATA');
            $table->string('EMPENHO_CREDOR', 150);
            $table->string('EMPENHO_CPFCNPJ', 18)->nullable();
            $table->text('EMPENHO_HISTORICO')->nullable();
            $table->decimal('EMPENHO_VALOR', 18, 2);
            $table->enum('EMPENHO_TIPO', ['ORDINARIO', 'ESTIMATIVO', 'GLOBAL'])->default('ORDINARIO');
            $table->enum('EMPENHO_STATUS', ['EMITIDO', 'LIQUIDADO', 'PAGO', 'ANULADO'])->default('EMITIDO');
            $table->unsignedInteger('USUARIO_ID')->nullable();
            $table->timestamps();
            $table->foreign('LOA_ID')->references('LOA_ID')->on('ORCAMENTO_LOA');
        });

        Schema::create('LIQUIDACAO', function (Blueprint $table) {
            $table->increments('LIQUIDACAO_ID');
            $table->unsignedInteger('EMPENHO_ID');
            $table->date('LIQUIDACAO_DATA');
            $table->decimal('LIQUIDACAO_VALOR', 18, 2);
            $table->text('LIQUIDACAO_HISTORICO')->nullable();
            $table->string('LIQUIDACAO_NF', 60)->nullable(); // nota fiscal / recibo
            $table->unsignedInteger('USUARIO_ID')->nullable();
            $table->timestamps();
            $table->foreign('EMPENHO_ID')->references('EMPENHO_ID')->on('EMPENHO');
        });

        Schema::create('PAGAMENTO_DESPESA', function (Blueprint $table) {
            $table->increments('PAGAMENTO_ID');
            $table->unsignedInteger('LIQUIDACAO_ID');
            $table->date('PAGAMENTO_DATA');
            $table->decimal('PAGAMENTO_VALOR', 18, 2);
            $table->string('PAGAMENTO_FORMA', 30)->default('TRANSFERENCIA'); // TRANSFERENCIA, CHEQUE, etc.
            $table->string('PAGAMENTO_BANCO', 30)->nullable();
            $table->string('PAGAMENTO_CONTA', 20)->nullable();
            $table->text('PAGAMENTO_HISTORICO')->nullable();
            $table->unsignedInteger('USUARIO_ID')->nullable();
            $table->timestamps();
            $table->foreign('LIQUIDACAO_ID')->references('LIQUIDACAO_ID')->on('LIQUIDACAO');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('PAGAMENTO_DESPESA');
        Schema::dropIfExists('LIQUIDACAO');
        Schema::dropIfExists('EMPENHO');
    }
};
