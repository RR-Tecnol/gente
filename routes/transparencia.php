<?php
// ══════════════════════════════════════════════════════════════════
// TRANSPARÊNCIA PÚBLICA — Lei Complementar 131/2009 + Decreto 7.185/2010
// LAT-02 / GAP-10
// ⚠️ NÃO abrir Route::middleware()->prefix()->group() aqui
//    O contexto api/v3 + auth já é herdado do web.php
// ══════════════════════════════════════════════════════════════════

use Illuminate\Support\Facades\Storage;

// POST /transparencia/exportar — gera CSV/JSON da competência
Route::post('/transparencia/exportar', function (Request $request) {
    try {
        $comp = $request->competencia ?? now()->format('Y-m');
        // Normaliza: '2025-03' → '202503'
        $compDb = str_replace('-', '', $comp);

        $user = Auth::user();

        // Buscar folha da competência
        $folha = DB::table('FOLHA')->where('FOLHA_COMPETENCIA', $compDb)->first();
        if (!$folha) {
            // Exportar dados de funcionários mesmo sem folha fechada
        }

        $dados = DB::table('FUNCIONARIO as f')
            ->join('PESSOA as p', 'p.PESSOA_ID', '=', 'f.PESSOA_ID')
            ->leftJoin('CARGO as c', 'c.CARGO_ID', '=', 'f.CARGO_ID')
            ->leftJoin('LOTACAO as l', function ($j) {
                $j->on('l.FUNCIONARIO_ID', '=', 'f.FUNCIONARIO_ID')
                    ->whereNull('l.LOTACAO_DATA_FIM');
            })
            ->leftJoin('SETOR as s', 's.SETOR_ID', '=', 'l.SETOR_ID')
            ->leftJoin('UNIDADE as u', 'u.UNIDADE_ID', '=', 's.UNIDADE_ID')
            ->leftJoin('DETALHE_FOLHA as df', function ($j) use ($folha) {
                $j->on('df.FUNCIONARIO_ID', '=', 'f.FUNCIONARIO_ID');
                if ($folha) {
                    $j->where('df.FOLHA_ID', $folha->FOLHA_ID);
                }
            })
            ->whereNull('f.FUNCIONARIO_DATA_FIM')
            ->select(
                'p.PESSOA_NOME as nome',
                'f.FUNCIONARIO_MATRICULA as matricula',
                'p.PESSOA_CPF_NUMERO as cpf',
                'c.CARGO_NOME as cargo',
                'f.FUNCIONARIO_REGIME_PREV as regime',
                's.SETOR_NOME as setor',
                'u.UNIDADE_NOME as secretaria',
                'f.FUNCIONARIO_DATA_INICIO as admissao',
                DB::raw('COALESCE(df.DETALHE_FOLHA_PROVENTOS, 0) as proventos'),
                DB::raw('COALESCE(df.DETALHE_FOLHA_DESCONTOS, 0) as descontos'),
                DB::raw('COALESCE(df.DETALHE_FOLHA_LIQUIDO, COALESCE(df.DETALHE_FOLHA_PROVENTOS,0) - COALESCE(df.DETALHE_FOLHA_DESCONTOS,0), 0) as liquido')
            )
            ->orderBy('p.PESSOA_NOME')
            ->get();

        $formato = $request->formato ?? 'csv';

        // Gerar CSV com BOM UTF-8 (§14 regras-gerais)
        $cabecalho = ['Nome', 'Matrícula', 'CPF', 'Cargo', 'Regime', 'Setor', 'Secretaria', 'Admissão', 'Proventos', 'Descontos', 'Líquido'];
        $linhas = $dados->map(fn($r) => [
            $r->nome,
            $r->matricula,
            $r->cpf,
            $r->cargo,
            $r->regime,
            $r->setor,
            $r->secretaria,
            $r->admissao,
            number_format($r->proventos, 2, ',', '.'),
            number_format($r->descontos, 2, ',', '.'),
            number_format($r->liquido, 2, ',', '.'),
        ])->toArray();

        $csv = "\xEF\xBB\xBF" . implode(';', $cabecalho) . "\n";
        foreach ($linhas as $linha) {
            $csv .= implode(';', array_map(fn($v) => '"' . str_replace('"', '""', $v ?? '') . '"', $linha)) . "\n";
        }

        $filename = "transparencia_{$comp}.csv";

        // Registrar exportação
        $expId = DB::table('TRANSPARENCIA_EXPORTACAO')->insertGetId([
            'COMPETENCIA' => $comp,
            'FORMATO' => strtoupper($formato),
            'TOTAL_REGISTROS' => $dados->count(),
            'TOTAL_LIQUIDO' => $dados->sum('liquido'),
            'ARQUIVO_NOME' => $filename,
            'EXPORTADO_POR' => $user->USUARIO_ID ?? null,
            'created_at' => now(),
            'updated_at' => now(),
        ]);

        return response($csv, 200, [
            'Content-Type' => 'text/csv; charset=UTF-8',
            'Content-Disposition' => "attachment; filename=\"{$filename}\"",
        ]);
    } catch (\Throwable $e) {
        return response()->json(['erro' => $e->getMessage()], 500);
    }
});

// GET /transparencia/historico — lista exportações anteriores
Route::get('/transparencia/historico', function () {
    try {
        $historico = DB::table('TRANSPARENCIA_EXPORTACAO as t')
            ->leftJoin('USUARIO as u', 'u.USUARIO_ID', '=', 't.EXPORTADO_POR')
            ->select(
                't.EXPORTACAO_ID as id',
                't.COMPETENCIA as competencia',
                't.FORMATO as formato',
                't.TOTAL_REGISTROS as total_registros',
                't.TOTAL_LIQUIDO as total_liquido',
                't.ARQUIVO_NOME as arquivo',
                'u.USUARIO_NOME as exportado_por',
                't.created_at as exportado_em'
            )
            ->orderByDesc('t.created_at')
            ->limit(50)
            ->get();

        return response()->json(['historico' => $historico]);
    } catch (\Throwable $e) {
        // Tabela pode não existir ainda — retorna vazio
        return response()->json(['historico' => [], 'aviso' => 'Nenhuma exportação registrada ainda.']);
    }
});

// GET /transparencia/download/{id} — re-gerar download por id
Route::get('/transparencia/download/{id}', function ($id) {
    try {
        $exp = DB::table('TRANSPARENCIA_EXPORTACAO')->where('EXPORTACAO_ID', $id)->first();
        if (!$exp) {
            return response()->json(['erro' => 'Exportação não encontrada.'], 404);
        }

        // Re-gerar com os mesmos parâmetros
        $comp = $exp->COMPETENCIA;
        $compDb = str_replace('-', '', $comp);
        $folha = DB::table('FOLHA')->where('FOLHA_COMPETENCIA', $compDb)->first();

        $dados = DB::table('FUNCIONARIO as f')
            ->join('PESSOA as p', 'p.PESSOA_ID', '=', 'f.PESSOA_ID')
            ->leftJoin('CARGO as c', 'c.CARGO_ID', '=', 'f.CARGO_ID')
            ->leftJoin('LOTACAO as l', function ($j) {
                $j->on('l.FUNCIONARIO_ID', '=', 'f.FUNCIONARIO_ID')->whereNull('l.LOTACAO_DATA_FIM');
            })
            ->leftJoin('SETOR as s', 's.SETOR_ID', '=', 'l.SETOR_ID')
            ->leftJoin('UNIDADE as u', 'u.UNIDADE_ID', '=', 's.UNIDADE_ID')
            ->leftJoin('DETALHE_FOLHA as df', function ($j) use ($folha) {
                $j->on('df.FUNCIONARIO_ID', '=', 'f.FUNCIONARIO_ID');
                if ($folha)
                    $j->where('df.FOLHA_ID', $folha->FOLHA_ID);
            })
            ->whereNull('f.FUNCIONARIO_DATA_FIM')
            ->select(
                'p.PESSOA_NOME as nome',
                'f.FUNCIONARIO_MATRICULA as matricula',
                'p.PESSOA_CPF_NUMERO as cpf',
                'c.CARGO_NOME as cargo',
                'f.FUNCIONARIO_REGIME_PREV as regime',
                's.SETOR_NOME as setor',
                'u.UNIDADE_NOME as secretaria',
                'f.FUNCIONARIO_DATA_INICIO as admissao',
                DB::raw('COALESCE(df.DETALHE_FOLHA_PROVENTOS, 0) as proventos'),
                DB::raw('COALESCE(df.DETALHE_FOLHA_DESCONTOS, 0) as descontos'),
                DB::raw('COALESCE(df.DETALHE_FOLHA_LIQUIDO, COALESCE(df.DETALHE_FOLHA_PROVENTOS,0) - COALESCE(df.DETALHE_FOLHA_DESCONTOS,0), 0) as liquido')
            )
            ->orderBy('p.PESSOA_NOME')->get();

        $cab = ['Nome', 'Matrícula', 'CPF', 'Cargo', 'Regime', 'Setor', 'Secretaria', 'Admissão', 'Proventos', 'Descontos', 'Líquido'];
        $csv = "\xEF\xBB\xBF" . implode(';', $cab) . "\n";
        foreach ($dados as $r) {
            $csv .= implode(';', array_map(fn($v) => '"' . str_replace('"', '""', $v ?? '') . '"', [
                $r->nome,
                $r->matricula,
                $r->cpf,
                $r->cargo,
                $r->regime,
                $r->setor,
                $r->secretaria,
                $r->admissao,
                number_format($r->proventos, 2, ',', '.'),
                number_format($r->descontos, 2, ',', '.'),
                number_format($r->liquido, 2, ',', '.'),
            ])) . "\n";
        }

        return response($csv, 200, [
            'Content-Type' => 'text/csv; charset=UTF-8',
            'Content-Disposition' => "attachment; filename=\"transparencia_{$comp}.csv\"",
        ]);
    } catch (\Throwable $e) {
        return response()->json(['erro' => $e->getMessage()], 500);
    }
});
