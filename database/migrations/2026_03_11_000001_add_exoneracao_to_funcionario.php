<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Adiciona campos de exoneração/desligamento na tabela FUNCIONARIO.
 * Esses campos permitem registrar o ato formal de saída do servidor
 * e rastrear o status do cálculo de verbas rescisórias.
 */
return new class extends Migration {
    public function up(): void
    {
        Schema::table('FUNCIONARIO', function (Blueprint $table) {
            if (!Schema::hasColumn('FUNCIONARIO', 'FUNCIONARIO_MOTIVO_SAIDA')) {
                // EXONERACAO | DEMISSAO | APOSENTADORIA | FALECIMENTO | TRANSFERENCIA
                $table->string('FUNCIONARIO_MOTIVO_SAIDA', 50)->nullable()->after('FUNCIONARIO_TIPO_SAIDA');
            }
            if (!Schema::hasColumn('FUNCIONARIO', 'FUNCIONARIO_DATA_EXONERACAO')) {
                $table->date('FUNCIONARIO_DATA_EXONERACAO')->nullable()->after('FUNCIONARIO_MOTIVO_SAIDA');
            }
            if (!Schema::hasColumn('FUNCIONARIO', 'FUNCIONARIO_PORTARIA_SAIDA')) {
                $table->string('FUNCIONARIO_PORTARIA_SAIDA', 100)->nullable()->after('FUNCIONARIO_DATA_EXONERACAO');
            }
            if (!Schema::hasColumn('FUNCIONARIO', 'FUNCIONARIO_STATUS_RESCISORIO')) {
                // NENHUM | PENDENTE | CALCULADO | INCLUIDO_FOLHA | PAGO
                $table->string('FUNCIONARIO_STATUS_RESCISORIO', 20)->default('NENHUM')->after('FUNCIONARIO_PORTARIA_SAIDA');
            }
        });
    }

    public function down(): void
    {
        Schema::table('FUNCIONARIO', function (Blueprint $table) {
            foreach ([
                'FUNCIONARIO_MOTIVO_SAIDA',
                'FUNCIONARIO_DATA_EXONERACAO',
                'FUNCIONARIO_PORTARIA_SAIDA',
                'FUNCIONARIO_STATUS_RESCISORIO',
            ] as $col) {
                if (Schema::hasColumn('FUNCIONARIO', $col)) {
                    $table->dropColumn($col);
                }
            }
        });
    }
};
