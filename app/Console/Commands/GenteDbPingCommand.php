<?php

namespace App\Console\Commands;

use Illuminate\Console\Command;
use Illuminate\Support\Facades\Config;
use Illuminate\Support\Facades\DB;

class GenteDbPingCommand extends Command
{
    protected $signature = 'gente:db-ping {--json : Saída JSON}';

    protected $description = 'Testa conexão SQL Server (PDO) com timeout curto e saída auditável.';

    public function handle(): int
    {
        $default = (string) Config::get('database.default', 'sqlsrv');
        $cfg = (array) Config::get("database.connections.{$default}", []);

        $safe = [
            'connection' => $default,
            'host' => $cfg['host'] ?? null,
            'port' => $cfg['port'] ?? null,
            'database' => $cfg['database'] ?? null,
            'username' => $cfg['username'] ?? null,
            'login_timeout' => $cfg['login_timeout'] ?? null,
        ];

        $prev = ini_get('default_socket_timeout');
        $t0 = microtime(true);
        try {
            ini_set('default_socket_timeout', '6');
            DB::connection()->getPdo();
            $ms = (int) round((microtime(true) - $t0) * 1000);
            $payload = [
                'ok' => true,
                'latencia_ms' => $ms,
                'config' => $safe,
                'executado_em' => now()->toIso8601String(),
            ];
            $this->emit($payload);
            return self::SUCCESS;
        } catch (\Throwable $e) {
            $ms = (int) round((microtime(true) - $t0) * 1000);
            $payload = [
                'ok' => false,
                'latencia_ms' => $ms,
                'config' => $safe,
                'erro' => mb_substr($e->getMessage(), 0, 400),
                'dicas' => [
                    'Confirme se o SQL Server está rodando (ex.: docker compose ps, container gente_sqlserver).',
                    'Se `php artisan` roda no host e o compose publica 1433, use DB_HOST=127.0.0.1 (ou localhost). O hostname `sqlserver` só resolve dentro da rede Docker — no container `app`, use DB_HOST=sqlserver.',
                    'Ajuste DB_LOGIN_TIMEOUT no .env se precisar (padrão vem de config/database.php).',
                ],
                'executado_em' => now()->toIso8601String(),
            ];
            $this->emit($payload);
            return self::FAILURE;
        } finally {
            if ($prev !== false) {
                ini_set('default_socket_timeout', $prev);
            } else {
                ini_set('default_socket_timeout', '60');
            }
        }
    }

    private function emit(array $payload): void
    {
        if ($this->option('json')) {
            $this->line(json_encode($payload, JSON_UNESCAPED_UNICODE | JSON_PRETTY_PRINT));
        } else {
            $this->info($payload['ok'] ? 'DB: OK' : 'DB: FALHA');
            foreach ($payload as $k => $v) {
                if ($k === 'dicas' && is_array($v)) {
                    foreach ($v as $d) {
                        $this->line('- ' . $d);
                    }
                    continue;
                }
                if (is_array($v)) {
                    $this->line($k . ': ' . json_encode($v, JSON_UNESCAPED_UNICODE));
                    continue;
                }
                $this->line($k . ': ' . (string) $v);
            }
        }
    }
}
