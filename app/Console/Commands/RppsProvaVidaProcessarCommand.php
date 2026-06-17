<?php

namespace App\Console\Commands;

use Carbon\Carbon;
use Illuminate\Console\Command;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

class RppsProvaVidaProcessarCommand extends Command
{
    protected $signature = 'rpps:prova-vida-processar
                            {--competencia= : Competência YYYY-MM (default atual)}
                            {--inicializar : Inicializa pendências antes de processar}';

    protected $description = 'S6.1: processa estados da prova de vida RPPS/IPAM';

    public function handle(): int
    {
        if (!Schema::hasTable('RPPS_PROVA_VIDA')) {
            $this->warn('Tabela RPPS_PROVA_VIDA inexistente.');
            return self::SUCCESS;
        }

        $comp = (string) ($this->option('competencia') ?: now()->format('Y-m'));
        if (!preg_match('/^\d{4}-\d{2}$/', $comp)) {
            $this->error('competencia inválida (use YYYY-MM).');
            return self::FAILURE;
        }

        if ((bool) $this->option('inicializar')) {
            $this->inicializarPendencias($comp);
        }

        $hoje = Carbon::now()->toDateString();
        $itens = DB::table('RPPS_PROVA_VIDA')->where('COMPETENCIA', $comp)->get();
        $iminentes = 0;
        $bloqueados = 0;

        foreach ($itens as $i) {
            if (($i->STATUS ?? '') === 'regular') {
                continue;
            }
            $prazo = !empty($i->PRAZO_FINAL)
                ? (string) $i->PRAZO_FINAL
                : Carbon::parse($i->DATA_REFERENCIA ?: $hoje)->endOfMonth()->toDateString();
            $novo = $hoje > $prazo ? 'bloqueado' : 'bloqueio_iminente';
            if ($novo === 'bloqueado') {
                $bloqueados++;
            } else {
                $iminentes++;
            }

            DB::table('RPPS_PROVA_VIDA')
                ->where('RPPS_PROVA_VIDA_ID', $i->RPPS_PROVA_VIDA_ID)
                ->update([
                    'STATUS' => $novo,
                    'MOTIVO' => $novo === 'bloqueado'
                        ? 'Prazo expirado sem prova de vida'
                        : 'Prazo próximo do vencimento',
                    'updated_at' => now(),
                ]);

            if (Schema::hasTable('RPPS_BLOQUEIO_EVENTO')) {
                DB::table('RPPS_BLOQUEIO_EVENTO')->insert([
                    'FUNCIONARIO_ID' => $i->FUNCIONARIO_ID,
                    'COMPETENCIA' => $comp,
                    'EVENTO' => $novo,
                    'ORIGEM' => 'scheduler',
                    'MOTIVO' => $novo === 'bloqueado'
                        ? 'Processamento automático de prova de vida'
                        : 'Alerta automático de prazo',
                    'USUARIO_ID' => null,
                    'created_at' => now(),
                    'updated_at' => now(),
                ]);
            }
        }

        $this->info("Competência {$comp}: bloqueio_iminente={$iminentes}, bloqueados={$bloqueados}");
        return self::SUCCESS;
    }

    private function inicializarPendencias(string $comp): void
    {
        $prazo = Carbon::createFromDate((int) substr($comp, 0, 4), (int) substr($comp, 5, 2), 1)
            ->endOfMonth()
            ->toDateString();
        $hoje = now()->toDateString();

        $beneficiarios = collect();
        if (Schema::hasTable('RPPS_BENEFICIARIO')) {
            $beneficiarios = DB::table('RPPS_BENEFICIARIO')
                ->select('FUNCIONARIO_ID')
                ->distinct()
                ->get();
        } elseif (Schema::hasTable('FUNCIONARIO')) {
            $beneficiarios = DB::table('FUNCIONARIO')
                ->whereRaw("LOWER(COALESCE(FUNCIONARIO_REGIME_PREV,'')) like '%rpps%'")
                ->select('FUNCIONARIO_ID')
                ->get();
        }

        $inseridos = 0;
        foreach ($beneficiarios as $b) {
            $funcId = (int) ($b->FUNCIONARIO_ID ?? 0);
            if ($funcId <= 0) {
                continue;
            }
            $exists = DB::table('RPPS_PROVA_VIDA')
                ->where('FUNCIONARIO_ID', $funcId)
                ->where('COMPETENCIA', $comp)
                ->exists();
            if ($exists) {
                continue;
            }
            DB::table('RPPS_PROVA_VIDA')->insert([
                'FUNCIONARIO_ID' => $funcId,
                'COMPETENCIA' => $comp,
                'STATUS' => 'pendente',
                'TIPO_PROCEDIMENTO' => 'ordinaria',
                'CANAL' => null,
                'DATA_REFERENCIA' => $hoje,
                'PRAZO_FINAL' => $prazo,
                'MOTIVO' => 'Pendência inicial automática da competência',
                'VALIDADO_POR' => null,
                'created_at' => now(),
                'updated_at' => now(),
            ]);
            $inseridos++;
        }

        $this->line("Inicialização {$comp}: {$inseridos} pendência(s) criada(s).");
    }
}
