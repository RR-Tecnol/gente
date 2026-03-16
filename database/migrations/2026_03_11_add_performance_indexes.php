<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

// PERF-02 — Índices de banco para otimização de queries frequentes

return new class extends Migration {
    public function up()
    {
        // DETALHE_FOLHA — consultas por funcionário e por folha
        if (Schema::hasTable('DETALHE_FOLHA')) {
            Schema::table('DETALHE_FOLHA', function (Blueprint $table) {
                try {
                    $table->index(['FUNCIONARIO_ID', 'FOLHA_ID'], 'idx_df_func_folha');
                } catch (\Exception $e) {
                }
            });
        }

        // FOLHA — ordenação por competência (usada em quase toda lista de folhas)
        if (Schema::hasTable('FOLHA')) {
            Schema::table('FOLHA', function (Blueprint $table) {
                try {
                    $table->index('FOLHA_COMPETENCIA', 'idx_folha_comp');
                } catch (\Exception $e) {
                }
                try {
                    $table->index(['FOLHA_COMPETENCIA', 'FOLHA_TIPO_ESPECIAL'], 'idx_folha_comp_tipo');
                } catch (\Exception $e) {
                }
            });
        }

        // HORA_EXTRA — filtros por competência e status
        if (Schema::hasTable('HORA_EXTRA')) {
            Schema::table('HORA_EXTRA', function (Blueprint $table) {
                try {
                    $table->index(['COMPETENCIA', 'STATUS'], 'idx_he_comp_status');
                } catch (\Exception $e) {
                }
            });
        }

        // CONSIG_CONTRATO — margem e listagem de ativos
        if (Schema::hasTable('CONSIG_CONTRATO')) {
            Schema::table('CONSIG_CONTRATO', function (Blueprint $table) {
                try {
                    $table->index(['FUNCIONARIO_ID', 'STATUS'], 'idx_cc_func_status');
                } catch (\Exception $e) {
                }
            });
        }

        // CONSIG_PARCELA — desconto mensal (CONSIG-03)
        if (Schema::hasTable('CONSIG_PARCELA')) {
            Schema::table('CONSIG_PARCELA', function (Blueprint $table) {
                try {
                    $table->index(['COMPETENCIA', 'STATUS'], 'idx_cp_comp_status');
                } catch (\Exception $e) {
                }
            });
        }

        // ESOCIAL_EVENTO — pendências (BUG-05 usa LEFT JOIN, índice acelera ainda mais)
        if (Schema::hasTable('ESOCIAL_EVENTO')) {
            Schema::table('ESOCIAL_EVENTO', function (Blueprint $table) {
                try {
                    $table->index(['FUNCIONARIO_ID', 'TIPO_EVENTO'], 'idx_es_func_tipo');
                } catch (\Exception $e) {
                }
            });
        }

        // LOTACAO — lotação atual (JOIN mais comum do sistema)
        if (Schema::hasTable('LOTACAO')) {
            Schema::table('LOTACAO', function (Blueprint $table) {
                try {
                    $table->index(['FUNCIONARIO_ID', 'LOTACAO_DATA_FIM'], 'idx_lot_func_fim');
                } catch (\Exception $e) {
                }
            });
        }

        // BANCO_HORAS — saldo por funcionário
        if (Schema::hasTable('BANCO_HORAS')) {
            Schema::table('BANCO_HORAS', function (Blueprint $table) {
                try {
                    $table->index(['FUNCIONARIO_ID', 'COMPETENCIA'], 'idx_bh_func_comp');
                } catch (\Exception $e) {
                }
            });
        }

        // ATESTADO_MEDICO — listagem por funcionário  e status
        if (Schema::hasTable('ATESTADO_MEDICO')) {
            Schema::table('ATESTADO_MEDICO', function (Blueprint $table) {
                try {
                    $table->index(['FUNCIONARIO_ID', 'STATUS'], 'idx_am_func_status');
                } catch (\Exception $e) {
                }
            });
        }
    }

    public function down()
    {
        $indexes = [
            'DETALHE_FOLHA' => ['idx_df_func_folha'],
            'FOLHA' => ['idx_folha_comp', 'idx_folha_comp_tipo'],
            'HORA_EXTRA' => ['idx_he_comp_status'],
            'CONSIG_CONTRATO' => ['idx_cc_func_status'],
            'CONSIG_PARCELA' => ['idx_cp_comp_status'],
            'ESOCIAL_EVENTO' => ['idx_es_func_tipo'],
            'LOTACAO' => ['idx_lot_func_fim'],
            'BANCO_HORAS' => ['idx_bh_func_comp'],
            'ATESTADO_MEDICO' => ['idx_am_func_status'],
        ];

        foreach ($indexes as $table => $tableIndexes) {
            if (Schema::hasTable($table)) {
                Schema::table($table, function (Blueprint $t) use ($tableIndexes) {
                    foreach ($tableIndexes as $idx) {
                        try {
                            $t->dropIndex($idx);
                        } catch (\Exception $e) {
                        }
                    }
                });
            }
        }
    }
};
