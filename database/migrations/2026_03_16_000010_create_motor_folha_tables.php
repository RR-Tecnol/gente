<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration {
    public function up(): void
    {
        // ══════════════════════════════════════════════════════════════════════
        // NOVA: ADICIONAL_SERVIDOR — Camada 2 (adicionais permanentes por servidor)
        // ══════════════════════════════════════════════════════════════════════
        if (!Schema::hasTable('ADICIONAL_SERVIDOR')) {
            Schema::create('ADICIONAL_SERVIDOR', function (Blueprint $table) {
                $table->increments('ADICIONAL_ID');
                $table->unsignedInteger('FUNCIONARIO_ID');
                $table->unsignedInteger('RUBRICA_ID');
                $table->string('ADICIONAL_TIPO', 20);             // fixo|percentual|percentual_sm
                $table->decimal('ADICIONAL_VALOR', 12, 2)->default(0);
                $table->string('ADICIONAL_BASE', 30)->nullable(); // null|salario_base|salario_minimo
                $table->boolean('ADICIONAL_INCIDE_PREV')->default(true);
                $table->boolean('ADICIONAL_INCIDE_IRRF')->default(true);
                $table->boolean('ADICIONAL_INCIDE_FGTS')->default(false);
                $table->date('ADICIONAL_VIGENCIA_INICIO');
                $table->date('ADICIONAL_VIGENCIA_FIM')->nullable(); // null = permanente
                $table->string('ADICIONAL_ATO_ADM', 100)->nullable();
                $table->text('ADICIONAL_OBS')->nullable();
                $table->timestamps();
            });
        }

        // ══════════════════════════════════════════════════════════════════════
        // NOVA: LANCAMENTO_FOLHA — Camada 3 (variáveis mensais)
        // ══════════════════════════════════════════════════════════════════════
        if (!Schema::hasTable('LANCAMENTO_FOLHA')) {
            Schema::create('LANCAMENTO_FOLHA', function (Blueprint $table) {
                $table->increments('LANCAMENTO_ID');
                $table->unsignedInteger('FUNCIONARIO_ID');
                $table->unsignedInteger('FOLHA_ID');
                $table->unsignedInteger('RUBRICA_ID');
                $table->char('LANCAMENTO_TIPO', 1);               // P=provento D=desconto
                $table->decimal('LANCAMENTO_QTDE', 8, 2)->default(1);
                $table->decimal('LANCAMENTO_VALOR_UNIT', 12, 2);
                $table->decimal('LANCAMENTO_VALOR_TOTAL', 12, 2); // qtde × unit
                $table->boolean('LANCAMENTO_INCIDE_PREV')->default(true);
                $table->boolean('LANCAMENTO_INCIDE_IRRF')->default(true);
                $table->string('LANCAMENTO_ORIGEM', 20)->default('manual'); // manual|ponto|judicial|consignacao
                $table->text('LANCAMENTO_OBS')->nullable();
                $table->timestamps();
            });
        }

        // ══════════════════════════════════════════════════════════════════════
        // ALTER: RUBRICA — campos de controle do motor
        // ══════════════════════════════════════════════════════════════════════
        if (Schema::hasTable('RUBRICA')) {
            Schema::table('RUBRICA', function (Blueprint $table) {
                if (!Schema::hasColumn('RUBRICA', 'RUBRICA_CAMADA'))
                    $table->integer('RUBRICA_CAMADA')->default(1)->after('RUBRICA_TIPO'); // 1|2|3
                if (!Schema::hasColumn('RUBRICA', 'RUBRICA_CALCULO'))
                    $table->string('RUBRICA_CALCULO', 30)->nullable()->after('RUBRICA_CAMADA');
                // fixo|tabela_salarial|percentual_base|percentual_sm|irrf|inss_rgps|inss_rpps
                if (!Schema::hasColumn('RUBRICA', 'RUBRICA_INCIDE_FGTS'))
                    $table->boolean('RUBRICA_INCIDE_FGTS')->default(false)->after('RUBRICA_CALCULO');
                if (!Schema::hasColumn('RUBRICA', 'RUBRICA_SAGRES_COD'))
                    $table->string('RUBRICA_SAGRES_COD', 10)->nullable()->after('RUBRICA_INCIDE_FGTS');
                if (!Schema::hasColumn('RUBRICA', 'RUBRICA_ORDEM'))
                    $table->integer('RUBRICA_ORDEM')->default(0)->after('RUBRICA_SAGRES_COD');
            });
        }

        // ══════════════════════════════════════════════════════════════════════
        // ALTER: ITEM_FOLHA — detalhamento para holerite/TCE
        // ══════════════════════════════════════════════════════════════════════
        if (Schema::hasTable('ITEM_FOLHA')) {
            Schema::table('ITEM_FOLHA', function (Blueprint $table) {
                if (!Schema::hasColumn('ITEM_FOLHA', 'ITEM_CAMADA'))
                    $table->integer('ITEM_CAMADA')->default(1)->nullable();
                if (!Schema::hasColumn('ITEM_FOLHA', 'ITEM_QTDE'))
                    $table->decimal('ITEM_QTDE', 8, 2)->default(1)->nullable();
                if (!Schema::hasColumn('ITEM_FOLHA', 'ITEM_VALOR_UNIT'))
                    $table->decimal('ITEM_VALOR_UNIT', 12, 2)->nullable();
                if (!Schema::hasColumn('ITEM_FOLHA', 'ITEM_INCIDE_PREV'))
                    $table->boolean('ITEM_INCIDE_PREV')->default(true)->nullable();
                if (!Schema::hasColumn('ITEM_FOLHA', 'ITEM_INCIDE_IRRF'))
                    $table->boolean('ITEM_INCIDE_IRRF')->default(true)->nullable();
            });
        }

        // ══════════════════════════════════════════════════════════════════════
        // ALTER: VINCULO — flags do motor de cálculo
        // ══════════════════════════════════════════════════════════════════════
        if (Schema::hasTable('VINCULO')) {
            Schema::table('VINCULO', function (Blueprint $table) {
                if (!Schema::hasColumn('VINCULO', 'VINCULO_TIPO'))
                    $table->string('VINCULO_TIPO', 30)->default('efetivo')->nullable();
                // efetivo|servico_prestado|comissao_puro|efetivo_cc_m1|efetivo_cc_m2|funcao_confianca|pss
                if (!Schema::hasColumn('VINCULO', 'VINCULO_REGIME'))
                    $table->string('VINCULO_REGIME', 10)->default('RPPS')->nullable(); // RPPS|RGPS
                if (!Schema::hasColumn('VINCULO', 'VINCULO_FGTS'))
                    $table->boolean('VINCULO_FGTS')->default(false)->nullable();
                if (!Schema::hasColumn('VINCULO', 'VINCULO_INSS'))
                    $table->boolean('VINCULO_INSS')->default(true)->nullable();
                if (!Schema::hasColumn('VINCULO', 'VINCULO_IRRF'))
                    $table->boolean('VINCULO_IRRF')->default(true)->nullable();
                if (!Schema::hasColumn('VINCULO', 'VINCULO_ANUENIO_PCT'))
                    $table->decimal('VINCULO_ANUENIO_PCT', 5, 2)->default(1.00)->nullable();
            });
        }

        // ══════════════════════════════════════════════════════════════════════
        // ALTER: DETALHE_FOLHA — expandir para base de cálculo e piso SM
        // ══════════════════════════════════════════════════════════════════════
        if (Schema::hasTable('DETALHE_FOLHA')) {
            Schema::table('DETALHE_FOLHA', function (Blueprint $table) {
                if (!Schema::hasColumn('DETALHE_FOLHA', 'DETALHE_BASE_PREV'))
                    $table->decimal('DETALHE_BASE_PREV', 12, 2)->nullable();
                if (!Schema::hasColumn('DETALHE_FOLHA', 'DETALHE_BASE_IRRF'))
                    $table->decimal('DETALHE_BASE_IRRF', 12, 2)->nullable();
                if (!Schema::hasColumn('DETALHE_FOLHA', 'DETALHE_DESC_PREV'))
                    $table->decimal('DETALHE_DESC_PREV', 12, 2)->default(0)->nullable();
                if (!Schema::hasColumn('DETALHE_FOLHA', 'DETALHE_DESC_IRRF'))
                    $table->decimal('DETALHE_DESC_IRRF', 12, 2)->default(0)->nullable();
                if (!Schema::hasColumn('DETALHE_FOLHA', 'DETALHE_DESC_OUTROS'))
                    $table->decimal('DETALHE_DESC_OUTROS', 12, 2)->default(0)->nullable();
                if (!Schema::hasColumn('DETALHE_FOLHA', 'DETALHE_VINCULO_TIPO'))
                    $table->string('DETALHE_VINCULO_TIPO', 30)->nullable(); // snapshot
                if (!Schema::hasColumn('DETALHE_FOLHA', 'DETALHE_COMPLEMENTO_SM'))
                    $table->decimal('DETALHE_COMPLEMENTO_SM', 12, 2)->default(0)->nullable();
            });
        }

        // ══════════════════════════════════════════════════════════════════════
        // ALTER: PESSOA — dependentes IRRF
        // ══════════════════════════════════════════════════════════════════════
        if (Schema::hasTable('PESSOA')) {
            Schema::table('PESSOA', function (Blueprint $table) {
                if (!Schema::hasColumn('PESSOA', 'PESSOA_DEPENDENTES_IRRF'))
                    $table->integer('PESSOA_DEPENDENTES_IRRF')->default(0)->nullable();
            });
        }
    }

    public function down(): void
    {
        Schema::dropIfExists('LANCAMENTO_FOLHA');
        Schema::dropIfExists('ADICIONAL_SERVIDOR');
        // Não reverter ALTERs para evitar perda de dados
    }
};
