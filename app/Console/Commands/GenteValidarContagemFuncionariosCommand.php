<?php

namespace App\Console\Commands;

use Illuminate\Console\Command;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

class GenteValidarContagemFuncionariosCommand extends Command
{
    protected $signature = 'gente:validar-contagem-funcionarios
                            {--expected= : Total esperado de funcionários activos (omissão: env GENTE_EXPECT_FUNCIONARIOS_ATIVOS ou 90007)}
                            {--tolerance=0 : Diferença máxima aceitável em relação ao esperado}';

    protected $description = 'Compara COUNT de FUNCIONARIO activos com o valor esperado (homolog / stress seed)';

    public function handle(): int
    {
        if (! Schema::hasTable('FUNCIONARIO')) {
            $this->error('Tabela FUNCIONARIO não existe.');

            return self::FAILURE;
        }

        $expected = $this->option('expected');
        if ($expected === null || $expected === '') {
            $expected = env('GENTE_EXPECT_FUNCIONARIOS_ATIVOS', 90007);
        }
        $expected = (int) $expected;
        $tolerance = max(0, (int) $this->option('tolerance'));

        $total = (int) DB::table('FUNCIONARIO')->count();

        if (! Schema::hasColumn('FUNCIONARIO', 'FUNCIONARIO_ATIVO')) {
            $this->warn('Coluna FUNCIONARIO_ATIVO ausente — a usar COUNT(*) total como «activos».');
            $ativos = $total;
            $inativos = null;
        } else {
            $ativos = (int) DB::table('FUNCIONARIO')->where('FUNCIONARIO_ATIVO', 1)->count();
            $inativos = (int) DB::table('FUNCIONARIO')->where(function ($q) {
                $q->where('FUNCIONARIO_ATIVO', '!=', 1)->orWhereNull('FUNCIONARIO_ATIVO');
            })->count();
        }

        $this->line('FUNCIONARIO total:        '.$total);
        $this->line('FUNCIONARIO «activos»:    '.$ativos);
        if ($inativos !== null) {
            $this->line('FUNCIONARIO inactivos:    '.$inativos);
        }
        $this->line('Esperado (alvo):          '.$expected);
        $this->line('Tolerância:               ±'.$tolerance);

        $delta = abs($ativos - $expected);
        if ($delta <= $tolerance) {
            $this->info('OK — contagem dentro do intervalo.');

            return self::SUCCESS;
        }

        $this->error('FALHA — activos='.$ativos.' esperado='.$expected.' (delta '.$delta.', tolerância '.$tolerance.').');

        return self::FAILURE;
    }
}
