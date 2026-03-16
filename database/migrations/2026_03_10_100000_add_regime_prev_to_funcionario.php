<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;
use Illuminate\Support\Facades\DB;

return new class extends Migration {
    public function up(): void
    {
        // Adiciona campo de regime previdenciário se ainda não existir
        if (Schema::hasTable('FUNCIONARIO') && !Schema::hasColumn('FUNCIONARIO', 'FUNCIONARIO_REGIME_PREV')) {
            Schema::table('FUNCIONARIO', function (Blueprint $table) {
                // RPPS = Regime Próprio (IPAM - São Luís)
                // RGPS = Regime Geral (INSS - temporários, PSS, estagiários, celetistas)
                $table->string('FUNCIONARIO_REGIME_PREV', 10)->default('RPPS')->after('FUNCIONARIO_ID');
            });
        }

        // Inferência automática de regime com base no tipo de vínculo já cadastrado
        // Servidores efetivos/estatutários → RPPS | Temporários/CLT/PSS → RGPS
        if (Schema::hasTable('FUNCIONARIO') && Schema::hasColumn('FUNCIONARIO', 'FUNCIONARIO_VINCULO_TIPO')) {
            DB::statement("
                UPDATE FUNCIONARIO
                SET FUNCIONARIO_REGIME_PREV = 'RGPS'
                WHERE FUNCIONARIO_VINCULO_TIPO IN ('PSS', 'TEMPORARIO', 'CLT', 'ESTAGIARIO', 'CONTRATO_TEMPORARIO')
                  AND (FUNCIONARIO_REGIME_PREV IS NULL OR FUNCIONARIO_REGIME_PREV = 'RPPS')
            ");
        }
    }

    public function down(): void
    {
        if (Schema::hasTable('FUNCIONARIO') && Schema::hasColumn('FUNCIONARIO', 'FUNCIONARIO_REGIME_PREV')) {
            Schema::table('FUNCIONARIO', function (Blueprint $table) {
                $table->dropColumn('FUNCIONARIO_REGIME_PREV');
            });
        }
    }
};
