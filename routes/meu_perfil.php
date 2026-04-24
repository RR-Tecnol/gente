<?php
// PERFIL FUNCIONARIO - GET/PUT /perfil
// Extraido de web.php - herda prefix api/v3 + auth do grupo principal

//  GET: dados completos do perfil próprio
Route::get('/perfil', function () {
    try {
        $user = \Illuminate\Support\Facades\Auth::user();
        $funcionario = \App\Models\Funcionario::with([
            'pessoa',
            'lotacoes.setor.unidade',
            'lotacoes.atribuicaoLotacoes.atribuicao',
            'lotacoes.vinculo',
        ])->where('USUARIO_ID', $user->USUARIO_ID)->first();

        if (
            !$funcionario &&
            !app()->isProduction() &&
            strtolower((string) ($user->USUARIO_LOGIN ?? '')) === 'admin'
        ) {
            $livre = \App\Models\Funcionario::whereNull('USUARIO_ID')->orderBy('FUNCIONARIO_ID')->first();
            if ($livre) {
                \Illuminate\Support\Facades\DB::table('FUNCIONARIO')
                    ->where('FUNCIONARIO_ID', $livre->FUNCIONARIO_ID)
                    ->update(['USUARIO_ID' => $user->USUARIO_ID]);
                $funcionario = \App\Models\Funcionario::with([
                    'pessoa',
                    'lotacoes.setor.unidade',
                    'lotacoes.atribuicaoLotacoes.atribuicao',
                    'lotacoes.vinculo',
                ])->where('FUNCIONARIO_ID', $livre->FUNCIONARIO_ID)->first();
            }
        }

        if (!$funcionario) {
            return response()->json(['erro' => 'Nenhum funcionário vinculado a este usuário.'], 404);
        }

        $lotacao = $funcionario->lotacoes
            ? $funcionario->lotacoes->firstWhere('LOTACAO_DATA_FIM', null) ?? $funcionario->lotacoes->first()
            : null;
        $contatos = [];
        try {
            $contatos = \App\Models\Contato::where('PESSOA_ID', $funcionario->pessoa?->PESSOA_ID)->get()->toArray();
        } catch (\Throwable $e) {
        }

        return response()->json([
            'funcionario' => [
                'FUNCIONARIO_ID' => $funcionario->FUNCIONARIO_ID,
                'FUNCIONARIO_MATRICULA' => $funcionario->FUNCIONARIO_MATRICULA,
                'FUNCIONARIO_DATA_INICIO' => $funcionario->FUNCIONARIO_DATA_INICIO,
                'FUNCIONARIO_DATA_FIM' => $funcionario->FUNCIONARIO_DATA_FIM,
                'pessoa' => $funcionario->pessoa ? [
                    'PESSOA_ID' => $funcionario->pessoa->PESSOA_ID,
                    'PESSOA_NOME' => $funcionario->pessoa->PESSOA_NOME,
                    'PESSOA_CPF_NUMERO' => $funcionario->pessoa->PESSOA_CPF_NUMERO,
                    'PESSOA_DATA_NASCIMENTO' => $funcionario->pessoa->PESSOA_DATA_NASCIMENTO,
                    'PESSOA_SEXO' => $funcionario->pessoa->PESSOA_SEXO,
                    'PESSOA_ESTADO_CIVIL' => $funcionario->pessoa->PESSOA_ESTADO_CIVIL,
                    'PESSOA_ESCOLARIDADE' => $funcionario->pessoa->PESSOA_ESCOLARIDADE,
                    'PESSOA_NOME_SOCIAL' => $funcionario->pessoa->PESSOA_NOME_SOCIAL ?? null,
                    'PESSOA_RG_NUMERO' => $funcionario->pessoa->PESSOA_RG_NUMERO ?? null,
                    'PESSOA_PIS_PASEP' => $funcionario->pessoa->PESSOA_PIS_PASEP ?? null,
                ] : null,
                'setor' => $lotacao?->setor?->SETOR_NOME,
                'unidade' => $lotacao?->setor?->unidade?->UNIDADE_NOME,
                'vinculo' => $lotacao?->vinculo?->VINCULO_NOME ?? null,
                'atribuicao' => $lotacao?->atribuicaoLotacoes?->first()?->atribuicao?->ATRIBUICAO_NOME ?? null,
                'contatos' => $contatos,
            ],
            'usuario' => [
                'USUARIO_ID' => $user->USUARIO_ID,
                'USUARIO_LOGIN' => $user->USUARIO_LOGIN,
                'USUARIO_EMAIL' => $user->USUARIO_EMAIL ?? null,
            ],
        ]);
    } catch (\Throwable $e) {
        \Illuminate\Support\Facades\Log::error('Perfil: ' . $e->getMessage());
        return response()->json(['erro' => $e->getMessage()], 500);
    }
});

//  PUT: atualizar dados do perfil (contato e nome social)
Route::put('/perfil', function (\Illuminate\Http\Request $request) {
    try {
        $user = \Illuminate\Support\Facades\Auth::user();
        $funcionario = \App\Models\Funcionario::with('pessoa')
            ->where('USUARIO_ID', $user->USUARIO_ID)->first();
        if (!$funcionario)
            return response()->json(['erro' => 'Funcionário não encontrado.'], 404);

        // Atualizar campos editáveis da Pessoa
        if ($funcionario->pessoa) {
            $pessoa = $funcionario->pessoa;
            $campos = ['PESSOA_NOME_SOCIAL', 'PESSOA_ESTADO_CIVIL', 'PESSOA_ESCOLARIDADE'];
            foreach ($campos as $campo) {
                if ($request->has($campo)) {
                    $pessoa->$campo = $request->$campo;
                }
            }
            $pessoa->save();
        }

        // Atualizar email do usuário
        if ($request->has('USUARIO_EMAIL')) {
            $user->USUARIO_EMAIL = $request->USUARIO_EMAIL;
            $user->save();
        }

        return response()->json(['message' => 'Perfil atualizado com sucesso.']);
    } catch (\Throwable $e) {
        return response()->json(['erro' => $e->getMessage()], 500);
    }
});

//  POST: alterar senha do usuário
Route::post('/perfil/alterar-senha', function (\Illuminate\Http\Request $request) {
    try {
        $request->validate([
            'senha_atual' => 'required',
            'nova_senha' => 'required|min:6'
        ]);

        $user = \Illuminate\Support\Facades\Auth::user();

        if (!\Illuminate\Support\Facades\Hash::check($request->senha_atual, $user->USUARIO_SENHA)) {
            return response()->json(['erro' => 'Senha atual incorreta.'], 400);
        }

        $user->USUARIO_SENHA = \Illuminate\Support\Facades\Hash::make($request->nova_senha);
        $user->USUARIO_ALTERAR_SENHA = 0;
        $user->save();

        return response()->json(['message' => 'Senha alterada com sucesso.']);
    } catch (\Throwable $e) {
        return response()->json(['erro' => $e->getMessage()], 500);
    }
});
