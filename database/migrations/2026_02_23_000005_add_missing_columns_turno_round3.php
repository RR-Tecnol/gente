<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

class AddMissingColumnsTurnoRound3 extends Migration
{
    public function up()
    {
        // Colunas faltantes na tabela TURNO
        // O model usa: TURNO_SIGLA, TURNO_DESCRICAO, TURNO_HORA_INICIO,
        //              TURNO_HORA_FIM, TURNO_INTERVALO, TURNO_DATA_EXCLUSAO
        $turnoColumns = [
            'TURNO_DESCRICAO' => fn($t) => $t->string('TURNO_DESCRICAO', 100)->nullable(),
            'TURNO_SIGLA' => fn($t) => $t->string('TURNO_SIGLA', 20)->nullable(),
            'TURNO_HORA_INICIO' => fn($t) => $t->string('TURNO_HORA_INICIO', 8)->nullable(),
            'TURNO_HORA_FIM' => fn($t) => $t->string('TURNO_HORA_FIM', 8)->nullable(),
            'TURNO_INTERVALO' => fn($t) => $t->integer('TURNO_INTERVALO')->nullable(),
            'TURNO_DATA_EXCLUSAO' => fn($t) => $t->date('TURNO_DATA_EXCLUSAO')->nullable(),
        ];

        foreach ($turnoColumns as $column => $definition) {
            if (Schema::hasTable('TURNO') && !Schema::hasColumn('TURNO', $column)) {
                Schema::table('TURNO', function (Blueprint $table) use ($definition) {
                    $definition($table);
                });
            }
        }

        // Colunas faltantes na tabela FERIADO
        // TipoDocumento pode ter colunas faltantes também
        if (Schema::hasTable('FERIADO') && !Schema::hasColumn('FERIADO', 'FERIADO_DATA_EXCLUSAO')) {
            Schema::table('FERIADO', function (Blueprint $table) {
                $table->date('FERIADO_DATA_EXCLUSAO')->nullable();
            });
        }
    }

    public function down()
    {
        $drops = [
            'TURNO' => [
                'TURNO_DESCRICAO',
                'TURNO_SIGLA',
                'TURNO_HORA_INICIO',
                'TURNO_HORA_FIM',
                'TURNO_INTERVALO',
                'TURNO_DATA_EXCLUSAO'
            ],
            'FERIADO' => ['FERIADO_DATA_EXCLUSAO'],
        ];
        foreach ($drops as $table => $columns) {
            foreach ($columns as $column) {
                if (Schema::hasTable($table) && Schema::hasColumn($table, $column)) {
                    Schema::table($table, fn($t) => $t->dropColumn($column));
                }
            }
        }
    }
}
