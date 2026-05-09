<?php

namespace Database\Seeders;

use Illuminate\Database\Seeder;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

/**
 * Seed dos eventos básicos usados pelo MotorFolhaService.
 * Idempotente: usa updateOrInsert pelo nome do evento.
 *
 * Defensivo:
 *  - Detecta nome da coluna de descrição (EVENTO_NOME ou EVENTO_DESCRICAO)
 *  - Só preenche colunas que existem no schema
 *  - Sempre preenche colunas NOT NULL com defaults seguros
 */
class EventosBaseSeeder extends Seeder
{
    public function run(): void
    {
        if (! Schema::hasTable('EVENTO')) {
            $this->command->warn('Tabela EVENTO não existe — seeder ignorado.');
            return;
        }

        // Detecta nome da coluna principal (PMSL: EVENTO_NOME, legado: EVENTO_DESCRICAO)
        $colNome = Schema::hasColumn('EVENTO', 'EVENTO_NOME')
            ? 'EVENTO_NOME'
            : (Schema::hasColumn('EVENTO', 'EVENTO_DESCRICAO') ? 'EVENTO_DESCRICAO' : null);

        if (!$colNome) {
            $this->command->error('EVENTO sem coluna de nome — seeder abortado.');
            return;
        }

        // Detecta colunas opcionais
        $temIncidencia = Schema::hasColumn('EVENTO', 'EVENTO_INCIDENCIA');
        $temSistema    = Schema::hasColumn('EVENTO', 'EVENTO_SISTEMA');
        $temIncideINSS = Schema::hasColumn('EVENTO', 'EVENTO_INCIDE_INSS');
        $temIncideIRRF = Schema::hasColumn('EVENTO', 'EVENTO_INCIDE_IRRF');
        $temIncideRPPS = Schema::hasColumn('EVENTO', 'EVENTO_INCIDE_RPPS');

        // Eventos básicos: nome, salario, imposto, incide_inss, incide_irrf, incide_rpps
        $eventos = [
            // Proventos C1
            ['nome' => 'VENCIMENTO BASE',            'salario' => 1, 'imposto' => 0, 'inss' => 1, 'irrf' => 1, 'rpps' => 1],
            ['nome' => 'ANUENIO',                    'salario' => 1, 'imposto' => 0, 'inss' => 1, 'irrf' => 1, 'rpps' => 1],

            // Descontos previdenciários
            ['nome' => 'INSS RPPS',                  'salario' => 0, 'imposto' => 1, 'inss' => 0, 'irrf' => 0, 'rpps' => 0],
            ['nome' => 'INSS RGPS',                  'salario' => 0, 'imposto' => 1, 'inss' => 0, 'irrf' => 0, 'rpps' => 0],

            // Imposto de renda
            ['nome' => 'IRRF',                       'salario' => 0, 'imposto' => 1, 'inss' => 0, 'irrf' => 0, 'rpps' => 0],

            // Outros descontos
            ['nome' => 'CONSIGNACOES',               'salario' => 0, 'imposto' => 0, 'inss' => 0, 'irrf' => 0, 'rpps' => 0],
            ['nome' => 'COMPLEMENTO SALARIO MINIMO', 'salario' => 1, 'imposto' => 0, 'inss' => 1, 'irrf' => 1, 'rpps' => 1],
        ];

        foreach ($eventos as $e) {
            $payload = [
                'EVENTO_SALARIO' => $e['salario'],
                'EVENTO_IMPOSTO' => $e['imposto'],
                'EVENTO_ATIVO'   => 1,
            ];

            if ($temSistema)    $payload['EVENTO_SISTEMA']     = 1;
            if ($temIncidencia) $payload['EVENTO_INCIDENCIA']  = 0;
            if ($temIncideINSS) $payload['EVENTO_INCIDE_INSS'] = $e['inss'];
            if ($temIncideIRRF) $payload['EVENTO_INCIDE_IRRF'] = $e['irrf'];
            if ($temIncideRPPS) $payload['EVENTO_INCIDE_RPPS'] = $e['rpps'];

            DB::table('EVENTO')->updateOrInsert(
                [$colNome => $e['nome']],
                $payload
            );
        }

        $this->command->info('EventosBaseSeeder: ' . count($eventos) . ' eventos básicos garantidos.');
    }
}
