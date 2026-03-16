<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Correção em massa de dois problemas identificados por varredura sistemática:
 *
 * 1. COLUNA _ATIVO vs _ATIVA: as migrations anteriores criaram flags como
 *    ATRIBUICAO_ATIVO mas o Model usa ATRIBUICAO_ATIVA (padrão feminino do sistema).
 *    Solução: adicionar a coluna no nome correto (_ATIVA) — a _ATIVO fica como null/ignored.
 *
 * 2. TABELAS FALTANTES: TRIBUTACAO, PROFISSAO, PESSOA_PROFISSAO, STATUS_ESCALA,
 *    VIGENCIA_IMPOSTO, TABELA_IMPOSTO, PROGRAMA, SCRIPT, PARAMETRO_FINANCEIRO,
 *    HISTORICO_PARAMETRO, HISTORICO_EVENTO, HIST_ATRIBUICAO_CONFIG, ATRIBUICAO_LOTACAO_EVENTO.
 */
class FixColumnNamesAndAddMissingTables extends Migration
{
    public function up()
    {
        // ──────────────────────────────────────────────────────────────────
        // 1. CORRIGIR COLUNAS _ATIVO → _ATIVA NAS TABELAS EXISTENTES
        // ──────────────────────────────────────────────────────────────────

        $fixes = [
            // [tabela, coluna errada, coluna correta]
            ['ATRIBUICAO', 'ATRIBUICAO_ATIVO', 'ATRIBUICAO_ATIVA'],
            ['ATRIBUICAO_CONFIG', 'ATRIBUICAO_CONFIG_ATIVO', 'ATRIBUICAO_CONFIG_ATIVA'],
            ['FUNCAO', 'FUNCAO_ATIVO', 'FUNCAO_ATIVA'],
            ['OCUPACAO', 'OCUPACAO_ATIVO', 'OCUPACAO_ATIVA'],
            ['FIM_LOTACAO', null, 'FIM_LOTACAO_ATIVA'],
            ['UNIDADE', 'UNIDADE_ATIVO', 'UNIDADE_ATIVA'],
            ['ATRIBUICAO_LOTACAO', 'ATRIBUICAO_LOTACAO_ATIVO', 'ATRIBUICAO_LOTACAO_ATIVA'],
        ];

        foreach ($fixes as [$tabela, $colunaErrada, $colunaCorreta]) {
            if (!Schema::hasTable($tabela))
                continue;
            if (Schema::hasColumn($tabela, $colunaCorreta))
                continue;

            Schema::table($tabela, function (Blueprint $table) use ($colunaCorreta) {
                $table->integer($colunaCorreta)->default(1)->nullable();
            });
        }

        // ──────────────────────────────────────────────────────────────────
        // 2. TABELAS FALTANTES
        // ──────────────────────────────────────────────────────────────────

        // STATUS_ESCALA
        if (!Schema::hasTable('STATUS_ESCALA')) {
            Schema::create('STATUS_ESCALA', function (Blueprint $table) {
                $table->integer('STATUS_ESCALA_ID')->autoIncrement();
                $table->string('STATUS_ESCALA_NOME', 100);
                $table->string('STATUS_ESCALA_COR', 20)->nullable();
                $table->integer('STATUS_ESCALA_ATIVA')->default(1);
            });
        }

        // PROFISSAO
        if (!Schema::hasTable('PROFISSAO')) {
            Schema::create('PROFISSAO', function (Blueprint $table) {
                $table->integer('PROFISSAO_ID')->autoIncrement();
                $table->string('PROFISSAO_NOME', 200);
                $table->integer('PROFISSAO_ATIVA')->default(1);
            });
        }

        // PESSOA_PROFISSAO
        if (!Schema::hasTable('PESSOA_PROFISSAO')) {
            Schema::create('PESSOA_PROFISSAO', function (Blueprint $table) {
                $table->integer('PESSOA_PROFISSAO_ID')->autoIncrement();
                $table->integer('PESSOA_ID');
                $table->integer('PROFISSAO_ID');
                $table->integer('PESSOA_PROFISSAO_ATIVA')->default(1);
            });
        }

        // TRIBUTACAO
        if (!Schema::hasTable('TRIBUTACAO')) {
            Schema::create('TRIBUTACAO', function (Blueprint $table) {
                $table->integer('TRIBUTACAO_ID')->autoIncrement();
                $table->string('TRIBUTACAO_NOME', 200);
                $table->integer('TRIBUTACAO_ATIVA')->default(1);
            });
        }

        // TABELA_IMPOSTO
        if (!Schema::hasTable('TABELA_IMPOSTO')) {
            Schema::create('TABELA_IMPOSTO', function (Blueprint $table) {
                $table->integer('TABELA_IMPOSTO_ID')->autoIncrement();
                $table->integer('TRIBUTACAO_ID')->nullable();
                $table->decimal('TABELA_IMPOSTO_FAIXA_INI', 12, 2)->nullable();
                $table->decimal('TABELA_IMPOSTO_FAIXA_FIM', 12, 2)->nullable();
                $table->decimal('TABELA_IMPOSTO_ALIQUOTA', 8, 4)->nullable();
                $table->decimal('TABELA_IMPOSTO_DEDUCAO', 12, 2)->nullable();
                $table->string('TABELA_IMPOSTO_COMPETENCIA', 7)->nullable();
            });
        }

        // VIGENCIA_IMPOSTO
        if (!Schema::hasTable('VIGENCIA_IMPOSTO')) {
            Schema::create('VIGENCIA_IMPOSTO', function (Blueprint $table) {
                $table->integer('VIGENCIA_IMPOSTO_ID')->autoIncrement();
                $table->integer('TRIBUTACAO_ID')->nullable();
                $table->string('VIGENCIA_IMPOSTO_COMPETENCIA', 7)->nullable();
                $table->integer('VIGENCIA_IMPOSTO_ATIVO')->default(1);
            });
        }

        // PARAMETRO_FINANCEIRO
        if (!Schema::hasTable('PARAMETRO_FINANCEIRO')) {
            Schema::create('PARAMETRO_FINANCEIRO', function (Blueprint $table) {
                $table->integer('PARAMETRO_FINANCEIRO_ID')->autoIncrement();
                $table->string('PARAMETRO_FINANCEIRO_NOME', 200);
                $table->decimal('PARAMETRO_FINANCEIRO_VALOR', 15, 4)->nullable();
                $table->string('PARAMETRO_FINANCEIRO_COMPETENCIA', 7)->nullable();
                $table->integer('PARAMETRO_FINANCEIRO_ATIVO')->default(1);
            });
        }

        // HISTORICO_PARAMETRO
        if (!Schema::hasTable('HISTORICO_PARAMETRO')) {
            Schema::create('HISTORICO_PARAMETRO', function (Blueprint $table) {
                $table->integer('HISTORICO_PARAMETRO_ID')->autoIncrement();
                $table->integer('PARAMETRO_FINANCEIRO_ID');
                $table->integer('USUARIO_ID')->nullable();
                $table->dateTime('HISTORICO_PARAMETRO_DATA')->nullable();
                $table->text('HISTORICO_PARAMETRO_OBSERVACAO')->nullable();
            });
        }

        // HISTORICO_EVENTO
        if (!Schema::hasTable('HISTORICO_EVENTO')) {
            Schema::create('HISTORICO_EVENTO', function (Blueprint $table) {
                $table->integer('HISTORICO_EVENTO_ID')->autoIncrement();
                $table->integer('EVENTO_ID');
                $table->integer('USUARIO_ID')->nullable();
                $table->dateTime('HISTORICO_EVENTO_DATA')->nullable();
                $table->text('HISTORICO_EVENTO_OBSERVACAO')->nullable();
            });
        }

        // HIST_ATRIBUICAO_CONFIG
        if (!Schema::hasTable('HIST_ATRIBUICAO_CONFIG')) {
            Schema::create('HIST_ATRIBUICAO_CONFIG', function (Blueprint $table) {
                $table->integer('HIST_ATRIBUICAO_CONFIG_ID')->autoIncrement();
                $table->integer('ATRIBUICAO_CONFIG_ID');
                $table->integer('USUARIO_ID')->nullable();
                $table->dateTime('HIST_ATRIBUICAO_CONFIG_DATA')->nullable();
                $table->text('HIST_ATRIBUICAO_CONFIG_DESCRICAO')->nullable();
            });
        }

        // ATRIBUICAO_LOTACAO_EVENTO
        if (!Schema::hasTable('ATRIBUICAO_LOTACAO_EVENTO')) {
            Schema::create('ATRIBUICAO_LOTACAO_EVENTO', function (Blueprint $table) {
                $table->integer('ATRIBUICAO_LOTACAO_EVENTO_ID')->autoIncrement();
                $table->integer('ATRIBUICAO_LOTACAO_ID');
                $table->integer('EVENTO_ID');
                $table->integer('ATRIBUICAO_LOTACAO_EVENTO_ATIVO')->default(1);
            });
        }

        // PROGRAMA (scripts de implantação)
        if (!Schema::hasTable('PROGRAMA')) {
            Schema::create('PROGRAMA', function (Blueprint $table) {
                $table->integer('PROGRAMA_ID')->autoIncrement();
                $table->string('PROGRAMA_NOME', 200);
                $table->string('PROGRAMA_VERSAO', 20)->nullable();
                $table->integer('PROGRAMA_ATIVO')->default(1);
            });
        }

        // SCRIPT (blocos SQL do módulo Scripts SQL do menu)
        if (!Schema::hasTable('SCRIPT')) {
            Schema::create('SCRIPT', function (Blueprint $table) {
                $table->integer('SCRIPT_ID')->autoIncrement();
                $table->string('SCRIPT_NOME', 200);
                $table->text('SCRIPT_SQL');
                $table->integer('USUARIO_ID')->nullable();
                $table->dateTime('SCRIPT_DATA')->nullable();
                $table->integer('SCRIPT_ATIVO')->default(1);
            });
        }
    }

    public function down()
    {
        // No-op intencional
    }
}
