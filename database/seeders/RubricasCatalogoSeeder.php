<?php

namespace Database\Seeders;

use Illuminate\Database\Seeder;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

class RubricasCatalogoSeeder extends Seeder
{
    public function run(): void
    {
        // [codigo, descricao, tipo, camada, calculo, prev, irrf, fgts, sagres, ordem]
        $rubricas = [
            ['001', 'Vencimento Base', 'P', 1, 'tabela_salarial', 1, 1, 0, '01001', 1],
            ['002', 'Anuênio / Adicional por Tempo de Serviço', 'P', 1, 'percentual_base', 1, 1, 0, '01002', 2],
            ['010', 'Gratificação de Função (FC)', 'P', 2, 'fixo', 1, 1, 0, '01010', 10],
            ['011', 'Vantagem Pessoal Incorporada (Art.17 ADCT)', 'P', 2, 'fixo', 1, 1, 0, '01011', 11],
            ['020', 'Adicional de Insalubridade 20%', 'P', 2, 'percentual_sm', 0, 1, 0, '01020', 20],
            ['021', 'Adicional de Insalubridade 40%', 'P', 2, 'percentual_sm', 0, 1, 0, '01021', 21],
            ['022', 'Adicional de Periculosidade 30%', 'P', 2, 'percentual_base', 0, 1, 0, '01022', 22],
            ['023', 'Adicional Noturno', 'P', 2, 'percentual_base', 1, 1, 0, '01023', 23],
            ['024', 'Adicional de Urgência e Emergência', 'P', 2, 'fixo', 1, 1, 0, '01024', 24],
            ['025', 'Adicional de Saúde', 'P', 2, 'fixo', 1, 1, 0, '01025', 25],
            ['026', 'Adicional de Informática', 'P', 2, 'fixo', 1, 1, 0, '01026', 26],
            ['030', 'Hora Extra 50%', 'P', 3, 'percentual_base', 1, 1, 0, '01030', 30],
            ['031', 'Hora Extra 100%', 'P', 3, 'percentual_base', 1, 1, 0, '01031', 31],
            ['032', 'Plantão Extra', 'P', 3, 'fixo', 1, 1, 0, '01032', 32],
            ['033', 'Substituição (FC temporária)', 'P', 3, 'fixo', 1, 1, 0, '01033', 33],
            ['040', 'Diária', 'P', 3, 'fixo', 0, 0, 0, '01040', 40],
            ['041', 'Ajuda de Custo', 'P', 3, 'fixo', 0, 0, 0, '01041', 41],
            ['042', 'Vale Transporte', 'P', 3, 'fixo', 0, 0, 0, '01042', 42],
            ['900', 'Desconto RPPS (IPAM)', 'D', 1, 'inss_rpps', 0, 0, 0, '09001', 90],
            ['901', 'Desconto RGPS (INSS)', 'D', 1, 'inss_rgps', 0, 0, 0, '09002', 91],
            ['902', 'Desconto IRRF', 'D', 1, 'irrf', 0, 0, 0, '09003', 92],
            ['903', 'Desconto Consignação (Empréstimo)', 'D', 3, 'fixo', 0, 0, 0, '09004', 93],
            ['904', 'Desconto Consignação (Cartão)', 'D', 3, 'fixo', 0, 0, 0, '09005', 94],
            ['905', 'Pensão Alimentícia Judicial', 'D', 3, 'percentual_base', 0, 0, 0, '09006', 95],
            ['906', 'Desconto Faltas', 'D', 3, 'fixo', 0, 0, 0, '09007', 96],
            ['907', 'FGTS (recolhimento patronal)', 'D', 1, 'percentual_base', 0, 0, 1, '09008', 97],
        ];

        $temCamada = Schema::hasColumn('RUBRICA', 'RUBRICA_CAMADA');
        $temCalculo = Schema::hasColumn('RUBRICA', 'RUBRICA_CALCULO');
        $temFgts = Schema::hasColumn('RUBRICA', 'RUBRICA_INCIDE_FGTS');
        $temSagres = Schema::hasColumn('RUBRICA', 'RUBRICA_SAGRES_COD');
        $temOrdem = Schema::hasColumn('RUBRICA', 'RUBRICA_ORDEM');

        foreach ($rubricas as [$cod, $desc, $tipo, $camada, $calculo, $prev, $irrf, $fgts, $sagres, $ordem]) {
            // RUBRICA não tem timestamps — usar apenas colunas que existem
            $data = [
                'RUBRICA_DESCRICAO' => $desc,
                'RUBRICA_TIPO' => $tipo,
                'RUBRICA_ATIVO' => true,
            ];
            if ($temCamada)
                $data['RUBRICA_CAMADA'] = $camada;
            if ($temCalculo)
                $data['RUBRICA_CALCULO'] = $calculo;
            if ($temFgts)
                $data['RUBRICA_INCIDE_FGTS'] = $fgts;
            if ($temSagres)
                $data['RUBRICA_SAGRES_COD'] = $sagres;
            if ($temOrdem)
                $data['RUBRICA_ORDEM'] = $ordem;

            DB::table('RUBRICA')->updateOrInsert(
                ['RUBRICA_CODIGO' => $cod],
                $data
            );
        }

        $this->command->info('✅ RubricasCatalogoSeeder: ' . count($rubricas) . ' rubricas inseridas/atualizadas.');
    }
}
