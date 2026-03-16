<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

class AddMissingColumnsRound2 extends Migration
{
    public function up()
    {
        // 1. SETOR: adicionar SETOR_SIGLA (faltando na tabela)
        if (Schema::hasTable('SETOR') && !Schema::hasColumn('SETOR', 'SETOR_SIGLA')) {
            Schema::table('SETOR', function (Blueprint $table) {
                $table->string('SETOR_SIGLA', 20)->nullable();
            });
        }

        // 2. ATRIBUICAO: adicionar ATRIBUICAO_DATA_EXCLUSAO
        if (Schema::hasTable('ATRIBUICAO') && !Schema::hasColumn('ATRIBUICAO', 'ATRIBUICAO_DATA_EXCLUSAO')) {
            Schema::table('ATRIBUICAO', function (Blueprint $table) {
                $table->date('ATRIBUICAO_DATA_EXCLUSAO')->nullable();
            });
        }

        // 3. TABELA_GENERICA: adicionar COLUNA_DESCRICAO
        if (Schema::hasTable('TABELA_GENERICA') && !Schema::hasColumn('TABELA_GENERICA', 'COLUNA_DESCRICAO')) {
            Schema::table('TABELA_GENERICA', function (Blueprint $table) {
                $table->string('COLUNA_DESCRICAO', 200)->nullable();
            });
        }

        // 4. EVENTO: adicionar EVENTO_IMPOSTO (flag boolean 0/1)
        if (Schema::hasTable('EVENTO') && !Schema::hasColumn('EVENTO', 'EVENTO_IMPOSTO')) {
            Schema::table('EVENTO', function (Blueprint $table) {
                $table->integer('EVENTO_IMPOSTO')->default(0);
            });
        }

        // 5. EVENTO: adicionar EVENTO_INCIDENCIA (verificar se falta)
        if (Schema::hasTable('EVENTO') && !Schema::hasColumn('EVENTO', 'EVENTO_INCIDENCIA')) {
            Schema::table('EVENTO', function (Blueprint $table) {
                $table->integer('EVENTO_INCIDENCIA')->nullable();
            });
        }

        // 6. EVENTO: adicionar EVENTO_SISTEMA (verificar se falta)
        if (Schema::hasTable('EVENTO') && !Schema::hasColumn('EVENTO', 'EVENTO_SISTEMA')) {
            Schema::table('EVENTO', function (Blueprint $table) {
                $table->integer('EVENTO_SISTEMA')->default(0);
            });
        }

        // 7. EVENTO: adicionar EVENTO_SALARIO (verificar se falta)
        if (Schema::hasTable('EVENTO') && !Schema::hasColumn('EVENTO', 'EVENTO_SALARIO')) {
            Schema::table('EVENTO', function (Blueprint $table) {
                $table->integer('EVENTO_SALARIO')->default(0);
            });
        }
    }

    public function down()
    {
        $drops = [
            'SETOR' => 'SETOR_SIGLA',
            'ATRIBUICAO' => 'ATRIBUICAO_DATA_EXCLUSAO',
            'TABELA_GENERICA' => 'COLUNA_DESCRICAO',
        ];
        foreach ($drops as $table => $column) {
            if (Schema::hasTable($table) && Schema::hasColumn($table, $column)) {
                Schema::table($table, function (Blueprint $t) use ($column) {
                    $t->dropColumn($column);
                });
            }
        }
    }
}
