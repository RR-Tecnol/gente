<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Restaura colunas do legado que estavam ausentes no banco local SQLite.
 * Confirmadas ausentes via Schema::getColumnListing() em 04/03/2026.
 */
return new class extends Migration {
    public function up(): void
    {
        // ── PESSOA ──────────────────────────────────────────────────────────
        // PESSOA_ESCOLARIDADE: referenciada no Model, na view e no relacionamento escolaridade()
        if (!Schema::hasColumn('PESSOA', 'PESSOA_ESCOLARIDADE')) {
            Schema::table('PESSOA', function (Blueprint $table) {
                $table->integer('PESSOA_ESCOLARIDADE')->nullable()->after('PESSOA_DATA_NASCIMENTO');
            });
        }

        // ── FUNCIONARIO ─────────────────────────────────────────────────────
        // FUNCIONARIO_OBSERVACAO: campo de observações livres sobre o servidor
        if (!Schema::hasColumn('FUNCIONARIO', 'FUNCIONARIO_OBSERVACAO')) {
            Schema::table('FUNCIONARIO', function (Blueprint $table) {
                $table->text('FUNCIONARIO_OBSERVACAO')->nullable()->after('FUNCIONARIO_DATA_FIM');
            });
        }

        // ── LOTACAO ─────────────────────────────────────────────────────────
        // FUNCIONARIO_ID: FK que estava faltando na tabela local
        if (!Schema::hasColumn('LOTACAO', 'FUNCIONARIO_ID')) {
            Schema::table('LOTACAO', function (Blueprint $table) {
                $table->unsignedInteger('FUNCIONARIO_ID')->nullable()->after('LOTACAO_ID');
            });
        }

        // VINCULO_ID: tipo de vínculo do servidor (Efetivo, Comissionado, Contratado, etc.)
        if (!Schema::hasColumn('LOTACAO', 'VINCULO_ID')) {
            Schema::table('LOTACAO', function (Blueprint $table) {
                $table->unsignedInteger('VINCULO_ID')->nullable()->after('SETOR_ID');
            });
        }
    }

    public function down(): void
    {
        if (Schema::hasColumn('PESSOA', 'PESSOA_ESCOLARIDADE')) {
            Schema::table('PESSOA', function (Blueprint $table) {
                $table->dropColumn('PESSOA_ESCOLARIDADE');
            });
        }

        if (Schema::hasColumn('FUNCIONARIO', 'FUNCIONARIO_OBSERVACAO')) {
            Schema::table('FUNCIONARIO', function (Blueprint $table) {
                $table->dropColumn('FUNCIONARIO_OBSERVACAO');
            });
        }

        if (Schema::hasColumn('LOTACAO', 'VINCULO_ID')) {
            Schema::table('LOTACAO', function (Blueprint $table) {
                $table->dropColumn('VINCULO_ID');
            });
        }

        if (Schema::hasColumn('LOTACAO', 'FUNCIONARIO_ID')) {
            Schema::table('LOTACAO', function (Blueprint $table) {
                $table->dropColumn('FUNCIONARIO_ID');
            });
        }
    }
};
