<?php

namespace App\Jobs;

use Illuminate\Bus\Batchable;
use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Bus\Dispatchable;
use Illuminate\Queue\InteractsWithQueue;
use Illuminate\Queue\SerializesModels;
use Illuminate\Support\Facades\DB;

class ShadowDiffChunkJob implements ShouldQueue
{
    use Batchable, Dispatchable, InteractsWithQueue, Queueable, SerializesModels;

    public int $tries = 3;

    public function __construct(
        public string $runId,
        public string $competencia,
        public string $limiarRs,
        public array $diffChunk
    ) {
    }

    public function handle(): void
    {
        if (!extension_loaded('bcmath')) {
            throw new \RuntimeException('BCMath não carregado. ShadowDiffChunkJob requer precisão decimal determinística.');
        }

        foreach ($this->diffChunk as $row) {
            $legacy = $this->money((string) ($row['legacy'] ?? '0'));
            $novo = $this->money((string) ($row['novo'] ?? '0'));
            $delta = bcsub($legacy, $novo, 2);
            $absDelta = ltrim($delta, '-');

            $classificacao = 'FALHA_SISTEMICA_CRITICA';
            if (bccomp($absDelta, '0.00', 2) === 0) {
                $classificacao = 'APROVADO_EXATO';
            } elseif (bccomp($absDelta, $this->limiarRs, 2) <= 0) {
                $classificacao = 'DIVERGENCIA_TOLERAVEL';
            } elseif (!empty($row['justificavel'])) {
                $classificacao = 'DIVERGENCIA_JUSTIFICAVEL';
            }

            $agregacao = (string) ($row['agregacao'] ?? 'liquido');
            $ins = [
                'RUN_ID' => $this->runId,
                'COMPETENCIA' => $this->competencia,
                'CPF' => (string) ($row['cpf'] ?? '') ?: null,
                'MATRICULA' => (string) ($row['matricula'] ?? '') ?: null,
                'CHAVE_SERVIDOR' => (string) ($row['servidor_key'] ?? ($row['cpf'] ?? '')) ?: null,
                'VALOR_LEGADO' => $legacy,
                'VALOR_NOVO' => $novo,
                'DELTA_ABSOLUTO' => $absDelta,
                'CLASSIFICACAO' => $classificacao,
                'JUSTIFICADO' => !empty($row['justificavel']),
                'JUSTIFICATIVA' => $row['justificativa'] ?? null,
                'created_at' => now(),
                'updated_at' => now(),
            ];
            if ($agregacao === 'rubrica') {
                $ins['RUBRICA_CODIGO'] = (string) ($row['rubrica_codigo'] ?? '') ?: null;
                $ins['RUBRICA_TIPO'] = (string) ($row['rubrica_tipo'] ?? '') ?: null;
                $ins['AGREGACAO'] = 'rubrica';
            } else {
                $ins['RUBRICA_CODIGO'] = null;
                $ins['RUBRICA_TIPO'] = null;
                $ins['AGREGACAO'] = 'liquido';
            }

            DB::table('DIFF_RECONCILIACAO')->insert($ins);

            $suf = $agregacao === 'rubrica'
                ? 'r' . (string) ($row['rubrica_codigo'] ?? '') . '.' . (string) ($row['rubrica_tipo'] ?? '')
                : 'l';
            $idiff = $this->competencia . '|diff|' . ((string) ($row['cpf'] ?? '')) . '|' . $suf;
            DB::table('SHADOW_CHECKPOINT')->updateOrInsert(
                [
                    'RUN_ID' => $this->runId,
                    'IDEMPOTENCY_KEY' => $idiff,
                    'ETAPA' => 'diff_ok',
                ],
                [
                    'COMPETENCIA' => $this->competencia,
                    'CPF' => (string) ($row['cpf'] ?? '') ?: null,
                    'SERVIDOR_KEY' => (string) ($row['servidor_key'] ?? ($row['cpf'] ?? '')) ?: null,
                    'STATUS' => $classificacao === 'FALHA_SISTEMICA_CRITICA' ? 'erro' : 'ok',
                    'PAYLOAD_HASH' => hash('sha256', json_encode($row, JSON_UNESCAPED_UNICODE)),
                    'DETALHE' => $classificacao,
                    'created_at' => now(),
                    'updated_at' => now(),
                ]
            );
        }
    }

    private function money(string $value): string
    {
        $normalized = str_replace(',', '.', trim($value));
        if ($normalized === '' || !is_numeric($normalized)) {
            return '0.00';
        }

        return bcadd($normalized, '0', 2);
    }
}

