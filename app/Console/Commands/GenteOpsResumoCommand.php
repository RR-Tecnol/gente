<?php

namespace App\Console\Commands;

use Illuminate\Console\Command;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

class GenteOpsResumoCommand extends Command
{
    protected $signature = 'gente:ops-resumo
        {--json : Saída JSON}
        {--amostra-falhas=5 : Quantas linhas de exemplo listar de failed_jobs (0 desliga)}';

    protected $description = 'P2: resumo operacional mínimo (failed_jobs, batches, shadow, eSocial)';

    public function handle(): int
    {
        $now = now()->toIso8601String();
        $out = [
            'timestamp' => $now,
        ];

        try {
            if (Schema::hasTable('failed_jobs')) {
                $n = (int) DB::table('failed_jobs')->count();
                $out['failed_jobs'] = [
                    'count' => $n,
                    'por_fila' => $this->failedJobsPorFila(),
                    'total_payload_bytes' => $this->sumPayloadBytes('failed_jobs'),
                ];
                $amostra = (int) $this->option('amostra-falhas');
                if ($amostra > 0) {
                    $out['failed_jobs']['amostra_ultimas'] = DB::table('failed_jobs')
                        ->orderByDesc('id')
                        ->limit($amostra)
                        ->get(['id', 'uuid', 'queue', 'connection', 'failed_at'])
                        ->map(fn ($r) => (array) $r)
                        ->all();
                }
            } else {
                $out['failed_jobs'] = null;
            }
        } catch (\Throwable $e) {
            $out['failed_jobs_erro'] = $e->getMessage();
        }

        try {
            if (Schema::hasTable('job_batches')) {
                $out['job_batches_pendentes'] = (int) DB::table('job_batches')
                    ->whereNull('finished_at')
                    ->whereNull('cancelled_at')
                    ->count();
                $out['job_batches_com_falha'] = (int) DB::table('job_batches')
                    ->where('failed_jobs', '>', 0)
                    ->count();
            } else {
                $out['job_batches_pendentes'] = null;
            }
        } catch (\Throwable $e) {
            $out['job_batches_erro'] = $e->getMessage();
        }

        try {
            if (Schema::hasTable('SHADOW_RUN')) {
                $out['shadow_ultimos'] = DB::table('SHADOW_RUN')
                    ->orderByDesc('updated_at')
                    ->limit(5)
                    ->get(['RUN_ID', 'COMPETENCIA', 'STATUS', 'updated_at'])
                    ->map(fn ($r) => (array) $r)
                    ->all();
            }
        } catch (\Throwable $e) {
            $out['shadow_erro'] = $e->getMessage();
        }

        try {
            if (Schema::hasTable('ESOCIAL_EVENTO')) {
                $out['esocial_falha_permanente'] = (int) DB::table('ESOCIAL_EVENTO')
                    ->where('STATUS', 'FALHA_PERMANENTE')
                    ->count();
            }
        } catch (\Throwable $e) {
            $out['esocial_resumo_erro'] = $e->getMessage();
        }

        if ((bool) $this->option('json')) {
            $this->line(json_encode($out, JSON_UNESCAPED_UNICODE | JSON_PRETTY_PRINT));
        } else {
            $this->info('Resumo operacional GENTE');
            if (isset($out['failed_jobs']['count'])) {
                $this->line('failed_jobs: ' . $out['failed_jobs']['count'] . ' registro(s); payload ~ '
                    . ($out['failed_jobs']['total_payload_bytes'] ?? 'n/d') . ' byte(s) somados.');
            }
            $this->line(json_encode($out, JSON_UNESCAPED_UNICODE | JSON_PRETTY_PRINT));
        }

        return self::SUCCESS;
    }

    private function failedJobsPorFila(): array
    {
        if (!Schema::hasTable('failed_jobs')) {
            return [];
        }
        $rows = DB::table('failed_jobs')
            ->select('queue', DB::raw('COUNT(*) as c'))
            ->groupBy('queue')
            ->get();
        $map = [];
        foreach ($rows as $r) {
            $q = (string) ($r->queue ?? 'default');
            $map[$q] = (int) $r->c;
        }

        return $map;
    }

    private function sumPayloadBytes(string $table): ?int
    {
        if (!Schema::hasTable($table)) {
            return null;
        }
        $driver = Schema::getConnection()->getDriverName();
        try {
            if ($driver === 'sqlsrv') {
                $v = DB::table($table)->selectRaw('COALESCE(SUM(CAST(DATALENGTH(CAST(payload AS NVARCHAR(MAX))) AS BIGINT)), 0) as b')
                    ->value('b');
            } else {
                $v = DB::table($table)->selectRaw('COALESCE(SUM(LENGTH(payload)), 0) as b')->value('b');
            }

            return $v !== null ? (int) $v : null;
        } catch (\Throwable $e) {
            return null;
        }
    }
}
