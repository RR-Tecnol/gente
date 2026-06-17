<?php

namespace App\Console\Commands;

use Illuminate\Console\Command;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

class EsocialProcessarFilaCommand extends Command
{
    protected $signature = 'esocial:processar-fila
                            {--limit=100 : Limite de eventos por execução}';

    protected $description = 'S7 fase 1: processa fila de envio eSocial com retry/backoff';

    public function handle(): int
    {
        if (!Schema::hasTable('ESOCIAL_EVENTO')) {
            $this->warn('Tabela ESOCIAL_EVENTO inexistente.');
            return self::SUCCESS;
        }

        $limit = max(1, (int) $this->option('limit'));
        $maxRetry = max(1, (int) config('esocial.max_retry', 5));
        $cols = DB::getSchemaBuilder()->getColumnListing('ESOCIAL_EVENTO');

        $q = DB::table('ESOCIAL_EVENTO')
            ->whereIn('STATUS', ['PENDENTE_ENVIO', 'REJEITADO'])
            ->orderBy('updated_at');

        if (in_array('NEXT_RETRY_AT', $cols, true)) {
            $q->where(function ($w) {
                $w->whereNull('NEXT_RETRY_AT')->orWhere('NEXT_RETRY_AT', '<=', now());
            });
        }

        $itens = $q->limit($limit)->get();
        $ok = 0;
        $erro = 0;

        foreach ($itens as $ev) {
            $retry = (int) ($ev->RETRY_COUNT ?? 0);
            try {
                // Simulação controlada: XML vazio falha; caso contrário "envia".
                $xml = (string) ($ev->XML_GERADO ?? '');
                if (trim($xml) === '') {
                    throw new \RuntimeException('XML ausente para envio.');
                }

                $recibo = 'REC-' . ($ev->EVENTO_ID ?? '0') . '-' . now()->format('YmdHis');
                $payload = [
                    'STATUS' => 'ENVIADO',
                    'NUMERO_RECIBO' => $recibo,
                    'DT_ENVIO' => now(),
                    'LAST_ERROR' => null,
                    'updated_at' => now(),
                ];
                if (in_array('NEXT_RETRY_AT', $cols, true)) {
                    $payload['NEXT_RETRY_AT'] = null;
                }
                if (in_array('RETRY_COUNT', $cols, true)) {
                    $payload['RETRY_COUNT'] = $retry;
                }
                $payload = array_intersect_key($payload, array_flip($cols));

                DB::table('ESOCIAL_EVENTO')->where('EVENTO_ID', $ev->EVENTO_ID)->update($payload);
                $ok++;
            } catch (\Throwable $e) {
                $retry++;
                $min = [5, 15, 45][min($retry - 1, 2)];
                $isPoisonPill = $retry >= $maxRetry;
                $payload = [
                    'STATUS' => $isPoisonPill ? 'FALHA_PERMANENTE' : 'REJEITADO',
                    'LAST_ERROR' => mb_substr($e->getMessage(), 0, 900),
                    'MOTIVO_ERRO' => mb_substr($e->getMessage(), 0, 240),
                    'updated_at' => now(),
                ];
                if (in_array('RETRY_COUNT', $cols, true)) {
                    $payload['RETRY_COUNT'] = $retry;
                }
                if (in_array('NEXT_RETRY_AT', $cols, true)) {
                    $payload['NEXT_RETRY_AT'] = $isPoisonPill ? null : now()->addMinutes($min);
                }
                if (in_array('DEAD_LETTER_AT', $cols, true)) {
                    $payload['DEAD_LETTER_AT'] = $isPoisonPill ? now() : null;
                }
                if (in_array('DEAD_LETTER_REASON', $cols, true)) {
                    $payload['DEAD_LETTER_REASON'] = $isPoisonPill
                        ? 'Poison pill após exceder max_retry=' . $maxRetry
                        : null;
                }
                $payload = array_intersect_key($payload, array_flip($cols));

                DB::table('ESOCIAL_EVENTO')->where('EVENTO_ID', $ev->EVENTO_ID)->update($payload);
                $erro++;
            }
        }

        $this->info("Processados={$itens->count()} enviados={$ok} rejeitados={$erro}");
        return self::SUCCESS;
    }
}
