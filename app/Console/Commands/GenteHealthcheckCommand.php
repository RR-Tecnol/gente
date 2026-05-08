<?php

namespace App\Console\Commands;

use Illuminate\Console\Command;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

class GenteHealthcheckCommand extends Command
{
    protected $signature = 'gente:healthcheck {--json : Saída em JSON} {--skip-db : Não testa conectividade e contagens no banco}';

    protected $description = 'S9: healthcheck operacional mínimo do programa GENTE';

    public function handle(): int
    {
        $now = now()->toIso8601String();
        $checks = [];
        $skipDb = (bool) $this->option('skip-db');

        if ($skipDb) {
            $checks['db_conexao'] = ['ok' => true, 'value' => 'skip-db'];
        } else {
            $checks['db_conexao'] = $this->check(function () {
                DB::select('SELECT 1 as ok');
                return true;
            });
        }

        $checks['routes_total'] = $this->check(function () {
            return app('router')->getRoutes()->count();
        });
        $checks['p1_php_minimo_82'] = [
            'ok' => version_compare(PHP_VERSION, '8.2.0', '>='),
            'value' => PHP_VERSION,
        ];
        $checks['p1_ext_bcmath'] = [
            'ok' => extension_loaded('bcmath'),
            'value' => extension_loaded('bcmath') ? 'loaded' : 'missing',
        ];

        if ($skipDb) {
            $checks['s6_tabela_prova_vida'] = ['ok' => true, 'value' => 'skip-db'];
            $checks['s7_tabela_esocial_evento'] = ['ok' => true, 'value' => 'skip-db'];
            $checks['s8_tabela_transparencia_exportacao'] = ['ok' => true, 'value' => 'skip-db'];
            $checks['p3_tabela_shadow_run'] = ['ok' => true, 'value' => 'skip-db'];
            $checks['p3_tabela_diff_reconciliacao'] = ['ok' => true, 'value' => 'skip-db'];
            $checks['p3_tabela_job_batches'] = ['ok' => true, 'value' => 'skip-db'];
        } else {
            $checks['s6_tabela_prova_vida'] = $this->check(function () {
                return Schema::hasTable('RPPS_PROVA_VIDA');
            });
            $checks['s7_tabela_esocial_evento'] = $this->check(function () {
                return Schema::hasTable('ESOCIAL_EVENTO');
            });
            $checks['s8_tabela_transparencia_exportacao'] = $this->check(function () {
                return Schema::hasTable('TRANSPARENCIA_EXPORTACAO');
            });
            $checks['p3_tabela_shadow_run'] = $this->check(function () {
                return Schema::hasTable('SHADOW_RUN');
            });
            $checks['p3_tabela_diff_reconciliacao'] = $this->check(function () {
                return Schema::hasTable('DIFF_RECONCILIACAO');
            });
            $checks['p3_tabela_job_batches'] = $this->check(function () {
                return Schema::hasTable('job_batches');
            });
        }

        $metrics = [
            'esocial_rejeitado' => $skipDb ? null : $this->safeCount('ESOCIAL_EVENTO', function ($q) {
                $q->where('STATUS', 'REJEITADO');
            }),
            'esocial_pendente_envio' => $skipDb ? null : $this->safeCount('ESOCIAL_EVENTO', function ($q) {
                $q->whereIn('STATUS', ['PENDENTE_ENVIO', 'GERADO']);
            }),
            'rpps_bloqueados' => $skipDb ? null : $this->safeCount('RPPS_PROVA_VIDA', function ($q) {
                $q->where('STATUS', 'bloqueado');
            }),
            'transparencia_exportacoes_30d' => $skipDb ? null : $this->safeCount('TRANSPARENCIA_EXPORTACAO', function ($q) {
                $q->where('created_at', '>=', now()->subDays(30));
            }),
            'shadow_diff_critico_30d' => $skipDb ? null : $this->safeCount('DIFF_RECONCILIACAO', function ($q) {
                $q->where('CLASSIFICACAO', 'FALHA_SISTEMICA_CRITICA')
                    ->where('created_at', '>=', now()->subDays(30));
            }),
        ];

        $ok = collect($checks)->every(fn($c) => (bool) ($c['ok'] ?? false));
        $payload = [
            'timestamp' => $now,
            'status' => $ok ? 'ok' : 'erro',
            'checks' => $checks,
            'metrics' => $metrics,
        ];

        if ($this->option('json')) {
            $this->line(json_encode($payload, JSON_UNESCAPED_UNICODE));
        } else {
            $this->info('Healthcheck GENTE: ' . strtoupper($payload['status']));
            foreach ($checks as $name => $item) {
                $mark = $item['ok'] ? 'OK' : 'ERRO';
                $detail = isset($item['value']) ? (string) $item['value'] : ($item['error'] ?? '');
                $this->line("- {$name}: {$mark}" . ($detail !== '' ? " ({$detail})" : ''));
            }
            $this->line('Métricas: ' . json_encode($metrics, JSON_UNESCAPED_UNICODE));
        }

        return $ok ? self::SUCCESS : self::FAILURE;
    }

    private function check(callable $fn): array
    {
        try {
            $value = $fn();
            return ['ok' => (bool) $value, 'value' => $value];
        } catch (\Throwable $e) {
            return ['ok' => false, 'error' => $e->getMessage()];
        }
    }

    private function safeCount(string $table, callable $scope): ?int
    {
        try {
            if (!Schema::hasTable($table)) {
                return null;
            }
            $q = DB::table($table);
            $scope($q);
            return (int) $q->count();
        } catch (\Throwable $e) {
            return null;
        }
    }
}
