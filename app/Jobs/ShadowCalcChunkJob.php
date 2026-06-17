<?php

namespace App\Jobs;

use App\Services\MotorFolhaService;
use Illuminate\Bus\Batchable;
use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Bus\Dispatchable;
use Illuminate\Queue\InteractsWithQueue;
use Illuminate\Queue\SerializesModels;
use Illuminate\Support\Facades\DB;

class ShadowCalcChunkJob implements ShouldQueue
{
    use Batchable, Dispatchable, InteractsWithQueue, Queueable, SerializesModels;

    public int $tries = 3;

    public function __construct(
        public string $runId,
        public string $competencia,
        public array $servidoresChunk
    ) {
    }

    public function handle(): void
    {
        $folhaIds = collect($this->servidoresChunk)
            ->pluck('folha_id')
            ->filter(fn ($v) => is_numeric($v))
            ->map(fn ($v) => (int) $v)
            ->unique()
            ->values()
            ->all();

        $motor = new MotorFolhaService();
        foreach ($folhaIds as $folhaId) {
            $folhaKey = $this->competencia . '|calc_folha|' . $folhaId;
            $checkpoint = DB::table('SHADOW_CHECKPOINT')
                ->where('RUN_ID', $this->runId)
                ->where('IDEMPOTENCY_KEY', $folhaKey)
                ->where('ETAPA', 'calc_folha')
                ->first();

            if (!$checkpoint) {
                try {
                    $motor->calcularFolha($folhaId);
                    DB::table('SHADOW_CHECKPOINT')->insert([
                        'RUN_ID' => $this->runId,
                        'COMPETENCIA' => $this->competencia,
                        'CPF' => null,
                        'SERVIDOR_KEY' => 'folha:' . $folhaId,
                        'ETAPA' => 'calc_folha',
                        'STATUS' => 'ok',
                        'IDEMPOTENCY_KEY' => $folhaKey,
                        'PAYLOAD_HASH' => null,
                        'DETALHE' => 'MotorFolhaService executado para FOLHA_ID=' . $folhaId,
                        'created_at' => now(),
                        'updated_at' => now(),
                    ]);
                } catch (\Throwable $e) {
                    DB::table('SHADOW_CHECKPOINT')->insert([
                        'RUN_ID' => $this->runId,
                        'COMPETENCIA' => $this->competencia,
                        'CPF' => null,
                        'SERVIDOR_KEY' => 'folha:' . $folhaId,
                        'ETAPA' => 'calc_folha',
                        'STATUS' => 'erro',
                        'IDEMPOTENCY_KEY' => $folhaKey,
                        'PAYLOAD_HASH' => null,
                        'DETALHE' => mb_substr($e->getMessage(), 0, 240),
                        'created_at' => now(),
                        'updated_at' => now(),
                    ]);
                    continue;
                }
            }
        }

        foreach ($this->servidoresChunk as $s) {
            $cpf = (string) ($s['cpf'] ?? '');
            $folhaId = is_numeric($s['folha_id'] ?? null) ? (int) $s['folha_id'] : null;
            $idempotencyKey = $this->competencia . '|calc|' . $cpf;
            $payloadHash = hash('sha256', json_encode($s, JSON_UNESCAPED_UNICODE));

            $resultado = null;
            if ($cpf !== '' && $folhaId !== null) {
                $resultado = DB::table('DETALHE_FOLHA as df')
                    ->join('FUNCIONARIO as f', 'f.FUNCIONARIO_ID', '=', 'df.FUNCIONARIO_ID')
                    ->join('PESSOA as p', 'p.PESSOA_ID', '=', 'f.PESSOA_ID')
                    ->where('df.FOLHA_ID', $folhaId)
                    ->where('p.PESSOA_CPF_NUMERO', $cpf)
                    ->select([
                        'df.FUNCIONARIO_ID',
                        'f.FUNCIONARIO_MATRICULA as MATRICULA',
                        'df.DETALHE_FOLHA_LIQUIDO as LIQUIDO',
                        'df.DETALHE_FOLHA_PROVENTOS as PROVENTOS',
                        'df.DETALHE_FOLHA_DESCONTOS as DESCONTOS',
                    ])
                    ->first();
            }

            if ($resultado) {
                DB::table('SHADOW_RESULTADO_CALC')->updateOrInsert(
                    [
                        'RUN_ID' => $this->runId,
                        'COMPETENCIA' => $this->competencia,
                        'CPF' => $cpf,
                    ],
                    [
                        'FOLHA_ID' => $folhaId,
                        'FUNCIONARIO_ID' => (int) $resultado->FUNCIONARIO_ID,
                        'MATRICULA' => (string) ($resultado->MATRICULA ?? ''),
                        'VALOR_LIQUIDO' => (string) $resultado->LIQUIDO,
                        'VALOR_PROVENTOS' => (string) $resultado->PROVENTOS,
                        'VALOR_DESCONTOS' => (string) $resultado->DESCONTOS,
                        'FONTE' => 'motorfolha',
                        'created_at' => now(),
                        'updated_at' => now(),
                    ]
                );
            }

            DB::table('SHADOW_CHECKPOINT')->updateOrInsert(
                [
                    'RUN_ID' => $this->runId,
                    'IDEMPOTENCY_KEY' => $idempotencyKey,
                    'ETAPA' => 'calc_ok',
                ],
                [
                    'COMPETENCIA' => $this->competencia,
                    'CPF' => $cpf ?: null,
                    'SERVIDOR_KEY' => (string) ($s['servidor_key'] ?? $cpf),
                    'STATUS' => $resultado ? 'ok' : 'erro',
                    'PAYLOAD_HASH' => $payloadHash,
                    'DETALHE' => $resultado
                        ? 'Resultado do MotorFolhaService persistido em SHADOW_RESULTADO_CALC.'
                        : 'Sem resultado calculado para CPF/FOLHA no DETALHE_FOLHA.',
                    'created_at' => now(),
                    'updated_at' => now(),
                ]
            );
        }
    }
}

