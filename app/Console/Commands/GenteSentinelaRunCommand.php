<?php

namespace App\Console\Commands;

use App\Support\IntegritySentinelService;
use Illuminate\Console\Command;

class GenteSentinelaRunCommand extends Command
{
    protected $signature = 'gente:sentinela-run {--json : Exibe payload JSON}';

    protected $description = 'Executa probes da Sentinela de Integridade e persiste status em cache';

    public function handle(): int
    {
        $payload = IntegritySentinelService::run(true);
        if ($this->option('json')) {
            $this->line(json_encode($payload, JSON_UNESCAPED_UNICODE));
        } else {
            $this->info('Sentinela: ' . strtoupper((string) ($payload['status'] ?? 'unknown')));
        }

        return ($payload['status'] ?? 'critical') === 'ok' ? self::SUCCESS : self::FAILURE;
    }
}

