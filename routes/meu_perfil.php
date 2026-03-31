<?php
// PERFIL FUNCIONARIO - GET/PUT /perfil
// Extraido de web.php - herda prefix api/v3 + auth do grupo principal

//  GET: dados completos do perfil prÃ³prio
Route::get('/perfil', function () {
    try {
        $user = \Illuminate\Support\Facades\Auth::user();
        $funcionario = \App\Models\Funcionario::with([
            'pessoa',
            'lotacao.setor.unidade',
            'lotacao.atribuicaoLotacao.atribuicao',
            'lotacao.vinculo',
        ])->where('USUARIO_ID', $user->USUARIO_ID)->first();

        if (!$funcionario) {
            return response()->json(['erro' => 'Nenhum funcionÃ¡rio vinculado a este usuÃ¡rio.'], 404);
        }

        $lotacao = $funcionario->lotacao;
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
                'atribuicao' => $lotacao?->atribuicaoLotacao?->first()?->atribuicao?->ATRIBUICAO_NOME ?? null,
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
            return response()->json(['erro' => 'FuncionÃ¡rio nÃ£o encontrado.'], 404);

        // Atualizar campos editÃ¡veis da Pessoa
        if ($funcionario->pessoa) {
            $pessoa = $funcionario->pessoa;
            $campos = ['PESSOA_NOME_SOCIAL', 'PESSOA_ESTADO_CIVIL', 'PESSOA_ESCOLARIDADE'];
            foreach ($campos as $campo) {
                if ($request->has($campo)) {
                    try {
                        $pessoa->$campo = $request->$campo;
                    } catch (\Throwable $e) {
                    }
                }
            }
            $pessoa->save();
        }

        // Atualizar email do usuÃ¡rio
        if ($request->has('USUARIO_EMAIL')) {
            try {
                $user->USUARIO_EMAIL = $request->USUARIO_EMAIL;
                $user->save();
            } catch (\Throwable $e) {
            }
        }

        return response()->json(['message' => 'Perfil atualizado com sucesso.']);
    } catch (\Throwable $e) {
        return response()->json(['erro' => $e->getMessage()], 500);
    }
});
