<?php

namespace Database\Seeders;

use Carbon\Carbon;
use Illuminate\Database\Seeder;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

/**
 * Cobertura temporal opcional para homologação (sem substituir o stress N→FUNCIONARIO).
 *
 * Variável de ambiente: {@code GENTE_TIMELINE_SEED_MONTHS} — número de meses (1–24)
 * contando para trás a partir do mês corrente. Com 0 ou ausente, não insere nada.
 *
 * Insere batidas mínimas em {@code REGISTRO_PONTO} para um pequeno conjunto de servidores
 * activos, apenas nos meses ainda sem qualquer registo para cada servidor.
 *
 * Matriz de smoke (rotas): {@see database/scripts/smoke_routes_matrix.json}
 */
class GenteTimelineCoverageSeeder extends Seeder
{
    public function run(): void
    {
        $n = max(0, min(24, (int) env('GENTE_TIMELINE_SEED_MONTHS', 0)));
        if ($n <= 0) {
            $this->command?->info('GenteTimelineCoverageSeeder: ignorado (GENTE_TIMELINE_SEED_MONTHS=0 ou ausente).');

            return;
        }

        if (! Schema::hasTable('REGISTRO_PONTO')) {
            $this->command?->warn('GenteTimelineCoverageSeeder: tabela REGISTRO_PONTO ausente.');

            return;
        }

        $cols = Schema::getColumnListing('REGISTRO_PONTO');
        if (! in_array('REGISTRO_DATA_HORA', $cols, true) || ! in_array('REGISTRO_TIPO', $cols, true)) {
            $this->command?->warn('GenteTimelineCoverageSeeder: colunas REGISTRO_DATA_HORA / REGISTRO_TIPO ausentes.');

            return;
        }

        $origemCol = in_array('REGISTRO_ORIGEM', $cols, true);

        $q = DB::table('FUNCIONARIO');
        if (Schema::hasColumn('FUNCIONARIO', 'FUNCIONARIO_ATIVO')) {
            $q->where('FUNCIONARIO_ATIVO', 1);
        }
        $funcIds = $q->orderBy('FUNCIONARIO_ID')->limit(8)->pluck('FUNCIONARIO_ID')->all();
        if ($funcIds === []) {
            $this->command?->warn('GenteTimelineCoverageSeeder: nenhum FUNCIONARIO encontrado.');

            return;
        }

        $inseridos = 0;

        for ($offset = 0; $offset < $n; $offset++) {
            $monthStart = Carbon::now()->startOfMonth()->subMonths($offset);
            $monthEnd = $monthStart->copy()->endOfMonth();

            $workDay = $monthStart->copy();
            while ($workDay->lte($monthEnd) && $workDay->isWeekend()) {
                $workDay->addDay();
            }
            if ($workDay->gt($monthEnd)) {
                continue;
            }

            foreach ($funcIds as $fid) {
                $exists = DB::table('REGISTRO_PONTO')
                    ->where('FUNCIONARIO_ID', $fid)
                    ->whereBetween('REGISTRO_DATA_HORA', [
                        $monthStart->copy()->startOfDay()->toDateTimeString(),
                        $monthEnd->copy()->endOfDay()->toDateTimeString(),
                    ])
                    ->exists();
                if ($exists) {
                    continue;
                }

                $dia = $workDay->toDateString();
                foreach ([
                    ['entrada', '08:01:00'],
                    ['saida_alm', '12:03:00'],
                    ['ret_alm', '13:02:00'],
                    ['saida', '17:04:00'],
                ] as [$tipo, $hora]) {
                    $row = [
                        'FUNCIONARIO_ID' => $fid,
                        'REGISTRO_DATA_HORA' => $dia.' '.$hora,
                        'REGISTRO_TIPO' => $tipo,
                    ];
                    if ($origemCol) {
                        $row['REGISTRO_ORIGEM'] = 'SEED_TIMELINE';
                    }
                    if (in_array('created_at', $cols, true)) {
                        $row['created_at'] = now();
                    }
                    if (in_array('updated_at', $cols, true)) {
                        $row['updated_at'] = now();
                    }
                    DB::table('REGISTRO_PONTO')->insert($row);
                    $inseridos++;
                }
            }
        }

        $this->command?->info("GenteTimelineCoverageSeeder: {$inseridos} linha(s) em REGISTRO_PONTO (janela de {$n} mês(es)).");
    }
}
