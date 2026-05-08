<?php
// ══════════════════════════════════════════════════════════════════
// TRANSPARÊNCIA PÚBLICA — Lei Complementar 131/2009 + Decreto 7.185/2010
// LAT-02 / GAP-10
// ⚠️ NÃO abrir Route::middleware()->prefix()->group() aqui
//    O contexto api/v3 + auth já é herdado do web.php
// ══════════════════════════════════════════════════════════════════

use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Schema;
use Illuminate\Http\Request;

if (!function_exists('maskCpfTransparencia')) {
    function maskCpfTransparencia(?string $cpf): string
    {
        $dig = preg_replace('/\D+/', '', (string) ($cpf ?? ''));
        if (strlen($dig) < 11) {
            return '***.***.***-**';
        }
        return substr($dig, 0, 3) . '.***.***-' . substr($dig, -2);
    }
}

if (!function_exists('ffTransparenciaEnabled')) {
    function ffTransparenciaEnabled(string $feature): bool
    {
        return (bool) config('feature_flags.transparencia.' . $feature, false);
    }
}

// POST /transparencia/exportar — gera CSV/JSON da competência
Route::post('/transparencia/exportar', function (Request $request) {
    try {
        $path = storage_path('app/public/transparencia');
        if (!is_dir($path)) {
            mkdir($path, 0775, true);
        }

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
            maskCpfTransparencia($r->cpf),
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
                maskCpfTransparencia($r->cpf),
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

// GET /transparencia/dossie-terceirizacao — visão pública mínima (S5.4)
Route::get('/transparencia/dossie-terceirizacao', function (Request $request) {
    if (!ffTransparenciaEnabled('dossie_terceirizacao')) {
        return response()->json(['erro' => 'Recurso temporariamente indisponível por governança.'], 404);
    }

    try {
        $query = DB::table('TERCEIRO_POSTO as p')
            ->leftJoin('TERCEIRO_EMPRESA as e', 'e.EMPRESA_ID', '=', 'p.EMPRESA_ID')
            ->leftJoin('SETOR as s', 's.SETOR_ID', '=', 'p.SETOR_ID')
            ->leftJoin('UNIDADE as u', 'u.UNIDADE_ID', '=', 's.UNIDADE_ID')
            ->select(
                DB::raw('COALESCE(p.TRABALHADOR_NOME, p.PESSOA_NOME) as nome'),
                DB::raw('COALESCE(p.FUNCAO, p.CARGO, p.POSTO_DESCRICAO) as funcao'),
                'e.RAZAO_SOCIAL as razao_social',
                'e.EMPRESA_RAZAO_SOCIAL as empresa_razao_social',
                'e.CONTRATO_NUMERO as contrato_numero',
                'e.CONTRATO_ANO as contrato_ano',
                'u.UNIDADE_NOME as secretaria',
                's.SETOR_NOME as setor',
                DB::raw('COALESCE(p.TRABALHADOR_CPF, p.PESSOA_CPF_NUMERO) as cpf')
            );

        if (Schema::hasColumn('TERCEIRO_POSTO', 'ATIVO')) {
            $query->where('p.ATIVO', 1);
        }

        $itens = $query->orderBy('nome')->limit(5000)->get()->map(function ($r) {
            return [
                'nome' => $r->nome,
                'funcao' => $r->funcao,
                'empresa' => $r->razao_social ?? $r->empresa_razao_social,
                'contrato' => trim((string) (($r->contrato_numero ?? '') . (empty($r->contrato_ano) ? '' : '/' . $r->contrato_ano))),
                'secretaria' => $r->secretaria,
                'setor' => $r->setor,
                'cpf_mascarado' => maskCpfTransparencia($r->cpf ?? null),
            ];
        });

        return response()->json([
            'fonte' => 'dossie_terceirizacao',
            'total' => $itens->count(),
            'itens' => $itens,
            'campos_publicos' => ['nome', 'funcao', 'empresa', 'contrato', 'secretaria', 'setor', 'cpf_mascarado'],
        ]);
    } catch (\Throwable $e) {
        return response()->json(['erro' => $e->getMessage()], 500);
    }
})->middleware('tenant.resolve');

// GET /transparencia/observabilidade-integracoes — S8 fase 1 (indicadores públicos)
Route::get('/transparencia/observabilidade-integracoes', function () {
    if (!ffTransparenciaEnabled('observabilidade_integracoes')) {
        return response()->json(['erro' => 'Recurso temporariamente indisponível por governança.'], 404);
    }

    try {
        $payload = [
            'gerado_em' => now()->toIso8601String(),
            'metricas' => [],
        ];

        if (Schema::hasTable('TRANSPARENCIA_EXPORTACAO')) {
            $payload['metricas']['transparencia_exportacoes_7d'] = (int) DB::table('TRANSPARENCIA_EXPORTACAO')
                ->where('created_at', '>=', now()->subDays(7))
                ->count();
        }

        if (Schema::hasTable('ESOCIAL_EVENTO')) {
            $payload['metricas']['esocial_pendente_envio'] = (int) DB::table('ESOCIAL_EVENTO')
                ->whereIn('STATUS', ['PENDENTE_ENVIO', 'GERADO'])
                ->count();
            $payload['metricas']['esocial_rejeitado_7d'] = (int) DB::table('ESOCIAL_EVENTO')
                ->where('STATUS', 'REJEITADO')
                ->where('updated_at', '>=', now()->subDays(7))
                ->count();
        }

        if (Schema::hasTable('RPPS_BLOQUEIO_EVENTO')) {
            $payload['metricas']['rpps_bloqueios_30d'] = (int) DB::table('RPPS_BLOQUEIO_EVENTO')
                ->where('EVENTO', 'bloqueado')
                ->where('created_at', '>=', now()->subDays(30))
                ->count();
            $payload['metricas']['rpps_desbloqueios_30d'] = (int) DB::table('RPPS_BLOQUEIO_EVENTO')
                ->where('EVENTO', 'desbloqueado')
                ->where('created_at', '>=', now()->subDays(30))
                ->count();
        }

        return response()->json($payload);
    } catch (\Throwable $e) {
        return response()->json(['erro' => $e->getMessage()], 500);
    }
})->middleware('tenant.resolve');

// GET /transparencia/catalogo-dados — S8 fase 2 (catálogo público versionado)
Route::get('/transparencia/catalogo-dados', function () {
    if (!ffTransparenciaEnabled('catalogo_dados')) {
        return response()->json(['erro' => 'Recurso temporariamente indisponível por governança.'], 404);
    }

    try {
        $catalogo = config('transparencia_catalogo', []);
        return response()->json([
            'versao' => $catalogo['versao'] ?? null,
            'fontes' => $catalogo['fontes'] ?? [],
            'gerado_em' => now()->toIso8601String(),
        ]);
    } catch (\Throwable $e) {
        return response()->json(['erro' => $e->getMessage()], 500);
    }
})->middleware('tenant.resolve');
