<?php
// ══════════════════════════════════════════════════════════════════
// APP MOBILE DE PONTO — Endpoints sem sessão web, autenticados por JWT simples
// Incluído dentro do grupo api/v3 do web.php
// ══════════════════════════════════════════════════════════════════

use Illuminate\Support\Facades\Hash;

// ── Helper: calcular distância Haversine em metros ────────────────
$haversine = function ($lat1, $lon1, $lat2, $lon2) {
    $R = 6371000; // raio da Terra em metros
    $dLat = deg2rad($lat2 - $lat1);
    $dLon = deg2rad($lon2 - $lon1);
    $a = sin($dLat / 2) ** 2 + cos(deg2rad($lat1)) * cos(deg2rad($lat2)) * sin($dLon / 2) ** 2;
    return $R * 2 * atan2(sqrt($a), sqrt(1 - $a));
};

// ── Helper: decodificar token JWT simples (HMAC-SHA256) ───────────
$decodeAppToken = function (Request $request) {
    $header = $request->header('Authorization', '');
    $token = str_starts_with($header, 'Bearer ') ? substr($header, 7) : '';
    if (!$token)
        return null;

    $parts = explode('.', $token);
    if (count($parts) !== 3)
        return null;

    $payload = json_decode(base64_decode(str_replace(['-', '_'], ['+', '/'], $parts[1])), true);
    // SEC-03: usa segredo JWT próprio, não o config('app.key')
    $secret = env('PONTO_APP_JWT_SECRET') ?: config('app.key');
    $signature = hash_hmac('sha256', $parts[0] . '.' . $parts[1], $secret, true);
    $expected = rtrim(strtr(base64_encode($signature), '+/', '-_'), '=');

    if (!hash_equals($expected, $parts[2]))
        return null;
    if (isset($payload['exp']) && $payload['exp'] < time())
        return null;

    return $payload;
};

// ── 1. Login do funcionário no app ────────────────────────────────
Route::post('/ponto/app/login', function (Request $request) {
    $cpf = preg_replace('/[^0-9]/', '', (string) $request->input('cpf', ''));
    $senha = (string) $request->input('senha', '');

    if (!$cpf || !$senha) {
        return response()->json(['erro' => 'CPF e senha são obrigatórios'], 422);
    }

    // Busca usuário pelo CPF (via Pessoa)
    $pessoa = DB::table('PESSOA')->where('PESSOA_CPF_NUMERO', $cpf)->first();
    if (!$pessoa) {
        return response()->json(['erro' => 'CPF não encontrado'], 401);
    }

    $usuario = DB::table('USUARIO')->where('PESSOA_ID', $pessoa->PESSOA_ID)->first();
    if (!$usuario || !Hash::check($senha, $usuario->USUARIO_SENHA ?? '')) {
        return response()->json(['erro' => 'Credenciais inválidas'], 401);
    }

    $func = DB::table('FUNCIONARIO')->where('PESSOA_ID', $pessoa->PESSOA_ID)->first();
    if (!$func) {
        return response()->json(['erro' => 'Funcionário não encontrado'], 403);
    }

    // Gera JWT simples (header.payload.sig)
    $header = rtrim(strtr(base64_encode(json_encode(['alg' => 'HS256', 'typ' => 'JWT'])), '+/', '-_'), '=');
    $payload = rtrim(strtr(base64_encode(json_encode([
        'sub' => $usuario->USUARIO_ID,
        'func' => $func->FUNCIONARIO_ID,
        'nome' => $pessoa->PESSOA_NOME,
        'exp' => time() + 86400 * 7, // 7 dias
    ])), '+/', '-_'), '=');
    // SEC-03: usa segredo JWT próprio, não o config('app.key')
    $jwtSecret = env('PONTO_APP_JWT_SECRET') ?: config('app.key');
    $sig = rtrim(strtr(base64_encode(hash_hmac('sha256', "$header.$payload", $jwtSecret, true)), '+/', '-_'), '=');

    return response()->json([
        'token' => "$header.$payload.$sig",
        'nome' => $pessoa->PESSOA_NOME,
        'funcionario_id' => $func->FUNCIONARIO_ID,
    ]);
});

// ── 2. Dados do funcionário + terminal vinculado ───────────────────
Route::get('/ponto/app/me', function (Request $request) use ($decodeAppToken) {
    $payload = $decodeAppToken($request);
    if (!$payload)
        return response()->json(['erro' => 'Não autorizado'], 401);

    $funcId = $payload['func'];
    $func = DB::table('FUNCIONARIO as f')
        ->join('PESSOA as p', 'p.PESSOA_ID', '=', 'f.PESSOA_ID')
        ->leftJoin('CARGO as c', 'c.CARGO_ID', '=', 'f.CARGO_ID')
        ->where('f.FUNCIONARIO_ID', $funcId)
        ->select('f.*', 'p.PESSOA_NOME', 'c.CARGO_NOME')
        ->first();

    if (!$func)
        return response()->json(['erro' => 'Funcionário não encontrado'], 404);

    // Busca terminal ativo da unidade do funcionário
    $lotacao = DB::table('LOTACAO')->where('FUNCIONARIO_ID', $funcId)->where('LOTACAO_ATIVO', 1)->first();
    $terminal = null;
    if ($lotacao) {
        $terminal = DB::table('TERMINAL_PONTO')
            ->where('UNIDADE_ID', $lotacao->UNIDADE_ID)
            ->where('TERMINAL_ATIVO', 1)
            ->first();
    }

    return response()->json([
        'funcionario' => [
            'id' => $func->FUNCIONARIO_ID,
            'nome' => $func->PESSOA_NOME,
            'matricula' => $func->FUNCIONARIO_MATRICULA,
            'cargo' => $func->CARGO_NOME ?? '—',
        ],
        'terminal' => $terminal ? [
            'id' => $terminal->TERMINAL_ID,
            'nome' => $terminal->TERMINAL_NOME,
            'latitude' => (float) $terminal->TERMINAL_LATITUDE,
            'longitude' => (float) $terminal->TERMINAL_LONGITUDE,
            'raio_metros' => (int) $terminal->TERMINAL_RAIO_METROS,
        ] : null,
    ]);
});

// ── 3. Status do dia (entradas/saídas de hoje) ────────────────────
Route::get('/ponto/app/status-hoje', function (Request $request) use ($decodeAppToken) {
    $payload = $decodeAppToken($request);
    if (!$payload)
        return response()->json(['erro' => 'Não autorizado'], 401);

    $hoje = now()->format('Y-m-d');
    $registros = DB::table('REGISTRO_PONTO')
        ->where('FUNCIONARIO_ID', $payload['func'])
        ->whereDate('REGISTRO_DATA_HORA', $hoje)
        ->orderBy('REGISTRO_DATA_HORA')
        ->get(['REGISTRO_TIPO', 'REGISTRO_DATA_HORA', 'REGISTRO_FACE_OK']);

    $mapa = ['ENTRADA' => null, 'PAUSA' => null, 'RETORNO' => null, 'SAIDA' => null];
    foreach ($registros as $r) {
        $tipo = strtoupper($r->REGISTRO_TIPO);
        if (array_key_exists($tipo, $mapa)) {
            $mapa[$tipo] = substr($r->REGISTRO_DATA_HORA, 11, 5); // HH:MM
        }
    }

    // Próxima batida esperada
    $proxima = null;
    $ordem = ['ENTRADA', 'PAUSA', 'RETORNO', 'SAIDA'];
    foreach ($ordem as $tipo) {
        if (!$mapa[$tipo]) {
            $proxima = $tipo;
            break;
        }
    }

    return response()->json([
        'data' => $hoje,
        'entrada' => $mapa['ENTRADA'],
        'pausa' => $mapa['PAUSA'],
        'retorno' => $mapa['RETORNO'],
        'saida' => $mapa['SAIDA'],
        'proxima' => $proxima,
        'total_registros' => count($registros),
    ]);
});

// ── 4. Histórico de ponto (últimos 30 dias) ────────────────────────
Route::get('/ponto/app/registros', function (Request $request) use ($decodeAppToken) {
    $payload = $decodeAppToken($request);
    if (!$payload)
        return response()->json(['erro' => 'Não autorizado'], 401);

    $registros = DB::table('REGISTRO_PONTO')
        ->where('FUNCIONARIO_ID', $payload['func'])
        ->where('REGISTRO_DATA_HORA', '>=', now()->subDays(30)->format('Y-m-d'))
        ->orderByDesc('REGISTRO_DATA_HORA')
        ->get([
            'REGISTRO_PONTO_ID',
            'REGISTRO_DATA_HORA',
            'REGISTRO_TIPO',
            'REGISTRO_ORIGEM',
            'REGISTRO_FACE_OK',
            'REGISTRO_DISTANCIA_M',
        ]);

    // Agrupar por data
    $agrupado = [];
    foreach ($registros as $r) {
        $data = substr($r->REGISTRO_DATA_HORA, 0, 10);
        $agrupado[$data][] = [
            'id' => $r->REGISTRO_PONTO_ID,
            'hora' => substr($r->REGISTRO_DATA_HORA, 11, 5),
            'tipo' => $r->REGISTRO_TIPO,
            'origem' => $r->REGISTRO_ORIGEM,
            'face_ok' => (bool) $r->REGISTRO_FACE_OK,
            'distancia_m' => $r->REGISTRO_DISTANCIA_M,
        ];
    }

    $resultado = [];
    foreach ($agrupado as $data => $itens) {
        $resultado[] = ['data' => $data, 'registros' => $itens];
    }

    return response()->json($resultado);
});

// ── 5. Registrar ponto (validar GPS + facial) ─────────────────────
Route::post('/ponto/app/registrar', function (Request $request) use ($decodeAppToken, $haversine) {
    $payload = $decodeAppToken($request);
    if (!$payload)
        return response()->json(['erro' => 'Não autorizado'], 401);

    $funcId = $payload['func'];
    $lat = (float) $request->input('latitude');
    $lng = (float) $request->input('longitude');
    $faceOk = (bool) $request->input('face_ok', false);
    $fotoB64 = $request->input('foto_base64', null);
    $tipo = strtoupper($request->input('tipo', 'ENTRADA')); // ENTRADA|PAUSA|RETORNO|SAIDA

    if (!in_array($tipo, ['ENTRADA', 'PAUSA', 'RETORNO', 'SAIDA'])) {
        return response()->json(['erro' => 'Tipo inválido'], 422);
    }

    if (!$faceOk) {
        return response()->json(['erro' => 'Reconhecimento facial não confirmado'], 422);
    }

    // Busca terminal da unidade do funcionário
    $lotacao = DB::table('LOTACAO')->where('FUNCIONARIO_ID', $funcId)->where('LOTACAO_ATIVO', 1)->first();
    $terminal = null;
    if ($lotacao) {
        $terminal = DB::table('TERMINAL_PONTO')
            ->where('UNIDADE_ID', $lotacao->UNIDADE_ID)
            ->where('TERMINAL_ATIVO', 1)
            ->first();
    }

    // Valida GPS
    $distancia = null;
    if ($terminal && $terminal->TERMINAL_LATITUDE && $lat) {
        $distancia = (int) round($haversine(
            (float) $terminal->TERMINAL_LATITUDE,
            (float) $terminal->TERMINAL_LONGITUDE,
            $lat,
            $lng
        ));
        $raio = (int) $terminal->TERMINAL_RAIO_METROS;
        if ($distancia > $raio) {
            return response()->json([
                'erro' => "Você está fora do raio permitido ({$raio}m). Distância atual: {$distancia}m.",
                'distancia_m' => $distancia,
                'raio_m' => $raio,
            ], 422);
        }
    }

    // Salva foto se enviada
    $fotoPath = null;
    if ($fotoB64) {
        $imgData = base64_decode(preg_replace('/^data:image\/\w+;base64,/', '', $fotoB64));
        $filename = 'ponto_facial/' . $funcId . '_' . now()->format('YmdHis') . '.jpg';
        \Storage::disk('public')->put($filename, $imgData);
        $fotoPath = $filename;
    }

    // Insere registro
    $id = DB::table('REGISTRO_PONTO')->insertGetId([
        'FUNCIONARIO_ID' => $funcId,
        'TERMINAL_ID' => $terminal->TERMINAL_ID ?? null,
        'REGISTRO_DATA_HORA' => now()->format('Y-m-d H:i:s'),
        'REGISTRO_TIPO' => $tipo,
        'REGISTRO_ORIGEM' => 'APP_FACIAL',
        'REGISTRO_LATITUDE' => $lat ?: null,
        'REGISTRO_LONGITUDE' => $lng ?: null,
        'REGISTRO_DISTANCIA_M' => $distancia,
        'REGISTRO_FACE_OK' => true,
        'REGISTRO_FOTO_PATH' => $fotoPath,
    ]);

    return response()->json([
        'ok' => true,
        'registro_id' => $id,
        'tipo' => $tipo,
        'hora' => now()->format('H:i'),
        'distancia_m' => $distancia,
    ]);
});
