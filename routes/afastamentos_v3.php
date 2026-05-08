<?php
use Illuminate\Support\Facades\Route;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Schema;

if (!function_exists('resolveFuncionarioAfastamentosV3')) {
    function resolveFuncionarioAfastamentosV3($user)
    {
        if (!$user) {
            return null;
        }

        $func = DB::table('FUNCIONARIO')->where('USUARIO_ID', $user->USUARIO_ID)->first();
        if ($func) {
            return $func;
        }

        return null;
    }
}

if (!function_exists('resolveTipoAfastamentoV3')) {
    function resolveTipoAfastamentoV3($tipoRaw)
    {
        if ($tipoRaw === null || $tipoRaw === '') {
            return null;
        }
        if (is_numeric($tipoRaw)) {
            return (int) $tipoRaw;
        }

        $normal = strtolower(str_replace(['-', ' '], '_', trim((string) $tipoRaw)));
        $apelidos = [
            'licenca_premio' => 'licenca premio',
            'fins_particulares' => 'fins particulares',
            'licenca_maternidade' => 'licenca maternidade',
            'licenca_paternidade' => 'licenca paternidade',
            'licenca_capacitacao' => 'licenca capacitacao',
            'licenca_judicial' => 'judicial',
        ];
        $termo = $apelidos[$normal] ?? str_replace('_', ' ', $normal);

        $mapaDireto = [
            'licenca_premio' => 1,
            'fins_particulares' => 2,
            'licenca_maternidade' => 3,
            'licenca_paternidade' => 4,
            'licenca_capacitacao' => 5,
            'licenca_judicial' => 6,
        ];
        if (isset($mapaDireto[$normal])) {
            return $mapaDireto[$normal];
        }

        try {
            $tipos = DB::table('TABELA_GENERICA')
                ->where('TABELA_ID', \App\MyLibs\RTG::TIPO_AFASTAMENTO)
                ->where('COLUNA_ID', '!=', 0)
                ->select('COLUNA_ID', 'COLUNA_DESCRICAO')
                ->get();

            foreach ($tipos as $t) {
                $desc = strtolower(iconv('UTF-8', 'ASCII//TRANSLIT//IGNORE', (string) ($t->COLUNA_DESCRICAO ?? '')));
                if (str_contains($desc, $termo)) {
                    return (int) $t->COLUNA_ID;
                }
            }
        } catch (\Throwable $e) {
            // fallback silencioso para schema que não tem tabela genérica
        }

        return 1;
    }
}

if (!function_exists('ensureAnexoAfastamentoTableV3')) {
    function ensureAnexoAfastamentoTableV3(): void
    {
        if (!Schema::hasTable('ANEXO_AFASTAMENTO')) {
            throw new \RuntimeException('Tabela ANEXO_AFASTAMENTO não encontrada. Execute migrations canônicas.');
        }
    }
}

Route::get('/afastamentos', function () {
    $user = Auth::user();
    $func = resolveFuncionarioAfastamentosV3($user);
    if (!$func)
        return response()->json(['afastamentos' => []]);

    $rows = DB::table('AFASTAMENTO')
        ->where('FUNCIONARIO_ID', $func->FUNCIONARIO_ID)
        ->orderByDesc('AFASTAMENTO_DATA_INICIO')
        ->get();

    $tiposPorId = [];
    try {
        $tiposPorId = DB::table('TABELA_GENERICA')
            ->where('TABELA_ID', \App\MyLibs\RTG::TIPO_AFASTAMENTO)
            ->where('COLUNA_ID', '!=', 0)
            ->pluck('COLUNA_DESCRICAO', 'COLUNA_ID')
            ->toArray();
    } catch (\Throwable $e) {
        $tiposPorId = [];
    }

    $anexosPorAfastamento = [];
    if (Schema::hasTable('ANEXO_AFASTAMENTO')) {
        $ids = $rows->pluck('AFASTAMENTO_ID')->filter()->values();
        if ($ids->isNotEmpty()) {
            $anexos = DB::table('ANEXO_AFASTAMENTO')
                ->whereIn('AFASTAMENTO_ID', $ids)
                ->orderByDesc('ANEXO_AFASTAMENTO_ID')
                ->get();
            foreach ($anexos as $anexo) {
                if (!isset($anexosPorAfastamento[$anexo->AFASTAMENTO_ID])) {
                    $anexosPorAfastamento[$anexo->AFASTAMENTO_ID] = $anexo;
                }
            }
        }
    }

    $mapped = $rows->map(function ($r) use ($tiposPorId, $anexosPorAfastamento) {
        $tipoRaw = $r->AFASTAMENTO_TIPO ?? null;
        $tipoNome = $r->AFASTAMENTO_TIPO_NOME
            ?? (is_numeric($tipoRaw) ? ($tiposPorId[(int) $tipoRaw] ?? null) : $tipoRaw);
        if (is_numeric($tipoRaw)) {
            $tipoMap = [
                1 => 'licenca_premio',
                2 => 'fins_particulares',
                3 => 'licenca_maternidade',
                4 => 'licenca_paternidade',
                5 => 'licenca_capacitacao',
                6 => 'licenca_judicial',
            ];
            $tipo = $tipoMap[(int) $tipoRaw] ?? 'outros';
        } else {
            $tipo = strtolower(str_replace(' ', '_', (string) ($tipoRaw ?? $tipoNome ?? 'outros')));
        }

        $anexo = $anexosPorAfastamento[$r->AFASTAMENTO_ID] ?? null;
        $ext = strtolower((string) ($anexo->ANEXO_AFASTAMENTO_EXTENSAO ?? ''));
        $ehImagem = in_array($ext, ['jpg', 'jpeg', 'png', 'gif', 'webp'], true);

        return [
            'id' => $r->AFASTAMENTO_ID ?? null,
            'tipo' => $tipo,
            'tipo_nome' => $tipoNome ?? $tipo,
            'inicio' => $r->AFASTAMENTO_DATA_INICIO ?? null,
            'fim' => $r->AFASTAMENTO_DATA_FIM ?? null,
            'obs' => $r->AFASTAMENTO_OBS ?? $r->AFASTAMENTO_OBSERVACAO ?? null,
            'status' => $r->AFASTAMENTO_STATUS ?? 'pendente',
            'anexo' => $anexo ? [
                'id' => $anexo->ANEXO_AFASTAMENTO_ID ?? null,
                'nome' => $anexo->ANEXO_AFASTAMENTO_NOME ?? 'anexo',
                'extensao' => $ext,
                'eh_imagem' => $ehImagem,
                'download_url' => url('/api/v3/afastamentos/' . ($r->AFASTAMENTO_ID ?? 0) . '/anexo/' . ($anexo->ANEXO_AFASTAMENTO_ID ?? 0) . '/download'),
            ] : null,
            'AFASTAMENTO_ID' => $r->AFASTAMENTO_ID ?? null,
            'AFASTAMENTO_TIPO' => $tipoRaw,
            'AFASTAMENTO_DATA_INICIO' => $r->AFASTAMENTO_DATA_INICIO ?? null,
            'AFASTAMENTO_DATA_FIM' => $r->AFASTAMENTO_DATA_FIM ?? null,
            'AFASTAMENTO_STATUS' => $r->AFASTAMENTO_STATUS ?? 'pendente',
        ];
    })->values();

    return response()->json(['afastamentos' => $mapped]);
});

Route::post('/afastamentos', function (\Illuminate\Http\Request $request) {
    try {
        $request->validate([
            'tipo' => 'required',
            'inicio' => 'required|date',
            'fim' => 'nullable|date|after_or_equal:inicio',
            'obs' => 'nullable|string',
        ]);

        $user = Auth::user();
        $func = resolveFuncionarioAfastamentosV3($user);
        if (!$func)
            return response()->json(['erro' => 'Funcionário não encontrado.'], 404);

        $insert = [
            'FUNCIONARIO_ID' => $func->FUNCIONARIO_ID,
            'AFASTAMENTO_DATA_INICIO' => $request->inicio,
        ];

        if (Schema::hasColumn('AFASTAMENTO', 'AFASTAMENTO_DATA_FIM')) {
            $insert['AFASTAMENTO_DATA_FIM'] = $request->fim;
        }

        $tipoId = resolveTipoAfastamentoV3($request->tipo);
        if (Schema::hasColumn('AFASTAMENTO', 'AFASTAMENTO_TIPO')) {
            $insert['AFASTAMENTO_TIPO'] = (int) ($tipoId ?? 1);
        }
        if (Schema::hasColumn('AFASTAMENTO', 'AFASTAMENTO_TIPO_NOME')) {
            $insert['AFASTAMENTO_TIPO_NOME'] = (string) $request->tipo;
        }

        if (Schema::hasColumn('AFASTAMENTO', 'AFASTAMENTO_OBS')) {
            $insert['AFASTAMENTO_OBS'] = $request->obs;
        } elseif (Schema::hasColumn('AFASTAMENTO', 'AFASTAMENTO_OBSERVACAO')) {
            $insert['AFASTAMENTO_OBSERVACAO'] = $request->obs;
        }

        if (Schema::hasColumn('AFASTAMENTO', 'AFASTAMENTO_STATUS')) {
            $insert['AFASTAMENTO_STATUS'] = 'pendente';
        }
        if (Schema::hasColumn('AFASTAMENTO', 'created_at')) {
            $insert['created_at'] = now();
        }
        if (Schema::hasColumn('AFASTAMENTO', 'updated_at')) {
            $insert['updated_at'] = now();
        }
        if (Schema::hasColumn('AFASTAMENTO', 'AFASTAMENTO_DATA_CADASTRO')) {
            $insert['AFASTAMENTO_DATA_CADASTRO'] = now();
        }

        $id = DB::table('AFASTAMENTO')->insertGetId($insert);

        return response()->json([
            'ok' => true,
            'id' => $id,
            'protocolo' => 'AFT-' . str_pad($id, 5, '0', STR_PAD_LEFT),
        ], 201);
    } catch (\Throwable $e) {
        return response()->json(['erro' => 'Erro ao registrar afastamento: ' . $e->getMessage()], 500);
    }
})->middleware('perfil:SERVIDOR,ADMINISTRADOR,Administrador,GESTOR');

Route::post('/afastamentos/{id}/anexo', function (int $id, \Illuminate\Http\Request $request) {
    try {
        ensureAnexoAfastamentoTableV3();

        $request->validate([
            'arquivo' => 'required|file|mimes:jpg,jpeg,png,gif,webp,pdf,doc,docx|max:5120',
        ]);

        $user = Auth::user();
        $func = resolveFuncionarioAfastamentosV3($user);
        if (!$func) {
            return response()->json(['erro' => 'Funcionário não encontrado.'], 404);
        }

        $afast = DB::table('AFASTAMENTO')
            ->where('AFASTAMENTO_ID', $id)
            ->where('FUNCIONARIO_ID', $func->FUNCIONARIO_ID)
            ->first();
        if (!$afast) {
            return response()->json(['erro' => 'Afastamento não encontrado para o usuário logado.'], 404);
        }

        $file = $request->file('arquivo');
        $nomeOriginal = pathinfo((string) $file->getClientOriginalName(), PATHINFO_FILENAME);
        $ext = strtolower((string) $file->getClientOriginalExtension());
        $conteudo = base64_encode((string) file_get_contents($file->getRealPath()));

        $insert = [
            'AFASTAMENTO_ID' => $id,
            'ANEXO_AFASTAMENTO_ARQUIVO' => $conteudo,
        ];
        if (Schema::hasColumn('ANEXO_AFASTAMENTO', 'ANEXO_AFASTAMENTO_DESCRICAO')) {
            $insert['ANEXO_AFASTAMENTO_DESCRICAO'] = (string) ($request->input('descricao') ?? 'Anexo enviado pelo servidor');
        }
        if (Schema::hasColumn('ANEXO_AFASTAMENTO', 'ANEXO_AFASTAMENTO_NOME')) {
            $insert['ANEXO_AFASTAMENTO_NOME'] = $nomeOriginal ?: ('anexo_' . $id);
        }
        if (Schema::hasColumn('ANEXO_AFASTAMENTO', 'ANEXO_AFASTAMENTO_EXTENSAO')) {
            $insert['ANEXO_AFASTAMENTO_EXTENSAO'] = $ext;
        }

        $anexoId = DB::table('ANEXO_AFASTAMENTO')->insertGetId($insert);

        return response()->json([
            'ok' => true,
            'anexo_id' => $anexoId,
            'download_url' => url('/api/v3/afastamentos/' . $id . '/anexo/' . $anexoId . '/download'),
        ], 201);
    } catch (\Illuminate\Validation\ValidationException $e) {
        return response()->json(['erro' => $e->errors()['arquivo'][0] ?? 'Arquivo inválido.'], 422);
    } catch (\Throwable $e) {
        return response()->json(['erro' => 'Erro ao anexar documento: ' . $e->getMessage()], 500);
    }
})->middleware('perfil:SERVIDOR,ADMINISTRADOR,Administrador,GESTOR');

Route::get('/afastamentos/{id}/anexo/{anexoId}/download', function (int $id, int $anexoId) {
    try {
        ensureAnexoAfastamentoTableV3();

        $user = Auth::user();
        $func = resolveFuncionarioAfastamentosV3($user);
        if (!$func) {
            return response()->json(['erro' => 'Funcionário não encontrado.'], 404);
        }

        $afast = DB::table('AFASTAMENTO')
            ->where('AFASTAMENTO_ID', $id)
            ->where('FUNCIONARIO_ID', $func->FUNCIONARIO_ID)
            ->first();
        if (!$afast) {
            return response()->json(['erro' => 'Afastamento não encontrado.'], 404);
        }

        $anexo = DB::table('ANEXO_AFASTAMENTO')
            ->where('ANEXO_AFASTAMENTO_ID', $anexoId)
            ->where('AFASTAMENTO_ID', $id)
            ->first();
        if (!$anexo) {
            return response()->json(['erro' => 'Anexo não encontrado.'], 404);
        }

        $bin = base64_decode((string) ($anexo->ANEXO_AFASTAMENTO_ARQUIVO ?? ''), true);
        if ($bin === false) {
            return response()->json(['erro' => 'Arquivo inválido no repositório.'], 422);
        }

        $ext = strtolower((string) ($anexo->ANEXO_AFASTAMENTO_EXTENSAO ?? 'bin'));
        $nome = (string) ($anexo->ANEXO_AFASTAMENTO_NOME ?? 'anexo');
        $mimeMap = [
            'jpg' => 'image/jpeg',
            'jpeg' => 'image/jpeg',
            'png' => 'image/png',
            'gif' => 'image/gif',
            'webp' => 'image/webp',
            'pdf' => 'application/pdf',
            'doc' => 'application/msword',
            'docx' => 'application/vnd.openxmlformats-officedocument.wordprocessingml.document',
        ];
        $mime = $mimeMap[$ext] ?? 'application/octet-stream';
        $inline = str_starts_with($mime, 'image/') || $mime === 'application/pdf';
        $disposition = ($inline ? 'inline' : 'attachment') . '; filename="' . $nome . '.' . $ext . '"';

        return response($bin, 200, [
            'Content-Type' => $mime,
            'Content-Disposition' => $disposition,
            'Cache-Control' => 'private, max-age=60',
        ]);
    } catch (\Throwable $e) {
        return response()->json(['erro' => 'Erro ao baixar anexo: ' . $e->getMessage()], 500);
    }
});

// ═════════════════════════════════════════════════════════════════════
// GAP-ALERT (Fase 4 T4.8 — 08/05/2026): Migração da rota legada
// /afastamento/alerta-expirar (web.php, bloco autenticado) → /api/v3/afastamentos/alerta-expirar
// Mudança de naming: singular → plural para alinhar com restante deste arquivo.
// Lógica idêntica delegada ao AfastamentoController::alertaExpirar (já Auth-aware).
// Filtro: COORD_DE_SETOR vê apenas seu setor; demais perfis veem todos.
// ═════════════════════════════════════════════════════════════════════
Route::get('/afastamentos/alerta-expirar', [\App\Http\Controllers\AfastamentoController::class, 'alertaExpirar'])
    ->name('api.v3.afastamentos.alerta-expirar');

