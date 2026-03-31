<?php

namespace Database\Seeders;

use Illuminate\Database\Seeder;
use Illuminate\Support\Facades\DB;

class PcaspSeeder extends Seeder
{
    public function run(): void
    {
        if (DB::table('PCASP_CONTA')->count() > 0) {
            $this->command->info('PCASP já populado — seed ignorado.');
            return;
        }

        $contas = [
            // [CODIGO, NOME, NATUREZA, GRUPO, PAI_CODIGO]
            // Grupo 2 — Passivo
            ['2', 'PASSIVO', 'CREDORA', 'PASSIVO', null],
            ['2.1', 'Passivo Circulante', 'CREDORA', 'PASSIVO', '2'],
            ['2.1.3', 'Obrigações Trabalhistas, Previdenciárias e Assistenciais', 'CREDORA', 'PASSIVO', '2.1'],
            ['2.1.3.1', 'Pessoal a Pagar', 'CREDORA', 'PASSIVO', '2.1.3'],
            ['2.1.3.1.01', 'Salários e Vantagens a Pagar', 'CREDORA', 'PASSIVO', '2.1.3.1'],
            ['2.1.3.2', 'Contribuições a Recolher', 'CREDORA', 'PASSIVO', '2.1.3'],
            ['2.1.3.2.01', 'RPPS/IPAM a Recolher', 'CREDORA', 'PASSIVO', '2.1.3.2'],
            ['2.1.3.3', 'Tributos a Recolher', 'CREDORA', 'PASSIVO', '2.1.3'],
            ['2.1.3.3.01', 'IRRF Folha a Recolher', 'CREDORA', 'PASSIVO', '2.1.3.3'],
            // Grupo 3 — Variações Patrimoniais Diminutivas
            ['3', 'VARIAÇÕES PATRIMONIAIS DIMINUTIVAS', 'DEVEDORA', 'VARIACAO', null],
            ['3.1', 'Pessoal e Encargos', 'DEVEDORA', 'VARIACAO', '3'],
            ['3.1.1', 'Remuneração a Pessoal', 'DEVEDORA', 'VARIACAO', '3.1'],
            ['3.1.1.1', 'Vencimentos e Vantagens', 'DEVEDORA', 'VARIACAO', '3.1.1'],
            ['3.1.1.1.01', 'Vencimentos e Vantagens Fixas', 'DEVEDORA', 'VARIACAO', '3.1.1.1'],
            ['3.1.2', 'Encargos Patronais', 'DEVEDORA', 'VARIACAO', '3.1'],
            ['3.1.2.1', 'Contribuições Patronais', 'DEVEDORA', 'VARIACAO', '3.1.2'],
            ['3.1.2.1.01', 'Contribuição Patronal IPAM', 'DEVEDORA', 'VARIACAO', '3.1.2.1'],
        ];

        // Inserir sem pai primeiro, depois resolver FKs por código
        $ids = [];
        foreach ($contas as [$codigo, $nome, $natureza, $grupo, $paiCodigo]) {
            $ids[$codigo] = DB::table('PCASP_CONTA')->insertGetId([
                'CONTA_CODIGO'   => $codigo,
                'CONTA_NOME'     => $nome,
                'CONTA_NATUREZA' => $natureza,
                'CONTA_GRUPO'    => $grupo,
                'CONTA_PAI_ID'   => $paiCodigo ? ($ids[$paiCodigo] ?? null) : null,
                'CONTA_ATIVA'    => true,
                'created_at'     => now(),
                'updated_at'     => now(),
            ]);
        }

        $this->command->info('PCASP seedado com ' . count($contas) . ' contas.');
    }
}
