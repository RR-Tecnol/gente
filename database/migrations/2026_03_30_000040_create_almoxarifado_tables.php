<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        // ALMOXARIFADO — depósitos/almoxarifados do município
        if (!Schema::hasTable('ALMOXARIFADO')) {
            Schema::create('ALMOXARIFADO', function (Blueprint $table) {
                $table->increments('ALMOX_ID');
                $table->string('ALMOX_NOME', 100);
                $table->unsignedInteger('UO_ID')->nullable();
                $table->string('ALMOX_RESPONSAVEL', 150)->nullable();
                $table->boolean('ALMOX_ATIVO')->default(true);
                $table->timestamps();
            });
        }

        // ITEM_ESTOQUE — catálogo de materiais/produtos
        if (!Schema::hasTable('ITEM_ESTOQUE')) {
            Schema::create('ITEM_ESTOQUE', function (Blueprint $table) {
                $table->increments('ITEM_ID');
                $table->string('ITEM_CODIGO', 20)->unique();
                // Nome padronizado DCI (Denominação Comum Internacional) para medicamentos
                $table->string('ITEM_DESCRICAO', 300);
                // UN | CX | KG | L | M | PCT | RESMA | AMPOLA | FRASCO | COMPRIMIDO
                $table->string('ITEM_UNIDADE', 15)->default('UN');
                // MATERIAL | MEDICAMENTO | EQUIPAMENTO | EPI | REAGENTE | VACINA
                $table->string('ITEM_CATEGORIA', 50)->nullable();
                $table->integer('ITEM_ESTOQUE_MINIMO')->default(0);
                $table->boolean('ITEM_ATIVO')->default(true);

                // ── Campos específicos para itens médicos/farmacêuticos ──────
                // Código CATMAT — padrão SCTIE/MS para aquisição pública de medicamentos
                $table->string('ITEM_CATMAT', 20)->nullable();
                // Código ANVISA — registro do produto na agência
                $table->string('ITEM_CODIGO_ANVISA', 20)->nullable();
                // Portaria SVS 344/1998 — substância psicotrópica/entorpecente
                $table->boolean('ITEM_CONTROLADO')->default(false);
                // Requer rastreamento de lote + validade (obrigatório para medicamentos)
                $table->boolean('ITEM_REQUER_LOTE')->default(false);
                // Temperatura de armazenamento (importante para vacinas e termolábeis)
                $table->string('ITEM_TEMP_ARMAZENAMENTO', 30)->nullable(); // ex: "2-8°C", "15-25°C"
                // ────────────────────────────────────────────────────────────

                $table->timestamps();
            });
        }

        // LOTE_ESTOQUE — rastreamento de lote/validade por item × almoxarifado
        // Obrigatório para medicamentos, vacinas e materiais biológicos
        if (!Schema::hasTable('LOTE_ESTOQUE')) {
            Schema::create('LOTE_ESTOQUE', function (Blueprint $table) {
                $table->increments('LOTE_ID');
                $table->unsignedInteger('ALMOX_ID');
                $table->unsignedInteger('ITEM_ID');
                $table->string('LOTE_NUMERO', 50);          // número do lote do fabricante
                $table->string('LOTE_FABRICANTE', 100)->nullable();
                $table->date('LOTE_DATA_FABRICACAO')->nullable();
                $table->date('LOTE_DATA_VALIDADE');          // obrigatório para rastreabilidade
                $table->integer('LOTE_QUANTIDADE_INICIAL')->default(0);
                $table->integer('LOTE_QUANTIDADE_ATUAL')->default(0);
                // DISPONIVEL | VENCIDO | ESGOTADO | INUTILIZADO
                $table->string('LOTE_STATUS', 20)->default('DISPONIVEL');
                $table->unsignedInteger('REGISTRADO_POR')->nullable();
                $table->timestamps();

                $table->index(['ITEM_ID', 'LOTE_STATUS']);
                $table->index(['LOTE_DATA_VALIDADE']); // para alertas de vencimento
            });
        }

        // SALDO_ESTOQUE — saldo atual por item × almoxarifado
        if (!Schema::hasTable('SALDO_ESTOQUE')) {
            Schema::create('SALDO_ESTOQUE', function (Blueprint $table) {
                $table->increments('SALDO_ID');
                $table->unsignedInteger('ALMOX_ID');
                $table->unsignedInteger('ITEM_ID');
                $table->integer('SALDO_QUANTIDADE')->default(0);
                $table->decimal('SALDO_VALOR_MEDIO', 10, 2)->default(0); // custo médio
                $table->timestamps();

                $table->unique(['ALMOX_ID', 'ITEM_ID']);
            });
        }

        // MOVIMENTACAO_ESTOQUE — histórico de entradas e saídas
        if (!Schema::hasTable('MOVIMENTACAO_ESTOQUE')) {
            Schema::create('MOVIMENTACAO_ESTOQUE', function (Blueprint $table) {
                $table->increments('MOV_ID');
                $table->unsignedInteger('ALMOX_ID');
                $table->unsignedInteger('ITEM_ID');
                // ENTRADA | SAIDA | AJUSTE | TRANSFERENCIA
                $table->string('MOV_TIPO', 20);
                $table->integer('MOV_QUANTIDADE');
                $table->decimal('MOV_VALOR_UNITARIO', 10, 2)->nullable();
                $table->string('MOV_DOCUMENTO', 50)->nullable();  // NF, requisição
                $table->unsignedInteger('UO_DESTINO_ID')->nullable();
                $table->unsignedInteger('PEDIDO_COMPRA_ID')->nullable(); // vínculo com D1
                $table->unsignedInteger('REGISTRADO_POR')->nullable();
                $table->text('MOV_OBS')->nullable();
                $table->timestamps();

                $table->index(['ALMOX_ID', 'ITEM_ID']);
                $table->index(['MOV_TIPO']);
            });
        }
    }

    public function down(): void
    {
        Schema::dropIfExists('MOVIMENTACAO_ESTOQUE');
        Schema::dropIfExists('SALDO_ESTOQUE');
        Schema::dropIfExists('LOTE_ESTOQUE');
        Schema::dropIfExists('ITEM_ESTOQUE');
        Schema::dropIfExists('ALMOXARIFADO');
    }
};
