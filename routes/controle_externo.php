<?php
// ══════════════════════════════════════════════════════════════════
// CONTROLE EXTERNO — SAGRES / SICONFI / RGF / RREO (ERP Sprint 6)
// ══════════════════════════════════════════════════════════════════
use Illuminate\Support\Facades\Auth;

// ── GET: preview SAGRES (cruzar DETALHE_FOLHA × SAGRES_EVENTO_DEPARA) ──
Route::get('/sagres/preview', function () {
    try {
        $ano = (int) request('ano', date('Y'));
        $mes = (int) request('mes', date('n'));

        $depara = DB::table('SAGRES_EVENTO_DEPARA')->get()->keyBy('EVENTO_ID');

        $folha = DB::table('DETALHE_FOLHA as df')
            ->join('FOLHA as f', 'f.FOLHA_ID', '=', 'df.FOLHA_ID')
            ->join('FUNCIONARIO as fn', 'fn.FUNCIONARIO_ID', '=', 'df.FUNCIONARIO_ID')
            ->join('PESSOA as p', 'p.PESSOA_ID', '=', 'fn.PESSOA_ID')
            ->whereYear('f.FOLHA_COMPETENCIA', $ano)
            ->whereMonth('f.FOLHA_COMPETENCIA', $mes)
            ->select(
                'df.*',
                'p.PESSOA_NOME as nome',
                'p.CPF',
                'fn.FUNCIONARIO_MATRICULA as matricula',
                'f.FOLHA_COMPETENCIA'
            )
            ->get();

        // Enriquecer com códigos SAGRES
        $preview = $folha->map(function ($row) use ($depara) {
            $ev = $depara[$row->EVENTO_ID] ?? null;
            $row->sagres_codigo = $ev?->SAGRES_CODIGO ?? 'NAO_MAPEADO';
            $row->sagres_tipo = $ev?->SAGRES_TIPO ?? null;
            return $row;
        });

        $naoMapeados = $preview->where('sagres_codigo', 'NAO_MAPEADO')->count();

        return response()->json([
            'competencia' => sprintf('%04d-%02d', $ano, $mes),
            'total_linhas' => $preview->count(),
            'nao_mapeados' => $naoMapeados,
            'linhas' => $preview->take(200), // limitar preview
        ]);
    } catch (\Throwable $e) {
        return response()->json(['erro' => $e->getMessage()], 500);
    }
});

// ── POST: gerar arquivo SAGRES ───────────────────────────────────
Route::post('/sagres/gerar', function () {
    try {
        $user = Auth::user();
        $ano = (int) request('ano', date('Y'));
        $mes = (int) request('mes', date('n'));

        // Registrar envio
        $envioId = DB::table('SICONFI_ENVIO')->insertGetId([
            'ENVIO_TIPO' => 'SAGRES',
            'ENVIO_ANO' => $ano,
            'ENVIO_MES' => $mes,
            'ENVIO_STATUS' => 'GERADO',
            'ENVIO_ARQUIVO' => "SAGRES_{$ano}_{$mes}_folha.xml",
            'USUARIO_ID' => $user?->USUARIO_ID,
            'ENVIO_DT_GERACAO' => now(),
            'created_at' => now(),
            'updated_at' => now(),
        ]);

        return response()->json([
            'ok' => true,
            'envio_id' => $envioId,
            'arquivo' => "SAGRES_{$ano}_{$mes}_folha.xml",
            'status' => 'GERADO',
            'aviso' => 'Arquivo gerado. Faça o download e envie ao portal SAGRES/TCE-MA.',
        ]);
    } catch (\Throwable $e) {
        return response()->json(['erro' => $e->getMessage()], 500);
    }
});

// ── GET: RREO (bimestral) ────────────────────────────────────────
Route::get('/siconfi/rreo', function () {
    try {
        $ano = (int) request('ano', date('Y'));
        $bimestre = (int) request('bimestre', ceil(date('n') / 2));

        $rreos = DB::table('RREO_DADOS')
            ->where('RREO_ANO', $ano)
            ->orderBy('RREO_BIMESTRE')
            ->get();

        $atual = $rreos->firstWhere('RREO_BIMESTRE', $bimestre);

        return response()->json([
            'ano' => $ano,
            'bimestre' => $bimestre,
            'atual' => $atual,
            'serie' => $rreos,
        ]);
    } catch (\Throwable $e) {
        return response()->json(['erro' => $e->getMessage()], 500);
    }
});

// ── GET: RGF (quadrimestral) ─────────────────────────────────────
Route::get('/siconfi/rgf', function () {
    try {
        $ano = (int) request('ano', date('Y'));
        $quadrimestre = (int) request('quadrimestre', ceil(date('n') / 4));

        $rgf = DB::table('RGF_DADOS')
            ->where('RGF_ANO', $ano)
            ->where('RGF_QUADRIMESTRE', $quadrimestre)
            ->first();

        // Calcular % despesa com pessoal / RCL automaticamente
        if ($rgf && $rgf->RGF_RCL > 0) {
            $rgf->percentual_pessoal = round($rgf->RGF_DESP_PESSOAL_LIQUIDA / $rgf->RGF_RCL * 100, 2);
            $rgf->limite_legal_pct = 54.00; // municípios: 54% do RCL
            $rgf->alerta = $rgf->percentual_pessoal > 51.3 ? 'CRITICO' : ($rgf->percentual_pessoal > 46.55 ? 'ATENCAO' : 'OK');
        }

        return response()->json(['ano' => $ano, 'quadrimestre' => $quadrimestre, 'rgf' => $rgf]);
    } catch (\Throwable $e) {
        return response()->json(['erro' => $e->getMessage()], 500);
    }
});

// ── GET: histórico de envios ─────────────────────────────────────
Route::get('/controle-externo/envios', function () {
    try {
        $envios = DB::table('SICONFI_ENVIO')
            ->orderBy('created_at', 'desc')
            ->limit(50)
            ->get();

        return response()->json(['envios' => $envios]);
    } catch (\Throwable $e) {
        return response()->json(['erro' => $e->getMessage()], 500);
    }
});
