<?php

namespace App\Jobs;

use Illuminate\Bus\Batchable;
use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Bus\Dispatchable;
use Illuminate\Queue\InteractsWithQueue;
use Illuminate\Queue\SerializesModels;
use Illuminate\Support\Facades\DB;

class ShadowIngestChunkJob implements ShouldQueue
{
    use Batchable, Dispatchable, InteractsWithQueue, Queueable, SerializesModels;

    public int $tries = 3;

    public function __construct(
        public string $runId,
        public string $competencia,
        public string $snapshotSha,
        public array $servidoresChunk
    ) {
    }

    public function handle(): void
    {
        foreach ($this->servidoresChunk as $s) {
            $cpf = (string) ($s['cpf'] ?? '');
            $idempotencyKey = $this->competencia . '|' . $this->snapshotSha . '|' . $cpf;
            $payloadHash = hash('sha256', json_encode($s, JSON_UNESCAPED_UNICODE));

            DB::table('SHADOW_CHECKPOINT')->updateOrInsert(
                [
                    'RUN_ID' => $this->runId,
                    'IDEMPOTENCY_KEY' => $idempotencyKey,
                    'ETAPA' => 'etl_ok',
                ],
                [
                    'COMPETENCIA' => $this->competencia,
                    'CPF' => $cpf ?: null,
                    'SERVIDOR_KEY' => (string) ($s['servidor_key'] ?? $cpf),
                    'STATUS' => 'ok',
                    'PAYLOAD_HASH' => $payloadHash,
                    'DETALHE' => null,
                    'created_at' => now(),
                    'updated_at' => now(),
                ]
            );
        }
    }
}

