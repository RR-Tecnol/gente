<?php
// gerado — não editar cegamente (regen_api_v3_fachada.py)


    // Valida token e retorna dados pré-preenchidos (nome, email)
    Route::get('/autocadastro-legacy/{token}', function ($token) {
        try {
            $reg = \Illuminate\Support\Facades\DB::table('AUTOCADASTRO_TOKEN')
                ->where('TOKEN', $token)->first();

            if (!$reg)
                return response()->json(['status' => 'invalido', 'erro' => 'Token não encontrado'], 404);

            if ($reg->expira_em && now()->gt($reg->expira_em)) {
                \Illuminate\Support\Facades\DB::table('AUTOCADASTRO_TOKEN')
                    ->where('TOKEN', $token)
                    ->update(['TOKEN_STATUS' => 'expirado', 'updated_at' => now()]);
                return response()->json(['status' => 'invalido', 'erro' => 'Token expirado']);
            }

            return response()->json([
                'status' => $reg->TOKEN_STATUS,
                'nome' => $reg->TOKEN_NOME,
                'email' => $reg->TOKEN_EMAIL,
            ]);
        } catch (\Throwable $e) {
            return response()->json(['status' => 'invalido', 'erro' => $e->getMessage()], 500);
        }
    });

    // Candidato envia o formulário preenchido
    Route::post('/autocadastro-legacy/{token}', function (\Illuminate\Http\Request $request, $token) {
        try {
            $reg = \Illuminate\Support\Facades\DB::table('AUTOCADASTRO_TOKEN')
                ->where('TOKEN', $token)
                ->where('TOKEN_STATUS', 'pendente')
                ->first();

            if (!$reg)
                return response()->json(['erro' => 'Token inválido ou já utilizado'], 404);

            if ($reg->expira_em && now()->gt($reg->expira_em))
                return response()->json(['erro' => 'Token expirado'], 422);

            $dados = $request->except(['_token']);

            if (isset($dados['dependentes']) && is_string($dados['dependentes']))
                $dados['dependentes'] = json_decode($dados['dependentes'], true);

            \Illuminate\Support\Facades\DB::table('AUTOCADASTRO_TOKEN')
                ->where('TOKEN', $token)
                ->update([
                    'TOKEN_STATUS' => 'preenchido',
                    'TOKEN_DADOS' => json_encode($dados),
                    'usado_em' => now(),
                    'updated_at' => now(),
                ]);

            return response()->json(['ok' => true, 'msg' => 'Dados recebidos. Aguarde a aprovação do RH.']);
        } catch (\Throwable $e) {
            return response()->json(['erro' => $e->getMessage()], 500);
        }
    });
