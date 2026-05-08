<?php

namespace App\Jobs;

use App\Models\Folha;
use App\Services\ContabilidadeService;
use App\Services\MotorFolhaService;
use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Bus\Dispatchable;
use Illuminate\Queue\InteractsWithQueue;
use Illuminate\Queue\SerializesModels;
use Illuminate\Support\Facades\Log;

/**
 * Despacha o processamento de folha via MotorFolhaService.
 *
 * Histórico:
 *   - BUG-S2-15: removida dependência de Folha::processarFolha() (não existe no Model).
 *   - Fase 3 (08/05/2026): aposentado FolhaParserService legado. Job agora chama
 *     MotorFolhaService::despacharProcessamentoAssincrono(), que internamente faz batch
 *     de ProcessarLoteFolhaJob (500 servidores por job) + persiste em DETALHE_FOLHA +
 *     EVENTO_DETALHE_FOLHA via PersistenciaRubricasService.
 *
 * Este Job continua existindo para manter a interface da rota legada
 * `FolhaController::inserir`. O caminho de rotas SPA Vue 3 (routes/folha.php) já chama
 * MotorFolhaService diretamente.
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

    public function handle(MotorFolhaService $motor): void
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

        Log::info("[ProcessarFolhaJob] Iniciando processamento da Folha {$folhaId} (competência {$folha->FOLHA_COMPETENCIA}) pelo usuário {$this->userId} via MotorFolhaService.");

        // Fase 3: usa o caminho síncrono in-process do MotorFolha
        // (este Job já está rodando assíncrono — não precisa de novo batch interno).
        $resultado = $motor->calcularFolha((int) $folhaId);

        if (! ($resultado['ok'] ?? false)) {
            Log::error("[ProcessarFolhaJob] MotorFolha retornou erro.", [
                'folha_id' => $folhaId,
                'erro' => $resultado['erro'] ?? 'sem detalhes',
            ]);
            return;
        }

        Log::info("[ProcessarFolhaJob] MotorFolha concluiu cálculo.", [
            'folha_id'        => $folhaId,
            'servidores'      => $resultado['servidores'] ?? 0,
            'total_proventos' => $resultado['total_proventos'] ?? 0,
            'total_descontos' => $resultado['total_descontos'] ?? 0,
            'total_liquido'   => $resultado['total_liquido'] ?? 0,
        ]);

        // Gerar lançamentos contábeis automáticos após o processamento.
        // Falha contábil não reverte a folha — apenas loga o erro
        // (idempotência R7-R10 garante que reprocesso não duplica).
        try {
            $contabilidade = new ContabilidadeService();
            $contabRes = $contabilidade->lancarFolha((int) $folha->FOLHA_ID, (string) $folha->FOLHA_COMPETENCIA);
            Log::info("[ProcessarFolhaJob] Lançamentos contábeis gerados.", [
                'folha_id'    => $folhaId,
                'lancamentos' => $contabRes['lancamentos_criados'],
                'proventos'   => $contabRes['total_proventos'],
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
