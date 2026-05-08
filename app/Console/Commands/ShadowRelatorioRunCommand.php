<?php

namespace App\Console\Commands;

use Illuminate\Console\Command;
use Illuminate\Support\Facades\DB;

class ShadowRelatorioRunCommand extends Command
{
    protected $signature = 'shadow:relatorio-run
        {run_id}
        {--json : Saída JSON}
        {--persistir : Atualiza SHADOW_RUN com totais e status de aceite}';

    protected $description = 'Consolida relatório executivo da reconciliação de um RUN_ID shadow.';

    public function handle(): int
    {
        $runId = (string) $this->argument('run_id');
        $run = DB::table('SHADOW_RUN')->where('RUN_ID', $runId)->first();
        if (!$run) {
            $this->error('RUN_ID não encontrado: ' . $runId);
            return self::FAILURE;
        }

        $classes = DB::table('DIFF_RECONCILIACAO')
            ->where('RUN_ID', $runId)
            ->select('CLASSIFICACAO', DB::raw('COUNT(*) as qtd'))
            ->groupBy('CLASSIFICACAO')
            ->pluck('qtd', 'CLASSIFICACAO')
            ->toArray();

        $criticos = (int) ($classes['FALHA_SISTEMICA_CRITICA'] ?? 0);
        $status = $criticos === 0 ? 'aprovado_sem_criticos' : 'reprovado_com_criticos';

        $payload = [
            'run_id' => $runId,
            'competencia' => $run->COMPETENCIA,
            'status_aceite' => $status,
            'total_diff' => (int) array_sum($classes),
            'classificacoes' => [
                'APROVADO_EXATO' => (int) ($classes['APROVADO_EXATO'] ?? 0),
                'DIVERGENCIA_TOLERAVEL' => (int) ($classes['DIVERGENCIA_TOLERAVEL'] ?? 0),
                'DIVERGENCIA_JUSTIFICAVEL' => (int) ($classes['DIVERGENCIA_JUSTIFICAVEL'] ?? 0),
                'FALHA_SISTEMICA_CRITICA' => $criticos,
            ],
            'executado_em' => now()->toIso8601String(),
        ];

        if ((bool) $this->option('persistir')) {
            DB::table('SHADOW_RUN')
                ->where('RUN_ID', $runId)
                ->update([
                    'TOTAL_ETL_OK' => (int) DB::table('SHADOW_CHECKPOINT')
                        ->where('RUN_ID', $runId)
                        ->where('ETAPA', 'etl_ok')
                        ->where('STATUS', 'ok')
                        ->count(),
                    'TOTAL_CALC_OK' => (int) DB::table('SHADOW_CHECKPOINT')
                        ->where('RUN_ID', $runId)
                        ->where('ETAPA', 'calc_ok')
                        ->where('STATUS', 'ok')
                        ->count(),
                    'TOTAL_DIFF_OK' => (int) (($classes['APROVADO_EXATO'] ?? 0) + ($classes['DIVERGENCIA_TOLERAVEL'] ?? 0) + ($classes['DIVERGENCIA_JUSTIFICAVEL'] ?? 0)),
                    'TOTAL_DIFF_CRITICO' => $criticos,
                    'STATUS' => $status,
                    'OBS' => 'Fechamento por shadow:relatorio-run em ' . now()->format('Y-m-d H:i:s'),
                    'updated_at' => now(),
                ]);
        }

        if ((bool) $this->option('json')) {
            $this->line(json_encode($payload, JSON_UNESCAPED_UNICODE | JSON_PRETTY_PRINT));
        } else {
            $this->info('Relatório de run shadow');
            $this->line('RUN_ID: ' . $payload['run_id']);
            $this->line('Competência: ' . $payload['competencia']);
            $this->line('Status aceite: ' . $payload['status_aceite']);
            $this->line('Total diffs: ' . $payload['total_diff']);
            foreach ($payload['classificacoes'] as $k => $v) {
                $this->line("- {$k}: {$v}");
            }
        }

        return self::SUCCESS;
    }
}

