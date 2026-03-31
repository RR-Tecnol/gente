<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        // PROCESSO_LICITATORIO — licitações e dispensas
        if (!Schema::hasTable('PROCESSO_LICITATORIO')) {
            Schema::create('PROCESSO_LICITATORIO', function (Blueprint $table) {
                $table->increments('PROCESSO_ID');
                $table->string('PROCESSO_NUMERO', 30);
                // PREGAO | TOMADA_PRECOS | CONVITE | DISPENSA | INEXIGIBILIDADE
                $table->string('PROCESSO_MODALIDADE', 30);
                $table->string('PROCESSO_OBJETO', 500);
                $table->decimal('PROCESSO_VALOR_ESTIMADO', 15, 2)->nullable();
                // ABERTO | EM_ANDAMENTO | HOMOLOGADO | CANCELADO | DESERTO
                $table->string('PROCESSO_STATUS', 20)->default('ABERTO');
                $table->date('PROCESSO_DATA_ABERTURA');
                $table->date('PROCESSO_DATA_ENCERRAMENTO')->nullable();
                $table->unsignedInteger('UO_ID')->nullable();
                $table->unsignedInteger('USUARIO_ID')->nullable();
                $table->timestamps();
            });
        }

        // CONTRATO_ADMINISTRATIVO — contratos firmados com fornecedores
        if (!Schema::hasTable('CONTRATO_ADMINISTRATIVO')) {
            Schema::create('CONTRATO_ADMINISTRATIVO', function (Blueprint $table) {
                $table->increments('CONTRATO_ID');
                $table->string('CONTRATO_NUMERO', 30);
                $table->unsignedInteger('PROCESSO_ID')->nullable();
                $table->unsignedInteger('CREDOR_ID')->nullable(); // FK CREDOR (C2)
                $table->string('CONTRATO_FORNECEDOR', 150)->nullable(); // fallback textual
                $table->string('CONTRATO_OBJETO', 500);
                $table->decimal('CONTRATO_VALOR', 15, 2);
                $table->date('CONTRATO_INICIO');
                $table->date('CONTRATO_FIM');
                // VIGENTE | ENCERRADO | RESCINDIDO | SUSPENSO
                $table->string('CONTRATO_STATUS', 20)->default('VIGENTE');
                $table->unsignedInteger('USUARIO_ID')->nullable();
                $table->timestamps();

                $table->index(['CONTRATO_STATUS', 'CONTRATO_FIM']);
            });
        }

        // PEDIDO_COMPRA — solicitações de compra por secretaria
        if (!Schema::hasTable('PEDIDO_COMPRA')) {
            Schema::create('PEDIDO_COMPRA', function (Blueprint $table) {
                $table->increments('PEDIDO_ID');
                $table->unsignedInteger('UO_ID')->nullable();
                $table->string('PEDIDO_DESCRICAO', 300);
                $table->decimal('PEDIDO_VALOR_ESTIMADO', 15, 2)->nullable();
                // SOLICITADO | EM_ANALISE | APROVADO | VINCULADO | CANCELADO
                $table->string('PEDIDO_STATUS', 20)->default('SOLICITADO');
                $table->unsignedInteger('PROCESSO_ID')->nullable();
                $table->unsignedInteger('SOLICITANTE_ID')->nullable();
                $table->timestamps();
            });
        }
    }

    public function down(): void
    {
        Schema::dropIfExists('PEDIDO_COMPRA');
        Schema::dropIfExists('CONTRATO_ADMINISTRATIVO');
        Schema::dropIfExists('PROCESSO_LICITATORIO');
    }
};
