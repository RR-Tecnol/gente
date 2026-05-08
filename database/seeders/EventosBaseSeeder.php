<?php

namespace Database\Seeders;

use Illuminate\Database\Seeder;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

/**
 * Seed dos eventos básicos usados pelo MotorFolhaService.
 * Idempotente: usa updateOrInsert por EVENTO_DESCRICAO.
 */
class EventosBaseSeeder extends Seeder
{
    public function run(): void
    {
        if (! Schema::hasTable('EVENTO')) {
            $this->command->warn('Tabela EVENTO não existe — seeder ignorado.');
            return;
        }

        $eventos = [
            // Proventos C1
            ['descricao' => 'VENCIMENTO BASE',                'salario' => 1, 'imposto' => 0],
            ['descricao' => 'ANUENIO',                        'salario' => 1, 'imposto' => 0],

            // Descontos previdenciários
            ['descricao' => 'INSS RPPS',                      'salario' => 0, 'imposto' => 1],
            ['descricao' => 'INSS RGPS',                      'salario' => 0, 'imposto' => 1],

            // Imposto de renda
            ['descricao' => 'IRRF',                           'salario' => 0, 'imposto' => 1],

            // Outros descontos
            ['descricao' => 'CONSIGNACOES',                   'salario' => 0, 'imposto' => 0],
            ['descricao' => 'COMPLEMENTO SALARIO MINIMO',     'salario' => 1, 'imposto' => 0],
        ];

        foreach ($eventos as $e) {
            $payload = [
                'EVENTO_SALARIO' => $e['salario'],
                'EVENTO_IMPOSTO' => $e['imposto'],
                'EVENTO_INCIDENCIA' => 0,
                'EVENTO_SISTEMA' => 1,
                'EVENTO_ATIVO' => 1,
            ];

            DB::table('EVENTO')->updateOrInsert(
                ['EVENTO_DESCRICAO' => $e['descricao']],
                $payload
            );
        }

        $this->command->info('EventosBaseSeeder: eventos básicos garantidos.');
    }
}
