<?php

namespace App\Console\Commands;

use Illuminate\Console\Command;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

class GenteProntidaoCertificarCommand extends Command
{
    protected $signature = 'gente:prontidao-certificar {--json : Saída JSON} {--skip-db : Não valida critérios dependentes de banco}';

    protected $description = 'P7: certifica prontidão operacional mínima (pass/fail) do programa GENTE.';

    public function handle(): int
    {
        $skipDb = (bool) $this->option('skip-db');
        $checks = [];

        $checks['php_minimo_82'] = [
            'ok' => version_compare(PHP_VERSION, '8.2.0', '>='),
            'value' => PHP_VERSION,
        ];
        $checks['bcmath_habilitado'] = [
            'ok' => extension_loaded('bcmath'),
            'value' => extension_loaded('bcmath') ? 'loaded' : 'missing',
        ];

        if ($skipDb) {
            $checks['db_conectividade'] = ['ok' => true, 'value' => 'skip-db'];
            $checks['shadow_schema_ok'] = ['ok' => true, 'value' => 'skip-db'];
            $checks['shadow_sem_critico_30d'] = ['ok' => true, 'value' => 'skip-db'];
            $checks['esocial_dlq_visivel'] = ['ok' => true, 'value' => 'skip-db'];
        } else {
            $checks['db_conectividade'] = $this->quickDbPing();

            if (!($checks['db_conectividade']['ok'] ?? false)) {
                $checks['shadow_schema_ok'] = ['ok' => false, 'value' => 'db-indisponivel'];
                $checks['shadow_sem_critico_30d'] = ['ok' => false, 'value' => 'db-indisponivel'];
                $checks['esocial_dlq_visivel'] = ['ok' => false, 'value' => 'db-indisponivel'];
            } else {
                $checks['shadow_schema_ok'] = $this->check(function () {
                    return Schema::hasTable('SHADOW_RUN')
                        && Schema::hasTable('DIFF_RECONCILIACAO')
                        && Schema::hasTable('SHADOW_RESULTADO_CALC')
                        && Schema::hasTable('job_batches');
                });

                $checks['shadow_sem_critico_30d'] = $this->check(function () {
                    if (!Schema::hasTable('DIFF_RECONCILIACAO')) {
                        return false;
                    }
                    $qtd = DB::table('DIFF_RECONCILIACAO')
                        ->where('CLASSIFICACAO', 'FALHA_SISTEMICA_CRITICA')
                        ->where('created_at', '>=', now()->subDays(30))
                        ->count();
                    return $qtd === 0;
                });

                $checks['esocial_dlq_visivel'] = $this->check(function () {
                    if (!Schema::hasTable('ESOCIAL_EVENTO')) {
                        return false;
                    }
                    return Schema::hasColumn('ESOCIAL_EVENTO', 'DEAD_LETTER_AT')
                        && Schema::hasColumn('ESOCIAL_EVENTO', 'DEAD_LETTER_REASON');
                });
            }
        }

        $pass = collect($checks)->every(fn($c) => (bool) ($c['ok'] ?? false));
        $blockers = $this->buildBlockers($checks);
        $payload = [
            'status' => $pass ? 'pass' : 'fail',
            'go_live_decisao' => $pass ? 'go' : 'no-go',
            'executado_em' => now()->toIso8601String(),
            'checks' => $checks,
            'blockers' => $blockers,
        ];

        if ((bool) $this->option('json')) {
            $this->line(json_encode($payload, JSON_UNESCAPED_UNICODE));
        } else {
            $this->info('Certificação de prontidão: ' . strtoupper($payload['status']));
            foreach ($checks as $name => $check) {
                $mark = $check['ok'] ? 'OK' : 'FAIL';
                $detail = isset($check['value']) ? (string) $check['value'] : ($check['error'] ?? '');
                $this->line("- {$name}: {$mark}" . ($detail !== '' ? " ({$detail})" : ''));
            }
            if (!empty($blockers)) {
                $this->warn('Blockers ativos para go-live:');
                foreach ($blockers as $blocker) {
                    $this->line("- {$blocker['code']}: {$blocker['message']}");
                }
            }
        }

        return $pass ? self::SUCCESS : self::FAILURE;
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

    private function buildBlockers(array $checks): array
    {
        $map = [
            'php_minimo_82' => ['code' => 'BLOQ-P1-PHP', 'message' => 'Runtime PHP abaixo do mínimo para P1.'],
            'bcmath_habilitado' => ['code' => 'BLOQ-P1-BCMATH', 'message' => 'BCMath ausente; cálculo/diff financeiro não homologável.'],
            'db_conectividade' => ['code' => 'BLOQ-P0-DB-CONN', 'message' => 'Banco indisponível ou fora do SLA de conexão; gate completo bloqueado.'],
            'shadow_schema_ok' => ['code' => 'BLOQ-P3-SCHEMA', 'message' => 'Schema de shadow deployment incompleto.'],
            'shadow_sem_critico_30d' => ['code' => 'BLOQ-P3-CRITICO', 'message' => 'Há divergência matemática crítica recente.'],
            'esocial_dlq_visivel' => ['code' => 'BLOQ-P2-DLQ', 'message' => 'Rastreabilidade de DLQ eSocial incompleta.'],
        ];

        $blockers = [];
        if (($checks['db_conectividade']['ok'] ?? true) === false && isset($map['db_conectividade'])) {
            return [$map['db_conectividade']];
        }

        foreach ($checks as $name => $check) {
            if (($check['ok'] ?? false) === false && isset($map[$name])) {
                $blockers[] = $map[$name];
            }
        }

        return $blockers;
    }

    /**
     * Ping curto para evitar travar minutos em timeout de ODBC/SQL Server.
     */
    private function quickDbPing(): array
    {
        $prev = ini_get('default_socket_timeout');
        try {
            ini_set('default_socket_timeout', '6');
            DB::connection()->getPdo();

            return ['ok' => true, 'value' => 'connected'];
        } catch (\Throwable $e) {
            return ['ok' => false, 'error' => mb_substr($e->getMessage(), 0, 220)];
        } finally {
            if ($prev !== false) {
                ini_set('default_socket_timeout', $prev);
            } else {
                ini_set('default_socket_timeout', '60');
            }
        }
    }
}

