<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Cria a tabela RESCISAO_CALCULO.
 * Armazena o cálculo completo de verbas rescisórias de cada servidor desligado,
 * com rastreamento de status até o pagamento final.
 */
return new class extends Migration {
    public function up(): void
    {
        if (!Schema::hasTable('RESCISAO_CALCULO')) {
            Schema::create('RESCISAO_CALCULO', function (Blueprint $table) {
                $table->increments('RESCISAO_ID');
                $table->unsignedInteger('FUNCIONARIO_ID');
                $table->date('DATA_EXONERACAO');
                // EXONERACAO | DEMISSAO | APOSENTADORIA | FALECIMENTO | TRANSFERENCIA
                $table->string('MOTIVO_SAIDA', 50);
                $table->string('PORTARIA_NUM', 100)->nullable();
                $table->datetime('DATA_CALCULO')->nullable();
                $table->unsignedInteger('CALCULADO_POR')->nullable();   // USUARIO_ID

                // Situação do cálculo
                // RASCUNHO → VALIDADO → INCLUIDO_FOLHA → PAGO
                $table->string('STATUS', 20)->default('RASCUNHO');

                // ── Verbas calculadas ──────────────────────────────────────────
                // Saldo dos dias não pagos no mês da saída
                $table->decimal('SALDO_SALARIO', 15, 2)->default(0);
                // Férias proporcionais (dias corridos no período aquisitivo ÷ 30 × salário)
                $table->decimal('FERIAS_PROP', 15, 2)->default(0);
                // 1/3 constitucional sobre férias proporcionais
                $table->decimal('FERIAS_PROP_TERCIO', 15, 2)->default(0);
                // Períodos aquisitivos completos não gozados
                $table->decimal('FERIAS_VENCIDAS', 15, 2)->default(0);
                // 1/3 constitucional sobre férias vencidas
                $table->decimal('FERIAS_VENCIDAS_TERCIO', 15, 2)->default(0);
                // Meses trabalhados no ano ÷ 12 × salário
                $table->decimal('DECIMO_TERCEIRO_PROP', 15, 2)->default(0);
                // Licença-prêmio não gozada (se estatuto local prever)
                $table->decimal('LICENCA_PREMIO', 15, 2)->default(0);
                // Indenização por redução de quadro (extinção de cargo)
                $table->decimal('INDENIZACAO_CARGO', 15, 2)->default(0);
                // Outros eventuais
                $table->decimal('OUTROS', 15, 2)->default(0);

                // ── Totais ────────────────────────────────────────────────────
                $table->decimal('TOTAL_BRUTO', 15, 2)->default(0);
                $table->decimal('DESCONTO_IRRF', 15, 2)->default(0);
                $table->decimal('DESCONTO_OUTROS', 15, 2)->default(0);  // consignações, etc.
                $table->decimal('TOTAL_LIQUIDO', 15, 2)->default(0);

                // ── Referência à folha (quando incluído) ──────────────────────
                $table->unsignedInteger('FOLHA_ID')->nullable();
                $table->date('DATA_PAGAMENTO')->nullable();

                // Regime (para definir se tem FGTS/multa ou apenas verbas estatutárias)
                // RPPS = estatutário | RGPS = CLT/PSS
                $table->string('REGIME_PREV', 10)->default('RPPS');
                // Valor do FGTS + multa 40% (apenas RGPS/CLT)
                $table->decimal('FGTS_MULTA', 15, 2)->default(0);

                $table->text('OBSERVACOES')->nullable();
                $table->timestamps();
            });
        }
    }

    public function down(): void
    {
        Schema::dropIfExists('RESCISAO_CALCULO');
    }
};
