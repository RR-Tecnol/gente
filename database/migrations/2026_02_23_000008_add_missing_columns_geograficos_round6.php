<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

class AddMissingColumnsGeograficosRound6 extends Migration
{
    /**
     * Round6 — tabelas geográficas e de configuração
     * Identificado durante seed_dados_base.php em 23/02/2026
     */
    public function up()
    {
        // ── UF ───────────────────────────────────────────────────────────────
        $ufColunas = [
            'UF_NOME' => fn($t) => $t->string('UF_NOME', 50)->nullable(),
            'UF_SIGLA' => fn($t) => $t->string('UF_SIGLA', 2)->nullable(),
        ];
        foreach ($ufColunas as $col => $def) {
            if (Schema::hasTable('UF') && !Schema::hasColumn('UF', $col)) {
                Schema::table('UF', fn(Blueprint $t) => $def($t));
            }
        }

        // ── CIDADE ───────────────────────────────────────────────────────────
        $cidadeColunas = [
            'CIDADE_NOME' => fn($t) => $t->string('CIDADE_NOME', 100)->nullable(),
            'UF_ID' => fn($t) => $t->integer('UF_ID')->nullable(),
            'CIDADE_ATIVA' => fn($t) => $t->integer('CIDADE_ATIVA')->default(1),
        ];
        foreach ($cidadeColunas as $col => $def) {
            if (Schema::hasTable('CIDADE') && !Schema::hasColumn('CIDADE', $col)) {
                Schema::table('CIDADE', fn(Blueprint $t) => $def($t));
            }
        }

        // ── BAIRRO ───────────────────────────────────────────────────────────
        $bairroColunas = [
            'BAIRRO_NOME' => fn($t) => $t->string('BAIRRO_NOME', 100)->nullable(),
            'CIDADE_ID' => fn($t) => $t->integer('CIDADE_ID')->nullable(),
        ];
        foreach ($bairroColunas as $col => $def) {
            if (Schema::hasTable('BAIRRO') && !Schema::hasColumn('BAIRRO', $col)) {
                Schema::table('BAIRRO', fn(Blueprint $t) => $def($t));
            }
        }

        // ── ATRIBUICAO ───────────────────────────────────────────────────────
        // Já tem 5 colunas — falta ATRIBUICAO_SIGLA
        $atribuicaoColunas = [
            'ATRIBUICAO_SIGLA' => fn($t) => $t->string('ATRIBUICAO_SIGLA', 20)->nullable(),
            'ATRIBUICAO_DATA_INCLUSAO' => fn($t) => $t->date('ATRIBUICAO_DATA_INCLUSAO')->nullable(),
        ];
        foreach ($atribuicaoColunas as $col => $def) {
            if (Schema::hasTable('ATRIBUICAO') && !Schema::hasColumn('ATRIBUICAO', $col)) {
                Schema::table('ATRIBUICAO', fn(Blueprint $t) => $def($t));
            }
        }

        // ── SETOR ────────────────────────────────────────────────────────────
        $setorColunas = [
            'SETOR_NOME' => fn($t) => $t->string('SETOR_NOME', 100)->nullable(),
            'SETOR_SIGLA' => fn($t) => $t->string('SETOR_SIGLA', 20)->nullable(),
            'UNIDADE_ID' => fn($t) => $t->integer('UNIDADE_ID')->nullable(),
            'SETOR_DATA_EXCLUSAO' => fn($t) => $t->date('SETOR_DATA_EXCLUSAO')->nullable(),
        ];
        foreach ($setorColunas as $col => $def) {
            if (Schema::hasTable('SETOR') && !Schema::hasColumn('SETOR', $col)) {
                Schema::table('SETOR', fn(Blueprint $t) => $def($t));
            }
        }

        // ── UNIDADE ──────────────────────────────────────────────────────────
        // round5 já adicionou UNIDADE_NOME e UNIDADE_SIGLA — checar o restante
        $unidadeColunas = [
            'UNIDADE_CNES' => fn($t) => $t->string('UNIDADE_CNES', 20)->nullable(),
            'BAIRRO_ID' => fn($t) => $t->integer('BAIRRO_ID')->nullable(),
            'UNIDADE_ENDERECO' => fn($t) => $t->string('UNIDADE_ENDERECO', 300)->nullable(),
            'UNIDADE_COMPLEMENTO' => fn($t) => $t->string('UNIDADE_COMPLEMENTO', 200)->nullable(),
            'UNIDADE_TELEFONE' => fn($t) => $t->string('UNIDADE_TELEFONE', 20)->nullable(),
            'UNIDADE_ATIVA' => fn($t) => $t->integer('UNIDADE_ATIVA')->default(1),
            'UNIDADE_PORTE' => fn($t) => $t->integer('UNIDADE_PORTE')->nullable(),
            'UNIDADE_TIPO' => fn($t) => $t->integer('UNIDADE_TIPO')->nullable(),
        ];
        foreach ($unidadeColunas as $col => $def) {
            if (Schema::hasTable('UNIDADE') && !Schema::hasColumn('UNIDADE', $col)) {
                Schema::table('UNIDADE', fn(Blueprint $t) => $def($t));
            }
        }

        // ── VINCULO ──────────────────────────────────────────────────────────
        // Usado por Folha — fillable: VINCULO_NOME, VINCULO_SIGLA, VINCULO_ATIVO
        $vinculoColunas = [
            'VINCULO_NOME' => fn($t) => $t->string('VINCULO_NOME', 100)->nullable(),
            'VINCULO_SIGLA' => fn($t) => $t->string('VINCULO_SIGLA', 20)->nullable(),
        ];
        foreach ($vinculoColunas as $col => $def) {
            if (Schema::hasTable('VINCULO') && !Schema::hasColumn('VINCULO', $col)) {
                Schema::table('VINCULO', fn(Blueprint $t) => $def($t));
            }
        }

        // ── CARTORIO ─────────────────────────────────────────────────────────
        $cartorioColunas = [
            'CARTORIO_NOME' => fn($t) => $t->string('CARTORIO_NOME', 200)->nullable(),
            'CARTORIO_NUMERO' => fn($t) => $t->string('CARTORIO_NUMERO', 10)->nullable(),
            'CIDADE_ID' => fn($t) => $t->integer('CIDADE_ID')->nullable(),
        ];
        foreach ($cartorioColunas as $col => $def) {
            if (Schema::hasTable('CARTORIO') && !Schema::hasColumn('CARTORIO', $col)) {
                Schema::table('CARTORIO', fn(Blueprint $t) => $def($t));
            }
        }

        // ── TIPO_ALERTA ──────────────────────────────────────────────────────
        $tipoAlertaColunas = [
            'TIPO_ALERTA_NOME' => fn($t) => $t->string('TIPO_ALERTA_NOME', 100)->nullable(),
            'TIPO_ALERTA_VISIVEL' => fn($t) => $t->integer('TIPO_ALERTA_VISIVEL')->default(1),
        ];
        foreach ($tipoAlertaColunas as $col => $def) {
            if (Schema::hasTable('TIPO_ALERTA') && !Schema::hasColumn('TIPO_ALERTA', $col)) {
                Schema::table('TIPO_ALERTA', fn(Blueprint $t) => $def($t));
            }
        }
    }

    public function down()
    {
        $drops = [
            'UF' => ['UF_NOME', 'UF_SIGLA'],
            'CIDADE' => ['CIDADE_NOME', 'UF_ID', 'CIDADE_ATIVA'],
            'BAIRRO' => ['BAIRRO_NOME', 'CIDADE_ID'],
            'ATRIBUICAO' => ['ATRIBUICAO_SIGLA', 'ATRIBUICAO_DATA_INCLUSAO'],
            'SETOR' => ['SETOR_NOME', 'SETOR_SIGLA', 'UNIDADE_ID', 'SETOR_DATA_EXCLUSAO'],
            'UNIDADE' => ['UNIDADE_CNES', 'BAIRRO_ID', 'UNIDADE_ENDERECO', 'UNIDADE_COMPLEMENTO', 'UNIDADE_TELEFONE', 'UNIDADE_ATIVA', 'UNIDADE_PORTE', 'UNIDADE_TIPO'],
            'VINCULO' => ['VINCULO_NOME', 'VINCULO_SIGLA'],
            'CARTORIO' => ['CARTORIO_NOME', 'CARTORIO_NUMERO', 'CIDADE_ID'],
            'TIPO_ALERTA' => ['TIPO_ALERTA_NOME', 'TIPO_ALERTA_VISIVEL'],
        ];
        foreach ($drops as $table => $columns) {
            foreach ($columns as $col) {
                if (Schema::hasTable($table) && Schema::hasColumn($table, $col)) {
                    Schema::table($table, fn($t) => $t->dropColumn($col));
                }
            }
        }
    }
}
