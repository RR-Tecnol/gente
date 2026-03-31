<?php

use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Auth;

/**
 * DIRF — Declaração de Imposto de Renda Retido na Fonte
 * Prazo: último dia útil de fevereiro do ano seguinte.
 * Aplica-se a todos os servidores com IRRF retido no ano.
 *
 * Layout: arquivo texto posicional Receita Federal.
 * Código de receita: 0561 (trabalho assalariado).
 */

// Preview dos beneficiários e totais do ano
Route::get('/dirf/preview/{ano}', function (int $ano) {
    try {
        // Somar IRRF retido por servidor no ano via DETALHE_FOLHA
        $beneficiarios = DB::table('DETALHE_FOLHA as df')
            ->join('FOLHA as f', 'f.FOLHA_ID', '=', 'df.FOLHA_ID')
            ->join('FUNCIONARIO as fn', 'fn.FUNCIONARIO_ID', '=', 'df.FUNCIONARIO_ID')
            ->join('PESSOA as p', 'p.PESSOA_ID', '=', 'fn.PESSOA_ID')
            ->whereYear('f.FOLHA_COMPETENCIA', $ano)
            ->whereNull('df.DETALHE_FOLHA_ERRO')
            ->groupBy(
                'df.FUNCIONARIO_ID', 'p.PESSOA_NOME',
                'p.PESSOA_CPF_NUMERO', 'p.PESSOA_DATA_NASCIMENTO'
            )
            ->havingRaw('SUM(COALESCE(df.DETALHE_FOLHA_DESCONTOS, 0)) > 0')
            ->select(
                'df.FUNCIONARIO_ID',
                'p.PESSOA_NOME as nome',
                'p.PESSOA_CPF_NUMERO as cpf',
                'p.PESSOA_DATA_NASCIMENTO as nascimento',
                DB::raw('SUM(COALESCE(df.DETALHE_FOLHA_PROVENTOS, 0)) as total_rendimentos'),
                DB::raw('SUM(COALESCE(df.DETALHE_FOLHA_DESCONTOS, 0)) as total_irrf')
            )
            ->get();

        return response()->json([
            'ano'                   => $ano,
            'beneficiarios'         => $beneficiarios,
            'total_beneficiarios'   => $beneficiarios->count(),
            'total_rendimentos'     => $beneficiarios->sum('total_rendimentos'),
            'total_irrf'            => $beneficiarios->sum('total_irrf'),
        ]);
    } catch (\Throwable $e) {
        return response()->json(['erro' => $e->getMessage()], 500);
    }
});

// Gerar arquivo DIRF
Route::post('/dirf/gerar/{ano}', function (int $ano) {
    try {
        $user  = Auth::user();
        $cnpj  = preg_replace('/\D/', '', env('ORGAO_CNPJ', '06223007000114'));
        $razao = str_pad(mb_strtoupper(env('ORGAO_NOME', 'PREFEITURA MUNICIPAL'), 'UTF-8'), 60);

        $beneficiarios = DB::table('DETALHE_FOLHA as df')
            ->join('FOLHA as f', 'f.FOLHA_ID', '=', 'df.FOLHA_ID')
            ->join('FUNCIONARIO as fn', 'fn.FUNCIONARIO_ID', '=', 'df.FUNCIONARIO_ID')
            ->join('PESSOA as p', 'p.PESSOA_ID', '=', 'fn.PESSOA_ID')
            ->whereYear('f.FOLHA_COMPETENCIA', $ano)
            ->whereNull('df.DETALHE_FOLHA_ERRO')
            ->groupBy(
                'df.FUNCIONARIO_ID', 'p.PESSOA_NOME',
                'p.PESSOA_CPF_NUMERO', 'p.PESSOA_DATA_NASCIMENTO'
            )
            ->havingRaw('SUM(COALESCE(df.DETALHE_FOLHA_DESCONTOS, 0)) > 0')
            ->select(
                'p.PESSOA_NOME as nome',
                'p.PESSOA_CPF_NUMERO as cpf',
                DB::raw('SUM(COALESCE(df.DETALHE_FOLHA_PROVENTOS, 0)) as total_rendimentos'),
                DB::raw('SUM(COALESCE(df.DETALHE_FOLHA_DESCONTOS, 0)) as total_irrf')
            )
            ->get();

        $linhas = [];

        // Registro DIRF — Identificação da Declaração
        $linhas[] = 'DIRF';
        $linhas[] = 'INFDIRF';
        $linhas[] = str_pad($ano, 4) . '0' . $cnpj . $razao . str_pad('', 10);

        $totalIrrf        = 0;
        $totalRendimentos = 0;

        foreach ($beneficiarios as $b) {
            $cpf         = str_pad(preg_replace('/\D/', '', $b->cpf ?? ''), 11, '0', STR_PAD_LEFT);
            $nome        = str_pad(mb_substr(mb_strtoupper($b->nome ?? '', 'UTF-8'), 0, 60), 60);
            $rendimentos = str_pad(str_replace('.', '', number_format((float)$b->total_rendimentos, 2, '.', '')), 14, '0', STR_PAD_LEFT);
            $irrf        = str_pad(str_replace('.', '', number_format((float)$b->total_irrf, 2, '.', '')), 14, '0', STR_PAD_LEFT);

            // Registro BPFDEC — beneficiário pessoa física
            $linhas[] = 'BPFDEC' . $cpf . $nome . str_pad($ano, 4)
                . '0561' // código receita trabalho assalariado
                . $rendimentos . $irrf;

            $totalIrrf        += (float) $b->total_irrf;
            $totalRendimentos += (float) $b->total_rendimentos;
        }

        // Registro de encerramento
        $linhas[] = 'FIMDIRF';

        $conteudo    = implode("\r\n", $linhas);
        $nomeArquivo = "DIRF_{$ano}_" . date('YmdHis') . ".txt";

        DB::table('DIRF_ENVIO')->updateOrInsert(
            ['DIRF_ANO' => $ano],
            [
                'DIRF_STATUS'               => 'GERADO',
                'DIRF_TOTAL_BENEFICIARIOS'  => count($beneficiarios),
                'DIRF_TOTAL_RENDIMENTOS'    => round($totalRendimentos, 2),
                'DIRF_TOTAL_IRRF'           => round($totalIrrf, 2),
                'DIRF_ARQUIVO_NOME'         => $nomeArquivo,
                'DIRF_CONTEUDO'             => $conteudo,
                'GERADO_POR'                => $user->USUARIO_ID ?? null,
                'updated_at'                => now(),
                'created_at'                => now(),
            ]
        );

        return response($conteudo, 200, [
            'Content-Type'        => 'text/plain; charset=UTF-8',
            'Content-Disposition' => "attachment; filename=\"{$nomeArquivo}\"",
            'X-Total-Beneficiarios' => count($beneficiarios),
            'X-Total-IRRF'          => round($totalIrrf, 2),
        ]);
    } catch (\Throwable $e) {
        return response()->json(['erro' => $e->getMessage()], 500);
    }
});

// Histórico
Route::get('/dirf/historico', function () {
    try {
        return response()->json([
            'historico' => DB::table('DIRF_ENVIO')->orderByDesc('DIRF_ANO')->limit(10)->get()
        ]);
    } catch (\Throwable $e) {
        return response()->json(['erro' => $e->getMessage()], 500);
    }
});
