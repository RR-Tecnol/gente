<?php

namespace App\Console\Commands;

use App\Support\AuditLogChainer;
use Illuminate\Console\Command;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

class AuditVerifyChain extends Command
{
    protected $signature = 'gente:audit-verify-chain {--json : Saída JSON}';

    protected $description = 'Verifica a integridade da cadeia HASH em AUDIT_LOG.';

    public function handle(): int
    {
        if (! Schema::hasTable('AUDIT_LOG') || ! Schema::hasColumn('AUDIT_LOG', 'HASH_CONCAT')) {
            $out = [
                'ok' => true,
                'skip' => 'AUDIT_LOG sem coluna HASH_CONCAT ou tabela inexistente.',
            ];
            $this->emit($out);

            return self::SUCCESS;
        }

        $rows = DB::table('AUDIT_LOG')->orderBy('id')->cursor();
        $genesis = hash('sha256', 'GENTE_AUDIT_CHAIN_GENESIS_V1');
        $prev = $genesis;
        $verificados = 0;
        $sem_hash = 0;
        $broken = [];
        foreach ($rows as $r) {
            $a = (array) $r;
            $h = $a['HASH_CONCAT'] ?? null;
            if (! is_string($h) || strlen($h) !== 64) {
                $sem_hash++;

                continue;
            }
            $verificados++;
            $without = $a;
            foreach (array_keys($without) as $k) {
                if (strtoupper((string) $k) === 'HASH_CONCAT') {
                    unset($without[$k]);
                }
            }
            $body = AuditLogChainer::canonizeRow($without);
            $expected = hash('sha256', $body . $prev);
            if ($expected !== $h) {
                $broken[] = (int) ($a['id'] ?? 0);
            }
            $prev = $h;
        }
        $ok = $broken === [];
        $out = [
            'ok' => $ok,
            'verificados' => $verificados,
            'sem_hash_legado' => $sem_hash,
            'rompidos' => $broken,
        ];
        $this->emit($out);

        return $ok ? self::SUCCESS : self::FAILURE;
    }

    private function emit(array $payload): void
    {
        if ($this->option('json')) {
            $this->line(json_encode($payload, JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES));
        } else {
            if ($payload['ok'] && isset($payload['skip'])) {
                $this->info($payload['skip']);
            } elseif ($payload['ok'] ?? false) {
                $this->info('Cadeia de auditoria: OK. Verificados: '.($payload['verificados'] ?? 0)
                    .'; legado sem hash: '.($payload['sem_hash_legado'] ?? 0).'.');
            } else {
                $this->error('Cadeia rompida (IDs: '.implode(', ', $payload['rompidos'] ?? []).')');
            }
        }
    }
}
