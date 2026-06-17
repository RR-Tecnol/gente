<?php

namespace App\Jobs;

use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Schema;
use Illuminate\Support\Facades\Storage;

/**
 * Frente 4: cópia periódica de AUDIT_LOG para disco remoto (S3 / destino privado) — prova fora do BD.
 */
class StreamAuditToSecureVault
{
    public function handle(): void
    {
        if (! (bool) config('gente.secure_vault.enabled', true)) {
            return;
        }
        if (! Schema::hasTable('AUDIT_LOG')) {
            return;
        }

        $stateFile = storage_path('app/gente_audit_vault_state.json');
        $last = 0;
        if (is_file($stateFile)) {
            $j = json_decode((string) @file_get_contents($stateFile), true);
            if (is_array($j) && isset($j['last_id'])) {
                $last = (int) $j['last_id'];
            }
        }

        $maxId = (int) DB::table('AUDIT_LOG')->max('id');
        if ($maxId <= 0) {
            return;
        }
        if ($last >= $maxId) {
            return;
        }
        $batch = max(100, (int) config('gente.secure_vault.batch_size', 2000));
        $q = DB::table('AUDIT_LOG')->orderBy('id');
        if ($last > 0) {
            $q->where('id', '>', $last);
        }
        $rows = $q->limit($batch)->get();
        if ($rows->isEmpty()) {
            return;
        }

        $lines = [];
        $newMax = $last;
        foreach ($rows as $r) {
            $ar = (array) $r;
            $line = json_encode($ar, JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES);
            if ($line !== false) {
                $lines[] = $line;
            }
            $id = (int) ($ar['id'] ?? 0);
            if ($id > $newMax) {
                $newMax = $id;
            }
        }
        if ($lines === []) {
            return;
        }
        $disk = (string) config('gente.secure_vault.disk', 'local');
        $dir = trim((string) config('gente.secure_vault.path', 'secure_vault/audit'), '/');
        $name = $dir.'/'.'audit-'.now()->format('Y-m-d_His').'-'.$last.'-to-'.$newMax.'.jsonl';
        $body = implode("\n", $lines)."\n";
        $ok = Storage::disk($disk)->put($name, $body, [
            'visibility' => 'private',
        ]);
        if (! $ok) {
            Log::channel('security')->error('gente_audit_vault_falha_escrita', [
                'disk' => $disk,
                'path' => $name,
            ]);

            return;
        }
        $payload = [
            'last_id' => $newMax,
            'at' => now()->toIso8601String(),
        ];
        @file_put_contents(
            $stateFile,
            json_encode($payload, JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES)
        );
        Log::channel('security')->info('gente_audit_vault_export', [
            'n' => count($lines),
            'id_max' => $newMax,
            'disk' => $disk,
            'file' => $name,
        ]);
    }
}
