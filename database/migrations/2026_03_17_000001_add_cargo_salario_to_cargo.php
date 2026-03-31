<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Sprint 3a — TASK-13
 * Adiciona colunas de tabela salarial à tabela CARGO.
 * Idempotente — usa Schema::hasColumn() antes de cada adição.
 */
class AddCargoSalarioToCargo extends Migration
{
    public function up()
    {
        if (!Schema::hasTable('CARGO'))
            return;

        Schema::table('CARGO', function (Blueprint $table) {
            // Carreira (ex: "Professor", "Administrativo", "Saúde")
            if (!Schema::hasColumn('CARGO', 'CARGO_CARREIRA')) {
                $table->string('CARGO_CARREIRA', 50)->nullable()->after('CARGO_NOME');
            }
            // Classe (ex: "A", "B", "C" ou "I", "II", "III")
            if (!Schema::hasColumn('CARGO', 'CARGO_CLASSE')) {
                $table->string('CARGO_CLASSE', 10)->nullable()->after('CARGO_CARREIRA');
            }
            // Referência salarial dentro da classe (ex: "1", "2", "3")
            if (!Schema::hasColumn('CARGO', 'CARGO_REFERENCIA')) {
                $table->string('CARGO_REFERENCIA', 10)->nullable()->after('CARGO_CLASSE');
            }
            // Nível hierárquico numérico (para ordenação)
            if (!Schema::hasColumn('CARGO', 'CARGO_NIVEL')) {
                $table->integer('CARGO_NIVEL')->nullable()->after('CARGO_REFERENCIA');
            }
            // Vencimento base da referência (salário tabela)
            if (!Schema::hasColumn('CARGO', 'CARGO_SALARIO_BASE')) {
                $table->decimal('CARGO_SALARIO_BASE', 12, 2)->nullable()->after('CARGO_NIVEL');
            }
            // Carga horária mensal contratual (para cálculo de hora-extra)
            if (!Schema::hasColumn('CARGO', 'CARGO_CARGA_HORARIA')) {
                $table->integer('CARGO_CARGA_HORARIA')->nullable()->after('CARGO_SALARIO_BASE');
            }
            // Código CBO para eSocial S-2200
            if (!Schema::hasColumn('CARGO', 'CARGO_CODIGO_CBO')) {
                $table->string('CARGO_CODIGO_CBO', 10)->nullable()->after('CARGO_CARGA_HORARIA');
            }
        });
    }

    public function down()
    {
        if (!Schema::hasTable('CARGO'))
            return;
        Schema::table('CARGO', function (Blueprint $table) {
            foreach ([
                'CARGO_CARREIRA',
                'CARGO_CLASSE',
                'CARGO_REFERENCIA',
                'CARGO_NIVEL',
                'CARGO_SALARIO_BASE',
                'CARGO_CARGA_HORARIA',
                'CARGO_CODIGO_CBO'
            ] as $col) {
                if (Schema::hasColumn('CARGO', $col)) {
                    $table->dropColumn($col);
                }
            }
        });
    }
}
