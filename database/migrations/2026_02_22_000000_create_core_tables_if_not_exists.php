<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;
use Illuminate\Support\Facades\DB;

/**
 * Cria as tabelas core do sistema que não existem ainda no banco Docker de dev.
 * Em produção essas tabelas já existem — o up() usa CREATE TABLE IF NOT EXISTS
 * (via Schema::hasTable) para ser seguro.
 */
class CreateCoreTablesIfNotExists extends Migration
{
    public function up()
    {
        // ── TABELA_GENERICA ────────────────────────────────────────────────
        if (!Schema::hasTable('TABELA_GENERICA')) {
            Schema::create('TABELA_GENERICA', function (Blueprint $table) {
                $table->integer('TABELA_ID');
                $table->integer('COLUNA_ID');
                $table->string('DESCRICAO', 200)->nullable();
                $table->integer('ATIVO')->default(1);
                $table->primary(['TABELA_ID', 'COLUNA_ID']);
            });
        }

        // ── PERFIL ────────────────────────────────────────────────────────
        if (!Schema::hasTable('PERFIL')) {
            Schema::create('PERFIL', function (Blueprint $table) {
                $table->integer('PERFIL_ID')->autoIncrement();
                $table->string('PERFIL_NOME', 100);
                $table->integer('PERFIL_ATIVO')->default(1);
            });
        }

        // ── APLICACAO ─────────────────────────────────────────────────────
        if (!Schema::hasTable('APLICACAO')) {
            Schema::create('APLICACAO', function (Blueprint $table) {
                $table->integer('APLICACAO_ID')->autoIncrement();
                $table->string('APLICACAO_NOME', 100);
                $table->string('APLICACAO_ICONE', 60)->nullable();
                $table->string('APLICACAO_URL', 200)->nullable();
                $table->integer('APLICACAO_PAI_ID')->nullable();
                $table->integer('APLICACAO_ORDEM')->default(99);
                $table->integer('APLICACAO_ATIVA')->default(1);
                $table->integer('APLICACAO_GESTAO')->default(0);
            });
        }

        // ── ACESSO ────────────────────────────────────────────────────────
        if (!Schema::hasTable('ACESSO')) {
            Schema::create('ACESSO', function (Blueprint $table) {
                $table->integer('ACESSO_ID')->autoIncrement();
                $table->integer('APLICACAO_ID');
                $table->integer('PERFIL_ID');
                $table->integer('ACESSO_ATIVO')->default(1);
                $table->unique(['APLICACAO_ID', 'PERFIL_ID']);
            });
        }

        // ── PESSOA ────────────────────────────────────────────────────────
        if (!Schema::hasTable('PESSOA')) {
            Schema::create('PESSOA', function (Blueprint $table) {
                $table->integer('PESSOA_ID')->autoIncrement();
                $table->string('PESSOA_NOME', 200);
                $table->string('PESSOA_CPF', 14)->nullable();
                $table->date('PESSOA_NASC')->nullable();
                $table->integer('PESSOA_SEXO')->nullable();
                $table->integer('ESCOLARIDADE_ID')->nullable();
                $table->integer('ESTADO_CIVIL')->nullable();
                $table->integer('TIPO_SANGUINEO')->nullable();
                $table->integer('FATOR_RH')->nullable();
                $table->string('PESSOA_RG', 30)->nullable();
                $table->string('PESSOA_ORG_EMISSOR', 20)->nullable();
                $table->date('PESSOA_DATA_EXPEDICAO')->nullable();
                $table->string('PESSOA_PIS_PASEP', 20)->nullable();
                $table->string('PESSOA_TITULO_ELEITOR', 30)->nullable();
                $table->string('PESSOA_ZONA', 10)->nullable();
                $table->string('PESSOA_SECAO', 10)->nullable();
                $table->string('PESSOA_MUN_TÍTULO_ELEITOR', 50)->nullable();
                $table->string('PESSOA_FOTO', 200)->nullable();
                $table->integer('PESSOA_ATIVO')->default(1);
            });
        }

        // ── USUARIO ───────────────────────────────────────────────────────
        if (!Schema::hasTable('USUARIO')) {
            Schema::create('USUARIO', function (Blueprint $table) {
                $table->integer('USUARIO_ID')->autoIncrement();
                $table->string('USUARIO_NOME', 200);
                $table->string('USUARIO_LOGIN', 100)->unique();
                $table->string('USUARIO_SENHA', 255);
                $table->integer('PERFIL_ID')->nullable();
                $table->integer('USUARIO_ATIVO')->default(1);
            });
        }

        // ── USUARIO_PERFIL ─────────────────────────────────────────────────
        if (!Schema::hasTable('USUARIO_PERFIL')) {
            Schema::create('USUARIO_PERFIL', function (Blueprint $table) {
                $table->integer('USUARIO_PERFIL_ID')->autoIncrement();
                $table->integer('USUARIO_ID');
                $table->integer('PERFIL_ID');
                $table->integer('USUARIO_PERFIL_ATIVO')->default(1);
            });
        }

        // ── UNIDADE ───────────────────────────────────────────────────────
        if (!Schema::hasTable('UNIDADE')) {
            Schema::create('UNIDADE', function (Blueprint $table) {
                $table->integer('UNIDADE_ID')->autoIncrement();
                $table->string('UNIDADE_NOME', 200);
                $table->string('UNIDADE_SIGLA', 20)->nullable();
                $table->integer('TIPO_UNIDADE')->nullable();
                $table->integer('PORTE_UNIDADE')->nullable();
                $table->integer('UNIDADE_ATIVA')->default(1);
            });
        }

        // ── SETOR ─────────────────────────────────────────────────────────
        if (!Schema::hasTable('SETOR')) {
            Schema::create('SETOR', function (Blueprint $table) {
                $table->integer('SETOR_ID')->autoIncrement();
                $table->string('SETOR_NOME', 200);
                $table->integer('UNIDADE_ID');
                $table->integer('SETOR_ATIVO')->default(1);
            });
        }

        // ── FUNCIONARIO ───────────────────────────────────────────────────
        if (!Schema::hasTable('FUNCIONARIO')) {
            Schema::create('FUNCIONARIO', function (Blueprint $table) {
                $table->integer('FUNCIONARIO_ID')->autoIncrement();
                $table->integer('PESSOA_ID');
                $table->string('FUNCIONARIO_MATRICULA', 30)->nullable();
                $table->integer('VINCULO_ID')->nullable();
                $table->date('FUNCIONARIO_DATA_INICIO')->nullable();
                $table->date('FUNCIONARIO_DATA_FIM')->nullable();
                $table->integer('FUNCIONARIO_ATIVO')->default(1);
            });
        }

        // ── LOTACAO ───────────────────────────────────────────────────────
        if (!Schema::hasTable('LOTACAO')) {
            Schema::create('LOTACAO', function (Blueprint $table) {
                $table->integer('LOTACAO_ID')->autoIncrement();
                $table->integer('FUNCIONARIO_ID');
                $table->integer('SETOR_ID');
                $table->date('LOTACAO_DATA_INICIO')->nullable();
                $table->date('LOTACAO_DATA_FIM')->nullable();
            });
        }

        // ── FERIAS ────────────────────────────────────────────────────────
        if (!Schema::hasTable('FERIAS')) {
            Schema::create('FERIAS', function (Blueprint $table) {
                $table->integer('FERIAS_ID')->autoIncrement();
                $table->integer('FUNCIONARIO_ID');
                $table->date('FERIAS_DATA_INICIO')->nullable();
                $table->date('FERIAS_DATA_FIM')->nullable();
                $table->integer('FERIAS_AQUISITIVO_INICIO')->nullable();
                $table->integer('FERIAS_AQUISITIVO_FIM')->nullable();
            });
        }

        // ── AFASTAMENTO ───────────────────────────────────────────────────
        if (!Schema::hasTable('AFASTAMENTO')) {
            Schema::create('AFASTAMENTO', function (Blueprint $table) {
                $table->integer('AFASTAMENTO_ID')->autoIncrement();
                $table->integer('FUNCIONARIO_ID');
                $table->date('AFASTAMENTO_DATA_INICIO')->nullable();
                $table->date('AFASTAMENTO_DATA_FIM')->nullable();
                $table->integer('AFASTAMENTO_TIPO')->nullable();
            });
        }
    }

    public function down()
    {
        // Não derrubamos as tabelas em produção — down() é no-op intencional
    }
}
