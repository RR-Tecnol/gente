<?php

use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Auth;

/**
 * CAGED — Cadastro Geral de Empregados e Desempregados
 * Aplica-se a vínculos RGPS (comissionados e contratados temporários).
 * Layout posicional MTE — registro tipo 1 (movimentação).
 *
 * Prazo: dia 7 do mês seguinte à competência.
 */

// Preview dos movimentos da competência
Route::get('/caged/preview/{competencia}', function (string $competencia) {
    try {
        $ano = (int) substr($competencia, 0, 4);
        $mes = (int) substr($competencia, 4, 2);
        $ini = sprintf('%04d-%02d-01', $ano, $mes);
        $fim = date('Y-m-t', strtotime($ini));

        // Admissões RGPS no período
        $admissoes = DB::table('FUNCIONARIO as f')
            ->join('PESSOA as p', 'p.PESSOA_ID', '=', 'f.PESSOA_ID')
            ->leftJoin('LOTACAO as l', function ($j) {
                $j->on('l.FUNCIONARIO_ID', '=', 'f.FUNCIONARIO_ID')
                  ->whereNull('l.LOTACAO_DATA_FIM');
            })
            ->leftJoin('CARGO as c', 'c.CARGO_ID', '=', 'l.CARGO_ID')
            ->where('f.FUNCIONARIO_REGIME_PREV', 'RGPS')
            ->whereBetween('f.FUNCIONARIO_DATA_INICIO', [$ini, $fim])
            ->select(
                'p.PESSOA_NOME as nome',
                'p.PESSOA_CPF_NUMERO as cpf',
                'f.FUNCIONARIO_DATA_INICIO as data_movimento',
                'c.CARGO_NOME as cargo',
                'c.CARGO_SALARIO as salario',
                DB::raw("'ADMISSAO' as tipo_movimento")
            )->get();

        // Demissões RGPS no período
        $demissoes = DB::table('FUNCIONARIO as f')
            ->join('PESSOA as p', 'p.PESSOA_ID', '=', 'f.PESSOA_ID')
            ->leftJoin('LOTACAO as l', function ($j) {
                $j->on('l.FUNCIONARIO_ID', '=', 'f.FUNCIONARIO_ID')
                  ->whereNull('l.LOTACAO_DATA_FIM');
            })
            ->leftJoin('CARGO as c', 'c.CARGO_ID', '=', 'l.CARGO_ID')
            ->where('f.FUNCIONARIO_REGIME_PREV', 'RGPS')
            ->whereBetween('f.FUNCIONARIO_DATA_FIM', [$ini, $fim])
            ->select(
                'p.PESSOA_NOME as nome',
                'p.PESSOA_CPF_NUMERO as cpf',
                'f.FUNCIONARIO_DATA_FIM as data_movimento',
                'c.CARGO_NOME as cargo',
                'c.CARGO_SALARIO as salario',
                DB::raw("'DEMISSAO' as tipo_movimento")
            )->get();

        return response()->json([
            'competencia'  => $competencia,
            'admissoes'    => $admissoes,
            'demissoes'    => $demissoes,
            'total_admissoes' => $admissoes->count(),
            'total_demissoes' => $demissoes->count(),
            'saldo'        => $admissoes->count() - $demissoes->count(),
        ]);
    } catch (\Throwable $e) {
        return response()->json(['erro' => $e->getMessage()], 500);
    }
});

// Gerar arquivo CAGED posicional
Route::post('/caged/gerar/{competencia}', function (string $competencia) {
    try {
        $user    = Auth::user();
        $cnpj    = preg_replace('/\D/', '', env('ORGAO_CNPJ', '06223007000114'));
        $ano     = substr($competencia, 0, 4);
        $mes     = substr($competencia, 4, 2);
        $ini     = "{$ano}-{$mes}-01";
        $fim     = date('Y-m-t', strtotime($ini));

        // Buscar movimentações RGPS
        $movimentos = DB::table('FUNCIONARIO as f')
            ->join('PESSOA as p', 'p.PESSOA_ID', '=', 'f.PESSOA_ID')
            ->leftJoin('LOTACAO as l', function ($j) {
                $j->on('l.FUNCIONARIO_ID', '=', 'f.FUNCIONARIO_ID')
                  ->whereNull('l.LOTACAO_DATA_FIM');
            })
            ->leftJoin('CARGO as c', 'c.CARGO_ID', '=', 'l.CARGO_ID')
            ->where('f.FUNCIONARIO_REGIME_PREV', 'RGPS')
            ->where(function ($q) use ($ini, $fim) {
                $q->whereBetween('f.FUNCIONARIO_DATA_INICIO', [$ini, $fim])
                  ->orWhereBetween('f.FUNCIONARIO_DATA_FIM', [$ini, $fim]);
            })
            ->select(
                'p.PESSOA_CPF_NUMERO as cpf',
                'p.PESSOA_NOME as nome',
                'p.PESSOA_DATA_NASCIMENTO as nascimento',
                'f.FUNCIONARIO_DATA_INICIO as admissao',
                'f.FUNCIONARIO_DATA_FIM as demissao',
                'c.CARGO_SALARIO as salario',
                DB::raw('COALESCE(c.CBO_CODIGO, "412110") as cbo') // CBO padrão: Auxiliar Administrativo
            )->get();

        $linhas = [];
        $admissoes = 0;
        $demissoes = 0;

        foreach ($movimentos as $mov) {
            $cpf = str_pad(preg_replace('/\D/', '', $mov->cpf ?? ''), 11, '0', STR_PAD_LEFT);
            $nome = str_pad(mb_substr(mb_strtoupper($mov->nome ?? ''), 0, 40), 40);
            $salario = str_pad(str_replace('.', '', number_format((float)($mov->salario ?? 0), 2, '.', '')), 10, '0', STR_PAD_LEFT);
            $cbo = str_pad($mov->cbo ?? '412110', 6);

            // Tipo de movimento: A=Admissão, D=Demissão
            if ($mov->admissao && $mov->admissao >= $ini && $mov->admissao <= $fim) {
                $tipoMov = 'A';
                $dataMov = str_replace('-', '', $mov->admissao);
                $admissoes++;
            } else {
                $tipoMov = 'D';
                $dataMov = str_replace('-', '', $mov->demissao ?? $fim);
                $demissoes++;
            }

            // Layout posicional simplificado CAGED (campos principais)
            $linha = $cnpj                          // 14 chars — CNPJ
                   . $cpf                           // 11 chars — CPF
                   . $nome                          // 40 chars — Nome
                   . $dataMov                       //  8 chars — Data movimento YYYYMMDD
                   . $tipoMov                       //  1 char  — A/D
                   . $salario                       // 10 chars — Salário sem separador, 2 decimais
                   . $cbo;                          //  6 chars — CBO

            $linhas[] = $linha;
        }

        $conteudo    = implode("\r\n", $linhas);
        $nomeArquivo = "CAGED_{$competencia}_" . date('YmdHis') . ".txt";

        // Persistir
        DB::table('CAGED_ENVIO')->updateOrInsert(
            ['CAGED_COMPETENCIA' => $competencia],
            [
                'CAGED_STATUS'       => 'GERADO',
                'CAGED_ADMISSOES'    => $admissoes,
                'CAGED_DEMISSOES'    => $demissoes,
                'CAGED_ARQUIVO_NOME' => $nomeArquivo,
                'CAGED_CONTEUDO'     => $conteudo,
                'GERADO_POR'         => $user->USUARIO_ID ?? null,
                'updated_at'         => now(),
                'created_at'         => now(),
            ]
        );

        return response($conteudo, 200, [
            'Content-Type'        => 'text/plain; charset=UTF-8',
            'Content-Disposition' => "attachment; filename=\"{$nomeArquivo}\"",
            'X-Admissoes'         => $admissoes,
            'X-Demissoes'         => $demissoes,
        ]);
    } catch (\Throwable $e) {
        return response()->json(['erro' => $e->getMessage()], 500);
    }
});

// Histórico de envios
Route::get('/caged/historico', function () {
    try {
        $historico = DB::table('CAGED_ENVIO')
            ->orderByDesc('CAGED_COMPETENCIA')
            ->limit(24)
            ->get();
        return response()->json(['historico' => $historico]);
    } catch (\Throwable $e) {
        return response()->json(['erro' => $e->getMessage()], 500);
    }
});
