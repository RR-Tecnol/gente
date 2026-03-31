<?php

namespace Database\Seeders;

use Illuminate\Database\Seeder;
use Illuminate\Support\Facades\DB;

class SagresDeParaSeeder extends Seeder
{
    public function run(): void
    {
        if (DB::table('SAGRES_EVENTO_DEPARA')->whereNotNull('RUBRICA_SISTEMA')->count() > 0) {
            $this->command->info('De-para SAGRES já populado — seed ignorado.');
            return;
        }

        // Mapeamento: rubricas internas do sistema → código SAGRES TCE-MA
        // Tipo P = Provento | D = Desconto
        $depara = [
            // Proventos
            ['RUBRICA_SISTEMA' => '0001', 'SAGRES_COD' => 'VENC',   'SAGRES_DESCRICAO' => 'Vencimento Base',               'TIPO' => 'P'],
            ['RUBRICA_SISTEMA' => '0010', 'SAGRES_COD' => 'GRAT',   'SAGRES_DESCRICAO' => 'Gratificação',                  'TIPO' => 'P'],
            ['RUBRICA_SISTEMA' => '0020', 'SAGRES_COD' => 'ADIC',   'SAGRES_DESCRICAO' => 'Adicional de Tempo de Serviço', 'TIPO' => 'P'],
            ['RUBRICA_SISTEMA' => '0030', 'SAGRES_COD' => 'HEXT',   'SAGRES_DESCRICAO' => 'Horas Extras',                  'TIPO' => 'P'],
            ['RUBRICA_SISTEMA' => '0040', 'SAGRES_COD' => '13SA',   'SAGRES_DESCRICAO' => '13° Salário',                   'TIPO' => 'P'],
            ['RUBRICA_SISTEMA' => '0050', 'SAGRES_COD' => 'FERI',   'SAGRES_DESCRICAO' => 'Férias',                        'TIPO' => 'P'],
            ['RUBRICA_SISTEMA' => '0060', 'SAGRES_COD' => 'INSA',   'SAGRES_DESCRICAO' => 'Insalubridade',                 'TIPO' => 'P'],
            ['RUBRICA_SISTEMA' => '0070', 'SAGRES_COD' => 'PERI',   'SAGRES_DESCRICAO' => 'Periculosidade',                'TIPO' => 'P'],
            // Descontos
            ['RUBRICA_SISTEMA' => '1001', 'SAGRES_COD' => 'RPPS',   'SAGRES_DESCRICAO' => 'Contribuição RPPS/IPAM',        'TIPO' => 'D'],
            ['RUBRICA_SISTEMA' => '1002', 'SAGRES_COD' => 'IRRF',   'SAGRES_DESCRICAO' => 'IRRF',                          'TIPO' => 'D'],
            ['RUBRICA_SISTEMA' => '1010', 'SAGRES_COD' => 'CONS',   'SAGRES_DESCRICAO' => 'Consignação em Folha',          'TIPO' => 'D'],
            ['RUBRICA_SISTEMA' => '1020', 'SAGRES_COD' => 'FALT',   'SAGRES_DESCRICAO' => 'Desconto por Faltas',           'TIPO' => 'D'],
            ['RUBRICA_SISTEMA' => '1030', 'SAGRES_COD' => 'PLAN',   'SAGRES_DESCRICAO' => 'Plano de Saúde',                'TIPO' => 'D'],
        ];

        foreach ($depara as $row) {
            DB::table('SAGRES_EVENTO_DEPARA')->insert([
                'RUBRICA_SISTEMA'      => $row['RUBRICA_SISTEMA'],
                'EVENTO_INTERNO_COD'   => $row['RUBRICA_SISTEMA'],
                'EVENTO_INTERNO_NOME'  => $row['SAGRES_DESCRICAO'],
                'SAGRES_COD'           => $row['SAGRES_COD'],
                'SAGRES_DESCRICAO'     => $row['SAGRES_DESCRICAO'],
                'TIPO'                 => $row['TIPO'],
                'ATIVO'                => true,
                'created_at'           => now(),
                'updated_at'           => now(),
            ]);
        }

        $this->command->info('SAGRES de-para seedado com ' . count($depara) . ' rubricas.');
    }
}
