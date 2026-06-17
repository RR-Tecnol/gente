<?php

namespace App\Console\Commands;

use App\Services\Smoke\SmokeTeiaFolhaOptions;
use App\Services\Smoke\SmokeTeiaFolhaRunner;
use Illuminate\Console\Command;
use Illuminate\Support\Facades\DB;

class GenteSmokeTeia7aCommand extends Command
{
    protected $signature = 'gente:smoke-teia-7a
                            {--json : Saída em JSON (UTF-8)}
                            {--dry-run : Modo seguro (por omissão); transação é revertida — mesmo efeito que omitir --write}
                            {--write : Persiste alterações na base (não usar fora de BD de teste)}
                            {--funcionario-id= : FUNCIONARIO_ID âncora para o fluxo 1}
                            {--folha-id= : FOLHA_ID opcional}
                            {--competencia= : Competência AAAA-MM}
                            {--tenant-scope-log : Fluxo 4b — inspecionar ficheiro tenant_scope.log}
                            {--log-file= : Gravar relatório JSON (nome ou caminho sob storage/logs)}';

    protected $description = 'Fase 7A — Smoke da Teia e Motor da Folha (pass|fail|skip por fluxo)';

    public function handle(): int
    {
        $opts = new SmokeTeiaFolhaOptions(
            funcionarioId: $this->option('funcionario-id') !== null && $this->option('funcionario-id') !== ''
                ? (int) $this->option('funcionario-id')
                : null,
            folhaId: $this->option('folha-id') !== null && $this->option('folha-id') !== ''
                ? (int) $this->option('folha-id')
                : null,
            competencia: $this->option('competencia') !== null && $this->option('competencia') !== ''
                ? (string) $this->option('competencia')
                : null,
            checkTenantScopeLog: (bool) $this->option('tenant-scope-log'),
        );

        $write = (bool) $this->option('write');
        $runner = new SmokeTeiaFolhaRunner();

        if ($write) {
            $rows = $runner->run($opts);
        } else {
            DB::beginTransaction();
            try {
                $rows = $runner->run($opts);
            } finally {
                DB::rollBack();
            }
        }

        $logFile = (string) ($this->option('log-file') ?? '');
        if ($logFile !== '') {
            $path = $this->resolveLogPath($logFile);
            $dir = dirname($path);
            if (! is_dir($dir)) {
                mkdir($dir, 0755, true);
            }
            file_put_contents($path, json_encode($rows, JSON_UNESCAPED_UNICODE | JSON_PRETTY_PRINT)."\n");
            if (! $this->option('json')) {
                $this->info('Relatório JSON: '.$path);
            }
        }

        if ($this->option('json')) {
            $this->line(json_encode($rows, JSON_UNESCAPED_UNICODE | JSON_PRETTY_PRINT));
        } else {
            $this->table(
                ['fluxo', 'status', 'detalhe', 'onde_rompeu'],
                array_map(fn (array $r) => [
                    $r['fluxo'],
                    strtoupper($r['status']),
                    $r['detalhe'],
                    $r['onde_rompeu'],
                ], $rows)
            );
            if (! $write) {
                $this->warn('Transação revertida (use --write para persistir alterações; não recomendado fora de BD de teste).');
            }
        }

        $hasFail = false;
        foreach ($rows as $r) {
            if (($r['status'] ?? '') === 'fail') {
                $hasFail = true;
                break;
            }
        }

        return $hasFail ? 1 : 0;
    }

    private function resolveLogPath(string $logFile): string
    {
        if ($logFile[0] === '/' || preg_match('#^[A-Za-z]:\\\\#', $logFile)) {
            return $logFile;
        }

        return storage_path('logs/'.ltrim($logFile, '/'));
    }
}
