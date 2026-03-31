<?php

namespace App\Services;

use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;

class ContabilidadeService
{
    /**
     * Gera lançamentos contábeis automáticos para fechamento de folha.
     * Usa o modelo simplificado: 1 débito + 1 crédito por lançamento.
     */
    public function lancarFolha(int $folhaId, string $competencia): array
    {
        // Buscar totais da folha
        $folha = DB::table('FOLHA')->where('FOLHA_ID', $folhaId)->first();
        if (!$folha) {
            throw new \RuntimeException("Folha #{$folhaId} não encontrada.");
        }

        $totais = DB::table('DETALHE_FOLHA')
            ->where('FOLHA_ID', $folhaId)
            ->selectRaw('
                SUM(COALESCE(DETALHE_FOLHA_PROVENTOS, 0)) as total_proventos,
                SUM(COALESCE(DETALHE_FOLHA_DESCONTOS, 0)) as total_descontos
            ')
            ->first();

        $totalProventos = (float) ($totais->total_proventos ?? 0);
        $totalDescontos = (float) ($totais->total_descontos ?? 0);
        $totalLiquido   = $totalProventos - $totalDescontos;

        // Buscar IDs das contas necessárias
        $contas = DB::table('PCASP_CONTA')
            ->whereIn('CONTA_CODIGO', [
                '3.1.1.1.01', // Vencimentos e Vantagens Fixas (débito)
                '2.1.3.1.01', // Salários e Vantagens a Pagar (crédito)
                '3.1.2.1.01', // Contribuição Patronal IPAM (débito)
                '2.1.3.2.01', // RPPS/IPAM a Recolher (crédito)
            ])
            ->pluck('CONTA_ID', 'CONTA_CODIGO');

        $lancamentos = [];
        $dt = now()->format('Y-m-d');
        $ano = (int) now()->format('Y');
        $mes = (int) now()->format('n');
        $historico = "Folha de pagamento — competência {$competencia}";

        // Lançamento 1: D 3.1.1.1.01 / C 2.1.3.1.01 (vencimentos brutos)
        if ($totalProventos > 0 && isset($contas['3.1.1.1.01'], $contas['2.1.3.1.01'])) {
            $lancamentos[] = DB::table('LANCAMENTO_CONTABIL')->insertGetId([
                'LANCAMENTO_DATA'      => $dt,
                'LANCAMENTO_ANO'       => $ano,
                'LANCAMENTO_MES'       => $mes,
                'LANCAMENTO_HISTORICO' => $historico . ' — vencimentos',
                'LANCAMENTO_VALOR'     => $totalProventos,
                'CONTA_DEBITO_ID'      => $contas['3.1.1.1.01'],
                'CONTA_CREDITO_ID'     => $contas['2.1.3.1.01'],
                'ORIGEM_TIPO'          => 'FOLHA_PAGAMENTO',
                'ORIGEM_ID'            => $folhaId,
                'created_at'           => now(),
                'updated_at'           => now(),
            ]);
        }

        // Lançamento 2: D 3.1.2.1.01 / C 2.1.3.2.01 (patronal IPAM — estimativa 14%)
        $patronal = round($totalProventos * 0.14, 2);
        if ($patronal > 0 && isset($contas['3.1.2.1.01'], $contas['2.1.3.2.01'])) {
            $lancamentos[] = DB::table('LANCAMENTO_CONTABIL')->insertGetId([
                'LANCAMENTO_DATA'      => $dt,
                'LANCAMENTO_ANO'       => $ano,
                'LANCAMENTO_MES'       => $mes,
                'LANCAMENTO_HISTORICO' => $historico . ' — contribuição patronal IPAM',
                'LANCAMENTO_VALOR'     => $patronal,
                'CONTA_DEBITO_ID'      => $contas['3.1.2.1.01'],
                'CONTA_CREDITO_ID'     => $contas['2.1.3.2.01'],
                'ORIGEM_TIPO'          => 'FOLHA_PAGAMENTO',
                'ORIGEM_ID'            => $folhaId,
                'created_at'           => now(),
                'updated_at'           => now(),
            ]);
        }

        Log::info('ContabilidadeService: folha lançada', [
            'folha_id'    => $folhaId,
            'competencia' => $competencia,
            'lancamentos' => count($lancamentos),
            'proventos'   => $totalProventos,
            'patronal'    => $patronal,
        ]);

        return [
            'lancamentos_criados' => count($lancamentos),
            'ids'                 => $lancamentos,
            'total_proventos'     => $totalProventos,
            'total_patronal'      => $patronal,
        ];
    }
}
