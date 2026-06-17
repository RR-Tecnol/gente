<?php
use Illuminate\Support\Facades\Route;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;
use Illuminate\Support\Str;

Route::get('/autocadastro/pendentes', function () {
    $pendentes = DB::table('AUTOCADASTRO_TOKEN')
        ->orderByDesc('created_at')
        ->get();
    $pendentes = $pendentes->map(function ($t) {
        if (is_string($t->TOKEN_DADOS) && $t->TOKEN_DADOS !== '') {
            $json = json_decode($t->TOKEN_DADOS, true);
            $t->TOKEN_DADOS = is_array($json) ? $json : null;
        }
        return $t;
    });
    return response()->json(['pendentes' => $pendentes]);
});

Route::post('/autocadastro/gerar-link', function (\Illuminate\Http\Request $request) {
    $token = Str::uuid();
    $validadeDias = max(1, min((int) ($request->validade_dias ?? 7), 30));
    DB::table('AUTOCADASTRO_TOKEN')->insert([
        'TOKEN'        => (string) $token,
        'TOKEN_STATUS' => 'pendente',
        'TOKEN_NOME'   => $request->nome,
        'TOKEN_EMAIL'  => $request->email,
        'expira_em'    => now()->addDays($validadeDias),
        'created_at'   => now(),
        'updated_at'   => now(),
    ]);
    return response()->json([
        'url' => url("/autocadastro/{$token}"),
        'token' => (string) $token,
        'expira_em' => now()->addDays($validadeDias)->toDateString(),
    ]);
});

Route::delete('/autocadastro/{token}', function (string $token) {
    $reg = DB::table('AUTOCADASTRO_TOKEN')->where('TOKEN', $token)->first();
    if (!$reg) {
        return response()->json(['erro' => 'Token não encontrado.'], 404);
    }
    DB::table('AUTOCADASTRO_TOKEN')->where('TOKEN', $token)->update([
        'TOKEN_STATUS' => 'revogado',
        'updated_at' => now(),
    ]);
    return response()->json(['ok' => true]);
});

Route::post('/autocadastro/{token}/aprovar', function (string $token) {
    try {
        $reg = DB::table('AUTOCADASTRO_TOKEN')
            ->where('TOKEN', $token)
            ->where('TOKEN_STATUS', 'preenchido')
            ->first();
        if (!$reg) {
            return response()->json(['erro' => 'Registro não encontrado ou já aprovado.'], 404);
        }

        $dados = is_string($reg->TOKEN_DADOS) ? json_decode($reg->TOKEN_DADOS, true) : $reg->TOKEN_DADOS;
        if (!is_array($dados)) {
            return response()->json(['erro' => 'Dados do token inválidos para aprovação.'], 422);
        }

        $limpar = static fn($v) => is_string($v) ? trim($v) : $v;
        $soDigitos = static fn($v) => preg_replace('/\D+/', '', (string) ($v ?? ''));

        $resultado = DB::transaction(function () use ($reg, $dados, $limpar, $soDigitos) {
            $nome = $limpar($dados['nome'] ?? $reg->TOKEN_NOME ?? '');
            $email = strtolower($limpar($dados['email'] ?? $reg->TOKEN_EMAIL ?? ''));
            $cpf = $soDigitos($dados['cpf'] ?? '');
            if ($nome === '' || $cpf === '') {
                throw new \RuntimeException('Nome e CPF são obrigatórios para aprovar o autocadastro.');
            }

            // 1) USUARIO
            $loginBase = $cpf !== '' ? $cpf : ($email !== '' ? $email : ('user' . Str::random(6)));
            $login = $loginBase;
            $inc = 1;
            while (DB::table('USUARIO')->where('USUARIO_LOGIN', $login)->exists()) {
                $login = $loginBase . '.' . $inc;
                $inc++;
            }

            $usuarioCols = Schema::getColumnListing('USUARIO');
            $senhaHash = $dados['senha_hash'] ?? null;
            if (!$senhaHash || !is_string($senhaHash) || strlen($senhaHash) < 20) {
                $senhaHash = bcrypt((string) ($dados['senha'] ?? 'Mudar@123'));
            }
            $payloadUsuario = [
                'USUARIO_NOME' => $nome,
                'USUARIO_LOGIN' => $login,
                'USUARIO_SENHA' => $senhaHash,
                'USUARIO_EMAIL' => $email !== '' ? $email : null,
                'USUARIO_ATIVO' => 1,
                'USUARIO_ALTERAR_SENHA' => 0,
                'created_at' => now(),
                'updated_at' => now(),
            ];
            $payloadUsuario = array_intersect_key($payloadUsuario, array_flip($usuarioCols));
            $usuarioId = DB::table('USUARIO')->insertGetId($payloadUsuario);

            // 2) PERFIL padrão funcionario/external
            if (Schema::hasTable('USUARIO_PERFIL')) {
                $upCols = Schema::getColumnListing('USUARIO_PERFIL');
                $perfilId = DB::table('PERFIL')
                    ->whereIn('PERFIL_NOME', ['Funcionario', 'Funcionário', 'Externo'])
                    ->value('PERFIL_ID') ?? 5;
                $payloadUp = [
                    'USUARIO_ID' => $usuarioId,
                    'PERFIL_ID' => $perfilId,
                    'USUARIO_PERFIL_ATIVO' => 1,
                    'created_at' => now(),
                    'updated_at' => now(),
                ];
                $payloadUp = array_intersect_key($payloadUp, array_flip($upCols));
                DB::table('USUARIO_PERFIL')->insert($payloadUp);
            }

            // 3) PESSOA
            $pCols = Schema::getColumnListing('PESSOA');
            $pis = $soDigitos($dados['pis'] ?? '');
            $payloadPessoa = [
                'PESSOA_NOME' => $nome,
                'PESSOA_NOME_SOCIAL' => $limpar($dados['nome_social'] ?? null),
                'PESSOA_CPF_NUMERO' => $cpf,
                'PESSOA_CPF' => $cpf,
                'PESSOA_DATA_NASCIMENTO' => $dados['data_nasc'] ?? null,
                'PESSOA_NASC' => $dados['data_nasc'] ?? null,
                'PESSOA_SEXO' => ($dados['sexo'] ?? null) !== '' ? (int) $dados['sexo'] : null,
                'PESSOA_RG' => $limpar($dados['rg'] ?? null),
                'PESSOA_RG_NUMERO' => $limpar($dados['rg'] ?? null),
                'PESSOA_ORG_EMISSOR' => $limpar($dados['org_emissor'] ?? null),
                'PESSOA_RG_EXPEDIDOR' => $limpar($dados['org_emissor'] ?? null),
                'PESSOA_PIS_PASEP' => $pis !== '' ? $pis : null,
                'PESSOA_PIS' => $pis !== '' ? $pis : null,
                'PESSOA_CEP' => $soDigitos($dados['cep'] ?? ''),
                'PESSOA_ENDERECO' => $limpar($dados['logradouro'] ?? null),
                'PESSOA_EMAIL' => $email !== '' ? $email : null,
                'PESSOA_TELEFONE' => $limpar($dados['telefone'] ?? null),
                'USUARIO_ID' => $usuarioId,
                'PESSOA_ATIVO' => 1,
                'PESSOA_DATA_CADASTRO' => now()->toDateString(),
                'created_at' => now(),
                'updated_at' => now(),
            ];
            $payloadPessoa = array_intersect_key($payloadPessoa, array_flip($pCols));
            $pessoaId = DB::table('PESSOA')->insertGetId($payloadPessoa);

            // 4) FUNCIONARIO
            $fCols = Schema::getColumnListing('FUNCIONARIO');
            $ano = now()->format('Y');
            $ultima = DB::table('FUNCIONARIO')
                ->where('FUNCIONARIO_MATRICULA', 'like', "{$ano}-%")
                ->orderByDesc('FUNCIONARIO_MATRICULA')
                ->value('FUNCIONARIO_MATRICULA');
            $seq = 1;
            if (is_string($ultima) && str_contains($ultima, '-')) {
                $partes = explode('-', $ultima);
                $seq = ((int) end($partes)) + 1;
            }
            $matricula = $ano . '-' . str_pad((string) $seq, 4, '0', STR_PAD_LEFT);
            $payloadFunc = [
                'PESSOA_ID' => $pessoaId,
                'USUARIO_ID' => $usuarioId,
                'FUNCIONARIO_MATRICULA' => $matricula,
                'FUNCIONARIO_DATA_INICIO' => now()->toDateString(),
                'FUNCIONARIO_REGIME_PREV' => 'RPPS',
                'FUNCIONARIO_ATIVO' => 1,
                'created_at' => now(),
                'updated_at' => now(),
            ];
            $payloadFunc = array_intersect_key($payloadFunc, array_flip($fCols));
            $funcionarioId = DB::table('FUNCIONARIO')->insertGetId($payloadFunc);

            // 5) Dependentes (se tabela existir)
            $dependentes = is_array($dados['dependentes'] ?? null) ? $dados['dependentes'] : [];
            if (!empty($dependentes) && Schema::hasTable('PESSOA_DEPENDENTE')) {
                $dCols = Schema::getColumnListing('PESSOA_DEPENDENTE');
                foreach ($dependentes as $dep) {
                    $depNome = $limpar($dep['nome'] ?? '');
                    if ($depNome === '') continue;
                    $payloadDep = [
                        'FUNCIONARIO_ID' => $funcionarioId,
                        'PESSOA_DEPENDENTE_NOME' => $depNome,
                        'PESSOA_DEPENDENTE_CPF' => $soDigitos($dep['cpf'] ?? ''),
                        'PESSOA_DEPENDENTE_NASCIMENTO' => $dep['data_nasc'] ?? null,
                        'PESSOA_DEPENDENTE_PARENTESCO' => $dep['parentesco'] ?? null,
                        'PESSOA_DEPENDENTE_DEDUCAO_IRRF' => (int) ($dep['deducao_irrf'] ?? 0),
                        'created_at' => now(),
                        'updated_at' => now(),
                    ];
                    $payloadDep = array_intersect_key($payloadDep, array_flip($dCols));
                    DB::table('PESSOA_DEPENDENTE')->insert($payloadDep);
                }
            }

            // 6) marca token aprovado e vinculado
            DB::table('AUTOCADASTRO_TOKEN')->where('TOKEN', $reg->TOKEN)->update([
                'TOKEN_STATUS' => 'aprovado',
                'FUNCIONARIO_ID' => $funcionarioId,
                'updated_at' => now(),
            ]);

            return [
                'funcionario_id' => $funcionarioId,
                'matricula' => $matricula,
                'login' => $login,
            ];
        });

        return response()->json(['ok' => true] + $resultado);
    } catch (\Throwable $e) {
        return response()->json(['erro' => $e->getMessage()], 500);
    }
});
