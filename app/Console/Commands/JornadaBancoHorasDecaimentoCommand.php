<?php

namespace App\Console\Commands;

use App\Services\Jornada\JornadaRegraParametros;
use Illuminate\Console\Command;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

/**
 * S3.3–S3.4: passo operacional. Por ora apenas inventário; decaimento real depende
 * regra de negócio (prazos, justificativa) e aprovação SEMAD.
 */
class JornadaBancoHorasDecaimentoCommand extends Command
{
    protected $signature = 'jornada:banco-horas-decaimento
                            {--dias=365 : Janela em dias para listar créditos antigos (relatório)}
                            {--dry-run : Só contar, sem alterar}';

    protected $description = 'Relatório / placeholder de decaimento de banco de horas (S3)';

    public function handle(): int
    {
        $dias = max(1, (int) $this->option('dias'));
        $dry = (bool) $this->option('dry-run');
        $lim = now()->subDays($dias);

        if (!Schema::hasTable('BANCO_HORAS')) {
            $this->warn('Tabela BANCO_HORAS inexistente; nada a fazer.');

            return self::SUCCESS;
        }

        $q = DB::table('BANCO_HORAS');
        if (Schema::hasColumn('BANCO_HORAS', 'created_at')) {
            $q->where('created_at', '<', $lim);
        }
        $n = (clone $q)->count();
        $this->info("Registros BANCO_HORAS com created_at < {$lim->toDateTimeString()}: {$n}");
        $this->line('Tolerância ponto vigente: ' . JornadaRegraParametros::toleranciaPontoMinutos() . ' min');

        if (!$dry) {
            $this->comment('Mutação ainda não implementada — requer regra e autorização (SEMAD).');
        }

        return self::SUCCESS;
    }
}
