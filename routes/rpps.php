<?php
// routes/rpps.php — RPPS/IPAM
// ⚠️ NÃO abrir Route::middleware()->prefix()->group() — herda contexto api/v3 + auth do web.php (§2 das regras)
use Illuminate\Support\Facades\Route;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;

// Dashboard RPPS
Route::get('/rpps/dashboard', function (Request $req) {
    $comp = $req->query('competencia', date('Y-m'));
    try {
        // PERF-03 — Alíquotas dinâmicas via RPPS_CONFIG (não hardcoded)
        $config = null;
        try {
            $config = DB::table('RPPS_CONFIG')
                ->where('VIGENCIA_INICIO', '<=', $comp)
                ->where(function ($q) use ($comp) {
                    $q->whereNull('VIGENCIA_FIM')->orWhere('VIGENCIA_FIM', '>=', $comp);
                })
                ->orderByDesc('VIGENCIA_INICIO')
                ->first();
        } catch (\Throwable $ex) { /* tabela ainda não existe */
        }

        $aliqServidor = $config->ALIQUOTA_SERVIDOR ?? 14.0;
        $aliqPatronal = $config->ALIQUOTA_PATRONAL ?? 28.0;

        $totais = DB::table('RPPS_CONTRIBUICAO')
            ->where('COMPETENCIA', $comp)
            ->selectRaw('SUM(VALOR_SERVIDOR) as total_servidor, SUM(VALOR_PATRONAL) as total_patronal, COUNT(*) as qtd')
            ->first();

        $historico = DB::table('RPPS_CONTRIBUICAO')
            ->select('COMPETENCIA', DB::raw('SUM(VALOR_SERVIDOR) as servidor'), DB::raw('SUM(VALOR_PATRONAL) as patronal'))
            ->groupBy('COMPETENCIA')
            ->orderByDesc('COMPETENCIA')
            ->limit(12)
            ->get();

        return response()->json([
            'competencia' => $comp,
            'total_servidor' => $totais->total_servidor ?? 0,
            'total_patronal' => $totais->total_patronal ?? 0,
            'total_geral' => ($totais->total_servidor ?? 0) + ($totais->total_patronal ?? 0),
            'qtd_servidores' => $totais->qtd ?? 0,
            'historico' => $historico,
            'aliquota_servidor' => $aliqServidor,
            'aliquota_patronal' => $aliqPatronal,
        ]);
    } catch (\Throwable $e) {
        return response()->json(['fallback' => true, 'erro' => $e->getMessage()], 200);
    }
});

// Listagem de beneficiários
Route::get('/rpps/beneficiarios', function (Request $req) {
    try {
        $q = DB::table('RPPS_BENEFICIARIO as rb')
            ->join('FUNCIONARIO as f', 'f.FUNCIONARIO_ID', '=', 'rb.FUNCIONARIO_ID')
            ->join('PESSOA as p', 'p.PESSOA_ID', '=', 'f.PESSOA_ID')
            ->select('rb.*', 'p.PESSOA_NOME as nome', 'f.FUNCIONARIO_MATRICULA as matricula');
        if ($req->tipo)
            $q->where('rb.TIPO', $req->tipo);
        return response()->json(['beneficiarios' => $q->paginate(50)]);
    } catch (\Throwable $e) {
        return response()->json(['fallback' => true]);
    }
});

// Calcular contribuições da competência
Route::post('/rpps/calcular', function (Request $req) {
    $req->validate(['competencia' => 'required|string']);
    $comp = $req->competencia;
    try {
        $servidores = DB::table('DETALHE_FOLHA as df')
            ->join('FOLHA as fl', 'fl.FOLHA_ID', '=', 'df.FOLHA_ID')
            ->where('fl.FOLHA_COMPETENCIA', str_replace('-', '', $comp))
            ->select('df.FUNCIONARIO_ID', DB::raw('SUM(COALESCE(df.DETALHE_BASE_PREV, df.DETALHE_FOLHA_PROVENTOS, 0)) as base')) // BUG-RPPS-01: usar base previdenciária real
            ->groupBy('df.FUNCIONARIO_ID')
            ->get();

        // PERF-03 — Aliqóuotas dinâmicas via RPPS_CONFIG (não hardcoded)
        $config = null;
        try {
            $config = DB::table('RPPS_CONFIG')
                ->where('VIGENCIA_INICIO', '<=', $comp)
                ->where(function ($q) use ($comp) {
                    $q->whereNull('VIGENCIA_FIM')->orWhere('VIGENCIA_FIM', '>=', $comp);
                })
                ->orderByDesc('VIGENCIA_INICIO')
                ->first();
        } catch (\Throwable $ex) { /* tabela ainda não existe */
        }

        $aliqServidor = ($config->ALIQUOTA_SERVIDOR ?? 14.0) / 100;
        $aliqPatronal = ($config->ALIQUOTA_PATRONAL ?? 28.0) / 100;

        $inseridos = 0;
        foreach ($servidores as $s) {
            $base = floatval($s->base);
            $srv = round($base * $aliqServidor, 2);
            $patronal = round($base * $aliqPatronal, 2);
            DB::table('RPPS_CONTRIBUICAO')->updateOrInsert(
                ['FUNCIONARIO_ID' => $s->FUNCIONARIO_ID, 'COMPETENCIA' => $comp],
                ['BASE_CALCULO' => $base, 'VALOR_SERVIDOR' => $srv, 'VALOR_PATRONAL' => $patronal, 'STATUS' => 'CALCULADO', 'updated_at' => now()]
            );
            $inseridos++;
        }
        return response()->json(['mensagem' => "RPPS calculado para {$inseridos} servidores em {$comp}.", 'qtd' => $inseridos]);
    } catch (\Throwable $e) {
        return response()->json(['erro' => $e->getMessage()], 500);
    }
});

// Exportar CADPREV (simulado)
Route::post('/rpps/exportar-cadprev', function (Request $req) {
    $req->validate(['competencia' => 'required|string']);
    $comp = $req->competencia;
    try {
        DB::table('RPPS_EXPORTACAO')->insert(['TIPO' => 'CADPREV', 'COMPETENCIA' => $comp, 'STATUS' => 'GERADO', 'created_at' => now(), 'updated_at' => now()]);
        return response()->json(['mensagem' => "Exportação CADPREV para {$comp} registrada."]);
    } catch (\Throwable $e) {
        return response()->json(['erro' => $e->getMessage()], 500);
    }
});

