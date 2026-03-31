<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        // EMPENHO — fase 1 da execução orçamentária
        if (!Schema::hasTable('EMPENHO')) {
            Schema::create('EMPENHO', function (Blueprint $table) {
                $table->increments('EMPENHO_ID');
                $table->unsignedInteger('LOA_ID');
                $table->string('EMPENHO_NUMERO', 30);
                $table->date('EMPENHO_DATA');
                $table->string('EMPENHO_CREDOR', 150);
                $table->string('EMPENHO_CPFCNPJ', 18)->nullable();
                $table->text('EMPENHO_HISTORICO')->nullable();
                $table->decimal('EMPENHO_VALOR', 15, 2);
                // ORDINARIO = valor exato | ESTIMATIVO = valor previsto | GLOBAL = contrato
                $table->string('EMPENHO_TIPO', 20)->default('ORDINARIO');
                // EMITIDO → LIQUIDADO → PAGO | ANULADO
                $table->string('EMPENHO_STATUS', 20)->default('EMITIDO');
                $table->unsignedInteger('USUARIO_ID')->nullable();
                $table->timestamps();
            });
        }

        // LIQUIDACAO — fase 2: confirmação da entrega do bem/serviço
        if (!Schema::hasTable('LIQUIDACAO')) {
            Schema::create('LIQUIDACAO', function (Blueprint $table) {
                $table->increments('LIQUIDACAO_ID');
                $table->unsignedInteger('EMPENHO_ID');
                $table->date('LIQUIDACAO_DATA');
                $table->decimal('LIQUIDACAO_VALOR', 15, 2);
                $table->text('LIQUIDACAO_HISTORICO')->nullable();
                $table->string('LIQUIDACAO_NF', 50)->nullable(); // número da nota fiscal
                $table->unsignedInteger('USUARIO_ID')->nullable();
                $table->timestamps();
            });
        }

        // PAGAMENTO_DESPESA — fase 3: efetivação do pagamento
        if (!Schema::hasTable('PAGAMENTO_DESPESA')) {
            Schema::create('PAGAMENTO_DESPESA', function (Blueprint $table) {
                $table->increments('PAGAMENTO_ID');
                $table->unsignedInteger('LIQUIDACAO_ID');
                $table->date('PAGAMENTO_DATA');
                $table->decimal('PAGAMENTO_VALOR', 15, 2);
                // TRANSFERENCIA | PIX | CHEQUE | DINHEIRO
                $table->string('PAGAMENTO_FORMA', 30)->default('TRANSFERENCIA');
                $table->string('PAGAMENTO_BANCO', 10)->nullable();
                $table->string('PAGAMENTO_CONTA', 30)->nullable();
                $table->text('PAGAMENTO_HISTORICO')->nullable();
                $table->unsignedInteger('USUARIO_ID')->nullable();
                $table->timestamps();
            });
        }
    }

    public function down(): void
    {
        Schema::dropIfExists('PAGAMENTO_DESPESA');
        Schema::dropIfExists('LIQUIDACAO');
        Schema::dropIfExists('EMPENHO');
    }
};
