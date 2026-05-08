<?php

namespace Database\Seeders;

use Illuminate\Database\Seeder;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

/**
 * Seed das rubricas usadas pelo InclusaoHorasExtrasService (GAP-MF-04).
 * Idempotente: usa updateOrInsert por RUBRICA_CODIGO.
 */
class RubricasHePlantaoSeeder extends Seeder
{
    public function run(): void
    {
        if (! Schema::hasTable('RUBRICA')) {
            $this->command->warn('Tabela RUBRICA não existe — seeder ignorado.');
            return;
        }

        $rubricas = [
            ['codigo' => 'HE_50',         'descricao' => 'Hora Extra 50%',          'tipo' => 'P', 'camada' => 3],
            ['codigo' => 'HE_100',        'descricao' => 'Hora Extra 100%',         'tipo' => 'P', 'camada' => 3],
            ['codigo' => 'HE_FER',        'descricao' => 'Hora Extra Feriado',      'tipo' => 'P', 'camada' => 3],
            ['codigo' => 'PLANTAO_EXTRA', 'descricao' => 'Plantão Extra',           'tipo' => 'P', 'camada' => 3],
        ];

        foreach ($rubricas as $r) {
            $payload = [
                'RUBRICA_DESCRICAO' => $r['descricao'],
                'RUBRICA_TIPO' => $r['tipo'],
                'updated_at' => now(),
            ];
            if (Schema::hasColumn('RUBRICA', 'RUBRICA_CAMADA')) {
                $payload['RUBRICA_CAMADA'] = $r['camada'];
            }
            if (Schema::hasColumn('RUBRICA', 'RUBRICA_CALCULO')) {
                $payload['RUBRICA_CALCULO'] = 'fixo'; // HE/Plantão sempre vêm com valor já calculado
            }
            if (Schema::hasColumn('RUBRICA', 'RUBRICA_ATIVO')) {
                $payload['RUBRICA_ATIVO'] = 1;
            }

            DB::table('RUBRICA')->updateOrInsert(
                ['RUBRICA_CODIGO' => $r['codigo']],
                $payload + ['created_at' => now()]
            );
        }
    }
}
