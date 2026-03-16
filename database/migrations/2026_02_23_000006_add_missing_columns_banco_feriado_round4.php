<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

class AddMissingColumnsBancoFeriadoRound4 extends Migration
{
    /**
     * Colunas faltantes identificadas durante teste de CRUD de inserção (23/02/2026)
     * Raiz: migrations anteriores criaram as tabelas apenas com ID e ATIVO,
     * mas não adicionaram as colunas de dados que os models $fillable referenciam.
     */
    public function up()
    {
        // ── BANCO ────────────────────────────────────────────────────────────
        // Model fillable: BANCO_CODIGO, BANCO_NOME, BANCO_OFICIAL, BANCO_ATIVO
        $bancoColunas = [
            'BANCO_CODIGO' => fn($t) => $t->string('BANCO_CODIGO', 10)->nullable(),
            'BANCO_NOME' => fn($t) => $t->string('BANCO_NOME', 150)->nullable(),
            'BANCO_OFICIAL' => fn($t) => $t->integer('BANCO_OFICIAL')->default(0),
        ];
        foreach ($bancoColunas as $col => $def) {
            if (Schema::hasTable('BANCO') && !Schema::hasColumn('BANCO', $col)) {
                Schema::table('BANCO', fn(Blueprint $t) => $def($t));
            }
        }

        // ── FERIADO ──────────────────────────────────────────────────────────
        // Model fillable: FERIADO_DATA, FERIADO_DESCRICAO, FERIADO_TIPO, FERIADO_ATIVO
        $feriadoColunas = [
            'FERIADO_DATA' => fn($t) => $t->date('FERIADO_DATA')->nullable(),
            'FERIADO_DESCRICAO' => fn($t) => $t->string('FERIADO_DESCRICAO', 200)->nullable(),
            'FERIADO_TIPO' => fn($t) => $t->integer('FERIADO_TIPO')->nullable(),
        ];
        foreach ($feriadoColunas as $col => $def) {
            if (Schema::hasTable('FERIADO') && !Schema::hasColumn('FERIADO', $col)) {
                Schema::table('FERIADO', fn(Blueprint $t) => $def($t));
            }
        }

        // ── CARGO ────────────────────────────────────────────────────────────
        // Model fillable: CARGO_NOME, CARGO_SIGLA, CARGO_ATIVO
        $cargoColunas = [
            'CARGO_NOME' => fn($t) => $t->string('CARGO_NOME', 150)->nullable(),
            'CARGO_SIGLA' => fn($t) => $t->string('CARGO_SIGLA', 20)->nullable(),
        ];
        foreach ($cargoColunas as $col => $def) {
            if (Schema::hasTable('CARGO') && !Schema::hasColumn('CARGO', $col)) {
                Schema::table('CARGO', fn(Blueprint $t) => $def($t));
            }
        }

        // ── FUNCAO ───────────────────────────────────────────────────────────
        $funcaoColunas = [
            'FUNCAO_NOME' => fn($t) => $t->string('FUNCAO_NOME', 150)->nullable(),
            'FUNCAO_SIGLA' => fn($t) => $t->string('FUNCAO_SIGLA', 20)->nullable(),
        ];
        foreach ($funcaoColunas as $col => $def) {
            if (Schema::hasTable('FUNCAO') && !Schema::hasColumn('FUNCAO', $col)) {
                Schema::table('FUNCAO', fn(Blueprint $t) => $def($t));
            }
        }

        // ── CONSELHO ─────────────────────────────────────────────────────────
        $conselhoColunas = [
            'CONSELHO_NOME' => fn($t) => $t->string('CONSELHO_NOME', 100)->nullable(),
            'CONSELHO_SIGLA' => fn($t) => $t->string('CONSELHO_SIGLA', 20)->nullable(),
        ];
        foreach ($conselhoColunas as $col => $def) {
            if (Schema::hasTable('CONSELHO') && !Schema::hasColumn('CONSELHO', $col)) {
                Schema::table('CONSELHO', fn(Blueprint $t) => $def($t));
            }
        }

        // ── OCUPACAO ─────────────────────────────────────────────────────────
        $ocupacaoColunas = [
            'OCUPACAO_NOME' => fn($t) => $t->string('OCUPACAO_NOME', 150)->nullable(),
            'OCUPACAO_CODIGO' => fn($t) => $t->string('OCUPACAO_CODIGO', 20)->nullable(),
        ];
        foreach ($ocupacaoColunas as $col => $def) {
            if (Schema::hasTable('OCUPACAO') && !Schema::hasColumn('OCUPACAO', $col)) {
                Schema::table('OCUPACAO', fn(Blueprint $t) => $def($t));
            }
        }
    }

    public function down()
    {
        $drops = [
            'BANCO' => ['BANCO_CODIGO', 'BANCO_NOME', 'BANCO_OFICIAL'],
            'FERIADO' => ['FERIADO_DATA', 'FERIADO_DESCRICAO', 'FERIADO_TIPO'],
            'CARGO' => ['CARGO_NOME', 'CARGO_SIGLA'],
            'FUNCAO' => ['FUNCAO_NOME', 'FUNCAO_SIGLA'],
            'CONSELHO' => ['CONSELHO_NOME', 'CONSELHO_SIGLA'],
            'OCUPACAO' => ['OCUPACAO_NOME', 'OCUPACAO_CODIGO'],
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
