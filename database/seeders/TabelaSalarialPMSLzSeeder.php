<?php

namespace Database\Seeders;

use Illuminate\Database\Seeder;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

class TabelaSalarialPMSLzSeeder extends Seeder
{
    public function run(): void
    {
        // Detectar colunas das tabelas envolvidas
        $temCarreiraRegime = Schema::hasColumn('CARREIRA', 'CARREIRA_REGIME');
        $temCarreiraDesc = Schema::hasColumn('CARREIRA', 'CARREIRA_DESCRICAO');
        $temCarreiraAtivo = Schema::hasColumn('CARREIRA', 'CARREIRA_ATIVO');
        $temCarreiraTS = Schema::hasColumn('CARREIRA', 'updated_at');
        $temProgConfig = Schema::hasTable('PROGRESSAO_CONFIG');
        $temTabelaOrdem = Schema::hasColumn('TABELA_SALARIAL', 'TABELA_REFERENCIA_ORDEM');
        $temTabelaTS = Schema::hasColumn('TABELA_SALARIAL', 'updated_at');

        // ── Carreira 1: Efetivos Gerais ───────────────────────────────────────
        $carrId1 = $this->upsertCarreira(
            'Servidores Efetivos Gerais',
            'efetivo',
            'Lei 4.616/2006 — reajuste 6% Lei 7.731/2025',
            $temCarreiraRegime,
            $temCarreiraDesc,
            $temCarreiraAtivo,
            $temCarreiraTS
        );

        if ($temProgConfig) {
            $this->upsertProgConfig($carrId1, 24, 7.00, 1.00, 'I', 'XI', 36);
        }

        $tabelaGeral = [
            'I' => [782.42, 801.95, 822.03, 842.56, 863.61, 885.24, 907.36, 930.05, 953.29],
            'II' => [863.61, 885.24, 907.36, 930.05, 953.30, 977.15, 1001.55, 1026.61, 1052.28],
            'III' => [953.30, 977.15, 1001.57, 1026.61, 1052.28, 1078.60, 1105.55, 1133.16, 1161.54],
            'IV' => [1052.29, 1078.60, 1105.56, 1133.19, 1161.55, 1190.58, 1220.30, 1250.83, 1282.09],
            'V' => [1161.55, 1190.58, 1220.31, 1250.86, 1282.09, 1314.17, 1347.00, 1380.70, 1415.22],
            'VI' => [1115.83, 1143.74, 1172.34, 1201.69, 1231.70, 1262.50, 1294.04, 1326.42, 1359.56],
            'VII' => [1428.35, 1464.05, 1500.68, 1538.20, 1576.67, 1616.07, 1656.48, 1697.89, 1740.34],
            'VIII' => [1828.45, 1874.19, 1921.01, 1969.03, 2018.25, 2068.74, 2120.42, 2173.45, 2227.79],
            'IX' => [2615.32, 2706.84, 2801.59, 2899.64, 3001.14, 3106.16, 3214.87, 3327.41, 3443.86],
            'X' => [3689.13, 3818.31, 3951.92, 4090.23, 4233.39, 4381.56, 4534.95, 4693.63, 4857.92],
            'XI' => [5203.94, 5386.07, 5574.55, 5769.66, 5971.63, 6180.64, 6396.96, 6620.84, 6852.60],
        ];
        $this->insertTabela($carrId1, $tabelaGeral, ['A', 'B', 'C', 'D', 'E', 'F', 'G', 'H', 'I'], $temTabelaOrdem, $temTabelaTS);

        // ── Carreira 2: Guarda Municipal ──────────────────────────────────────
        $carrId2 = $this->upsertCarreira(
            'Guarda Municipal',
            'efetivo',
            'Lei 5.509/2011 — reajuste 6% Lei 7.731/2025',
            $temCarreiraRegime,
            $temCarreiraDesc,
            $temCarreiraAtivo,
            $temCarreiraTS
        );
        if ($temProgConfig)
            $this->upsertProgConfig($carrId2, 24, 7.00, 1.00, 'H', null, 36);

        $tabelaGuarda = [
            'GI' => [1115.85, 1127.02, 1138.30, 1149.70, 1161.15, 1172.78, 1184.50, 1196.38],
            'GII' => [1208.33, 1220.39, 1232.59, 1244.94, 1257.38, 1269.93, 1282.65, 1295.48],
            'GIII' => [1308.43, 1334.60, 1361.29, 1388.54, 1416.30, 1444.63, 1473.52, 1502.97],
            'GIV' => [1533.05, 1563.73, 1594.97, 1626.88, 1659.43, 1692.60, 1726.46, 1760.97],
            'GV' => [1796.21, 1832.13, 1868.80, 1906.17, 1944.26, 1983.15, 2022.81, 2063.28],
            'GVI' => [2104.56, 2178.22, 2254.44, 2333.35, 2415.03, 2499.55, 2587.04, 2677.58],
            'GVII' => [2771.27, 2868.30, 2968.68, 3072.57, 3180.11, 3291.41, 3406.61, 3525.84],
            'GVIII' => [3649.25, 3776.98, 3909.18, 4046.01, 4187.61, 4334.17, 4485.89, 4642.87],
        ];
        $this->insertTabela($carrId2, $tabelaGuarda, ['A', 'B', 'C', 'D', 'E', 'F', 'G', 'H'], $temTabelaOrdem, $temTabelaTS);

        // ── Carreira 3: Magistério ────────────────────────────────────────────
        $carrId3 = $this->upsertCarreira(
            'Magistério Municipal',
            'efetivo',
            'Lei 4.931/2008 — reajuste 6,5% Lei 7.727/2025',
            $temCarreiraRegime,
            $temCarreiraDesc,
            $temCarreiraAtivo,
            $temCarreiraTS
        );
        if ($temProgConfig)
            $this->upsertProgConfig($carrId3, 24, 7.00, 1.00, 'E', null, 36);

        $tabelaMag = [
            'PNM-I' => [1412.00, 1448.30, 1485.51, 1523.65, 1562.74],
            'PNM-II' => [1562.74, 1601.81, 1641.85, 1682.90, 1724.97],
            'PNM-III' => [1724.97, 1768.09, 1812.29, 1857.60, 1904.04],
            'PNS-I' => [2118.00, 2170.95, 2225.22, 2280.85, 2337.87],
            'PNS-II' => [2337.87, 2396.32, 2456.23, 2517.63, 2580.57],
            'PNS-III' => [2580.57, 2645.08, 2711.21, 2779.00, 2848.47],
        ];
        $this->insertTabela($carrId3, $tabelaMag, ['A', 'B', 'C', 'D', 'E'], $temTabelaOrdem, $temTabelaTS);

        $this->command->info("✅ TabelaSalarialPMSLzSeeder: 3 carreiras (ID: {$carrId1}, {$carrId2}, {$carrId3}).");
        $this->command->info("   GERAL={$carrId1} GUARDA={$carrId2} MAGISTERIO={$carrId3}");
    }

    private function upsertCarreira(
        string $nome,
        string $regime,
        string $desc,
        bool $temRegime,
        bool $temDesc,
        bool $temAtivo,
        bool $temTS
    ): int {
        $existing = DB::table('CARREIRA')->where('CARREIRA_NOME', $nome)->value('CARREIRA_ID');
        if ($existing)
            return $existing;

        $data = ['CARREIRA_NOME' => $nome];
        if ($temRegime)
            $data['CARREIRA_REGIME'] = $regime;
        if ($temDesc)
            $data['CARREIRA_DESCRICAO'] = $desc;
        if ($temAtivo)
            $data['CARREIRA_ATIVO'] = true;
        if ($temTS) {
            $data['updated_at'] = now();
            $data['created_at'] = now();
        }

        return DB::table('CARREIRA')->insertGetId($data);
    }

    private function upsertProgConfig(
        int $carrId,
        int $inter,
        float $nota,
        float $anuenio,
        string $refMax,
        ?string $classFinal,
        int $estagio
    ): void {
        $data = ['CARREIRA_ID' => $carrId];
        if (Schema::hasColumn('PROGRESSAO_CONFIG', 'CONFIG_INTERSTICIO_MESES'))
            $data['CONFIG_INTERSTICIO_MESES'] = $inter;
        if (Schema::hasColumn('PROGRESSAO_CONFIG', 'CONFIG_NOTA_MINIMA'))
            $data['CONFIG_NOTA_MINIMA'] = $nota;
        if (Schema::hasColumn('PROGRESSAO_CONFIG', 'CONFIG_ANUENIO_PCT'))
            $data['CONFIG_ANUENIO_PCT'] = $anuenio;
        if (Schema::hasColumn('PROGRESSAO_CONFIG', 'CONFIG_REFERENCIA_MAXIMA'))
            $data['CONFIG_REFERENCIA_MAXIMA'] = $refMax;
        if ($classFinal && Schema::hasColumn('PROGRESSAO_CONFIG', 'CONFIG_CLASSE_FINAL'))
            $data['CONFIG_CLASSE_FINAL'] = $classFinal;
        if (Schema::hasColumn('PROGRESSAO_CONFIG', 'CONFIG_ESTAGIO_PROBATORIO_MESES'))
            $data['CONFIG_ESTAGIO_PROBATORIO_MESES'] = $estagio;
        if (Schema::hasColumn('PROGRESSAO_CONFIG', 'updated_at')) {
            $data['updated_at'] = now();
            $data['created_at'] = now();
        }

        DB::table('PROGRESSAO_CONFIG')->updateOrInsert(['CARREIRA_ID' => $carrId], $data);
    }

    private function insertTabela(int $carrId, array $tabela, array $refs, bool $temOrdem, bool $temTS): void
    {
        foreach ($tabela as $classe => $valores) {
            foreach ($refs as $i => $ref) {
                if (!isset($valores[$i]))
                    continue;
                if (
                    DB::table('TABELA_SALARIAL')
                        ->where('CARREIRA_ID', $carrId)
                        ->where('TABELA_CLASSE', $classe)
                        ->where('TABELA_REFERENCIA', $ref)
                        ->exists()
                )
                    continue;

                $data = [
                    'CARREIRA_ID' => $carrId,
                    'TABELA_CLASSE' => $classe,
                    'TABELA_REFERENCIA' => $ref,
                    'TABELA_VENCIMENTO_BASE' => $valores[$i],
                ];
                if ($temOrdem)
                    $data['TABELA_REFERENCIA_ORDEM'] = $i + 1;
                if ($temTS) {
                    $data['updated_at'] = now();
                    $data['created_at'] = now();
                }
                DB::table('TABELA_SALARIAL')->insert($data);
            }
        }
    }
}
