<?php

namespace Database\Seeders;

use Illuminate\Database\Seeder;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

class VinculosPMSLzSeeder extends Seeder
{
    public function run(): void
    {
        // Detectar colunas novas adicionadas pela migration Sprint 3
        $temTipo = Schema::hasColumn('VINCULO', 'VINCULO_TIPO');
        $temRegime = Schema::hasColumn('VINCULO', 'VINCULO_REGIME');
        $temFgts = Schema::hasColumn('VINCULO', 'VINCULO_FGTS');
        $temInss = Schema::hasColumn('VINCULO', 'VINCULO_INSS');
        $temIrrf = Schema::hasColumn('VINCULO', 'VINCULO_IRRF');
        $temAnuenio = Schema::hasColumn('VINCULO', 'VINCULO_ANUENIO_PCT');

        // [NOME, SIGLA, TIPO, REGIME, FGTS, INSS, IRRF, ANUENIO_PCT]
        $vinculos = [
            ['Estatutário Efetivo', 'EFT', 'efetivo', 'RPPS', 0, 1, 1, 1.00],
            ['Serviço Prestado (Art.19 ADCT)', 'SP', 'servico_prestado', 'RGPS', 1, 1, 1, 0.00],
            ['Cargo em Comissão — Puro', 'CC', 'comissao_puro', 'RGPS', 0, 1, 1, 0.00],
            ['Efetivo em CC — Modalidade M1', 'CC-M1', 'efetivo_cc_m1', 'RPPS', 0, 1, 1, 0.00],
            ['Efetivo em CC — Modalidade M2', 'CC-M2', 'efetivo_cc_m2', 'RPPS', 0, 1, 1, 1.00],
            ['Função de Confiança / FG', 'FC', 'funcao_confianca', 'RPPS', 0, 1, 1, 1.00],
            ['PSS / Temporário', 'PSS', 'pss', 'RGPS', 1, 1, 1, 0.00],
            ['Guarda Municipal Efetivo', 'GM', 'efetivo', 'RPPS', 0, 1, 1, 1.00],
            ['Professor Municipal Efetivo', 'PROF', 'efetivo', 'RPPS', 0, 1, 1, 1.00],
            ['Empregado Público (CLT)', 'CLT', 'pss', 'RGPS', 1, 1, 1, 0.00],
        ];

        foreach ($vinculos as [$nome, $sigla, $tipo, $regime, $fgts, $inss, $irrf, $anuenio]) {
            $data = [
                'VINCULO_SIGLA' => $sigla,
                'VINCULO_ATIVO' => 1,
                // VINCULO não tem timestamps nem VINCULO_DESCRICAO
            ];
            if ($temTipo)
                $data['VINCULO_TIPO'] = $tipo;
            if ($temRegime)
                $data['VINCULO_REGIME'] = $regime;
            if ($temFgts)
                $data['VINCULO_FGTS'] = $fgts;
            if ($temInss)
                $data['VINCULO_INSS'] = $inss;
            if ($temIrrf)
                $data['VINCULO_IRRF'] = $irrf;
            if ($temAnuenio)
                $data['VINCULO_ANUENIO_PCT'] = $anuenio;

            DB::table('VINCULO')->updateOrInsert(
                ['VINCULO_NOME' => $nome],
                $data
            );
        }

        $this->command->info('✅ VinculosPMSLzSeeder: ' . count($vinculos) . ' vínculos inseridos/atualizados.');
    }
}
