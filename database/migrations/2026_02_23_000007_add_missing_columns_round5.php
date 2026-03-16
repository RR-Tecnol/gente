<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

class AddMissingColumnsRound5 extends Migration
{
    /**
     * Round5 — colunas faltantes nos módulos Escala, Folha e Administração
     * Identificado via audit_colunas.php em 23/02/2026
     */
    public function up()
    {
        // ── ESCALA ───────────────────────────────────────────────────────────
        // fillable: SETOR_ID, ESCALA_COMPETENCIA, ESCALA_DESCRICAO,
        //           ESCALA_OBSERVACAO, TIPO_ESCALA_ID
        $escalaColunas = [
            'SETOR_ID' => fn($t) => $t->integer('SETOR_ID')->nullable(),
            'ESCALA_COMPETENCIA' => fn($t) => $t->string('ESCALA_COMPETENCIA', 6)->nullable(),
            'ESCALA_DESCRICAO' => fn($t) => $t->string('ESCALA_DESCRICAO', 200)->nullable(),
            'ESCALA_OBSERVACAO' => fn($t) => $t->string('ESCALA_OBSERVACAO', 500)->nullable(),
            'TIPO_ESCALA_ID' => fn($t) => $t->integer('TIPO_ESCALA_ID')->nullable(),
        ];
        foreach ($escalaColunas as $col => $def) {
            if (Schema::hasTable('ESCALA') && !Schema::hasColumn('ESCALA', $col)) {
                Schema::table('ESCALA', fn(Blueprint $t) => $def($t));
            }
        }

        // ── FOLHA ────────────────────────────────────────────────────────────
        // fillable: FOLHA_DESCRICAO, FOLHA_TIPO, VINCULO_ID, FOLHA_COMPETENCIA,
        //           FOLHA_QTD_SERVIDORES, FOLHA_VALOR_TOTAL, FOLHA_ARQUIVO, FOLHA_CHECKSUM
        $folhaColunas = [
            'FOLHA_DESCRICAO' => fn($t) => $t->string('FOLHA_DESCRICAO', 200)->nullable(),
            'FOLHA_TIPO' => fn($t) => $t->integer('FOLHA_TIPO')->nullable(),
            'VINCULO_ID' => fn($t) => $t->integer('VINCULO_ID')->nullable(),
            'FOLHA_COMPETENCIA' => fn($t) => $t->string('FOLHA_COMPETENCIA', 6)->nullable(),
            'FOLHA_QTD_SERVIDORES' => fn($t) => $t->integer('FOLHA_QTD_SERVIDORES')->nullable(),
            'FOLHA_VALOR_TOTAL' => fn($t) => $t->bigInteger('FOLHA_VALOR_TOTAL')->nullable(),
            'FOLHA_ARQUIVO' => fn($t) => $t->string('FOLHA_ARQUIVO', 255)->nullable(),
            'FOLHA_CHECKSUM' => fn($t) => $t->string('FOLHA_CHECKSUM', 64)->nullable(),
        ];
        foreach ($folhaColunas as $col => $def) {
            if (Schema::hasTable('FOLHA') && !Schema::hasColumn('FOLHA', $col)) {
                Schema::table('FOLHA', fn(Blueprint $t) => $def($t));
            }
        }

        // ── APLICACAO ────────────────────────────────────────────────────────
        // fillable: APLICACAO_NOME, APLICACAO_ICONE, APLICACAO_URL,
        //           APLICACAO_GESTAO, APLICACAO_ATIVA, APLICACAO_ORDEM, APLICACAO_PAI_ID
        $aplicacaoColunas = [
            'APLICACAO_NOME' => fn($t) => $t->string('APLICACAO_NOME', 100)->nullable(),
            'APLICACAO_ICONE' => fn($t) => $t->string('APLICACAO_ICONE', 100)->nullable(),
            'APLICACAO_URL' => fn($t) => $t->string('APLICACAO_URL', 200)->nullable(),
            'APLICACAO_GESTAO' => fn($t) => $t->integer('APLICACAO_GESTAO')->default(0),
            'APLICACAO_ATIVA' => fn($t) => $t->integer('APLICACAO_ATIVA')->default(1),
            'APLICACAO_ORDEM' => fn($t) => $t->integer('APLICACAO_ORDEM')->default(0),
            'APLICACAO_PAI_ID' => fn($t) => $t->integer('APLICACAO_PAI_ID')->nullable(),
        ];
        foreach ($aplicacaoColunas as $col => $def) {
            if (Schema::hasTable('APLICACAO') && !Schema::hasColumn('APLICACAO', $col)) {
                Schema::table('APLICACAO', fn(Blueprint $t) => $def($t));
            }
        }

        // ── PERFIL ───────────────────────────────────────────────────────────
        // fillable: PERFIL_NOME, PERFIL_ATIVO, PERFIL_DASHBOARD_LINK
        $perfilColunas = [
            'PERFIL_NOME' => fn($t) => $t->string('PERFIL_NOME', 100)->nullable(),
            'PERFIL_DASHBOARD_LINK' => fn($t) => $t->string('PERFIL_DASHBOARD_LINK', 200)->nullable(),
        ];
        foreach ($perfilColunas as $col => $def) {
            if (Schema::hasTable('PERFIL') && !Schema::hasColumn('PERFIL', $col)) {
                Schema::table('PERFIL', fn(Blueprint $t) => $def($t));
            }
        }

        // ── TRIBUTACAO ───────────────────────────────────────────────────────
        // Model Tributacao.php — colunas de dados
        $tributacaoColunas = [
            'TRIBUTACAO_NOME' => fn($t) => $t->string('TRIBUTACAO_NOME', 100)->nullable(),
            'TRIBUTACAO_CODIGO' => fn($t) => $t->string('TRIBUTACAO_CODIGO', 20)->nullable(),
            'TRIBUTACAO_ALIQUOTA' => fn($t) => $t->decimal('TRIBUTACAO_ALIQUOTA', 8, 4)->nullable(),
            'TRIBUTACAO_DEDUCAO' => fn($t) => $t->decimal('TRIBUTACAO_DEDUCAO', 10, 2)->nullable(),
        ];
        foreach ($tributacaoColunas as $col => $def) {
            if (Schema::hasTable('TRIBUTACAO') && !Schema::hasColumn('TRIBUTACAO', $col)) {
                Schema::table('TRIBUTACAO', fn(Blueprint $t) => $def($t));
            }
        }

        // ── UNIDADE ──────────────────────────────────────────────────────────
        // Model Unidade.php fillable: UNIDADE_NOME, UNIDADE_SIGLA, UNIDADE_ATIVO, etc.
        $unidadeColunas = [
            'UNIDADE_NOME' => fn($t) => $t->string('UNIDADE_NOME', 200)->nullable(),
            'UNIDADE_SIGLA' => fn($t) => $t->string('UNIDADE_SIGLA', 20)->nullable(),
            'UNIDADE_CNPJ' => fn($t) => $t->string('UNIDADE_CNPJ', 20)->nullable(),
        ];
        foreach ($unidadeColunas as $col => $def) {
            if (Schema::hasTable('UNIDADE') && !Schema::hasColumn('UNIDADE', $col)) {
                Schema::table('UNIDADE', fn(Blueprint $t) => $def($t));
            }
        }

        // ── TIPO_DOCUMENTO ───────────────────────────────────────────────────
        $tipoDocColunas = [
            'TIPO_DOCUMENTO_NOME' => fn($t) => $t->string('TIPO_DOCUMENTO_NOME', 100)->nullable(),
        ];
        foreach ($tipoDocColunas as $col => $def) {
            if (Schema::hasTable('TIPO_DOCUMENTO') && !Schema::hasColumn('TIPO_DOCUMENTO', $col)) {
                Schema::table('TIPO_DOCUMENTO', fn(Blueprint $t) => $def($t));
            }
        }
    }

    public function down()
    {
        $drops = [
            'ESCALA' => ['SETOR_ID', 'ESCALA_COMPETENCIA', 'ESCALA_DESCRICAO', 'ESCALA_OBSERVACAO', 'TIPO_ESCALA_ID'],
            'FOLHA' => ['FOLHA_DESCRICAO', 'FOLHA_TIPO', 'VINCULO_ID', 'FOLHA_COMPETENCIA', 'FOLHA_QTD_SERVIDORES', 'FOLHA_VALOR_TOTAL', 'FOLHA_ARQUIVO', 'FOLHA_CHECKSUM'],
            'APLICACAO' => ['APLICACAO_NOME', 'APLICACAO_ICONE', 'APLICACAO_URL', 'APLICACAO_GESTAO', 'APLICACAO_ATIVA', 'APLICACAO_ORDEM', 'APLICACAO_PAI_ID'],
            'PERFIL' => ['PERFIL_NOME', 'PERFIL_DASHBOARD_LINK'],
            'TRIBUTACAO' => ['TRIBUTACAO_NOME', 'TRIBUTACAO_CODIGO', 'TRIBUTACAO_ALIQUOTA', 'TRIBUTACAO_DEDUCAO'],
            'UNIDADE' => ['UNIDADE_NOME', 'UNIDADE_SIGLA', 'UNIDADE_CNPJ'],
            'TIPO_DOCUMENTO' => ['TIPO_DOCUMENTO_NOME'],
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
