<?php

namespace Database\Seeders;

use Illuminate\Database\Seeder;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

class TurnosBaseSeeder extends Seeder
{
    /**
     * Run the database seeds.
     *
     * @return void
     */
    public function run()
    {
        $turnos = [
            ['TURNO_SIGLA'=>'M', 'TURNO_DESCRICAO'=>'Matutino',    'TURNO_HORA_INICIO'=>'07:00','TURNO_HORA_FIM'=>'13:00','TURNO_CARGA_HORARIA'=>6, 'TURNO_INTERVALO'=>0,  'TURNO_ATIVO'=>1],
            ['TURNO_SIGLA'=>'V', 'TURNO_DESCRICAO'=>'Vespertino',   'TURNO_HORA_INICIO'=>'13:00','TURNO_HORA_FIM'=>'19:00','TURNO_CARGA_HORARIA'=>6, 'TURNO_INTERVALO'=>0,  'TURNO_ATIVO'=>1],
            ['TURNO_SIGLA'=>'N', 'TURNO_DESCRICAO'=>'Noturno',      'TURNO_HORA_INICIO'=>'19:00','TURNO_HORA_FIM'=>'22:00','TURNO_CARGA_HORARIA'=>3, 'TURNO_INTERVALO'=>0,  'TURNO_ATIVO'=>1],
            ['TURNO_SIGLA'=>'I', 'TURNO_DESCRICAO'=>'Integral',     'TURNO_HORA_INICIO'=>'07:00','TURNO_HORA_FIM'=>'17:00','TURNO_CARGA_HORARIA'=>8, 'TURNO_INTERVALO'=>60, 'TURNO_ATIVO'=>1],
            ['TURNO_SIGLA'=>'F', 'TURNO_DESCRICAO'=>'Folga',        'TURNO_HORA_INICIO'=>'00:00','TURNO_HORA_FIM'=>'00:00','TURNO_CARGA_HORARIA'=>0, 'TURNO_INTERVALO'=>0,  'TURNO_ATIVO'=>1],
            ['TURNO_SIGLA'=>'SO','TURNO_DESCRICAO'=>'Sobreaviso',   'TURNO_HORA_INICIO'=>'00:00','TURNO_HORA_FIM'=>'00:00','TURNO_CARGA_HORARIA'=>0, 'TURNO_INTERVALO'=>0,  'TURNO_ATIVO'=>1],
            ['TURNO_SIGLA'=>'AT','TURNO_DESCRICAO'=>'Afastamento',  'TURNO_HORA_INICIO'=>'00:00','TURNO_HORA_FIM'=>'00:00','TURNO_CARGA_HORARIA'=>0, 'TURNO_INTERVALO'=>0,  'TURNO_ATIVO'=>1],
        ];

        $hasExclusao = Schema::hasColumn('TURNO', 'TURNO_DATA_EXCLUSAO');

        foreach ($turnos as $turno) {
            if ($hasExclusao) {
                $turno['TURNO_DATA_EXCLUSAO'] = null;
            }

            DB::table('TURNO')->updateOrInsert(
                ['TURNO_SIGLA' => $turno['TURNO_SIGLA']],
                $turno
            );
        }
    }
}
