<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * PERF-11B — Índices adicionais para listagens massivas (progressão, lotação por setor, afastamentos).
 */
return new class extends Migration {
    public function up(): void
    {
        if (Schema::hasTable('FUNCIONARIO') && Schema::hasColumn('FUNCIONARIO', 'FUNCIONARIO_MATRICULA')) {
            Schema::table('FUNCIONARIO', function (Blueprint $table) {
                try {
                    $table->index('FUNCIONARIO_MATRICULA', 'idx_func_matricula');
                } catch (\Throwable $e) {
                }
            });
        }

        if (Schema::hasTable('AFASTAMENTO')
            && Schema::hasColumn('AFASTAMENTO', 'FUNCIONARIO_ID')
            && Schema::hasColumn('AFASTAMENTO', 'AFASTAMENTO_DATA_INICIO')) {
            Schema::table('AFASTAMENTO', function (Blueprint $table) {
                try {
                    $table->index(['FUNCIONARIO_ID', 'AFASTAMENTO_DATA_INICIO'], 'idx_afast_func_inicio');
                } catch (\Throwable $e) {
                }
            });
        }

        if (Schema::hasTable('LOTACAO')
            && Schema::hasColumn('LOTACAO', 'SETOR_ID')
            && Schema::hasColumn('LOTACAO', 'FUNCIONARIO_ID')
            && Schema::hasColumn('LOTACAO', 'LOTACAO_DATA_FIM')) {
            Schema::table('LOTACAO', function (Blueprint $table) {
                try {
                    $table->index(['SETOR_ID', 'FUNCIONARIO_ID', 'LOTACAO_DATA_FIM'], 'idx_lot_setor_func_fim');
                } catch (\Throwable $e) {
                }
            });
        }

        if (Schema::hasTable('TABELA_SALARIAL')
            && Schema::hasColumn('TABELA_SALARIAL', 'CARREIRA_ID')
            && Schema::hasColumn('TABELA_SALARIAL', 'TABELA_CLASSE')
            && Schema::hasColumn('TABELA_SALARIAL', 'TABELA_REFERENCIA_ORDEM')) {
            Schema::table('TABELA_SALARIAL', function (Blueprint $table) {
                try {
                    $table->index(['CARREIRA_ID', 'TABELA_CLASSE', 'TABELA_REFERENCIA_ORDEM'], 'idx_ts_carreira_classe_ordem');
                } catch (\Throwable $e) {
                }
            });
        }
    }

    public function down(): void
    {
        $map = [
            'FUNCIONARIO' => ['idx_func_matricula'],
            'AFASTAMENTO' => ['idx_afast_func_inicio'],
            'LOTACAO' => ['idx_lot_setor_func_fim'],
            'TABELA_SALARIAL' => ['idx_ts_carreira_classe_ordem'],
        ];

        foreach ($map as $table => $indexes) {
            if (! Schema::hasTable($table)) {
                continue;
            }
            Schema::table($table, function (Blueprint $t) use ($indexes) {
                foreach ($indexes as $idx) {
                    try {
                        $t->dropIndex($idx);
                    } catch (\Throwable $e) {
                    }
                }
            });
        }
    }
};
