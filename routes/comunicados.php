<?php
// COMUNICADOS - GET/POST/PUT/DELETE /comunicados
// Extraido de web.php - herda prefix api/v3 + auth do grupo principal


// Helpers de tabela
$tabelaExiste = function ($tabela) {
    // SEC-PROD-09: whitelist de tabelas permitidas — impede SQL injection via nome de tabela
    $permitidas = ['COMUNICADO', 'COMUNICADO_LEITURA', 'AGENDA_EVENTO'];
    if (!in_array($tabela, $permitidas, true)) {
        return false;
    }
    try {
        \Illuminate\Support\Facades\DB::select("SELECT TOP 1 1 FROM $tabela");
        return true;
    } catch (\Throwable $e) {
        return false;
    }
};

//  Listar comunicados
Route::get('/comunicados', function () use ($tabelaExiste) {
    $user = \Illuminate\Support\Facades\Auth::user();
    try {
        if ($tabelaExiste('COMUNICADO')) {
            $rows = \Illuminate\Support\Facades\DB::table('COMUNICADO')
                ->orderByDesc('COMUNICADO_DATA')
                ->get()
                ->map(fn($r) => [
                    'id' => $r->COMUNICADO_ID,
                    'titulo' => $r->COMUNICADO_TITULO,
                    'conteudo' => $r->COMUNICADO_CONTEUDO,
                    'preview' => mb_substr(strip_tags($r->COMUNICADO_CONTEUDO ?? ''), 0, 140) . '...',
                    'categoria' => $r->COMUNICADO_CATEGORIA ?? 'rh',
                    'prioridade' => $r->COMUNICADO_PRIORIDADE ?? 'normal',
                    'fixado' => (bool) ($r->COMUNICADO_FIXADO ?? false),
                    'data' => $r->COMUNICADO_DATA,
                    'autorNome' => $r->COMUNICADO_AUTOR ?? 'Sistema',
                    'autorSetor' => $r->COMUNICADO_SETOR ?? '',
                    'lido' => false,
                    'meu' => isset($r->USUARIO_ID) && (int) $r->USUARIO_ID === (int) $user->USUARIO_ID,
                ]);
            return response()->json(['comunicados' => $rows]);
        }
    } catch (\Throwable $e) {
    }
    // Fallback: retorna vazio (front usarÃ¡ dados mockados)
    return response()->json(['comunicados' => [], 'fallback' => true]);
});

//  Criar comunicado
Route::post('/comunicados', function (\Illuminate\Http\Request $request) use ($tabelaExiste) {
    try {
        $user = \Illuminate\Support\Facades\Auth::user();

        // Tenta buscar nome do funcionÃ¡rio
        $nome = $user->USUARIO_LOGIN ?? 'Sistema';
        try {
            $func = \App\Models\Funcionario::with('pessoa')
                ->where('USUARIO_ID', $user->USUARIO_ID)->first();
            if ($func && $func->pessoa)
                $nome = $func->pessoa->PESSOA_NOME;
        } catch (\Throwable $e) {
        }

        if ($tabelaExiste('COMUNICADO')) {
            $id = \Illuminate\Support\Facades\DB::table('COMUNICADO')->insertGetId([
                'COMUNICADO_TITULO' => $request->titulo,
                'COMUNICADO_CONTEUDO' => $request->conteudo,
                'COMUNICADO_CATEGORIA' => $request->categoria ?? 'rh',
                'COMUNICADO_PRIORIDADE' => $request->prioridade ?? 'normal',
                'COMUNICADO_FIXADO' => $request->fixado ? 1 : 0,
                'COMUNICADO_DATA' => now()->toDateString(),
                'COMUNICADO_AUTOR' => $nome,
                'COMUNICADO_SETOR' => $request->setor ?? '',
                'USUARIO_ID' => $user->USUARIO_ID,
            ]);
            return response()->json(['message' => 'Comunicado publicado!', 'id' => $id], 201);
        }
        // Se a tabela nÃ£o existe, simula sucesso
        return response()->json(['message' => 'Comunicado publicado (modo demo).', 'id' => rand(1000, 9999)], 201);
    } catch (\Throwable $e) {
        \Illuminate\Support\Facades\Log::error('Comunicado: ' . $e->getMessage());
        return response()->json(['erro' => $e->getMessage()], 500);
    }
});

//  Editar comunicado
Route::put('/comunicados/{id}', function ($id, \Illuminate\Http\Request $request) use ($tabelaExiste) {
    try {
        if ($tabelaExiste('COMUNICADO')) {
            \Illuminate\Support\Facades\DB::table('COMUNICADO')
                ->where('COMUNICADO_ID', $id)
                ->update(array_filter([
                    'COMUNICADO_TITULO' => $request->titulo,
                    'COMUNICADO_CONTEUDO' => $request->conteudo,
                    'COMUNICADO_CATEGORIA' => $request->categoria,
                    'COMUNICADO_PRIORIDADE' => $request->prioridade,
                    'COMUNICADO_FIXADO' => $request->has('fixado') ? ($request->fixado ? 1 : 0) : null,
                ], fn($v) => $v !== null));
        }
        return response()->json(['message' => 'Comunicado atualizado.']);
    } catch (\Throwable $e) {
        return response()->json(['erro' => $e->getMessage()], 500);
    }
});

//  Excluir/Arquivar comunicado
Route::delete('/comunicados/{id}', function ($id) use ($tabelaExiste) {
    try {
        if ($tabelaExiste('COMUNICADO')) {
            \Illuminate\Support\Facades\DB::table('COMUNICADO')
                ->where('COMUNICADO_ID', $id)
                ->delete();
        }
        return response()->json(['message' => 'Comunicado excluÃ­do.']);
    } catch (\Throwable $e) {
        return response()->json(['erro' => $e->getMessage()], 500);
    }
});
