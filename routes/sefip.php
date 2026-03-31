<?php

use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Auth;

/**
 * SEFIP/GFIP — Recolhimento FGTS + Informações Previdenciárias
 * Aplica-se a vínculos RGPS (comissionados e contratados temporários).
 * Alíquotas: FGTS = 8% sobre remuneração bruta
 *            INSS patronal = 20% sobre remuneração (para o setor público RGPS)
 * Prazo: dia 7 do mês seguinte.
 */

// Preview dos trabalhadores RGPS e valores da competência
Route::get('/sefip/preview/{competencia}', function (string $competencia) {
    try {
        $ano = (int) substr($competencia, 0, 4);
        $mes = (int) substr($competencia, 4, 2);

        // Buscar trabalhadores RGPS ativos na competência
        $trabalhadores = DB::table('FUNCIONARIO as f')
            ->join('PESSOA as p', 'p.PESSOA_ID', '=', 'f.PESSOA_ID')
            ->leftJoin('LOTACAO as l', function ($j) {
                $j->on('l.FUNCIONARIO_ID', '=', 'f.FUNCIONARIO_ID')
                  ->whereNull('l.LOTACAO_DATA_FIM');
            })
            ->leftJoin('CARGO as c', 'c.CARGO_ID', '=', 'l.CARGO_ID')
            ->where('f.FUNCIONARIO_REGIME_PREV', 'RGPS')
            ->whereNull('f.FUNCIONARIO_DATA_FIM')
            ->select(
                'p.PESSOA_NOME as nome',
                'p.PESSOA_CPF_NUMERO as cpf',
                'p.PESSOA_DATA_NASCIMENTO as nascimento',
                'f.FUNCIONARIO_DATA_INICIO as admissao',
                'c.CARGO_NOME as cargo',
                DB::raw('COALESCE(c.CARGO_SALARIO, 0) as remuneracao'),
                DB::raw('ROUND(COALESCE(c.CARGO_SALARIO, 0) * 0.08, 2) as fgts'),
                DB::raw('ROUND(COALESCE(c.CARGO_SALARIO, 0) * 0.20, 2) as inss_patronal')
            )
            ->get();

        return response()->json([
            'competencia'        => $competencia,
            'trabalhadores'      => $trabalhadores,
            'total_trabalhadores'=> $trabalhadores->count(),
            'total_remuneracao'  => $trabalhadores->sum('remuneracao'),
            'total_fgts'         => $trabalhadores->sum('fgts'),
            'total_inss'         => $trabalhadores->sum('inss_patronal'),
        ]);
    } catch (\Throwable $e) {
        return response()->json(['erro' => $e->getMessage()], 500);
    }
});

// Gerar arquivo SEFIP
Route::post('/sefip/gerar/{competencia}', function (string $competencia) {
    try {
        $user  = Auth::user();
        $cnpj  = preg_replace('/\D/', '', env('ORGAO_CNPJ', '06223007000114'));
        $mes   = substr($competencia, 4, 2);
        $ano   = substr($competencia, 0, 4);

        $trabalhadores = DB::table('FUNCIONARIO as f')
            ->join('PESSOA as p', 'p.PESSOA_ID', '=', 'f.PESSOA_ID')
            ->leftJoin('LOTACAO as l', function ($j) {
                $j->on('l.FUNCIONARIO_ID', '=', 'f.FUNCIONARIO_ID')
                  ->whereNull('l.LOTACAO_DATA_FIM');
            })
            ->leftJoin('CARGO as c', 'c.CARGO_ID', '=', 'l.CARGO_ID')
            ->where('f.FUNCIONARIO_REGIME_PREV', 'RGPS')
            ->whereNull('f.FUNCIONARIO_DATA_FIM')
            ->select(
                'p.PESSOA_CPF_NUMERO as cpf',
                'p.PESSOA_NOME as nome',
                'p.PESSOA_DATA_NASCIMENTO as nascimento',
                'f.FUNCIONARIO_DATA_INICIO as admissao',
                DB::raw('COALESCE(c.CARGO_SALARIO, 0) as remuneracao')
            )->get();

        $linhas      = [];
        $totalFgts   = 0;
        $totalInss   = 0;

        // Registro tipo 1 — Empregador
        $linhas[] = '1'                                    // tipo registro
            . str_pad($cnpj, 14, '0', STR_PAD_LEFT)       // CNPJ
            . str_pad('', 15)                              // FPAS (Fundo Previdência)
            . $mes . $ano;                                 // competência MMAAAA

        // Registros tipo 2 — Trabalhadores
        foreach ($trabalhadores as $t) {
            $cpf         = str_pad(preg_replace('/\D/', '', $t->cpf ?? ''), 11, '0', STR_PAD_LEFT);
            $nome        = str_pad(mb_substr(mb_strtoupper($t->nome ?? ''), 0, 40), 40);
            $remuneracao = (float) $t->remuneracao;
            $fgts        = round($remuneracao * 0.08, 2);
            $inss        = round($remuneracao * 0.20, 2);
            $remStr      = str_pad(str_replace('.', '', number_format($remuneracao, 2, '.', '')), 12, '0', STR_PAD_LEFT);
            $fgtsStr     = str_pad(str_replace('.', '', number_format($fgts, 2, '.', '')), 10, '0', STR_PAD_LEFT);
            $inssStr     = str_pad(str_replace('.', '', number_format($inss, 2, '.', '')), 10, '0', STR_PAD_LEFT);

            $linhas[]  = '2' . $cpf . $nome . $remStr . $fgtsStr . $inssStr;
            $totalFgts += $fgts;
            $totalInss += $inss;
        }

        // Registro tipo 9 — Totalizador
        $totalFgtsStr = str_pad(str_replace('.', '', number_format($totalFgts, 2, '.', '')), 12, '0', STR_PAD_LEFT);
        $totalInssStr = str_pad(str_replace('.', '', number_format($totalInss, 2, '.', '')), 12, '0', STR_PAD_LEFT);
        $linhas[] = '9' . str_pad((string) count($trabalhadores), 6, '0', STR_PAD_LEFT) . $totalFgtsStr . $totalInssStr;

        $conteudo    = implode("\r\n", $linhas);
        $nomeArquivo = "SEFIP_{$competencia}_" . date('YmdHis') . ".txt";

        DB::table('SEFIP_ENVIO')->updateOrInsert(
            ['SEFIP_COMPETENCIA' => $competencia],
            [
                'SEFIP_STATUS'              => 'GERADO',
                'SEFIP_TOTAL_TRABALHADORES' => count($trabalhadores),
                'SEFIP_TOTAL_FGTS'          => round($totalFgts, 2),
                'SEFIP_TOTAL_INSS'          => round($totalInss, 2),
                'SEFIP_ARQUIVO_NOME'        => $nomeArquivo,
                'SEFIP_CONTEUDO'            => $conteudo,
                'GERADO_POR'                => $user->USUARIO_ID ?? null,
                'updated_at'                => now(),
                'created_at'                => now(),
            ]
        );

        return response($conteudo, 200, [
            'Content-Type'        => 'text/plain; charset=UTF-8',
            'Content-Disposition' => "attachment; filename=\"{$nomeArquivo}\"",
            'X-Total-FGTS'        => round($totalFgts, 2),
            'X-Total-INSS'        => round($totalInss, 2),
        ]);
    } catch (\Throwable $e) {
        return response()->json(['erro' => $e->getMessage()], 500);
    }
});

// Histórico de envios
Route::get('/sefip/historico', function () {
    try {
        $historico = DB::table('SEFIP_ENVIO')
            ->orderByDesc('SEFIP_COMPETENCIA')
            ->limit(24)
            ->get();
        return response()->json(['historico' => $historico]);
    } catch (\Throwable $e) {
        return response()->json(['erro' => $e->getMessage()], 500);
    }
});
