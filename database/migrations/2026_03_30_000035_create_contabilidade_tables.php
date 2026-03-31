<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        // PCASP_CONTA — Plano de Contas Aplicado ao Setor Público
        if (!Schema::hasTable('PCASP_CONTA')) {
            Schema::create('PCASP_CONTA', function (Blueprint $table) {
                $table->increments('CONTA_ID');
                $table->string('CONTA_CODIGO', 20)->unique(); // ex: 3.1.1.1.01
                $table->string('CONTA_NOME', 200);
                // DEVEDORA = saldo normal a débito | CREDORA = saldo normal a crédito
                $table->string('CONTA_NATUREZA', 10)->default('DEVEDORA');
                // ATIVO|PASSIVO|PL|VARIACAO|CONTROLE|COMPENSACAO
                $table->string('CONTA_GRUPO', 20)->nullable();
                $table->unsignedInteger('CONTA_PAI_ID')->nullable();
                $table->boolean('CONTA_ATIVA')->default(true);
                $table->timestamps();
            });
        }

        // LANCAMENTO_CONTABIL — lançamentos em partida dobrada simplificada
        // Modelo: 1 débito + 1 crédito por lançamento (adequado para folha e empenhos)
        if (!Schema::hasTable('LANCAMENTO_CONTABIL')) {
            Schema::create('LANCAMENTO_CONTABIL', function (Blueprint $table) {
                $table->increments('LANCAMENTO_ID');
                $table->date('LANCAMENTO_DATA');
                $table->unsignedSmallInteger('LANCAMENTO_ANO');
                $table->unsignedTinyInteger('LANCAMENTO_MES');
                $table->string('LANCAMENTO_HISTORICO', 500);
                $table->decimal('LANCAMENTO_VALOR', 15, 2);
                $table->unsignedInteger('CONTA_DEBITO_ID');   // FK → PCASP_CONTA
                $table->unsignedInteger('CONTA_CREDITO_ID');  // FK → PCASP_CONTA
                // Rastreabilidade: de onde veio o lançamento
                $table->string('ORIGEM_TIPO', 30)->nullable(); // FOLHA_PAGAMENTO|EMPENHO|MANUAL
                $table->unsignedInteger('ORIGEM_ID')->nullable();
                $table->unsignedInteger('USUARIO_ID')->nullable();
                $table->timestamps();

                $table->index(['LANCAMENTO_ANO', 'LANCAMENTO_MES']);
                $table->index(['ORIGEM_TIPO', 'ORIGEM_ID']);
            });
        }
    }

    public function down(): void
    {
        Schema::dropIfExists('LANCAMENTO_CONTABIL');
        Schema::dropIfExists('PCASP_CONTA');
    }
};
