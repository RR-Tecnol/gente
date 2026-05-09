<?php

namespace Database\Seeders;

use Illuminate\Database\Seeder;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

class Feriados2026Seeder extends Seeder
{
    public function run(): void
    {
        if (!Schema::hasTable('FERIADO')) {
            $this->command->warn('Tabela FERIADO não existe — seeder ignorado.');
            return;
        }

        $cols = Schema::getColumnListing('FERIADO');
        $colNome = in_array('FERIADO_NOME', $cols, true) ? 'FERIADO_NOME' : 'FERIADO_DESCRICAO';
        $colRecorrente = in_array('FERIADO_RECORRENTE', $cols, true);
        $colAtivo = in_array('FERIADO_ATIVO', $cols, true);

        // FERIADO_TIPO pode ser int (PMSL) ou string (legado).
        // Usa INFORMATION_SCHEMA.COLUMNS para evitar Schema::getColumnType,
        // que quebra com Doctrine DBAL + SQL Server.
        $tipoNumerico = self::isFeriadoTipoNumerico();
        $tipoMap = ['N' => 1, 'E' => 2, 'M' => 3, 'F' => 4];

        $feriados = [
            ['2026-01-01', 'Confraternização Universal', 'N'],
            ['2026-02-16', 'Carnaval (Segunda-feira)', 'F'],
            ['2026-02-17', 'Carnaval (Terça-feira)', 'F'],
            ['2026-02-18', 'Quarta-feira de Cinzas (até 14h)', 'F'],
            ['2026-04-03', 'Sexta-feira Santa', 'N'],
            ['2026-04-21', 'Tiradentes', 'N'],
            ['2026-05-01', 'Dia do Trabalhador', 'N'],
            ['2026-06-04', 'Corpus Christi', 'F'],
            ['2026-07-28', 'Adesão do MA à Independência', 'E'],
            ['2026-09-07', 'Independência do Brasil', 'N'],
            ['2026-09-08', 'Natividade de N. Sra. / Aniversário de São Luís', 'M'],
            ['2026-10-12', 'Nossa Senhora Aparecida', 'N'],
            ['2026-10-28', 'Dia do Servidor Público', 'F'],
            ['2026-11-02', 'Finados', 'N'],
            ['2026-11-15', 'Proclamação da República', 'N'],
            ['2026-11-20', 'Dia da Consciência Negra', 'N'],
            ['2026-12-08', 'Nossa Senhora da Conceição', 'M'],
            ['2026-12-25', 'Natal', 'N'],
            ['2026-06-29', 'São Pedro (São Luís)', 'M'],
        ];

        foreach ($feriados as [$data, $nome, $tipo]) {
            $tipoValue = $tipoNumerico ? ($tipoMap[$tipo] ?? 1) : $tipo;
            $where = ['FERIADO_DATA' => $data, $colNome => $nome];
            $payload = ['FERIADO_TIPO' => $tipoValue, $colNome => $nome];
            if ($colRecorrente) {
                $payload['FERIADO_RECORRENTE'] = 0;
            }
            if ($colAtivo) {
                $payload['FERIADO_ATIVO'] = 1;
            }
            DB::table('FERIADO')->updateOrInsert($where, $payload);
        }

        $this->command->info('Feriados2026Seeder: ' . count($feriados) . ' feriados garantidos.');
    }

    /**
     * Detecta se a coluna FERIADO_TIPO é numérica (int/bigint/smallint/tinyint)
     * sem usar Doctrine DBAL (que não suporta SQL Server nesta versão).
     *
     * Usa INFORMATION_SCHEMA.COLUMNS, que é padrão SQL ANSI e funciona em
     * SQL Server, MySQL, PostgreSQL e SQLite >= 3.16.
     */
    private static function isFeriadoTipoNumerico(): bool
    {
        try {
            $row = DB::selectOne(
                "SELECT DATA_TYPE FROM INFORMATION_SCHEMA.COLUMNS
                 WHERE TABLE_NAME = ? AND COLUMN_NAME = ?",
                ['FERIADO', 'FERIADO_TIPO']
            );

            if (!$row || !isset($row->DATA_TYPE)) {
                // Sem info de tipo: assume não-numérico (compat string)
                return false;
            }

            $tipo = strtolower((string) $row->DATA_TYPE);
            return in_array($tipo, ['int', 'integer', 'bigint', 'smallint', 'tinyint'], true);
        } catch (\Throwable $e) {
            // Em caso de erro, assume não-numérico (string legacy)
            return false;
        }
    }
}
