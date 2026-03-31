<?php

namespace App\Jobs;

use App\Models\Folha;
use App\Services\FolhaParserService;
use App\Services\ContabilidadeService;
use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Bus\Dispatchable;
use Illuminate\Queue\InteractsWithQueue;
use Illuminate\Queue\SerializesModels;
use Illuminate\Support\Facades\Log;

/**
 * BUG-S2-15 corrigido: Folha::processarFolha() não existe no Model.
 * O job agora usa FolhaParserService::processar() corretamente.
 */
class ProcessarFolhaJob implements ShouldQueue
{
    use Dispatchable, InteractsWithQueue, Queueable, SerializesModels;

    private array $request;
    private ?int $userId;

    public function __construct(array $request, ?int $userId)
    {
        $this->request = $request;
        $this->userId = $userId;
    }

    public function handle(FolhaParserService $parser): void
    {
        $folhaId = $this->request['FOLHA_ID'] ?? null;

        if (!$folhaId) {
            Log::warning('[ProcessarFolhaJob] FOLHA_ID não informado — job ignorado.');
            return;
        }

        $folha = Folha::find($folhaId);

        if (!$folha) {
            Log::error("[ProcessarFolhaJob] Folha {$folhaId} não encontrada.");
            return;
        }

        Log::info("[ProcessarFolhaJob] Iniciando processamento da Folha {$folhaId} (competência {$folha->FOLHA_COMPETENCIA}) pelo usuário {$this->userId}.");

        $parser->processar($folha);

        // Gerar lançamentos contábeis automáticos após o processamento
        // Falha contábil não reverte a folha — apenas loga o erro
        try {
            $contabilidade = new ContabilidadeService();
            $resultado = $contabilidade->lancarFolha($folha->FOLHA_ID, (string) $folha->FOLHA_COMPETENCIA);
            Log::info("[ProcessarFolhaJob] Lançamentos contábeis gerados.", [
                'folha_id'    => $folhaId,
                'lancamentos' => $resultado['lancamentos_criados'],
                'proventos'   => $resultado['total_proventos'],
            ]);
        } catch (\Throwable $e) {
            Log::error("[ProcessarFolhaJob] Falha nos lançamentos contábeis — folha não revertida.", [
                'folha_id' => $folhaId,
                'erro'     => $e->getMessage(),
            ]);
        }

        Log::info("[ProcessarFolhaJob] Folha {$folhaId} processada com sucesso.");
    }
}
