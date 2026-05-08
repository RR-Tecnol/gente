<?php

namespace App\Jobs;

use App\Services\MotorFolhaService;
use Illuminate\Bus\Batchable;
use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Bus\Dispatchable;
use Illuminate\Queue\InteractsWithQueue;
use Illuminate\Queue\SerializesModels;

class ProcessarLoteFolhaJob implements ShouldQueue
{
    use Batchable;
    use Dispatchable;
    use InteractsWithQueue;
    use Queueable;
    use SerializesModels;

    public int $tries = 3;

    /**
     * @param  list<int>  $funcionarioIds
     */
    public function __construct(
        public int $folhaId,
        public array $funcionarioIds
    ) {
    }

    public function handle(MotorFolhaService $motor): void
    {
        if ($this->batch()?->cancelled()) {
            return;
        }

        $ids = array_values(array_unique(array_map('intval', $this->funcionarioIds)));
        $contexto = MotorFolhaService::prepararContextoLote($this->folhaId, $ids);

        $out = $motor->calcularLoteParaFuncionarios($this->folhaId, $ids, $contexto);
        if (! ($out['ok'] ?? false)) {
            throw new \RuntimeException($out['erro'] ?? 'Falha no cálculo do lote da folha.');
        }
    }
}
