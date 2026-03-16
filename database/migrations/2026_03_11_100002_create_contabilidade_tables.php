<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

// ERP Sprint 3 — Contabilidade Pública (PCASP)
return new class extends Migration {
    public function up(): void
    {
        // Plano de Contas Aplicado ao Setor Público (hierárquico)
        Schema::create('PCASP_CONTA', function (Blueprint $table) {
            $table->increments('CONTA_ID');
            $table->unsignedInteger('CONTA_PAI_ID')->nullable(); // hierarquia
            $table->string('CONTA_CODIGO', 30)->unique(); // ex: "1.1.1.1.1.00.00"
            $table->string('CONTA_NOME', 200);
            $table->enum('CONTA_NATUREZA', ['DEVEDORA', 'CREDORA']); // saldo normal
            $table->enum('CONTA_TIPO', ['SINTETICA', 'ANALITICA'])->default('ANALITICA');
            $table->enum('CONTA_GRUPO', [
                'ATIVO',
                'PASSIVO',
                'PATRIMONIO_LIQUIDO',
                'VARIACAO_PATRIMONIAL_AUMENTATIVA',
                'VARIACAO_PATRIMONIAL_DIMINUTIVA',
                'CONTROLE'
            ]);
            $table->boolean('CONTA_ATIVA')->default(true);
            $table->timestamps();
        });

        // Lançamento contábil (partidas dobradas)
        Schema::create('LANCAMENTO_CONTABIL', function (Blueprint $table) {
            $table->increments('LANCAMENTO_ID');
            $table->date('LANCAMENTO_DATA');
            $table->smallInteger('LANCAMENTO_ANO');
            $table->tinyInteger('LANCAMENTO_MES');
            $table->text('LANCAMENTO_HISTORICO');
            $table->decimal('LANCAMENTO_VALOR', 18, 2);
            // Partidas
            $table->unsignedInteger('CONTA_DEBITO_ID');
            $table->unsignedInteger('CONTA_CREDITO_ID');
            // Origem (pode ser nulo → lançamento manual)
            $table->string('ORIGEM_TIPO', 30)->nullable(); // 'EMPENHO', 'LIQUIDACAO', 'PAGAMENTO', 'MANUAL'
            $table->unsignedInteger('ORIGEM_ID')->nullable();
            $table->unsignedInteger('USUARIO_ID')->nullable();
            $table->timestamps();
            $table->foreign('CONTA_DEBITO_ID')->references('CONTA_ID')->on('PCASP_CONTA');
            $table->foreign('CONTA_CREDITO_ID')->references('CONTA_ID')->on('PCASP_CONTA');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('LANCAMENTO_CONTABIL');
        Schema::dropIfExists('PCASP_CONTA');
    }
};
