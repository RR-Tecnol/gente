<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Cria as tabelas de Consignações em Folha.
 *
 * CONSIG_CONVENIO — convênios/credores cadastrados (bancos, sindicatos, cooperativas)
 * CONSIG_CONTRATO — contratos de empréstimo/consignação por servidor
 * CONSIG_PARCELA  — histórico de descontos mês a mês
 *
 * Regras LRF:
 *   - Teto de consignação: 35% da remuneração líquida (30% empréstimos + 5% outros)
 *   - O limite de 5% é reservado para despesas de natureza alimentícia (cartão consignado)
 */
return new class extends Migration {
    public function up(): void
    {
        // ── CONSIG_CONVENIO ────────────────────────────────────────────────────
        if (!Schema::hasTable('CONSIG_CONVENIO')) {
            Schema::create('CONSIG_CONVENIO', function (Blueprint $table) {
                $table->increments('CONVENIO_ID');
                $table->string('CONVENIO_NOME', 150);
                // BANCO | SINDICATO | COOPERATIVA | CARTAO | OUTROS
                $table->string('CONVENIO_TIPO', 30)->default('BANCO');
                $table->string('BANCO_NOME', 100)->nullable();
                $table->string('BANCO_CODIGO', 10)->nullable();   // código COMPE
                $table->decimal('TAXA_JUROS_MAX', 5, 2)->default(0);  // % ao mês
                $table->boolean('ATIVO')->default(true);
                $table->timestamps();
            });

            // Seed: convênios padrão
            \Illuminate\Support\Facades\DB::table('CONSIG_CONVENIO')->insert([
                ['CONVENIO_NOME' => 'Banco do Brasil', 'CONVENIO_TIPO' => 'BANCO', 'BANCO_CODIGO' => '001', 'TAXA_JUROS_MAX' => 2.14, 'ATIVO' => true, 'created_at' => now(), 'updated_at' => now()],
                ['CONVENIO_NOME' => 'Caixa Econômica Federal', 'CONVENIO_TIPO' => 'BANCO', 'BANCO_CODIGO' => '104', 'TAXA_JUROS_MAX' => 2.14, 'ATIVO' => true, 'created_at' => now(), 'updated_at' => now()],
                ['CONVENIO_NOME' => 'Bradesco Consig', 'CONVENIO_TIPO' => 'BANCO', 'BANCO_CODIGO' => '237', 'TAXA_JUROS_MAX' => 2.14, 'ATIVO' => true, 'created_at' => now(), 'updated_at' => now()],
                ['CONVENIO_NOME' => 'Sindicato dos Servidores', 'CONVENIO_TIPO' => 'SINDICATO', 'BANCO_CODIGO' => null, 'TAXA_JUROS_MAX' => 0, 'ATIVO' => true, 'created_at' => now(), 'updated_at' => now()],
                ['CONVENIO_NOME' => 'Cooperativa Municipal', 'CONVENIO_TIPO' => 'COOPERATIVA', 'BANCO_CODIGO' => null, 'TAXA_JUROS_MAX' => 1.80, 'ATIVO' => true, 'created_at' => now(), 'updated_at' => now()],
                ['CONVENIO_NOME' => 'Cartão Consignado', 'CONVENIO_TIPO' => 'CARTAO', 'BANCO_CODIGO' => null, 'TAXA_JUROS_MAX' => 3.06, 'ATIVO' => true, 'created_at' => now(), 'updated_at' => now()],
            ]);
        }

        // ── CONSIG_CONTRATO ────────────────────────────────────────────────────
        if (!Schema::hasTable('CONSIG_CONTRATO')) {
            Schema::create('CONSIG_CONTRATO', function (Blueprint $table) {
                $table->increments('CONTRATO_ID');
                $table->unsignedInteger('FUNCIONARIO_ID');
                $table->unsignedInteger('CONVENIO_ID');
                $table->string('NUMERO_CONTRATO', 50)->nullable();
                $table->date('DATA_INICIO');
                $table->date('DATA_FIM')->nullable();
                $table->decimal('VALOR_TOTAL', 15, 2);          // principal
                $table->decimal('VALOR_PARCELA', 15, 2);        // desconto mensal
                $table->integer('PRAZO_MESES');                  // total de parcelas
                $table->integer('PARCELAS_PAGAS')->default(0);
                $table->decimal('SALDO_DEVEDOR', 15, 2);
                $table->decimal('TAXA_JUROS', 5, 2)->default(0); // % ao mês
                // ATIVO | QUITADO | SUSPENSO | CANCELADO
                $table->string('STATUS', 20)->default('ATIVO');
                $table->text('OBSERVACAO')->nullable();
                $table->unsignedInteger('CADASTRADO_POR')->nullable();
                $table->timestamps();
            });
        }

        // ── CONSIG_PARCELA ─────────────────────────────────────────────────────
        if (!Schema::hasTable('CONSIG_PARCELA')) {
            Schema::create('CONSIG_PARCELA', function (Blueprint $table) {
                $table->increments('PARCELA_ID');
                $table->unsignedInteger('CONTRATO_ID');
                $table->string('COMPETENCIA', 7);               // AAAA-MM
                $table->integer('NUMERO_PARCELA');               // 1, 2, 3 ...
                $table->decimal('VALOR_PARCELA', 15, 2);
                $table->decimal('VALOR_PAGO', 15, 2)->nullable();
                // PENDENTE | DESCONTADA | SUSPENSA
                $table->string('STATUS', 20)->default('PENDENTE');
                $table->timestamps();
            });
        }
    }

    public function down(): void
    {
        Schema::dropIfExists('CONSIG_PARCELA');
        Schema::dropIfExists('CONSIG_CONTRATO');
        Schema::dropIfExists('CONSIG_CONVENIO');
    }
};
