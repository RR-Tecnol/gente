<?php

namespace App\Console\Commands;

use Illuminate\Console\Command;
use Illuminate\Support\Facades\DB;

class E2EMotorFolha extends Command
{
    protected $signature = 'motor:e2e';
    protected $description = 'Parte 12 — Teste E2E do Motor de Folha (competência 202503)';

    public function handle(): int
    {
        $this->info('═══════════════════════════════════════');
        $this->info(' PARTE 12 — E2E MOTOR DE FOLHA 202503');
        $this->info('═══════════════════════════════════════');

        // ── PASSO 1: Criar/recuperar folha 202503 ──
        $this->info("\nPASSO 1 — Criando folha 202503...");
        $existe = DB::table('FOLHA')->where('FOLHA_COMPETENCIA', '202503')->first();
        if ($existe) {
            $folhaId = $existe->FOLHA_ID;
            $this->warn("  Folha 202503 já existe. FOLHA_ID={$folhaId}");
        } else {
            $folhaId = DB::table('FOLHA')->insertGetId([
                'FOLHA_COMPETENCIA' => '202503',
                'FOLHA_STATUS' => 'Aberta',
                'FOLHA_DESCRICAO' => 'Folha Marco 2026',
            ]);
            $this->info("  ✅ Folha 202503 CRIADA. FOLHA_ID={$folhaId}");
        }

        // ── PASSO 2: Confirmar folha ──
        $this->info("\nPASSO 2 — Verificando folha...");
        $folha = DB::table('FOLHA')->where('FOLHA_COMPETENCIA', '202503')->first();
        $this->line("  ID={$folha->FOLHA_ID} | Comp={$folha->FOLHA_COMPETENCIA} | Status={$folha->FOLHA_STATUS}");

        // ── PASSO 3: Rodar o motor ──
        $this->info("\nPASSO 3 — Rodando MotorFolhaService...");
        try {
            $motor = new \App\Services\MotorFolhaService();
            $result = $motor->calcularFolha((int) $folhaId);

            if ($result['ok']) {
                $this->info("  ✅ Motor concluiu com SUCESSO!");
                $this->line("  servidores    : " . ($result['servidores'] ?? 0));
                $this->line("  total_proventos: R$ " . number_format($result['total_proventos'] ?? 0, 2, ',', '.'));
                $this->line("  total_descontos: R$ " . number_format($result['total_descontos'] ?? 0, 2, ',', '.'));
                $this->line("  total_liquido  : R$ " . number_format($result['total_liquido'] ?? 0, 2, ',', '.'));
            } else {
                $this->error("  ❌ Motor retornou erro: " . ($result['erro'] ?? 'desconhecido'));
                return 1;
            }
        } catch (\Throwable $e) {
            $this->error("  ❌ EXCEÇÃO: " . $e->getMessage());
            $this->line("  Arquivo : " . $e->getFile() . " linha " . $e->getLine());
            $this->line("  Trace:\n" . substr($e->getTraceAsString(), 0, 800));
            return 1;
        }

        // ── PASSO 4: Contar DETALHE_FOLHA ──
        $this->info("\nPASSO 4 — Verificando DETALHE_FOLHA...");
        $qtd = DB::table('DETALHE_FOLHA')->where('FOLHA_ID', $folhaId)->count();
        $this->info("  {$qtd} registros calculados");

        // Top 10 servidores
        $this->info("\nPASSO 4b — Top 10 servidores calculados:");
        $rows = DB::table('DETALHE_FOLHA as df')
            ->join('FUNCIONARIO as f', 'f.FUNCIONARIO_ID', '=', 'df.FUNCIONARIO_ID')
            ->join('PESSOA as p', 'p.PESSOA_ID', '=', 'f.PESSOA_ID')
            ->where('df.FOLHA_ID', $folhaId)
            ->select([
                'p.PESSOA_NOME',
                'f.FUNCIONARIO_MATRICULA',
                'df.DETALHE_FOLHA_PROVENTOS',
                'df.DETALHE_FOLHA_DESCONTOS',
                'df.DETALHE_FOLHA_LIQUIDO',
                'df.DETALHE_COMPLEMENTO_SM',
            ])
            ->limit(10)
            ->get();

        $headers = ['Nome', 'Matrícula', 'Proventos', 'Descontos', 'Líquido', 'Comp.SM'];
        $data = $rows->map(fn($r) => [
            substr($r->PESSOA_NOME ?? '?', 0, 30),
            $r->FUNCIONARIO_MATRICULA ?? '?',
            'R$ ' . number_format($r->DETALHE_FOLHA_PROVENTOS ?? 0, 2, ',', '.'),
            'R$ ' . number_format($r->DETALHE_FOLHA_DESCONTOS ?? 0, 2, ',', '.'),
            'R$ ' . number_format($r->DETALHE_FOLHA_LIQUIDO ?? 0, 2, ',', '.'),
            'R$ ' . number_format($r->DETALHE_COMPLEMENTO_SM ?? 0, 2, ',', '.'),
        ])->toArray();

        $this->table($headers, $data);

        $this->info("\n✅ PARTE 12 CONCLUÍDA — Motor de Folha operacional!");
        return 0;
    }
}
