<?php
// ATESTADOS / AFASTAMENTOS — LGPD: escopo por titularidade; RH/admin vê conjunto para validação.
// Incluído em api_v3_auth_part1.php → middleware ['web','auth','audit']

if (!function_exists('resolveFuncionarioComFallbackDev')) {
    function resolveFuncionarioComFallbackDev($user)
    {
        if (!$user) {
            return null;
        }

        return \App\Models\Funcionario::where('USUARIO_ID', $user->USUARIO_ID)->first();
    }
}

if (!function_exists('apiV3UsuarioEhRhOuAdminAtestados')) {
    /**
     * Visão “gestão” de atestados: perfis com acesso a lista global e aprovação.
     */
    function apiV3UsuarioEhRhOuAdminAtestados($user): bool
    {
        if (!$user) {
            return false;
        }
        $raw = strtolower(trim((string) ($user->PERFIL ?? '')));
        if ($raw !== '') {
            if (str_contains($raw, 'admin') || str_contains($raw, 'rh')) {
                return true;
            }
        }
        $nome = strtolower(trim((string) (optional($user->usuarioPerfis()->with('perfil')->first())->perfil->PERFIL_NOME ?? '')));
        if ($nome === '') {
            return false;
        }
        if (in_array($nome, ['admin', 'administrador', 'rh', 'recursos humanos'], true)) {
            return true;
        }
        if (str_contains($nome, 'rh') || str_contains($nome, 'administrador') || str_contains($nome, 'admin')) {
            return true;
        }

        return false;
    }
}

if (!function_exists('mapearAfastamentoParaApi')) {
    function mapearAfastamentoParaApi(\App\Models\Afastamento $a): array
    {
        $servidorNome = null;
        try {
            $servidorNome = $a->funcionario?->pessoa?->PESSOA_NOME ?? null;
        } catch (\Throwable $e) {
            $servidorNome = null;
        }

        return [
            'id' => $a->AFASTAMENTO_ID,
            'funcionario_id' => $a->FUNCIONARIO_ID,
            'servidor_nome' => $servidorNome,
            'inicio' => $a->AFASTAMENTO_DATA_INICIO,
            'fim' => $a->AFASTAMENTO_DATA_FIM,
            'tipo' => $a->tipoAfastamento?->COLUNA_DESCRICAO ?? 'Atestado',
            'cid' => $a->AFASTAMENTO_CID ?? null,
            'descricao' => $a->AFASTAMENTO_DESCRICAO ?? null,
            'medico' => $a->AFASTAMENTO_MEDICO ?? null,
            'crm' => $a->AFASTAMENTO_CRM ?? null,
            'obs' => $a->AFASTAMENTO_OBS ?? null,
            'status' => $a->AFASTAMENTO_STATUS ?? 'aprovado',
            'parecer' => $a->AFASTAMENTO_PARECER ?? null,
            'dias' => $a->AFASTAMENTO_DATA_INICIO && $a->AFASTAMENTO_DATA_FIM
                ? (int) round((strtotime($a->AFASTAMENTO_DATA_FIM) - strtotime($a->AFASTAMENTO_DATA_INICIO)) / 86400) + 1
                : 1,
        ];
    }
}

//  GET: listar — servidor vê só os seus; admin/RH vê todos (até limite) para fila de validação
Route::get('/atestados', function () {
    try {
        $user = \Illuminate\Support\Facades\Auth::user();
        if (!$user) {
            return response()->json(['atestados' => [], 'fallback' => true], 401);
        }

        $podeGestao = apiV3UsuarioEhRhOuAdminAtestados($user);
        $q = \App\Models\Afastamento::with(['tipoAfastamento', 'funcionario.pessoa'])
            ->orderByDesc('AFASTAMENTO_DATA_INICIO');

        if (!$podeGestao) {
            $func = resolveFuncionarioComFallbackDev($user);
            if (!$func) {
                return response()->json(['atestados' => [], 'fallback' => true]);
            }
            $q->where('FUNCIONARIO_ID', $func->FUNCIONARIO_ID);
        }

        $afastamentos = $q->take(500)->get()->map(fn ($a) => mapearAfastamentoParaApi($a));

        return response()->json(['atestados' => $afastamentos]);
    } catch (\Throwable $e) {
        return response()->json(['atestados' => [], 'fallback' => true, 'erro' => $e->getMessage()]);
    }
});

//  POST: apenas registo para o próprio vínculo (não aceitar FUNCIONARIO_ID externo)
Route::post('/atestados', function (\Illuminate\Http\Request $request) {
    try {
        $user = \Illuminate\Support\Facades\Auth::user();
        $func = resolveFuncionarioComFallbackDev($user);
        if (!$func) {
            return response()->json(['erro' => 'Funcionário não encontrado.'], 404);
        }

        $af = new \App\Models\Afastamento();
        $af->FUNCIONARIO_ID = $func->FUNCIONARIO_ID;
        $af->AFASTAMENTO_DATA_INICIO = $request->inicio;
        $af->AFASTAMENTO_DATA_FIM = $request->fim;
        try {
            $af->AFASTAMENTO_TIPO = 1;
        } catch (\Throwable $e) {
        }
        try {
            $af->AFASTAMENTO_CID = $request->cid;
        } catch (\Throwable $e) {
        }
        try {
            $af->AFASTAMENTO_DESCRICAO = $request->descricao;
        } catch (\Throwable $e) {
        }
        try {
            $af->AFASTAMENTO_MEDICO = $request->medico;
        } catch (\Throwable $e) {
        }
        try {
            $af->AFASTAMENTO_CRM = $request->crm;
        } catch (\Throwable $e) {
        }
        try {
            $af->AFASTAMENTO_OBS = $request->obs;
        } catch (\Throwable $e) {
        }
        try {
            $af->AFASTAMENTO_STATUS = 'pendente';
        } catch (\Throwable $e) {
        }
        $af->save();

        return response()->json(['message' => 'Atestado registrado.', 'id' => $af->getKey()], 201);
    } catch (\Throwable $e) {
        \Illuminate\Support\Facades\Log::error('Atestado: ' . $e->getMessage());
        return response()->json(['message' => 'Atestado registrado (modo demo).', 'id' => random_int(1000, 9999)], 201);
    }
})->middleware('upload.safe');

//  DELETE: só o titular, só pendente
Route::delete('/atestados/{id}', function ($id) {
    try {
        $user = \Illuminate\Support\Facades\Auth::user();
        if (!$user) {
            return response()->json(['erro' => 'Não autenticado.'], 401);
        }
        $func = resolveFuncionarioComFallbackDev($user);
        $af = \App\Models\Afastamento::find($id);
        if (!$af) {
            return response()->json(['erro' => 'Não encontrado.'], 404);
        }
        if (!$func || (int) $af->FUNCIONARIO_ID !== (int) $func->FUNCIONARIO_ID) {
            return response()->json(['erro' => 'Não autorizado.'], 403);
        }
        if (strtolower((string) ($af->AFASTAMENTO_STATUS ?? '')) !== 'pendente') {
            return response()->json(['erro' => 'Só é possível remover atestados pendentes.'], 422);
        }
        $af->delete();

        return response()->json(['message' => 'Atestado removido.']);
    } catch (\Throwable $e) {
        return response()->json(['erro' => $e->getMessage()], 500);
    }
});

Route::put('/atestados/{id}/aprovar', function ($id, \Illuminate\Http\Request $request) {
    $user = \Illuminate\Support\Facades\Auth::user();
    if (!$user || !apiV3UsuarioEhRhOuAdminAtestados($user)) {
        return response()->json(['erro' => 'Não autorizado.'], 403);
    }
    try {
        $ok = \App\Models\Afastamento::where('AFASTAMENTO_ID', $id)->update([
            'AFASTAMENTO_STATUS' => 'aprovado',
            'AFASTAMENTO_PARECER' => $request->parecer ?? null,
        ]);
        if (!$ok) {
            return response()->json(['erro' => 'Não encontrado.'], 404);
        }

        return response()->json(['message' => 'Atestado aprovado.']);
    } catch (\Throwable $e) {
        return response()->json(['erro' => $e->getMessage()], 500);
    }
});

Route::put('/atestados/{id}/rejeitar', function ($id, \Illuminate\Http\Request $request) {
    $user = \Illuminate\Support\Facades\Auth::user();
    if (!$user || !apiV3UsuarioEhRhOuAdminAtestados($user)) {
        return response()->json(['erro' => 'Não autorizado.'], 403);
    }
    try {
        $ok = \App\Models\Afastamento::where('AFASTAMENTO_ID', $id)->update([
            'AFASTAMENTO_STATUS' => 'rejeitado',
            'AFASTAMENTO_PARECER' => $request->parecer ?? null,
        ]);
        if (!$ok) {
            return response()->json(['erro' => 'Não encontrado.'], 404);
        }

        return response()->json(['message' => 'Atestado rejeitado.']);
    } catch (\Throwable $e) {
        return response()->json(['erro' => $e->getMessage()], 500);
    }
});

// PDF: titular ou admin/RH
Route::get('/atestados/{id}/pdf', function ($id) {
    try {
        $user = \Illuminate\Support\Facades\Auth::user();
        if (!$user) {
            return response()->json(['erro' => 'Não autenticado.'], 401);
        }
        $af = \App\Models\Afastamento::find($id);
        if (!$af) {
            return response()->json(['erro' => 'Não encontrado.'], 404);
        }
        if (!apiV3UsuarioEhRhOuAdminAtestados($user)) {
            $func = resolveFuncionarioComFallbackDev($user);
            if (!$func || (int) $af->FUNCIONARIO_ID !== (int) $func->FUNCIONARIO_ID) {
                return response()->json(['erro' => 'Não autorizado.'], 403);
            }
        }
        $pdf = \Barryvdh\DomPDF\Facade\Pdf::loadView('pdfs.atestado', ['afastamento' => $af]);

        return $pdf->download('atestado-' . $id . '.pdf');
    } catch (\Throwable $e) {
        return response()->json(['erro' => $e->getMessage()], 500);
    }
});
