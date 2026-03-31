<?php

use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Auth;

/**
 * RAIS — Relação Anual de Informações Sociais
 * Prazo: último dia útil de março do ano seguinte (prorrogável).
 * Vínculos setor público:
 *   30 = Estatutário (RPPS)
 *   35 = Cargo em comissão / temporário (RGPS)
 */

// Preview dos vínculos do ano
Route::get('/rais/preview/{ano}', function (int $ano) {
    try {
        $vinculos = DB::table('FUNCIONARIO as f')
            ->join('PESSOA as p', 'p.PESSOA_ID', '=', 'f.PESSOA_ID')
            ->leftJoin('LOTACAO as l', function ($j) {
                $j->on('l.FUNCIONARIO_ID', '=', 'f.FUNCIONARIO_ID')
                  ->whereNull('l.LOTACAO_DATA_FIM');
            })
            ->leftJoin('CARGO as c', 'c.CARGO_ID', '=', 'l.CARGO_ID')
            ->where(function ($q) use ($ano) {
                // Ativos no ano ou demitidos no ano
                $q->whereYear('f.FUNCIONARIO_DATA_INICIO', '<=', $ano)
                  ->where(function ($q2) use ($ano) {
                      $q2->whereNull('f.FUNCIONARIO_DATA_FIM')
                         ->orWhereYear('f.FUNCIONARIO_DATA_FIM', '>=', $ano);
                  });
            })
            ->select(
                'p.PESSOA_NOME as nome',
                'p.PESSOA_CPF_NUMERO as cpf',
                'p.PESSOA_DATA_NASCIMENTO as nascimento',
                'f.FUNCIONARIO_DATA_INICIO as admissao',
                'f.FUNCIONARIO_DATA_FIM as demissao',
                'f.FUNCIONARIO_REGIME_PREV as regime',
                'c.CARGO_NOME as cargo',
                DB::raw('COALESCE(c.CARGO_SALARIO, 0) as salario'),
                DB::raw("CASE WHEN f.FUNCIONARIO_REGIME_PREV = 'RGPS' THEN '35' ELSE '30' END as codigo_vinculo")
            )
            ->get();

        return response()->json([
            'ano'             => $ano,
            'vinculos'        => $vinculos,
            'total_vinculos'  => $vinculos->count(),
            'estatutarios'    => $vinculos->where('codigo_vinculo', '30')->count(),
            'comissionados'   => $vinculos->where('codigo_vinculo', '35')->count(),
        ]);
    } catch (\Throwable $e) {
        return response()->json(['erro' => $e->getMessage()], 500);
    }
});

// Gerar arquivo RAIS posicional
Route::post('/rais/gerar/{ano}', function (int $ano) {
    try {
        $user  = Auth::user();
        $cnpj  = preg_replace('/\D/', '', env('ORGAO_CNPJ', '06223007000114'));
        $razao = str_pad(mb_strtoupper(env('ORGAO_NOME', 'PREFEITURA MUNICIPAL'), 'UTF-8'), 60);

        $vinculos = DB::table('FUNCIONARIO as f')
            ->join('PESSOA as p', 'p.PESSOA_ID', '=', 'f.PESSOA_ID')
            ->leftJoin('LOTACAO as l', function ($j) {
                $j->on('l.FUNCIONARIO_ID', '=', 'f.FUNCIONARIO_ID')
                  ->whereNull('l.LOTACAO_DATA_FIM');
            })
            ->leftJoin('CARGO as c', 'c.CARGO_ID', '=', 'l.CARGO_ID')
            ->where(function ($q) use ($ano) {
                $q->whereYear('f.FUNCIONARIO_DATA_INICIO', '<=', $ano)
                  ->where(function ($q2) use ($ano) {
                      $q2->whereNull('f.FUNCIONARIO_DATA_FIM')
                         ->orWhereYear('f.FUNCIONARIO_DATA_FIM', '>=', $ano);
                  });
            })
            ->select(
                'p.PESSOA_CPF_NUMERO as cpf',
                'p.PESSOA_NOME as nome',
                'p.PESSOA_DATA_NASCIMENTO as nascimento',
                'f.FUNCIONARIO_DATA_INICIO as admissao',
                'f.FUNCIONARIO_DATA_FIM as demissao',
                'f.FUNCIONARIO_REGIME_PREV as regime',
                DB::raw('COALESCE(c.CARGO_SALARIO, 0) as salario'),
                DB::raw("CASE WHEN f.FUNCIONARIO_REGIME_PREV = 'RGPS' THEN '35' ELSE '30' END as codigo_vinculo")
            )->get();

        $linhas = [];

        // Registro tipo 10 — Estabelecimento
        $linhas[] = '10'
            . str_pad($cnpj, 14, '0', STR_PAD_LEFT)
            . $razao
            . str_pad($ano, 4);

        foreach ($vinculos as $v) {
            $cpf          = str_pad(preg_replace('/\D/', '', $v->cpf ?? ''), 11, '0', STR_PAD_LEFT);
            $nome         = str_pad(mb_substr(mb_strtoupper($v->nome ?? '', 'UTF-8'), 0, 70), 70);
            $nascimento   = $v->nascimento ? str_replace('-', '', $v->nascimento) : str_repeat('0', 8);
            $admissao     = $v->admissao   ? str_replace('-', '', substr($v->admissao, 0, 10))   : str_repeat('0', 8);
            $demissao     = $v->demissao   ? str_replace('-', '', substr($v->demissao, 0, 10))   : str_repeat('0', 8);
            $salario      = str_pad(str_replace('.', '', number_format((float)$v->salario, 2, '.', '')), 12, '0', STR_PAD_LEFT);
            $codVinculo   = str_pad($v->codigo_vinculo, 2, '0', STR_PAD_LEFT);

            // Registro tipo 20 — Trabalhador
            $linhas[] = '20'
                . $cpf
                . $nome
                . $nascimento
                . $admissao
                . $demissao
                . $codVinculo
                . $salario;
        }

        // Registro tipo 99 — Encerramento
        $linhas[] = '99' . str_pad((string) count($vinculos), 8, '0', STR_PAD_LEFT);

        $conteudo    = "\xEF\xBB\xBF" . implode("\r\n", $linhas); // BOM UTF-8
        $nomeArquivo = "RAIS_{$ano}_" . date('YmdHis') . ".txt";

        DB::table('RAIS_ENVIO')->updateOrInsert(
            ['RAIS_ANO' => $ano],
            [
                'RAIS_STATUS'         => 'GERADO',
                'RAIS_TOTAL_VINCULOS' => count($vinculos),
                'RAIS_ARQUIVO_NOME'   => $nomeArquivo,
                'RAIS_CONTEUDO'       => $conteudo,
                'GERADO_POR'          => $user->USUARIO_ID ?? null,
                'updated_at'          => now(),
                'created_at'          => now(),
            ]
        );

        return response($conteudo, 200, [
            'Content-Type'        => 'text/plain; charset=UTF-8',
            'Content-Disposition' => "attachment; filename=\"{$nomeArquivo}\"",
            'X-Total-Vinculos'    => count($vinculos),
        ]);
    } catch (\Throwable $e) {
        return response()->json(['erro' => $e->getMessage()], 500);
    }
});

// Histórico
Route::get('/rais/historico', function () {
    try {
        return response()->json([
            'historico' => DB::table('RAIS_ENVIO')->orderByDesc('RAIS_ANO')->limit(10)->get()
        ]);
    } catch (\Throwable $e) {
        return response()->json(['erro' => $e->getMessage()], 500);
    }
});
