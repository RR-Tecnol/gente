<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

// CONSIG-02 / CONSIG-04 — Rastreabilidade de consignações

return new class extends Migration {
    public function up()
    {
        // 1. Novas colunas em CONSIG_CONTRATO (se não existirem)
        if (Schema::hasTable('CONSIG_CONTRATO')) {
            Schema::table('CONSIG_CONTRATO', function (Blueprint $table) {
                if (!Schema::hasColumn('CONSIG_CONTRATO', 'STATUS_AUTORIZACAO')) {
                    $table->string('STATUS_AUTORIZACAO', 30)->default('SOLICITADO')->after('STATUS');
                }
                if (!Schema::hasColumn('CONSIG_CONTRATO', 'AUTORIZADO_POR')) {
                    $table->unsignedInteger('AUTORIZADO_POR')->nullable()->after('STATUS_AUTORIZACAO');
                }
                if (!Schema::hasColumn('CONSIG_CONTRATO', 'AUTORIZADO_EM')) {
                    $table->timestamp('AUTORIZADO_EM')->nullable()->after('AUTORIZADO_POR');
                }
                if (!Schema::hasColumn('CONSIG_CONTRATO', 'MOTIVO_REJEICAO')) {
                    $table->text('MOTIVO_REJEICAO')->nullable()->after('AUTORIZADO_EM');
                }
            });
        }

        // 2. Tabela de ocorrências (trilha de auditoria de cada mudança de status)
        if (!Schema::hasTable('CONSIG_OCORRENCIA')) {
            Schema::create('CONSIG_OCORRENCIA', function (Blueprint $table) {
                $table->increments('OCORRENCIA_ID');
                $table->unsignedInteger('CONTRATO_ID');
                // Tipos: AUTORIZACAO | REJEICAO | SUSPENSO | ATIVO | CANCELADO | QUITADO
                $table->string('TIPO', 30);
                $table->text('DESCRICAO')->nullable();
                $table->string('MOTIVO', 200)->nullable();
                $table->date('DATA_INICIO_EFEITO')->nullable();
                $table->date('DATA_FIM_EFEITO')->nullable();
                $table->unsignedInteger('USUARIO_ID')->nullable();
                $table->timestamps();

                $table->index('CONTRATO_ID', 'idx_consig_oc_contrato');
            });
        }

        // 3. Coluna STATUS SUSPENSA em CONSIG_PARCELA (se não existir)
        // O status válido 'SUSPENSA' precisa caber no campo existente
        // Normalmente VARCHAR(20) já suporta — sem alterar o tipo
    }

    public function down()
    {
        Schema::dropIfExists('CONSIG_OCORRENCIA');

        if (Schema::hasTable('CONSIG_CONTRATO')) {
            Schema::table('CONSIG_CONTRATO', function (Blueprint $table) {
                $table->dropColumn(['STATUS_AUTORIZACAO', 'AUTORIZADO_POR', 'AUTORIZADO_EM', 'MOTIVO_REJEICAO']);
            });
        }
    }
};
