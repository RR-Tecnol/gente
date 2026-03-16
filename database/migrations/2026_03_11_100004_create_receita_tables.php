<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

// ERP Sprint 5 — Receita Municipal
return new class extends Migration {
    public function up(): void
    {
        Schema::create('RECEITA_LANCAMENTO', function (Blueprint $table) {
            $table->increments('RECEITA_ID');
            $table->date('RECEITA_DATA');
            $table->smallInteger('RECEITA_ANO');
            $table->tinyInteger('RECEITA_MES');
            $table->string('RECEITA_CODIGO_NATUREZA', 30); // ex: "1.1.1.00.00" IRRF retido
            $table->string('RECEITA_DESCRICAO', 200);
            $table->enum('RECEITA_TIPO', [
                'TRIBUTARIA',
                'CONTRIBUICOES',
                'PATRIMONIAL',
                'TRANSFERENCIAS_CORRENTES',
                'OUTRAS_CORRENTES',
                'CAPITAL'
            ]);
            $table->decimal('RECEITA_VALOR_PREVISTO', 18, 2)->default(0);
            $table->decimal('RECEITA_VALOR_ARRECADADO', 18, 2)->default(0);
            $table->string('RECEITA_FONTE', 60)->nullable();
            $table->unsignedInteger('CONTA_ID')->nullable(); // conta bancária de destino
            $table->unsignedInteger('USUARIO_ID')->nullable();
            $table->timestamps();
        });

        Schema::create('RECEITA_DIVIDA_ATIVA', function (Blueprint $table) {
            $table->increments('DA_ID');
            $table->string('DA_DEVEDOR', 150);
            $table->string('DA_CPFCNPJ', 18)->nullable();
            $table->string('DA_INSCRICAO', 30)->unique();
            $table->date('DA_DATA_INSCRICAO');
            $table->decimal('DA_VALOR_PRINCIPAL', 18, 2);
            $table->decimal('DA_MULTA', 18, 2)->default(0);
            $table->decimal('DA_JUROS', 18, 2)->default(0);
            $table->decimal('DA_HONORARIO', 18, 2)->default(0);
            $table->enum('DA_STATUS', ['ATIVA', 'PARCELADA', 'QUITADA', 'AJUIZADA', 'CANCELADA'])->default('ATIVA');
            $table->text('DA_HISTORICO')->nullable();
            $table->timestamps();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('RECEITA_DIVIDA_ATIVA');
        Schema::dropIfExists('RECEITA_LANCAMENTO');
    }
};
