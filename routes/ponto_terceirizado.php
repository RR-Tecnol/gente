<?php

use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Auth;

// ── Listar postos de uma empresa ──────────────────────────────────────────────
Route::get('/terceiros/postos/{empresa_id}', function (int $empresaId) {
    try {
        $postos = DB::table('TERCEIRO_POSTO')
            ->where('EMPRESA_ID', $empresaId)
            ->get();
        return response()->json(['postos' => $postos]);
    } catch (\Throwable $e) {
        return response()->json(['erro' => $e->getMessage()], 500);
    }
});

// ── Registrar frequência de um posto em uma data ──────────────────────────────
Route::post('/terceiros/frequencia', function () {
    try {
        $data = request()->validate([
            'posto_id'        => 'required|integer',
            'freq_data'       => 'required|date',
            'freq_status'     => 'required|in:PRESENTE,AUSENTE,FALTA_JUSTIFICADA,AFASTADO,FOLGA',
            'freq_entrada'    => 'nullable|date_format:H:i',
            'freq_saida'      => 'nullable|date_format:H:i',
            'freq_observacao' => 'nullable|string|max:300',
        ]);

        $posto = DB::table('TERCEIRO_POSTO')->where('POSTO_ID', $data['posto_id'])->first();
        if (!$posto) return response()->json(['erro' => 'Posto não encontrado.'], 404);

        // Calcular horas trabalhadas
        $horas = 0;
        if (!empty($data['freq_entrada']) && !empty($data['freq_saida'])) {
            $entrada = strtotime($data['freq_entrada']);
            $saida   = strtotime($data['freq_saida']);
            $horas   = $saida > $entrada ? round(($saida - $entrada) / 3600, 2) : 0;
        }

        $competencia = date('Ym', strtotime($data['freq_data']));

        DB::table('TERCEIRO_FREQUENCIA')->updateOrInsert(
            ['POSTO_ID' => $data['posto_id'], 'FREQ_DATA' => $data['freq_data']],
            [
                'EMPRESA_ID'       => $posto->EMPRESA_ID,
                'TRABALHADOR_CPF'  => $posto->TRABALHADOR_CPF,
                'TRABALHADOR_NOME' => $posto->TRABALHADOR_NOME,
                'FREQ_COMPETENCIA' => $competencia,
                'FREQ_STATUS'      => $data['freq_status'],
                'FREQ_ENTRADA'     => $data['freq_entrada'] ?? null,
                'FREQ_SAIDA'       => $data['freq_saida'] ?? null,
                'FREQ_HORAS'       => $horas,
                'FREQ_OBSERVACAO'  => $data['freq_observacao'] ?? null,
                'REGISTRADO_POR'   => Auth::id(),
                'updated_at'       => now(),
                'created_at'       => now(),
            ]
        );

        return response()->json(['ok' => true, 'horas' => $horas]);
    } catch (\Throwable $e) {
        return response()->json(['erro' => $e->getMessage()], 422);
    }
});

// ── Listar frequências por empresa e competência ──────────────────────────────
Route::get('/terceiros/frequencia/{empresa_id}/{competencia}', function (int $empresaId, string $competencia) {
    try {
        $frequencias = DB::table('TERCEIRO_FREQUENCIA as f')
            ->join('TERCEIRO_POSTO as p', 'p.POSTO_ID', '=', 'f.POSTO_ID')
            ->where('f.EMPRESA_ID', $empresaId)
            ->where('f.FREQ_COMPETENCIA', $competencia)
            ->select('f.*', 'p.FUNCAO', 'p.LOCALIDADE', 'p.TURNO')
            ->orderBy('f.FREQ_DATA')
            ->orderBy('p.POSTO_ID')
            ->get();

        $resumo = [
            'total'     => $frequencias->count(),
            'presentes' => $frequencias->where('FREQ_STATUS', 'PRESENTE')->count(),
            'ausentes'  => $frequencias->where('FREQ_STATUS', 'AUSENTE')->count(),
            'faltas'    => $frequencias->whereIn('FREQ_STATUS', ['AUSENTE', 'FALTA_JUSTIFICADA'])->count(),
            'horas'     => $frequencias->sum('FREQ_HORAS'),
        ];

        return response()->json(['frequencias' => $frequencias, 'resumo' => $resumo]);
    } catch (\Throwable $e) {
        return response()->json(['erro' => $e->getMessage()], 500);
    }
});

// ── Fechar apuração mensal — calcula glosas e valor a pagar ──────────────────
Route::post('/terceiros/apuracao/fechar', function () {
    try {
        $data = request()->validate([
            'empresa_id'  => 'required|integer',
            'competencia' => 'required|string|size:6',
        ]);

        $empresa = DB::table('TERCEIRO_EMPRESA')
            ->where('EMPRESA_ID', $data['empresa_id'])->first();
        if (!$empresa) return response()->json(['erro' => 'Empresa não encontrada.'], 404);

        $frequencias = DB::table('TERCEIRO_FREQUENCIA')
            ->where('EMPRESA_ID', $data['empresa_id'])
            ->where('FREQ_COMPETENCIA', $data['competencia'])
            ->get();

        $totalPostos  = DB::table('TERCEIRO_POSTO')
            ->where('EMPRESA_ID', $data['empresa_id'])->count();
        $presencas    = $frequencias->where('FREQ_STATUS', 'PRESENTE')->count();
        $faltas       = $frequencias->whereIn('FREQ_STATUS', ['AUSENTE'])->count();
        $diasUteis    = max(1, $frequencias->count());
        $pctPresenca  = round($presencas / $diasUteis * 100, 2);

        // Glosa proporcional: valor_mensal × (faltas / dias_úteis)
        $valorContrato = (float) $empresa->VALOR_MENSAL;
        $valorGlosa    = $diasUteis > 0
            ? round($valorContrato * ($faltas / $diasUteis), 2) : 0;
        $valorPagar    = max(0, round($valorContrato - $valorGlosa, 2));

        DB::table('TERCEIRO_APURACAO')->updateOrInsert(
            ['EMPRESA_ID' => $data['empresa_id'], 'APURACAO_COMPETENCIA' => $data['competencia']],
            [
                'TOTAL_POSTOS'    => $totalPostos,
                'TOTAL_PRESENCAS' => $presencas,
                'TOTAL_FALTAS'    => $faltas,
                'PCT_PRESENCA'    => $pctPresenca,
                'VALOR_CONTRATO'  => $valorContrato,
                'VALOR_GLOSA'     => $valorGlosa,
                'VALOR_PAGAR'     => $valorPagar,
                'APURACAO_STATUS' => 'FECHADA',
                'FECHADO_POR'     => Auth::id(),
                'updated_at'      => now(),
                'created_at'      => now(),
            ]
        );

        return response()->json([
            'ok'           => true,
            'pct_presenca' => $pctPresenca,
            'valor_contrato'=> $valorContrato,
            'valor_glosa'  => $valorGlosa,
            'valor_pagar'  => $valorPagar,
        ]);
    } catch (\Throwable $e) {
        return response()->json(['erro' => $e->getMessage()], 422);
    }
});

// ── Histórico de apurações por empresa ───────────────────────────────────────
Route::get('/terceiros/apuracao/{empresa_id}', function (int $empresaId) {
    try {
        $apuracoes = DB::table('TERCEIRO_APURACAO')
            ->where('EMPRESA_ID', $empresaId)
            ->orderByDesc('APURACAO_COMPETENCIA')
            ->limit(24)
            ->get();
        return response()->json(['apuracoes' => $apuracoes]);
    } catch (\Throwable $e) {
        return response()->json(['erro' => $e->getMessage()], 500);
    }
});
