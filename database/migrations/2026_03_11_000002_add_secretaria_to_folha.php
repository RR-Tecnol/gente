<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Adiciona campos de secretaria/organograma na FOLHA e DETALHE_FOLHA.
 * Permite filtrar folha por secretaria, setor e tipo especial (rescisória etc.).
 */
return new class extends Migration {
    public function up(): void
    {
        // ── FOLHA ──────────────────────────────────────────────────────────────
        Schema::table('FOLHA', function (Blueprint $table) {
            if (!Schema::hasColumn('FOLHA', 'FOLHA_TIPO_ESPECIAL')) {
                // ORDINARIA | RESCISORIA | COMPLEMENTAR | FERIAS | 13_SALARIO
                $table->string('FOLHA_TIPO_ESPECIAL', 30)->default('ORDINARIA')->after('FOLHA_TIPO');
            }
            if (!Schema::hasColumn('FOLHA', 'UNIDADE_ID')) {
                $table->unsignedInteger('UNIDADE_ID')->nullable()->after('FOLHA_TIPO_ESPECIAL');
            }
            if (!Schema::hasColumn('FOLHA', 'FOLHA_SITUACAO')) {
                // ABERTA | FECHADA | HOMOLOGADA
                $table->string('FOLHA_SITUACAO', 20)->default('ABERTA')->after('VINCULO_ID');
            }
        });

        // ── DETALHE_FOLHA ──────────────────────────────────────────────────────
        Schema::table('DETALHE_FOLHA', function (Blueprint $table) {
            if (!Schema::hasColumn('DETALHE_FOLHA', 'DETALHE_FOLHA_LIQUIDO')) {
                $table->decimal('DETALHE_FOLHA_LIQUIDO', 15, 2)->default(0)->after('DETALHE_FOLHA_DESCONTOS');
            }
            if (!Schema::hasColumn('DETALHE_FOLHA', 'UNIDADE_ID')) {
                // Snapshot da secretaria no momento do cálculo (não muda se servidor for transferido)
                $table->unsignedInteger('UNIDADE_ID')->nullable()->after('PENSIONISTA_ID');
            }
            if (!Schema::hasColumn('DETALHE_FOLHA', 'SETOR_ID')) {
                $table->unsignedInteger('SETOR_ID')->nullable()->after('UNIDADE_ID');
            }
            if (!Schema::hasColumn('DETALHE_FOLHA', 'VINCULO_ID')) {
                $table->unsignedInteger('VINCULO_ID')->nullable()->after('SETOR_ID');
            }
        });
    }

    public function down(): void
    {
        Schema::table('FOLHA', function (Blueprint $table) {
            foreach (['FOLHA_TIPO_ESPECIAL', 'UNIDADE_ID', 'FOLHA_SITUACAO'] as $col) {
                if (Schema::hasColumn('FOLHA', $col)) {
                    $table->dropColumn($col);
                }
            }
        });

        Schema::table('DETALHE_FOLHA', function (Blueprint $table) {
            foreach (['DETALHE_FOLHA_LIQUIDO', 'UNIDADE_ID', 'SETOR_ID', 'VINCULO_ID'] as $col) {
                if (Schema::hasColumn('DETALHE_FOLHA', $col)) {
                    $table->dropColumn($col);
                }
            }
        });
    }
};
