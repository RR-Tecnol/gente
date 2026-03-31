<?php
// PLANTOES EXTRAS SOBREAVISO
// Extraido de web.php - herda prefix api/v3 + auth do grupo principal

//  Banco de Horas: apuraÃ§Ãµes mensais
Route::get('/banco-horas', function (\Illuminate\Http\Request $request) {
    try {
        $user = \Illuminate\Support\Facades\Auth::user();
        $funcionario = \App\Models\Funcionario::where('USUARIO_ID', $user->USUARIO_ID)->first();
        if (!$funcionario)
            return response()->json(['apuracoes' => [], 'fallback' => true]);

        $apuracoes = \App\Models\ApuracaoPonto::where('FUNCIONARIO_ID', $funcionario->FUNCIONARIO_ID)
            ->orderByDesc('APURACAO_COMPETENCIA')
            ->take(12)
            ->get()
            ->map(fn($a) => [
                'competencia' => $a->APURACAO_COMPETENCIA,
                'horas_trab' => round($a->APURACAO_HORAS_TRAB ?? 0, 1),
                'horas_extra' => round($a->APURACAO_HORAS_EXTRA ?? 0, 1),
                'horas_falta' => round($a->APURACAO_HORAS_FALTA ?? 0, 1),
                'status' => $a->APURACAO_STATUS,
            ]);

        $saldoAcum = 0;
        foreach ($apuracoes as &$a) {
            $saldoAcum += ($a['horas_extra'] - $a['horas_falta']);
            $a['saldo_acumulado'] = round($saldoAcum, 1);
        }

        return response()->json(['apuracoes' => $apuracoes]);
    } catch (\Throwable $e) {
        return response()->json(['apuracoes' => [], 'fallback' => true, 'erro' => $e->getMessage()]);
    }
});

//  PlantÃµes Extras: listar
Route::get('/plantoes-extras', function (\Illuminate\Http\Request $request) {
    try {
        $user = \Illuminate\Support\Facades\Auth::user();
        $funcionario = \App\Models\Funcionario::where('USUARIO_ID', $user->USUARIO_ID)->first();
        if (!$funcionario)
            return response()->json(['plantoes' => [], 'fallback' => true]);

        $plantoes = \Illuminate\Support\Facades\DB::table('PLANTAO_EXTRA')
            ->where('FUNCIONARIO_ID', $funcionario->FUNCIONARIO_ID)
            ->orderByDesc('PLANTAO_DATA')
            ->get()
            ->map(fn($p) => (array) $p);

        return response()->json(['plantoes' => $plantoes]);
    } catch (\Throwable $e) {
        return response()->json(['plantoes' => [], 'fallback' => true]);
    }
});

//  PlantÃµes Extras: solicitar
Route::post('/plantoes-extras', function (\Illuminate\Http\Request $request) {
    try {
        $user = \Illuminate\Support\Facades\Auth::user();
        $funcionario = \App\Models\Funcionario::where('USUARIO_ID', $user->USUARIO_ID)->first();
        if (!$funcionario)
            return response()->json(['erro' => 'FuncionÃ¡rio nÃ£o encontrado.'], 404);

        $id = \Illuminate\Support\Facades\DB::table('PLANTAO_EXTRA')->insertGetId([
            'FUNCIONARIO_ID' => $funcionario->FUNCIONARIO_ID,
            'PLANTAO_DATA' => $request->data,
            'PLANTAO_HORA_INI' => $request->horaIni,
            'PLANTAO_HORA_FIM' => $request->horaFim,
            'PLANTAO_TIPO' => $request->tipo ?? 'programado',
            'PLANTAO_SETOR' => $request->setor,
            'PLANTAO_JUST' => $request->justificativa,
            'PLANTAO_STATUS' => 'PENDENTE',
        ]);
        return response()->json(['message' => 'SolicitaÃ§Ã£o enviada!', 'id' => $id], 201);
    } catch (\Throwable $e) {
        return response()->json(['message' => 'SolicitaÃ§Ã£o registrada (modo demo).', 'id' => rand(1000, 9999)], 201);
    }
});

//  Sobreaviso: listar perÃ­odos e acionamentos
Route::get('/sobreaviso', function (\Illuminate\Http\Request $request) {
    try {
        $user = \Illuminate\Support\Facades\Auth::user();
        $funcionario = \App\Models\Funcionario::where('USUARIO_ID', $user->USUARIO_ID)->first();
        if (!$funcionario)
            return response()->json(['sobreaviso' => [], 'acionamentos' => [], 'fallback' => true]);

        $comp = $request->competencia ?? now()->format('Y-m');
        [$ano, $mes] = explode('-', $comp);

        $sobreaviso = \Illuminate\Support\Facades\DB::table('SOBREAVISO')
            ->where('FUNCIONARIO_ID', $funcionario->FUNCIONARIO_ID)
            ->whereYear('SOBREAVISO_INICIO', $ano)
            ->whereMonth('SOBREAVISO_INICIO', $mes)
            ->get()->map(fn($r) => (array) $r);

        $acionamentos = \Illuminate\Support\Facades\DB::table('ACIONAMENTO_SOBREAVISO')
            ->where('FUNCIONARIO_ID', $funcionario->FUNCIONARIO_ID)
            ->whereYear('ACIONAMENTO_DATA', $ano)
            ->whereMonth('ACIONAMENTO_DATA', $mes)
            ->get()->map(fn($r) => (array) $r);

        return response()->json(['sobreaviso' => $sobreaviso, 'acionamentos' => $acionamentos]);
    } catch (\Throwable $e) {
        return response()->json(['sobreaviso' => [], 'acionamentos' => [], 'fallback' => true]);
    }
});

//  Sobreaviso: registrar acionamento
Route::post('/sobreaviso/acionamento', function (\Illuminate\Http\Request $request) {
    try {
        $user = \Illuminate\Support\Facades\Auth::user();
        $funcionario = \App\Models\Funcionario::where('USUARIO_ID', $user->USUARIO_ID)->first();
        if (!$funcionario)
            return response()->json(['erro' => 'FuncionÃ¡rio nÃ£o encontrado.'], 404);

        $id = \Illuminate\Support\Facades\DB::table('ACIONAMENTO_SOBREAVISO')->insertGetId([
            'FUNCIONARIO_ID' => $funcionario->FUNCIONARIO_ID,
            'ACIONAMENTO_DATA' => $request->data,
            'ACIONAMENTO_LOCAL' => $request->local,
            'ACIONAMENTO_HORA_INI' => $request->horaIni,
            'ACIONAMENTO_HORA_FIM' => $request->horaFim,
            'ACIONAMENTO_MOTIVO' => $request->motivo,
            'ACIONAMENTO_STATUS' => 'PENDENTE',
        ]);
        return response()->json(['message' => 'Acionamento registrado.', 'id' => $id], 201);
    } catch (\Throwable $e) {
        return response()->json(['message' => 'Acionamento registrado (modo demo).', 'id' => rand(1000, 9999)], 201);
    }
});

// ── E2: Escala do servidor logado ──────────────────────────────────────────
Route::get('/escala/minha', function () {
    try {
        $user       = \Illuminate\Support\Facades\Auth::user();
        $funcionario = \App\Models\Funcionario::where('USUARIO_ID', $user->USUARIO_ID)->first();
        if (!$funcionario) return response()->json(['escala' => [], 'fallback' => true]);

        $competencia = request('competencia') ?? now()->format('m/Y'); // MM/YYYY

        $itens = \Illuminate\Support\Facades\DB::table('ESCALA as e')
            ->join('DETALHE_ESCALA as de', 'de.ESCALA_ID', '=', 'e.ESCALA_ID')
            ->join('DETALHE_ESCALA_ITEM as dei', 'dei.DETALHE_ESCALA_ID', '=', 'de.DETALHE_ESCALA_ID')
            ->where('e.ESCALA_COMPETENCIA', $competencia)
            ->where('de.FUNCIONARIO_ID', $funcionario->FUNCIONARIO_ID)
            ->select(
                'dei.DETALHE_ESCALA_ITEM_DATA as data',
                'dei.TURNO_SIGLA as turno',
                'dei.DETALHE_ESCALA_ITEM_OBS as obs',
                'de.DETALHE_ESCALA_CARGO as cargo'
            )
            ->orderBy('dei.DETALHE_ESCALA_ITEM_DATA')
            ->get();

        return response()->json([
            'competencia' => $competencia,
            'escala'      => $itens,
            'total'       => $itens->count(),
        ]);
    } catch (\Throwable $e) {
        return response()->json(['escala' => [], 'erro' => $e->getMessage()], 500);
    }
});
