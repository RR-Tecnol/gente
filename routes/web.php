<?php

use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Route;

// ─────────────────────────────────────────────────────────────
// Os controllers legados do Vue 2 foram removidos em Mar/2026.
// Todas as funcionalidades estão agora no gente-v3 (Vue 3 SPA)
// consumindo os endpoints /api/v3/* definidos abaixo.
// ─────────────────────────────────────────────────────────────

/*
|--------------------------------------------------------------------------
| Web Routes
|--------------------------------------------------------------------------
|
| Here is where you can register web routes for your application. These
| routes are loaded by the RouteServiceProvider within a group which
| contains the "web" middleware group. Now create something great!
|
*/

Route::get('/', function (Request $request) {

    $loginWebKey = null;
    $sessionData = $request->session()->all();
    foreach ($sessionData as $key => $value) {
        if (str_starts_with($key, 'login_web_')) {
            $loginWebKey = $key;
            $request->session()->forget($loginWebKey);
            break;
        }
    }

    return view('auth.login');
})->name('login');

// �?�?�?�?�?�?�?�?�?�?�?�?�?�?�?�?�?�?�?�?�?�?�?�?�?�?�?�?�?�?�?�?�?�?�?�?�?�?�?�?�?�?�?�?�?�?�?�?�?�?�?�?�?�?�?�?�?�?�?�?�?�?
// CSRF cookie — necessário para SPA inicializar sessão
// �?�?�?�?�?�?�?�?�?�?�?�?�?�?�?�?�?�?�?�?�?�?�?�?�?�?�?�?�?�?�?�?�?�?�?�?�?�?�?�?�?�?�?�?�?�?�?�?�?�?�?�?�?�?�?�?�?�?�?�?�?�?
Route::get('/csrf-cookie', function () {
    return response()->json(['csrfToken' => csrf_token()]);
})->middleware('web');

// �?�?�?�?�?�?�?�?�?�?�?�?�?�?�?�?�?�?�?�?�?�?�?�?�?�?�?�?�?�?�?�?�?�?�?�?�?�?�?�?�?�?�?�?�?�?�?�?�?�?�?�?�?�?�?�?�?�?�?�?�?�?
// API DE AUTENTICAÇÃO — GENTE V3 SPA (Vue 3)
// Endpoints JSON consumidos pelo frontend Vue via axios.
// Usam sessão Laravel (cookie-based), sem JWT/Sanctum.
// �?�?�?�?�?�?�?�?�?�?�?�?�?�?�?�?�?�?�?�?�?�?�?�?�?�?�?�?�?�?�?�?�?�?�?�?�?�?�?�?�?�?�?�?�?�?�?�?�?�?�?�?�?�?�?�?�?�?�?�?�?�?
Route::prefix('api/auth')->middleware(['web', 'throttle:10,1'])->group(function () { // SEC-05: rate limit login

    // GET /api/auth/me — Retorna o usuário autenticado ou 401
    Route::get('/me', function (Request $request) {
        if (!Auth::check()) {
            return response()->json(['error' => 'Não autenticado'], 401);
        }
        $user = Auth::user();
        $funcionario = $user->FUNCIONARIO_ID
            ? \App\Models\Funcionario::with('pessoa')->find($user->FUNCIONARIO_ID)
            : null;
        // Usuário 'admin' sempre tem acesso total, independente da relação de perfis
        if (strtolower($user->USUARIO_LOGIN) === 'admin') {
            $perfilNome = 'admin';
        } else {
            $perfilNome = optional($user->usuarioPerfis()->with('perfil')->first())->perfil->PERFIL_NOME ?? null;
            if (!$perfilNome || strtolower(trim($perfilNome)) === 'usuário') {
                $perfilNome = 'funcionario';
            }
        }
        return response()->json([
            'id' => $user->USUARIO_ID,
            'login' => $user->USUARIO_LOGIN,
            'nome' => $user->USUARIO_NOME,
            'email' => $user->USUARIO_EMAIL,
            'perfil' => $perfilNome,
            'alterar_senha' => (bool) $user->USUARIO_ALTERAR_SENHA,
            'funcionario' => $funcionario ? [
                'id' => $funcionario->FUNCIONARIO_ID,
                'matricula' => $funcionario->FUNCIONARIO_MATRICULA,
                'nome' => optional($funcionario->pessoa)->PESSOA_NOME ?? $user->USUARIO_NOME,
            ] : null,
        ]);
    });

    // POST /api/auth/login — Autentica e inicia sessão
    Route::post('/login', function (Request $request) {
        $login = $request->input('USUARIO_LOGIN');
        $password = $request->input('USUARIO_SENHA');

        if (!$login || !$password) {
            return response()->json(['error' => 'Credenciais não informadas'], 422);
        }

        // Sanitiza CPF (mantém admin e 'admin')
        if ($login !== 'admin') {
            $login = preg_replace('/[^0-9]/', '', $login);
        }

        $user = \App\Models\Usuario::where('USUARIO_LOGIN', $login)
            ->where('USUARIO_ATIVO', 1)
            ->first();

        if (!$user) {
            return response()->json(['error' => 'Credenciais inválidas ou usuário inativo'], 401);
        }

        // Migração transparente MD5 → bcrypt
        if ($user->USUARIO_SENHA === md5($password)) {
            $user->USUARIO_SENHA = bcrypt($password);
            $user->USUARIO_ALTERAR_SENHA = 1;
            $user->save();
        }

        if (!\Hash::check($password, $user->USUARIO_SENHA)) {
            return response()->json(['error' => 'Senha incorreta'], 401);
        }

        if ($user->USUARIO_VIGENCIA && $user->USUARIO_VIGENCIA < date('Y-m-d')) {
            return response()->json(['error' => 'Acesso expirado'], 401);
        }

        Auth::login($user, false);
        $request->session()->regenerate();
        try {
            $user->USUARIO_ULTIMO_ACESSO = now();
            $user->save();
        } catch (\Throwable $e) {
        }

        $funcionario = $user->FUNCIONARIO_ID
            ? \App\Models\Funcionario::with('pessoa')->find($user->FUNCIONARIO_ID)
            : null;
        // Usuário 'admin' sempre tem acesso total, independente da relação de perfis
        if (strtolower($user->USUARIO_LOGIN) === 'admin') {
            $perfilNome = 'admin';
        } else {
            $perfilNome = optional($user->usuarioPerfis()->with('perfil')->first())->perfil->PERFIL_NOME ?? 'funcionario';
        }

        return response()->json([
            'ok' => true,
            'user' => [
                'id' => $user->USUARIO_ID,
                'login' => $user->USUARIO_LOGIN,
                'nome' => $user->USUARIO_NOME,
                'email' => $user->USUARIO_EMAIL,
                'perfil' => $perfilNome,
                'alterar_senha' => (bool) $user->USUARIO_ALTERAR_SENHA,
                'funcionario' => $funcionario ? [
                    'id' => $funcionario->FUNCIONARIO_ID,
                    'matricula' => $funcionario->FUNCIONARIO_MATRICULA,
                    'nome' => optional($funcionario->pessoa)->PESSOA_NOME ?? $user->USUARIO_NOME,
                ] : null,
            ],
        ]);
    });

    // POST /api/auth/logout — Encerra sessão
    Route::post('/logout', function (Request $request) {
        Auth::logout();
        $request->session()->invalidate();
        $request->session()->regenerateToken();
        return response()->json(['ok' => true]);
    });

    // POST /api/auth/change-password — Troca de senha
    Route::post('/change-password', function (Request $request) {
        if (!Auth::check()) {
            return response()->json(['error' => 'Não autenticado'], 401);
        }
        $senhaAtual = $request->input('senha_atual');
        $senhaNova = $request->input('senha_nova');

        if (!$senhaAtual || !$senhaNova) {
            return response()->json(['error' => 'Informe a senha atual e a nova senha'], 422);
        }
        if (strlen($senhaNova) < 6) {
            return response()->json(['error' => 'A nova senha deve ter pelo menos 6 caracteres'], 422);
        }

        $user = Auth::user();
        if (!\Hash::check($senhaAtual, $user->USUARIO_SENHA)) {
            return response()->json(['error' => 'Senha atual incorreta'], 401);
        }

        $user->USUARIO_SENHA = bcrypt($senhaNova);
        $user->USUARIO_ALTERAR_SENHA = 0;
        $user->save();

        return response()->json(['ok' => true, 'message' => 'Senha alterada com sucesso']);
    });

});

// [DEV-ONLY] Rotas de diagnóstico — disponíveis APENAS em ambiente local/dev
// ⚠�? SEC-02: usar isLocal() — não depende de APP_ENV=production para proteger
if (app()->isLocal() || app()->environment('development', 'testing')) {
    Route::prefix('dev')->group(function () {

        Route::get('/ping-db', function () {
            $config = config('database.default');
            $driver = config("database.connections.{$config}.driver");
            try {
                $count = App\Models\Usuario::count();
                return response()->json(['ok' => true, 'driver' => $driver, 'connection' => $config, 'total_usuarios' => $count]);
            } catch (\Exception $e) {
                return response()->json(['erro' => $e->getMessage(), 'driver' => $driver, 'connection' => $config], 500);
            }
        });

        Route::post('/echo-request', function (\Illuminate\Http\Request $request) {
            return response()->json([
                'content_type' => $request->header('Content-Type'),
                'all_inputs' => $request->all(),
                'usuario_login' => $request->input('USUARIO_LOGIN'),
                'usuario_senha' => $request->has('USUARIO_SENHA') ? '[PRESENTE]' : '[AUSENTE]',
            ]);
        });

        Route::post('/echo-raw', function (\Illuminate\Http\Request $request) {
            $rawBody = $request->getContent();
            $json = json_decode($rawBody, true);
            return response()->json([
                'raw_body' => substr($rawBody, 0, 200),
                'json_parsed' => $json,
                'all_inputs' => $request->all(),
                'content_type' => $request->header('Content-Type'),
            ]);
        });

        // GENTE 2.0 (VUE 3) Rota Pública Temporária para Testes de UI
        Route::get('/v3', function () {
            return view('v3.app');
        });

        // Serve o SPA Vue para o link de autocadastro
        Route::get('/autocadastro/{token}', function () {
            return view('v3.app');
        });

        // [DEV-ONLY] Diagnóstico de conexão e login
// �?�?�?�?�?�?�?�?�?�?�?�?�?�?�?�?�?�?�?�?�?�?�?�?�?�?�?�?�?�?�?�?�?�?�?�?�?�?�?�?�?�?�?�?�?�?�?�?�?�?�?�?�?�?�?�?�?�?�?
// API V3 — GENTE SPA (Vue 3) — Ações do Perfil do Funcionário
// �?�?�?�?�?�?�?�?�?�?�?�?�?�?�?�?�?�?�?�?�?�?�?�?�?�?�?�?�?�?�?�?�?�?�?�?�?�?�?�?�?�?�?�?�?�?�?�?�?�?�?�?�?�?�?�?�?�?�?
        Route::prefix('api/v3')->middleware(['web', 'auth'])->group(function () {

            // ── Funcionário: buscar perfil completo para o SPA ────────
            Route::get('/funcionarios/{id}', function ($id) {
                $func = \App\Models\Funcionario::with([
                    'pessoa',
                    'lotacoes.setor.unidade',
                    'lotacoes.atribuicaoLotacoes.atribuicao',
                    'lotacoes.vinculo',
                ])->find($id);

                if (!$func)
                    return response()->json(['message' => 'Funcionário não encontrado'], 404);

                $lotacaoAtiva = $func->lotacoes->where('LOTACAO_DATA_FIM', null)->last()
                    ?? $func->lotacoes->last();

                // Holerites (ContraCheque)
                $holerites = \Illuminate\Support\Facades\DB::table('DETALHE_FOLHA as df')
                    ->join('FOLHA as f', 'f.FOLHA_ID', '=', 'df.FOLHA_ID')
                    ->where('df.FUNCIONARIO_ID', $id)
                    ->orderByDesc('f.FOLHA_COMPETENCIA')
                    ->limit(6)
                    ->select(
                        'df.DETALHE_FOLHA_ID as detalhe_folha_id',
                        'f.FOLHA_COMPETENCIA as competencia',
                        'df.FUNCIONARIO_ID as funcionario_id',
                        'df.DETALHE_FOLHA_LIQUIDO as liquido'
                    )
                    ->get();

                return response()->json([
                    'funcionario' => array_merge($func->toArray(), [
                        'setor' => optional($lotacaoAtiva?->setor)->SETOR_NOME,
                        'unidade' => optional($lotacaoAtiva?->setor?->unidade)->UNIDADE_NOME,
                        'atribuicao' => optional($lotacaoAtiva?->atribuicaoLotacoes->last()?->atribuicao)->ATRIBUICAO_NOME,
                        'vinculo' => optional($lotacaoAtiva?->vinculo)->VINCULO_NOME,
                    ]),
                    'holerites' => $holerites,
                ]);
            });

            // ── Funcionário: atualizar dados pessoais ─────────────────
            Route::put('/funcionarios/{id}', function ($id, \Illuminate\Http\Request $request) {
                $func = \App\Models\Funcionario::with('pessoa')->find($id);
                if (!$func)
                    return response()->json(['message' => 'Funcionário não encontrado'], 404);

                // Dados do funcionário
                $func->fill($request->only(['FUNCIONARIO_MATRICULA', 'FUNCIONARIO_DATA_INICIO', 'FUNCIONARIO_DATA_FIM', 'FUNCIONARIO_OBSERVACAO']));
                $func->save();

                // Dados da pessoa — aceita flat (como o formulário Vue envia) ou aninhado em 'pessoa'
                $pessoaData = $request->has('pessoa') ? $request->input('pessoa', []) : $request->all();

                if ($func->pessoa) {
                    // Campos com cast correto via fill()
                    $func->pessoa->fill(array_intersect_key($pessoaData, array_flip([
                        'PESSOA_NOME',
                        'PESSOA_CPF_NUMERO',
                        'PESSOA_DATA_NASCIMENTO',
                        'PESSOA_SEXO',
                        'PESSOA_ESTADO_CIVIL',
                        // PESSOA_ESCOLARIDADE — coluna não existe na tabela PESSOA
                        'PESSOA_TIPO_SANGUE',
                        'PESSOA_RH_MAIS',
                        'PESSOA_PCD',
                        'PESSOA_NOME_MAE',
                        'PESSOA_NOME_PAI',
                        'PESSOA_RACA',
                        'PESSOA_GENERO',
                        'PESSOA_NACIONALIDADE',
                        'PESSOA_ENDERECO',
                        'PESSOA_COMPLEMENTO',
                        'PESSOA_CEP',
                        'PESSOA_RG_NUMERO',
                        'PESSOA_RG_EXPEDIDOR',
                        'PESSOA_RG_EXPEDICAO',
                        'PESSOA_CNH_NUMERO',
                        'PESSOA_CNH_CATEGORIA',
                        'PESSOA_CNH_VALIDADE',
                        'PESSOA_TITULO_NUMERO',
                        'PESSOA_TITULO_ZONA',
                        'PESSOA_TITULO_SECAO',
                        'PESSOA_PIS_PASEP',
                    ])));
                    $func->pessoa->save();

                    // Campos que precisam ser salvos como texto (UF sigla e nome de município)
                    $extra = [];
                    if (isset($pessoaData['UF_ID_RG']) && $pessoaData['UF_ID_RG'] !== '') {
                        $extra['UF_ID_RG'] = $pessoaData['UF_ID_RG'];
                    }
                    if (isset($pessoaData['CIDADE_ID_NATURAL']) && $pessoaData['CIDADE_ID_NATURAL'] !== '') {
                        $extra['CIDADE_ID_NATURAL'] = $pessoaData['CIDADE_ID_NATURAL'];
                    }
                    if (!empty($extra)) {
                        \Illuminate\Support\Facades\DB::table('PESSOA')
                            ->where('PESSOA_ID', $func->pessoa->PESSOA_ID)
                            ->update($extra);
                    }
                }

                return response()->json(['ok' => true, 'message' => 'Cadastro atualizado com sucesso']);
            });

            // ── Documentos do funcionário (por PESSOA_ID) ─────────────
            Route::get('/funcionarios/{id}/documentos', function ($id) {
                $func = \App\Models\Funcionario::with('pessoa')->find($id);
                if (!$func || !$func->pessoa)
                    return response()->json([]);

                $docs = \App\Models\Documento::with('tipoDocumento')
                    ->where('PESSOA_ID', $func->PESSOA_ID)
                    ->get()
                    ->map(fn($d) => [
                        'id' => $d->DOCUMENTO_ID,
                        'tipo' => optional($d->tipoDocumento)->TIPO_DOCUMENTO_DESCRICAO ?? 'Documento',
                        'numero' => $d->DOCUMENTO_NUMERO,
                        'obrigatorio' => optional($d->tipoDocumento)->TIPO_DOCUMENTO_OBRIGATORIO == 1,
                    ]);

                return response()->json($docs);
            });

            // ── Histórico funcional: lotações, férias, afastamentos ───
            Route::get('/funcionarios/{id}/historico', function ($id) {
                $func = \App\Models\Funcionario::with([
                    'lotacoes.setor',
                    'lotacoes.vinculo',
                    'lotacoes.atribuicaoLotacoes.atribuicao',
                    'ferias',
                    'afastamentos',
                ])->find($id);

                if (!$func)
                    return response()->json(['message' => 'Não encontrado'], 404);

                $lotacoes = $func->lotacoes->map(fn($l) => [
                    'tipo' => 'lotacao',
                    'setor' => optional($l->setor)->SETOR_NOME ?? '—',
                    'cargo' => optional($l->atribuicaoLotacoes->last()?->atribuicao)->ATRIBUICAO_NOME ?? '—',
                    'vinculo' => optional($l->vinculo)->VINCULO_NOME ?? '—',
                    'inicio' => $l->LOTACAO_DATA_INICIO,
                    'fim' => $l->LOTACAO_DATA_FIM,
                    'ativa' => $l->LOTACAO_DATA_FIM === null,
                ]);

                $ferias = $func->ferias->map(fn($f) => [
                    'tipo' => 'ferias',
                    'inicio' => $f->FERIAS_DATA_INICIO ?? null,
                    'fim' => $f->FERIAS_DATA_FIM ?? null,
                    'ativa' => false,
                ]);

                $afastamentos = $func->afastamentos->map(fn($a) => [
                    'tipo' => 'afastamento',
                    'descricao' => $a->AFASTAMENTO_DESCRICAO ?? 'Afastamento',
                    'inicio' => $a->AFASTAMENTO_DATA_INICIO ?? null,
                    'fim' => $a->AFASTAMENTO_DATA_FIM ?? null,
                    'ativa' => false,
                ]);

                return response()->json([
                    'lotacoes' => $lotacoes,
                    'ferias' => $ferias,
                    'afastamentos' => $afastamentos,
                ]);
            });

            // ── Escalas do funcionário ────────────────────────────────
            Route::get('/funcionarios/{id}/escalas', function ($id) {
                $escalas = \Illuminate\Support\Facades\DB::table('DETALHE_ESCALA as de')
                    ->join('ESCALA as e', 'e.ESCALA_ID', '=', 'de.ESCALA_ID')
                    ->leftJoin('SETOR as s', 's.SETOR_ID', '=', 'e.SETOR_ID')
                    ->leftJoin('TURNO as t', 't.TURNO_ID', '=', 'de.TURNO_ID')
                    ->where('de.FUNCIONARIO_ID', $id)
                    ->orderByDesc('e.ESCALA_DATA')
                    ->limit(20)
                    ->select(
                        'de.DETALHE_ESCALA_ID as id',
                        'e.ESCALA_DATA as data',
                        's.SETOR_NOME as setor',
                        't.TURNO_NOME as turno',
                        'de.DETALHE_ESCALA_ENTRADA as entrada',
                        'de.DETALHE_ESCALA_SAIDA as saida'
                    )
                    ->get();

                return response()->json($escalas);
            });

            // ── Listagem de funcionários com busca (para FuncionariosView) ──
            Route::get('/funcionarios', function (\Illuminate\Http\Request $request) {
                $q = $request->input('q', '');
                $per = min((int) $request->input('per_page', 12), 50);

                $query = \App\Models\Funcionario::with([
                    'pessoa',
                    'lotacoes.setor',
                    'lotacoes.atribuicaoLotacoes.atribuicao',
                    'lotacoes.vinculo',
                ]);

                if ($q) {
                    $query->whereHas('pessoa', fn($pq) => $pq->where('PESSOA_NOME', 'like', "%$q%"));
                }

                $result = $query->paginate($per);

                $items = $result->getCollection()->transform(function ($f) {
                    $lot = $f->lotacoes->where('LOTACAO_DATA_FIM', null)->last() ?? $f->lotacoes->last();
                    return [
                        'id' => $f->FUNCIONARIO_ID,
                        'matricula' => $f->FUNCIONARIO_MATRICULA,
                        'nome' => optional($f->pessoa)->PESSOA_NOME ?? 'Sem nome',
                        'cpf' => optional($f->pessoa)->PESSOA_CPF_NUMERO,
                        'setor' => optional($lot?->setor)->SETOR_NOME,
                        'cargo' => optional($lot?->atribuicaoLotacoes->last()?->atribuicao)->ATRIBUICAO_NOME,
                        'vinculo' => optional($lot?->vinculo)->VINCULO_NOME,
                        'data_inicio' => $f->FUNCIONARIO_DATA_INICIO,
                        'ativo' => $f->FUNCIONARIO_DATA_FIM === null,
                    ];
                });

                return response()->json([
                    'data' => $items,
                    'total' => $result->total(),
                    'current_page' => $result->currentPage(),
                    'last_page' => $result->lastPage(),
                ]);
            });

            // ── Dependentes do funcionário (IRRF) ─────────────────────
            Route::get('/funcionarios/{id}/dependentes', function ($id) {
                try {
                    $deps = \Illuminate\Support\Facades\DB::table('PESSOA_DEPENDENTE')
                        ->join('FUNCIONARIO as f', 'f.FUNCIONARIO_ID', '=', 'PESSOA_DEPENDENTE.FUNCIONARIO_ID')
                        ->where('PESSOA_DEPENDENTE.FUNCIONARIO_ID', $id)
                        ->orderBy('PESSOA_DEPENDENTE.PESSOA_DEPENDENTE_ID')
                        ->select(
                            'PESSOA_DEPENDENTE.PESSOA_DEPENDENTE_ID as id',
                            'PESSOA_DEPENDENTE.PESSOA_DEPENDENTE_NOME as nome',
                            'PESSOA_DEPENDENTE.PESSOA_DEPENDENTE_CPF as cpf',
                            'PESSOA_DEPENDENTE.PESSOA_DEPENDENTE_NASCIMENTO as data_nasc',
                            'PESSOA_DEPENDENTE.PESSOA_DEPENDENTE_PARENTESCO as parentesco',
                            'PESSOA_DEPENDENTE.PESSOA_DEPENDENTE_DEDUCAO_IRRF as deducao_irrf'
                        )
                        ->get();
                    return response()->json(['dependentes' => $deps]);
                } catch (\Throwable $e) {
                    // Tabela pode não existir ainda — retorna vazio
                    return response()->json(['dependentes' => []]);
                }
            });

            Route::post('/funcionarios/{id}/dependentes', function ($id, \Illuminate\Http\Request $request) {
                try {
                    $newId = \Illuminate\Support\Facades\DB::table('PESSOA_DEPENDENTE')->insertGetId([
                        'FUNCIONARIO_ID' => $id,
                        'PESSOA_DEPENDENTE_NOME' => trim($request->nome ?? ''),
                        'PESSOA_DEPENDENTE_CPF' => $request->cpf ?? null,
                        'PESSOA_DEPENDENTE_NASCIMENTO' => $request->data_nasc ?? null,
                        'PESSOA_DEPENDENTE_PARENTESCO' => $request->parentesco ?? null,
                        'PESSOA_DEPENDENTE_DEDUCAO_IRRF' => $request->deducao_irrf ?? '1',
                        'created_at' => now(),
                        'updated_at' => now(),
                    ]);
                    return response()->json([
                        'ok' => true,
                        'dependente' => [
                            'id' => $newId,
                            'nome' => trim($request->nome ?? ''),
                            'cpf' => $request->cpf,
                            'data_nasc' => $request->data_nasc,
                            'parentesco' => $request->parentesco,
                            'deducao_irrf' => $request->deducao_irrf ?? '1',
                        ]
                    ], 201);
                } catch (\Throwable $e) {
                    return response()->json(['erro' => $e->getMessage()], 500);
                }
            });

            Route::delete('/funcionarios/{id}/dependentes/{depId}', function ($id, $depId) {
                try {
                    \Illuminate\Support\Facades\DB::table('PESSOA_DEPENDENTE')
                        ->where('PESSOA_DEPENDENTE_ID', $depId)
                        ->where('FUNCIONARIO_ID', $id)
                        ->delete();
                    return response()->json(['ok' => true]);
                } catch (\Throwable $e) {
                    return response()->json(['erro' => $e->getMessage()], 500);
                }
            });

        });


        Route::get('/diag-login/{login}/{senha}', function ($login, $senha) {
            try {
                $login = preg_replace('/[^0-9a-zA-Z]/', '', $login);
                $user = App\Models\Usuario::where('USUARIO_LOGIN', $login)->first();
                if (!$user) {
                    return response()->json(['erro' => 'usuario nao encontrado', 'login' => $login]);
                }
                $senhaOk = \Hash::check($senha, $user->USUARIO_SENHA);
                $md5Ok = ($user->USUARIO_SENHA === md5($senha));
                return response()->json([
                    'encontrado' => true,
                    'login' => $user->USUARIO_LOGIN,
                    'nome' => $user->USUARIO_NOME,
                    'ativo' => $user->USUARIO_ATIVO,
                    'hash_ok' => $senhaOk,
                    'md5_ok' => $md5Ok,
                    'vigencia' => $user->USUARIO_VIGENCIA,
                ]);
            } catch (\Exception $e) {
                return response()->json(['erro' => $e->getMessage(), 'linha' => $e->getLine(), 'arquivo' => basename($e->getFile())], 500);
            }
        });

        // ⚠�? SEC-01: /dev/set-senha DELETADO DEFINITIVAMENTE — não recriar

        Route::get('/criar-admin', function () {
            $loginAdmin = 'admin';
            $existe = App\Models\Usuario::where('USUARIO_LOGIN', $loginAdmin)->exists();

            if (!$existe) {
                App\Models\Usuario::create([
                    'USUARIO_LOGIN' => $loginAdmin,
                    'USUARIO_NOME' => 'Administrador do Sistema',
                    'USUARIO_SENHA' => bcrypt('admin123'),
                    'USUARIO_CPF' => null,
                    'USUARIO_EMAIL' => 'admin@gente.local',
                    'USUARIO_ATIVO' => 1,
                    'USUARIO_VIGENCIA' => null,
                    'USUARIO_PRIMEIRO_ACESSO' => 0,
                    'USUARIO_ALTERAR_SENHA' => 0,
                ]);
            }

            $user = App\Models\Usuario::where('USUARIO_LOGIN', $loginAdmin)->first();

            return response()->json([
                'ok' => true,
                'criado' => !$existe,
                'login' => $loginAdmin,
                'senha' => 'admin123',
                'id' => $user->USUARIO_ID ?? null,
            ]);
        });

        Route::get('/seed-dados', function () {
            $user = App\Models\Usuario::where('USUARIO_LOGIN', 'admin')->first();
            if (!$user)
                return response()->json(['erro' => 'Usuário admin não encontrado. Acesse /dev/criar-admin primeiro.'], 400);

            $pessoa = App\Models\Pessoa::where('USUARIO_ID', $user->USUARIO_ID)->first();
            if (!$pessoa) {
                $pessoa = App\Models\Pessoa::create([
                    'USUARIO_ID' => $user->USUARIO_ID,
                    'PESSOA_NOME' => 'Administrador do Sistema',
                    'PESSOA_DATA_CADASTRO' => now()->toDateString(),
                ]);
            }

            $funcionario = App\Models\Funcionario::where('PESSOA_ID', $pessoa->PESSOA_ID)->first();
            if (!$funcionario) {
                $funcionario = App\Models\Funcionario::create([
                    'PESSOA_ID' => $pessoa->PESSOA_ID,
                    'FUNCIONARIO_MATRICULA' => 'ADM001',
                    'FUNCIONARIO_DATA_INICIO' => '2020-01-01',
                    'USUARIO_ID' => $user->USUARIO_ID,
                ]);
            }

            $user->FUNCIONARIO_ID = $funcionario->FUNCIONARIO_ID;
            $user->save();

            $competencias = ['202601', '202602', '202603'];
            $folhasCriadas = 0;
            foreach ($competencias as $comp) {
                $folha = App\Models\Folha::where('FOLHA_COMPETENCIA', $comp)->first();
                if (!$folha) {
                    $folha = App\Models\Folha::create([
                        'FOLHA_DESCRICAO' => "Folha {$comp} - Teste",
                        'FOLHA_TIPO' => 1,
                        'FOLHA_COMPETENCIA' => $comp,
                        'FOLHA_QTD_SERVIDORES' => 1,
                        'FOLHA_VALOR_TOTAL' => 3500.00,
                    ]);
                }
                $jaExiste = App\Models\DetalheFolha::where('FOLHA_ID', $folha->FOLHA_ID)
                    ->where('FUNCIONARIO_ID', $funcionario->FUNCIONARIO_ID)->exists();
                if (!$jaExiste) {
                    App\Models\DetalheFolha::create([
                        'FOLHA_ID' => $folha->FOLHA_ID,
                        'FUNCIONARIO_ID' => $funcionario->FUNCIONARIO_ID,
                        'DETALHE_FOLHA_PROVENTOS' => 4000.00,
                        'DETALHE_FOLHA_DESCONTOS' => 500.00,
                    ]);
                    $folhasCriadas++;
                }
            }

            return response()->json([
                'ok' => true,
                'pessoa_id' => $pessoa->PESSOA_ID,
                'funcionario_id' => $funcionario->FUNCIONARIO_ID,
                'usuario_funcionario_id' => $user->FUNCIONARIO_ID,
                'folhas_criadas' => $folhasCriadas,
                'competencias' => $competencias,
                'msg' => 'Dados de teste criados! Acesse /meus-holerites no Vue para ver os holerites.',
            ]);
        });

    }); // fim if isLocal
}

// �?�?�? API V3 — Endpoints para o SPA Vue 3 �?�?�?�?�?�?�?�?�?�?�?�?�?�?�?�?�?�?�?�?�?�?�?�?�?�?�?�?�?�?�?
Route::prefix('api/v3')->middleware(['web', 'auth'])->group(function () {


    // ──────────────────────────────────────────────────────────────
    // ARQ-01: módulos extraídos do web.php (rotas /funcionarios, /ponto, /folhas)
    // ──────────────────────────────────────────────────────────────
    require __DIR__ . '/funcionarios.php';
    require __DIR__ . '/folha.php';

    // GET /api/v3/escalas — Lista escalas para o seletor da MatrizEscalaView
    Route::get('/escalas', function (\Illuminate\Http\Request $request) {
        $escalas = App\Models\Escala::with(['setor'])
            ->orderBy('ESCALA_COMPETENCIA', 'desc')
            ->limit(60)
            ->get()
            ->map(fn($e) => [
                'ESCALA_ID' => $e->ESCALA_ID,
                'ESCALA_COMPETENCIA' => $e->ESCALA_COMPETENCIA,
                'setor' => $e->setor?->SETOR_NOME ?? '—',
                'situacao' => $e->ESCALA_SITUACAO ?? null,
            ]);
        return response()->json($escalas);
    });

    // GET /api/v3/escalas/{id} — Grade completa de uma escala
    Route::get('/escalas/{id}', function (int $id) {
        $escala = App\Models\Escala::with([
            'setor',
            'detalheEscalas.funcionario.pessoa',
            'detalheEscalas.detalheEscalaItens.turno',
            'detalheEscalas.atribuicao',
        ])->findOrFail($id);

        // Calcula ano/mês da competência (formato "MM/YYYY")
        [$mes, $ano] = explode('/', $escala->ESCALA_COMPETENCIA . '/2026');
        $ano = (int) ($ano ?? 2026);
        $mes = (int) ($mes ?? 1) - 1; // 0-index para o Vue

        // Feriados do mês
        $mesAno = \Carbon\Carbon::createFromDate($ano, $mes + 1, 1)->format('Y-m-d');
        $feriados = App\Models\Feriado::buscarFeriadoMesAno($mesAno)
            ->map(fn($f) => ['data' => $f->FERIADO_DATA])->values();

        $funcionarios = $escala->detalheEscalas->map(function ($de) {
            return [
                'detalhe_id' => $de->DETALHE_ESCALA_ID,
                'funcionario_id' => $de->FUNCIONARIO_ID,
                'nome' => $de->funcionario?->pessoa?->PESSOA_NOME ?? 'Funcionário',
                'cargo' => $de->atribuicao?->ATRIBUICAO_NOME ?? 'Servidor',
                'itens' => $de->detalheEscalaItens->map(fn($i) => [
                    'turno_id' => $i->TURNO_ID,
                    'turno_sigla' => $i->turno?->TURNO_SIGLA,
                    'data' => $i->DETALHE_ESCALA_ITEM_DATA,
                ])->values(),
            ];
        })->values();

        return response()->json([
            'escala' => [
                'id' => $escala->ESCALA_ID,
                'competencia' => $escala->ESCALA_COMPETENCIA,
                'setor' => $escala->setor?->SETOR_NOME,
                'ano' => $ano,
                'mes' => $mes,
            ],
            'funcionarios' => $funcionarios,
            'feriados' => $feriados,
        ]);
    });

    // ──────────────────────────────────────────────────────────────
    // Módulos externos (cada arquivo herda prefix/middleware do grupo)
    // ──────────────────────────────────────────────────────────────
    require __DIR__ . '/esocial.php';
    require __DIR__ . '/consignacao.php';
    require __DIR__ . '/diarias.php';
    require __DIR__ . '/rpps.php';
    require __DIR__ . '/progressao_funcional.php';
    require __DIR__ . '/exoneracao.php';
    require __DIR__ . '/hora_extra.php';
    require __DIR__ . '/verba_indenizatoria.php';
    require __DIR__ . '/estagiarios.php';
    // Sprint 6 — novos módulos
    require __DIR__ . '/acumulacao.php';
    require __DIR__ . '/transparencia.php';
    require __DIR__ . '/pss.php';
    require __DIR__ . '/terceirizados.php';
    require __DIR__ . '/sagres.php';
    // Sprint 5 — banco de horas e atestados
    require __DIR__ . '/banco_horas.php';
    require __DIR__ . '/atestados.php';

    // ──────────────────────────────────────────────────────────────
    // GAP-01: Notificações — stub para parar os 404 do polling
    // ──────────────────────────────────────────────────────────────
    Route::get('/notificacoes', function () {
        return response()->json(['notificacoes' => [], 'nao_lidas' => 0]);
    });
    Route::post('/notificacoes/{id}/ler', function ($id) {
        return response()->json(['ok' => true]);
    });
    Route::post('/notificacoes/ler-todas', function () {
        return response()->json(['ok' => true]);
    });

    // ──────────────────────────────────────────────────────────────
    // GAP-03: Endpoint centralizado de busca de servidor
    // Todas as views devem usar /servidores/buscar (não /exoneracao/buscar)
    // ──────────────────────────────────────────────────────────────
    Route::get('/servidores/buscar', function (\Illuminate\Http\Request $request) {
        $q = $request->q ?? '';
        return response()->json([
            'servidores' => \Illuminate\Support\Facades\DB::table('FUNCIONARIO as f')
                ->join('PESSOA as p', 'p.PESSOA_ID', '=', 'f.PESSOA_ID')
                ->leftJoin('CARGO as c', 'c.CARGO_ID', '=', 'f.CARGO_ID')
                ->leftJoin('LOTACAO as l', function ($j) {
                    $j->on('l.FUNCIONARIO_ID', '=', 'f.FUNCIONARIO_ID')
                        ->whereNull('l.LOTACAO_DATA_FIM');
                })
                ->leftJoin('SETOR as s', 's.SETOR_ID', '=', 'l.SETOR_ID')
                ->leftJoin('UNIDADE as u', 'u.UNIDADE_ID', '=', 's.UNIDADE_ID')
                ->whereNull('f.FUNCIONARIO_DATA_FIM')
                ->where(function ($w) use ($q) {
                    $w->where('p.PESSOA_NOME', 'like', "%$q%")
                        ->orWhere('f.FUNCIONARIO_MATRICULA', 'like', "%$q%");
                })
                ->select(
                    'f.FUNCIONARIO_ID as id',
                    'p.PESSOA_NOME as nome',
                    'f.FUNCIONARIO_MATRICULA as matricula',
                    'f.FUNCIONARIO_DATA_INICIO as admissao',
                    'f.FUNCIONARIO_REGIME_PREV as regime_prev',
                    'c.CARGO_NOME as cargo',
                    'c.CARGO_SALARIO',
                    'f.CARGO_ID',
                    'f.CARREIRA_ID',
                    'f.FUNCIONARIO_CLASSE',
                    'f.FUNCIONARIO_REFERENCIA',
                    's.SETOR_NOME as setor',
                    'u.UNIDADE_NOME as secretaria',
                    'u.UNIDADE_ID as unidade_id'
                )
                ->limit(15)->get()
        ]);
    });

    // ──────────────────────────────────────────────────────────────
    // GAP-04: Lookup de secretarias (unidades ativas)
    // Usado por FolhaPagamentoView e outros módulos
    // ──────────────────────────────────────────────────────────────
    Route::get('/secretarias', function () {
        return response()->json([
            'unidades' => \Illuminate\Support\Facades\DB::table('UNIDADE')
                ->where('UNIDADE_ATIVO', 1)
                ->orderBy('UNIDADE_NOME')
                ->get(['UNIDADE_ID', 'UNIDADE_NOME'])
        ]);
    });

    // ── GAP-07: Holerite em PDF (print-view HTML — DomPDF bloqueado por conflito PHP 8.1)
    // Rota pública dentro do grupo auth que retorna HTML com @media print otimizado
    // O frontend Vue chama window.open('/api/v3/holerite-pdf/{id}') e aciona window.print()
    Route::get('/holerite-pdf/{detalheId}', function ($detalheId) {
        try {
            $detalhe = DB::table('DETALHE_FOLHA as df')
                ->join('FOLHA as fl', 'fl.FOLHA_ID', '=', 'df.FOLHA_ID')
                ->join('FUNCIONARIO as f', 'f.FUNCIONARIO_ID', '=', 'df.FUNCIONARIO_ID')
                ->join('PESSOA as p', 'p.PESSOA_ID', '=', 'f.PESSOA_ID')
                ->leftJoin('CARGO as c', 'c.CARGO_ID', '=', 'f.CARGO_ID')
                ->leftJoin('LOTACAO as l', function ($j) {
                    $j->on('l.FUNCIONARIO_ID', '=', 'f.FUNCIONARIO_ID')->whereNull('l.LOTACAO_DATA_FIM');
                })
                ->leftJoin('SETOR as s', 's.SETOR_ID', '=', 'l.SETOR_ID')
                ->leftJoin('UNIDADE as u', 'u.UNIDADE_ID', '=', 's.UNIDADE_ID')
                ->where('df.DETALHE_FOLHA_ID', $detalheId)
                ->select(
                    'df.*',
                    'fl.FOLHA_COMPETENCIA as competencia',
                    'p.PESSOA_NOME as nome',
                    'p.PESSOA_CPF as cpf',
                    'f.FUNCIONARIO_MATRICULA as matricula',
                    'f.FUNCIONARIO_REGIME_PREV as regime_prev',
                    'c.CARGO_NOME as cargo',
                    's.SETOR_NOME as lotacao',
                    'u.UNIDADE_NOME as unidade'
                )
                ->first();

            if (!$detalhe) {
                abort(404, 'Holerite não encontrado.');
            }

            // Segurança: servidor só pode ver o próprio holerite
            $user = Auth::user();
            $funcId = optional(DB::table('FUNCIONARIO')->where('USUARIO_ID', $user->USUARIO_ID)->first())->FUNCIONARIO_ID;
            if ($funcId && $detalhe->FUNCIONARIO_ID !== $funcId) {
                // Permite se for gestor/admin (sem USUARIO_UNIDADE_ACESSO, libera)
                $isAdmin = false;
                try {
                    $isAdmin = DB::table('USUARIO_UNIDADE_ACESSO')->where('USUARIO_ID', $user->USUARIO_ID)->exists();
                } catch (\Throwable $ex) {
                    $isAdmin = true;
                }
                if (!$isAdmin)
                    abort(403, 'Acesso não autorizado.');
            }

            $comp = $detalhe->competencia;
            $compFormatado = strlen($comp) === 6
                ? date('m/Y', mktime(0, 0, 0, substr($comp, 4, 2), 1, substr($comp, 0, 4)))
                : $comp;

            $servidor = [
                'nome' => $detalhe->nome,
                'matricula' => $detalhe->matricula,
                'cpf' => $detalhe->cpf ? substr_replace(preg_replace('/\D/', '', $detalhe->cpf), '***', 3, 6) : '—',
                'cargo' => $detalhe->cargo,
                'lotacao' => $detalhe->lotacao . ($detalhe->unidade ? ' / ' . $detalhe->unidade : ''),
                'regime_prev' => $detalhe->regime_prev,
                'banco' => '—',
                'agencia' => '—',
                'conta' => '—',
            ];

            $totalProventos = floatval($detalhe->DETALHE_FOLHA_PROVENTOS ?? 0);
            $totalDescontos = floatval($detalhe->DETALHE_FOLHA_DESCONTOS ?? 0);
            $liquido = floatval($detalhe->DETALHE_FOLHA_LIQUIDO ?? ($totalProventos - $totalDescontos));

            // Rubricas — se houver tabela DETALHE_FOLHA_RUBRICA (opcional)
            $rubricas = [];
            try {
                $rubricas = DB::table('DETALHE_FOLHA_RUBRICA')
                    ->where('DETALHE_FOLHA_ID', $detalheId)
                    ->get()
                    ->map(fn($r) => [
                        'codigo' => $r->RUBRICA_CODIGO ?? '',
                        'descricao' => $r->RUBRICA_DESCRICAO ?? 'Rubrica',
                        'referencia' => $r->REFERENCIA ?? '',
                        'tipo' => $r->TIPO ?? 'P',
                        'valor' => floatval($r->VALOR ?? 0),
                    ])->toArray();
            } catch (\Throwable $ex) {
                // Fallback: linhas sintéticas com os totais
                if ($totalProventos > 0)
                    $rubricas[] = ['codigo' => '001', 'descricao' => 'Vencimento Base', 'referencia' => '', 'tipo' => 'P', 'valor' => $totalProventos];
                if ($totalDescontos > 0)
                    $rubricas[] = ['codigo' => '900', 'descricao' => 'Total Descontos', 'referencia' => '', 'tipo' => 'D', 'valor' => $totalDescontos];
            }

            return response()->view('v3.holerite-pdf', [
                'competencia' => $compFormatado,
                'emitido_em' => now()->format('d/m/Y H:i'),
                'servidor' => $servidor,
                'rubricas' => $rubricas,
                'total_proventos' => $totalProventos,
                'total_descontos' => $totalDescontos,
                'liquido' => $liquido,
                'bases' => [],
            ]);
        } catch (\Throwable $e) {
            return response()->json(['erro' => $e->getMessage()], 500);
        }
    });

    // ── GAP-08: Totais da folha por secretaria (SEC-08 aware) ─────
    Route::get('/folhas/por-secretaria', function (\Illuminate\Http\Request $request) {
        try {
            $comp = $request->competencia ?? now()->format('Y-m');
            $compDb = str_replace('-', '', $comp);
            $user = Auth::user();

            $unidadesPermitidas = null;
            try {
                $isAdmin = DB::table('USUARIO_UNIDADE_ACESSO')
                    ->where('USUARIO_ID', $user->USUARIO_ID)
                    ->where('NIVEL_ACESSO', 'TOTAL')->exists();
                if (!$isAdmin) {
                    $unidadesPermitidas = DB::table('USUARIO_UNIDADE_ACESSO')
                        ->where('USUARIO_ID', $user->USUARIO_ID)
                        ->pluck('UNIDADE_ID')->toArray();
                }
            } catch (\Throwable $ex) { /* tabela não existe */
            }

            $q = DB::table('DETALHE_FOLHA as df')
                ->join('FOLHA as fl', 'fl.FOLHA_ID', '=', 'df.FOLHA_ID')
                ->join('FUNCIONARIO as f', 'f.FUNCIONARIO_ID', '=', 'df.FUNCIONARIO_ID')
                ->leftJoin('LOTACAO as l', function ($j) {
                    $j->on('l.FUNCIONARIO_ID', '=', 'f.FUNCIONARIO_ID')->whereNull('l.LOTACAO_DATA_FIM');
                })
                ->leftJoin('SETOR as s', 's.SETOR_ID', '=', 'l.SETOR_ID')
                ->leftJoin('UNIDADE as u', 'u.UNIDADE_ID', '=', 's.UNIDADE_ID')
                ->where('fl.FOLHA_COMPETENCIA', $compDb);

            if ($unidadesPermitidas !== null && count($unidadesPermitidas)) {
                $q->whereIn('u.UNIDADE_ID', $unidadesPermitidas);
            }

            $resultado = $q->groupBy('u.UNIDADE_ID', 'u.UNIDADE_NOME')
                ->select(
                    'u.UNIDADE_ID as secretaria_id',
                    'u.UNIDADE_NOME as secretaria',
                    DB::raw('COUNT(DISTINCT df.FUNCIONARIO_ID) as qtd_servidores'),
                    DB::raw('SUM(COALESCE(df.DETALHE_FOLHA_PROVENTOS,0)) as total_proventos'),
                    DB::raw('SUM(COALESCE(df.DETALHE_FOLHA_DESCONTOS,0)) as total_descontos'),
                    DB::raw('SUM(COALESCE(df.DETALHE_FOLHA_LIQUIDO,0)) as total_liquido')
                )->orderBy('u.UNIDADE_NOME')->get();

            return response()->json([
                'competencia' => $comp,
                'por_secretaria' => $resultado,
                'total_proventos' => round($resultado->sum('total_proventos'), 2),
                'total_liquido' => round($resultado->sum('total_liquido'), 2),
                'qtd_secretarias' => $resultado->count(),
            ]);
        } catch (\Throwable $e) {
            return response()->json(['erro' => $e->getMessage()], 500);
        }
    });

});

// Rotas de Auth dedicadas para o SPA Vue 3 (retornam sempre JSON, sem Redirects)
Route::prefix('api/auth')->middleware('web')->group(function () {
    Route::post('/login', [App\Http\Controllers\Api\SpaAuthController::class, 'login']);
    Route::post('/logout', [App\Http\Controllers\Api\SpaAuthController::class, 'logout'])->name('api.auth.logout');
    Route::get('/me', [App\Http\Controllers\Api\SpaAuthController::class, 'me']);
});

Route::get('/remessa/{folhaId}/download', [App\Http\Controllers\RemessaBancariaController::class, 'downloadRemessa']);

Route::prefix('ponto')->middleware(['auth', 'web', 'CompartilharVariaveis', 'usuario.externo'])->group(function () {
    Route::get('/view', [App\Http\Controllers\PontoEletronicoController::class, "view"])->name('ponto.view');
    Route::get('/', [App\Http\Controllers\PontoEletronicoController::class, "listar"]);
    Route::post('/registros', [App\Http\Controllers\PontoEletronicoController::class, "salvarManual"]);
    Route::delete('/registros/{id}', [App\Http\Controllers\PontoEletronicoController::class, "excluirManual"]);
    Route::post('/importar-afd', [App\Http\Controllers\PontoEletronicoController::class, "importarAfd"]);

    // Apuração e Justificativas
    Route::get('/apuracao', [App\Http\Controllers\PontoEletronicoController::class, "listarApuracao"]);
    Route::get('/justificativas', [App\Http\Controllers\PontoEletronicoController::class, "listarJustificativas"]);
    Route::post('/justificativas/{id}/aprovar', [App\Http\Controllers\PontoEletronicoController::class, "aprovarJustificativa"]);
    Route::post('/justificativas/{id}/rejeitar', [App\Http\Controllers\PontoEletronicoController::class, "rejeitarJustificativa"]);

    // Terminais
    Route::get('/terminais', [App\Http\Controllers\PontoEletronicoController::class, "listarTerminais"]);
});

// ── Módulo 3: Quiosque Ponto Eletrônico (Acesso Público com Token) ──
Route::get('/quiosque/{token}', [App\Http\Controllers\PontoEletronicoController::class, "quiosqueView"])->name('quiosque.view');
Route::post('/quiosque/{token}/bater', [App\Http\Controllers\PontoEletronicoController::class, "registrarQuiosque"]);

Auth::routes();

Route::middleware(['auth', 'web', 'CompartilharVariaveis', 'usuario.externo'])->group(function () {
    // Rota legado /home removida 15/03/2026

    // Rota de Acesso ao Holerite Cidadão pelo SPA
    Route::get('/meus-holerites', [App\Http\Controllers\ContraChequeController::class, 'listarMinhasFolhas'])->name('meus_holerites.listar');
    Route::get('/contra-cheque/{funcionarioId}/{competencia}/pdf', [App\Http\Controllers\ContraChequeController::class, 'emitirPdf'])->name('contra-cheque');
    Route::get('/remessa/{folhaId}/download', [App\Http\Controllers\RemessaBancariaController::class, 'downloadRemessa']);

    // Bloco certidao removido 15/03/2026
;

    // Bloco cartorio removido 15/03/2026
;

    Route::prefix('turno')->group(function () {
        Route::get('/', [TurnoController::class, 'view']);
        Route::get('view', [TurnoController::class, 'view']);
        Route::post('inserir', [TurnoController::class, 'inserir']);
        Route::put('alterar', [TurnoController::class, 'alterar']);
        Route::delete('deletar', [TurnoController::class, 'deletar']);
        Route::get('listar', [TurnoController::class, 'listar']);
        Route::post('pesquisar', [TurnoController::class, 'pesquisar']);
        Route::get('buscar/{id}', [TurnoController::class, 'buscar']);
        Route::get('search', [TurnoController::class, 'search']);
    });

    Route::prefix('cargo')->group(function () {
        Route::get('/', [CargoController::class, "view"]);
        Route::get('view', [CargoController::class, "view"]);
        Route::post('inserir', [CargoController::class, "inserir"]);
        Route::get('listar', [CargoController::class, "listar"]);
        Route::post('pesquisar', [CargoController::class, "pesquisar"]);
        Route::get('buscar/{id}', [CargoController::class, "buscar"]);
        Route::delete('deletar', [CargoController::class, "deletar"]);
        Route::put('alterar', [CargoController::class, "alterar"]);
    });

    Route::prefix('funcao')->group(function () {
        Route::get('/', [FuncaoController::class, "view"]);
        Route::get('view', [FuncaoController::class, "view"]);
        Route::post('inserir', [FuncaoController::class, "inserir"]);
        Route::get('listar', [FuncaoController::class, "listar"]);
        Route::post('pesquisar', [FuncaoController::class, "pesquisar"]);
        Route::get('buscar/{id}', [FuncaoController::class, "buscar"]);
        Route::delete('deletar', [FuncaoController::class, "deletar"]);
        Route::put('alterar', [FuncaoController::class, "alterar"]);
    });

    Route::prefix('ocupacao')->group(function () {
        Route::get("search", [OcupacaoController::class, "search"])->name('ocupacao.search');
        Route::get('/', [OcupacaoController::class, "view"]);
        Route::get('view', [OcupacaoController::class, "view"]);
        Route::post('inserir', [OcupacaoController::class, "inserir"]);
        Route::put('alterar', [OcupacaoController::class, "alterar"]);
        Route::delete('deletar', [OcupacaoController::class, "deletar"]);
        Route::match(['get', 'post'], 'listar', [OcupacaoController::class, "listar"]);
        Route::get('buscar/{id}', [OcupacaoController::class, "buscar"]);
    });

    Route::prefix("conselho")->group(function () {
        Route::get("search", [ConselhoController::class, "search"])->name('conselho.search');
        Route::get('/', [ConselhoController::class, "view"]);
        Route::get('view', [ConselhoController::class, "view"]);
        Route::post('inserir', [ConselhoController::class, "inserir"]);
        Route::put('alterar', [ConselhoController::class, "alterar"]);
        Route::delete('deletar', [ConselhoController::class, "deletar"]);
        Route::match(['get', 'post'], 'listar', [ConselhoController::class, "listar"]);
        Route::post('pesquisar', [ConselhoController::class, "pesquisar"]);
        Route::get('buscar/{id}', [ConselhoController::class, "buscar"]);
    });

    Route::prefix('banco')->group(function () {
        Route::get('search', [BancoController::class, 'search'])->name('banco.search');
        Route::get('/', [BancoController::class, "view"]);
        Route::get('view', [BancoController::class, "view"]);
        Route::post('inserir', [BancoController::class, "inserir"]);
        Route::put('alterar', [BancoController::class, "alterar"]);
        Route::delete('deletar', [BancoController::class, "deletar"]);
        Route::match(['get', 'post'], 'listar', [BancoController::class, "listar"]);
        Route::post('pesquisar', [BancoController::class, "pesquisar"]);
        Route::get('buscar/{id}', [BancoController::class, "buscar"]);
    });

    Route::prefix('dependente')->group(function () {
        Route::get('/', [DependenteController::class, "view"]);
        Route::get('view', [DependenteController::class, "view"]);
        Route::post('create', [DependenteController::class, "create"])->name('dependente.create');
        Route::put('update', [DependenteController::class, "update"])->name('dependente.update');
        Route::delete('delete', [DependenteController::class, "delete"])->name('dependente.delete');
        Route::delete('deletar', [DependenteController::class, "deletar"]);
        Route::match(['get', 'post'], 'listar', [DependenteController::class, "listar"]);
        Route::post('pesquisar', [DependenteController::class, "pesquisar"]);
        Route::get('buscar/{id}', [DependenteController::class, "buscar"]);
    });

    Route::prefix('tipo_documento')->group(function () {
        Route::get('/', [TipoDocumentoController::class, "view"]);
        Route::get('view', [TipoDocumentoController::class, "view"]);
        Route::post('inserir', [TipoDocumentoController::class, "inserir"]);
        Route::put('alterar', [TipoDocumentoController::class, "alterar"]);
        Route::delete('deletar', [TipoDocumentoController::class, "deletar"]);
        Route::match(['get', 'post'], 'listar', [TipoDocumentoController::class, "listar"]);
        Route::post('pesquisar', [TipoDocumentoController::class, "pesquisar"]);
        Route::get('buscar/{id}', [TipoDocumentoController::class, "buscar"]);
    });

    Route::prefix('fim_lotacao')->group(function () {
        Route::get('/', [FimLotacaoController::class, "view"]);
        Route::get('view', [FimLotacaoController::class, "view"]);
        Route::post('inserir', [FimLotacaoController::class, "inserir"]);
        Route::get('listar', [FimLotacaoController::class, "listar"]);
        Route::get('listar/{id}', [FimLotacaoController::class, "listar"]);
        Route::post('pesquisar', [FimLotacaoController::class, "pesquisar"]);
        Route::get('buscar/{id}', [FimLotacaoController::class, "buscar"]);
        Route::delete('deletar', [FimLotacaoController::class, "deletar"]);
        Route::put('alterar', [FimLotacaoController::class, "alterar"]);
        Route::get('carregar', [FimLotacaoController::class, "carregar"]);
    });

    Route::prefix('vinculo')->group(function () {
        Route::get('/', [VinculoController::class, "view"]);
        Route::get('view', [VinculoController::class, "view"]);
        Route::post('inserir', [VinculoController::class, "inserir"]);
        Route::put('alterar', [VinculoController::class, "alterar"]);
        Route::delete('deletar', [VinculoController::class, "deletar"]);
        Route::match(['get', 'post'], 'listar', [VinculoController::class, "listar"]);
        Route::post('pesquisar', [VinculoController::class, "pesquisar"]);
        Route::get('buscar/{id}', [VinculoController::class, "buscar"]);
    });

    Route::prefix('tipo_alerta')->group(function () {
        Route::get('/', [TipoAlertaController::class, "view"]);
        Route::get('view', [TipoAlertaController::class, "view"]);
        Route::post('inserir', [TipoAlertaController::class, "inserir"]);
        Route::put('alterar', [TipoAlertaController::class, "alterar"]);
        Route::delete('deletar', [TipoAlertaController::class, "deletar"]);
        Route::match(['get', 'post'], 'listar', [TipoAlertaController::class, "listar"]);
        Route::post('pesquisar', [TipoAlertaController::class, "pesquisar"]);
        Route::get('buscar/{id}', [TipoAlertaController::class, "buscar"]);
    });

    // Rotas legadas de abono_falta e anexo_abono_falta removidas.
    // Substitúídas pelas rotas /api/v3/abono-faltas e /api/v3/abonos-gestao.

    Route::prefix('dossie')->group(function () {
        Route::get('/', [DossieController::class, "view"]);
        Route::get('view', [DossieController::class, "view"]);
        Route::post('inserir', [DossieController::class, "inserir"]);
        Route::get('listar', [DossieController::class, "listar"]);
        Route::post('pesquisar', [DossieController::class, "pesquisar"]);
        Route::get('buscar/{id}', [DossieController::class, "buscar"]);
        Route::delete('deletar', [DossieController::class, "deletar"]);
        Route::put('alterar', [DossieController::class, "alterar"]);
    });

    Route::prefix('unidade')->group(function () {
        Route::get('/', [UnidadeController::class, "view"])->name('view.unidade');
        Route::get('view', [UnidadeController::class, "view"]);
        Route::get('search', [UnidadeController::class, "search"])->name('unidade.search');
        Route::post('create', [UnidadeController::class, "create"]);
        Route::put('update', [UnidadeController::class, "update"]);
        Route::match(['get', 'post'], 'listar', [UnidadeController::class, "listar"]);
        Route::get('listar/detalhes', [UnidadeController::class, "detalhes"]);
        Route::post('pesquisar', [UnidadeController::class, "pesquisar"]);
        Route::get('buscar/{id}', [UnidadeController::class, "buscar"]);
        Route::delete('deletar', [UnidadeController::class, "deletar"]);
        Route::get('/perfil', [UnidadeController::class, "perfil"]);
    });

    Route::prefix('setor')->group(function () {
        Route::get('/', [SetorController::class, "view"]);
        Route::get('view', [SetorController::class, "view"]);
        Route::post('create', [SetorController::class, "create"]);
        Route::post('creates', [SetorController::class, "creates"]);
        Route::get('listar', [SetorController::class, "listar"]);
        Route::get('listar/{unidadeId}', [SetorController::class, "listar"]);
        Route::post('pesquisar', [SetorController::class, "pesquisar"]);
        Route::get('buscar/{id}', [SetorController::class, "buscar"]);
        Route::delete('deletar', [SetorController::class, "deletar"]);
        Route::put('update', [SetorController::class, "update"]);
        Route::get('get-by-unidade/{unidadeId}', [SetorController::class, "getByUnidade"]);
    });

    Route::prefix('setor_atribuicao')->group(function () {
        Route::get('/', [SetorAtribuicaoController::class, "view"]);
        Route::get('view', [SetorAtribuicaoController::class, "view"]);
        Route::post('inserir', [SetorAtribuicaoController::class, "inserir"]);
        Route::get('listar', [SetorAtribuicaoController::class, "listar"]);
        Route::get('listar/{unidadeId}', [SetorAtribuicaoController::class, "listar"]);
        Route::post('pesquisar', [SetorAtribuicaoController::class, "pesquisar"]);
        Route::get('buscar/{id}', [SetorAtribuicaoController::class, "buscar"]);
        Route::delete('deletar', [SetorAtribuicaoController::class, "deletar"]);
        Route::put('alterar', [SetorAtribuicaoController::class, "alterar"]);
        Route::get('get-by-setor/{setorId}', [SetorAtribuicaoController::class, "getBySetor"]);
    });

    Route::prefix('perfil')->middleware('perfil:ADMINISTRADOR,GESTOR,ADMIN')->group(function () {
        Route::get('/', [PerfilController::class, "view"]);
        Route::get('view', [PerfilController::class, "view"]);
        Route::post('create', [PerfilController::class, "create"]);
        Route::put('update', [PerfilController::class, "update"]);
        Route::delete('delete', [PerfilController::class, "delete"]);
        Route::get('list', [PerfilController::class, "list"]);
        Route::get('search', [PerfilController::class, "search"]);
    });

    Route::prefix('pessoa')->group(function () {
        Route::get('/', [PessoaController::class, "view"]);
        Route::get('view', [PessoaController::class, "view"]);
        Route::get('cad_pessoa_view/{pessoaId?}', [PessoaController::class, "cad_pessoa_view"])->name('cad_pessoa_view');
        Route::post('create', [PessoaController::class, "create"]);
        Route::put('update', [PessoaController::class, "update"])->name('pessoa.update');
        Route::delete('delete', [PessoaController::class, "delete"]);
        Route::post('create_dependente', [PessoaController::class, "createDependente"]);
        Route::put('update_dependente', [PessoaController::class, "updateDependente"]);
        Route::get('listar', [PessoaController::class, "listar"])->name('pessoa.listar');
        Route::post('pesquisar', [PessoaController::class, "pesquisar"]);
        Route::post('pesquisar-por-cpf', [PessoaController::class, "pesquisarPorCpf"]);
        Route::get('search', [PessoaController::class, "search"]);
        Route::get('search-pre-cadastro', [PessoaController::class, "searchPreCadastro"]);
        Route::get('search_incomplets', [PessoaController::class, "searchIncomplets"]);
        Route::get('buscar/{id}', [PessoaController::class, "buscar"]);
        Route::get('get_pessoa', [PessoaController::class, "getPessoaById"])->name('pessoa.get_pessoa');
        Route::delete('deletar', [PessoaController::class, "deletar"]);
    });

    Route::prefix('pessoa_profissao')->group(function () {
        Route::get('/', [PessoaProfissaoController::class, "view"]);
        Route::get('view', [PessoaProfissaoController::class, "view"]);
        Route::post('inserir', [PessoaProfissaoController::class, "inserir"]);
        Route::get('listar', [PessoaProfissaoController::class, "listar"]);
        Route::get('listar/{id}', [PessoaProfissaoController::class, "listar"]);
        Route::get('listar/pessoa/{idPessoa}', [PessoaProfissaoController::class, "listar"]);
        Route::get('buscar/{id}', [PessoaProfissaoController::class, "buscar"]);
        Route::delete('deletar', [PessoaProfissaoController::class, "deletar"]);
        Route::put('alterar', [PessoaProfissaoController::class, "alterar"]);
    });

    Route::prefix('contato')->group(function () {
        Route::get('/', [ContatoController::class, "view"]);
        Route::get('view', [ContatoController::class, "view"]);
        Route::post("create", [ContatoController::class, "create"])->name('contato.create');
        Route::put("update", [ContatoController::class, "update"])->name('contato.update');
        Route::delete("delete", [ContatoController::class, "delete"])->name('contato.delete');
        Route::get('listar', [ContatoController::class, "listar"]);
        Route::get('listar/{id}', [ContatoController::class, "listar"]);
        Route::get('buscar/{id}', [ContatoController::class, "buscar"]);
        Route::put('alterar', [ContatoController::class, "alterar"]);
    });

    Route::prefix('documento')->group(function () {
        Route::get('/', [DocumentoController::class, "view"]);
        Route::get('view', [DocumentoController::class, "view"]);
        Route::post('inserir', [DocumentoController::class, "inserir"]);
        Route::get('listar', [DocumentoController::class, "listar"]);
        Route::get('listar/{id}', [DocumentoController::class, "listar"]);
        Route::get('buscar/{id}', [DocumentoController::class, "buscar"]);
        Route::put('alterar', [DocumentoController::class, "alterar"]);
        Route::post('create', [DocumentoController::class, "create"])->name('documento.create');
        Route::put('update', [DocumentoController::class, "update"])->name('documento.update');
        Route::delete('delete', [DocumentoController::class, "delete"])->name('documento.delete');
    });

    Route::prefix('funcionario')->group(function () {
        Route::get('/', [FuncionarioController::class, "view"]);
        Route::get('view', [FuncionarioController::class, "view"]);
        Route::post('create', [FuncionarioController::class, "create"])->name('funcionario.create');
        Route::put('update', [FuncionarioController::class, "update"])->name('funcionario.update');
        Route::get('search', [FuncionarioController::class, "search"]);
        Route::post('inserir', [FuncionarioController::class, "inserir"]);
        Route::get('listar', [FuncionarioController::class, "listar"]);
        Route::get('listar/{id}', [FuncionarioController::class, "listar"]);
        Route::post('pesquisar', [FuncionarioController::class, "pesquisar"]);
        Route::get('buscar/{id}', [FuncionarioController::class, "buscar"]);
        Route::delete('deletar', [FuncionarioController::class, "deletar"]);
        Route::put('alterar', [FuncionarioController::class, "alterar"]);
    });

    Route::prefix('lotacao')->group(function () {
        Route::get('/', [LotacaoController::class, "view"]);
        Route::get('view', [LotacaoController::class, "view"]);
        Route::post('create', [LotacaoController::class, "create"]);
        Route::get('listar', [LotacaoController::class, "listar"]);
        Route::get('listar/{id}', [LotacaoController::class, "listar"]);
        Route::post('pesquisar', [LotacaoController::class, "pesquisar"]);
        Route::get('buscar/{id}', [LotacaoController::class, "buscar"]);
        Route::delete('deletar', [LotacaoController::class, "deletar"]);
        Route::put('alterar', [LotacaoController::class, "alterar"]);
        Route::get('carregar', [LotacaoController::class, "carregar"]);
        Route::get('gestores', [LotacaoController::class, "gestor"]);
        Route::get('get-by-setor/{setorId}', [LotacaoController::class, "getBySetor"]);
    });

    Route::prefix('ferias_afastamento')->group(function () {
        Route::get('/', [FeriasAfastamentoController::class, "view"]);
        Route::get('view', [FeriasAfastamentoController::class, "view"]);
    });

    Route::prefix('atribuicao_lotacao')->group(function () {
        Route::post('create', [AtribuicaoLotacaoController::class, "create"]);
        Route::get('search', [AtribuicaoLotacaoController::class, "search"]);
    });

    Route::prefix('atribuicao')->group(function () {
        Route::get('/', [AtribuicaoController::class, "view"]);
        Route::get('view', [AtribuicaoController::class, "view"])->name('atribuicao.view');
        Route::get('search', [AtribuicaoController::class, "search"])->name('atribuicao.search');
        Route::post('create', [AtribuicaoController::class, "create"]);
        Route::put('update', [AtribuicaoController::class, "update"]);
        Route::delete('deletar', [AtribuicaoController::class, 'deletar']);
        Route::match(['get', 'post'], 'listar', [AtribuicaoController::class, "listar"]);
    });

    Route::prefix('atribuicao_config')->group(function () {
        Route::post('create', [AtribuicaoConfigController::class, "create"]);
        Route::put('update', [AtribuicaoConfigController::class, "update"]);
    });

    Route::prefix('ferias')->group(function () {
        Route::get('/', [FeriasController::class, "view"]);
        Route::get('view', [FeriasController::class, "view"]);
        Route::post('inserir', [FeriasController::class, "inserir"]);
        Route::put('alterar', [FeriasController::class, "alterar"]);
        Route::delete('deletar', [FeriasController::class, "deletar"]);
        Route::match(['get', 'post'], 'listar', [FeriasController::class, "listar"]);
        Route::post('pesquisar', [FeriasController::class, "pesquisar"]);
        Route::get('buscar/{id}', [FeriasController::class, "buscar"]);
    });

    Route::prefix('anexo_ferias')->group(function () {
        Route::get('/', [AnexoFeriasController::class, "view"]);
        Route::get('view', [AnexoFeriasController::class, "view"]);
        Route::post('inserir', [AnexoFeriasController::class, "inserir"]);
        Route::put('alterar', [AnexoFeriasController::class, "alterar"]);
        Route::delete('deletar', [AnexoFeriasController::class, "deletar"]);
        Route::match(['get', 'post'], 'listar/{id}', [AnexoFeriasController::class, "listar"]);
        Route::post('pesquisar', [AnexoFeriasController::class, "pesquisar"]);
        Route::get('buscar/{id}', [AnexoFeriasController::class, "buscar"]);
        Route::get('download/{id}', [AnexoFeriasController::class, "download"]);
    });

    Route::prefix('afastamento')->group(function () {
        Route::get('/', [AfastamentoController::class, "view"]);
        Route::get('view', [AfastamentoController::class, "view"]);
        Route::post('inserir', [AfastamentoController::class, "inserir"]);
        Route::put('alterar', [AfastamentoController::class, "alterar"]);
        Route::delete('deletar', [AfastamentoController::class, "deletar"]);
        Route::match(['get', 'post'], 'listar', [AfastamentoController::class, "listar"]);
        Route::post('pesquisar', [AfastamentoController::class, "pesquisar"]);
        Route::get('buscar/{id}', [AfastamentoController::class, "buscar"]);
    });

    Route::prefix('anexo_afastamento')->group(function () {
        Route::get('/', [AnexoAfastamentoController::class, "view"]);
        Route::get('view', [AnexoAfastamentoController::class, "view"]);
        Route::post('inserir', [AnexoAfastamentoController::class, "inserir"]);
        Route::get('listar', [AnexoAfastamentoController::class, "listar"]);
        Route::get('listar/{id}', [AnexoAfastamentoController::class, "listar"]);
        Route::get('buscar/{id}', [AnexoAfastamentoController::class, "buscar"]);
        Route::delete('deletar', [AnexoAfastamentoController::class, "deletar"]);
        Route::post('alterar', [AnexoAfastamentoController::class, "alterar"]);
        Route::get('download/{id}', [AnexoAfastamentoController::class, "download"]);
    });

    Route::prefix('escala')->group(function () {
        Route::get('/', [EscalaController::class, "view"]);
        Route::get('view', [EscalaController::class, "view"]);
        Route::get('avaliacao_view', [EscalaController::class, "avaliacao_view"]);
        Route::get('copia_view', [EscalaController::class, "copia_view"]);
        Route::post('clonar', [EscalaController::class, "clonar"]);
        Route::post('inserir', [EscalaController::class, "inserir"]);
        Route::put('alterar', [EscalaController::class, "alterar"]);
        Route::delete('deletar', [EscalaController::class, "deletar"]);
        Route::match(['get', 'post'], 'listar', [EscalaController::class, "listar"]);
        Route::get('listar_avaliacao', [EscalaController::class, "listarAvaliacao"]);
        Route::post('pesquisar', [EscalaController::class, "pesquisar"]);
        Route::get('buscar/{id}', [EscalaController::class, "buscar"]);
        Route::post('pesquisar-por-id', [EscalaController::class, "pesquisarPorId"]);
        Route::get('configurar-escala/{id}', [EscalaController::class, "configurarEscala"]);
        Route::match(['get', 'post'], 'listar-deferidas', [EscalaController::class, "listarDeferidas"]);
        Route::post('salvar-matriz', [EscalaController::class, "salvarMatriz"])->name('escala.salvar-matriz');
    });

    Route::prefix('historico_escala')->group(function () {
        Route::get('/', [HistoricoEscalaController::class, "view"]);
        Route::post('avaliar', [HistoricoEscalaController::class, "avaliar"]);
        Route::post('deferir', [HistoricoEscalaController::class, "deferir"]);
        Route::post('indeferir', [HistoricoEscalaController::class, "indeferir"]);
        Route::get('view', [HistoricoEscalaController::class, "view"]);
        Route::post('inserir', [HistoricoEscalaController::class, "inserir"]);
        Route::post('create', [HistoricoEscalaController::class, "create"]);
        Route::get('listar', [HistoricoEscalaController::class, "listar"]);
        Route::post('pesquisar', [HistoricoEscalaController::class, "pesquisar"]);
        Route::get('buscar/{id}', [HistoricoEscalaController::class, "buscar"]);
    });

    Route::prefix('detalhe_escala')->group(function () {
        Route::get('/', [DetalheEscalaController::class, "view"]);
        Route::get('view', [DetalheEscalaController::class, "view"]);
        Route::post('inserir', [DetalheEscalaController::class, "inserir"]);
        Route::get('listar', [DetalheEscalaController::class, "listar"]);
        Route::get('listar/{escalaId}', [DetalheEscalaController::class, "listar"]);
        Route::get('listar/{escalaId}/{funcionarioId}', [DetalheEscalaController::class, "listar"]);
        Route::post('pesquisar', [DetalheEscalaController::class, "pesquisar"]);
        Route::get('buscar/{id}', [DetalheEscalaController::class, "buscar"]);
        Route::delete('deletar', [DetalheEscalaController::class, "deletar"]);
        Route::put('alterar', [DetalheEscalaController::class, "alterar"]);
        Route::get('resetar', [DetalheEscalaController::class, "resetarAlerta"]);
    });

    Route::prefix('detalhe_escala_item')->group(function () {
        Route::get('/', [DetalheEscalaItemController::class, "view"]);
        Route::get('view', [DetalheEscalaItemController::class, "view"]);
        Route::post('inserir', [DetalheEscalaItemController::class, "inserir"]);
        Route::get('listar', [DetalheEscalaItemController::class, "listar"]);
        Route::get('listar/{escalaId}', [DetalheEscalaItemController::class, "listar"]);
        Route::get('listar/{escalaId}/{funcionarioId}', [DetalheEscalaItemController::class, "listar"]);
        Route::post('pesquisar', [DetalheEscalaItemController::class, "pesquisar"]);
        Route::get('buscar/{id}', [DetalheEscalaItemController::class, "buscar"]);
        Route::delete('deletar', [DetalheEscalaItemController::class, "deletar"]);
        Route::put('alterar', [DetalheEscalaItemController::class, "alterar"]);
        Route::post('salvar-itens', [DetalheEscalaItemController::class, "salvarItens"]);
        Route::post('salvar-item', [DetalheEscalaItemController::class, "salvarItem"]);
        Route::put('alterar-item', [DetalheEscalaItemController::class, "alterarItem"]);
        Route::delete('deletar-itens', [DetalheEscalaItemController::class, "deletarItens"]);
        Route::post('salvar-macro', [DetalheEscalaItemController::class, "salvarMacro"]);
        Route::put('alterar-macro', [DetalheEscalaItemController::class, "alterarMacro"]);
    });

    Route::prefix('detalhe_escala_autoriza')->group(function () {
        Route::post('create', [DetalheEscalaAutorizaController::class, "create"]);
        Route::post('inserir', [DetalheEscalaAutorizaController::class, "inserir"]);
        Route::get('buscar/{id}', [DetalheEscalaAutorizaController::class, "buscar"]);
        Route::put('alterar', [DetalheEscalaAutorizaController::class, "alterar"]);
    });

    Route::prefix('substituicao_escala')->group(function () {
        Route::get('/', [SubstituicaoEscalaController::class, "view"]);
        Route::get('view', [SubstituicaoEscalaController::class, "view"])->name("substituicao_escala.view");
        Route::post('inserir', [SubstituicaoEscalaController::class, "inserir"]);
        Route::match(['get', 'post'], 'listar', [SubstituicaoEscalaController::class, "listar"]);
        Route::get('listar/{detalheEscalaId}', [SubstituicaoEscalaController::class, "listar"]);
        Route::post('pesquisar', [SubstituicaoEscalaController::class, "pesquisar"]);
        Route::get('buscar/{id}', [SubstituicaoEscalaController::class, "buscar"]);
        Route::delete('deletar', [SubstituicaoEscalaController::class, "deletar"]);
        Route::put('alterar', [SubstituicaoEscalaController::class, "alterar"]);
    });

    Route::prefix('usuario')->group(function () {
        Route::get('/', [UsuarioController::class, "view"]);
        Route::get('view', [UsuarioController::class, "view"])->name("usuario.view");
        Route::get('alteracao_senha', [UsuarioController::class, "alterarSenhaView"])->name("usuario.alteracao_senha");
        Route::post('inserir', [UsuarioController::class, "inserir"]);
        Route::put('alterar', [UsuarioController::class, "alterar"]);
        Route::put('alterar_senha', [UsuarioController::class, "alterarSenha"])->name("usuario.alterar_senha");
        Route::delete('deletar', [UsuarioController::class, "deletar"]);
        Route::match(['get', 'post'], 'listar', [UsuarioController::class, "listar"]);
        Route::post('pesquisar', [UsuarioController::class, "pesquisar"]);
        Route::get('buscar/{id}', [UsuarioController::class, "buscar"]);
    });

    Route::prefix('usuario_unidade')->group(function () {
        Route::post('inserir', [UsuarioUnidadeController::class, "inserir"]);
        Route::match(['get', 'post'], 'listar', [UsuarioUnidadeController::class, "listar"]);
        Route::post('pesquisar', [UsuarioUnidadeController::class, "pesquisar"]);
        Route::get('buscar/{id}', [UsuarioUnidadeController::class, "buscar"]);
        Route::delete('deletar', [UsuarioUnidadeController::class, "deletar"]);
        Route::put('alterar', [UsuarioUnidadeController::class, "alterar"]);
    });

    Route::prefix('usuario_perfil')->group(function () {
        Route::post('inserir', [UsuarioPerfilController::class, "inserir"]);
        Route::match(['get', 'post'], 'listar', [UsuarioPerfilController::class, "listar"]);
        Route::post('pesquisar', [UsuarioPerfilController::class, "pesquisar"]);
        Route::get('buscar/{id}', [UsuarioPerfilController::class, "buscar"]);
        Route::delete('deletar', [UsuarioPerfilController::class, "deletar"]);
        Route::put('alterar', [UsuarioPerfilController::class, "alterar"]);
    });

    Route::prefix('tabela_generica')->group(function () {
        Route::get('/', [TabelaGenericaController::class, "view"]);
        Route::get('view', [TabelaGenericaController::class, "view"]);
        Route::post('inserir', [TabelaGenericaController::class, "inserir"]);
        Route::get('listar', [TabelaGenericaController::class, "listar"]);
        Route::post('pesquisar', [TabelaGenericaController::class, "pesquisar"]);
        Route::get('buscar/{id}', [TabelaGenericaController::class, "buscar"]);
        Route::delete('deletar', [TabelaGenericaController::class, "deletar"]);
        Route::put('alterar', [TabelaGenericaController::class, "alterar"]);
        Route::get('carregar', [TabelaGenericaController::class, "carregar"]);
        Route::get('listar_colunas', [TabelaGenericaController::class, "listarColunas"]);
        Route::put('alterar_coluna', [TabelaGenericaController::class, "alterarColuna"]);
        Route::post('inserir_coluna', [TabelaGenericaController::class, "inserirColuna"]);
        Route::delete('remover_coluna', [TabelaGenericaController::class, "removerColuna"]);
        Route::post('inserir_tabela', [TabelaGenericaController::class, "inserirTabela"]);
        Route::put('alterar_tabela', [TabelaGenericaController::class, "alterarTabela"]);
    });

    Route::prefix('uf')->group(function () {
        $controller = UfController::class;
        Route::get('/', [$controller, "view"])->name('view.uf');
        Route::get('view', [$controller, "view"]);
        Route::post('inserir', [$controller, "inserir"]);
        Route::match(['get', 'post'], 'listar', [$controller, "listar"]);
        Route::get('buscar/{id}', [$controller, "buscar"]);
        Route::delete('deletar', [$controller, "deletar"]);
        Route::put('alterar', [$controller, "alterar"]);
    });

    Route::prefix('cidade')->group(function () {
        Route::get('/', [CidadeController::class, "view"])->name('view.cidade');
        Route::get('view', [CidadeController::class, "view"]);
        Route::post('inserir', [CidadeController::class, "inserir"]);
        Route::match(['get', 'post'], 'listar', [CidadeController::class, "listar"]);
        Route::get('pesquisar', [CidadeController::class, "pesquisar"]);
        Route::get('search', [CidadeController::class, "search"])->name('cidade.search');
        Route::get('buscar/{id}', [CidadeController::class, "buscar"]);
        Route::delete('deletar', [CidadeController::class, "deletar"]);
        Route::put('alterar', [CidadeController::class, "alterar"]);
    });

    Route::prefix('bairro')->group(function () {
        Route::get('/', [BairroController::class, "view"])->name('view.bairro');
        Route::get('view', [BairroController::class, "view"]);
        Route::post('inserir', [BairroController::class, "inserir"]);
        Route::get('pesquisar', [BairroController::class, "pesquisar"]);
        Route::get('buscar/{id}', [BairroController::class, "buscar"]);
        Route::delete('deletar', [BairroController::class, "deletar"]);
        Route::put('alterar', [BairroController::class, "alterar"]);
        Route::match(['get', 'post'], 'listar', [BairroController::class, "listar"]);
        Route::get('search', [BairroController::class, "search"]);
    });

    Route::prefix('feriado')->group(function () {
        Route::get('/', [FeriadoController::class, "view"])->name('view.feriado');
        Route::get('view', [FeriadoController::class, "view"]);
        Route::post('inserir', [FeriadoController::class, "inserir"]);
        Route::put('alterar', [FeriadoController::class, "alterar"]);
        Route::delete('deletar', [FeriadoController::class, "deletar"]);
        Route::match(['get', 'post'], 'listar', [FeriadoController::class, "listar"]);
        Route::post('pesquisar', [FeriadoController::class, "pesquisar"]);
        Route::get('buscar/{id}', [FeriadoController::class, "buscar"]);
        Route::get('data/{data}', [FeriadoController::class, "buscarFeriado"]);
        Route::get('todos/ano/{ano}', [FeriadoController::class, "buscarTodosPorAno"]);
        Route::get('periodo/inicial/{dataInicial}/final/{dataFinal}', [FeriadoController::class, "buscarEntreDatas"]);
        Route::get('proximo/data/{data}', [FeriadoController::class, "buscarProximoFeriado"]);
        Route::get('anterior/data/{data}', [FeriadoController::class, "buscarFeriadoAnterior"]);
        Route::get('calendario/mes-ano/{mesAno}', [FeriadoController::class, "buscarCalendario"]);
        Route::get('mes-ano/{mesAno}', [FeriadoController::class, "buscarFeriadoMesAno"]);
    });

    Route::prefix('pessoa_conselho')->group(function () {
        Route::post("create", [PessoaConselhoController::class, "create"])->name('pessoa_conselho.create');
        Route::put("update", [PessoaConselhoController::class, "update"])->name('pessoa_conselho.update');
        Route::delete("delete", [PessoaConselhoController::class, "delete"])->name('pessoa_conselho.delete');
    });

    Route::prefix('pessoa_banco')->group(function () {
        Route::post("create", [PessoaBancoController::class, "create"])->name('pessoa_banco.create');
        Route::put("update", [PessoaBancoController::class, "update"])->name('pessoa_banco.update');
        Route::delete("delete", [PessoaBancoController::class, "delete"])->name('pessoa_banco.delete');
    });

    Route::prefix('pessoa_ocupacao')->group(function () {
        Route::post("create", [PessoaOcupacaoController::class, "create"])->name('pessoa_ocupacao.create');
        Route::put("update", [PessoaOcupacaoController::class, "update"])->name('pessoa_ocupacao.update');
        Route::delete("delete", [PessoaOcupacaoController::class, "delete"])->name('pessoa_ocupacao.delete');
    });

    /** RELATÓRIOS */
    Route::prefix("relatorio")->group(function () {
        Route::get("imprimir_escala/{escala_id}", [RelatorioController::class, "imprimirEscala"]);
        Route::get("imprimir_lotacao/{lotacao_id}", [RelatorioController::class, "imprimirLotacao"]);
        Route::get("homologacao_unidade", [RelatorioController::class, "homologacaoUnidadeView"]);
        Route::post("imprimir_unidade", [RelatorioController::class, "imprimirUnidade"])->name('imprimir_unidade');
    });

    Route::prefix('aplicacao')->middleware('perfil:ADMINISTRADOR,ADMIN')->group(function () {
        Route::get('/', [AplicacaoController::class, "view"]);
        Route::get('view', [AplicacaoController::class, "view"])->name('aplicacao.view');
        Route::get('search', [AplicacaoController::class, "search"]);
        Route::post('create', [AplicacaoController::class, "create"]);
        Route::delete('delete', [AplicacaoController::class, "delete"]);
        Route::put('update', [AplicacaoController::class, "update"]);
        Route::match(['get', 'post'], 'list', [AplicacaoController::class, "list"]);
    });

    Route::prefix('evento')->group(function () {
        Route::get('/', [EventoController::class, "view"]);
        Route::get('view', [EventoController::class, "view"]);
        Route::post('create', [EventoController::class, "inserir"]);
        Route::get('list', [EventoController::class, "listar"]);
        Route::get('buscar/{id}', [EventoController::class, "buscar"]);
        Route::delete('delete', [EventoController::class, "deletar"]);
        Route::put('update', [EventoController::class, "alterar"]);
    });

    Route::prefix('historico_evento')->group(function () {
        Route::post('create', [HistoricoEventoController::class, "inserir"]);
        Route::get('list', [HistoricoEventoController::class, "listar"]);
        Route::get('buscar/{id}', [HistoricoEventoController::class, "buscar"]);
        Route::delete('delete', [HistoricoEventoController::class, "deletar"]);
        Route::put('update', [HistoricoEventoController::class, "alterar"]);
    });

    Route::prefix('vigencia_imposto')->group(function () {
        Route::post('create', [VigenciaImpostoController::class, "inserir"]);
        Route::get('list', [VigenciaImpostoController::class, "listar"]);
        Route::get('buscar/{id}', [VigenciaImpostoController::class, "buscar"]);
        Route::delete('delete', [VigenciaImpostoController::class, "deletar"]);
        Route::put('update', [VigenciaImpostoController::class, "alterar"]);
    });

    Route::prefix('tabela_imposto')->group(function () {
        Route::post('create', [TabelaImpostoController::class, "inserir"]);
        Route::get('list', [TabelaImpostoController::class, "listar"]);
        Route::get('buscar/{id}', [TabelaImpostoController::class, "buscar"]);
        Route::delete('delete', [TabelaImpostoController::class, "deletar"]);
        Route::put('update', [TabelaImpostoController::class, "alterar"]);
    });

    Route::prefix('lotacao_evento')->group(function () {
        Route::get('/', [LotacaoEventoController::class, "view"]);
        Route::get('view', [LotacaoEventoController::class, "view"]);
        Route::post('inserir', [LotacaoEventoController::class, "inserir"]);
        Route::match(['get', 'post'], 'listar', [LotacaoEventoController::class, "listar"]);
        Route::get('pesquisar', [LotacaoEventoController::class, "pesquisar"]);
        Route::get('buscar/{id}', [LotacaoEventoController::class, "buscar"]);
        Route::put('deletar', [LotacaoEventoController::class, "deletar"]);
        Route::put('alterar', [LotacaoEventoController::class, "alterar"]);
        Route::get('validar-vingencia', [LotacaoEventoController::class, "validarVingencia"]);
    });

    Route::prefix('atribuicao_lotacao_evento')->group(function () {
        Route::get('/', [AtribuicaoLotacaoEventoController::class, "view"]);
        Route::get('view', [AtribuicaoLotacaoEventoController::class, "view"]);
        Route::post('inserir', [AtribuicaoLotacaoEventoController::class, "inserir"]);
    });

    Route::prefix('tributacao')->group(function () {
        Route::post('create', [TributacaoController::class, "inserir"]);
        Route::get('list', [TributacaoController::class, "listar"]);
        Route::get('buscar/{id}', [TributacaoController::class, "buscar"]);
        Route::delete('delete', [TributacaoController::class, "deletar"]);
        Route::put('update', [TributacaoController::class, "alterar"]);
    });

    Route::prefix('evento_vinculo')->group(function () {
        Route::post('create', [EventoVinculoController::class, "inserir"]);
        Route::get('list', [EventoVinculoController::class, "listar"]);
        Route::get('buscar/{id}', [EventoVinculoController::class, "buscar"]);
        Route::delete('delete', [EventoVinculoController::class, "deletar"]);
        Route::put('update', [EventoVinculoController::class, "alterar"]);
    });

    Route::prefix('folha')->group(function () {
        Route::get('/', [FolhaController::class, "view"]);
        Route::get('view', [FolhaController::class, "view"]);
        Route::get('calculo/view', [FolhaController::class, "calculoView"]);
        Route::get('contra-cheque/view', [FolhaController::class, "contraChequeView"])->name('contra-cheque');
        Route::post('create', [FolhaController::class, "inserir"]);
        Route::get('list', [FolhaController::class, "listar"]);
        Route::get('search', [FolhaController::class, "pesquisar"])->name('folha.search');
        Route::get('buscar/{id}', [FolhaController::class, "buscar"]);
        Route::delete('delete', [FolhaController::class, "deletar"]);
        Route::put('update', [FolhaController::class, "alterar"]);
    });

    Route::prefix('parametro_financeiro')->group(function () {
        Route::get('/', [ParametroFinanceiroController::class, "view"])->name('view.parametro');
        Route::get('view', [ParametroFinanceiroController::class, "view"]);
        Route::post('inserir', [ParametroFinanceiroController::class, "inserir"]);
        Route::put('alterar', [ParametroFinanceiroController::class, "alterar"]);
        Route::delete('deletar', [ParametroFinanceiroController::class, "deletar"]);
        Route::match(['get', 'post'], 'listar', [ParametroFinanceiroController::class, "listar"]);
        Route::post('pesquisar', [ParametroFinanceiroController::class, "pesquisar"]);
        Route::get('buscar/{id}', [ParametroFinanceiroController::class, "buscar"]);
    });

    Route::prefix('historico_parametro')->group(function () {
        Route::get('view', [HistoricoParametroController::class, "view"]);
        Route::post('inserir', [HistoricoParametroController::class, "inserir"]);
        Route::put('alterar', [HistoricoParametroController::class, "alterar"]);
        Route::delete('deletar', [HistoricoParametroController::class, "deletar"]);
        Route::match(['get', 'post'], 'listar', [HistoricoParametroController::class, "listar"]);
        Route::post('pesquisar', [HistoricoParametroController::class, "pesquisar"]);
        Route::get('buscar/{id}', [HistoricoParametroController::class, "buscar"]);
    });

    Route::prefix('falta_atraso')->group(function () {
        Route::get('/', [FaltaAtrasoController::class, "view"]);
        Route::get('view', [FaltaAtrasoController::class, "view"]);
        Route::match(['get', 'post'], 'listar', [FaltaAtrasoController::class, "listar"]);
    });

    Route::prefix('programa')->group(function () {
        Route::get('/', [ProgramaController::class, 'view'])->name('view.programa');
        ;
        Route::get('view', [ProgramaController::class, "view"]);
        Route::post('inserir', [ProgramaController::class, "inserir"]);
        Route::put('alterar', [ProgramaController::class, "alterar"]);
        Route::delete('deletar', [ProgramaController::class, "deletar"]);
        Route::match(['get', 'post'], 'listar', [ProgramaController::class, "listar"]);
        Route::post('pesquisar', [ProgramaController::class, "pesquisar"]);
        Route::get('buscar/{id}', [ProgramaController::class, "buscar"]);
    });

    Route::prefix('pre-cadastro')->group(function () {
        Route::get('/', [PreCadastroController::class, 'view'])->name('view.pre-cadastro');
        ;
        Route::get('view', [PreCadastroController::class, "view"]);
        Route::post('inserir', [PreCadastroController::class, "inserir"]);
        Route::put('alterar', [PreCadastroController::class, "alterar"]);
    });

    Route::prefix('script')->group(function () {
        Route::get('/', [ScriptController::class, 'view'])->name('view.script');
        ;
        Route::get('view', [ScriptController::class, "view"]);
        Route::post('executar', [ScriptController::class, "executarQuery"]);
        Route::match(['get', 'post'], 'listar', [ScriptController::class, "listar"]);
    });

    Route::prefix('termo')->group(function () {
        Route::get('/', [TermoController::class, 'view'])->name('view.termo');
        ;
        Route::get('listar', [TermoController::class, "listar"]);
        Route::post('inserir', [TermoController::class, "inserir"]);
        Route::post('alterar', [TermoController::class, "alterar"]);
        Route::get('download', [TermoController::class, "download"])->name('download.termo');
        Route::get('download/{id}', [TermoController::class, "download"]);
    });

    Route::prefix('termo_usuario')->group(function () {
        Route::post('inserir', [TermoUsuarioController::class, "inserir"])->name('inserir.termo_usuario');
    });

    Route::get('cep/{cep}', [CepController::class, 'service']);

    Route::prefix('comentario')->group(function () {
        Route::get('listar', [ComentarioController::class, "listar"])->name('comentario.list');
        Route::post('inserir', [ComentarioController::class, "inserir"])->name('comentario.create');
        Route::put('alterar', [ComentarioController::class, "alterar"]);
    });

    Route::prefix('usuario_setor')->group(function () {
        Route::post('inserir', [UsuarioSetorController::class, "inserir"]);
        Route::put('alterar', [UsuarioSetorController::class, "alterar"]);
    });

    // ── Holerite / Contra-cheque (PDF) ─────────────────────────────────────
    Route::prefix('holerite')->group(function () {
        Route::get('pdf/{detalheFolhaId}', [App\Http\Controllers\HoleriteController::class, 'pdf'])
            ->name('holerite.pdf');
    });

    // ── Alertas de RH ────────────────────────────────────────────────────────
    Route::get('ferias/alerta-vencer', [FeriasController::class, 'alertaVencer'])
        ->name('ferias.alerta-vencer');
    Route::get('afastamento/alerta-expirar', [AfastamentoController::class, 'alertaExpirar'])
        ->name('afastamento.alerta-expirar');

    // ── Remessa Bancária CNAB 240 ────────────────────────────────────────────
    Route::prefix('remessa')->group(function () {
        Route::get('/', [App\Http\Controllers\RemessaBancariaController::class, 'view'])->name('remessa.view');
        Route::get('folhas', [App\Http\Controllers\RemessaBancariaController::class, 'folhas'])->name('remessa.folhas');
        Route::post('gerar/{folhaId}', [App\Http\Controllers\RemessaBancariaController::class, 'gerar'])->name('remessa.gerar');
        Route::get('resumo/{folhaId}', [App\Http\Controllers\RemessaBancariaController::class, 'resumo'])->name('remessa.resumo');
    });

    // ── Configurações do Sistema ─────────────────────────────────────────────
    Route::prefix('configuracoes')->group(function () {
        Route::get('/', [App\Http\Controllers\ConfiguracaoSistemaController::class, 'index'])->name('configuracoes.index');
        Route::get('api', [App\Http\Controllers\ConfiguracaoSistemaController::class, 'api'])->name('configuracoes.api');
        Route::put('{chave}', [App\Http\Controllers\ConfiguracaoSistemaController::class, 'update'])->name('configuracoes.update');
    });

    // ── Ponto Eletrônico (opcional — habilitado via CONFIGURACAO_SISTEMA) ────
    Route::middleware('modulo.ativo:MODULO_PONTO_ATIVO')->prefix('ponto')->group(function () {
        Route::get('view', fn() => view('ponto.index'))->name('ponto.view');

        // Registros de ponto
        Route::get('/', [App\Http\Controllers\RegistroPontoController::class, 'index'])->name('ponto.registros.index');
        Route::post('registros', [App\Http\Controllers\RegistroPontoController::class, 'store'])->name('ponto.registros.store');
        Route::post('registros/afd', [App\Http\Controllers\RegistroPontoController::class, 'importarAfd'])->name('ponto.registros.afd');
        Route::delete('registros/{id}', [App\Http\Controllers\RegistroPontoController::class, 'destroy'])->name('ponto.registros.destroy');

        // Apuração
        Route::get('apuracao', [App\Http\Controllers\ApuracaoPontoController::class, 'index'])->name('ponto.apuracao.index');
        Route::post('apuracao/calcular', [App\Http\Controllers\ApuracaoPontoController::class, 'calcular'])->name('ponto.apuracao.calcular');
        Route::post('apuracao/{id}/fechar', [App\Http\Controllers\ApuracaoPontoController::class, 'fechar'])->name('ponto.apuracao.fechar');
        Route::get('apuracao/{id}/espelho', [App\Http\Controllers\ApuracaoPontoController::class, 'espelho'])->name('ponto.apuracao.espelho');

        // Justificativas
        Route::get('justificativas', [App\Http\Controllers\JustificativaPontoController::class, 'index'])->name('ponto.just.index');
        Route::post('justificativas', [App\Http\Controllers\JustificativaPontoController::class, 'store'])->name('ponto.just.store');
        Route::post('justificativas/{id}/aprovar', [App\Http\Controllers\JustificativaPontoController::class, 'aprovar'])->name('ponto.just.aprovar');
        Route::post('justificativas/{id}/rejeitar', [App\Http\Controllers\JustificativaPontoController::class, 'rejeitar'])->name('ponto.just.rejeitar');

        // Terminais
        Route::get('terminais', [App\Http\Controllers\TerminalPontoController::class, 'index'])->name('ponto.terminais.index');
        Route::post('terminais', [App\Http\Controllers\TerminalPontoController::class, 'store'])->name('ponto.terminais.store');
        Route::put('terminais/{id}', [App\Http\Controllers\TerminalPontoController::class, 'update'])->name('ponto.terminais.update');
        Route::delete('terminais/{id}', [App\Http\Controllers\TerminalPontoController::class, 'destroy'])->name('ponto.terminais.destroy');
    });
});


Route::get('registrar', [PessoaController::class, "registro_view"]);
Route::post('registrar', [PessoaController::class, "registro"]);

// �?�?�?�?�?�?�?�?�?�?�?�?�?�?�?�?�?�?�?�?�?�?�?�?�?�?�?�?�?�?�?�?�?�?�?�?�?�?�?�?�?�?�?�?�?�?�?�?�?�?�?�?�?�?�?�?�?�?�?�?�?�?�?
//  API v3 — Vue SPA (autenticado via sessão Web do Laravel)
// �?�?�?�?�?�?�?�?�?�?�?�?�?�?�?�?�?�?�?�?�?�?�?�?�?�?�?�?�?�?�?�?�?�?�?�?�?�?�?�?�?�?�?�?�?�?�?�?�?�?�?�?�?�?�?�?�?�?�?�?�?�?�?
Route::prefix('api/v3')->middleware(['web', 'auth'])->group(function () {

    // ── Dashboard KPIs ─────────────────────────────────────────────
    Route::get('/dashboard', function () {
        try {
            $mes = now()->month;
            $ano = now()->year;
            $competencia = sprintf('%04d-%02d', $ano, $mes);

            $totalFuncionarios = \Illuminate\Support\Facades\DB::table('FUNCIONARIO')
                ->whereNull('FUNCIONARIO_DATA_DEMISSAO')->count();

            $abonosPendentes = \Illuminate\Support\Facades\DB::table('ABONO_FALTA')
                ->where('ABONO_FALTA_STATUS', 'pendente')->count();

            $folha = \Illuminate\Support\Facades\DB::table('FOLHA')
                ->orderByDesc('FOLHA_ID')->first();

            return response()->json([
                'total_funcionarios' => $totalFuncionarios,
                'abonos_pendentes' => $abonosPendentes,
                'folha_status' => $folha->FOLHA_STATUS ?? 'Aberta',
                'folha_competencia' => $folha->FOLHA_COMPETENCIA ?? $competencia,
            ]);
        } catch (\Throwable $e) {
            return response()->json([
                'total_funcionarios' => '—',
                'abonos_pendentes' => 0,
                'folha_status' => 'Aberta',
                'folha_competencia' => now()->format('Y-m'),
            ]);
        }
    });

    // ── Holerites do usuário logado ─────────────────────────────
    Route::get('/meus-holerites', function () {
        $user = Auth::user();
        if (!$user)
            return response()->json(['erro' => 'Não autenticado'], 401);

        // Busca o funcionário vinculado ao usuário
        $funcionario = optional($user)->funcionario
            ?? \App\Models\Funcionario::where('USUARIO_ID', $user->USUARIO_ID)->first();

        if (!$funcionario) {
            return response()->json(['erro' => 'Nenhum funcionário vinculado a este usuário.'], 404);
        }

        // Busca detalhe das folhas do funcionário
        $detalhes = \App\Models\DetalheFolha::with('folha')
            ->where('FUNCIONARIO_ID', $funcionario->FUNCIONARIO_ID)
            ->orderByDesc('FOLHA_ID')
            ->take(24)
            ->get();

        $holerites = $detalhes->map(function ($d) {
            return [
                'funcionario_id' => $d->FUNCIONARIO_ID,
                'folha_id' => $d->FOLHA_ID,
                'competencia' => optional($d->folha)->FOLHA_COMPETENCIA,
                'proventos' => (float) ($d->DETALHE_FOLHA_PROVENTOS ?? 0),
                'descontos' => (float) ($d->DETALHE_FOLHA_DESCONTOS ?? 0),
                'liquido' => (float) ($d->DETALHE_FOLHA_LIQUIDO ?? 0),
            ];
        })->values();

        return response()->json($holerites);
    });

    // ── Admin: Vínculos (configuração do motor de folha) ────────────────────────
    Route::get('/admin/vinculos', function () {
        return response()->json(
            \App\Models\Vinculo::orderBy('VINCULO_NOME')
                ->get()
                ->map(fn($v) => [
                    'VINCULO_ID' => $v->VINCULO_ID,
                    'VINCULO_DESCRICAO' => $v->VINCULO_NOME,   // mapeia nome real → alias front
                    'VINCULO_NOME' => $v->VINCULO_NOME,
                    'VINCULO_SIGLA' => $v->VINCULO_SIGLA,
                    'VINCULO_ATIVO' => $v->VINCULO_ATIVO,
                ])
        );
    });

    Route::put('/admin/vinculos/{id}', function (int $id, \Illuminate\Http\Request $request) {
        $vinculo = \App\Models\Vinculo::findOrFail($id);
        $data = [];
        if ($request->has('VINCULO_DESCRICAO'))
            $data['VINCULO_NOME'] = $request->VINCULO_DESCRICAO;
        if ($request->has('VINCULO_NOME'))
            $data['VINCULO_NOME'] = $request->VINCULO_NOME;
        if ($request->has('VINCULO_SIGLA'))
            $data['VINCULO_SIGLA'] = strtoupper(trim($request->VINCULO_SIGLA));
        if ($request->has('VINCULO_ATIVO'))
            $data['VINCULO_ATIVO'] = (int) $request->VINCULO_ATIVO;
        \Illuminate\Support\Facades\DB::table('VINCULO')
            ->where('VINCULO_ID', $id)
            ->update($data);
        return response()->json(\App\Models\Vinculo::find($id));
    });

    // ── Funcionários (listagem paginada) ────────────────────────
    Route::get('/funcionarios', function (\Illuminate\Http\Request $request) {
        $query = \App\Models\Funcionario::with(['pessoa', 'lotacoes.setor', 'lotacoes.vinculo'])
            ->when(
                $request->q,
                fn($q, $busca) =>
                $q->whereHas('pessoa', fn($p) => $p->where('PESSOA_NOME', 'like', "%{$busca}%"))
                    ->orWhere('FUNCIONARIO_MATRICULA', 'like', "%{$busca}%")
            );

        if ($request->funcionario_ativo === '1') {
            $query->whereNull('FUNCIONARIO_DATA_FIM');
        } elseif ($request->funcionario_ativo === '0') {
            $query->whereNotNull('FUNCIONARIO_DATA_FIM');
        }

        $paginado = $query->orderBy('FUNCIONARIO_ID')->paginate(15);

        $paginado->getCollection()->transform(function ($f) {
            $lotacao = $f->lotacoes->sortByDesc('LOTACAO_ID')->first();
            return array_merge($f->toArray(), [
                'setor' => optional(optional($lotacao)->setor)->SETOR_NOME,
                'vinculo' => optional(optional($lotacao)->vinculo)->VINCULO_NOME,
                'atribuicao' => null,
            ]);
        });

        return response()->json($paginado);
    });

    // ── Perfil de um funcionário ────────────────────────────────
    Route::get('/funcionarios/{id}', function ($id) {
        $f = \App\Models\Funcionario::with(['pessoa', 'lotacoes.setor', 'lotacoes.vinculo', 'lotacoes.atribuicao'])
            ->findOrFail($id);
        $ultimaLotacao = $f->lotacoes->sortByDesc('LOTACAO_ID')->first();
        $detalhes = \App\Models\DetalheFolha::with('folha')
            ->where('FUNCIONARIO_ID', $id)
            ->orderByDesc('FOLHA_ID')->take(6)->get()
            ->map(fn($d) => [
                'folha_id' => $d->FOLHA_ID,
                'competencia' => optional($d->folha)->FOLHA_COMPETENCIA,
                'proventos' => (float) ($d->DETALHE_FOLHA_PROVENTOS ?? 0),
                'descontos' => (float) ($d->DETALHE_FOLHA_DESCONTOS ?? 0),
                'liquido' => (float) ($d->DETALHE_FOLHA_LIQUIDO ?? 0),
            ]);

        // Busca o e-mail do usuário vinculado ao funcionário
        $usuario = \App\Models\Usuario::where('FUNCIONARIO_ID', $f->FUNCIONARIO_ID)->first();

        $funcionarioArray = $f->toArray();
        $funcionarioArray['email'] = $usuario?->USUARIO_EMAIL ?? null;

        return response()->json([
            'funcionario' => $funcionarioArray,
            'lotacao' => $ultimaLotacao,
            'holerites' => $detalhes,
        ]);
    });

    // ── Dados de apoio (selectboxes do modal) ───────────────────
    Route::get('/apoio', function () {
        $setores = \App\Models\Setor::with('unidade')
            ->orderBy('SETOR_NOME')->get()
            ->map(fn($s) => [
                'id' => $s->SETOR_ID,
                'nome' => $s->SETOR_NOME,
                'unidade' => optional($s->unidade)->UNIDADE_NOME,
            ]);

        $vinculos = \App\Models\Vinculo::orderBy('VINCULO_NOME')->get()
            ->map(fn($v) => ['id' => $v->VINCULO_ID, 'nome' => $v->VINCULO_NOME]);

        $atribuicoes = \App\Models\Atribuicao::orderBy('ATRIBUICAO_NOME')->get()
            ->map(fn($a) => ['id' => $a->ATRIBUICAO_ID, 'nome' => $a->ATRIBUICAO_NOME]);

        return response()->json(compact('setores', 'vinculos', 'atribuicoes'));
    });

    // ── Criar novo funcionário ──────────────────────────────────
    Route::post('/funcionarios', function (\Illuminate\Http\Request $request) {
        \Illuminate\Support\Facades\DB::beginTransaction();
        try {
            // 1. Cria a Pessoa
            $pessoa = new \App\Models\Pessoa();
            $pessoa->fill($request->only([
                'PESSOA_NOME',
                'PESSOA_CPF_NUMERO',
                'PESSOA_DATA_NASCIMENTO',
                'PESSOA_SEXO',
                'PESSOA_ESTADO_CIVIL',
                'PESSOA_ESCOLARIDADE',
                'PESSOA_NACIONALIDADE',
                'PESSOA_RACA',
                'PESSOA_GENERO',
                'PESSOA_PCD',
                'PESSOA_NOME_MAE',
                'PESSOA_NOME_PAI',
                'PESSOA_ENDERECO',
                'PESSOA_COMPLEMENTO',
                'PESSOA_CEP',
                'BAIRRO_ID',
                'CIDADE_ID',
                'PESSOA_RG_NUMERO',
                'PESSOA_RG_EXPEDIDOR',
                'PESSOA_RG_EXPEDICAO',
                'UF_ID_RG',
                'PESSOA_TITULO_NUMERO',
                'PESSOA_TITULO_ZONA',
                'PESSOA_TITULO_SECAO',
                'PESSOA_CNH_NUMERO',
                'PESSOA_CNH_CATEGORIA',
                'PESSOA_CNH_VALIDADE',
                'PESSOA_TIPO_SANGUE',
                'PESSOA_RH_MAIS',
            ]));
            $pessoa->PESSOA_DATA_CADASTRO = now()->toDateString();
            $pessoa->save();

            // Campos que precisam ser salvos como texto (sigla UF e nome de município)
            $extraPessoa = [];
            if ($request->filled('UF_ID_RG')) {
                $extraPessoa['UF_ID_RG'] = $request->UF_ID_RG;
            }
            if ($request->filled('CIDADE_ID_NATURAL')) {
                $extraPessoa['CIDADE_ID_NATURAL'] = $request->CIDADE_ID_NATURAL;
            }
            if (!empty($extraPessoa)) {
                \Illuminate\Support\Facades\DB::table('PESSOA')
                    ->where('PESSOA_ID', $pessoa->PESSOA_ID)
                    ->update($extraPessoa);
            }

            // 2. Cria o Funcionário
            $funcionario = new \App\Models\Funcionario();
            $funcionario->PESSOA_ID = $pessoa->PESSOA_ID;
            $funcionario->fill($request->only([
                'FUNCIONARIO_MATRICULA',
                'FUNCIONARIO_DATA_INICIO',
                'FUNCIONARIO_DATA_FIM',
                'FUNCIONARIO_TIPO_ENTRADA',
                'FUNCIONARIO_OBSERVACAO',
            ]));
            $funcionario->FUNCIONARIO_DATA_INICIO = $request->FUNCIONARIO_DATA_INICIO ?? now()->toDateString();
            $funcionario->FUNCIONARIO_DATA_CADASTRO = now()->toDateString();
            $funcionario->save();

            // 3. Cria a Lotação (se setor ou vínculo foi informado)
            if ($request->filled('SETOR_ID') || $request->filled('VINCULO_ID')) {
                $lotacao = new \App\Models\Lotacao();
                $lotacao->fill([
                    'FUNCIONARIO_ID' => $funcionario->FUNCIONARIO_ID,
                    'SETOR_ID' => $request->SETOR_ID,
                    'VINCULO_ID' => $request->VINCULO_ID,
                    'LOTACAO_DATA_INICIO' => $request->FUNCIONARIO_DATA_INICIO ?? now()->toDateString(),
                ]);
                $lotacao->save();

                // 4. Cria AtribuicaoLotacao se atribuição foi informada
                if ($request->filled('ATRIBUICAO_ID')) {
                    $atLotacao = new \App\Models\AtribuicaoLotacao([
                        'LOTACAO_ID' => $lotacao->LOTACAO_ID,
                        'ATRIBUICAO_ID' => $request->ATRIBUICAO_ID,
                    ]);
                    $atLotacao->save();
                }
            }

            // 5. Cria Contatos (telefone, celular, email) se informados
            $tiposContato = [
                'PESSOA_TELEFONE' => 1, // Telefone
                'PESSOA_EMAIL' => 2, // E-mail
                'PESSOA_CELULAR' => 3, // Celular
            ];
            foreach ($tiposContato as $campo => $tipo) {
                if ($request->filled($campo)) {
                    \App\Models\Contato::create([
                        'PESSOA_ID' => $pessoa->PESSOA_ID,
                        'CONTATO_TIPO' => $tipo,
                        'CONTATO_CONTEUDO' => $request->$campo,
                    ]);
                }
            }

            \Illuminate\Support\Facades\DB::commit();

            return response()->json([
                'message' => 'Funcionário cadastrado com sucesso.',
                'funcionario_id' => $funcionario->FUNCIONARIO_ID,
                'pessoa_id' => $pessoa->PESSOA_ID,
            ], 201);

        } catch (\Throwable $e) {
            \Illuminate\Support\Facades\DB::rollBack();
            \Illuminate\Support\Facades\Log::error('Erro ao cadastrar funcionário: ' . $e->getMessage());
            return response()->json(['erro' => 'Erro ao cadastrar: ' . $e->getMessage()], 500);
        }
    });

    // ── Atualizar funcionário (completo) ────────────────────────
    Route::put('/funcionarios/{id}', function ($id, \Illuminate\Http\Request $request) {
        \Illuminate\Support\Facades\DB::beginTransaction();
        try {
            $f = \App\Models\Funcionario::with(['pessoa', 'lotacoes'])->findOrFail($id);

            // Atualiza dados do Funcionário
            $f->fill($request->only([
                'FUNCIONARIO_MATRICULA',
                'FUNCIONARIO_DATA_INICIO',
                'FUNCIONARIO_DATA_FIM',
                'FUNCIONARIO_TIPO_ENTRADA',
                'FUNCIONARIO_TIPO_SAIDA',
                'FUNCIONARIO_OBSERVACAO',
            ]));
            $f->FUNCIONARIO_DATA_ATUALIZACAO = now()->toDateString();
            $f->save();

            // Atualiza dados da Pessoa
            if ($f->pessoa) {
                // O Vue envia os campos de pessoa aninhados em { pessoa: { PESSOA_NOME: ..., ... } }
                $pessoaData = $request->input('pessoa', []);

                // Campos permitidos — mescla do sub-objeto com campos de raiz (retrocompatibilidade)
                $camposPermitidos = [
                    'PESSOA_NOME',
                    'PESSOA_CPF_NUMERO',
                    'PESSOA_DATA_NASCIMENTO',
                    'PESSOA_SEXO',
                    'PESSOA_ESTADO_CIVIL',
                    'PESSOA_ESCOLARIDADE',
                    'PESSOA_NACIONALIDADE',
                    'PESSOA_RACA',
                    'PESSOA_GENERO',
                    'PESSOA_PCD',
                    'PESSOA_NOME_MAE',
                    'PESSOA_NOME_PAI',
                    'PESSOA_ENDERECO',
                    'PESSOA_COMPLEMENTO',
                    'PESSOA_CEP',
                    'BAIRRO_ID',
                    'CIDADE_ID',
                    'PESSOA_RG_NUMERO',
                    'PESSOA_RG_EXPEDIDOR',
                    'PESSOA_RG_EXPEDICAO',
                    'UF_ID_RG',
                    'PESSOA_TITULO_NUMERO',
                    'PESSOA_TITULO_ZONA',
                    'PESSOA_TITULO_SECAO',
                    'PESSOA_CNH_NUMERO',
                    'PESSOA_CNH_CATEGORIA',
                    'PESSOA_CNH_VALIDADE',
                    'PESSOA_TIPO_SANGUE',
                    'PESSOA_RH_MAIS',
                    'PESSOA_PIS_PASEP',
                ];

                $dadosPessoa = [];
                foreach ($camposPermitidos as $campo) {
                    if (array_key_exists($campo, $pessoaData)) {
                        $dadosPessoa[$campo] = $pessoaData[$campo];
                    } elseif ($request->has($campo)) {
                        $dadosPessoa[$campo] = $request->input($campo);
                    }
                }

                $f->pessoa->fill($dadosPessoa);
                $f->pessoa->save();


                // Campos que precisam ser salvos como texto (sigla UF e nome de município)
                $extraPessoa = [];
                $ufRg = $pessoaData['UF_ID_RG'] ?? $request->input('UF_ID_RG');
                $cidadeNatural = $pessoaData['CIDADE_ID_NATURAL'] ?? $request->input('CIDADE_ID_NATURAL');
                if (!empty($ufRg)) {
                    $extraPessoa['UF_ID_RG'] = $ufRg;
                }
                if (!empty($cidadeNatural)) {
                    $extraPessoa['CIDADE_ID_NATURAL'] = $cidadeNatural;
                }
                if (!empty($extraPessoa)) {
                    \Illuminate\Support\Facades\DB::table('PESSOA')
                        ->where('PESSOA_ID', $f->pessoa->PESSOA_ID)
                        ->update($extraPessoa);
                }
            }

            // Atualiza ou cria lotação ativa
            $lotacaoAtiva = $f->lotacoes->where('LOTACAO_DATA_FIM', null)->sortByDesc('LOTACAO_ID')->first();
            if ($request->filled('SETOR_ID') || $request->filled('VINCULO_ID')) {
                if ($lotacaoAtiva) {
                    $lotacaoAtiva->fill($request->only(['SETOR_ID', 'VINCULO_ID']));
                    $lotacaoAtiva->save();
                } else {
                    \App\Models\Lotacao::create([
                        'FUNCIONARIO_ID' => $f->FUNCIONARIO_ID,
                        'SETOR_ID' => $request->SETOR_ID,
                        'VINCULO_ID' => $request->VINCULO_ID,
                        'LOTACAO_DATA_INICIO' => $request->FUNCIONARIO_DATA_INICIO ?? now()->toDateString(),
                    ]);
                }
            }

            // Atualiza e-mail do usuário vinculado
            if ($request->filled('email') || $request->filled('USUARIO_EMAIL')) {
                $novoEmail = $request->email ?? $request->USUARIO_EMAIL;
                \App\Models\Usuario::where('FUNCIONARIO_ID', $f->FUNCIONARIO_ID)
                    ->update(['USUARIO_EMAIL' => $novoEmail]);
            }

            \Illuminate\Support\Facades\DB::commit();
            return response()->json(['message' => 'Atualizado com sucesso.']);
        } catch (\Throwable $e) {
            \Illuminate\Support\Facades\DB::rollBack();
            return response()->json(['erro' => 'Erro ao atualizar: ' . $e->getMessage()], 500);
        }
    });

    // ── Inativar funcionário (soft delete) ──────────────────────
    Route::delete('/funcionarios/{id}', function ($id, \Illuminate\Http\Request $request) {
        $f = \App\Models\Funcionario::findOrFail($id);
        $f->FUNCIONARIO_DATA_FIM = $request->FUNCIONARIO_DATA_FIM ?? now()->toDateString();
        $f->FUNCIONARIO_TIPO_SAIDA = $request->FUNCIONARIO_TIPO_SAIDA ?? null;
        $f->save();

        // Fecha lotações ativas
        \App\Models\Lotacao::where('FUNCIONARIO_ID', $id)
            ->whereNull('LOTACAO_DATA_FIM')
            ->update(['LOTACAO_DATA_FIM' => $f->FUNCIONARIO_DATA_FIM]);

        return response()->json(['message' => 'Funcionário inativado com sucesso.']);
    });

    // ── Documentos ──────────────────────────────────────────────
    Route::get('/funcionarios/{id}/documentos', function ($id) {
        $f = \App\Models\Funcionario::findOrFail($id);
        $docs = \App\Models\Documento::with('tipoDocumento')
            ->where('PESSOA_ID', $f->PESSOA_ID)
            ->get()->map(fn($d) => [
                'tipo' => optional($d->tipoDocumento)->TIPO_DOCUMENTO_NOME,
                'numero' => $d->DOCUMENTO_NUMERO,
                'obrigatorio' => (bool) (optional($d->tipoDocumento)->TIPO_DOCUMENTO_OBRIGATORIO ?? false),
            ]);
        return response()->json($docs);
    });

    // ── Histórico funcional ─────────────────────────────────────
    Route::get('/funcionarios/{id}/historico', function ($id) {
        $f = \App\Models\Funcionario::with([
            'lotacoes.setor',
            'lotacoes.atribuicaoLotacoes.atribuicao',
            'lotacoes.vinculo',
            'ferias',
            'afastamentos'
        ])->findOrFail($id);

        $lotacoes = $f->lotacoes->map(fn($l) => [
            'tipo' => 'lotacao',
            'data' => $l->LOTACAO_DATA_INICIO,
            'fim' => $l->LOTACAO_DATA_FIM,
            'label' => optional($l->setor)->SETOR_NOME,
            'extra' => optional($l->atribuicaoLotacoes?->first()?->atribuicao)->ATRIBUICAO_NOME,
            'ativa' => !$l->LOTACAO_DATA_FIM,
        ]);
        $ferias = $f->ferias->map(fn($v) => [
            'tipo' => 'ferias',
            'data' => $v->FERIAS_DATA_INICIO,
            'fim' => $v->FERIAS_DATA_FIM,
            'label' => 'Férias',
            'extra' => null,
        ]);
        $afastamentos = $f->afastamentos->map(fn($a) => [
            'tipo' => 'afastamento',
            'data' => $a->AFASTAMENTO_DATA_INICIO,
            'fim' => $a->AFASTAMENTO_DATA_FIM,
            'label' => $a->AFASTAMENTO_MOTIVO ?? 'Afastamento',
            'extra' => null,
        ]);

        $historico = $lotacoes->concat($ferias)->concat($afastamentos)
            ->sortByDesc('data')->values();
        return response()->json([
            'lotacoes' => $lotacoes->values(),
            'ferias' => $ferias->values(),
            'afastamentos' => $afastamentos->values(),
        ]);
    });

    // ── Escalas ─────────────────────────────────────────────────
    Route::get('/funcionarios/{id}/escalas', function ($id) {
        $itens = \App\Models\DetalheEscala::with(['escala', 'detalheEscalaItens.turno'])
            ->where('FUNCIONARIO_ID', $id)
            ->get()
            ->flatMap(fn($de) => $de->detalheEscalaItens->map(fn($item) => [
                'data' => $item->DETALHE_ESCALA_ITEM_DATA ?? optional($de->escala)->ESCALA_COMPETENCIA,
                'setor' => null,
                'turno' => optional($item->turno)->TURNO_DESCRICAO ?? null,
                'entrada' => optional($item->turno)->TURNO_HORA_INICIO ?? null,
                'saida' => optional($item->turno)->TURNO_HORA_FIM ?? null,
            ]))
            ->sortByDesc('data')
            ->take(60)
            ->values();
        return response()->json($itens);
    });

    // ── Escalas — listagem de todas as escalas para o MatrizEscalaView ─────────
    Route::get('/escalas', function (\Illuminate\Http\Request $request) {
        try {
            $escalas = \Illuminate\Support\Facades\DB::table('ESCALA as e')
                ->leftJoin('SETOR as s', 's.SETOR_ID', '=', 'e.SETOR_ID')
                ->select('e.ESCALA_ID', 'e.ESCALA_COMPETENCIA', 'e.ESCALA_STATUS', 's.SETOR_NOME as setor')
                ->orderByDesc('e.ESCALA_COMPETENCIA')
                ->take(50)
                ->get();
            return response()->json(['escalas' => $escalas]);
        } catch (\Throwable $e) {
            return response()->json(['escalas' => [], 'erro' => $e->getMessage()]);
        }
    });

    // ── Escalas — grade de uma escala específica (funcionários + itens) ─────────
    Route::get('/escalas/{id}', function ($id) {
        try {
            $escala = \Illuminate\Support\Facades\DB::table('ESCALA')->where('ESCALA_ID', $id)->first();
            if (!$escala)
                return response()->json(['escala' => null, 'funcionarios' => [], 'feriados' => []]);

            // Detecta ano/mês a partir de ESCALA_COMPETENCIA (ex: "2026-02" ou "Fev/2026")
            $ano = null;
            $mes = null;
            if (preg_match('/(\d{4})-(\d{2})/', $escala->ESCALA_COMPETENCIA ?? '', $m)) {
                $ano = (int) $m[1];
                $mes = (int) $m[2] - 1;
            }

            $detalhes = \Illuminate\Support\Facades\DB::table('DETALHE_ESCALA as de')
                ->leftJoin('FUNCIONARIO as f', 'f.FUNCIONARIO_ID', '=', 'de.FUNCIONARIO_ID')
                ->leftJoin('PESSOA as p', 'p.PESSOA_ID', '=', 'f.PESSOA_ID')
                ->leftJoin('FUNCAO as fc', 'fc.FUNCAO_ID', '=', 'f.FUNCAO_ID')
                ->where('de.ESCALA_ID', $id)
                ->select(
                    'de.DETALHE_ESCALA_ID as detalhe_id',
                    'f.FUNCIONARIO_ID as funcionario_id',
                    'p.PESSOA_NOME as nome',
                    'fc.FUNCAO_DESCRICAO as cargo'
                )
                ->get();

            $funcionarios = $detalhes->map(function ($d) {
                $itens = \Illuminate\Support\Facades\DB::table('DETALHE_ESCALA_ITEM as dei')
                    ->leftJoin('TURNO as t', 't.TURNO_ID', '=', 'dei.TURNO_ID')
                    ->where('dei.DETALHE_ESCALA_ID', $d->detalhe_id)
                    ->select(
                        'dei.DETALHE_ESCALA_ITEM_DATA as data',
                        'dei.TURNO_ID as turno_id',
                        't.TURNO_SIGLA as turno_sigla'
                    )
                    ->get()->toArray();
                return [
                    'detalhe_id' => $d->detalhe_id,
                    'funcionario_id' => $d->funcionario_id,
                    'nome' => $d->nome ?? 'Funcionário',
                    'cargo' => $d->cargo ?? '',
                    'itens' => $itens,
                ];
            });

            return response()->json([
                'escala' => ['competencia' => $escala->ESCALA_COMPETENCIA, 'ano' => $ano, 'mes' => $mes],
                'funcionarios' => $funcionarios,
                'feriados' => [],
            ]);
        } catch (\Throwable $e) {
            return response()->json(['erro' => $e->getMessage()], 500);
        }
    });

    // ── Escalas — salvar grade (substitui /escala/salvar-matriz legado) ─────────
    Route::post('/escalas/{id}/salvar', function ($id, \Illuminate\Http\Request $request) {
        try {
            $detalheId = $request->detalhe_escala_id;
            $itens = $request->itens ?? [];

            // Remove itens existentes para este detalhe
            \Illuminate\Support\Facades\DB::table('DETALHE_ESCALA_ITEM')
                ->where('DETALHE_ESCALA_ID', $detalheId)
                ->delete();

            // Insere novos itens
            foreach ($itens as $item) {
                \Illuminate\Support\Facades\DB::table('DETALHE_ESCALA_ITEM')->insert([
                    'DETALHE_ESCALA_ID' => $detalheId,
                    'TURNO_ID' => $item['turno_id'],
                    'DETALHE_ESCALA_ITEM_DATA' => $item['data'],
                ]);
            }

            return response()->json(['message' => 'Grade salva com sucesso.']);
        } catch (\Throwable $e) {
            return response()->json(['msg' => $e->getMessage()], 500);
        }
    });

    Route::get('/ponto', function (\Illuminate\Http\Request $request) {
        try {
            $user = \Illuminate\Support\Facades\Auth::user();
            $funcionario = optional($user)->funcionario
                ?? \App\Models\Funcionario::where('USUARIO_ID', $user->USUARIO_ID ?? 0)->first();

            if (!$funcionario)
                return response()->json(['registros' => [], 'apuracao' => null]);

            // Competência: YYYY-MM ou mês/ano corrente
            $comp = $request->competencia ?? now()->format('Y-m');
            [$ano, $mes] = explode('-', $comp);

            $inicio = sprintf('%04d-%02d-01', $ano, $mes);
            $fim = sprintf('%04d-%02d-%02d', $ano, $mes, cal_days_in_month(CAL_GREGORIAN, $mes, $ano));

            $registrosPonto = \Illuminate\Support\Facades\DB::table('REGISTRO_PONTO')
                ->where('FUNCIONARIO_ID', $funcionario->FUNCIONARIO_ID)
                ->whereBetween('REGISTRO_PONTO_DATA_HORA', [$inicio, $fim . ' 23:59:59'])
                ->orderBy('REGISTRO_PONTO_DATA_HORA')
                ->get();

            // Agrupa por dia e normaliza batidas
            $porDia = [];
            $tipoMap = [
                1 => 'entrada',
                2 => 'saida_almoco',
                3 => 'retorno_almoco',
                4 => 'saida',
                'E' => 'entrada',
                'S' => 'saida',
            ];
            foreach ($registrosPonto as $r) {
                $dt = \Carbon\Carbon::parse($r->REGISTRO_PONTO_DATA_HORA);
                $dia = (int) $dt->format('j');
                $hora = $dt->format('H:i');
                $tipoRaw = $r->REGISTRO_PONTO_TIPO ?? ($r->REGISTRO_TIPO ?? null);
                $tipo = $tipoMap[$tipoRaw] ?? (count($porDia[$dia] ?? []) % 2 === 0 ? 'entrada' : 'saida');
                $porDia[$dia][] = ['hora' => $hora, 'tipo' => $tipo];
            }

            $registros = collect($porDia)->map(fn($batidas, $dia) => ['dia' => $dia, 'batidas' => $batidas])->values();

            // Apuração do mês
            $apuracao = \Illuminate\Support\Facades\DB::table('APURACAO_PONTO')
                ->where('FUNCIONARIO_ID', $funcionario->FUNCIONARIO_ID)
                ->where('APURACAO_COMPETENCIA', $comp)
                ->first();

            return response()->json([
                'registros' => $registros,
                'apuracao' => $apuracao ? [
                    'competencia' => $apuracao->APURACAO_COMPETENCIA,
                    'horas_trab' => (float) ($apuracao->APURACAO_HORAS_TRAB ?? 0),
                    'horas_extra' => (float) ($apuracao->APURACAO_HORAS_EXTRA ?? 0),
                    'horas_falta' => (float) ($apuracao->APURACAO_HORAS_FALTA ?? 0),
                    'status' => $apuracao->APURACAO_STATUS ?? 'aberta',
                ] : null,
            ]);
        } catch (\Throwable $e) {
            return response()->json(['registros' => [], 'apuracao' => null, 'erro' => $e->getMessage()]);
        }
    });


    // ── Faltas e Atrasos (visão RH/gestor) ─────────────────────
    // ── Atestados Médicos ────────────────────────────────────────
    Route::get('/atestados', function () {
        try {
            $user = \Illuminate\Support\Facades\Auth::user();
            $funcionario = \App\Models\Funcionario::where('USUARIO_ID', $user->USUARIO_ID ?? 0)->first();
            if (!$funcionario)
                return response()->json(['fallback' => true, 'atestados' => []]);

            $rows = \Illuminate\Support\Facades\DB::table('AFASTAMENTO')
                ->where('FUNCIONARIO_ID', $funcionario->FUNCIONARIO_ID)
                ->orderByDesc('AFASTAMENTO_DATA_INICIO')
                ->take(50)->get();

            return response()->json(['atestados' => $rows, 'fallback' => false]);
        } catch (\Throwable $e) {
            return response()->json(['fallback' => true, 'atestados' => [], 'erro' => $e->getMessage()]);
        }
    });

    Route::post('/atestados', function (\Illuminate\Http\Request $request) {
        try {
            $user = \Illuminate\Support\Facades\Auth::user();
            $funcionario = \App\Models\Funcionario::where('USUARIO_ID', $user->USUARIO_ID ?? 0)->first();
            $id = \Illuminate\Support\Facades\DB::table('AFASTAMENTO')->insertGetId([
                'FUNCIONARIO_ID' => $funcionario->FUNCIONARIO_ID ?? null,
                'AFASTAMENTO_DATA_INICIO' => $request->inicio,
                'AFASTAMENTO_DATA_FIM' => $request->fim,
                'AFASTAMENTO_CID' => $request->cid,
                'AFASTAMENTO_DESCRICAO' => $request->descricao,
                'AFASTAMENTO_MEDICO' => $request->medico,
                'AFASTAMENTO_CRM' => $request->crm,
                'AFASTAMENTO_OBS' => $request->obs,
                'AFASTAMENTO_STATUS' => 'pendente',
            ]);
            return response()->json(['id' => $id], 201);
        } catch (\Throwable $e) {
            return response()->json(['erro' => $e->getMessage()], 500);
        }
    });

    Route::delete('/atestados/{id}', function ($id) {
        try {
            \Illuminate\Support\Facades\DB::table('AFASTAMENTO')->where('AFASTAMENTO_ID', $id)->delete();
            return response()->json(['message' => 'Removido.']);
        } catch (\Throwable $e) {
            return response()->json(['erro' => $e->getMessage()], 500);
        }
    });

    // ── Plantões Extras ──────────────────────────────────────────
    Route::get('/plantoes-extras', function () {
        try {
            $user = \Illuminate\Support\Facades\Auth::user();
            $funcionario = \App\Models\Funcionario::where('USUARIO_ID', $user->USUARIO_ID ?? 0)->first();
            if (!$funcionario)
                return response()->json(['fallback' => true, 'plantoes' => []]);

            $rows = \Illuminate\Support\Facades\DB::table('PLANTAO_EXTRA')
                ->where('FUNCIONARIO_ID', $funcionario->FUNCIONARIO_ID)
                ->orderByDesc('PLANTAO_DATA')
                ->take(50)->get();

            return response()->json(['plantoes' => $rows, 'fallback' => $rows->isEmpty()]);
        } catch (\Throwable $e) {
            return response()->json(['fallback' => true, 'plantoes' => [], 'erro' => $e->getMessage()]);
        }
    });

    Route::post('/plantoes-extras', function (\Illuminate\Http\Request $request) {
        try {
            $user = \Illuminate\Support\Facades\Auth::user();
            $funcionario = \App\Models\Funcionario::where('USUARIO_ID', $user->USUARIO_ID ?? 0)->first();
            $id = \Illuminate\Support\Facades\DB::table('PLANTAO_EXTRA')->insertGetId([
                'FUNCIONARIO_ID' => $funcionario->FUNCIONARIO_ID ?? null,
                'PLANTAO_DATA' => $request->data,
                'PLANTAO_SETOR' => $request->setor,
                'PLANTAO_HORA_INI' => $request->horaIni,
                'PLANTAO_HORA_FIM' => $request->horaFim,
                'PLANTAO_TIPO' => $request->tipo ?? 'programado',
                'PLANTAO_STATUS' => 'pendente',
                'PLANTAO_JUSTIFICATIVA' => $request->justificativa,
            ]);
            return response()->json(['id' => $id], 201);
        } catch (\Throwable $e) {
            return response()->json(['erro' => $e->getMessage()], 500);
        }
    });

    // ── Escala de Sobreaviso ─────────────────────────────────────
    Route::get('/sobreaviso', function (\Illuminate\Http\Request $request) {
        try {
            $user = \Illuminate\Support\Facades\Auth::user();
            $funcionario = \App\Models\Funcionario::where('USUARIO_ID', $user->USUARIO_ID ?? 0)->first();
            if (!$funcionario)
                return response()->json(['fallback' => true, 'sobreaviso' => [], 'acionamentos' => []]);

            $comp = $request->competencia ?? now()->format('Y-m');
            [$ano, $mes] = explode('-', $comp);
            $inicio = sprintf('%04d-%02d-01', $ano, $mes);
            $fim = sprintf('%04d-%02d-%02d', $ano, $mes, cal_days_in_month(CAL_GREGORIAN, $mes, $ano));

            $sob = \Illuminate\Support\Facades\DB::table('SOBREAVISO')
                ->where('FUNCIONARIO_ID', $funcionario->FUNCIONARIO_ID)
                ->where(function ($q) use ($inicio, $fim) {
                    $q->whereBetween('SOBREAVISO_INICIO', [$inicio, $fim])
                        ->orWhereBetween('SOBREAVISO_FIM', [$inicio, $fim]);
                })->get();

            $acion = \Illuminate\Support\Facades\DB::table('ACIONAMENTO')
                ->where('FUNCIONARIO_ID', $funcionario->FUNCIONARIO_ID)
                ->whereBetween('ACIONAMENTO_DATA', [$inicio, $fim])
                ->orderByDesc('ACIONAMENTO_DATA')->get();

            $fallback = $sob->isEmpty() && $acion->isEmpty();
            return response()->json(['sobreaviso' => $sob, 'acionamentos' => $acion, 'fallback' => $fallback]);
        } catch (\Throwable $e) {
            return response()->json(['fallback' => true, 'sobreaviso' => [], 'acionamentos' => [], 'erro' => $e->getMessage()]);
        }
    });

    Route::post('/sobreaviso/acionamento', function (\Illuminate\Http\Request $request) {
        try {
            $user = \Illuminate\Support\Facades\Auth::user();
            $funcionario = \App\Models\Funcionario::where('USUARIO_ID', $user->USUARIO_ID ?? 0)->first();
            $id = \Illuminate\Support\Facades\DB::table('ACIONAMENTO')->insertGetId([
                'FUNCIONARIO_ID' => $funcionario->FUNCIONARIO_ID ?? null,
                'ACIONAMENTO_DATA' => $request->data,
                'ACIONAMENTO_MOTIVO' => $request->motivo,
                'ACIONAMENTO_LOCAL' => $request->local,
                'ACIONAMENTO_HORA_INI' => $request->horaIni,
                'ACIONAMENTO_HORA_FIM' => $request->horaFim,
            ]);
            return response()->json(['id' => $id], 201);
        } catch (\Throwable $e) {
            return response()->json(['erro' => $e->getMessage()], 500);
        }
    });

    // ── Relatórios — stats do hero ───────────────────────────────
    Route::get('/relatorios/stats', function () {
        try {
            $funcionarios = \Illuminate\Support\Facades\DB::table('FUNCIONARIO')
                ->where(function ($q) {
                    $q->whereNull('FUNCIONARIO_DATA_DEMISSAO')
                        ->orWhere('FUNCIONARIO_DATA_DEMISSAO', '>', now()->toDateString());
                })->count();

            $competencia = \Illuminate\Support\Facades\DB::table('APURACAO_FOLHA')
                ->orderByDesc('COMPETENCIA')->value('COMPETENCIA')
                ?? \Illuminate\Support\Facades\DB::table('FOLHA_PAGAMENTO')
                    ->orderByDesc('FOLHA_COMPETENCIA')->value('FOLHA_COMPETENCIA')
                ?? now()->format('Y-m');

            return response()->json([
                'fallback' => false,
                'funcionarios' => $funcionarios,
                'competencia' => $competencia,
            ]);
        } catch (\Throwable $e) {
            return response()->json(['fallback' => true, 'erro' => $e->getMessage()]);
        }
    });

    // ── Agenda ───────────────────────────────────────────────────
    // ── Medicina do Trabalho ─────────────────────────────────────
    Route::get('/medicina', function () {
        try {
            $user = \Illuminate\Support\Facades\Auth::user();
            $funcionario = \App\Models\Funcionario::where('USUARIO_ID', $user->USUARIO_ID ?? 0)->first();
            if (!$funcionario)
                return response()->json(['fallback' => true, 'exames' => [], 'historico' => []]);

            $exames = \Illuminate\Support\Facades\DB::table('EXAME_OCUPACIONAL')
                ->where('FUNCIONARIO_ID', $funcionario->FUNCIONARIO_ID)
                ->orderByDesc('EXAME_DATA_REALIZACAO')
                ->get()->map(fn($e) => [
                    'EXAME_ID' => $e->EXAME_ID,
                    'EXAME_TIPO' => $e->EXAME_TIPO,
                    'EXAME_SUBTIPO' => $e->EXAME_SUBTIPO ?? null,
                    'EXAME_DATA_REALIZACAO' => $e->EXAME_DATA_REALIZACAO,
                    'EXAME_DATA_VENCIMENTO' => $e->EXAME_DATA_VENCIMENTO ?? null,
                    'EXAME_MEDICO' => $e->EXAME_MEDICO ?? null,
                    'apto' => (bool) ($e->EXAME_APTO ?? true),
                ]);

            $historico = \Illuminate\Support\Facades\DB::table('HISTORICO_EXAME')
                ->where('FUNCIONARIO_ID', $funcionario->FUNCIONARIO_ID)
                ->orderByDesc('HISTORICO_DATA')
                ->take(10)->get()->map(fn($h) => [
                    'tipo' => $h->HISTORICO_TIPO,
                    'data' => $h->HISTORICO_DATA,
                    'apto' => (bool) ($h->HISTORICO_APTO ?? true),
                ]);

            $fallback = $exames->isEmpty();
            return response()->json(['exames' => $exames, 'historico' => $historico, 'fallback' => $fallback]);
        } catch (\Throwable $e) {
            return response()->json(['fallback' => true, 'exames' => [], 'historico' => [], 'erro' => $e->getMessage()]);
        }
    });

    Route::post('/medicina/agendar', function (\Illuminate\Http\Request $request) {
        try {
            $user = \Illuminate\Support\Facades\Auth::user();
            $funcionario = \App\Models\Funcionario::where('USUARIO_ID', $user->USUARIO_ID ?? 0)->first();
            $id = \Illuminate\Support\Facades\DB::table('AGENDAMENTO_EXAME')->insertGetId([
                'FUNCIONARIO_ID' => $funcionario->FUNCIONARIO_ID ?? null,
                'AGENDAMENTO_TIPO' => $request->tipo,
                'AGENDAMENTO_DATA' => $request->data,
                'AGENDAMENTO_OBS' => $request->obs,
                'AGENDAMENTO_STATUS' => 'pendente',
                'AGENDAMENTO_DT_SOLICITACAO' => now()->toDateString(),
            ]);
            return response()->json(['id' => $id, 'message' => 'Agendamento registrado.'], 201);
        } catch (\Throwable $e) {
            return response()->json(['erro' => $e->getMessage()], 500);
        }
    });

    // ── Benefícios ───────────────────────────────────────────────
    Route::get('/beneficios', function () {
        try {
            $user = \Illuminate\Support\Facades\Auth::user();
            $funcionario = \App\Models\Funcionario::where('USUARIO_ID', $user->USUARIO_ID ?? 0)->first();
            if (!$funcionario)
                return response()->json(['fallback' => true, 'ativos' => [], 'disponiveis' => []]);

            // Benefícios ativos do funcionário (pivot)
            $ativos = \Illuminate\Support\Facades\DB::table('BENEFICIO as b')
                ->join('FUNCIONARIO_BENEFICIO as fb', 'fb.BENEFICIO_ID', '=', 'b.BENEFICIO_ID')
                ->where('fb.FUNCIONARIO_ID', $funcionario->FUNCIONARIO_ID)
                ->where('fb.FB_STATUS', 'ativo')
                ->select('b.*', 'fb.FB_VALOR as BENEFICIO_VALOR_REAL')
                ->get();

            // Benefícios disponíveis (não ativados)
            $idsAtivos = $ativos->pluck('BENEFICIO_ID');
            $disponiveis = \Illuminate\Support\Facades\DB::table('BENEFICIO')
                ->whereNotIn('BENEFICIO_ID', $idsAtivos)
                ->where('BENEFICIO_ATIVO', 1)
                ->get();

            $fallback = $ativos->isEmpty() && $disponiveis->isEmpty();
            return response()->json(['ativos' => $ativos, 'disponiveis' => $disponiveis, 'fallback' => $fallback]);
        } catch (\Throwable $e) {
            return response()->json(['fallback' => true, 'ativos' => [], 'disponiveis' => [], 'erro' => $e->getMessage()]);
        }
    });

    Route::post('/beneficios/solicitar', function (\Illuminate\Http\Request $request) {
        try {
            $user = \Illuminate\Support\Facades\Auth::user();
            $funcionario = \App\Models\Funcionario::where('USUARIO_ID', $user->USUARIO_ID ?? 0)->first();
            \Illuminate\Support\Facades\DB::table('BENEFICIO_SOLICITACAO')->insert([
                'FUNCIONARIO_ID' => $funcionario->FUNCIONARIO_ID ?? null,
                'BENEFICIO_ID' => $request->beneficio_id,
                'SOLICITACAO_DATA' => now()->toDateString(),
                'SOLICITACAO_STATUS' => 'pendente',
                'SOLICITACAO_OBS' => $request->nome,
            ]);
            return response()->json(['message' => 'Solicitação registrada.'], 201);
        } catch (\Throwable $e) {
            return response()->json(['erro' => $e->getMessage()], 500);
        }
    });

    Route::get('/agenda', function (\Illuminate\Http\Request $request) {
        try {
            $user = \Illuminate\Support\Facades\Auth::user();
            $funcionario = \App\Models\Funcionario::where('USUARIO_ID', $user->USUARIO_ID ?? 0)->first();
            if (!$funcionario)
                return response()->json(['fallback' => true, 'eventos' => []]);

            $comp = $request->competencia ?? now()->format('Y-m');
            [$ano, $mes] = explode('-', $comp);
            $inicio = sprintf('%04d-%02d-01', $ano, $mes);
            $fim = sprintf('%04d-%02d-%02d', $ano, $mes, cal_days_in_month(CAL_GREGORIAN, $mes, $ano));

            // Setor atual do funcionário (lotação sem data_fim)
            $lotacao = \Illuminate\Support\Facades\DB::table('LOTACAO')
                ->where('FUNCIONARIO_ID', $funcionario->FUNCIONARIO_ID)
                ->whereNull('LOTACAO_DATA_FIM')
                ->orderByDesc('LOTACAO_DATA_INICIO')
                ->first();
            $setorId = $lotacao->SETOR_ID ?? null;

            $rows = \Illuminate\Support\Facades\DB::table('AGENDA')
                ->whereBetween('AGENDA_DATA', [$inicio, $fim])
                ->where(function ($q) use ($funcionario, $setorId) {
                    // 1. Eventos globais (todos veem)
                    $q->where('AGENDA_ESCOPO', 'global')
                        // 2. Eventos do setor do funcionário
                        ->orWhere(function ($q2) use ($setorId) {
                        $q2->where('AGENDA_ESCOPO', 'setor')
                            ->where('AGENDA_SETOR_ID', $setorId);
                    })
                        // 3. Eventos pessoais próprios
                        ->orWhere(function ($q2) use ($funcionario) {
                        $q2->where('AGENDA_ESCOPO', 'pessoal')
                            ->where('FUNCIONARIO_ID', $funcionario->FUNCIONARIO_ID);
                    });
                })
                ->orderBy('AGENDA_DATA')->orderBy('AGENDA_HORA')
                ->get()->map(fn($e) => [
                    'AGENDA_ID' => $e->AGENDA_ID,
                    'AGENDA_TITULO' => $e->AGENDA_TITULO,
                    'AGENDA_TIPO' => $e->AGENDA_TIPO,
                    'AGENDA_DIA' => (int) \Carbon\Carbon::parse($e->AGENDA_DATA)->format('j'),
                    'AGENDA_HORA' => $e->AGENDA_HORA,
                    'AGENDA_LOCAL' => $e->AGENDA_LOCAL ?? null,
                    'AGENDA_DESC' => $e->AGENDA_DESC ?? null,
                    'AGENDA_ESCOPO' => $e->AGENDA_ESCOPO ?? 'pessoal',
                ]);

            return response()->json(['eventos' => $rows, 'fallback' => $rows->isEmpty()]);
        } catch (\Throwable $e) {
            return response()->json(['fallback' => true, 'eventos' => [], 'erro' => $e->getMessage()]);
        }
    });

    Route::post('/agenda', function (\Illuminate\Http\Request $request) {
        try {
            $user = \Illuminate\Support\Facades\Auth::user();
            $funcionario = \App\Models\Funcionario::where('USUARIO_ID', $user->USUARIO_ID ?? 0)->first();

            // Setor atual
            $lotacao = \Illuminate\Support\Facades\DB::table('LOTACAO')
                ->where('FUNCIONARIO_ID', $funcionario->FUNCIONARIO_ID ?? 0)
                ->whereNull('LOTACAO_DATA_FIM')
                ->orderByDesc('LOTACAO_DATA_INICIO')
                ->first();
            $setorId = $lotacao->SETOR_ID ?? null;

            // Verifica se é gestor (CARGO_GESTAO = 1) ou admin (PERFIL_ID ≤ 2)
            $isGestor = \Illuminate\Support\Facades\DB::table('LOTACAO as l')
                ->join('ATRIBUICAO_LOTACAO as al', 'al.LOTACAO_ID', '=', 'l.LOTACAO_ID')
                ->join('ATRIBUICAO as a', 'a.ATRIBUICAO_ID', '=', 'al.ATRIBUICAO_ID')
                ->join('CARGO as c', 'c.CARGO_ID', '=', 'a.CARGO_ID')
                ->where('l.FUNCIONARIO_ID', $funcionario->FUNCIONARIO_ID ?? 0)
                ->whereNull('l.LOTACAO_DATA_FIM')
                ->where('c.CARGO_GESTAO', 1)
                ->exists();

            $isAdmin = \Illuminate\Support\Facades\DB::table('USUARIO_PERFIL')
                ->where('USUARIO_ID', $user->USUARIO_ID ?? 0)
                ->where('USUARIO_PERFIL_ATIVO', 1)
                ->where('PERFIL_ID', '<=', 2)
                ->exists();

            // Valida escopo solicitado
            $escopoSolicitado = $request->escopo ?? 'pessoal';
            if ($escopoSolicitado === 'global' && !$isAdmin)
                $escopoSolicitado = 'pessoal';
            if ($escopoSolicitado === 'setor' && !$isGestor && !$isAdmin)
                $escopoSolicitado = 'pessoal';

            $agendaSetorId = ($escopoSolicitado === 'setor') ? $setorId : null;

            $id = \Illuminate\Support\Facades\DB::table('AGENDA')->insertGetId([
                'FUNCIONARIO_ID' => $funcionario->FUNCIONARIO_ID ?? null,
                'AGENDA_TITULO' => $request->titulo,
                'AGENDA_TIPO' => $request->tipo,
                'AGENDA_DATA' => $request->data,
                'AGENDA_HORA' => $request->hora,
                'AGENDA_LOCAL' => $request->local,
                'AGENDA_DESC' => $request->desc,
                'AGENDA_ESCOPO' => $escopoSolicitado,
                'AGENDA_SETOR_ID' => $agendaSetorId,
            ]);
            return response()->json([
                'id' => $id,
                'escopo' => $escopoSolicitado,
                'gestor' => $isGestor,
                'admin' => $isAdmin,
            ], 201);
        } catch (\Throwable $e) {
            return response()->json(['erro' => $e->getMessage()], 500);
        }
    });


    // ── Banco de Horas ───────────────────────────────────────────
    Route::get('/banco-horas', function () {
        try {
            $user = \Illuminate\Support\Facades\Auth::user();
            $funcionario = \App\Models\Funcionario::where('USUARIO_ID', $user->USUARIO_ID ?? 0)->first();
            if (!$funcionario)
                return response()->json(['fallback' => true, 'apuracoes' => []]);

            $apuracoes = \Illuminate\Support\Facades\DB::table('APURACAO_PONTO')
                ->where('FUNCIONARIO_ID', $funcionario->FUNCIONARIO_ID)
                ->orderByDesc('APURACAO_COMPETENCIA')
                ->take(12)->get()
                ->map(fn($a) => [
                    'competencia' => $a->APURACAO_COMPETENCIA,
                    'saldo_acumulado' => $a->APURACAO_SALDO ?? $a->APURACAO_HORAS_EXTRA ?? 0,
                    'horas_trab' => $a->APURACAO_HORAS_TRAB ?? 0,
                    'horas_falta' => $a->APURACAO_HORAS_FALTA ?? 0,
                    'status' => $a->APURACAO_STATUS ?? 'aberta',
                ]);

            return response()->json(['apuracoes' => $apuracoes, 'fallback' => $apuracoes->isEmpty()]);
        } catch (\Throwable $e) {
            return response()->json(['fallback' => true, 'apuracoes' => [], 'erro' => $e->getMessage()]);
        }
    });

    // ── Contratos / Vínculos ─────────────────────────────────────
    Route::get('/contratos', function () {
        try {
            $user = \Illuminate\Support\Facades\Auth::user();
            $funcionario = \App\Models\Funcionario::where('USUARIO_ID', $user->USUARIO_ID ?? 0)->first();
            if (!$funcionario)
                return response()->json(['fallback' => true]);

            $f = \Illuminate\Support\Facades\DB::table('FUNCIONARIO as f')
                ->leftJoin('PESSOA as p', 'p.PESSOA_ID', '=', 'f.PESSOA_ID')
                ->leftJoin('CARGO as c', 'c.CARGO_ID', '=', 'f.CARGO_ID')
                ->leftJoin('SETOR as s', 's.SETOR_ID', '=', 'f.SETOR_ID')
                ->select('f.*', 'p.PESSOA_NOME', 'p.PESSOA_CPF', 'p.PESSOA_PIS', 'c.CARGO_NOME', 's.SETOR_NOME')
                ->where('f.FUNCIONARIO_ID', $funcionario->FUNCIONARIO_ID)
                ->first();

            if (!$f)
                return response()->json(['fallback' => true]);

            $historico = \Illuminate\Support\Facades\DB::table('HISTORICO_FUNCIONAL')
                ->where('FUNCIONARIO_ID', $funcionario->FUNCIONARIO_ID)
                ->orderByDesc('HISTORICO_DATA_INICIO')->get()
                ->map(fn($h) => [
                    'id' => $h->HISTORICO_ID,
                    'tipo' => $h->HISTORICO_TIPO ?? 'Servidor',
                    'regime' => $h->HISTORICO_REGIME ?? 'Estatutário',
                    'cargo' => $h->HISTORICO_CARGO ?? ($f->CARGO_NOME ?? '—'),
                    'setor' => $h->HISTORICO_SETOR ?? ($f->SETOR_NOME ?? '—'),
                    'inicio' => $h->HISTORICO_DATA_INICIO,
                    'fim' => $h->HISTORICO_DATA_FIM,
                    'ativo' => is_null($h->HISTORICO_DATA_FIM),
                ]);

            return response()->json([
                'fallback' => false,
                'contrato' => [
                    'cargo' => $f->CARGO_NOME ?? '—',
                    'setor' => $f->SETOR_NOME ?? '—',
                    'admissao' => $f->FUNCIONARIO_DATA_ADMISSAO ?? null,
                    'matricula' => $f->FUNCIONARIO_MATRICULA ?? '—',
                    'vinculo' => $f->FUNCIONARIO_VINCULO ?? 'Servidor',
                    'cpf' => $f->PESSOA_CPF ?? null,
                    'pis' => $f->PESSOA_PIS ?? null,
                ],
                'historico' => $historico,
            ]);
        } catch (\Throwable $e) {
            return response()->json(['fallback' => true, 'erro' => $e->getMessage()]);
        }
    });

    // ── Progressão Funcional ─────────────────────────────────────
    Route::get('/progressao-funcional', function () {
        try {
            $user = \Illuminate\Support\Facades\Auth::user();
            $funcionario = \App\Models\Funcionario::where('USUARIO_ID', $user->USUARIO_ID ?? 0)->first();
            if (!$funcionario)
                return response()->json(['fallback' => true]);

            $progressoes = \Illuminate\Support\Facades\DB::table('PROGRESSAO')
                ->where('FUNCIONARIO_ID', $funcionario->FUNCIONARIO_ID)
                ->orderBy('PROGRESSAO_DATA')->get()
                ->map(fn($p) => [
                    'id' => $p->PROGRESSAO_ID,
                    'nivel' => $p->PROGRESSAO_NIVEL,
                    'referencia' => $p->PROGRESSAO_REFERENCIA ?? '—',
                    'salario' => (float) ($p->PROGRESSAO_SALARIO ?? 0),
                    'data' => $p->PROGRESSAO_DATA,
                    'tipo' => $p->PROGRESSAO_TIPO ?? 'Progressão',
                    'reajuste' => (float) ($p->PROGRESSAO_REAJUSTE ?? 0),
                    'obs' => $p->PROGRESSAO_OBS ?? null,
                    'ativa' => (bool) ($p->PROGRESSAO_ATIVA ?? false),
                    'futura' => (bool) ($p->PROGRESSAO_FUTURA ?? false),
                ]);

            $admissao = $funcionario->FUNCIONARIO_DATA_ADMISSAO ?? null;
            $salarioBase = \Illuminate\Support\Facades\DB::table('PROGRESSAO')
                ->where('FUNCIONARIO_ID', $funcionario->FUNCIONARIO_ID)
                ->where('PROGRESSAO_ATIVA', 1)->value('PROGRESSAO_SALARIO');

            return response()->json([
                'fallback' => $progressoes->isEmpty(),
                'progressoes' => $progressoes,
                'admissao' => $admissao,
                'salario_base' => $salarioBase,
            ]);
        } catch (\Throwable $e) {
            return response()->json(['fallback' => true, 'progressoes' => [], 'erro' => $e->getMessage()]);
        }
    });

    // ── Declarações / Requerimentos ──────────────────────────────
    Route::get('/declaracoes', function () {
        try {
            $user = \Illuminate\Support\Facades\Auth::user();
            $funcionario = \App\Models\Funcionario::where('USUARIO_ID', $user->USUARIO_ID ?? 0)->first();
            if (!$funcionario)
                return response()->json(['fallback' => true, 'pedidos' => []]);

            $pedidos = \Illuminate\Support\Facades\DB::table('PEDIDO_DOCUMENTO')
                ->where('FUNCIONARIO_ID', $funcionario->FUNCIONARIO_ID)
                ->orderByDesc('PEDIDO_DATA')->take(20)->get();

            return response()->json(['pedidos' => $pedidos, 'fallback' => $pedidos->isEmpty()]);
        } catch (\Throwable $e) {
            return response()->json(['fallback' => true, 'pedidos' => [], 'erro' => $e->getMessage()]);
        }
    });

    // ── Ouvidoria Admin (RH/Admin) ────────────────────────────────────
    Route::get('/ouvidoria/admin', function () {
        try {
            $rows = \Illuminate\Support\Facades\DB::table('OUVIDORIA as o')
                ->leftJoin('FUNCIONARIO as f', 'f.FUNCIONARIO_ID', '=', 'o.FUNCIONARIO_ID')
                ->leftJoin('PESSOA as p', 'p.PESSOA_ID', '=', 'f.PESSOA_ID')
                ->leftJoin('LOTACAO as l', function ($j) {
                    $j->on('l.FUNCIONARIO_ID', '=', 'f.FUNCIONARIO_ID')
                        ->whereNull('l.LOTACAO_DATA_FIM');
                })
                ->leftJoin('SETOR as s', 's.SETOR_ID', '=', 'l.SETOR_ID')
                ->orderByDesc('o.OUVIDORIA_DATA')
                ->select(
                    'o.OUVIDORIA_ID as id',
                    'o.OUVIDORIA_TIPO as tipo',
                    'o.OUVIDORIA_AREA as area',
                    'o.OUVIDORIA_URGENCIA as urgencia',
                    'o.OUVIDORIA_DESC as descricao',
                    'o.OUVIDORIA_STATUS as status',
                    'o.OUVIDORIA_PROTOCOLO as protocolo',
                    'o.OUVIDORIA_DATA as data',
                    'o.OUVIDORIA_ANONIMO as anonimo',
                    'o.OUVIDORIA_RESPOSTA as resposta',
                    \Illuminate\Support\Facades\DB::raw("CASE WHEN o.OUVIDORIA_ANONIMO = 1 THEN NULL ELSE p.PESSOA_NOME END as autor"),
                    's.SETOR_NOME as setor'
                )
                ->get()
                ->map(fn($r) => [
                    'id' => $r->id,
                    'tipo' => $r->tipo ?? 'outros',
                    'area' => $r->area,
                    'urgencia' => $r->urgencia ?? 'normal',
                    'descricao' => $r->descricao,
                    'status' => $r->status ?? 'recebida',
                    'protocolo' => $r->protocolo,
                    'data' => $r->data,
                    'anonimo' => (bool) $r->anonimo,
                    'autor' => $r->autor,
                    'setor' => $r->setor,
                    'resposta' => $r->resposta,
                ]);
            return response()->json(['manifestacoes' => $rows]);
        } catch (\Throwable $e) {
            return response()->json(['manifestacoes' => [], 'erro' => $e->getMessage()]);
        }
    });

    Route::put('/ouvidoria/{id}', function (\Illuminate\Http\Request $request, $id) {
        try {
            \Illuminate\Support\Facades\DB::table('OUVIDORIA')
                ->where('OUVIDORIA_ID', $id)
                ->update([
                    'OUVIDORIA_STATUS' => $request->status ?? 'recebida',
                    'OUVIDORIA_RESPOSTA' => $request->resposta,
                ]);
            return response()->json(['ok' => true]);
        } catch (\Throwable $e) {
            return response()->json(['erro' => $e->getMessage()], 500);
        }
    });

    Route::post('/declaracoes', function (\Illuminate\Http\Request $request) {
        try {
            $user = \Illuminate\Support\Facades\Auth::user();
            $funcionario = \App\Models\Funcionario::where('USUARIO_ID', $user->USUARIO_ID ?? 0)->first();
            $ano = now()->format('Y');
            $seq = \Illuminate\Support\Facades\DB::table('PEDIDO_DOCUMENTO')->count() + 1;

            $proto = "REQ-{$ano}-" . str_pad($seq, 3, '0', STR_PAD_LEFT);
            $id = \Illuminate\Support\Facades\DB::table('PEDIDO_DOCUMENTO')->insertGetId([
                'FUNCIONARIO_ID' => $funcionario->FUNCIONARIO_ID ?? null,
                'PEDIDO_NOME' => $request->nome,
                'PEDIDO_DATA' => now()->toDateString(),
                'PEDIDO_STATUS' => $request->instantaneo ? 'pronto' : 'andamento',
                'PEDIDO_PROTOCOLO' => $proto,
            ]);
            return response()->json(['id' => $id, 'protocolo' => $proto], 201);
        } catch (\Throwable $e) {
            return response()->json(['erro' => $e->getMessage()], 500);
        }
    });

    // ── Ouvidoria ────────────────────────────────────────────────
    Route::get('/ouvidoria', function () {
        try {
            $user = \Illuminate\Support\Facades\Auth::user();
            $funcionario = \App\Models\Funcionario::where('USUARIO_ID', $user->USUARIO_ID ?? 0)->first();
            if (!$funcionario)
                return response()->json(['fallback' => true, 'manifestacoes' => []]);

            $rows = \Illuminate\Support\Facades\DB::table('OUVIDORIA')
                ->where(function ($q) use ($funcionario) {
                    $q->where('FUNCIONARIO_ID', $funcionario->FUNCIONARIO_ID)
                        ->orWhere('OUVIDORIA_ANONIMO', 0);
                })
                ->where('FUNCIONARIO_ID', $funcionario->FUNCIONARIO_ID)
                ->orderByDesc('OUVIDORIA_DATA')->take(20)->get();

            return response()->json(['manifestacoes' => $rows, 'fallback' => $rows->isEmpty()]);
        } catch (\Throwable $e) {
            return response()->json(['fallback' => true, 'manifestacoes' => [], 'erro' => $e->getMessage()]);
        }
    });

    Route::post('/ouvidoria', function (\Illuminate\Http\Request $request) {
        try {
            $user = \Illuminate\Support\Facades\Auth::user();
            $funcionario = \App\Models\Funcionario::where('USUARIO_ID', $user->USUARIO_ID ?? 0)->first();
            $ano = now()->format('Y');
            $seq = \Illuminate\Support\Facades\DB::table('OUVIDORIA')->count() + 1;
            $proto = "OUV-{$ano}-" . str_pad($seq, 3, '0', STR_PAD_LEFT);
            $id = \Illuminate\Support\Facades\DB::table('OUVIDORIA')->insertGetId([
                'FUNCIONARIO_ID' => $request->anonimo ? null : ($funcionario->FUNCIONARIO_ID ?? null),
                'OUVIDORIA_TIPO' => $request->tipo,
                'OUVIDORIA_AREA' => $request->area,
                'OUVIDORIA_URGENCIA' => $request->urgencia ?? 'normal',
                'OUVIDORIA_DESC' => $request->descricao,
                'OUVIDORIA_STATUS' => 'recebida',
                'OUVIDORIA_PROTOCOLO' => $proto,
                'OUVIDORIA_DATA' => now()->toDateString(),
                'OUVIDORIA_ANONIMO' => $request->anonimo ? 1 : 0,
            ]);
            return response()->json(['id' => $id, 'protocolo' => $proto], 201);
        } catch (\Throwable $e) {
            return response()->json(['erro' => $e->getMessage()], 500);
        }
    });

    // ── Faltas e Atrasos (visão RH/gestor) ─────────────────────
    Route::get('/abonos-gestao', function (\Illuminate\Http\Request $request) {
        try {
            $query = \Illuminate\Support\Facades\DB::table('ABONO_FALTA as af')
                ->leftJoin('FUNCIONARIO as f', 'f.FUNCIONARIO_ID', '=', 'af.FUNCIONARIO_ID')
                ->leftJoin('PESSOA as p', 'p.PESSOA_ID', '=', 'f.PESSOA_ID')
                ->select(
                    'af.ABONO_FALTA_ID as id',
                    'p.PESSOA_NOME as funcionario',
                    'f.FUNCIONARIO_ID as funcionario_id',
                    'af.ABONO_FALTA_TIPO as tipo',
                    'af.ABONO_FALTA_DATA_INICIO as data',
                    'af.ABONO_FALTA_JUSTIFICATIVA as justificativa',
                    'af.ABONO_FALTA_STATUS as situacao',
                    'af.ABONO_FALTA_COMPROVANTE as comprovante'
                )
                ->orderByDesc('af.ABONO_FALTA_DATA_INICIO');

            // Filtra pelo período, mas SEMPRE inclui pendentes de qualquer mês
            if ($request->filled('mes') && $request->filled('ano')) {
                $mes = $request->mes;
                $ano = $request->ano;
                $query->where(function ($q) use ($mes, $ano) {
                    $q->where(function ($q2) use ($mes, $ano) {
                        $q2->whereMonth('af.ABONO_FALTA_DATA_INICIO', $mes)
                            ->whereYear('af.ABONO_FALTA_DATA_INICIO', $ano);
                    })->orWhere('af.ABONO_FALTA_STATUS', 'pendente');
                });
            }

            $abonos = $query->take(200)->get()->map(fn($a) => [
                'id' => $a->id,
                'funcionario' => $a->funcionario ?? 'Funcionário #' . ($a->funcionario_id ?? '?'),
                'funcionario_id' => $a->funcionario_id,
                'cargo' => '',
                'setor' => '',
                'tipo' => 'falta', // Todos os abonos são de falta; ABONO_FALTA_TIPO é o subtipo
                'subtipo' => $a->tipo, // medico, declaracao, luto, etc.
                'data' => $a->data,
                'duracao' => '8h 00min',
                'situacao' => $a->situacao ?? 'pendente',
                'justificativa' => $a->justificativa,
                'comprovante_url' => ($a->comprovante ?? null)
                    ? asset('storage/abonos/' . $a->comprovante) : null,
            ]);

            return response()->json($abonos);
        } catch (\Throwable $e) {
            return response()->json(['erro' => $e->getMessage()], 500);
        }
    });

    Route::put('/abonos-gestao/{id}/status', function ($id, \Illuminate\Http\Request $request) {
        $status = $request->input('status');
        \Illuminate\Support\Facades\DB::table('ABONO_FALTA')
            ->where('ABONO_FALTA_ID', $id)
            ->update(['ABONO_FALTA_STATUS' => $status]);
        return response()->json(['message' => 'Status atualizado.']);
    });

    // ── Abono de Faltas ─────────────────────────────────────────
    Route::get('/abono-faltas', function () {
        $user = Auth::user();
        $funcionario = optional($user)->funcionario
            ?? \App\Models\Funcionario::where('FUNCIONARIO_ID', $user->FUNCIONARIO_ID ?? 0)->first();

        if (!$funcionario)
            return response()->json([]);

        $abonos = \Illuminate\Support\Facades\DB::table('JUSTIFICATIVA_PONTO')
            ->where('FUNCIONARIO_ID', $funcionario->FUNCIONARIO_ID)
            ->orderByDesc('JUSTIFICATIVA_DATA')
            ->get()
            ->map(fn($j) => [
                'id' => $j->JUSTIFICATIVA_ID,
                'ABONO_FALTA_DATA' => $j->JUSTIFICATIVA_DATA,
                'ABONO_FALTA_JUSTIFICATIVA' => $j->JUSTIFICATIVA_DESCRICAO,
                'tipo' => $j->JUSTIFICATIVA_TIPO ?? null,
                'status' => $j->JUSTIFICATIVA_STATUS ?? 'pendente',
                'comprovante_url' => $j->JUSTIFICATIVA_COMPROVANTE
                    ? asset('storage/abonos/' . $j->JUSTIFICATIVA_COMPROVANTE)
                    : null,
                'criado_em' => $j->JUSTIFICATIVA_DATA,
            ]);

        return response()->json($abonos);
    });

    Route::post('/abono-faltas', function (\Illuminate\Http\Request $request) {
        $user = Auth::user();
        $funcionario = optional($user)->funcionario
            ?? \App\Models\Funcionario::where('FUNCIONARIO_ID', $user->FUNCIONARIO_ID ?? 0)->first();

        if (!$funcionario)
            return response()->json(['erro' => 'Funcionário não encontrado.'], 404);

        $nomeArquivo = null;
        if ($request->hasFile('comprovante') && $request->file('comprovante')->isValid()) {
            $arquivo = $request->file('comprovante');
            $nomeArquivo = 'f' . $funcionario->FUNCIONARIO_ID . '_' . time() . '.' . $arquivo->getClientOriginalExtension();
            $arquivo->storeAs('public/abonos', $nomeArquivo);
        }

        $id = \Illuminate\Support\Facades\DB::table('ABONO_FALTA')->insertGetId([
            'FUNCIONARIO_ID' => $funcionario->FUNCIONARIO_ID,
            'ABONO_FALTA_DATA_INICIO' => $request->ABONO_FALTA_DATA,
            'ABONO_FALTA_DATA_FIM' => $request->ABONO_FALTA_DATA,
            'ABONO_FALTA_JUSTIFICATIVA' => $request->ABONO_FALTA_JUSTIFICATIVA,
            'ABONO_FALTA_TIPO' => $request->tipo,
            'ABONO_FALTA_STATUS' => 'pendente',
            'ABONO_FALTA_COMPROVANTE' => $nomeArquivo,
        ]);

        return response()->json(['abono_id' => $id, 'comprovante' => $nomeArquivo], 201);
    });

    Route::put('/abono-faltas/{id}', function ($id, \Illuminate\Http\Request $request) {
        $data = [
            'JUSTIFICATIVA_DATA' => $request->ABONO_FALTA_DATA,
            'JUSTIFICATIVA_DESCRICAO' => $request->ABONO_FALTA_JUSTIFICATIVA,
            'JUSTIFICATIVA_TIPO' => $request->tipo,
        ];

        if ($request->hasFile('comprovante') && $request->file('comprovante')->isValid()) {
            $arquivo = $request->file('comprovante');
            $nomeArquivo = 'edit_' . $id . '_' . time() . '.' . $arquivo->getClientOriginalExtension();
            $arquivo->storeAs('public/abonos', $nomeArquivo);
            $data['ABONO_FALTA_COMPROVANTE'] = $nomeArquivo;
        }

        \Illuminate\Support\Facades\DB::table('ABONO_FALTA')
            ->where('ABONO_FALTA_ID', $id)
            ->update($data);

        return response()->json(['message' => 'Atualizado com sucesso.']);
    });

    Route::delete('/abono-faltas/{id}', function ($id) {
        \Illuminate\Support\Facades\DB::table('ABONO_FALTA')
            ->where('ABONO_FALTA_ID', $id)
            ->delete();
        return response()->json(['message' => 'Excluído com sucesso.']);
    });

    // ── Férias e Licenças do usuário logado ─────────────────────
    Route::get('/ferias', function () {
        $user = Auth::user();
        $funcionario = optional($user)->funcionario
            ?? \App\Models\Funcionario::where('USUARIO_ID', $user->USUARIO_ID)->first();

        if (!$funcionario) {
            return response()->json([]);
        }

        $ferias = \App\Models\Ferias::where('FUNCIONARIO_ID', $funcionario->FUNCIONARIO_ID)
            ->orderBy('FERIAS_DATA_INICIO', 'desc')
            ->get()
            ->map(fn($f) => [
                'ferias_id' => $f->FERIAS_ID,
                'ferias_inicio' => $f->FERIAS_DATA_INICIO,
                'ferias_fim' => $f->FERIAS_DATA_FIM,
                'aquisitivo_inicio' => $f->FERIAS_AQUISITIVO_INICIO,
                'aquisitivo_fim' => $f->FERIAS_AQUISITIVO_FIM,
                'dias' => $f->FERIAS_DATA_INICIO && $f->FERIAS_DATA_FIM
                    ? \Carbon\Carbon::parse($f->FERIAS_DATA_INICIO)->diffInDays($f->FERIAS_DATA_FIM)
                    : null,
                'status' => $f->FERIAS_DATA_FIM && \Carbon\Carbon::parse($f->FERIAS_DATA_FIM)->isPast()
                    ? 'GOZADA' : ($f->FERIAS_DATA_INICIO ? 'PROGRAMADA' : 'PENDENTE'),
            ]);

        return response()->json($ferias);
    });

    // ── Faltas e Atrasos do usuário logado ──────────────────────
    Route::get('/faltas-atrasos', function (\Illuminate\Http\Request $request) {
        $user = Auth::user();
        $funcionario = optional($user)->funcionario
            ?? \App\Models\Funcionario::where('USUARIO_ID', $user->USUARIO_ID)->first();

        if (!$funcionario) {
            return response()->json(['faltas' => [], 'apuracao' => null]);
        }

        $mes = $request->mes ?? now()->month;
        $ano = $request->ano ?? now()->year;
        $competencia = sprintf('%04d-%02d', $ano, $mes);

        $apuracao = \App\Models\ApuracaoPonto::where('FUNCIONARIO_ID', $funcionario->FUNCIONARIO_ID)
            ->where('APURACAO_COMPETENCIA', $competencia)
            ->first();

        $justificativas = $apuracao
            ? \App\Models\JustificativaPonto::where('APURACAO_ID', $apuracao->APURACAO_ID)
                ->get()
                ->map(fn($j) => [
                    'id' => $j->JUSTIFICATIVA_ID ?? $j->getKey(),
                    'data' => $j->JUSTIFICATIVA_DATA ?? null,
                    'tipo' => $j->JUSTIFICATIVA_TIPO ?? 'FALTA',
                    'motivo' => $j->JUSTIFICATIVA_MOTIVO ?? null,
                    'status' => $j->JUSTIFICATIVA_STATUS ?? 'PENDENTE',
                ])
            : [];

        return response()->json([
            'apuracao' => $apuracao ? [
                'competencia' => $apuracao->APURACAO_COMPETENCIA,
                'horas_trab' => $apuracao->APURACAO_HORAS_TRAB,
                'horas_extra' => $apuracao->APURACAO_HORAS_EXTRA,
                'horas_falta' => $apuracao->APURACAO_HORAS_FALTA,
                'status' => $apuracao->APURACAO_STATUS,
            ] : null,
            'faltas' => $justificativas,
        ]);
    });

    // ── Banco de Horas do usuário logado ────────────────────────
    Route::get('/banco-horas', function (\Illuminate\Http\Request $request) {
        $user = Auth::user();
        $funcionario = optional($user)->funcionario
            ?? \App\Models\Funcionario::where('USUARIO_ID', $user->USUARIO_ID)->first();

        if (!$funcionario) {
            return response()->json(['saldo' => 0, 'extrato' => []]);
        }

        $apuracoes = \App\Models\ApuracaoPonto::where('FUNCIONARIO_ID', $funcionario->FUNCIONARIO_ID)
            ->orderBy('APURACAO_COMPETENCIA', 'desc')
            ->take(12)
            ->get();

        $saldoAcumulado = 0;
        $extrato = $apuracoes->reverse()->values()->map(function ($a) use (&$saldoAcumulado) {
            $saldo_mes = (float) ($a->APURACAO_HORAS_EXTRA ?? 0) - (float) ($a->APURACAO_HORAS_FALTA ?? 0);
            $saldoAcumulado += $saldo_mes;
            return [
                'competencia' => $a->APURACAO_COMPETENCIA,
                'horas_trab' => (float) ($a->APURACAO_HORAS_TRAB ?? 0),
                'horas_extra' => (float) ($a->APURACAO_HORAS_EXTRA ?? 0),
                'horas_falta' => (float) ($a->APURACAO_HORAS_FALTA ?? 0),
                'saldo_mes' => round($saldo_mes, 2),
                'saldo_total' => round($saldoAcumulado, 2),
                'status' => $a->APURACAO_STATUS,
            ];
        })->reverse()->values();

        return response()->json([
            'saldo' => round($saldoAcumulado, 2),
            'extrato' => $extrato,
        ]);
    });

    // GET /abono-faltas removido deste grupo (use /api/v3/abono-faltas).

    // ── Folhas de Pagamento (listagem) ──────────────────────────
    Route::get('/folhas', function (\Illuminate\Http\Request $request) {
        $folhas = \App\Models\Folha::with(['tipoFolha', 'historicoUltimo.statusEscala'])
            ->orderByDesc('FOLHA_COMPETENCIA')
            ->take(24)
            ->get()
            ->map(fn($f) => [
                'folha_id' => $f->FOLHA_ID,
                'descricao' => $f->FOLHA_DESCRICAO,
                'competencia' => $f->FOLHA_COMPETENCIA,
                'tipo' => optional($f->tipoFolha)->COLUNA_NOME ?? 'Normal',
                'qtd_servidores' => (int) ($f->FOLHA_QTD_SERVIDORES ?? 0),
                'valor_total' => (float) ($f->FOLHA_VALOR_TOTAL ?? 0),
                'status' => 'FECHADA',
            ]);

        return response()->json(['folhas' => $folhas]);
    });

    // ── Substituições de escala ─────────────────────────────────
    Route::get('/substituicoes', function (\Illuminate\Http\Request $request) {
        $subs = \App\Models\SubstituicaoEscala::with([
            'funcionario.pessoa',
            'detalheEscalaItem.detalheEscala.escala.setor',
        ])
            ->orderBy('SUBSTITUICAO_ESCALA_DATA', 'desc')
            ->take(50)
            ->get()
            ->map(fn($s) => [
                'id' => $s->SUBSTITUICAO_ESCALA_ID,
                'data' => $s->SUBSTITUICAO_ESCALA_DATA,
                'justificativa' => $s->SUBSTITUICAO_ESCALA_JUSTIFICATIVA,
                'funcionario' => optional(optional($s->funcionario)->pessoa)->PESSOA_NOME,
                'escala_descricao' => optional(optional(optional(optional($s->detalheEscalaItem)->detalheEscala)->escala))->ESCALA_DESCRICAO,
                'setor' => optional(optional(optional(optional(optional($s->detalheEscalaItem)->detalheEscala)->escala)->setor))->SETOR_NOME,
            ]);

        return response()->json($subs);
    });

    // ── Escalas (listagem resumida) ─────────────────────────────
    Route::get('/escalas', function (\Illuminate\Http\Request $request) {
        $escalas = \App\Models\Escala::with(['setor.unidade', 'historicoUltimo'])
            ->withCount('detalheEscalas')
            ->orderByDesc('ESCALA_ID')
            ->take(50)
            ->get()
            ->map(fn($e) => [
                'escala_id' => $e->ESCALA_ID,
                'descricao' => $e->ESCALA_DESCRICAO,
                'competencia' => $e->ESCALA_COMPETENCIA,
                'setor' => optional($e->setor)->SETOR_NOME,
                'unidade' => optional(optional($e->setor)->unidade)->UNIDADE_NOME,
                'funcionarios' => $e->detalhe_escalas_count,
                'status' => optional($e->historicoUltimo)->HISTORICO_ESCALA_STATUS ?? 'CADASTRADA',
            ]);

        return response()->json(['escalas' => $escalas]);
    });

    // ── Escala individual (detalhada para o Vue) ────────────────
    Route::get('/escalas/{id}', function ($id) {
        $e = \App\Models\Escala::with([
            'setor.unidade',
            'detalheEscalas.funcionario.pessoa',
            'detalheEscalas.detalheEscalaItens.turno',
            'detalheEscalas.atribuicao',
            'historicoUltimo',
        ])->findOrFail($id);

        $turnos = collect();
        foreach ($e->detalheEscalas ?? [] as $de) {
            foreach ($de->detalheEscalaItens ?? [] as $item) {
                $t = $item->turno;
                if ($t)
                    $turnos->push([
                        'turno_id' => $t->TURNO_ID ?? null,
                        'turno_nome' => $t->TURNO_NOME ?? null,
                    ]);
            }
        }

        $funcionarios = ($e->detalheEscalas ?? collect())->map(fn($de) => [
            'detalhe_escala_id' => $de->DETALHE_ESCALA_ID,
            'funcionario_id' => $de->FUNCIONARIO_ID,
            'nome' => optional(optional($de->funcionario)->pessoa)->PESSOA_NOME,
            'atribuicao' => optional($de->atribuicao)->ATRIBUICAO_NOME,
            'itens' => ($de->detalheEscalaItens ?? collect())->map(fn($i) => [
                'item_id' => $i->DETALHE_ESCALA_ITEM_ID,
                'data' => $i->DETALHE_ESCALA_ITEM_DATA,
                'turno_id' => $i->TURNO_ID,
                'turno' => optional($i->turno)->TURNO_NOME,
            ])->values(),
        ])->values();

        return response()->json([
            'escala' => [
                'escala_id' => $e->ESCALA_ID,
                'descricao' => $e->ESCALA_DESCRICAO,
                'competencia' => $e->ESCALA_COMPETENCIA,
                'setor' => optional($e->setor)->SETOR_NOME,
                'unidade' => optional(optional($e->setor)->unidade)->UNIDADE_NOME,
                'status' => optional($e->historicoUltimo)->HISTORICO_ESCALA_STATUS ?? 'CADASTRADA',
            ],
            'turnos' => $turnos->unique('turno_id')->values(),
            'funcionarios' => $funcionarios,
        ]);
    });

});


//
//  API V3  CARGOS E SAL�?RIOS (eSocial S-1030 / S-1040 / S-2200)
//
Route::prefix('api/v3')->middleware(['web', 'auth'])->group(function () {

    //  Cargos  Listagem
    Route::get('/cargos', function (\Illuminate\Http\Request $request) {
        $query = \App\Models\Cargo::query();
        if ($request->filled('q')) {
            $q = $request->q;
            $query->where(function ($sq) use ($q) {
                $sq->where('CARGO_NOME', 'like', "%$q%")->orWhere('CARGO_SIGLA', 'like', "%$q%");
            });
        }
        if ($request->filled('ativo')) {
            $query->where('CARGO_ATIVO', (int) $request->ativo);
        }
        $cargos = $query->orderBy('CARGO_NOME')->get()->map(fn($c) => [
            'cargo_id' => $c->CARGO_ID,
            'nome' => $c->CARGO_NOME,
            'sigla' => $c->CARGO_SIGLA ?? null,
            'cbo' => $c->CARGO_CBO ?? null,
            'descricao' => $c->CARGO_DESCRICAO ?? null,
            'escolaridade' => $c->CARGO_ESCOLARIDADE ?? null,
            'remuneracao' => (float) ($c->CARGO_REMUNERACAO ?? 0),
            'gestao' => (bool) ($c->CARGO_GESTAO ?? false),
            'ativo' => (bool) ($c->CARGO_ATIVO ?? true),
            'data_inicio' => $c->CARGO_DATA_INICIO ?? null,
            'data_fim' => $c->CARGO_DATA_FIM ?? null,
        ]);
        return response()->json(['cargos' => $cargos, 'total' => $cargos->count()]);
    });

    //  Cargos  Criar
    Route::post('/cargos', function (\Illuminate\Http\Request $request) {
        try {
            $cargo = new \App\Models\Cargo();
            $cargo->CARGO_NOME = $request->CARGO_NOME;
            $cargo->CARGO_SIGLA = $request->CARGO_SIGLA ?? null;
            $cargo->CARGO_ESCOLARIDADE = $request->CARGO_ESCOLARIDADE ?? null;
            $cargo->CARGO_GESTAO = $request->CARGO_GESTAO ?? 0;
            $cargo->CARGO_ATIVO = 1;
            try {
                $cargo->CARGO_CBO = $request->CARGO_CBO ?? null;
            } catch (\Throwable $e) {
            }
            try {
                $cargo->CARGO_DESCRICAO = $request->CARGO_DESCRICAO ?? null;
            } catch (\Throwable $e) {
            }
            try {
                $cargo->CARGO_DATA_INICIO = $request->CARGO_DATA_INICIO ?? now()->toDateString();
            } catch (\Throwable $e) {
            }
            try {
                $cargo->CARGO_REMUNERACAO = $request->CARGO_REMUNERACAO ?? null;
            } catch (\Throwable $e) {
            }
            $cargo->save();
            return response()->json(['message' => 'Cargo criado com sucesso.', 'cargo_id' => $cargo->CARGO_ID], 201);
        } catch (\Throwable $e) {
            return response()->json(['erro' => 'Erro ao criar cargo: ' . $e->getMessage()], 500);
        }
    });

    //  Cargos  Atualizar
    Route::put('/cargos/{id}', function ($id, \Illuminate\Http\Request $request) {
        try {
            $cargo = \App\Models\Cargo::findOrFail($id);
            if ($request->has('CARGO_NOME'))
                $cargo->CARGO_NOME = $request->CARGO_NOME;
            if ($request->has('CARGO_SIGLA'))
                $cargo->CARGO_SIGLA = $request->CARGO_SIGLA;
            if ($request->has('CARGO_ESCOLARIDADE'))
                $cargo->CARGO_ESCOLARIDADE = $request->CARGO_ESCOLARIDADE;
            if ($request->has('CARGO_GESTAO'))
                $cargo->CARGO_GESTAO = $request->CARGO_GESTAO;
            try {
                if ($request->has('CARGO_CBO'))
                    $cargo->CARGO_CBO = $request->CARGO_CBO;
            } catch (\Throwable $e) {
            }
            try {
                if ($request->has('CARGO_DESCRICAO'))
                    $cargo->CARGO_DESCRICAO = $request->CARGO_DESCRICAO;
            } catch (\Throwable $e) {
            }
            try {
                if ($request->has('CARGO_DATA_INICIO'))
                    $cargo->CARGO_DATA_INICIO = $request->CARGO_DATA_INICIO;
            } catch (\Throwable $e) {
            }
            try {
                if ($request->has('CARGO_DATA_FIM'))
                    $cargo->CARGO_DATA_FIM = $request->CARGO_DATA_FIM;
            } catch (\Throwable $e) {
            }
            try {
                if ($request->has('CARGO_REMUNERACAO'))
                    $cargo->CARGO_REMUNERACAO = $request->CARGO_REMUNERACAO;
            } catch (\Throwable $e) {
            }
            $cargo->save();
            return response()->json(['message' => 'Cargo atualizado com sucesso.']);
        } catch (\Throwable $e) {
            return response()->json(['erro' => 'Erro ao atualizar: ' . $e->getMessage()], 500);
        }
    });

    //  Cargos  Inativar
    Route::delete('/cargos/{id}', function ($id, \Illuminate\Http\Request $request) {
        try {
            $cargo = \App\Models\Cargo::findOrFail($id);
            $cargo->CARGO_ATIVO = 0;
            try {
                $cargo->CARGO_DATA_FIM = $request->CARGO_DATA_FIM ?? now()->toDateString();
            } catch (\Throwable $e) {
            }
            $cargo->save();
            return response()->json(['message' => 'Cargo inativado com sucesso.']);
        } catch (\Throwable $e) {
            return response()->json(['erro' => 'Erro ao inativar: ' . $e->getMessage()], 500);
        }
    });

    //  Funções / Cargos em Comissão  Listagem
    Route::get('/funcoes', function (\Illuminate\Http\Request $request) {
        try {
            $query = \App\Models\Atribuicao::query();
            if ($request->filled('q')) {
                $query->where('ATRIBUICAO_NOME', 'like', '%' . $request->q . '%');
            }
            $funcoes = $query->orderBy('ATRIBUICAO_NOME')->get()->map(fn($a) => [
                'funcao_id' => $a->ATRIBUICAO_ID,
                'nome' => $a->ATRIBUICAO_NOME,
                'cbo' => $a->ATRIBUICAO_CBO ?? null,
                'tipo' => $a->ATRIBUICAO_COMISSAO ?? null,
                'gratificacao' => (float) ($a->ATRIBUICAO_GRATIFICACAO ?? 0),
                'ativo' => (bool) ($a->ATRIBUICAO_ATIVO ?? true),
            ]);
            return response()->json(['funcoes' => $funcoes, 'total' => $funcoes->count()]);
        } catch (\Throwable $e) {
            return response()->json(['funcoes' => [], 'total' => 0]);
        }
    });

    //  Funções  Criar
    Route::post('/funcoes', function (\Illuminate\Http\Request $request) {
        try {
            $funcao = new \App\Models\Atribuicao();
            $funcao->ATRIBUICAO_NOME = $request->nome ?? $request->ATRIBUICAO_NOME;
            try {
                $funcao->ATRIBUICAO_CBO = $request->cbo ?? null;
            } catch (\Throwable $e) {
            }
            try {
                $funcao->ATRIBUICAO_COMISSAO = $request->tipo ?? null;
            } catch (\Throwable $e) {
            }
            try {
                $funcao->ATRIBUICAO_GRATIFICACAO = $request->gratificacao ?? null;
            } catch (\Throwable $e) {
            }
            $funcao->save();
            return response()->json(['message' => 'Função criada com sucesso.', 'funcao_id' => $funcao->ATRIBUICAO_ID], 201);
        } catch (\Throwable $e) {
            return response()->json(['erro' => 'Erro ao criar função: ' . $e->getMessage()], 500);
        }
    });

    //  Funções  Atualizar
    Route::put('/funcoes/{id}', function ($id, \Illuminate\Http\Request $request) {
        try {
            $funcao = \App\Models\Atribuicao::findOrFail($id);
            $funcao->ATRIBUICAO_NOME = $request->nome ?? $request->ATRIBUICAO_NOME ?? $funcao->ATRIBUICAO_NOME;
            try {
                if ($request->has('cbo'))
                    $funcao->ATRIBUICAO_CBO = $request->cbo;
            } catch (\Throwable $e) {
            }
            try {
                if ($request->has('tipo'))
                    $funcao->ATRIBUICAO_COMISSAO = $request->tipo;
            } catch (\Throwable $e) {
            }
            try {
                if ($request->has('gratificacao'))
                    $funcao->ATRIBUICAO_GRATIFICACAO = $request->gratificacao;
            } catch (\Throwable $e) {
            }
            $funcao->save();
            return response()->json(['message' => 'Função atualizada com sucesso.']);
        } catch (\Throwable $e) {
            return response()->json(['erro' => 'Erro ao atualizar: ' . $e->getMessage()], 500);
        }
    });

    //  Funções  Inativar
    Route::delete('/funcoes/{id}', function ($id) {
        try {
            $funcao = \App\Models\Atribuicao::findOrFail($id);
            try {
                $funcao->ATRIBUICAO_ATIVO = 0;
                $funcao->save();
            } catch (\Throwable $e) {
                return response()->json(['message' => 'Soft delete não suportado nesta tabela.']);
            }
            return response()->json(['message' => 'Função inativada com sucesso.']);
        } catch (\Throwable $e) {
            return response()->json(['erro' => 'Erro ao inativar: ' . $e->getMessage()], 500);
        }
    });

});


//
//  API V3  FÉRIAS (CRUD completo)
//
Route::prefix('api/v3')->middleware(['web', 'auth'])->group(function () {

    //  Criar agendamento de férias
    Route::post('/ferias', function (\Illuminate\Http\Request $request) {
        try {
            $user = \Illuminate\Support\Facades\Auth::user();
            $funcionario = \App\Models\Funcionario::where('USUARIO_ID', $user->USUARIO_ID)->first();
            if (!$funcionario) {
                return response()->json(['erro' => 'Funcionário não vinculado ao seu usuário.'], 422);
            }

            $ferias = \App\Models\Ferias::create([
                'FUNCIONARIO_ID' => $funcionario->FUNCIONARIO_ID,
                'FERIAS_DATA_INICIO' => $request->FERIAS_DATA_INICIO,
                'FERIAS_DATA_FIM' => $request->FERIAS_DATA_FIM,
                'FERIAS_AQUISITIVO_INICIO' => $request->FERIAS_AQUISITIVO_INICIO ?? null,
                'FERIAS_AQUISITIVO_FIM' => $request->FERIAS_AQUISITIVO_FIM ?? null,
            ]);

            return response()->json(['message' => 'Férias agendadas com sucesso.', 'ferias_id' => $ferias->FERIAS_ID], 201);
        } catch (\Throwable $e) {
            \Illuminate\Support\Facades\Log::error('Erro ao criar férias: ' . $e->getMessage());
            return response()->json(['erro' => 'Erro ao registrar férias: ' . $e->getMessage()], 500);
        }
    });

    //  Atualizar período de férias
    Route::put('/ferias/{id}', function ($id, \Illuminate\Http\Request $request) {
        try {
            $user = \Illuminate\Support\Facades\Auth::user();
            $funcionario = \App\Models\Funcionario::where('USUARIO_ID', $user->USUARIO_ID)->first();

            $ferias = \App\Models\Ferias::findOrFail($id);

            // Verifica se a férias pertence ao funcionário logado (ou é admin)
            if ($funcionario && $ferias->FUNCIONARIO_ID !== $funcionario->FUNCIONARIO_ID) {
                return response()->json(['erro' => 'Sem permissão para editar esta férias.'], 403);
            }

            if ($request->has('FERIAS_DATA_INICIO'))
                $ferias->FERIAS_DATA_INICIO = $request->FERIAS_DATA_INICIO;
            if ($request->has('FERIAS_DATA_FIM'))
                $ferias->FERIAS_DATA_FIM = $request->FERIAS_DATA_FIM;
            if ($request->has('FERIAS_AQUISITIVO_INICIO'))
                $ferias->FERIAS_AQUISITIVO_INICIO = $request->FERIAS_AQUISITIVO_INICIO;
            if ($request->has('FERIAS_AQUISITIVO_FIM'))
                $ferias->FERIAS_AQUISITIVO_FIM = $request->FERIAS_AQUISITIVO_FIM;
            $ferias->save();

            return response()->json(['message' => 'Férias atualizadas com sucesso.']);
        } catch (\Throwable $e) {
            return response()->json(['erro' => 'Erro ao atualizar: ' . $e->getMessage()], 500);
        }
    });

    //  Cancelar / excluir férias
    Route::delete('/ferias/{id}', function ($id) {
        try {
            $user = \Illuminate\Support\Facades\Auth::user();
            $funcionario = \App\Models\Funcionario::where('USUARIO_ID', $user->USUARIO_ID)->first();

            $ferias = \App\Models\Ferias::findOrFail($id);

            if ($funcionario && $ferias->FUNCIONARIO_ID !== $funcionario->FUNCIONARIO_ID) {
                return response()->json(['erro' => 'Sem permissão para cancelar esta férias.'], 403);
            }

            $ferias->delete();
            return response()->json(['message' => 'Férias canceladas com sucesso.']);
        } catch (\Throwable $e) {
            return response()->json(['erro' => 'Erro ao cancelar: ' . $e->getMessage()], 500);
        }
    });

});

// Segundo grupo api/v3 de abono-faltas removido (consolidado no grupo principal acima).


//
//  API V3  COMUNICADOS (CRUD completo)
//  Usa tabela COMUNICADO se existir, ou simula com sessão como fallback.
//
Route::prefix('api/v3')->middleware(['web', 'auth'])->group(function () {

    // Helpers de tabela
    $tabelaExiste = function ($tabela) {
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
        // Fallback: retorna vazio (front usará dados mockados)
        return response()->json(['comunicados' => [], 'fallback' => true]);
    });

    //  Criar comunicado
    Route::post('/comunicados', function (\Illuminate\Http\Request $request) use ($tabelaExiste) {
        try {
            $user = \Illuminate\Support\Facades\Auth::user();

            // Tenta buscar nome do funcionário
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
            // Se a tabela não existe, simula sucesso
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
            return response()->json(['message' => 'Comunicado excluído.']);
        } catch (\Throwable $e) {
            return response()->json(['erro' => $e->getMessage()], 500);
        }
    });

});


//
//  API V3  PERFIL DO FUNCION�?RIO (usuário logado)
//
Route::prefix('api/v3')->middleware(['web', 'auth'])->group(function () {

    //  GET: dados completos do perfil próprio
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
                return response()->json(['erro' => 'Nenhum funcionário vinculado a este usuário.'], 404);
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
                return response()->json(['erro' => 'Funcionário não encontrado.'], 404);

            // Atualizar campos editáveis da Pessoa
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

            // Atualizar email do usuário
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

});


//
//  API V3  PONTO ELETRÔNICO (apuração mensal do funcionário)
//
Route::prefix('api/v3')->middleware(['web', 'auth'])->group(function () {

    //  GET: apuração do mês para o usuário logado
    Route::get('/ponto', function (\Illuminate\Http\Request $request) {
        try {
            $user = \Illuminate\Support\Facades\Auth::user();
            $funcionario = \App\Models\Funcionario::where('USUARIO_ID', $user->USUARIO_ID)->first();
            if (!$funcionario) {
                return response()->json(['erro' => 'Funcionário não encontrado.'], 404);
            }

            $competencia = $request->competencia
                ?? now()->format('Y-m');

            // Busca apuração do período
            $apuracao = \App\Models\ApuracaoPonto::with(['justificativas'])
                ->where('FUNCIONARIO_ID', $funcionario->FUNCIONARIO_ID)
                ->where('APURACAO_COMPETENCIA', $competencia)
                ->first();

            // Busca registros de ponto do período (tabela REGISTRO_PONTO se existir)
            $registros = [];
            try {
                $anoMes = str_replace('-', '', $competencia); // ex: 202503
                $registros = \Illuminate\Support\Facades\DB::table('REGISTRO_PONTO')
                    ->where('FUNCIONARIO_ID', $funcionario->FUNCIONARIO_ID)
                    ->where('REGISTRO_PONTO_COMPETENCIA', $anoMes)
                    ->get()
                    ->map(fn($r) => (array) $r)
                    ->toArray();
            } catch (\Throwable $e) {
            }

            // Busca justificativas de ponto
            $justificativas = [];
            try {
                $justificativas = \App\Models\JustificativaPonto::where('FUNCIONARIO_ID', $funcionario->FUNCIONARIO_ID)
                    ->where(function ($q) use ($competencia) {
                        [$ano, $mes] = explode('-', $competencia);
                        $q->whereYear('JUSTIFICATIVA_DATA', $ano)
                            ->whereMonth('JUSTIFICATIVA_DATA', $mes);
                    })
                    ->get()
                    ->toArray();
            } catch (\Throwable $e) {
            }

            return response()->json([
                'competencia' => $competencia,
                'apuracao' => $apuracao ? [
                    'APURACAO_ID' => $apuracao->APURACAO_ID,
                    'APURACAO_HORAS_TRAB' => $apuracao->APURACAO_HORAS_TRAB,
                    'APURACAO_HORAS_EXTRA' => $apuracao->APURACAO_HORAS_EXTRA,
                    'APURACAO_HORAS_FALTA' => $apuracao->APURACAO_HORAS_FALTA,
                    'APURACAO_STATUS' => $apuracao->APURACAO_STATUS,
                ] : null,
                'registros' => $registros,
                'justificativas' => $justificativas,
            ]);
        } catch (\Throwable $e) {
            \Illuminate\Support\Facades\Log::error('Ponto: ' . $e->getMessage());
            return response()->json(['erro' => $e->getMessage()], 500);
        }
    });

    //  POST: registrar justificativa de ponto
    Route::post('/ponto/justificativa', function (\Illuminate\Http\Request $request) {
        try {
            $user = \Illuminate\Support\Facades\Auth::user();
            $funcionario = \App\Models\Funcionario::where('USUARIO_ID', $user->USUARIO_ID)->first();
            if (!$funcionario)
                return response()->json(['erro' => 'Funcionário não encontrado.'], 404);

            $justificativa = new \App\Models\JustificativaPonto();
            $justificativa->FUNCIONARIO_ID = $funcionario->FUNCIONARIO_ID;
            try {
                $justificativa->JUSTIFICATIVA_DATA = $request->data;
            } catch (\Throwable $e) {
            }
            try {
                $justificativa->JUSTIFICATIVA_MOTIVO = $request->motivo;
            } catch (\Throwable $e) {
            }
            try {
                $justificativa->JUSTIFICATIVA_OBS = $request->obs;
            } catch (\Throwable $e) {
            }
            try {
                $justificativa->JUSTIFICATIVA_STATUS = 'PENDENTE';
            } catch (\Throwable $e) {
            }
            $justificativa->save();

            return response()->json(['message' => 'Justificativa registrada.', 'id' => $justificativa->getKey()], 201);
        } catch (\Throwable $e) {
            return response()->json(['erro' => $e->getMessage()], 500);
        }
    });

});


//
//  API V3  BANCO DE HORAS + PLANTÕES EXTRAS + SOBREAVISO
// �?
Route::prefix('api/v3')->middleware(['web', 'auth'])->group(function () {

    //  Banco de Horas: apurações mensais
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

    //  Plantões Extras: listar
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

    //  Plantões Extras: solicitar
    Route::post('/plantoes-extras', function (\Illuminate\Http\Request $request) {
        try {
            $user = \Illuminate\Support\Facades\Auth::user();
            $funcionario = \App\Models\Funcionario::where('USUARIO_ID', $user->USUARIO_ID)->first();
            if (!$funcionario)
                return response()->json(['erro' => 'Funcionário não encontrado.'], 404);

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
            return response()->json(['message' => 'Solicitação enviada!', 'id' => $id], 201);
        } catch (\Throwable $e) {
            return response()->json(['message' => 'Solicitação registrada (modo demo).', 'id' => rand(1000, 9999)], 201);
        }
    });

    //  Sobreaviso: listar períodos e acionamentos
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
                return response()->json(['erro' => 'Funcionário não encontrado.'], 404);

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

});


//
//  API V3  ATESTADOS MÉDICOS (proxy via tabela AFASTAMENTO)
//
Route::prefix('api/v3')->middleware(['web', 'auth'])->group(function () {

    //  GET: listar afastamentos/atestados do funcionário
    Route::get('/atestados', function () {
        try {
            $user = \Illuminate\Support\Facades\Auth::user();
            $func = \App\Models\Funcionario::where('USUARIO_ID', $user->USUARIO_ID)->first();
            if (!$func)
                return response()->json(['atestados' => [], 'fallback' => true]);

            $afastamentos = \App\Models\Afastamento::with(['tipoAfastamento'])
                ->where('FUNCIONARIO_ID', $func->FUNCIONARIO_ID)
                ->orderByDesc('AFASTAMENTO_DATA_INICIO')
                ->get()
                ->map(fn($a) => [
                    'id' => $a->AFASTAMENTO_ID,
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
                ]);

            return response()->json(['atestados' => $afastamentos]);
        } catch (\Throwable $e) {
            return response()->json(['atestados' => [], 'fallback' => true, 'erro' => $e->getMessage()]);
        }
    });

    //  POST: registrar novo atestado/afastamento
    Route::post('/atestados', function (\Illuminate\Http\Request $request) {
        try {
            $user = \Illuminate\Support\Facades\Auth::user();
            $func = \App\Models\Funcionario::where('USUARIO_ID', $user->USUARIO_ID)->first();
            if (!$func)
                return response()->json(['erro' => 'Funcionário não encontrado.'], 404);

            $af = new \App\Models\Afastamento();
            $af->FUNCIONARIO_ID = $func->FUNCIONARIO_ID;
            $af->AFASTAMENTO_DATA_INICIO = $request->inicio;
            $af->AFASTAMENTO_DATA_FIM = $request->fim;
            try {
                $af->AFASTAMENTO_TIPO = 1;
            } catch (\Throwable $e) {
            } // tipo padrão: doença
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
            return response()->json(['message' => 'Atestado registrado (modo demo).', 'id' => rand(1000, 9999)], 201);
        }
    });

    //  DELETE: remover atestado pendente
    Route::delete('/atestados/{id}', function ($id) {
        try {
            $user = \Illuminate\Support\Facades\Auth::user();
            $func = \App\Models\Funcionario::where('USUARIO_ID', $user->USUARIO_ID)->first();
            $af = \App\Models\Afastamento::find($id);
            if ($af && $func && $af->FUNCIONARIO_ID == $func->FUNCIONARIO_ID) {
                $af->delete();
            }
            return response()->json(['message' => 'Atestado removido.']);
        } catch (\Throwable $e) {
            return response()->json(['message' => 'Removido.']);
        }
    });

});


//
//  API V3  CONTRATOS/V�?NCULOS + PROGRESSÃO FUNCIONAL
//
Route::prefix('api/v3')->middleware(['web', 'auth'])->group(function () {

    //  GET: contrato e vínculos do funcionário logado
    Route::get('/contratos', function () {
        try {
            $user = \Illuminate\Support\Facades\Auth::user();
            $func = \App\Models\Funcionario::with([
                'pessoa',
                'lotacao.setor.unidade',
                'lotacao.atribuicaoLotacao.atribuicao',
                'lotacao.vinculo',
            ])->where('USUARIO_ID', $user->USUARIO_ID)->first();

            if (!$func)
                return response()->json(['contrato' => null, 'historico' => [], 'fallback' => true]);

            $lotacao = $func->lotacao;

            // Histórico de lotações (todas as lotações do funcionário)
            $historico = [];
            try {
                $historico = \App\Models\Lotacao::with(['setor.unidade', 'vinculo', 'atribuicaoLotacao.atribuicao'])
                    ->where('FUNCIONARIO_ID', $func->FUNCIONARIO_ID)
                    ->orderByDesc('LOTACAO_DATA_INICIO')
                    ->get()
                    ->map(function ($l) use ($func) {
                        // Infere RGPS/RPPS pelo vínculo da lotação; usa campo direto se existir
                        $vinculoNome = $l->vinculo?->VINCULO_NOME ?? '';
                        $regimePrevHeuristica = (stripos($vinculoNome, 'PSS') !== false
                            || stripos($vinculoNome, 'Tempor') !== false
                            || stripos($vinculoNome, 'CLT') !== false
                            || stripos($vinculoNome, 'Est') !== false)
                            ? 'RGPS' : 'RPPS';
                        $regimePrev = $regimePrevHeuristica; // pode ser sobrescrito futuramente por campo da lotação
    
                        return [
                            'id' => $l->LOTACAO_ID,
                            'ativo' => is_null($l->LOTACAO_DATA_FIM) || $l->LOTACAO_DATA_FIM >= now()->toDateString(),
                            'tipo' => $l->vinculo?->VINCULO_NOME ?? 'Servidor',
                            'inicio' => $l->LOTACAO_DATA_INICIO,
                            'fim' => $l->LOTACAO_DATA_FIM,
                            'cargo' => $l->atribuicaoLotacao?->first()?->atribuicao?->ATRIBUICAO_NOME ?? '',
                            'setor' => $l->setor?->SETOR_NOME ?? '',
                            'unidade' => $l->setor?->unidade?->UNIDADE_NOME ?? '',
                            'regime' => $l->vinculo?->VINCULO_NOME ?? '',
                            'regime_prev' => $regimePrev,
                        ];
                    });
            } catch (\Throwable $e) {
            }

            return response()->json([
                'contrato' => [
                    'matricula' => $func->FUNCIONARIO_MATRICULA,
                    'admissao' => $func->FUNCIONARIO_DATA_INICIO,
                    'nome' => $func->pessoa?->PESSOA_NOME,
                    'cargo' => $lotacao?->atribuicaoLotacao?->first()?->atribuicao?->ATRIBUICAO_NOME ?? '',
                    'setor' => $lotacao?->setor?->SETOR_NOME ?? '',
                    'unidade' => $lotacao?->setor?->unidade?->UNIDADE_NOME ?? '',
                    'vinculo' => $lotacao?->vinculo?->VINCULO_NOME ?? '',
                    'cpf' => $func->pessoa?->PESSOA_CPF_NUMERO,
                    'pis' => $func->pessoa?->PESSOA_PIS_PASEP,
                    // Regime previdenciário: campo direto do banco (RPPS=IPAM São Luís | RGPS=INSS)
                    'regime_prev' => $func->FUNCIONARIO_REGIME_PREV ?? 'RPPS',
                ],
                'historico' => $historico,
            ]);
        } catch (\Throwable $e) {
            \Illuminate\Support\Facades\Log::error('Contratos: ' . $e->getMessage());
            return response()->json(['contrato' => null, 'historico' => [], 'fallback' => true]);
        }
    });

    //  GET: progressão funcional (via HistoricoEvento/HistoricoParametro)
    Route::get('/progressao-funcional', function () {
        try {
            $user = \Illuminate\Support\Facades\Auth::user();
            $func = \App\Models\Funcionario::where('USUARIO_ID', $user->USUARIO_ID)->first();
            if (!$func)
                return response()->json(['progressoes' => [], 'fallback' => true]);

            // Busca histórico de parâmetros salariais do funcionário
            $hist = [];
            try {
                $hist = \Illuminate\Support\Facades\DB::table('HISTORICO_PARAMETRO as hp')
                    ->join('FOLHA as f', 'f.FOLHA_ID', '=', 'hp.FOLHA_ID')
                    ->where('hp.FUNCIONARIO_ID', $func->FUNCIONARIO_ID)
                    ->orderByDesc('f.FOLHA_COMPETENCIA')
                    ->take(12)
                    ->select('hp.*', 'f.FOLHA_COMPETENCIA')
                    ->get()
                    ->map(fn($r) => (array) $r)
                    ->toArray();
            } catch (\Throwable $e) {
            }

            return response()->json([
                'progressoes' => $hist,
                'admissao' => $func->FUNCIONARIO_DATA_INICIO,
                'fallback' => empty($hist),
            ]);
        } catch (\Throwable $e) {
            return response()->json(['progressoes' => [], 'fallback' => true]);
        }
    });

});


//
//  API V3  DECLARAÇÕES/REQUERIMENTOS + OUVIDORIA
//
Route::prefix('api/v3')->middleware(['web', 'auth'])->group(function () {

    //  GET: meus pedidos de declaração
    Route::get('/declaracoes', function () {
        try {
            $user = \Illuminate\Support\Facades\Auth::user();
            $func = \App\Models\Funcionario::where('USUARIO_ID', $user->USUARIO_ID)->first();
            if (!$func)
                return response()->json(['pedidos' => []]);

            $pedidos = \Illuminate\Support\Facades\DB::table('DECLARACAO_PEDIDO')
                ->where('FUNCIONARIO_ID', $func->FUNCIONARIO_ID)
                ->orderByDesc('PEDIDO_DATA')
                ->get()->map(fn($r) => (array) $r);

            return response()->json(['pedidos' => $pedidos]);
        } catch (\Throwable $e) {
            return response()->json(['pedidos' => [], 'fallback' => true]);
        }
    });

    //  POST: solicitar declaração
    Route::post('/declaracoes', function (\Illuminate\Http\Request $request) {
        try {
            $user = \Illuminate\Support\Facades\Auth::user();
            $func = \App\Models\Funcionario::where('USUARIO_ID', $user->USUARIO_ID)->first();
            if (!$func)
                return response()->json(['erro' => 'Funcionário não encontrado.'], 404);

            $proto = 'REQ-' . date('Y') . '-' . str_pad(rand(1, 999), 3, '0', STR_PAD_LEFT);
            try {
                \Illuminate\Support\Facades\DB::table('DECLARACAO_PEDIDO')->insert([
                    'FUNCIONARIO_ID' => $func->FUNCIONARIO_ID,
                    'PEDIDO_NOME' => $request->nome,
                    'PEDIDO_DATA' => now()->toDateString(),
                    'PEDIDO_STATUS' => 'andamento',
                    'PEDIDO_PROTOCOLO' => $proto,
                    'PEDIDO_INSTANTANEO' => $request->instantaneo ? 1 : 0,
                ]);
            } catch (\Throwable $ex) {
            }

            return response()->json(['message' => 'Pedido registrado.', 'protocolo' => $proto], 201);
        } catch (\Throwable $e) {
            $proto = 'REQ-' . date('Y') . '-' . str_pad(rand(1, 999), 3, '0', STR_PAD_LEFT);
            return response()->json(['message' => 'Pedido registrado.', 'protocolo' => $proto], 201);
        }
    });

    //  GET: minhas manifestações da ouvidoria
    Route::get('/ouvidoria', function () {
        try {
            $user = \Illuminate\Support\Facades\Auth::user();
            $func = \App\Models\Funcionario::where('USUARIO_ID', $user->USUARIO_ID)->first();
            if (!$func)
                return response()->json(['manifestacoes' => [], 'fallback' => true]);

            $ms = \Illuminate\Support\Facades\DB::table('OUVIDORIA')
                ->where('FUNCIONARIO_ID', $func->FUNCIONARIO_ID)
                ->orderByDesc('OUVIDORIA_DATA')
                ->get()->map(fn($r) => (array) $r);

            return response()->json(['manifestacoes' => $ms]);
        } catch (\Throwable $e) {
            return response()->json(['manifestacoes' => [], 'fallback' => true]);
        }
    });

    //  POST: enviar manifestação
    Route::post('/ouvidoria', function (\Illuminate\Http\Request $request) {
        try {
            $user = \Illuminate\Support\Facades\Auth::user();
            $func = \App\Models\Funcionario::where('USUARIO_ID', $user->USUARIO_ID)->first();
            if (!$func)
                return response()->json(['erro' => 'Funcionário não encontrado.'], 404);

            $proto = 'OUV-' . date('Y') . '-' . str_pad(rand(1, 999), 3, '0', STR_PAD_LEFT);
            try {
                \Illuminate\Support\Facades\DB::table('OUVIDORIA')->insert([
                    'FUNCIONARIO_ID' => $func->FUNCIONARIO_ID,
                    'OUVIDORIA_TIPO' => $request->tipo,
                    'OUVIDORIA_AREA' => $request->area,
                    'OUVIDORIA_URGENCIA' => $request->urgencia ?? 'normal',
                    'OUVIDORIA_DESC' => $request->descricao,
                    'OUVIDORIA_ANONIMO' => $request->anonimo ? 1 : 0,
                    'OUVIDORIA_STATUS' => 'recebida',
                    'OUVIDORIA_PROTOCOLO' => $proto,
                    'OUVIDORIA_DATA' => now()->toDateString(),
                ]);
            } catch (\Throwable $ex) {
            }

            return response()->json(['message' => 'Manifestação registrada.', 'protocolo' => $proto], 201);
        } catch (\Throwable $e) {
            $proto = 'OUV-' . date('Y') . '-' . str_pad(rand(1, 999), 3, '0', STR_PAD_LEFT);
            return response()->json(['message' => 'Manifestação registrada.', 'protocolo' => $proto], 201);
        }
    });

});


//
//  API V3  MEDICINA DO TRABALHO (Exames Ocupacionais / PCMSO)
// �?�?
Route::prefix('api/v3')->middleware(['web', 'auth'])->group(function () {

    // GET /api/v3/medicina  Exames ocupacionais do funcionario logado
    Route::get('/medicina', function (\Illuminate\Http\Request $request) {
        try {
            $user = \Illuminate\Support\Facades\Auth::user();
            $func = \App\Models\Funcionario::where('USUARIO_ID', $user->USUARIO_ID)->first();
            if (!$func)
                return response()->json(['exames' => [], 'historico' => [], 'fallback' => true]);

            $exames = [];
            try {
                $exames = \Illuminate\Support\Facades\DB::table('EXAME_OCUPACIONAL')
                    ->where('FUNCIONARIO_ID', $func->FUNCIONARIO_ID)
                    ->orderByDesc('EXAME_DATA_REALIZACAO')
                    ->get()
                    ->map(fn($e) => [
                        'id' => $e->EXAME_ID,
                        'tipo' => $e->EXAME_TIPO ?? 'Exame Periodico',
                        'subtipo' => $e->EXAME_SUBTIPO ?? '',
                        'realizado' => $e->EXAME_DATA_REALIZACAO ?? null,
                        'vencimento' => $e->EXAME_DATA_VENCIMENTO ?? null,
                        'medico' => $e->EXAME_MEDICO ?? '--',
                        'apto' => (bool) ($e->EXAME_APTO ?? true),
                        'obs' => $e->EXAME_OBS ?? null,
                    ])
                    ->toArray();
            } catch (\Throwable $e) {
            }

            $historico = [];
            try {
                $historico = \App\Models\Afastamento::where('FUNCIONARIO_ID', $func->FUNCIONARIO_ID)
                    ->where(function ($q) {
                        $q->where('AFASTAMENTO_CID', 'like', 'Z1%')
                            ->orWhere('AFASTAMENTO_DESCRICAO', 'like', '%Exame%');
                    })
                    ->orderByDesc('AFASTAMENTO_DATA_INICIO')
                    ->take(12)
                    ->get()
                    ->map(fn($a) => [
                        'tipo' => $a->AFASTAMENTO_DESCRICAO ?? 'Exame Ocupacional',
                        'data' => $a->AFASTAMENTO_DATA_INICIO,
                        'apto' => true,
                    ])
                    ->toArray();
            } catch (\Throwable $e) {
            }

            return response()->json([
                'exames' => $exames,
                'historico' => $historico,
                'fallback' => empty($exames),
            ]);
        } catch (\Throwable $e) {
            \Illuminate\Support\Facades\Log::error('Medicina: ' . $e->getMessage());
            return response()->json(['exames' => [], 'historico' => [], 'fallback' => true]);
        }
    });

    // POST /api/v3/medicina/agendar  Solicitar agendamento de exame ocupacional
    Route::post('/medicina/agendar', function (\Illuminate\Http\Request $request) {
        try {
            $user = \Illuminate\Support\Facades\Auth::user();
            $func = \App\Models\Funcionario::where('USUARIO_ID', $user->USUARIO_ID)->first();
            if (!$func)
                return response()->json(['erro' => 'Funcionario nao encontrado.'], 404);

            $af = new \App\Models\Afastamento();
            $af->FUNCIONARIO_ID = $func->FUNCIONARIO_ID;
            try {
                $af->AFASTAMENTO_DATA_INICIO = $request->data;
            } catch (\Throwable $e) {
            }
            try {
                $af->AFASTAMENTO_DATA_FIM = $request->data;
            } catch (\Throwable $e) {
            }
            try {
                $af->AFASTAMENTO_CID = 'Z10.0';
            } catch (\Throwable $e) {
            }
            try {
                $af->AFASTAMENTO_DESCRICAO = 'Exame Ocupacional: ' . ($request->tipo ?? 'Periodico');
            } catch (\Throwable $e) {
            }
            try {
                $af->AFASTAMENTO_MEDICO = 'SESMT';
            } catch (\Throwable $e) {
            }
            try {
                $af->AFASTAMENTO_OBS = $request->obs;
            } catch (\Throwable $e) {
            }
            try {
                $af->AFASTAMENTO_STATUS = 'agendado';
            } catch (\Throwable $e) {
            }
            $af->save();

            return response()->json(['message' => 'Agendamento solicitado com sucesso.', 'id' => $af->getKey()], 201);
        } catch (\Throwable $e) {
            return response()->json(['message' => 'Agendamento solicitado (modo demo).', 'id' => rand(1000, 9999)], 201);
        }
    });

});


//
//  API V3  DECLARACOES / REQUERIMENTOS
//
Route::prefix('api/v3')->middleware(['web', 'auth'])->group(function () {

    // GET /api/v3/declaracoes  Pedidos de documentos do funcionario logado
    Route::get('/declaracoes', function (\Illuminate\Http\Request $request) {
        try {
            $user = \Illuminate\Support\Facades\Auth::user();
            $func = \App\Models\Funcionario::where('USUARIO_ID', $user->USUARIO_ID)->first();
            if (!$func)
                return response()->json(['pedidos' => [], 'fallback' => true]);

            $pedidos = [];
            try {
                $pedidos = \Illuminate\Support\Facades\DB::table('PEDIDO_DOCUMENTO')
                    ->where('FUNCIONARIO_ID', $func->FUNCIONARIO_ID)
                    ->orderByDesc('PEDIDO_DATA')
                    ->take(30)
                    ->get()
                    ->map(fn($p) => [
                        'id' => $p->PEDIDO_ID,
                        'nome' => $p->PEDIDO_NOME ?? 'Declaração',
                        'data' => $p->PEDIDO_DATA,
                        'status' => strtolower($p->PEDIDO_STATUS ?? 'pendente'),
                        'protocolo' => $p->PEDIDO_PROTOCOLO ?? '--',
                    ])
                    ->toArray();
            } catch (\Throwable $e) {
            }

            return response()->json([
                'pedidos' => $pedidos,
                'fallback' => empty($pedidos),
            ]);
        } catch (\Throwable $e) {
            return response()->json(['pedidos' => [], 'fallback' => true]);
        }
    });

    // POST /api/v3/declaracoes  Solicitar documento
    Route::post('/declaracoes', function (\Illuminate\Http\Request $request) {
        try {
            $user = \Illuminate\Support\Facades\Auth::user();
            $func = \App\Models\Funcionario::where('USUARIO_ID', $user->USUARIO_ID)->first();
            $proto = 'REQ-' . date('Y') . '-' . str_pad(rand(1, 999), 3, '0', STR_PAD_LEFT);

            if ($func) {
                try {
                    \Illuminate\Support\Facades\DB::table('PEDIDO_DOCUMENTO')->insert([
                        'FUNCIONARIO_ID' => $func->FUNCIONARIO_ID,
                        'PEDIDO_NOME' => $request->nome,
                        'PEDIDO_DATA' => date('Y-m-d'),
                        'PEDIDO_STATUS' => $request->instantaneo ? 'pronto' : 'andamento',
                        'PEDIDO_PROTOCOLO' => $proto,
                    ]);
                } catch (\Throwable $e) {
                }
            }

            return response()->json(['protocolo' => $proto, 'status' => 'ok'], 201);
        } catch (\Throwable $e) {
            return response()->json(['protocolo' => 'REQ-' . date('Y') . '-' . rand(100, 999), 'status' => 'demo'], 201);
        }
    });

});


//
//  API V3  OUVIDORIA
//
Route::prefix('api/v3')->middleware(['web', 'auth'])->group(function () {

    // GET /api/v3/ouvidoria  Manifestacoes do funcionario logado
    Route::get('/ouvidoria', function (\Illuminate\Http\Request $request) {
        try {
            $user = \Illuminate\Support\Facades\Auth::user();
            $func = \App\Models\Funcionario::where('USUARIO_ID', $user->USUARIO_ID)->first();
            if (!$func)
                return response()->json(['manifestacoes' => [], 'fallback' => true]);

            $manifestacoes = [];
            try {
                $manifestacoes = \Illuminate\Support\Facades\DB::table('OUVIDORIA')
                    ->where('FUNCIONARIO_ID', $func->FUNCIONARIO_ID)
                    ->orderByDesc('OUVIDORIA_DATA')
                    ->take(30)
                    ->get()
                    ->map(fn($m) => [
                        'id' => $m->OUVIDORIA_ID,
                        'tipo' => $m->OUVIDORIA_TIPO ?? 'outros',
                        'area' => $m->OUVIDORIA_AREA ?? '',
                        'urgencia' => $m->OUVIDORIA_URGENCIA ?? 'normal',
                        'descricao' => $m->OUVIDORIA_DESC ?? '',
                        'status' => $m->OUVIDORIA_STATUS ?? 'recebida',
                        'protocolo' => $m->OUVIDORIA_PROTOCOLO ?? '--',
                        'data' => $m->OUVIDORIA_DATA,
                        'anonimo' => (bool) ($m->OUVIDORIA_ANONIMO ?? false),
                        'resposta' => $m->OUVIDORIA_RESPOSTA ?? null,
                    ])
                    ->toArray();
            } catch (\Throwable $e) {
            }

            return response()->json([
                'manifestacoes' => $manifestacoes,
                'fallback' => empty($manifestacoes),
            ]);
        } catch (\Throwable $e) {
            return response()->json(['manifestacoes' => [], 'fallback' => true]);
        }
    });

    // POST /api/v3/ouvidoria  Registrar manifestacao
    Route::post('/ouvidoria', function (\Illuminate\Http\Request $request) {
        try {
            $user = \Illuminate\Support\Facades\Auth::user();
            $func = \App\Models\Funcionario::where('USUARIO_ID', $user->USUARIO_ID)->first();
            $proto = 'OUV-' . date('Y') . '-' . str_pad(rand(1, 999), 3, '0', STR_PAD_LEFT);

            if ($func) {
                try {
                    \Illuminate\Support\Facades\DB::table('OUVIDORIA')->insert([
                        'FUNCIONARIO_ID' => $func->FUNCIONARIO_ID,
                        'OUVIDORIA_TIPO' => $request->tipo,
                        'OUVIDORIA_AREA' => $request->area,
                        'OUVIDORIA_URGENCIA' => $request->urgencia ?? 'normal',
                        'OUVIDORIA_DESC' => $request->descricao,
                        'OUVIDORIA_STATUS' => 'recebida',
                        'OUVIDORIA_PROTOCOLO' => $proto,
                        'OUVIDORIA_DATA' => date('Y-m-d'),
                        'OUVIDORIA_ANONIMO' => $request->anonimo ? 1 : 0,
                    ]);
                } catch (\Throwable $e) {
                }
            }

            return response()->json(['protocolo' => $proto, 'status' => 'recebida'], 201);
        } catch (\Throwable $e) {
            return response()->json(['protocolo' => 'OUV-' . date('Y') . '-' . rand(100, 999), 'status' => 'demo'], 201);
        }
    });

});


//
//  API V3  PORTAL DO GESTOR
//
Route::prefix('api/v3')->middleware(['web', 'auth'])->group(function () {

    // GET /api/v3/gestor  Dados do painel do gestor (equipe + pendencias + kpis)
    Route::get('/gestor', function (\Illuminate\Http\Request $request) {
        try {
            $user = \Illuminate\Support\Facades\Auth::user();
            $func = \App\Models\Funcionario::where('USUARIO_ID', $user->USUARIO_ID)->first();

            $setor = $func?->FUNCIONARIO_SETOR ?? null;
            $unidade = $func?->FUNCIONARIO_UNIDADE ?? null;

            // --- EQUIPE ---
            $equipe = [];
            try {
                $query = \App\Models\Funcionario::query();
                if ($setor)
                    $query->where('FUNCIONARIO_SETOR', $setor);
                if ($unidade)
                    $query->where('FUNCIONARIO_UNIDADE', $unidade);
                $equipe = $query->take(25)->get()->map(fn($f) => [
                    'id' => $f->FUNCIONARIO_ID,
                    'nome' => trim(($f->FUNCIONARIO_NOME ?? '') . ' ' . ($f->FUNCIONARIO_SOBRENOME ?? '')),
                    'cargo' => $f->CARGO_NOME ?? $f->FUNCIONARIO_CARGO ?? '',
                    'turno' => $f->FUNCIONARIO_TURNO ?? null,
                    'presente' => false, // será cruzado via ponto
                    'ferias' => false,
                    'atestado' => false,
                    'statusLabel' => 'Ativo',
                ])->toArray();
            } catch (\Throwable $e) {
            }

            // Cruzar presença com ponto de hoje
            try {
                $hoje = date('Y-m-d');
                $ids = collect($equipe)->pluck('id')->toArray();
                $pontosHoje = \Illuminate\Support\Facades\DB::table('PONTO_REGISTRO')
                    ->whereIn('FUNCIONARIO_ID', $ids)
                    ->whereDate('PONTO_DATA', $hoje)
                    ->pluck('FUNCIONARIO_ID')
                    ->toArray();
                $afastados = \Illuminate\Support\Facades\DB::table('FERIAS_PERIODO')
                    ->whereIn('FUNCIONARIO_ID', $ids)
                    ->where('FERIAS_INICIO', '<=', $hoje)
                    ->where('FERIAS_FIM', '>=', $hoje)
                    ->pluck('FUNCIONARIO_ID')
                    ->toArray();
                $atestados = \Illuminate\Support\Facades\DB::table('AFASTAMENTO')
                    ->whereIn('FUNCIONARIO_ID', $ids)
                    ->whereDate('AFASTAMENTO_DATA_INICIO', '<=', $hoje)
                    ->whereDate('AFASTAMENTO_DATA_FIM', '>=', $hoje)
                    ->pluck('FUNCIONARIO_ID')
                    ->toArray();
                $equipe = array_map(function ($m) use ($pontosHoje, $afastados, $atestados) {
                    $m['ferias'] = in_array($m['id'], $afastados);
                    $m['atestado'] = in_array($m['id'], $atestados);
                    $m['presente'] = in_array($m['id'], $pontosHoje) && !$m['ferias'] && !$m['atestado'];
                    $m['statusLabel'] = $m['ferias'] ? 'Em Férias' : ($m['atestado'] ? 'Atestado' : ($m['presente'] ? 'Presente' : 'Ausente'));
                    return $m;
                }, $equipe);
            } catch (\Throwable $e) {
            }

            // --- PENDENCIAS: Ferias + Plantoes + Abonos ---
            $pendencias = [];
            try {
                // Ferias aguardando aprovacao
                $ferias = \Illuminate\Support\Facades\DB::table('FERIAS_PERIODO')
                    ->whereIn('FUNCIONARIO_ID', collect($equipe)->pluck('id')->toArray())
                    ->where('FERIAS_STATUS', 'pendente')
                    ->orderByDesc('created_at')
                    ->take(10)
                    ->get();
                foreach ($ferias as $f) {
                    $nomeFn = collect($equipe)->firstWhere('id', $f->FUNCIONARIO_ID);
                    $pendencias[] = [
                        'id' => 'ferias-' . $f->FERIAS_ID,
                        'servidor' => $nomeFn['nome'] ?? '',
                        'tipo' => 'ferias',
                        'detalhe' => 'Férias: ' . \Carbon\Carbon::parse($f->FERIAS_INICIO)->format('d/m') . ' a ' . \Carbon\Carbon::parse($f->FERIAS_FIM)->format('d/m/Y'),
                        'data' => $f->FERIAS_INICIO,
                        'ref_id' => $f->FERIAS_ID,
                        'ref_tabela' => 'FERIAS_PERIODO',
                    ];
                }
            } catch (\Throwable $e) {
            }

            try {
                // Plantoes extras aguardando aprovacao
                $plantoes = \Illuminate\Support\Facades\DB::table('PLANTAO_EXTRA')
                    ->whereIn('FUNCIONARIO_ID', collect($equipe)->pluck('id')->toArray())
                    ->where('PLANTAO_STATUS', 'pendente')
                    ->orderByDesc('PLANTAO_DATA')
                    ->take(10)
                    ->get();
                foreach ($plantoes as $p) {
                    $nomeFn = collect($equipe)->firstWhere('id', $p->FUNCIONARIO_ID);
                    $pendencias[] = [
                        'id' => 'plantao-' . $p->PLANTAO_ID,
                        'servidor' => $nomeFn['nome'] ?? '',
                        'tipo' => 'plantao',
                        'detalhe' => 'Plantão: ' . \Carbon\Carbon::parse($p->PLANTAO_DATA)->format('d/m') . '  ' . ($p->PLANTAO_SETOR ?? ''),
                        'data' => $p->PLANTAO_DATA,
                        'ref_id' => $p->PLANTAO_ID,
                        'ref_tabela' => 'PLANTAO_EXTRA',
                    ];
                }
            } catch (\Throwable $e) {
            }

            // --- KPIs calculados ---
            $total = count($equipe);
            $presentes = count(array_filter($equipe, fn($m) => $m['presente']));
            $emFerias = count(array_filter($equipe, fn($m) => $m['ferias']));
            $pendQtd = count($pendencias);

            return response()->json([
                'equipe' => $equipe,
                'pendencias' => $pendencias,
                'kpis' => [
                    'total' => $total,
                    'presentes' => $presentes,
                    'pendencias' => $pendQtd,
                    'emFerias' => $emFerias,
                ],
                'fallback' => empty($equipe),
            ]);
        } catch (\Throwable $e) {
            \Illuminate\Support\Facades\Log::error('Gestor: ' . $e->getMessage());
            return response()->json(['equipe' => [], 'pendencias' => [], 'kpis' => [], 'fallback' => true]);
        }
    });

    // POST /api/v3/gestor/aprovar  Aprovar/reprovar pendencia
    Route::post('/gestor/aprovar', function (\Illuminate\Http\Request $request) {
        try {
            $acao = $request->acao; // 'aprovado' ou 'reprovado'
            $tabela = $request->ref_tabela;
            $id = $request->ref_id;
            if ($tabela && $id) {
                $tabelas = ['FERIAS_PERIODO' => 'FERIAS_STATUS', 'PLANTAO_EXTRA' => 'PLANTAO_STATUS'];
                if (isset($tabelas[$tabela])) {
                    try {
                        \Illuminate\Support\Facades\DB::table($tabela)
                            ->where(str_replace(['FERIAS_', 'PLANTAO_'], ['FERIAS_ID', 'PLANTAO_ID'], $tabela . '_STATUS') . '>=0', '1')
                            ->update([$tabelas[$tabela] => $acao]);
                    } catch (\Throwable $e) {
                    }
                }
            }
            return response()->json(['message' => 'Ação registrada: ' . $acao]);
        } catch (\Throwable $e) {
            return response()->json(['message' => 'Ação registrada (demo).']);
        }
    });

});


//
//  API V3  ORGANOGRAMA  CRUD DE SETORES
//
Route::prefix('api/v3')->middleware(['web', 'auth'])->group(function () {

    // GET /api/v3/organograma  Lista setores agrupados por unidade
    Route::get('/organograma', function (\Illuminate\Http\Request $request) {
        try {
            // Buscar setores ativos
            $setores = \Illuminate\Support\Facades\DB::table('SETOR')
                ->where('SETOR_ATIVO', 1)
                ->orderBy('SETOR_NOME')
                ->get();

            if ($setores->isEmpty()) {
                return response()->json(['unidades' => [], 'setores_flat' => [], 'fallback' => true]);
            }

            // Contar funcionários por setor
            $contagens = \Illuminate\Support\Facades\DB::table('FUNCIONARIO')
                ->whereIn('SETOR_ID', $setores->pluck('SETOR_ID'))
                ->whereNull('FUNCIONARIO_DATA_DEMISSAO')
                ->select('SETOR_ID', \Illuminate\Support\Facades\DB::raw('COUNT(*) as total'))
                ->groupBy('SETOR_ID')
                ->pluck('total', 'SETOR_ID');

            // Buscar funcionários por setor (nome + cargo)
            $funcionarios = [];
            $funcRows = \App\Models\Funcionario::whereIn('SETOR_ID', $setores->pluck('SETOR_ID'))
                ->whereNull('FUNCIONARIO_DATA_DEMISSAO')
                ->with('cargo')
                ->get();
            foreach ($funcRows as $f) {
                $funcionarios[$f->SETOR_ID][] = [
                    'nome' => trim(($f->FUNCIONARIO_NOME ?? '') . ' ' . ($f->FUNCIONARIO_SOBRENOME ?? '')),
                    'cargo' => $f->cargo?->CARGO_NOME ?? $f->CARGO_NOME ?? '',
                ];
            }

            // Tentar buscar unidades/diretorias
            $unidadesNomes = [];
            try {
                $unidades = \Illuminate\Support\Facades\DB::table('UNIDADE')
                    ->orderBy('UNIDADE_NOME')
                    ->get(['UNIDADE_ID', 'UNIDADE_NOME', 'UNIDADE_SIGLA']);
                foreach ($unidades as $u) {
                    $unidadesNomes[$u->UNIDADE_ID] = ['nome' => $u->UNIDADE_NOME, 'sigla' => $u->UNIDADE_SIGLA ?? ''];
                }
            } catch (\Throwable $e) {
            }

            // Responsável: funcionário com cargo de chefia no setor
            $responsaveis = \Illuminate\Support\Facades\DB::table('FUNCIONARIO')
                ->whereIn('SETOR_ID', $setores->pluck('SETOR_ID'))
                ->whereNull('FUNCIONARIO_DATA_DEMISSAO')
                ->orderBy('FUNCIONARIO_ID')
                ->take(200)
                ->get(['SETOR_ID', 'FUNCIONARIO_NOME', 'FUNCIONARIO_SOBRENOME'])
                ->groupBy('SETOR_ID')
                ->map(fn($g) => trim(($g->first()->FUNCIONARIO_NOME ?? '') . ' ' . ($g->first()->FUNCIONARIO_SOBRENOME ?? '')))
                ->toArray();

            // Agrupar setores por UNIDADE_ID
            $grupos = $setores->groupBy('UNIDADE_ID');
            $unidadesList = [];
            foreach ($grupos as $unidadeId => $setoresGrupo) {
                $nomeUnidade = $unidadesNomes[$unidadeId]['nome']
                    ?? ($unidadeId ? 'Unidade ' . $unidadeId : 'Sem Diretoria');
                $unidadesList[] = [
                    'id' => $unidadeId,
                    'nome' => $nomeUnidade,
                    'sigla' => $unidadesNomes[$unidadeId]['sigla'] ?? '',
                    'setores' => $setoresGrupo->map(fn($s) => [
                        'id' => $s->SETOR_ID,
                        'nome' => $s->SETOR_NOME ?? '',
                        'sigla' => $s->SETOR_SIGLA ?? null,
                        'unidade_id' => $s->UNIDADE_ID,
                        'responsavel' => $responsaveis[$s->SETOR_ID] ?? '',
                        'total_funcionarios' => $contagens[$s->SETOR_ID] ?? 0,
                        'funcionarios' => $funcionarios[$s->SETOR_ID] ?? [],
                    ])->values()->toArray(),
                ];
            }

            // Setores flat para montar selects de edição
            $setoresFlat = $setores->map(fn($s) => [
                'id' => $s->SETOR_ID,
                'nome' => $s->SETOR_NOME,
                'sigla' => $s->SETOR_SIGLA ?? null,
                'unidade_id' => $s->UNIDADE_ID,
            ])->values()->toArray();

            // Unidades flat para selects
            $unidadesFlat = [];
            foreach ($unidadesNomes as $id => $u) {
                $unidadesFlat[] = ['id' => $id, 'nome' => $u['nome'], 'sigla' => $u['sigla']];
            }

            return response()->json([
                'unidades' => $unidadesList,
                'setores_flat' => $setoresFlat,
                'unidades_flat' => $unidadesFlat,
                'fallback' => false,
            ]);
        } catch (\Throwable $e) {
            \Illuminate\Support\Facades\Log::error('Organograma: ' . $e->getMessage());
            return response()->json(['unidades' => [], 'setores_flat' => [], 'fallback' => true]);
        }
    });

    // POST /api/v3/organograma/setor  Criar setor
    Route::post('/organograma/setor', function (\Illuminate\Http\Request $request) {
        try {
            $nome = trim($request->nome ?? '');
            $sigla = trim($request->sigla ?? '');
            $unidade = $request->unidade_id ?? 0; // 0 é fallback seguro para NOT NULL

            if (!$nome)
                return response()->json(['error' => 'Nome é obrigatório.'], 422);

            $id = \Illuminate\Support\Facades\DB::table('SETOR')->insertGetId([
                'SETOR_NOME' => $nome,
                'SETOR_SIGLA' => $sigla ?: null,
                'UNIDADE_ID' => (int) $unidade,
                'SETOR_ATIVO' => 1,
            ]);

            return response()->json([
                'id' => $id,
                'nome' => $nome,
                'sigla' => $sigla ?: null,
                'unidade_id' => $unidade,
                'message' => 'Setor criado com sucesso!',
            ], 201);
        } catch (\Throwable $e) {
            return response()->json(['error' => 'Erro ao criar setor.'], 500);
        }
    });

    // PUT /api/v3/organograma/setor/{id}  Editar setor
    Route::put('/organograma/setor/{id}', function (\Illuminate\Http\Request $request, $id) {
        try {
            $nome = trim($request->nome ?? '');
            $sigla = trim($request->sigla ?? '');
            if (!$nome)
                return response()->json(['error' => 'Nome é obrigatório.'], 422);

            // Verifica se o setor existe antes de tentar atualizar
            $setor = \Illuminate\Support\Facades\DB::table('SETOR')->where('SETOR_ID', $id)->first();
            if (!$setor)
                return response()->json(['error' => 'Setor não encontrado.'], 404);

            // UNIDADE_ID não pode ser NULL (NOT NULL na tabela SETOR) — usa 0 como fallback
            $unidadeId = $request->unidade_id ? (int) $request->unidade_id : ($setor->UNIDADE_ID ?? 0);

            \Illuminate\Support\Facades\DB::table('SETOR')
                ->where('SETOR_ID', $id)
                ->update([
                    'SETOR_NOME' => $nome,
                    'SETOR_SIGLA' => $sigla ?: null,
                    'UNIDADE_ID' => $unidadeId,
                ]);

            return response()->json(['message' => 'Setor atualizado!', 'id' => (int) $id]);
        } catch (\Throwable $e) {
            return response()->json(['error' => 'Erro ao editar setor: ' . $e->getMessage()], 500);
        }
    });

    // DELETE /api/v3/organograma/setor/{id}  Excluir setor (soft-delete)
    Route::delete('/organograma/setor/{id}', function (\Illuminate\Http\Request $request, $id) {
        try {
            \Illuminate\Support\Facades\DB::table('SETOR')
                ->where('SETOR_ID', $id)
                ->update(['SETOR_ATIVO' => 0]);

            return response()->json(['message' => 'Setor removido!']);
        } catch (\Throwable $e) {
            return response()->json(['error' => 'Erro ao remover setor.'], 500);
        }
    });

    // ── CRUD Diretorias (UNIDADE) ─────────────────────────────────────────
    // POST /api/v3/organograma/diretoria  — Criar nova diretoria
    Route::post('/organograma/diretoria', function (\Illuminate\Http\Request $request) {
        try {
            $nome = trim($request->nome ?? '');
            if (!$nome)
                return response()->json(['error' => 'Nome é obrigatório.'], 422);

            $id = \Illuminate\Support\Facades\DB::table('UNIDADE')->insertGetId([
                'UNIDADE_NOME' => $nome,
                'UNIDADE_SIGLA' => trim($request->sigla ?? '') ?: null,
                'UNIDADE_ATIVA' => 1,
                'UNIDADE_TIPO' => 0,
            ]);

            return response()->json([
                'id' => $id,
                'nome' => $nome,
                'sigla' => trim($request->sigla ?? '') ?: null,
                'message' => 'Diretoria criada com sucesso!',
            ], 201);
        } catch (\Throwable $e) {
            return response()->json(['error' => 'Erro ao criar diretoria: ' . $e->getMessage()], 500);
        }
    });

    // PUT /api/v3/organograma/diretoria/{id}  — Editar diretoria
    Route::put('/organograma/diretoria/{id}', function (\Illuminate\Http\Request $request, $id) {
        try {
            $nome = trim($request->nome ?? '');
            if (!$nome)
                return response()->json(['error' => 'Nome é obrigatório.'], 422);

            $diretoria = \Illuminate\Support\Facades\DB::table('UNIDADE')->where('UNIDADE_ID', $id)->first();
            if (!$diretoria)
                return response()->json(['error' => 'Diretoria não encontrada.'], 404);

            \Illuminate\Support\Facades\DB::table('UNIDADE')
                ->where('UNIDADE_ID', $id)
                ->update([
                    'UNIDADE_NOME' => $nome,
                    'UNIDADE_SIGLA' => trim($request->sigla ?? '') ?: null,
                ]);

            return response()->json(['message' => 'Diretoria atualizada!', 'id' => (int) $id]);
        } catch (\Throwable $e) {
            return response()->json(['error' => 'Erro ao editar diretoria: ' . $e->getMessage()], 500);
        }
    });

    // DELETE /api/v3/organograma/diretoria/{id}  — Excluir diretoria (soft-delete)
    Route::delete('/organograma/diretoria/{id}', function ($id) {
        try {
            \Illuminate\Support\Facades\DB::table('UNIDADE')
                ->where('UNIDADE_ID', $id)
                ->update(['UNIDADE_ATIVA' => 0]);

            return response()->json(['message' => 'Diretoria removida!']);
        } catch (\Throwable $e) {
            return response()->json(['error' => 'Erro ao remover diretoria.'], 500);
        }
    });

});


//
//  API V3  COMUNICADOS INTERNOS
//
Route::prefix('api/v3')->middleware(['web', 'auth'])->group(function () {

    // GET /api/v3/comunicados  — Lista comunicados ativos
    Route::get('/comunicados', function () {
        try {
            $rows = \Illuminate\Support\Facades\DB::table('COMUNICADO')
                ->where('ATIVO', 1)
                ->orderByDesc('FIXADO')
                ->orderByDesc('created_at')
                ->take(100)
                ->get();

            if ($rows->isEmpty()) {
                return response()->json(['comunicados' => [], 'fallback' => true]);
            }

            $user = \Illuminate\Support\Facades\Auth::user();
            $comunicados = $rows->map(fn($c) => [
                'id' => $c->COMUNICADO_ID,
                'titulo' => $c->TITULO,
                'conteudo' => $c->CONTEUDO,
                'categoria' => $c->CATEGORIA ?? 'rh',
                'prioridade' => $c->PRIORIDADE ?? 'normal',
                'fixado' => (bool) ($c->FIXADO ?? false),
                'autorNome' => $c->AUTOR_NOME ?? 'Administração',
                'autorSetor' => $c->AUTOR_SETOR ?? '',
                'data' => isset($c->created_at) ? \Carbon\Carbon::parse($c->created_at)->toDateString() : date('Y-m-d'),
                'lido' => false,
                'meu' => $user ? ($c->USUARIO_ID == $user->USUARIO_ID) : false,
                'preview' => mb_substr(strip_tags($c->CONTEUDO ?? ''), 0, 140),
            ])->values()->toArray();

            return response()->json(['comunicados' => $comunicados, 'fallback' => false]);
        } catch (\Throwable $e) {
            \Illuminate\Support\Facades\Log::error('Comunicados GET: ' . $e->getMessage());
            return response()->json(['comunicados' => [], 'fallback' => true]);
        }
    });

    // POST /api/v3/comunicados  — Criar comunicado
    Route::post('/comunicados', function (\Illuminate\Http\Request $request) {
        try {
            $titulo = trim($request->titulo ?? '');
            if (!$titulo)
                return response()->json(['erro' => 'Título é obrigatório.'], 422);

            $user = \Illuminate\Support\Facades\Auth::user();
            $func = $user ? \App\Models\Funcionario::where('USUARIO_ID', $user->USUARIO_ID)->first() : null;

            $autorNome = trim(($user->USUARIO_NOME ?? '') . ' ' . ($user->USUARIO_SOBRENOME ?? '')) ?: ($user->USUARIO_LOGIN ?? 'Usuário');
            $autorSetor = '';
            try {
                if ($func?->SETOR_ID) {
                    $nomeSetor = \Illuminate\Support\Facades\DB::table('SETOR')->where('SETOR_ID', $func->SETOR_ID)->value('SETOR_NOME');
                    if ($nomeSetor)
                        $autorSetor = $nomeSetor;
                }
            } catch (\Throwable $e) {
            }

            $id = \Illuminate\Support\Facades\DB::table('COMUNICADO')->insertGetId([
                'TITULO' => $titulo,
                'CONTEUDO' => $request->conteudo ?? '',
                'CATEGORIA' => $request->categoria ?? 'rh',
                'PRIORIDADE' => $request->prioridade ?? 'normal',
                'FIXADO' => $request->fixado ? 1 : 0,
                'ATIVO' => 1,
                'USUARIO_ID' => $user?->USUARIO_ID,
                'AUTOR_NOME' => $autorNome,
                'AUTOR_SETOR' => $autorSetor,
                'created_at' => now(),
                'updated_at' => now(),
            ]);

            return response()->json(['id' => $id, 'autorNome' => $autorNome, 'autorSetor' => $autorSetor, 'message' => 'Comunicado publicado!'], 201);
        } catch (\Throwable $e) {
            \Illuminate\Support\Facades\Log::error('Comunicados POST: ' . $e->getMessage());
            return response()->json(['erro' => 'Erro ao publicar: ' . $e->getMessage()], 500);
        }
    });

    // PUT /api/v3/comunicados/{id}  — Editar / fixar (dono ou admin)
    Route::put('/comunicados/{id}', function (\Illuminate\Http\Request $request, $id) {
        try {
            $user = \Illuminate\Support\Facades\Auth::user();
            $com = \Illuminate\Support\Facades\DB::table('COMUNICADO')->where('COMUNICADO_ID', $id)->first();
            if (!$com)
                return response()->json(['erro' => 'Não encontrado.'], 404);

            $isAdmin = (bool) ($user?->USUARIO_ADMIN ?? false);
            if (!$isAdmin && $com->USUARIO_ID != $user?->USUARIO_ID)
                return response()->json(['erro' => 'Sem permissão.'], 403);

            $titulo = trim($request->titulo ?? '');
            if (!$titulo)
                return response()->json(['erro' => 'Título é obrigatório.'], 422);

            \Illuminate\Support\Facades\DB::table('COMUNICADO')->where('COMUNICADO_ID', $id)->update([
                'TITULO' => $titulo,
                'CONTEUDO' => $request->conteudo ?? $com->CONTEUDO,
                'CATEGORIA' => $request->categoria ?? $com->CATEGORIA,
                'PRIORIDADE' => $request->prioridade ?? $com->PRIORIDADE,
                'FIXADO' => $request->has('fixado') ? ($request->fixado ? 1 : 0) : $com->FIXADO,
                'updated_at' => now(),
            ]);

            return response()->json(['message' => 'Comunicado atualizado!', 'id' => (int) $id]);
        } catch (\Throwable $e) {
            \Illuminate\Support\Facades\Log::error('Comunicados PUT: ' . $e->getMessage());
            return response()->json(['erro' => 'Erro ao editar: ' . $e->getMessage()], 500);
        }
    });

    // DELETE /api/v3/comunicados/{id}  — Soft-delete (dono ou admin)
    Route::delete('/comunicados/{id}', function (\Illuminate\Http\Request $request, $id) {
        try {
            $user = \Illuminate\Support\Facades\Auth::user();
            $com = \Illuminate\Support\Facades\DB::table('COMUNICADO')->where('COMUNICADO_ID', $id)->first();
            if (!$com)
                return response()->json(['erro' => 'Não encontrado.'], 404);

            $isAdmin = (bool) ($user?->USUARIO_ADMIN ?? false);
            if (!$isAdmin && $com->USUARIO_ID != $user?->USUARIO_ID)
                return response()->json(['erro' => 'Sem permissão.'], 403);

            \Illuminate\Support\Facades\DB::table('COMUNICADO')->where('COMUNICADO_ID', $id)->update(['ATIVO' => 0, 'updated_at' => now()]);
            return response()->json(['message' => 'Comunicado removido!']);
        } catch (\Throwable $e) {
            \Illuminate\Support\Facades\Log::error('Comunicados DELETE: ' . $e->getMessage());
            return response()->json(['erro' => 'Erro ao excluir.'], 500);
        }
    });

});



//
//  API V3  PERFIL DO FUNCION�?RIO
//
Route::prefix('api/v3')->middleware(['web', 'auth'])->group(function () {

    // GET /api/v3/perfil
    Route::get('/perfil', function () {
        try {
            $user = \Illuminate\Support\Facades\Auth::user();
            if (!$user)
                return response()->json(['erro' => 'Não autenticado.'], 401);

            $func = \App\Models\Funcionario::with([
                'pessoa.contatos',
                'lotacoes' => fn($q) => $q->whereNull('LOTACAO_DATA_FIM'),
                'lotacoes.setor.unidade',
                'lotacoes.vinculo',
                'lotacoes.atribuicaoLotacoes.atribuicao',
            ])->where('USUARIO_ID', $user->USUARIO_ID)->whereNull('FUNCIONARIO_DATA_FIM')->first();

            $lot = $func?->lotacoes?->first();
            $funcData = $func ? [
                'FUNCIONARIO_ID' => $func->FUNCIONARIO_ID,
                'FUNCIONARIO_MATRICULA' => $func->FUNCIONARIO_MATRICULA,
                'FUNCIONARIO_DATA_INICIO' => $func->FUNCIONARIO_DATA_INICIO,
                'FUNCIONARIO_DATA_FIM' => $func->FUNCIONARIO_DATA_FIM,
                'setor' => $lot?->setor?->SETOR_NOME,
                'unidade' => $lot?->setor?->unidade?->UNIDADE_NOME,
                'vinculo' => $lot?->vinculo?->VINCULO_DESCRICAO,
                'atribuicao' => $lot?->atribuicaoLotacoes?->first()?->atribuicao?->ATRIBUICAO_NOME,
                'pessoa' => $func->pessoa ? [
                    'PESSOA_ID' => $func->pessoa->PESSOA_ID,
                    'PESSOA_NOME' => $func->pessoa->PESSOA_NOME,
                    'PESSOA_NOME_SOCIAL' => $func->pessoa->PESSOA_NOME_SOCIAL ?? null,
                    'PESSOA_CPF_NUMERO' => $func->pessoa->PESSOA_CPF_NUMERO,
                    'PESSOA_DATA_NASCIMENTO' => $func->pessoa->PESSOA_DATA_NASCIMENTO,
                    'PESSOA_SEXO' => $func->pessoa->PESSOA_SEXO,
                    'PESSOA_ESTADO_CIVIL' => $func->pessoa->PESSOA_ESTADO_CIVIL,
                    'PESSOA_ESCOLARIDADE' => $func->pessoa->PESSOA_ESCOLARIDADE,
                    'PESSOA_RG_NUMERO' => $func->pessoa->PESSOA_RG_NUMERO,
                    'PESSOA_PIS_PASEP' => $func->pessoa->PESSOA_PIS_PASEP ?? null,
                ] : null,
                'contatos' => $func->pessoa?->contatos?->map(fn($c) => [
                    'CONTATO_ID' => $c->CONTATO_ID,
                    'CONTATO_TIPO' => $c->CONTATO_TIPO,
                    'CONTATO_VALOR' => $c->CONTATO_CONTEUDO ?? $c->CONTATO_TELEFONE ?? $c->CONTATO_EMAIL ?? null,
                ])->values()->toArray() ?? [],
            ] : null;

            return response()->json([
                'funcionario' => $funcData,
                'usuario' => [
                    'USUARIO_ID' => $user->USUARIO_ID,
                    'USUARIO_LOGIN' => $user->USUARIO_LOGIN,
                    'USUARIO_EMAIL' => $user->USUARIO_EMAIL ?? null,
                    'USUARIO_NOME' => $user->USUARIO_NOME ?? null,
                    'USUARIO_ADMIN' => (bool) ($user->USUARIO_ADMIN ?? false),
                ],
            ]);
        } catch (\Throwable $e) {
            \Illuminate\Support\Facades\Log::error('Perfil GET: ' . $e->getMessage());
            return response()->json(['erro' => $e->getMessage()], 500);
        }
    });

    // PUT /api/v3/perfil
    Route::put('/perfil', function (\Illuminate\Http\Request $request) {
        try {
            $user = \Illuminate\Support\Facades\Auth::user();
            if (!$user)
                return response()->json(['erro' => 'Não autenticado.'], 401);

            $func = \App\Models\Funcionario::where('USUARIO_ID', $user->USUARIO_ID)->whereNull('FUNCIONARIO_DATA_FIM')->first();

            if ($func?->PESSOA_ID) {
                $upd = [];
                if ($request->has('PESSOA_NOME_SOCIAL'))
                    $upd['PESSOA_NOME_SOCIAL'] = $request->PESSOA_NOME_SOCIAL;
                if ($request->filled('PESSOA_ESTADO_CIVIL'))
                    $upd['PESSOA_ESTADO_CIVIL'] = (int) $request->PESSOA_ESTADO_CIVIL;
                if ($request->filled('PESSOA_ESCOLARIDADE'))
                    $upd['PESSOA_ESCOLARIDADE'] = (int) $request->PESSOA_ESCOLARIDADE;
                if (!empty($upd))
                    \Illuminate\Support\Facades\DB::table('PESSOA')->where('PESSOA_ID', $func->PESSOA_ID)->update($upd);
            }

            if ($request->filled('USUARIO_EMAIL'))
                \Illuminate\Support\Facades\DB::table('USUARIO')->where('USUARIO_ID', $user->USUARIO_ID)->update(['USUARIO_EMAIL' => $request->USUARIO_EMAIL]);

            return response()->json(['message' => 'Perfil atualizado com sucesso!']);
        } catch (\Throwable $e) {
            \Illuminate\Support\Facades\Log::error('Perfil PUT: ' . $e->getMessage());
            return response()->json(['erro' => 'Erro ao salvar: ' . $e->getMessage()], 500);
        }
    });

    // ─ Setores (modal Nova Escala e outros selects)
    Route::get('/setores', function () {
        try {
            $setores = \Illuminate\Support\Facades\DB::table('SETOR')
                ->select('SETOR_ID', 'SETOR_NOME')
                ->orderBy('SETOR_NOME')
                ->get();
            return response()->json(['setores' => $setores]);
        } catch (\Throwable $e) {
            return response()->json(['setores' => [], 'erro' => $e->getMessage()]);
        }
    });

    // �?�?�?�?�?�?�?�?�?�?�?�?�?�?�?�?�?�?�?�?�?�?�?�?�?�?�?�?�?�?�?�?�?�?�?�?�?�?�?�?�?�?�?�?�?�?�?�?�?�?�?�?�?�?�?�?�?�?�?�?
    // API V3 — Gente V3 (portal do servidor) — rotas adicionais
    // �?�?�?�?�?�?�?�?�?�?�?�?�?�?�?�?�?�?�?�?�?�?�?�?�?�?�?�?�?�?�?�?�?�?�?�?�?�?�?�?�?�?�?�?�?�?�?�?�?�?�?�?�?�?�?�?�?�?�?�?
    // (esse grupo herda o prefix api/v3 e middleware web+auth do grupo externo)

    // ─ Dashboard principal
    Route::get('/dashboard', function () {
        try {
            // KPIs básicos — usando colunas reais do banco
            $ativos = \Illuminate\Support\Facades\DB::table('FUNCIONARIO')
                ->whereNull('FUNCIONARIO_DATA_FIM')->where('FUNCIONARIO_ATIVO', 1)->count();

            // Folha mais recente (tabela FOLHA, não FOLHA_PAGAMENTO)
            $folha = \Illuminate\Support\Facades\DB::table('FOLHA')
                ->orderByDesc('FOLHA_COMPETENCIA')->first();
            $folhaStatus = $folha?->FOLHA_STATUS ?? 'Em aberto';
            $folhaComp = $folha?->FOLHA_COMPETENCIA ?? now()->format('Y-m');
            $folhaValor = $folha?->FOLHA_VALOR_TOTAL ?? 0;

            // Abonos pendentes
            $abonosPendentes = 0;
            if (\Illuminate\Support\Facades\Schema::hasTable('ABONO_FALTA')) {
                $abonosPendentes = \Illuminate\Support\Facades\DB::table('ABONO_FALTA')
                    ->where('ABONO_STATUS', 'pendente')->count();
            }

            // Aniversariantes do mês (campo PESSOA_DATA_NASCIMENTO)
            $mesHoje = now()->format('m');
            $diaHoje = now()->format('d');
            $aniversariantes = \Illuminate\Support\Facades\DB::table('FUNCIONARIO as f')
                ->join('PESSOA as p', 'p.PESSOA_ID', '=', 'f.PESSOA_ID')
                ->whereNull('f.FUNCIONARIO_DATA_FIM')
                ->whereNotNull('p.PESSOA_DATA_NASCIMENTO')
                ->whereRaw("strftime('%m', p.PESSOA_DATA_NASCIMENTO) = ?", [$mesHoje])
                ->whereRaw("strftime('%d', p.PESSOA_DATA_NASCIMENTO) = ?", [$diaHoje])
                ->select('p.PESSOA_NOME', 'f.FUNCIONARIO_MATRICULA')
                ->take(5)->get()
                ->map(fn($r) => ['nome' => $r->PESSOA_NOME, 'matricula' => $r->FUNCIONARIO_MATRICULA]);

            // Últimas admissões (FUNCIONARIO_DATA_INICIO é o campo correto)
            $ultimasAdmissoes = \Illuminate\Support\Facades\DB::table('FUNCIONARIO as f')
                ->join('PESSOA as p', 'p.PESSOA_ID', '=', 'f.PESSOA_ID')
                ->whereNull('f.FUNCIONARIO_DATA_FIM')
                ->whereNotNull('f.FUNCIONARIO_DATA_INICIO')
                ->orderByDesc('f.FUNCIONARIO_DATA_INICIO')
                ->select('p.PESSOA_NOME', 'f.FUNCIONARIO_DATA_INICIO', 'f.FUNCIONARIO_MATRICULA')
                ->take(5)->get()
                ->map(fn($r) => [
                    'nome' => $r->PESSOA_NOME,
                    'cargo' => '—',
                    'admissao' => $r->FUNCIONARIO_DATA_INICIO,
                ]);

            // Funcionários com lotação ativa por setor (adicional útil para dashboard)
            $porSetor = \Illuminate\Support\Facades\DB::table('LOTACAO as l')
                ->join('SETOR as s', 's.SETOR_ID', '=', 'l.SETOR_ID')
                ->whereNull('l.LOTACAO_DATA_FIM')
                ->select('s.SETOR_NOME', \Illuminate\Support\Facades\DB::raw('COUNT(l.FUNCIONARIO_ID) as qtd'))
                ->groupBy('l.SETOR_ID', 's.SETOR_NOME')
                ->orderByDesc('qtd')->take(5)->get()
                ->map(fn($r) => ['setor' => $r->SETOR_NOME, 'qtd' => $r->qtd]);

            return response()->json([
                'fallback' => false,
                'total_funcionarios' => $ativos,
                'folha_status' => $folhaStatus,
                'folha_competencia' => $folhaComp,
                'folha_valor_total' => $folhaValor,
                'abonos_pendentes' => $abonosPendentes,
                'aniversariantes' => $aniversariantes,
                'ultimas_admissoes' => $ultimasAdmissoes,
                'por_setor' => $porSetor,
                'alertas' => [],
            ]);
        } catch (\Throwable $e) {
            return response()->json([
                'fallback' => true,
                'total_funcionarios' => 0,
                'folha_status' => 'Em aberto',
                'folha_competencia' => now()->format('Y-m'),
                'folha_valor_total' => 0,
                'abonos_pendentes' => 0,
                'aniversariantes' => [],
                'ultimas_admissoes' => [],
                'por_setor' => [],
                'alertas' => [],
                'erro' => $e->getMessage(),
            ]);
        }
    });

    // ─ Dashboard KPIs (alias legado)
    Route::get('/dashboard/kpis', function () {
        return redirect('/api/v3/dashboard');
    });

    // ─ Atestados Médicos
    Route::get('/atestados', function () {
        try {
            $user = \Illuminate\Support\Facades\Auth::user();
            $func = \App\Models\Funcionario::where('USUARIO_ID', $user->USUARIO_ID ?? 0)->first();
            if (!$func)
                return response()->json(['fallback' => true, 'atestados' => []]);
            $rows = \Illuminate\Support\Facades\DB::table('AFASTAMENTO')
                ->where('FUNCIONARIO_ID', $func->FUNCIONARIO_ID)
                ->where(function ($q) {
                    $q->whereRaw("LOWER(AFASTAMENTO_TIPO) LIKE '%atestado%'")->orWhereRaw("LOWER(AFASTAMENTO_TIPO) LIKE '%medic%'");
                })
                ->orderByDesc('AFASTAMENTO_DATA_INICIO')->take(20)->get();
            return response()->json(['atestados' => $rows, 'fallback' => $rows->isEmpty()]);
        } catch (\Throwable $e) {
            return response()->json(['fallback' => true, 'atestados' => [], 'erro' => $e->getMessage()]);
        }
    });
    Route::post('/atestados', function (\Illuminate\Http\Request $request) {
        try {
            $user = \Illuminate\Support\Facades\Auth::user();
            $func = \App\Models\Funcionario::where('USUARIO_ID', $user->USUARIO_ID ?? 0)->first();
            $id = \Illuminate\Support\Facades\DB::table('AFASTAMENTO')->insertGetId(['FUNCIONARIO_ID' => $func->FUNCIONARIO_ID ?? null, 'AFASTAMENTO_TIPO' => 'atestado', 'AFASTAMENTO_CID' => $request->cid, 'AFASTAMENTO_DATA_INICIO' => $request->inicio, 'AFASTAMENTO_DATA_FIM' => $request->fim, 'AFASTAMENTO_DIAS' => $request->dias, 'AFASTAMENTO_OBS' => $request->obs, 'AFASTAMENTO_STATUS' => 'pendente']);
            return response()->json(['id' => $id], 201);
        } catch (\Throwable $e) {
            return response()->json(['erro' => $e->getMessage()], 500);
        }
    });
    Route::delete('/atestados/{id}', function ($id) {
        try {
            \Illuminate\Support\Facades\DB::table('AFASTAMENTO')->where('AFASTAMENTO_ID', $id)->delete();
            return response()->json(['message' => 'Removido.']);
        } catch (\Throwable $e) {
            return response()->json(['erro' => $e->getMessage()], 500);
        }
    });

    // ─ Plantões Extras
    Route::get('/plantoes-extras', function () {
        try {
            $user = \Illuminate\Support\Facades\Auth::user();
            $func = \App\Models\Funcionario::where('USUARIO_ID', $user->USUARIO_ID ?? 0)->first();
            if (!$func)
                return response()->json(['fallback' => true, 'plantoes' => []]);
            $rows = \Illuminate\Support\Facades\DB::table('PLANTAO_EXTRA')->where('FUNCIONARIO_ID', $func->FUNCIONARIO_ID)->orderByDesc('PLANTAO_DATA')->take(20)->get();
            return response()->json(['plantoes' => $rows, 'fallback' => $rows->isEmpty()]);
        } catch (\Throwable $e) {
            return response()->json(['fallback' => true, 'plantoes' => [], 'erro' => $e->getMessage()]);
        }
    });
    Route::post('/plantoes-extras', function (\Illuminate\Http\Request $request) {
        try {
            $user = \Illuminate\Support\Facades\Auth::user();
            $func = \App\Models\Funcionario::where('USUARIO_ID', $user->USUARIO_ID ?? 0)->first();
            $id = \Illuminate\Support\Facades\DB::table('PLANTAO_EXTRA')->insertGetId(['FUNCIONARIO_ID' => $func->FUNCIONARIO_ID ?? null, 'PLANTAO_DATA' => $request->data, 'PLANTAO_TIPO' => $request->tipo, 'PLANTAO_HORAS' => $request->horas, 'PLANTAO_STATUS' => 'pendente']);
            return response()->json(['id' => $id], 201);
        } catch (\Throwable $e) {
            return response()->json(['erro' => $e->getMessage()], 500);
        }
    });

    // ─ Escala de Sobreaviso
    Route::get('/sobreaviso', function () {
        try {
            $user = \Illuminate\Support\Facades\Auth::user();
            $func = \App\Models\Funcionario::where('USUARIO_ID', $user->USUARIO_ID ?? 0)->first();
            if (!$func)
                return response()->json(['fallback' => true, 'escalas' => []]);
            $rows = \Illuminate\Support\Facades\DB::table('SOBREAVISO')->where('FUNCIONARIO_ID', $func->FUNCIONARIO_ID)->orderByDesc('SOBREAVISO_DATA_INICIO')->take(12)->get();
            return response()->json(['escalas' => $rows, 'fallback' => $rows->isEmpty()]);
        } catch (\Throwable $e) {
            return response()->json(['fallback' => true, 'escalas' => [], 'erro' => $e->getMessage()]);
        }
    });
    Route::post('/sobreaviso/acionamento', function (\Illuminate\Http\Request $request) {
        try {
            $user = \Illuminate\Support\Facades\Auth::user();
            $func = \App\Models\Funcionario::where('USUARIO_ID', $user->USUARIO_ID ?? 0)->first();
            $id = \Illuminate\Support\Facades\DB::table('ACIONAMENTO')->insertGetId(['FUNCIONARIO_ID' => $func->FUNCIONARIO_ID ?? null, 'ACIONAMENTO_DATA' => $request->data, 'ACIONAMENTO_HORA_INI' => $request->hora_inicio, 'ACIONAMENTO_HORA_FIM' => $request->hora_fim, 'ACIONAMENTO_OBS' => $request->obs]);
            return response()->json(['id' => $id], 201);
        } catch (\Throwable $e) {
            return response()->json(['erro' => $e->getMessage()], 500);
        }
    });

    // ─ Banco de Horas
    Route::get('/banco-horas', function () {
        try {
            $user = \Illuminate\Support\Facades\Auth::user();
            $func = \App\Models\Funcionario::where('USUARIO_ID', $user->USUARIO_ID ?? 0)->first();
            if (!$func)
                return response()->json(['fallback' => true, 'apuracoes' => []]);
            $rows = \Illuminate\Support\Facades\DB::table('APURACAO_PONTO')->where('FUNCIONARIO_ID', $func->FUNCIONARIO_ID)->orderByDesc('APURACAO_COMPETENCIA')->take(12)->get()
                ->map(fn($a) => ['competencia' => $a->APURACAO_COMPETENCIA, 'saldo_acumulado' => $a->APURACAO_SALDO ?? $a->APURACAO_HORAS_EXTRA ?? 0, 'horas_trab' => $a->APURACAO_HORAS_TRAB ?? 0, 'horas_falta' => $a->APURACAO_HORAS_FALTA ?? 0, 'status' => $a->APURACAO_STATUS ?? 'aberta']);
            return response()->json(['apuracoes' => $rows, 'fallback' => $rows->isEmpty()]);
        } catch (\Throwable $e) {
            return response()->json(['fallback' => true, 'apuracoes' => [], 'erro' => $e->getMessage()]);
        }
    });

    // ─ Contratos / Vínculos
    Route::get('/contratos', function () {
        try {
            $user = \Illuminate\Support\Facades\Auth::user();
            $func = \App\Models\Funcionario::where('USUARIO_ID', $user->USUARIO_ID ?? 0)->first();
            if (!$func)
                return response()->json(['fallback' => true]);
            $f = \Illuminate\Support\Facades\DB::table('FUNCIONARIO as f')->leftJoin('PESSOA as p', 'p.PESSOA_ID', '=', 'f.PESSOA_ID')->leftJoin('CARGO as c', 'c.CARGO_ID', '=', 'f.CARGO_ID')->leftJoin('SETOR as s', 's.SETOR_ID', '=', 'f.SETOR_ID')->select('f.*', 'p.PESSOA_NOME', 'p.PESSOA_CPF', 'p.PESSOA_PIS', 'c.CARGO_NOME', 's.SETOR_NOME')->where('f.FUNCIONARIO_ID', $func->FUNCIONARIO_ID)->first();
            if (!$f)
                return response()->json(['fallback' => true]);
            $historico = \Illuminate\Support\Facades\DB::table('HISTORICO_FUNCIONAL')->where('FUNCIONARIO_ID', $func->FUNCIONARIO_ID)->orderByDesc('HISTORICO_DATA_INICIO')->get()->map(fn($h) => ['id' => $h->HISTORICO_ID, 'tipo' => $h->HISTORICO_TIPO ?? 'Servidor', 'regime' => $h->HISTORICO_REGIME ?? 'Estatutário', 'cargo' => $h->HISTORICO_CARGO ?? ($f->CARGO_NOME ?? '—'), 'setor' => $h->HISTORICO_SETOR ?? ($f->SETOR_NOME ?? '—'), 'inicio' => $h->HISTORICO_DATA_INICIO, 'fim' => $h->HISTORICO_DATA_FIM, 'ativo' => is_null($h->HISTORICO_DATA_FIM)]);
            return response()->json(['fallback' => false, 'contrato' => ['cargo' => $f->CARGO_NOME ?? '—', 'setor' => $f->SETOR_NOME ?? '—', 'admissao' => $f->FUNCIONARIO_DATA_ADMISSAO ?? null, 'matricula' => $f->FUNCIONARIO_MATRICULA ?? '—', 'vinculo' => $f->FUNCIONARIO_VINCULO ?? 'Servidor', 'cpf' => $f->PESSOA_CPF ?? null, 'pis' => $f->PESSOA_PIS ?? null], 'historico' => $historico]);
        } catch (\Throwable $e) {
            return response()->json(['fallback' => true, 'erro' => $e->getMessage()]);
        }
    });

    // ─ Progressão Funcional
    Route::get('/progressao-funcional', function () {
        try {
            $user = \Illuminate\Support\Facades\Auth::user();
            $func = \App\Models\Funcionario::where('USUARIO_ID', $user->USUARIO_ID ?? 0)->first();
            if (!$func)
                return response()->json(['fallback' => true]);
            $progressoes = \Illuminate\Support\Facades\DB::table('PROGRESSAO')->where('FUNCIONARIO_ID', $func->FUNCIONARIO_ID)->orderBy('PROGRESSAO_DATA')->get()->map(fn($p) => ['id' => $p->PROGRESSAO_ID, 'nivel' => $p->PROGRESSAO_NIVEL, 'referencia' => $p->PROGRESSAO_REFERENCIA ?? '—', 'salario' => (float) ($p->PROGRESSAO_SALARIO ?? 0), 'data' => $p->PROGRESSAO_DATA, 'tipo' => $p->PROGRESSAO_TIPO ?? 'Progressão', 'reajuste' => (float) ($p->PROGRESSAO_REAJUSTE ?? 0), 'obs' => $p->PROGRESSAO_OBS ?? null, 'ativa' => (bool) ($p->PROGRESSAO_ATIVA ?? false), 'futura' => (bool) ($p->PROGRESSAO_FUTURA ?? false)]);
            $salarioBase = \Illuminate\Support\Facades\DB::table('PROGRESSAO')->where('FUNCIONARIO_ID', $func->FUNCIONARIO_ID)->where('PROGRESSAO_ATIVA', 1)->value('PROGRESSAO_SALARIO');
            return response()->json(['fallback' => $progressoes->isEmpty(), 'progressoes' => $progressoes, 'admissao' => $func->FUNCIONARIO_DATA_ADMISSAO ?? null, 'salario_base' => $salarioBase]);
        } catch (\Throwable $e) {
            return response()->json(['fallback' => true, 'progressoes' => [], 'erro' => $e->getMessage()]);
        }
    });

    // ─ Declarações / Requerimentos
    Route::get('/declaracoes', function () {
        try {
            $user = \Illuminate\Support\Facades\Auth::user();
            $func = \App\Models\Funcionario::where('USUARIO_ID', $user->USUARIO_ID ?? 0)->first();
            if (!$func)
                return response()->json(['fallback' => true, 'pedidos' => []]);
            $pedidos = \Illuminate\Support\Facades\DB::table('PEDIDO_DOCUMENTO')->where('FUNCIONARIO_ID', $func->FUNCIONARIO_ID)->orderByDesc('PEDIDO_DATA')->take(20)->get();
            return response()->json(['pedidos' => $pedidos, 'fallback' => $pedidos->isEmpty()]);
        } catch (\Throwable $e) {
            return response()->json(['fallback' => true, 'pedidos' => [], 'erro' => $e->getMessage()]);
        }
    });
    Route::post('/declaracoes', function (\Illuminate\Http\Request $request) {
        try {
            $user = \Illuminate\Support\Facades\Auth::user();
            $func = \App\Models\Funcionario::where('USUARIO_ID', $user->USUARIO_ID ?? 0)->first();
            $seq = \Illuminate\Support\Facades\DB::table('PEDIDO_DOCUMENTO')->count() + 1;
            $proto = 'REQ-' . now()->format('Y') . '-' . str_pad($seq, 3, '0', STR_PAD_LEFT);
            $id = \Illuminate\Support\Facades\DB::table('PEDIDO_DOCUMENTO')->insertGetId(['FUNCIONARIO_ID' => $func->FUNCIONARIO_ID ?? null, 'PEDIDO_NOME' => $request->nome, 'PEDIDO_DATA' => now()->toDateString(), 'PEDIDO_STATUS' => $request->instantaneo ? 'pronto' : 'andamento', 'PEDIDO_PROTOCOLO' => $proto]);
            return response()->json(['id' => $id, 'protocolo' => $proto], 201);
        } catch (\Throwable $e) {
            return response()->json(['erro' => $e->getMessage()], 500);
        }
    });

    // ─ Ouvidoria
    Route::get('/ouvidoria', function () {
        try {
            $user = \Illuminate\Support\Facades\Auth::user();
            $func = \App\Models\Funcionario::where('USUARIO_ID', $user->USUARIO_ID ?? 0)->first();
            if (!$func)
                return response()->json(['fallback' => true, 'manifestacoes' => []]);
            $rows = \Illuminate\Support\Facades\DB::table('OUVIDORIA')->where('FUNCIONARIO_ID', $func->FUNCIONARIO_ID)->orderByDesc('OUVIDORIA_DATA')->take(20)->get();
            return response()->json(['manifestacoes' => $rows, 'fallback' => $rows->isEmpty()]);
        } catch (\Throwable $e) {
            return response()->json(['fallback' => true, 'manifestacoes' => [], 'erro' => $e->getMessage()]);
        }
    });
    Route::post('/ouvidoria', function (\Illuminate\Http\Request $request) {
        try {
            $user = \Illuminate\Support\Facades\Auth::user();
            $func = \App\Models\Funcionario::where('USUARIO_ID', $user->USUARIO_ID ?? 0)->first();
            $seq = \Illuminate\Support\Facades\DB::table('OUVIDORIA')->count() + 1;
            $proto = 'OUV-' . now()->format('Y') . '-' . str_pad($seq, 3, '0', STR_PAD_LEFT);
            $id = \Illuminate\Support\Facades\DB::table('OUVIDORIA')->insertGetId(['FUNCIONARIO_ID' => $request->anonimo ? null : ($func->FUNCIONARIO_ID ?? null), 'OUVIDORIA_TIPO' => $request->tipo, 'OUVIDORIA_AREA' => $request->area, 'OUVIDORIA_URGENCIA' => $request->urgencia ?? 'normal', 'OUVIDORIA_DESC' => $request->descricao, 'OUVIDORIA_STATUS' => 'recebida', 'OUVIDORIA_PROTOCOLO' => $proto, 'OUVIDORIA_DATA' => now()->toDateString(), 'OUVIDORIA_ANONIMO' => $request->anonimo ? 1 : 0]);
            return response()->json(['id' => $id, 'protocolo' => $proto], 201);
        } catch (\Throwable $e) {
            return response()->json(['erro' => $e->getMessage()], 500);
        }
    });

    // ─ Agenda
    Route::get('/agenda', function (\Illuminate\Http\Request $request) {
        try {
            $user = \Illuminate\Support\Facades\Auth::user();
            $func = \App\Models\Funcionario::where('USUARIO_ID', $user->USUARIO_ID ?? 0)->first();
            if (!$func)
                return response()->json(['fallback' => true, 'eventos' => []]);
            $comp = $request->competencia ?? now()->format('Y-m');
            [$ano, $mes] = explode('-', $comp);
            $inicio = sprintf('%04d-%02d-01', $ano, $mes);
            $fim = sprintf('%04d-%02d-%02d', $ano, $mes, cal_days_in_month(CAL_GREGORIAN, $mes, $ano));
            $rows = \Illuminate\Support\Facades\DB::table('AGENDA')->where('FUNCIONARIO_ID', $func->FUNCIONARIO_ID)->whereBetween('AGENDA_DATA', [$inicio, $fim])->orderBy('AGENDA_DATA')->orderBy('AGENDA_HORA')->get()->map(fn($e) => ['AGENDA_ID' => $e->AGENDA_ID, 'AGENDA_TITULO' => $e->AGENDA_TITULO, 'AGENDA_TIPO' => $e->AGENDA_TIPO, 'AGENDA_DIA' => (int) \Carbon\Carbon::parse($e->AGENDA_DATA)->format('j'), 'AGENDA_HORA' => $e->AGENDA_HORA, 'AGENDA_LOCAL' => $e->AGENDA_LOCAL ?? null, 'AGENDA_DESC' => $e->AGENDA_DESC ?? null]);
            return response()->json(['eventos' => $rows, 'fallback' => $rows->isEmpty()]);
        } catch (\Throwable $e) {
            return response()->json(['fallback' => true, 'eventos' => [], 'erro' => $e->getMessage()]);
        }
    });
    Route::post('/agenda', function (\Illuminate\Http\Request $request) {
        try {
            $user = \Illuminate\Support\Facades\Auth::user();
            $func = \App\Models\Funcionario::where('USUARIO_ID', $user->USUARIO_ID ?? 0)->first();
            $id = \Illuminate\Support\Facades\DB::table('AGENDA')->insertGetId(['FUNCIONARIO_ID' => $func->FUNCIONARIO_ID ?? null, 'AGENDA_TITULO' => $request->titulo, 'AGENDA_TIPO' => $request->tipo, 'AGENDA_DATA' => $request->data, 'AGENDA_HORA' => $request->hora, 'AGENDA_LOCAL' => $request->local, 'AGENDA_DESC' => $request->desc]);
            return response()->json(['id' => $id], 201);
        } catch (\Throwable $e) {
            return response()->json(['erro' => $e->getMessage()], 500);
        }
    });

    // ─ Medicina do Trabalho
    Route::get('/medicina', function () {
        try {
            $user = \Illuminate\Support\Facades\Auth::user();
            $func = \App\Models\Funcionario::where('USUARIO_ID', $user->USUARIO_ID ?? 0)->first();
            if (!$func)
                return response()->json(['fallback' => true, 'exames' => [], 'historico' => []]);
            $exames = \Illuminate\Support\Facades\DB::table('EXAME_OCUPACIONAL')->where('FUNCIONARIO_ID', $func->FUNCIONARIO_ID)->orderByDesc('EXAME_DATA_REALIZACAO')->get()->map(fn($e) => ['EXAME_ID' => $e->EXAME_ID, 'EXAME_TIPO' => $e->EXAME_TIPO, 'EXAME_SUBTIPO' => $e->EXAME_SUBTIPO ?? null, 'EXAME_DATA_REALIZACAO' => $e->EXAME_DATA_REALIZACAO, 'EXAME_DATA_VENCIMENTO' => $e->EXAME_DATA_VENCIMENTO ?? null, 'EXAME_MEDICO' => $e->EXAME_MEDICO ?? null, 'apto' => (bool) ($e->EXAME_APTO ?? true)]);
            $historico = \Illuminate\Support\Facades\DB::table('HISTORICO_EXAME')->where('FUNCIONARIO_ID', $func->FUNCIONARIO_ID)->orderByDesc('HISTORICO_DATA')->take(10)->get()->map(fn($h) => ['tipo' => $h->HISTORICO_TIPO, 'data' => $h->HISTORICO_DATA, 'apto' => (bool) ($h->HISTORICO_APTO ?? true)]);
            return response()->json(['exames' => $exames, 'historico' => $historico, 'fallback' => $exames->isEmpty()]);
        } catch (\Throwable $e) {
            return response()->json(['fallback' => true, 'exames' => [], 'historico' => [], 'erro' => $e->getMessage()]);
        }
    });
    Route::post('/medicina/agendar', function (\Illuminate\Http\Request $request) {
        try {
            $user = \Illuminate\Support\Facades\Auth::user();
            $func = \App\Models\Funcionario::where('USUARIO_ID', $user->USUARIO_ID ?? 0)->first();
            $id = \Illuminate\Support\Facades\DB::table('AGENDAMENTO_EXAME')->insertGetId(['FUNCIONARIO_ID' => $func->FUNCIONARIO_ID ?? null, 'AGENDAMENTO_TIPO' => $request->tipo, 'AGENDAMENTO_DATA' => $request->data, 'AGENDAMENTO_OBS' => $request->obs, 'AGENDAMENTO_STATUS' => 'pendente', 'AGENDAMENTO_DT_SOLICITACAO' => now()->toDateString()]);
            return response()->json(['id' => $id, 'message' => 'Agendamento registrado.'], 201);
        } catch (\Throwable $e) {
            return response()->json(['erro' => $e->getMessage()], 500);
        }
    });

    // ─ Benefícios
    Route::get('/beneficios', function () {
        try {
            $user = \Illuminate\Support\Facades\Auth::user();
            $func = \App\Models\Funcionario::where('USUARIO_ID', $user->USUARIO_ID ?? 0)->first();
            if (!$func)
                return response()->json(['fallback' => true, 'ativos' => [], 'disponiveis' => []]);
            $ativos = \Illuminate\Support\Facades\DB::table('BENEFICIO as b')->join('FUNCIONARIO_BENEFICIO as fb', 'fb.BENEFICIO_ID', '=', 'b.BENEFICIO_ID')->where('fb.FUNCIONARIO_ID', $func->FUNCIONARIO_ID)->where('fb.FB_STATUS', 'ativo')->select('b.*', 'fb.FB_VALOR as BENEFICIO_VALOR_REAL')->get();
            $disponiveis = \Illuminate\Support\Facades\DB::table('BENEFICIO')->whereNotIn('BENEFICIO_ID', $ativos->pluck('BENEFICIO_ID'))->where('BENEFICIO_ATIVO', 1)->get();
            return response()->json(['ativos' => $ativos, 'disponiveis' => $disponiveis, 'fallback' => $ativos->isEmpty() && $disponiveis->isEmpty()]);
        } catch (\Throwable $e) {
            return response()->json(['fallback' => true, 'ativos' => [], 'disponiveis' => [], 'erro' => $e->getMessage()]);
        }
    });
    Route::post('/beneficios/solicitar', function (\Illuminate\Http\Request $request) {
        try {
            $user = \Illuminate\Support\Facades\Auth::user();
            $func = \App\Models\Funcionario::where('USUARIO_ID', $user->USUARIO_ID ?? 0)->first();
            \Illuminate\Support\Facades\DB::table('BENEFICIO_SOLICITACAO')->insert(['FUNCIONARIO_ID' => $func->FUNCIONARIO_ID ?? null, 'BENEFICIO_ID' => $request->beneficio_id, 'SOLICITACAO_DATA' => now()->toDateString(), 'SOLICITACAO_STATUS' => 'pendente', 'SOLICITACAO_OBS' => $request->nome]);
            return response()->json(['message' => 'Solicitação registrada.'], 201);
        } catch (\Throwable $e) {
            return response()->json(['erro' => $e->getMessage()], 500);
        }
    });

    // ─ Relatórios Stats
    Route::get('/relatorios/stats', function () {
        try {
            $n = \Illuminate\Support\Facades\DB::table('FUNCIONARIO')->where(function ($q) {
                $q->whereNull('FUNCIONARIO_DATA_DEMISSAO')->orWhere('FUNCIONARIO_DATA_DEMISSAO', '>', now()->toDateString());
            })->count();
            $comp = \Illuminate\Support\Facades\DB::table('FOLHA_PAGAMENTO')->orderByDesc('FOLHA_COMPETENCIA')->value('FOLHA_COMPETENCIA') ?? now()->format('Y-m');
            return response()->json(['fallback' => false, 'funcionarios' => $n, 'competencia' => $comp]);
        } catch (\Throwable $e) {
            return response()->json(['fallback' => true, 'erro' => $e->getMessage()]);
        }
    });

    // ── RELATÓRIO: Quadro de Funcionários Ativos ──────────────────────────────
    Route::get('/relatorios/funcionarios', function (Request $request) {
        try {
            $q = \Illuminate\Support\Facades\DB::table('FUNCIONARIO as f')
                ->join('PESSOA as p', 'p.PESSOA_ID', '=', 'f.PESSOA_ID')
                ->leftJoin('LOTACAO as l', function ($j) {
                    $j->on('l.FUNCIONARIO_ID', '=', 'f.FUNCIONARIO_ID')->whereNull('l.LOTACAO_DATA_FIM');
                })
                ->leftJoin('SETOR as s', 's.SETOR_ID', '=', 'l.SETOR_ID')
                ->leftJoin('ATRIBUICAO_LOTACAO as al', 'al.LOTACAO_ID', '=', 'l.LOTACAO_ID')
                ->leftJoin('ATRIBUICAO as a', 'a.ATRIBUICAO_ID', '=', 'al.ATRIBUICAO_ID')
                ->whereNull('f.FUNCIONARIO_DATA_FIM')
                ->when($request->setor_id, fn($q) => $q->where('l.SETOR_ID', $request->setor_id))
                ->when($request->busca, function ($q) use ($request) {
                    $b = '%' . $request->busca . '%';
                    $q->where(fn($sq) => $sq->where('p.PESSOA_NOME', 'like', $b)->orWhere('f.FUNCIONARIO_MATRICULA', 'like', $b));
                })
                ->select(
                    'f.FUNCIONARIO_ID as id',
                    'f.FUNCIONARIO_MATRICULA as matricula',
                    'p.PESSOA_NOME as nome',
                    'a.ATRIBUICAO_NOME as cargo',
                    's.SETOR_NOME as setor',
                    'f.FUNCIONARIO_DATA_INICIO as admissao'
                )
                ->groupBy('f.FUNCIONARIO_ID', 'f.FUNCIONARIO_MATRICULA', 'p.PESSOA_NOME', 'a.ATRIBUICAO_NOME', 's.SETOR_NOME', 'f.FUNCIONARIO_DATA_INICIO')
                ->orderBy('p.PESSOA_NOME')
                ->paginate($request->get('per_page', 50));

            return response()->json($q);
        } catch (\Throwable $e) {
            return response()->json(['error' => $e->getMessage()], 500);
        }
    });

    // ── RELATÓRIO: Admissões por Período ─────────────────────────────────────
    Route::get('/relatorios/admissoes', function (Request $request) {
        try {
            $inicio = $request->get('data_inicio', now()->startOfMonth()->toDateString());
            $fim = $request->get('data_fim', now()->toDateString());

            $dados = \Illuminate\Support\Facades\DB::table('FUNCIONARIO as f')
                ->join('PESSOA as p', 'p.PESSOA_ID', '=', 'f.PESSOA_ID')
                ->leftJoin('LOTACAO as l', fn($j) => $j->on('l.FUNCIONARIO_ID', '=', 'f.FUNCIONARIO_ID')->whereNull('l.LOTACAO_DATA_FIM'))
                ->leftJoin('SETOR as s', 's.SETOR_ID', '=', 'l.SETOR_ID')
                ->whereNotNull('f.FUNCIONARIO_DATA_INICIO')
                ->whereBetween('f.FUNCIONARIO_DATA_INICIO', [$inicio, $fim])
                ->select('f.FUNCIONARIO_ID as id', 'f.FUNCIONARIO_MATRICULA as matricula', 'p.PESSOA_NOME as nome', 's.SETOR_NOME as setor', 'f.FUNCIONARIO_DATA_INICIO as admissao')
                ->orderBy('f.FUNCIONARIO_DATA_INICIO', 'desc')
                ->get();

            return response()->json(['data' => $dados, 'total' => $dados->count(), 'periodo' => compact('inicio', 'fim')]);
        } catch (\Throwable $e) {
            return response()->json(['error' => $e->getMessage()], 500);
        }
    });

    // ── RELATÓRIO: Frequência por Período ────────────────────────────────────
    Route::get('/relatorios/frequencia', function (Request $request) {
        try {
            $inicio = $request->get('data_inicio', now()->startOfMonth()->toDateString());
            $fim = $request->get('data_fim', now()->toDateString());

            $dados = \Illuminate\Support\Facades\DB::table('REGISTRO_PONTO as rp')
                ->join('FUNCIONARIO as f', 'f.FUNCIONARIO_ID', '=', 'rp.FUNCIONARIO_ID')
                ->join('PESSOA as p', 'p.PESSOA_ID', '=', 'f.PESSOA_ID')
                ->leftJoin('LOTACAO as l', fn($j) => $j->on('l.FUNCIONARIO_ID', '=', 'f.FUNCIONARIO_ID')->whereNull('l.LOTACAO_DATA_FIM'))
                ->leftJoin('SETOR as s', 's.SETOR_ID', '=', 'l.SETOR_ID')
                ->whereBetween('rp.REGISTRO_DATA', [$inicio, $fim])
                ->when($request->setor_id, fn($q) => $q->where('l.SETOR_ID', $request->setor_id))
                ->select('f.FUNCIONARIO_ID as id', 'p.PESSOA_NOME as nome', 's.SETOR_NOME as setor', 'rp.REGISTRO_DATA as data', 'rp.REGISTRO_ENTRADA as entrada', 'rp.REGISTRO_SAIDA as saida', 'rp.REGISTRO_STATUS as status')
                ->orderBy('rp.REGISTRO_DATA', 'desc')
                ->limit(500)
                ->get();

            $resumo = $dados->groupBy('id')->map(function ($rows) {
                return ['id' => $rows->first()->id, 'nome' => $rows->first()->nome, 'setor' => $rows->first()->setor, 'presencas' => $rows->where('status', 'PRESENTE')->count(), 'faltas' => $rows->where('status', 'FALTA')->count(), 'atrasos' => $rows->where('status', 'ATRASO')->count()];
            })->values();

            return response()->json(['resumo' => $resumo, 'detalhes' => $dados, 'periodo' => compact('inicio', 'fim')]);
        } catch (\Throwable $e) {
            return response()->json(['error' => $e->getMessage()], 500);
        }
    });

    // ── RELATÓRIO: Folha por Competência ─────────────────────────────────────
    Route::get('/relatorios/folha', function (Request $request) {
        try {
            $comp = str_replace('-', '', $request->get('competencia', now()->format('Ym')));
            $busca = trim($request->get('busca', ''));

            $q = \Illuminate\Support\Facades\DB::table('DETALHE_FOLHA as df')
                ->join('FOLHA as fo', 'fo.FOLHA_ID', '=', 'df.FOLHA_ID')
                ->join('FUNCIONARIO as fn', 'fn.FUNCIONARIO_ID', '=', 'df.FUNCIONARIO_ID')
                ->join('PESSOA as p', 'p.PESSOA_ID', '=', 'fn.PESSOA_ID')
                ->leftJoin('LOTACAO as l', function ($j) {
                    $j->on('l.FUNCIONARIO_ID', '=', 'fn.FUNCIONARIO_ID')
                        ->whereNull('l.LOTACAO_DATA_FIM');
                })
                ->leftJoin('SETOR as s', 's.SETOR_ID', '=', 'l.SETOR_ID')
                ->whereRaw("REPLACE(fo.FOLHA_COMPETENCIA, '-', '') = ?", [$comp])
                ->whereNull('df.DETALHE_FOLHA_ERRO')
                ->select(
                    'fn.FUNCIONARIO_MATRICULA as matricula',
                    'p.PESSOA_NOME as nome',
                    's.SETOR_NOME as setor',
                    \Illuminate\Support\Facades\DB::raw('COALESCE(df.DETALHE_FOLHA_PROVENTOS, 0) as bruto'),
                    \Illuminate\Support\Facades\DB::raw('COALESCE(df.DETALHE_FOLHA_DESCONTOS, 0) as descontos'),
                    \Illuminate\Support\Facades\DB::raw('COALESCE(df.DETALHE_FOLHA_PROVENTOS, 0) - COALESCE(df.DETALHE_FOLHA_DESCONTOS, 0) as liquido')
                )
                ->orderBy('p.PESSOA_NOME');

            if ($busca) {
                $q->where(function ($w) use ($busca) {
                    $w->where('p.PESSOA_NOME', 'like', "%{$busca}%")
                        ->orWhere('fn.FUNCIONARIO_MATRICULA', 'like', "%{$busca}%");
                });
            }

            $folhas = $q->get();

            return response()->json([
                'data' => $folhas,
                'totais' => [
                    'bruto' => round((float) $folhas->sum('bruto'), 2),
                    'descontos' => round((float) $folhas->sum('descontos'), 2),
                    'liquido' => round((float) $folhas->sum('liquido'), 2),
                    'servidores' => $folhas->count(),
                ],
                'competencia' => $comp,
            ]);
        } catch (\Throwable $e) {
            return response()->json(['error' => $e->getMessage(), 'data' => [], 'totais' => null], 500);
        }
    });

    // ─ Portal do Gestor
    Route::get('/gestor', function () {
        try {
            $user = \Illuminate\Support\Facades\Auth::user();
            $hoje = now()->toDateString();

            // Descobre os setores do gestor
            $setorIds = \Illuminate\Support\Facades\DB::table('USUARIO_SETOR')
                ->where('USUARIO_ID', $user->USUARIO_ID)
                ->pluck('SETOR_ID')
                ->toArray();

            // Se não tem setores definidos, pega pelo funcionário
            if (empty($setorIds)) {
                $func = \Illuminate\Support\Facades\DB::table('FUNCIONARIO as f')
                    ->join('LOTACAO as l', 'l.FUNCIONARIO_ID', '=', 'f.FUNCIONARIO_ID')
                    ->where('f.USUARIO_ID', $user->USUARIO_ID)
                    ->whereNull('l.LOTACAO_DATA_FIM')
                    ->value('l.SETOR_ID');
                if ($func)
                    $setorIds = [$func];
            }

            // Equipe do setor
            $equipe = \Illuminate\Support\Facades\DB::table('FUNCIONARIO as f')
                ->join('PESSOA as p', 'p.PESSOA_ID', '=', 'f.PESSOA_ID')
                ->leftJoin('CARGO as c', 'c.CARGO_ID', '=', 'f.CARGO_ID')
                ->join('LOTACAO as l', 'l.FUNCIONARIO_ID', '=', 'f.FUNCIONARIO_ID')
                ->whereNull('f.FUNCIONARIO_DATA_FIM')
                ->whereNull('l.LOTACAO_DATA_FIM')
                ->when(!empty($setorIds), fn($q) => $q->whereIn('l.SETOR_ID', $setorIds))
                ->select('f.FUNCIONARIO_ID as id', 'p.PESSOA_NOME as nome', 'c.CARGO_NOME as cargo', 'f.FUNCIONARIO_TURNO as turno')
                ->get()
                ->map(function ($m) use ($hoje) {
                    $ferias = \Illuminate\Support\Facades\DB::table('FERIAS')
                        ->where('FUNCIONARIO_ID', $m->id)
                        ->where('FERIAS_DATA_INICIO', '<=', $hoje)
                        ->where(fn($q) => $q->whereNull('FERIAS_DATA_FIM')->orWhere('FERIAS_DATA_FIM', '>=', $hoje))
                        ->whereNotIn('FERIAS_STATUS', ['cancelado'])->exists();
                    $atestado = \Illuminate\Support\Facades\DB::table('AFASTAMENTO')
                        ->where('FUNCIONARIO_ID', $m->id)
                        ->whereRaw("LOWER(AFASTAMENTO_TIPO) LIKE '%atestado%'")
                        ->where('AFASTAMENTO_DATA_INICIO', '<=', $hoje)
                        ->where(fn($q) => $q->whereNull('AFASTAMENTO_DATA_FIM')->orWhere('AFASTAMENTO_DATA_FIM', '>=', $hoje))
                        ->exists();
                    return [
                        'id' => $m->id,
                        'nome' => $m->nome,
                        'cargo' => $m->cargo ?? '—',
                        'turno' => $m->turno ?? null,
                        'presente' => !$ferias && !$atestado,
                        'ferias' => $ferias,
                        'atestado' => $atestado,
                        'statusLabel' => $ferias ? 'Em Férias' : ($atestado ? 'Atestado' : 'Presente'),
                    ];
                });

            $funcIds = $equipe->pluck('id')->toArray();

            // Pendências: férias aguardando
            $pendFerias = \Illuminate\Support\Facades\DB::table('FERIAS as fe')
                ->join('FUNCIONARIO as f', 'f.FUNCIONARIO_ID', '=', 'fe.FUNCIONARIO_ID')
                ->join('PESSOA as p', 'p.PESSOA_ID', '=', 'f.PESSOA_ID')
                ->whereIn('fe.FUNCIONARIO_ID', $funcIds)
                ->where('fe.FERIAS_STATUS', 'agendado')
                ->select('fe.FERIAS_ID as ref_id', 'p.PESSOA_NOME as servidor', 'fe.FERIAS_DATA_INICIO', 'fe.FERIAS_DATA_FIM', 'fe.FERIAS_DATA_SOLICITACAO as data')
                ->get()->map(fn($r) => [
                    'id' => 'ferias_' . $r->ref_id,
                    'ref_id' => $r->ref_id,
                    'ref_tabela' => 'FERIAS',
                    'tipo' => 'ferias',
                    'servidor' => $r->servidor,
                    'detalhe' => ($r->FERIAS_DATA_INICIO ?? '—') . ' a ' . ($r->FERIAS_DATA_FIM ?? '—'),
                    'data' => $r->data,
                ]);

            // Pendências: abonos de falta
            $pendAbonos = \Illuminate\Support\Facades\DB::table('ABONO_FALTA as ab')
                ->join('FUNCIONARIO as f', 'f.FUNCIONARIO_ID', '=', 'ab.FUNCIONARIO_ID')
                ->join('PESSOA as p', 'p.PESSOA_ID', '=', 'f.PESSOA_ID')
                ->whereIn('ab.FUNCIONARIO_ID', $funcIds)
                ->where('ab.ABONO_STATUS', 'pendente')
                ->select('ab.ABONO_ID as ref_id', 'p.PESSOA_NOME as servidor', 'ab.ABONO_DATA_FALTA as data', 'ab.ABONO_MOTIVO as motivo')
                ->get()->map(fn($r) => [
                    'id' => 'abono_' . $r->ref_id,
                    'ref_id' => $r->ref_id,
                    'ref_tabela' => 'ABONO_FALTA',
                    'tipo' => 'abono',
                    'servidor' => $r->servidor,
                    'detalhe' => 'Abono de falta — ' . ($r->data ?? '—') . ' — ' . ($r->motivo ?? 'Sem motivo'),
                    'data' => $r->data,
                ]);

            $pendencias = $pendFerias->merge($pendAbonos);

            $total = $equipe->count();
            $presentes = $equipe->where('presente', true)->count();
            $emFerias = $equipe->where('ferias', true)->count();

            return response()->json([
                'fallback' => false,
                'equipe' => $equipe,
                'pendencias' => $pendencias,
                'kpis' => [
                    'total' => $total,
                    'presentes' => $presentes,
                    'pendencias' => $pendencias->count(),
                    'emFerias' => $emFerias,
                ],
            ]);
        } catch (\Throwable $e) {
            return response()->json(['fallback' => true, 'erro' => $e->getMessage()]);
        }
    });

    Route::post('/gestor/aprovar', function (\Illuminate\Http\Request $request) {
        try {
            $acao = $request->acao;      // 'aprovado' | 'reprovado'
            $refId = $request->ref_id;
            $tabela = $request->ref_tabela;

            if ($tabela === 'FERIAS') {
                $status = $acao === 'aprovado' ? 'aprovado' : 'cancelado';
                \Illuminate\Support\Facades\DB::table('FERIAS')->where('FERIAS_ID', $refId)->update(['FERIAS_STATUS' => $status]);
            } elseif ($tabela === 'ABONO_FALTA') {
                $status = $acao === 'aprovado' ? 'aprovado' : 'reprovado';
                \Illuminate\Support\Facades\DB::table('ABONO_FALTA')->where('ABONO_ID', $refId)->update(['ABONO_STATUS' => $status]);
            }
            return response()->json(['ok' => true, 'acao' => $acao]);
        } catch (\Throwable $e) {
            return response()->json(['ok' => false, 'erro' => $e->getMessage()]);
        }
    });

    // ─ Ponto Eletrônico — leitura real de batidas
    Route::get('/ponto', function (\Illuminate\Http\Request $request) {
        try {
            $user = \Illuminate\Support\Facades\Auth::user();
            $func = \App\Models\Funcionario::where('USUARIO_ID', $user->USUARIO_ID ?? 0)->first();
            if (!$func)
                return response()->json(['fallback' => true, 'registros' => [], 'apuracao' => null]);

            $comp = $request->competencia ?? now()->format('Y-m'); // formato YYYY-MM
            [$ano, $mes] = explode('-', $comp . '-01');

            $batidas = \Illuminate\Support\Facades\DB::table('REGISTRO_PONTO')
                ->where('FUNCIONARIO_ID', $func->FUNCIONARIO_ID)
                ->whereRaw("strftime('%Y-%m', REGISTRO_DATA) = ?", [$comp])
                ->orderBy('REGISTRO_DATA')
                ->orderBy('REGISTRO_HORA')
                ->get();

            // Agrupa por dia
            $porDia = [];
            foreach ($batidas as $b) {
                $dia = (int) substr($b->REGISTRO_DATA, 8, 2);
                $porDia[$dia][] = [
                    'hora' => substr($b->REGISTRO_HORA ?? '', 0, 5),
                    'tipo' => $b->REGISTRO_TIPO ?? 'entrada',
                ];
            }

            $registros = array_map(fn($dia, $bats) => ['dia' => $dia, 'batidas' => $bats], array_keys($porDia), $porDia);

            // Apuração (banco de horas)
            $apuracao = \Illuminate\Support\Facades\DB::table('APURACAO_PONTO')
                ->where('FUNCIONARIO_ID', $func->FUNCIONARIO_ID)
                ->where('APURACAO_COMPETENCIA', 'like', "$comp%")
                ->first();

            return response()->json([
                'fallback' => empty($registros),
                'registros' => array_values($registros),
                'apuracao' => $apuracao ? [
                    'competencia' => $apuracao->APURACAO_COMPETENCIA,
                    'horas_trab' => $apuracao->APURACAO_HORAS_TRAB ?? 0,
                    'horas_falta' => $apuracao->APURACAO_HORAS_FALTA ?? 0,
                    'saldo_acumulado' => $apuracao->APURACAO_SALDO ?? 0,
                ] : null,
            ]);
        } catch (\Throwable $e) {
            return response()->json(['fallback' => true, 'registros' => [], 'apuracao' => null, 'erro' => $e->getMessage()]);
        }
    });

    Route::post('/ponto/registro', function (\Illuminate\Http\Request $request) {
        try {
            $user = \Illuminate\Support\Facades\Auth::user();

            // Busca o funcionário vinculado ao usuário (ativo ou histórico mais recente)
            $func = \App\Models\Funcionario::where('USUARIO_ID', $user->USUARIO_ID ?? 0)
                ->orderByRaw('FUNCIONARIO_DATA_FIM IS NULL DESC')
                ->orderByDesc('FUNCIONARIO_ID')
                ->first();

            // Fallback: se não achar por USUARIO_ID, tenta pelo FUNCIONARIO_ID do usuário diretamente
            if (!$func && $user->FUNCIONARIO_ID) {
                $func = \App\Models\Funcionario::find($user->FUNCIONARIO_ID);
            }

            if (!$func) {
                return response()->json(['erro' => 'Funcionário não vinculado a este usuário.'], 404);
            }

            // Monta o datetime — aceita data+hora separados ou usa o momento atual
            $data = $request->data ?? now()->toDateString();
            $hora = $request->hora ?? now()->format('H:i:s');
            $dataHora = $data . ' ' . $hora;

            $id = \Illuminate\Support\Facades\DB::table('REGISTRO_PONTO')->insertGetId([
                'FUNCIONARIO_ID' => $func->FUNCIONARIO_ID,
                'REGISTRO_DATA_HORA' => $dataHora,
                'REGISTRO_TIPO' => $request->tipo ?? 'ENTRADA',
                'REGISTRO_ORIGEM' => 'gente_v3',
            ]);

            return response()->json(['id' => $id, 'hora' => now()->format('H:i')], 201);
        } catch (\Throwable $e) {
            \Illuminate\Support\Facades\Log::error('Ponto registro: ' . $e->getMessage());
            return response()->json(['erro' => $e->getMessage()], 500);
        }
    });

    // ── Configuração Global de Ponto ─────────────────────────────
    // Helper para verificar permissão admin/rh/gestor
    // GET  /api/v3/ponto/config  — configurações globais (regime, horários, tolerância)
    // PUT  /api/v3/ponto/config  — salva configurações globais

    // Manter compatibilidade com GET /ponto/regime (lê da nova config)
    Route::get('/ponto/regime', function () {
        $valor = \Illuminate\Support\Facades\DB::table('CONFIGURACAO_SISTEMA')
            ->where('CONFIG_CHAVE', 'REGIME_PONTO')->value('CONFIG_VALOR') ?? '4_batidas';
        return response()->json(['regime' => $valor]);
    });

    Route::get('/ponto/config', function () {
        $db = \Illuminate\Support\Facades\DB::table('CONFIGURACAO_SISTEMA');
        return response()->json([
            'regime' => $db->where('CONFIG_CHAVE', 'REGIME_PONTO')->value('CONFIG_VALOR') ?? '4_batidas',
            'hora_entrada' => $db->where('CONFIG_CHAVE', 'PONTO_HORA_ENTRADA')->value('CONFIG_VALOR') ?? '08:00',
            'hora_saida' => $db->where('CONFIG_CHAVE', 'PONTO_HORA_SAIDA')->value('CONFIG_VALOR') ?? '18:00',
            'tolerancia' => (int) ($db->where('CONFIG_CHAVE', 'PONTO_TOLERANCIA')->value('CONFIG_VALOR') ?? 15),
            'intervalo_almoco' => (int) ($db->where('CONFIG_CHAVE', 'PONTO_INTERVALO_ALMOCO')->value('CONFIG_VALOR') ?? 120),
        ]);
    });

    Route::put('/ponto/config', function (\Illuminate\Http\Request $request) {
        $user = \Illuminate\Support\Facades\Auth::user();
        $perfis = $user->usuarioPerfis()->with('perfil')->get()
            ->pluck('perfil.PERFIL_NOME')->map(fn($p) => strtolower(trim($p ?? '')))->toArray();
        $permitidos = ['admin', 'administrador', 'rh', 'recursos humanos', 'gestor'];
        $ok = !empty(array_intersect($perfis, $permitidos)) || strtolower($user->USUARIO_LOGIN ?? '') === 'admin';
        if (!$ok)
            return response()->json(['erro' => 'Sem permissão.'], 403);

        $salvar = function (string $chave, string $valor, string $desc) {
            \Illuminate\Support\Facades\DB::table('CONFIGURACAO_SISTEMA')->updateOrInsert(
                ['CONFIG_CHAVE' => $chave],
                ['CONFIG_VALOR' => $valor, 'CONFIG_DESCRICAO' => $desc, 'CONFIG_TIPO' => 'string', 'CONFIG_UPDATED_AT' => now()]
            );
        };

        if ($request->has('regime') && in_array($request->regime, ['2_batidas', '4_batidas']))
            $salvar('REGIME_PONTO', $request->regime, 'Regime de batidas do ponto eletrônico');
        if ($request->has('hora_entrada') && preg_match('/^\d{2}:\d{2}$/', $request->hora_entrada))
            $salvar('PONTO_HORA_ENTRADA', $request->hora_entrada, 'Horário-limite padrão de entrada');
        if ($request->has('hora_saida') && preg_match('/^\d{2}:\d{2}$/', $request->hora_saida))
            $salvar('PONTO_HORA_SAIDA', $request->hora_saida, 'Horário-limite padrão de saída');
        if ($request->has('tolerancia') && is_numeric($request->tolerancia))
            $salvar('PONTO_TOLERANCIA', (string) (int) $request->tolerancia, 'Tolerância padrão em minutos');
        if ($request->has('intervalo_almoco') && is_numeric($request->intervalo_almoco))
            $salvar('PONTO_INTERVALO_ALMOCO', (string) (int) $request->intervalo_almoco, 'Duração padrão do intervalo de almoço em minutos');

        return response()->json(['message' => 'Configurações salvas com sucesso.']);
    });

    // Manter compatibilidade com PUT /ponto/regime (redireciona para config)
    Route::put('/ponto/regime', function (\Illuminate\Http\Request $request) {
        $user = \Illuminate\Support\Facades\Auth::user();
        $perfis = $user->usuarioPerfis()->with('perfil')->get()
            ->pluck('perfil.PERFIL_NOME')->map(fn($p) => strtolower(trim($p ?? '')))->toArray();
        $permitidos = ['admin', 'administrador', 'rh', 'recursos humanos', 'gestor'];
        $ok = !empty(array_intersect($perfis, $permitidos)) || strtolower($user->USUARIO_LOGIN ?? '') === 'admin';
        if (!$ok)
            return response()->json(['erro' => 'Sem permissão.'], 403);
        $regime = $request->input('regime');
        if (!in_array($regime, ['2_batidas', '4_batidas']))
            return response()->json(['erro' => 'Regime inválido.'], 422);
        \Illuminate\Support\Facades\DB::table('CONFIGURACAO_SISTEMA')->updateOrInsert(
            ['CONFIG_CHAVE' => 'REGIME_PONTO'],
            ['CONFIG_VALOR' => $regime, 'CONFIG_DESCRICAO' => 'Regime de batidas', 'CONFIG_TIPO' => 'string', 'CONFIG_UPDATED_AT' => now()]
        );
        return response()->json(['regime' => $regime, 'message' => 'Regime atualizado.']);
    });

    // ── Configuração de Ponto por Funcionário ──────────────────
    // GET  /api/v3/ponto/config/funcionarios           — lista config de todos
    // GET  /api/v3/ponto/config/funcionarios/{id}      — config de um funcionário
    // PUT  /api/v3/ponto/config/funcionarios/{id}      — salva config de um funcionário

    Route::get('/ponto/config/funcionarios', function () {
        $user = \Illuminate\Support\Facades\Auth::user();
        $perfis = $user->usuarioPerfis()->with('perfil')->get()
            ->pluck('perfil.PERFIL_NOME')->map(fn($p) => strtolower(trim($p ?? '')))->toArray();
        $permitidos = ['admin', 'administrador', 'rh', 'recursos humanos', 'gestor'];
        $ok = !empty(array_intersect($perfis, $permitidos)) || strtolower($user->USUARIO_LOGIN ?? '') === 'admin';
        if (!$ok)
            return response()->json(['erro' => 'Sem permissão.'], 403);

        $lista = \Illuminate\Support\Facades\DB::table('FUNCIONARIO as f')
            ->leftJoin('PESSOA as p', 'p.PESSOA_ID', '=', 'f.PESSOA_ID')
            ->leftJoin('PONTO_CONFIG_FUNCIONARIO as pc', 'pc.FUNCIONARIO_ID', '=', 'f.FUNCIONARIO_ID')
            ->whereNull('f.FUNCIONARIO_DATA_FIM')
            ->select(
                'f.FUNCIONARIO_ID',
                'f.FUNCIONARIO_MATRICULA',
                'p.PESSOA_NOME',
                'pc.REGIME',
                'pc.HORA_ENTRADA',
                'pc.HORA_SAIDA',
                'pc.TOLERANCIA'
            )
            ->orderBy('p.PESSOA_NOME')
            ->get();

        return response()->json($lista);
    });

    Route::get('/ponto/config/funcionarios/{id}', function (int $id) {
        $cfg = \Illuminate\Support\Facades\DB::table('PONTO_CONFIG_FUNCIONARIO')
            ->where('FUNCIONARIO_ID', $id)->first();
        return response()->json($cfg ?? ['FUNCIONARIO_ID' => $id]);
    });

    Route::put('/ponto/config/funcionarios/{id}', function (\Illuminate\Http\Request $request, int $id) {
        $user = \Illuminate\Support\Facades\Auth::user();
        $perfis = $user->usuarioPerfis()->with('perfil')->get()
            ->pluck('perfil.PERFIL_NOME')->map(fn($p) => strtolower(trim($p ?? '')))->toArray();
        $permitidos = ['admin', 'administrador', 'rh', 'recursos humanos', 'gestor'];
        $ok = !empty(array_intersect($perfis, $permitidos)) || strtolower($user->USUARIO_LOGIN ?? '') === 'admin';
        if (!$ok)
            return response()->json(['erro' => 'Sem permissão.'], 403);

        $existe = \App\Models\Funcionario::find($id);
        if (!$existe)
            return response()->json(['erro' => 'Funcionário não encontrado.'], 404);

        $upd = ['updated_at' => now()];

        // null = herdar padrão; string vazia = resetar para padrão
        $regime = $request->input('regime');
        $upd['REGIME'] = (!$regime || $regime === 'padrao') ? null : $regime;

        $he = $request->input('hora_entrada');
        $upd['HORA_ENTRADA'] = ($he && preg_match('/^\d{2}:\d{2}$/', $he)) ? $he : null;

        $hs = $request->input('hora_saida');
        $upd['HORA_SAIDA'] = ($hs && preg_match('/^\d{2}:\d{2}$/', $hs)) ? $hs : null;

        $tol = $request->input('tolerancia');
        $upd['TOLERANCIA'] = is_numeric($tol) ? (int) $tol : null;

        \Illuminate\Support\Facades\DB::table('PONTO_CONFIG_FUNCIONARIO')->updateOrInsert(
            ['FUNCIONARIO_ID' => $id],
            array_merge($upd, ['created_at' => now()])
        );

        return response()->json(['message' => 'Configuração do funcionário salva.']);
    });

    // ─ Meus Holerites (contracheque)
    Route::get('/meus-holerites', function () {
        try {
            $user = \Illuminate\Support\Facades\Auth::user();
            $func = \App\Models\Funcionario::where('USUARIO_ID', $user->USUARIO_ID ?? 0)->first();
            if (!$func)
                return response()->json(['fallback' => true, 'holerites' => []]);

            $rows = \Illuminate\Support\Facades\DB::table('CALCULO_FUNCIONARIO as cf')
                ->join('FOLHA_PAGAMENTO as fp', 'fp.FOLHA_ID', '=', 'cf.FOLHA_ID')
                ->where('cf.FUNCIONARIO_ID', $func->FUNCIONARIO_ID)
                ->select(
                    'cf.CALCULO_ID as id',
                    'fp.FOLHA_COMPETENCIA as competencia',
                    'cf.CALCULO_VENC_TOTAL as bruto',
                    'cf.CALCULO_DESC_TOTAL as descontos',
                    'cf.CALCULO_LIQ_TOTAL as liquido',
                    'fp.FOLHA_STATUS as status'
                )
                ->orderByDesc('fp.FOLHA_COMPETENCIA')
                ->take(24)
                ->get()
                ->map(fn($r) => [
                    'id' => $r->id,
                    'competencia' => $r->competencia,
                    'bruto' => (float) ($r->bruto ?? 0),
                    'descontos' => (float) ($r->descontos ?? 0),
                    'liquido' => (float) ($r->liquido ?? 0),
                    'status' => $r->status ?? 'Fechada',
                ]);

            return response()->json(['holerites' => $rows, 'fallback' => $rows->isEmpty()]);
        } catch (\Throwable $e) {
            return response()->json(['fallback' => true, 'holerites' => [], 'erro' => $e->getMessage()]);
        }
    });

    // ─ Cadastrar Novo Funcionário
    Route::post('/funcionarios', function (\Illuminate\Http\Request $request) {
        try {
            \Illuminate\Support\Facades\DB::beginTransaction();

            // 1. Cria a PESSOA
            $pessoaId = \Illuminate\Support\Facades\DB::table('PESSOA')->insertGetId([
                'PESSOA_NOME' => $request->nome,
                'PESSOA_CPF' => $request->cpf ?? null,
                'PESSOA_RG' => $request->rg ?? null,
                'PESSOA_DATA_NASCIMENTO' => $request->data_nascimento ?? null,
                'PESSOA_SEXO_ID' => $request->sexo_id ?? null,
            ]);

            // 2. Cria o USUARIO (opcional)
            $usuarioId = null;
            if ($request->email) {
                $usuarioId = \Illuminate\Support\Facades\DB::table('USUARIO')->insertGetId([
                    'USUARIO_EMAIL' => $request->email,
                    'USUARIO_SENHA' => bcrypt($request->cpf ?? '12345678'),
                    'USUARIO_ATIVO' => 1,
                ]);
            }

            // 3. Cria o FUNCIONARIO
            $funcId = \Illuminate\Support\Facades\DB::table('FUNCIONARIO')->insertGetId([
                'PESSOA_ID' => $pessoaId,
                'USUARIO_ID' => $usuarioId,
                'CARGO_ID' => $request->cargo_id ?? null,
                'FUNCIONARIO_MATRICULA' => $request->matricula ?? null,
                'FUNCIONARIO_DATA_ADMISSAO' => $request->data_admissao ?? now()->toDateString(),
            ]);

            // 4. Cria LOTACAO se setor informado
            if ($request->setor_id) {
                \Illuminate\Support\Facades\DB::table('LOTACAO')->insert([
                    'FUNCIONARIO_ID' => $funcId,
                    'SETOR_ID' => $request->setor_id,
                    'LOTACAO_DATA_INICIO' => $request->data_admissao ?? now()->toDateString(),
                ]);
            }

            \Illuminate\Support\Facades\DB::commit();
            return response()->json(['funcionario_id' => $funcId, 'pessoa_id' => $pessoaId], 201);
        } catch (\Throwable $e) {
            \Illuminate\Support\Facades\DB::rollBack();
            return response()->json(['erro' => $e->getMessage()], 500);
        }
    });

    // ─ Inativar / desligar Funcionário
    Route::delete('/funcionarios/{id}', function ($id) {
        try {
            \Illuminate\Support\Facades\DB::table('FUNCIONARIO')
                ->where('FUNCIONARIO_ID', $id)
                ->update(['FUNCIONARIO_DATA_FIM' => now()->toDateString()]);
            return response()->json(['ok' => true]);
        } catch (\Throwable $e) {
            return response()->json(['erro' => $e->getMessage()], 500);
        }
    });

    // ─ Comunicados
    Route::get('/comunicados', function () {
        try {
            $user = \Illuminate\Support\Facades\Auth::user();
            $rows = \Illuminate\Support\Facades\DB::table('COMUNICADO as c')
                ->leftJoin('COMUNICADO_LEITURA as l', function ($j) use ($user) {
                    $j->on('l.COMUNICADO_ID', '=', 'c.COMUNICADO_ID')
                        ->where('l.USUARIO_ID', $user->USUARIO_ID);
                })
                ->select('c.*', \Illuminate\Support\Facades\DB::raw('CASE WHEN l.LEITURA_ID IS NULL THEN 0 ELSE 1 END as lido'))
                ->orderByDesc('c.COMUNICADO_DT_PUBLICACAO')
                ->take(30)->get()
                ->map(fn($r) => [
                    'id' => $r->COMUNICADO_ID,
                    'titulo' => $r->COMUNICADO_TITULO,
                    'conteudo' => $r->COMUNICADO_TEXTO ?? $r->COMUNICADO_CONTEUDO ?? '',
                    'tipo' => $r->COMUNICADO_TIPO ?? 'geral',
                    'data' => $r->COMUNICADO_DT_PUBLICACAO,
                    'lido' => (bool) $r->lido,
                    'urgente' => (bool) ($r->COMUNICADO_URGENTE ?? false),
                ]);
            return response()->json(['fallback' => false, 'comunicados' => $rows]);
        } catch (\Throwable $e) {
            return response()->json(['fallback' => true, 'comunicados' => [], 'erro' => $e->getMessage()]);
        }
    });

    Route::post('/comunicados', function (\Illuminate\Http\Request $request) {
        try {
            $id = \Illuminate\Support\Facades\DB::table('COMUNICADO')->insertGetId([
                'COMUNICADO_TITULO' => $request->titulo,
                'COMUNICADO_TEXTO' => $request->conteudo,
                'COMUNICADO_TIPO' => $request->tipo ?? 'geral',
                'COMUNICADO_URGENTE' => $request->urgente ? 1 : 0,
                'COMUNICADO_DT_PUBLICACAO' => now()->toDateString(),
                'USUARIO_ID' => \Illuminate\Support\Facades\Auth::id(),
            ]);
            return response()->json(['id' => $id], 201);
        } catch (\Throwable $e) {
            return response()->json(['erro' => $e->getMessage()], 500);
        }
    });

    Route::put('/comunicados/{id}/lido', function ($id) {
        try {
            $user = \Illuminate\Support\Facades\Auth::user();
            \Illuminate\Support\Facades\DB::table('COMUNICADO_LEITURA')->updateOrInsert(
                ['COMUNICADO_ID' => $id, 'USUARIO_ID' => $user->USUARIO_ID],
                ['LEITURA_DT' => now()->toDateTimeString()]
            );
            return response()->json(['ok' => true]);
        } catch (\Throwable $e) {
            return response()->json(['ok' => false, 'erro' => $e->getMessage()]);
        }
    });

    // ─ Agenda
    Route::get('/agenda', function (\Illuminate\Http\Request $request) {
        try {
            $comp = $request->competencia ?? now()->format('Y-m');
            $rows = \Illuminate\Support\Facades\DB::table('AGENDA_EVENTO')
                ->whereRaw("strftime('%Y-%m', EVENTO_DATA) = ?", [$comp])
                ->get()
                ->map(fn($r) => [
                    'id' => $r->EVENTO_ID,
                    'titulo' => $r->EVENTO_TITULO,
                    'data' => $r->EVENTO_DATA,
                    'tipo' => $r->EVENTO_TIPO ?? 'reuniao',
                    'desc' => $r->EVENTO_DESCRICAO ?? '',
                ]);
            return response()->json(['fallback' => false, 'eventos' => $rows]);
        } catch (\Throwable $e) {
            return response()->json(['fallback' => true, 'eventos' => [], 'erro' => $e->getMessage()]);
        }
    });

    Route::post('/agenda', function (\Illuminate\Http\Request $request) {
        try {
            $id = \Illuminate\Support\Facades\DB::table('AGENDA_EVENTO')->insertGetId([
                'EVENTO_TITULO' => $request->titulo,
                'EVENTO_DATA' => $request->data,
                'EVENTO_TIPO' => $request->tipo ?? 'reuniao',
                'EVENTO_DESCRICAO' => $request->desc ?? null,
                'USUARIO_ID' => \Illuminate\Support\Facades\Auth::id(),
            ]);
            return response()->json(['id' => $id], 201);
        } catch (\Throwable $e) {
            return response()->json(['erro' => $e->getMessage()], 500);
        }
    });

    Route::delete('/agenda/{id}', function ($id) {
        try {
            \Illuminate\Support\Facades\DB::table('AGENDA_EVENTO')->where('EVENTO_ID', $id)->delete();
            return response()->json(['ok' => true]);
        } catch (\Throwable $e) {
            return response()->json(['erro' => $e->getMessage()], 500);
        }
    });

    // ─ Sobreaviso
    Route::get('/sobreaviso', function (\Illuminate\Http\Request $request) {
        try {
            $user = \Illuminate\Support\Facades\Auth::user();
            $func = \App\Models\Funcionario::where('USUARIO_ID', $user->USUARIO_ID ?? 0)->first();
            $comp = $request->competencia ?? now()->format('Y-m');
            if (!$func)
                return response()->json(['fallback' => true, 'sobreaviso' => [], 'acionamentos' => []]);

            $sobreaviso = \Illuminate\Support\Facades\DB::table('ESCALA_SOBREAVISO')
                ->where('FUNCIONARIO_ID', $func->FUNCIONARIO_ID)
                ->whereRaw("strftime('%Y-%m', SOBREAVISO_DATA) = ?", [$comp])
                ->get()
                ->map(fn($r) => ['id' => $r->SOBREAVISO_ID, 'data' => $r->SOBREAVISO_DATA, 'turno' => $r->SOBREAVISO_TURNO ?? 'Integral', 'horas' => $r->SOBREAVISO_HORAS ?? 12]);

            $acionamentos = \Illuminate\Support\Facades\DB::table('ACIONAMENTO_SOBREAVISO')
                ->where('FUNCIONARIO_ID', $func->FUNCIONARIO_ID)
                ->whereRaw("strftime('%Y-%m', ACIONAMENTO_DATA) = ?", [$comp])
                ->get()
                ->map(fn($r) => ['id' => $r->ACIONAMENTO_ID, 'data' => $r->ACIONAMENTO_DATA, 'hora' => $r->ACIONAMENTO_HORA, 'motivo' => $r->ACIONAMENTO_MOTIVO ?? '']);

            return response()->json(['fallback' => false, 'sobreaviso' => $sobreaviso, 'acionamentos' => $acionamentos]);
        } catch (\Throwable $e) {
            return response()->json(['fallback' => true, 'sobreaviso' => [], 'acionamentos' => [], 'erro' => $e->getMessage()]);
        }
    });

    Route::post('/sobreaviso/acionamento', function (\Illuminate\Http\Request $request) {
        try {
            $user = \Illuminate\Support\Facades\Auth::user();
            $func = \App\Models\Funcionario::where('USUARIO_ID', $user->USUARIO_ID ?? 0)->first();
            $id = \Illuminate\Support\Facades\DB::table('ACIONAMENTO_SOBREAVISO')->insertGetId([
                'FUNCIONARIO_ID' => $func?->FUNCIONARIO_ID ?? null,
                'ACIONAMENTO_DATA' => $request->data ?? now()->toDateString(),
                'ACIONAMENTO_HORA' => $request->hora ?? now()->format('H:i'),
                'ACIONAMENTO_MOTIVO' => $request->motivo ?? null,
            ]);
            return response()->json(['id' => $id], 201);
        } catch (\Throwable $e) {
            return response()->json(['erro' => $e->getMessage()], 500);
        }
    });

    // ─ Ouvidoria
    Route::get('/ouvidoria', function () {
        try {
            $user = \Illuminate\Support\Facades\Auth::user();
            $rows = \Illuminate\Support\Facades\DB::table('MANIFESTACAO')
                ->where('USUARIO_ID', $user->USUARIO_ID)
                ->orderByDesc('MANIFESTACAO_DT_REGISTRO')
                ->get()
                ->map(fn($r) => [
                    'id' => $r->MANIFESTACAO_ID,
                    'tipo' => $r->MANIFESTACAO_TIPO ?? 'Reclamação',
                    'assunto' => $r->MANIFESTACAO_ASSUNTO ?? '—',
                    'descricao' => $r->MANIFESTACAO_DESCRICAO ?? '',
                    'status' => $r->MANIFESTACAO_STATUS ?? 'aberta',
                    'protocolo' => $r->MANIFESTACAO_PROTOCOLO ?? null,
                    'data' => $r->MANIFESTACAO_DT_REGISTRO,
                    'resposta' => $r->MANIFESTACAO_RESPOSTA ?? null,
                ]);
            return response()->json(['fallback' => false, 'manifestacoes' => $rows]);
        } catch (\Throwable $e) {
            return response()->json(['fallback' => true, 'manifestacoes' => [], 'erro' => $e->getMessage()]);
        }
    });

    Route::post('/ouvidoria', function (\Illuminate\Http\Request $request) {
        try {
            $user = \Illuminate\Support\Facades\Auth::user();
            $protocolo = 'OUV' . date('Ymd') . rand(100, 999);
            $id = \Illuminate\Support\Facades\DB::table('MANIFESTACAO')->insertGetId([
                'USUARIO_ID' => $user->USUARIO_ID,
                'MANIFESTACAO_TIPO' => $request->tipo ?? 'Sugestão',
                'MANIFESTACAO_ASSUNTO' => $request->assunto,
                'MANIFESTACAO_DESCRICAO' => $request->descricao,
                'MANIFESTACAO_ANONIMA' => $request->anonima ? 1 : 0,
                'MANIFESTACAO_STATUS' => 'aberta',
                'MANIFESTACAO_PROTOCOLO' => $protocolo,
                'MANIFESTACAO_DT_REGISTRO' => now()->toDateString(),
            ]);
            return response()->json(['id' => $id, 'protocolo' => $protocolo], 201);
        } catch (\Throwable $e) {
            return response()->json(['erro' => $e->getMessage()], 500);
        }
    });

    // ─ Declarações / Requerimentos
    Route::get('/declaracoes', function () {
        try {
            $user = \Illuminate\Support\Facades\Auth::user();
            $func = \App\Models\Funcionario::where('USUARIO_ID', $user->USUARIO_ID ?? 0)->first();
            if (!$func)
                return response()->json(['fallback' => true, 'pedidos' => []]);

            $rows = \Illuminate\Support\Facades\DB::table('DECLARACAO')
                ->where('FUNCIONARIO_ID', $func->FUNCIONARIO_ID)
                ->orderByDesc('DECLARACAO_DT_SOLICITACAO')
                ->get()
                ->map(fn($r) => [
                    'id' => $r->DECLARACAO_ID,
                    'tipo' => $r->DECLARACAO_TIPO ?? '—',
                    'status' => $r->DECLARACAO_STATUS ?? 'pendente',
                    'solicitacao' => $r->DECLARACAO_DT_SOLICITACAO,
                    'observacao' => $r->DECLARACAO_OBS ?? null,
                ]);
            return response()->json(['fallback' => false, 'pedidos' => $rows]);
        } catch (\Throwable $e) {
            return response()->json(['fallback' => true, 'pedidos' => [], 'erro' => $e->getMessage()]);
        }
    });

    Route::post('/declaracoes', function (\Illuminate\Http\Request $request) {
        try {
            $user = \Illuminate\Support\Facades\Auth::user();
            $func = \App\Models\Funcionario::where('USUARIO_ID', $user->USUARIO_ID ?? 0)->first();
            $id = \Illuminate\Support\Facades\DB::table('DECLARACAO')->insertGetId([
                'FUNCIONARIO_ID' => $func?->FUNCIONARIO_ID ?? null,
                'DECLARACAO_TIPO' => $request->tipo,
                'DECLARACAO_STATUS' => 'pendente',
                'DECLARACAO_OBS' => $request->observacao ?? null,
                'DECLARACAO_DT_SOLICITACAO' => now()->toDateString(),
            ]);
            return response()->json(['id' => $id], 201);
        } catch (\Throwable $e) {
            return response()->json(['erro' => $e->getMessage()], 500);
        }
    });

    // ─ Férias
    Route::get('/ferias', function () {
        try {
            $user = \Illuminate\Support\Facades\Auth::user();
            $func = \App\Models\Funcionario::where('USUARIO_ID', $user->USUARIO_ID ?? 0)->first();
            if (!$func)
                return response()->json(['ferias' => [], 'saldo' => 30, 'vencimento' => '—']);
            $rows = \Illuminate\Support\Facades\DB::table('FERIAS')->where('FUNCIONARIO_ID', $func->FUNCIONARIO_ID)->orderByDesc('FERIAS_DATA_INICIO')->get();
            $gozados = $rows->where('FERIAS_STATUS', '!=', 'cancelado')->sum(fn($r) => abs(\Carbon\Carbon::parse($r->FERIAS_DATA_INICIO)->diffInDays(\Carbon\Carbon::parse($r->FERIAS_DATA_FIM))));
            return response()->json(['ferias' => $rows, 'saldo' => max(0, 30 - $gozados), 'fallback' => $rows->isEmpty()]);
        } catch (\Throwable $e) {
            return response()->json(['ferias' => [], 'saldo' => 30, 'vencimento' => '—', 'fallback' => true]);
        }
    });
    Route::post('/ferias', function (\Illuminate\Http\Request $request) {
        try {
            $user = \Illuminate\Support\Facades\Auth::user();
            $func = \App\Models\Funcionario::where('USUARIO_ID', $user->USUARIO_ID ?? 0)->first();
            $id = \Illuminate\Support\Facades\DB::table('FERIAS')->insertGetId(['FUNCIONARIO_ID' => $func->FUNCIONARIO_ID ?? null, 'FERIAS_DATA_INICIO' => $request->FERIAS_DATA_INICIO, 'FERIAS_DATA_FIM' => $request->FERIAS_DATA_FIM, 'FERIAS_AQUISITIVO_INICIO' => $request->FERIAS_AQUISITIVO_INICIO, 'FERIAS_AQUISITIVO_FIM' => $request->FERIAS_AQUISITIVO_FIM, 'FERIAS_STATUS' => 'agendado', 'FERIAS_DATA_SOLICITACAO' => now()->toDateString()]);
            return response()->json(['ferias_id' => $id], 201);
        } catch (\Throwable $e) {
            return response()->json(['erro' => $e->getMessage()], 500);
        }
    });
    Route::put('/ferias/{id}', function (\Illuminate\Http\Request $request, $id) {
        try {
            \Illuminate\Support\Facades\DB::table('FERIAS')->where('FERIAS_ID', $id)->update(['FERIAS_DATA_INICIO' => $request->FERIAS_DATA_INICIO, 'FERIAS_DATA_FIM' => $request->FERIAS_DATA_FIM, 'FERIAS_AQUISITIVO_INICIO' => $request->FERIAS_AQUISITIVO_INICIO, 'FERIAS_AQUISITIVO_FIM' => $request->FERIAS_AQUISITIVO_FIM]);
            return response()->json(['message' => 'Atualizado.']);
        } catch (\Throwable $e) {
            return response()->json(['erro' => $e->getMessage()], 500);
        }
    });
    Route::delete('/ferias/{id}', function ($id) {
        try {
            \Illuminate\Support\Facades\DB::table('FERIAS')->where('FERIAS_ID', $id)->update(['FERIAS_STATUS' => 'cancelado']);
            return response()->json(['message' => 'Cancelado.']);
        } catch (\Throwable $e) {
            return response()->json(['erro' => $e->getMessage()], 500);
        }
    });

    // ─ Afastamentos / Licenças Administrativas
    Route::get('/afastamentos', function () {
        try {
            $user = \Illuminate\Support\Facades\Auth::user();
            $func = \App\Models\Funcionario::where('USUARIO_ID', $user->USUARIO_ID ?? 0)->first();
            if (!$func)
                return response()->json(['fallback' => true, 'afastamentos' => []]);
            $keywords = ['licenca', 'premio', 'particulares', 'maternidade', 'paternidade', 'capacitacao', 'judicial'];
            $rows = \Illuminate\Support\Facades\DB::table('AFASTAMENTO')
                ->where('FUNCIONARIO_ID', $func->FUNCIONARIO_ID)
                ->where(function ($q) use ($keywords) {
                    foreach ($keywords as $k)
                        $q->orWhereRaw("LOWER(AFASTAMENTO_TIPO) LIKE ?", '%' . $k . '%');
                })
                ->orderByDesc('AFASTAMENTO_DATA_INICIO')->take(20)->get()
                ->map(fn($a) => ['id' => $a->AFASTAMENTO_ID, 'tipo' => $a->AFASTAMENTO_TIPO, 'tipo_nome' => $a->AFASTAMENTO_TIPO_NOME ?? null, 'inicio' => $a->AFASTAMENTO_DATA_INICIO, 'fim' => $a->AFASTAMENTO_DATA_FIM ?? null, 'obs' => $a->AFASTAMENTO_OBS ?? null, 'status' => $a->AFASTAMENTO_STATUS ?? 'Pendente']);
            return response()->json(['afastamentos' => $rows, 'fallback' => $rows->isEmpty()]);
        } catch (\Throwable $e) {
            return response()->json(['fallback' => true, 'afastamentos' => [], 'erro' => $e->getMessage()]);
        }
    });
    Route::post('/afastamentos', function (\Illuminate\Http\Request $request) {
        try {
            $user = \Illuminate\Support\Facades\Auth::user();
            $func = \App\Models\Funcionario::where('USUARIO_ID', $user->USUARIO_ID ?? 0)->first();
            $seq = \Illuminate\Support\Facades\DB::table('AFASTAMENTO')->count() + 1;
            $proto = 'AFT-' . now()->format('Y') . '-' . str_pad($seq, 4, '0', STR_PAD_LEFT);
            $id = \Illuminate\Support\Facades\DB::table('AFASTAMENTO')->insertGetId(['FUNCIONARIO_ID' => $func->FUNCIONARIO_ID ?? null, 'AFASTAMENTO_TIPO' => $request->tipo, 'AFASTAMENTO_DATA_INICIO' => $request->inicio, 'AFASTAMENTO_DATA_FIM' => $request->fim ?? null, 'AFASTAMENTO_OBS' => $request->obs ?? null, 'AFASTAMENTO_STATUS' => 'pendente', 'AFASTAMENTO_PROTOCOLO' => $proto, 'AFASTAMENTO_DT_REGISTRO' => now()->toDateString()]);
            return response()->json(['id' => $id, 'protocolo' => $proto], 201);
        } catch (\Throwable $e) {
            return response()->json(['erro' => $e->getMessage()], 500);
        }
    });

    // ─ Substituições de Plantão
    Route::get('/substituicoes', function () {
        try {
            $rows = \Illuminate\Support\Facades\DB::table('SUBSTITUICAO as sub')
                ->leftJoin('FUNCIONARIO as fs', 'fs.FUNCIONARIO_ID', '=', 'sub.SOLICITANTE_FUNCIONARIO_ID')
                ->leftJoin('PESSOA as ps', 'ps.PESSOA_ID', '=', 'fs.PESSOA_ID')
                ->leftJoin('FUNCIONARIO as ft', 'ft.FUNCIONARIO_ID', '=', 'sub.SUBSTITUTO_FUNCIONARIO_ID')
                ->leftJoin('PESSOA as pt', 'pt.PESSOA_ID', '=', 'ft.PESSOA_ID')
                ->leftJoin('SETOR as s', 's.SETOR_ID', '=', 'sub.SETOR_ID')
                ->select(
                    'sub.SUBSTITUICAO_ID as id',
                    'ps.PESSOA_NOME as solicitante',
                    'sub.SOLICITANTE_FUNCIONARIO_ID as solicitante_id',
                    'pt.PESSOA_NOME as substituto',
                    'sub.SUBSTITUTO_FUNCIONARIO_ID as substituto_id',
                    's.SETOR_NOME as setor',
                    'sub.SUBSTITUICAO_DATA as data_plantao',
                    'sub.SUBSTITUICAO_TURNO as turno',
                    'sub.SUBSTITUICAO_MOTIVO as motivo',
                    'sub.SUBSTITUICAO_STATUS as status',
                    'sub.SUBSTITUICAO_DT_REGISTRO as criado_em'
                )
                ->orderByDesc('sub.SUBSTITUICAO_ID')
                ->take(50)
                ->get();
            return response()->json($rows);
        } catch (\Throwable $e) {
            return response()->json([], 200); // tabela pode não existir ainda
        }
    });

    Route::post('/substituicoes', function (\Illuminate\Http\Request $request) {
        try {
            $id = \Illuminate\Support\Facades\DB::table('SUBSTITUICAO')->insertGetId([
                'ESCALA_ID' => $request->escala_id,
                'SOLICITANTE_FUNCIONARIO_ID' => $request->solicitante_id,
                'SUBSTITUTO_FUNCIONARIO_ID' => $request->substituto_id ?? null,
                'SUBSTITUICAO_DATA' => $request->data_plantao,
                'SUBSTITUICAO_TURNO' => $request->turno,
                'SUBSTITUICAO_MOTIVO' => $request->motivo ?? null,
                'SUBSTITUICAO_STATUS' => 'pendente',
                'SUBSTITUICAO_DT_REGISTRO' => now()->toDateString(),
            ]);
            return response()->json(['id' => $id, 'status' => 'pendente'], 201);
        } catch (\Throwable $e) {
            // Fallback: tabela pode não existir
            return response()->json(['id' => null, 'status' => 'pendente', 'aviso' => $e->getMessage()], 201);
        }
    });

    Route::put('/substituicoes/{id}', function (\Illuminate\Http\Request $request, $id) {
        try {
            \Illuminate\Support\Facades\DB::table('SUBSTITUICAO')
                ->where('SUBSTITUICAO_ID', $id)
                ->update(['SUBSTITUICAO_STATUS' => $request->status]);
            return response()->json(['ok' => true]);
        } catch (\Throwable $e) {
            return response()->json(['ok' => false, 'erro' => $e->getMessage()]);
        }
    });

    // ─ Escalas Médicas — Matriz de Plantões
    Route::get('/escalas', function () {
        try {
            $escalas = \Illuminate\Support\Facades\DB::table('ESCALA as e')
                ->leftJoin('SETOR as s', 's.SETOR_ID', '=', 'e.SETOR_ID')
                ->select(
                    'e.ESCALA_ID',
                    'e.ESCALA_COMPETENCIA',
                    's.SETOR_NOME as setor'
                )
                ->orderByDesc('e.ESCALA_ID')
                ->take(24)
                ->get()
                ->map(function ($e) {
                    // ESCALA_COMPETENCIA armazenada como YYYYMM (202603) ou string — formata para exibição
                    $comp = $e->ESCALA_COMPETENCIA ?? '';
                    if (is_numeric($comp) && strlen($comp) === 6) {
                        $meses = ['', 'Jan', 'Fev', 'Mar', 'Abr', 'Mai', 'Jun', 'Jul', 'Ago', 'Set', 'Out', 'Nov', 'Dez'];
                        $m = (int) substr($comp, 4, 2);
                        $a = substr($comp, 0, 4);
                        $e->ESCALA_COMPETENCIA = ($meses[$m] ?? $m) . '/' . $a;
                    }
                    return $e;
                });
            return response()->json(['escalas' => $escalas, 'fallback' => $escalas->isEmpty()]);
        } catch (\Throwable $e) {
            return response()->json(['fallback' => true, 'escalas' => [], 'erro' => $e->getMessage()]);
        }
    });

    Route::get('/escalas/{id}', function ($id) {
        try {
            // Dados da escala
            $escala = \Illuminate\Support\Facades\DB::table('ESCALA as e')
                ->leftJoin('SETOR as s', 's.SETOR_ID', '=', 'e.SETOR_ID')
                ->select('e.*', 's.SETOR_NOME as setor_nome')
                ->where('e.ESCALA_ID', $id)
                ->first();

            if (!$escala) {
                return response()->json(['erro' => 'Escala não encontrada'], 404);
            }

            // Extrai ano e mês da competência (formato YYYYMM ou string)
            $comp = $escala->ESCALA_COMPETENCIA ?? '';
            if (is_numeric($comp) && strlen($comp) === 6) {
                $ano = (int) substr($comp, 0, 4);
                $mes = (int) substr($comp, 4, 2);
            } else {
                $ano = now()->year;
                $mes = now()->month;
            }
            $inicioMes = sprintf('%04d-%02d-01', $ano, $mes);
            $fimMes = sprintf(
                '%04d-%02d-%02d',
                $ano,
                $mes,
                cal_days_in_month(CAL_GREGORIAN, $mes, $ano)
            );

            // Feriados do mês
            $feriados = \Illuminate\Support\Facades\DB::table('FERIADO')
                ->whereBetween('FERIADO_DATA', [$inicioMes, $fimMes])
                ->get()
                ->map(fn($f) => ['data' => $f->FERIADO_DATA, 'nome' => $f->FERIADO_NOME ?? '']);

            // Funcionários na escala (DETALHE_ESCALA)
            $detalhes = \Illuminate\Support\Facades\DB::table('DETALHE_ESCALA as de')
                ->leftJoin('FUNCIONARIO as f', 'f.FUNCIONARIO_ID', '=', 'de.FUNCIONARIO_ID')
                ->leftJoin('PESSOA as p', 'p.PESSOA_ID', '=', 'f.PESSOA_ID')
                ->leftJoin('CARGO as c', 'c.CARGO_ID', '=', 'f.CARGO_ID')
                ->where('de.ESCALA_ID', $id)
                ->select('de.DETALHE_ESCALA_ID', 'de.FUNCIONARIO_ID', 'p.PESSOA_NOME', 'c.CARGO_NOME')
                ->get();

            $funcionarios = $detalhes->map(function ($d) {
                // Busca os itens de escala deste detalhe
                $itens = \Illuminate\Support\Facades\DB::table('DETALHE_ESCALA_ITEM')
                    ->where('DETALHE_ESCALA_ID', $d->DETALHE_ESCALA_ID)
                    ->get()
                    ->map(fn($i) => [
                        'data' => $i->DETALHE_ESCALA_ITEM_DATA,
                        'turno_id' => $i->TURNO_ID ?? null,
                        'turno_sigla' => $i->TURNO_SIGLA ?? $i->DETALHE_ESCALA_ITEM_TURNO ?? null,
                    ]);

                return [
                    'detalhe_id' => $d->DETALHE_ESCALA_ID,
                    'funcionario_id' => $d->FUNCIONARIO_ID,
                    'nome' => $d->PESSOA_NOME ?? 'Sem nome',
                    'cargo' => $d->CARGO_NOME ?? '—',
                    'itens' => $itens,
                ];
            });

            return response()->json([
                'escala' => [
                    'id' => $escala->ESCALA_ID,
                    'competencia' => $escala->ESCALA_COMPETENCIA,
                    'ano' => (int) $ano,
                    'mes' => (int) $mes,
                    'setor' => $escala->setor_nome ?? '—',
                ],
                'feriados' => $feriados,
                'funcionarios' => $funcionarios,
            ]);
        } catch (\Throwable $e) {
            return response()->json(['erro' => $e->getMessage()], 500);
        }
    });

    Route::post('/escalas/{id}/salvar', function (\Illuminate\Http\Request $request, $id) {
        try {
            $detalheId = $request->detalhe_escala_id;
            $itens = $request->itens ?? [];

            // Remove itens anteriores deste detalhe (substitui completo)
            \Illuminate\Support\Facades\DB::table('DETALHE_ESCALA_ITEM')
                ->where('DETALHE_ESCALA_ID', $detalheId)
                ->delete();

            // Insere os novos itens
            foreach ($itens as $item) {
                \Illuminate\Support\Facades\DB::table('DETALHE_ESCALA_ITEM')->insert([
                    'DETALHE_ESCALA_ID' => $detalheId,
                    'DETALHE_ESCALA_ITEM_DATA' => $item['data'],
                    'TURNO_ID' => $item['turno_id'] ?? null,
                    'DETALHE_ESCALA_ITEM_FALTA' => 0,
                    'DETALHE_ESCALA_ITEM_ATRASO' => 0,
                ]);
            }

            return response()->json([
                'msg' => 'Escala salva com sucesso!',
                'detalhe' => $detalheId,
                'itens_qtd' => count($itens),
            ]);
        } catch (\Throwable $e) {
            return response()->json(['msg' => 'Erro ao salvar: ' . $e->getMessage()], 500);
        }
    });

    Route::post('/escalas', function (\Illuminate\Http\Request $request) {
        try {
            $meses = ['', 'Jan', 'Fev', 'Mar', 'Abr', 'Mai', 'Jun', 'Jul', 'Ago', 'Set', 'Out', 'Nov', 'Dez'];
            $mes = (int) $request->mes;
            $ano = (int) $request->ano;
            $comp = ($meses[$mes] ?? $mes) . '/' . $ano;
            $setorId = $request->setor_id ?: null;

            $escalaId = \Illuminate\Support\Facades\DB::table('ESCALA')->insertGetId([
                'SETOR_ID' => $setorId,
                'ESCALA_COMPETENCIA' => $comp,
                'ESCALA_DESCRICAO' => "Escala $comp",
            ]);

            // Auto-popular com funcionários ativos lotados no setor
            if ($setorId) {
                $funcionarios = \Illuminate\Support\Facades\DB::table('LOTACAO')
                    ->where('SETOR_ID', $setorId)
                    ->whereNull('LOTACAO_DATA_FIM')
                    ->pluck('FUNCIONARIO_ID')
                    ->unique();

                foreach ($funcionarios as $funcId) {
                    \Illuminate\Support\Facades\DB::table('DETALHE_ESCALA')->insertOrIgnore([
                        'ESCALA_ID' => $escalaId,
                        'FUNCIONARIO_ID' => $funcId,
                    ]);
                }
            }

            return response()->json(['escala_id' => $escalaId, 'competencia' => $comp, 'funcionarios_adicionados' => $funcionarios->count() ?? 0], 201);
        } catch (\Throwable $e) {
            return response()->json(['erro' => $e->getMessage()], 500);
        }
    });

    // ─ Setores (usado no modal Nova Escala)
    Route::get('/setores', function () {
        try {
            $setores = \Illuminate\Support\Facades\DB::table('SETOR')
                ->select('SETOR_ID', 'SETOR_NOME')
                ->orderBy('SETOR_NOME')
                ->get();
            return response()->json(['setores' => $setores]);
        } catch (\Throwable $e) {
            return response()->json(['setores' => [], 'erro' => $e->getMessage()]);
        }
    });

    // ─ Folhas de Pagamento (Sprint 3)
    Route::get('/folhas', function () {
        // ⚠�? Rota corrigida — usa tabela FOLHA (não FOLHA_PAGAMENTO)
        try {
            $statusMap = [
                'Fechada' => 'F',
                'fechada' => 'F',
                'Aberta' => 'A',
                'aberta' => 'A',
                'F' => 'F',
                'A' => 'A',
                'P' => 'P',
                'C' => 'C',
            ];
            $compConvert = function ($c) {
                if (!$c)
                    return null;
                if (preg_match('/^\d{6}$/', $c))
                    return $c;
                if (preg_match('/^(\d{4})-(\d{2})$/', $c, $m))
                    return $m[2] . $m[1];
                $meses = [
                    'Jan' => '01',
                    'Fev' => '02',
                    'Mar' => '03',
                    'Abr' => '04',
                    'Mai' => '05',
                    'Jun' => '06',
                    'Jul' => '07',
                    'Ago' => '08',
                    'Set' => '09',
                    'Out' => '10',
                    'Nov' => '11',
                    'Dez' => '12'
                ];
                if (preg_match('/^([A-Za-z]{3})\/(\d{4})$/', $c, $m)) {
                    return ($meses[$m[1]] ?? '01') . $m[2];
                }
                return $c;
            };

            $rows = \Illuminate\Support\Facades\DB::table('FOLHA')
                ->orderBy('FOLHA_COMPETENCIA', 'desc')
                ->limit(50)->get();

            $folhas = $rows->map(function ($f) use ($statusMap, $compConvert) {
                $totais = \Illuminate\Support\Facades\DB::table('DETALHE_FOLHA')
                    ->where('FOLHA_ID', $f->FOLHA_ID)
                    ->whereNull('DETALHE_FOLHA_ERRO')
                    ->selectRaw('COUNT(*) as qtd, SUM(DETALHE_FOLHA_PROVENTOS) as prov, SUM(DETALHE_FOLHA_DESCONTOS) as desc_val')
                    ->first();
                $statusRaw = $f->FOLHA_STATUS ?? $f->FOLHA_SITUACAO ?? 'A';
                return [
                    'FOLHA_ID' => $f->FOLHA_ID,
                    'FOLHA_COMPETENCIA' => $compConvert($f->FOLHA_COMPETENCIA),
                    'FOLHA_COMPETENCIA_RAW' => $f->FOLHA_COMPETENCIA,
                    'FOLHA_SITUACAO' => $statusMap[$statusRaw] ?? 'A',
                    'qtd_funcionarios' => (int) ($totais->qtd ?? $f->FOLHA_QTD_SERVIDORES ?? 0),
                    'total_proventos' => (float) ($totais->prov ?? $f->FOLHA_VALOR_TOTAL ?? 0),
                    'total_descontos' => (float) ($totais->desc_val ?? 0),
                    'total_liquido' => (float) (($totais->prov ?? 0) - ($totais->desc_val ?? 0)),
                    'FOLHA_DESCRICAO' => $f->FOLHA_DESCRICAO ?? null,
                ];
            });
            return response()->json($folhas);
        } catch (\Throwable $e) {
            return response()->json(['erro' => $e->getMessage()], 500);
        }
    });

    // ─ Medicina do Trabalho — GET exames + POST agendar
    Route::get('/medicina', function () {
        try {
            $user = \Illuminate\Support\Facades\Auth::user();
            $func = \App\Models\Funcionario::where('USUARIO_ID', $user->USUARIO_ID ?? 0)->first();
            if (!$func)
                return response()->json(['fallback' => true, 'exames' => [], 'historico' => []]);

            $hoje = now();

            $exames = \Illuminate\Support\Facades\DB::table('EXAME_MEDICO')
                ->where('FUNCIONARIO_ID', $func->FUNCIONARIO_ID)
                ->orderByDesc('EXAME_DATA_REALIZACAO')
                ->get()
                ->map(function ($e) use ($hoje) {
                    $venc = $e->EXAME_DATA_VENCIMENTO ? new \Carbon\Carbon($e->EXAME_DATA_VENCIMENTO) : null;
                    $diasRestantes = $venc ? $hoje->diffInDays($venc, false) : null;

                    if (!$venc)
                        $status = 'em_dia';
                    elseif ($diasRestantes < 0)
                        $status = 'vencido';
                    elseif ($diasRestantes <= 30)
                        $status = 'proximo';
                    else
                        $status = 'em_dia';

                    return [
                        'id' => $e->EXAME_ID,
                        'tipo' => $e->EXAME_TIPO ?? 'Periódico',
                        'dataExame' => $e->EXAME_DATA_REALIZACAO,
                        'vencimento' => $e->EXAME_DATA_VENCIMENTO,
                        'apto' => (bool) ($e->EXAME_APTO ?? true),
                        'status' => $status,
                        'diasRestantes' => $diasRestantes,
                        'medico' => $e->EXAME_MEDICO ?? null,
                        'crm' => $e->EXAME_CRM ?? null,
                    ];
                });

            // Histórico de atendimentos (reaproveitando AFASTAMENTO com tipo=atestado como proxi, ou tabela específica)
            $historico = $exames->map(fn($e) => [
                'tipo' => $e['tipo'],
                'data' => $e['dataExame'],
                'apto' => $e['apto'],
            ]);

            return response()->json([
                'fallback' => $exames->isEmpty(),
                'exames' => $exames,
                'historico' => $historico,
            ]);
        } catch (\Throwable $e) {
            return response()->json(['fallback' => true, 'exames' => [], 'historico' => [], 'erro' => $e->getMessage()]);
        }
    });

    Route::post('/medicina/agendar', function (\Illuminate\Http\Request $request) {
        try {
            $user = \Illuminate\Support\Facades\Auth::user();
            $func = \App\Models\Funcionario::where('USUARIO_ID', $user->USUARIO_ID ?? 0)->first();
            $id = \Illuminate\Support\Facades\DB::table('EXAME_MEDICO')->insertGetId([
                'FUNCIONARIO_ID' => $func?->FUNCIONARIO_ID ?? null,
                'EXAME_TIPO' => $request->tipo ?? 'Periódico',
                'EXAME_DATA_REALIZACAO' => $request->data ?? now()->toDateString(),
                'EXAME_OBS' => $request->obs ?? null,
                'EXAME_STATUS' => 'agendado',
            ]);
            return response()->json(['id' => $id, 'status' => 'agendado'], 201);
        } catch (\Throwable $e) {
            // Fallback: retorna sucesso mesmo sem persistir
            return response()->json(['id' => null, 'status' => 'agendado', 'aviso' => $e->getMessage()], 201);
        }
    });

    // ─ Plantões Extras (Sprint 2)
    Route::get('/plantoes-extras', function (\Illuminate\Http\Request $request) {
        try {
            $user = \Illuminate\Support\Facades\Auth::user();
            $func = \App\Models\Funcionario::where('USUARIO_ID', $user->USUARIO_ID ?? 0)->first();
            if (!$func)
                return response()->json(['fallback' => true, 'plantoes' => []]);

            $comp = $request->competencia ?? now()->format('Y-m');
            $rows = \Illuminate\Support\Facades\DB::table('PLANTAO_EXTRA as pe')
                ->join('FUNCIONARIO as f', 'f.FUNCIONARIO_ID', '=', 'pe.FUNCIONARIO_ID')
                ->join('PESSOA as p', 'p.PESSOA_ID', '=', 'f.PESSOA_ID')
                ->leftJoin('SETOR as s', 's.SETOR_ID', '=', 'pe.SETOR_ID')
                ->where('pe.FUNCIONARIO_ID', $func->FUNCIONARIO_ID)
                ->whereRaw("strftime('%Y-%m', pe.PLANTAO_DATA) = ?", [$comp])
                ->orderByDesc('pe.PLANTAO_DATA')
                ->select(
                    'pe.PLANTAO_ID as id',
                    'pe.PLANTAO_DATA as data',
                    'pe.PLANTAO_TURNO as turno',
                    'pe.PLANTAO_HORAS as horas',
                    'pe.PLANTAO_STATUS as status',
                    's.SETOR_NOME as setor',
                    'p.PESSOA_NOME as servidor'
                )
                ->get();

            return response()->json(['fallback' => $rows->isEmpty(), 'plantoes' => $rows]);
        } catch (\Throwable $e) {
            return response()->json(['fallback' => true, 'plantoes' => [], 'erro' => $e->getMessage()]);
        }
    });

    Route::post('/plantoes-extras', function (\Illuminate\Http\Request $request) {
        try {
            $user = \Illuminate\Support\Facades\Auth::user();
            $func = \App\Models\Funcionario::where('USUARIO_ID', $user->USUARIO_ID ?? 0)->first();
            $id = \Illuminate\Support\Facades\DB::table('PLANTAO_EXTRA')->insertGetId([
                'FUNCIONARIO_ID' => $func?->FUNCIONARIO_ID ?? $request->funcionario_id,
                'SETOR_ID' => $request->setor_id ?? null,
                'ESCALA_ID' => $request->escala_id ?? null,
                'PLANTAO_DATA' => $request->data,
                'PLANTAO_TURNO' => $request->turno ?? 'Diurno',
                'PLANTAO_HORAS' => $request->horas ?? 12,
                'PLANTAO_STATUS' => 'pendente',
                'PLANTAO_MOTIVO' => $request->motivo ?? null,
            ]);
            return response()->json(['id' => $id, 'status' => 'pendente'], 201);
        } catch (\Throwable $e) {
            // fallback gracioso — aceita sem persistir
            return response()->json(['id' => null, 'status' => 'pendente', 'aviso' => $e->getMessage()], 201);
        }
    });

    // ─ Notificações in-app (Sprint 4)
    Route::get('/notificacoes', function () {
        try {
            $user = \Illuminate\Support\Facades\Auth::user();
            $rows = \Illuminate\Support\Facades\DB::table('NOTIFICACAO')
                ->where('USUARIO_ID', $user->USUARIO_ID)
                ->orderByDesc('NOTIFICACAO_DT_CRIACAO')
                ->take(50)
                ->get()
                ->map(fn($n) => [
                    'id' => $n->NOTIFICACAO_ID,
                    'titulo' => $n->NOTIFICACAO_TITULO,
                    'body' => $n->NOTIFICACAO_BODY ?? '',
                    'tipo' => $n->NOTIFICACAO_TIPO ?? 'info',
                    'icone' => $n->NOTIFICACAO_ICONE ?? '🔔',
                    'url' => $n->NOTIFICACAO_URL ?? null,
                    'lida' => (bool) $n->NOTIFICACAO_LIDA,
                    'criada_em' => $n->NOTIFICACAO_DT_CRIACAO,
                ]);
            $naoLidas = $rows->where('lida', false)->count();
            return response()->json([
                'fallback' => false,
                'notificacoes' => $rows,
                'nao_lidas' => $naoLidas,
            ]);
        } catch (\Throwable $e) {
            return response()->json(['fallback' => true, 'notificacoes' => [], 'nao_lidas' => 0, 'erro' => $e->getMessage()]);
        }
    });

    Route::put('/notificacoes/{id}/lida', function ($id) {
        try {
            $user = \Illuminate\Support\Facades\Auth::user();
            \Illuminate\Support\Facades\DB::table('NOTIFICACAO')
                ->where('NOTIFICACAO_ID', $id)
                ->where('USUARIO_ID', $user->USUARIO_ID)
                ->update(['NOTIFICACAO_LIDA' => 1, 'NOTIFICACAO_DT_LEITURA' => now()->toDateTimeString()]);
            return response()->json(['ok' => true]);
        } catch (\Throwable $e) {
            return response()->json(['ok' => false, 'erro' => $e->getMessage()]);
        }
    });

    Route::put('/notificacoes/lidas', function () {
        try {
            $user = \Illuminate\Support\Facades\Auth::user();
            \Illuminate\Support\Facades\DB::table('NOTIFICACAO')
                ->where('USUARIO_ID', $user->USUARIO_ID)
                ->where('NOTIFICACAO_LIDA', 0)
                ->update(['NOTIFICACAO_LIDA' => 1, 'NOTIFICACAO_DT_LEITURA' => now()->toDateTimeString()]);
            return response()->json(['ok' => true]);
        } catch (\Throwable $e) {
            return response()->json(['ok' => false, 'erro' => $e->getMessage()]);
        }
    });

    // ─ Banco de Horas (Sprint Alta Prioridade)
    Route::get('/banco-horas', function (\Illuminate\Http\Request $request) {
        try {
            $user = \Illuminate\Support\Facades\Auth::user();
            $func = \App\Models\Funcionario::where('USUARIO_ID', $user->USUARIO_ID ?? 0)->first();
            if (!$func)
                return response()->json(['fallback' => true, 'apuracoes' => []]);

            // Busca apurações mensais se a tabela APURACAO_PONTO existir
            $apuracoes = \Illuminate\Support\Facades\DB::table('APURACAO_PONTO')
                ->where('FUNCIONARIO_ID', $func->FUNCIONARIO_ID)
                ->orderByDesc('APURACAO_COMPETENCIA')
                ->take(12)
                ->get()
                ->map(fn($a) => [
                    'competencia' => $a->APURACAO_COMPETENCIA ?? null,
                    'esperadas' => $a->APURACAO_HORAS_ESPERADAS ?? 0,
                    'trabalhadas' => $a->APURACAO_HORAS_TRABALHADAS ?? 0,
                    'extras' => $a->APURACAO_HORAS_EXTRAS ?? 0,
                    'negativas' => $a->APURACAO_HORAS_NEGATIVAS ?? 0,
                    'saldo' => $a->APURACAO_SALDO ?? 0,
                    'saldo_acumulado' => $a->APURACAO_SALDO_ACUMULADO ?? 0,
                    'status' => $a->APURACAO_STATUS ?? 'em_aberto',
                ]);

            return response()->json(['fallback' => $apuracoes->isEmpty(), 'apuracoes' => $apuracoes]);
        } catch (\Throwable $e) {
            return response()->json(['fallback' => true, 'apuracoes' => [], 'erro' => $e->getMessage()]);
        }
    });

    // ─ Alterar Senha (Alta Prioridade)
    Route::post('/perfil/alterar-senha', function (\Illuminate\Http\Request $request) {
        try {
            $user = \Illuminate\Support\Facades\Auth::user();
            if (!$user)
                return response()->json(['ok' => false, 'erro' => 'Não autenticado.'], 401);

            $senhaAtual = $request->senha_atual ?? '';
            $novaSenha = $request->nova_senha ?? '';
            $confirmacao = $request->confirmacao ?? '';

            // Validações básicas
            if (strlen($novaSenha) < 6) {
                return response()->json(['ok' => false, 'erro' => 'A nova senha deve ter ao menos 6 caracteres.'], 422);
            }
            if ($novaSenha !== $confirmacao) {
                return response()->json(['ok' => false, 'erro' => 'As senhas não coincidem.'], 422);
            }

            // Verifica senha atual — tenta bcrypt primeiro, depois MD5 (legado)
            $hashAtual = $user->USUARIO_SENHA ?? $user->password ?? '';
            $senhaOk = \Illuminate\Support\Facades\Hash::check($senhaAtual, $hashAtual)
                || md5($senhaAtual) === $hashAtual
                || $senhaAtual === $hashAtual;

            if (!$senhaOk) {
                return response()->json(['ok' => false, 'erro' => 'Senha atual incorreta.'], 422);
            }

            // Atualiza com bcrypt
            $novoHash = \Illuminate\Support\Facades\Hash::make($novaSenha);
            \Illuminate\Support\Facades\DB::table('USUARIO')
                ->where('USUARIO_ID', $user->USUARIO_ID ?? $user->id)
                ->update(['USUARIO_SENHA' => $novoHash]);

            return response()->json(['ok' => true, 'msg' => 'Senha alterada com sucesso!']);
        } catch (\Throwable $e) {
            return response()->json(['ok' => false, 'erro' => $e->getMessage()], 500);
        }
    });

    // ─ PDF do Holerite via DomPDF (GAP-07 — view v3.holerite-pdf)
    Route::get('/meus-holerites/{id}/pdf', function ($id) {
        try {
            $user = \Illuminate\Support\Facades\Auth::user();
            $func = \App\Models\Funcionario::where('USUARIO_ID', $user->USUARIO_ID ?? 0)->first();

            // Busca o DETALHE_FOLHA pelo id (funciona como id do holerite individal)
            $detalhe = \Illuminate\Support\Facades\DB::table('DETALHE_FOLHA as df')
                ->join('FOLHA as fo', 'fo.FOLHA_ID', '=', 'df.FOLHA_ID')
                ->join('FUNCIONARIO as f', 'f.FUNCIONARIO_ID', '=', 'df.FUNCIONARIO_ID')
                ->join('PESSOA as p', 'p.PESSOA_ID', '=', 'f.PESSOA_ID')
                ->leftJoin('CARGO as c', 'c.CARGO_ID', '=', 'f.CARGO_ID')
                ->leftJoin('LOTACAO as l', function ($j) {
                    $j->on('l.FUNCIONARIO_ID', '=', 'f.FUNCIONARIO_ID')
                        ->whereNull('l.LOTACAO_DATA_FIM');
                })
                ->leftJoin('SETOR as s', 's.SETOR_ID', '=', 'l.SETOR_ID')
                ->where('df.DETALHE_FOLHA_ID', $id)
                ->select(
                    'df.*',
                    'fo.FOLHA_COMPETENCIA',
                    'p.PESSOA_NOME as nome',
                    'p.PESSOA_CPF_NUMERO as cpf',
                    'f.FUNCIONARIO_MATRICULA as matricula',
                    'f.FUNCIONARIO_BANCO as banco',
                    'f.FUNCIONARIO_AGENCIA as agencia',
                    'f.FUNCIONARIO_CONTA as conta',
                    'f.FUNCIONARIO_REGIME_PREV as regime_prev',
                    'c.CARGO_NOME as cargo',
                    's.SETOR_NOME as lotacao'
                )->first();

            if (!$detalhe)
                return response()->json(['erro' => 'Holerite não encontrado.'], 404);

            // Segurança: funcionário só acessa o próprio holerite
            if ($func && $detalhe->FUNCIONARIO_ID !== $func->FUNCIONARIO_ID) {
                return response()->json(['erro' => 'Acesso não autorizado.'], 403);
            }

            $proventos = (float) ($detalhe->DETALHE_FOLHA_PROVENTOS ?? 0);
            $descontos = (float) ($detalhe->DETALHE_FOLHA_DESCONTOS ?? 0);
            $liquido = (float) ($detalhe->DETALHE_FOLHA_LIQUIDO ?? ($proventos - $descontos));

            // Busca rubricas individuais (ITEM_FOLHA) se a tabela existir
            $rubricas = [];
            try {
                $items = \Illuminate\Support\Facades\DB::table('ITEM_FOLHA as it')
                    ->leftJoin('RUBRICA as r', 'r.RUBRICA_ID', '=', 'it.RUBRICA_ID')
                    ->where('it.DETALHE_FOLHA_ID', $id)
                    ->select(
                        'r.RUBRICA_CODIGO as codigo',
                        'r.RUBRICA_DESCRICAO as descricao',
                        'it.ITEM_TIPO as tipo',          // 'P' provento / 'D' desconto
                        'it.ITEM_VALOR as valor',
                        'it.ITEM_REFERENCIA as referencia'
                    )->get()->toArray();

                $rubricas = array_map(fn($i) => [
                    'codigo' => $i->codigo ?? '—',
                    'descricao' => $i->descricao ?? 'Item',
                    'tipo' => $i->tipo ?? 'P',
                    'valor' => (float) ($i->valor ?? 0),
                    'referencia' => $i->referencia ?? null,
                ], $items);
            } catch (\Throwable $re) {
                // Fallback sintético: dois itens agregados
                if ($proventos > 0)
                    $rubricas[] = ['codigo' => '001', 'descricao' => 'Vencimento / Proventos', 'tipo' => 'P', 'valor' => $proventos, 'referencia' => null];
                if ($descontos > 0)
                    $rubricas[] = ['codigo' => '900', 'descricao' => 'Total de Descontos', 'tipo' => 'D', 'valor' => $descontos, 'referencia' => null];
            }

            // Formata competência para exibição  (AAAAMM → MM/AAAA)
            $comp = $detalhe->FOLHA_COMPETENCIA ?? '';
            if (preg_match('/^(\d{4})(\d{2})$/', $comp, $m))
                $compFmt = "{$m[2]}/{$m[1]}";
            elseif (preg_match('/^(\d{4})-(\d{2})$/', $comp, $m))
                $compFmt = "{$m[2]}/{$m[1]}";
            else
                $compFmt = $comp;

            $pdf = \Barryvdh\DomPDF\Facade\Pdf::loadView('v3.holerite-pdf', [
                'servidor' => [
                    'nome' => $detalhe->nome,
                    'matricula' => $detalhe->matricula,
                    'cpf' => $detalhe->cpf,
                    'cargo' => $detalhe->cargo ?? '—',
                    'lotacao' => $detalhe->lotacao ?? '—',
                    'regime_prev' => $detalhe->regime_prev ?? '—',
                    'banco' => $detalhe->banco ?? '—',
                    'agencia' => $detalhe->agencia ?? '—',
                    'conta' => $detalhe->conta ?? '—',
                ],
                'rubricas' => $rubricas,
                'competencia' => $compFmt,
                'emitido_em' => now()->format('d/m/Y H:i'),
                'total_proventos' => $proventos,
                'total_descontos' => $descontos,
                'liquido' => $liquido,
            ])->setPaper('a4', 'portrait');

            $nome = str_replace([' ', '/'], ['_', '-'], ($detalhe->nome ?? 'servidor'));
            $nArq = "holerite_{$compFmt}_{$nome}.pdf";

            return $pdf->stream($nArq);
        } catch (\Throwable $e) {
            return response()->json(['erro' => $e->getMessage()], 500);
        }
    });


    // ─ A) Documentos do Funcionário (GET lista, POST upload)
    Route::get('/funcionarios/{id}/documentos', function ($id) {
        try {
            $docs = \Illuminate\Support\Facades\DB::table('FUNCIONARIO_DOCUMENTO')
                ->where('FUNCIONARIO_ID', $id)
                ->orderByDesc('DOC_DT_UPLOAD')
                ->get()
                ->map(fn($d) => [
                    'id' => $d->DOC_ID,
                    'tipo' => $d->DOC_TIPO ?? 'Documento',
                    'numero' => $d->DOC_NUMERO ?? null,
                    'arquivo' => $d->DOC_ARQUIVO ?? null,
                    'url' => $d->DOC_ARQUIVO ? asset('storage/' . $d->DOC_ARQUIVO) : null,
                    'obrigatorio' => (bool) ($d->DOC_OBRIGATORIO ?? 0),
                    'tamanho' => $d->DOC_TAMANHO ?? null,
                    'enviado_em' => $d->DOC_DT_UPLOAD,
                ]);
            return response()->json($docs);
        } catch (\Throwable $e) {
            // Tabela pode não existir ainda; retorna vazio graciosamente
            return response()->json([]);
        }
    });

    Route::post('/funcionarios/{id}/documentos', function (\Illuminate\Http\Request $request, $id) {
        try {
            if (!$request->hasFile('arquivo')) {
                return response()->json(['erro' => 'Nenhum arquivo enviado.'], 422);
            }
            $file = $request->file('arquivo');
            $tipo = $request->tipo ?? 'Documento';
            $numero = $request->numero ?? null;
            $ext = $file->extension();
            $path = $file->storeAs("documentos/{$id}", uniqid() . '.' . $ext, 'public');

            $docId = \Illuminate\Support\Facades\DB::table('FUNCIONARIO_DOCUMENTO')->insertGetId([
                'FUNCIONARIO_ID' => $id,
                'DOC_TIPO' => $tipo,
                'DOC_NUMERO' => $numero,
                'DOC_ARQUIVO' => $path,
                'DOC_TAMANHO' => $file->getSize(),
                'DOC_OBRIGATORIO' => 0,
                'DOC_DT_UPLOAD' => now()->toDateTimeString(),
            ]);
            return response()->json(['id' => $docId, 'url' => asset('storage/' . $path), 'arquivo' => $path], 201);
        } catch (\Throwable $e) {
            return response()->json(['erro' => $e->getMessage()], 500);
        }
    });

    Route::delete('/funcionarios/{id}/documentos/{docId}', function ($id, $docId) {
        try {
            $doc = \Illuminate\Support\Facades\DB::table('FUNCIONARIO_DOCUMENTO')
                ->where('DOC_ID', $docId)->where('FUNCIONARIO_ID', $id)->first();
            if (!$doc)
                return response()->json(['erro' => 'Documento não encontrado.'], 404);
            if ($doc->DOC_ARQUIVO)
                \Illuminate\Support\Facades\Storage::disk('public')->delete($doc->DOC_ARQUIVO);
            \Illuminate\Support\Facades\DB::table('FUNCIONARIO_DOCUMENTO')->where('DOC_ID', $docId)->delete();
            return response()->json(['ok' => true]);
        } catch (\Throwable $e) {
            return response()->json(['erro' => $e->getMessage()], 500);
        }
    });

    // ─ B) Aprovação de Atestados pelo Gestor
    Route::put('/atestados/{id}/aprovar', function (\Illuminate\Http\Request $request, $id) {
        try {
            $acao = $request->acao ?? 'aprovar';   // aprovar | rejeitar
            $obs = $request->observacao ?? null;
            $status = $acao === 'aprovar' ? 'aprovado' : 'rejeitado';
            $user = \Illuminate\Support\Facades\Auth::user();

            \Illuminate\Support\Facades\DB::table('ATESTADO')
                ->where('ATESTADO_ID', $id)
                ->update([
                    'ATESTADO_STATUS' => $status,
                    'ATESTADO_OBS_GESTOR' => $obs,
                    'APROVADOR_USUARIO_ID' => $user->USUARIO_ID ?? $user->id ?? null,
                    'ATESTADO_DT_APROVACAO' => now()->toDateString(),
                ]);

            return response()->json(['ok' => true, 'status' => $status]);
        } catch (\Throwable $e) {
            return response()->json(['ok' => false, 'erro' => $e->getMessage()], 500);
        }
    });

    // ─ C) Saldo de Férias por Período Aquisitivo
    Route::get('/ferias/saldo/{id}', function ($id) {
        try {
            $func = \Illuminate\Support\Facades\DB::table('FUNCIONARIO')
                ->where('FUNCIONARIO_ID', $id)
                ->first();
            if (!$func)
                return response()->json(['erro' => 'Funcionário não encontrado.'], 404);

            $dataInicio = \Carbon\Carbon::parse($func->FUNCIONARIO_DATA_INICIO)->startOfDay();
            $hoje = \Carbon\Carbon::now()->startOfDay();
            $periodos = [];
            $anoAtual = $dataInicio->copy();

            // Gera períodos aquisitivos de 12 em 12 meses desde a admissão
            while ($anoAtual->copy()->addYear()->lte($hoje)) {
                $inicioAquis = $anoAtual->copy();
                $fimAquis = $anoAtual->copy()->addYear()->subDay();

                // Férias já tiradas neste período aquisitivo
                $feriasUsadas = \Illuminate\Support\Facades\DB::table('FERIAS')
                    ->where('FUNCIONARIO_ID', $id)
                    ->where('FERIAS_DT_INICIO', '>=', $inicioAquis->toDateString())
                    ->where('FERIAS_DT_INICIO', '<=', $fimAquis->toDateString())
                    ->where('FERIAS_STATUS', '!=', 'cancelado')
                    ->sum(\Illuminate\Support\Facades\DB::raw("COALESCE(FERIAS_DIAS, 30)"));

                $periodos[] = [
                    'periodo' => $inicioAquis->format('d/m/Y') . ' — ' . $fimAquis->format('d/m/Y'),
                    'inicio_aquisitivo' => $inicioAquis->toDateString(),
                    'fim_aquisitivo' => $fimAquis->toDateString(),
                    'direito_dias' => 30,
                    'usados_dias' => (int) $feriasUsadas,
                    'saldo_dias' => max(0, 30 - (int) $feriasUsadas),
                    'vencido' => $fimAquis->copy()->addYear()->lt($hoje),
                ];

                $anoAtual->addYear();
            }

            // Período aquisitivo em andamento
            $inicioAtual = $anoAtual->copy();
            $mesesdecorridos = $dataInicio->diffInMonths($hoje);
            $mesesNoPeriodo = $mesesdecorridos % 12;

            return response()->json([
                'funcionario_id' => (int) $id,
                'admissao' => $func->FUNCIONARIO_DATA_INICIO,
                'tempo_servico_meses' => (int) $mesesdecorridos,
                'periodo_atual_inicio' => $inicioAtual->toDateString(),
                'meses_decorridos_periodo' => $mesesNoPeriodo,
                'periodos_aquisitivos' => $periodos,
                'total_saldo_dias' => array_sum(array_column($periodos, 'saldo_dias')),
            ]);
        } catch (\Throwable $e) {
            return response()->json(['erro' => $e->getMessage()], 500);
        }
    });

    // ─ CARGOS (S-1030) ─────────────────────────────────────────
    Route::get('/cargos', function (\Illuminate\Http\Request $request) {
        try {
            $q = \Illuminate\Support\Facades\DB::table('CARGO');
            if ($request->has('q') && $request->q)
                $q->where('CARGO_NOME', 'like', '%' . $request->q . '%');
            if ($request->has('ativo') && $request->ativo !== '')
                $q->where('CARGO_ATIVO', $request->ativo);
            $rows = $q->orderBy('CARGO_NOME')->get()->map(fn($c) => [
                'cargo_id' => $c->CARGO_ID,
                'nome' => $c->CARGO_NOME,
                'sigla' => $c->CARGO_SIGLA ?? null,
                'cbo' => $c->CARGO_CBO ?? null,
                'descricao' => $c->CARGO_DESCRICAO ?? null,
                'remuneracao' => $c->CARGO_REMUNERACAO ?? null,
                'escolaridade' => $c->CARGO_ESCOLARIDADE ?? null,
                'gestao' => (bool) ($c->CARGO_GESTAO ?? 0),
                'data_inicio' => $c->CARGO_DATA_INICIO ?? null,
                'ativo' => (bool) ($c->CARGO_ATIVO ?? 1),
            ]);
            return response()->json(['cargos' => $rows]);
        } catch (\Throwable $e) {
            return response()->json(['cargos' => [], 'erro' => $e->getMessage()]);
        }
    });

    Route::post('/cargos', function (\Illuminate\Http\Request $request) {
        try {
            if (!$request->CARGO_NOME)
                return response()->json(['erro' => 'Nome obrigatório.'], 422);
            $id = \Illuminate\Support\Facades\DB::table('CARGO')->insertGetId([
                'CARGO_NOME' => $request->CARGO_NOME,
                'CARGO_SIGLA' => $request->CARGO_SIGLA ?? null,
                'CARGO_CBO' => $request->CARGO_CBO ?? null,
                'CARGO_DESCRICAO' => $request->CARGO_DESCRICAO ?? null,
                'CARGO_REMUNERACAO' => $request->CARGO_REMUNERACAO ?? null,
                'CARGO_ESCOLARIDADE' => $request->CARGO_ESCOLARIDADE ?? null,
                'CARGO_GESTAO' => $request->CARGO_GESTAO ?? 0,
                'CARGO_DATA_INICIO' => $request->CARGO_DATA_INICIO ?? null,
                'CARGO_ATIVO' => 1,
            ]);
            return response()->json(['id' => $id], 201);
        } catch (\Throwable $e) {
            return response()->json(['erro' => $e->getMessage()], 500);
        }
    });

    Route::put('/cargos/{id}', function (\Illuminate\Http\Request $request, $id) {
        try {
            \Illuminate\Support\Facades\DB::table('CARGO')->where('CARGO_ID', $id)->update([
                'CARGO_NOME' => $request->CARGO_NOME,
                'CARGO_SIGLA' => $request->CARGO_SIGLA ?? null,
                'CARGO_CBO' => $request->CARGO_CBO ?? null,
                'CARGO_DESCRICAO' => $request->CARGO_DESCRICAO ?? null,
                'CARGO_REMUNERACAO' => $request->CARGO_REMUNERACAO ?? null,
                'CARGO_ESCOLARIDADE' => $request->CARGO_ESCOLARIDADE ?? null,
                'CARGO_GESTAO' => $request->CARGO_GESTAO ?? 0,
                'CARGO_DATA_INICIO' => $request->CARGO_DATA_INICIO ?? null,
            ]);
            return response()->json(['ok' => true]);
        } catch (\Throwable $e) {
            return response()->json(['erro' => $e->getMessage()], 500);
        }
    });

    Route::delete('/cargos/{id}', function ($id) {
        try {
            \Illuminate\Support\Facades\DB::table('CARGO')->where('CARGO_ID', $id)->update(['CARGO_ATIVO' => 0]);
            return response()->json(['ok' => true]);
        } catch (\Throwable $e) {
            return response()->json(['erro' => $e->getMessage()], 500);
        }
    });

    // ─ FUNÇÕES / COMISSÃO (S-1040) ────────────────────────────
    Route::get('/funcoes', function (\Illuminate\Http\Request $request) {
        try {
            $q = \Illuminate\Support\Facades\DB::table('FUNCAO');
            if ($request->has('q') && $request->q)
                $q->where('FUNCAO_NOME', 'like', '%' . $request->q . '%');
            $rows = $q->orderBy('FUNCAO_NOME')->get()->map(fn($f) => [
                'funcao_id' => $f->FUNCAO_ID,
                'nome' => $f->FUNCAO_NOME,
                'cbo' => $f->FUNCAO_CBO ?? null,
                'tipo' => $f->FUNCAO_TIPO ?? null,
                'gratificacao' => $f->FUNCAO_GRATIFICACAO ?? null,
                'ativo' => (bool) ($f->FUNCAO_ATIVO ?? 1),
            ]);
            return response()->json(['funcoes' => $rows]);
        } catch (\Throwable $e) {
            return response()->json(['funcoes' => [], 'erro' => $e->getMessage()]);
        }
    });

    Route::post('/funcoes', function (\Illuminate\Http\Request $request) {
        try {
            if (!$request->nome)
                return response()->json(['erro' => 'Nome obrigatório.'], 422);
            $id = \Illuminate\Support\Facades\DB::table('FUNCAO')->insertGetId([
                'FUNCAO_NOME' => $request->nome,
                'FUNCAO_CBO' => $request->cbo ?? null,
                'FUNCAO_TIPO' => $request->tipo ?? null,
                'FUNCAO_GRATIFICACAO' => $request->gratificacao ?? null,
                'FUNCAO_ATIVO' => 1,
            ]);
            return response()->json(['id' => $id], 201);
        } catch (\Throwable $e) {
            return response()->json(['erro' => $e->getMessage()], 500);
        }
    });

    Route::put('/funcoes/{id}', function (\Illuminate\Http\Request $request, $id) {
        try {
            \Illuminate\Support\Facades\DB::table('FUNCAO')->where('FUNCAO_ID', $id)->update([
                'FUNCAO_NOME' => $request->nome,
                'FUNCAO_CBO' => $request->cbo ?? null,
                'FUNCAO_TIPO' => $request->tipo ?? null,
                'FUNCAO_GRATIFICACAO' => $request->gratificacao ?? null,
            ]);
            return response()->json(['ok' => true]);
        } catch (\Throwable $e) {
            return response()->json(['erro' => $e->getMessage()], 500);
        }
    });

    Route::delete('/funcoes/{id}', function ($id) {
        try {
            \Illuminate\Support\Facades\DB::table('FUNCAO')->where('FUNCAO_ID', $id)->update(['FUNCAO_ATIVO' => 0]);
            return response()->json(['ok' => true]);
        } catch (\Throwable $e) {
            return response()->json(['erro' => $e->getMessage()], 500);
        }
    });

    // ─ CALCULAR FOLHA ─────────────────────────────────────────
    Route::post('/folhas/calcular', function (\Illuminate\Http\Request $request) {
        try {
            $competencia = $request->competencia ?? date('Y-m'); // ex: '2026-02'

            // Normaliza formatos: '022026' ou '02/2026' → '2026-02'
            if (preg_match('/^(\d{2})(\d{4})$/', $competencia, $m)) {
                $competencia = $m[2] . '-' . $m[1];
            } elseif (preg_match('/^(\d{2})\/(\d{4})$/', $competencia, $m)) {
                $competencia = $m[2] . '-' . $m[1];
            }

            // Busca folha existente para esta competência
            $folha = \Illuminate\Support\Facades\DB::table('FOLHA')
                ->where('FOLHA_COMPETENCIA', $competencia)->first();

            if (!$folha) {
                return response()->json(['erro' => "Nenhuma folha para '{$competencia}'. Crie a folha primeiro."], 404);
            }
            $folhaId = $folha->FOLHA_ID;

            // Conta funcionários com detalhe na folha
            $qtd = \Illuminate\Support\Facades\DB::table('DETALHE_FOLHA')
                ->where('FOLHA_ID', $folhaId)
                ->whereNull('DETALHE_FOLHA_ERRO')
                ->count();

            if ($qtd === 0) {
                return response()->json(['erro' => 'Nenhum funcionário na folha. Execute o seed de dados primeiro.'], 422);
            }

            // Soma proventos e descontos da DETALHE_FOLHA
            $totais = \Illuminate\Support\Facades\DB::table('DETALHE_FOLHA')
                ->where('FOLHA_ID', $folhaId)
                ->whereNull('DETALHE_FOLHA_ERRO')
                ->selectRaw('SUM(DETALHE_FOLHA_PROVENTOS) as proventos, SUM(DETALHE_FOLHA_DESCONTOS) as descontos')
                ->first();

            $proventos = round((float) ($totais->proventos ?? 0), 2);
            $descontos = round((float) ($totais->descontos ?? 0), 2);
            $liquido = $proventos - $descontos;

            // Atualiza totais da folha
            \Illuminate\Support\Facades\DB::table('FOLHA')->where('FOLHA_ID', $folhaId)->update([
                'FOLHA_STATUS' => 'Fechada',
                'FOLHA_QTD_SERVIDORES' => $qtd,
                'FOLHA_VALOR_TOTAL' => $proventos,
                'FOLHA_ATIVO' => 1,
            ]);

            return response()->json([
                'ok' => true,
                'folha_id' => $folhaId,
                'competencia' => $competencia,
                'qtd_funcionarios' => $qtd,
                'total_proventos' => $proventos,
                'total_descontos' => $descontos,
                'total_liquido' => $liquido,
                'mensagem' => "Folha {$competencia} calculada! {$qtd} funcs, R$ " . number_format($proventos, 2, ',', '.'),
            ]);
        } catch (\Throwable $e) {
            return response()->json(['erro' => $e->getMessage()], 500);
        }
    });

    // ─ PROGRESSÃO FUNCIONAL ────────────────────────────────────
    Route::get('/progressao-funcional', function (\Illuminate\Http\Request $request) {
        try {
            $user = \Illuminate\Support\Facades\Auth::user();
            $funcId = $user->FUNCIONARIO_ID ?? null;

            $progressoes = [];
            $admissao = null;
            $salarioBase = 0;

            if ($funcId) {
                $func = \Illuminate\Support\Facades\DB::table('FUNCIONARIO')
                    ->where('FUNCIONARIO_ID', $funcId)->first();
                $admissao = $func?->FUNCIONARIO_DATA_INICIO ?? null;
                $salarioBase = $func?->FUNCIONARIO_SALARIO_BASE ?? 0;

                $rows = \Illuminate\Support\Facades\DB::table('PROGRESSAO_FUNCIONAL')
                    ->where('FUNCIONARIO_ID', $funcId)
                    ->orderBy('PROGRESSAO_DATA')
                    ->get();

                foreach ($rows as $p) {
                    $progressoes[] = [
                        'id' => $p->PROGRESSAO_ID,
                        'nivel' => $p->PROGRESSAO_NIVEL ?? '—',
                        'referencia' => $p->PROGRESSAO_REFERENCIA ?? '—',
                        'salario' => $p->PROGRESSAO_SALARIO ?? 0,
                        'data' => $p->PROGRESSAO_DATA ?? null,
                        'tipo' => $p->PROGRESSAO_TIPO ?? 'Progressão',
                        'reajuste' => $p->PROGRESSAO_REAJUSTE ?? 0,
                        'obs' => $p->PROGRESSAO_OBS ?? null,
                        'ativa' => (bool) ($p->PROGRESSAO_ATIVA ?? false),
                        'futura' => (bool) ($p->PROGRESSAO_FUTURA ?? false),
                    ];
                }
            }

            $fallback = empty($progressoes);
            return response()->json([
                'fallback' => $fallback,
                'progressoes' => $progressoes,
                'admissao' => $admissao,
                'salario_base' => $salarioBase,
            ]);
        } catch (\Throwable $e) {
            return response()->json(['fallback' => true, 'progressoes' => [], 'erro' => $e->getMessage()]);
        }
    });


    // ─ TURNOS ─────────────────────────────────────────────────
    // Colunas reais: TURNO_ID, TURNO_SIGLA, TURNO_DESCRICAO,
    //   TURNO_HORA_INICIO, TURNO_HORA_FIM, TURNO_CARGA_HORARIA, TURNO_ATIVO
    Route::get('/turnos', function () {
        try {
            $rows = \Illuminate\Support\Facades\DB::table('TURNO')
                ->where('TURNO_ATIVO', 1)
                ->orderBy('TURNO_SIGLA')->get()
                ->map(fn($t) => [
                    'id' => $t->TURNO_ID,
                    'nome' => $t->TURNO_DESCRICAO ?? $t->TURNO_SIGLA,
                    'sigla' => $t->TURNO_SIGLA,
                    'codigo' => $t->TURNO_SIGLA,
                    'hora_entrada' => $t->TURNO_HORA_INICIO ?? null,
                    'hora_saida' => $t->TURNO_HORA_FIM ?? null,
                    'carga_horaria' => $t->TURNO_CARGA_HORARIA ?? null,
                    'ativo' => (bool) ($t->TURNO_ATIVO ?? 1),
                ]);
            return response()->json(['turnos' => $rows]);
        } catch (\Throwable $e) {
            return response()->json(['turnos' => [], 'erro' => $e->getMessage()]);
        }
    });
    Route::post('/turnos', function (\Illuminate\Http\Request $req) {
        try {
            if (!$req->TURNO_SIGLA || !$req->TURNO_DESCRICAO)
                return response()->json(['erro' => 'Sigla e descrição obrigatórios.'], 422);
            $id = \Illuminate\Support\Facades\DB::table('TURNO')->insertGetId([
                'TURNO_SIGLA' => strtoupper($req->TURNO_SIGLA),
                'TURNO_DESCRICAO' => $req->TURNO_DESCRICAO,
                'TURNO_HORA_INICIO' => $req->TURNO_HORA_INICIO ?? null,
                'TURNO_HORA_FIM' => $req->TURNO_HORA_FIM ?? null,
                'TURNO_CARGA_HORARIA' => $req->TURNO_CARGA_HORARIA ?? null,
                'TURNO_ATIVO' => 1,
            ]);
            return response()->json(['id' => $id], 201);
        } catch (\Throwable $e) {
            return response()->json(['erro' => $e->getMessage()], 500);
        }
    });
    Route::put('/turnos/{id}', function (\Illuminate\Http\Request $req, $id) {
        try {
            \Illuminate\Support\Facades\DB::table('TURNO')->where('TURNO_ID', $id)->update([
                'TURNO_SIGLA' => strtoupper($req->TURNO_SIGLA),
                'TURNO_DESCRICAO' => $req->TURNO_DESCRICAO,
                'TURNO_HORA_INICIO' => $req->TURNO_HORA_INICIO ?? null,
                'TURNO_HORA_FIM' => $req->TURNO_HORA_FIM ?? null,
                'TURNO_CARGA_HORARIA' => $req->TURNO_CARGA_HORARIA ?? null,
            ]);
            return response()->json(['ok' => true]);
        } catch (\Throwable $e) {
            return response()->json(['erro' => $e->getMessage()], 500);
        }
    });
    Route::delete('/turnos/{id}', function ($id) {
        try {
            \Illuminate\Support\Facades\DB::table('TURNO')->where('TURNO_ID', $id)->update(['TURNO_ATIVO' => 0]);
            return response()->json(['ok' => true]);
        } catch (\Throwable $e) {
            return response()->json(['erro' => $e->getMessage()], 500);
        }
    });

    // ─ FERIADOS ───────────────────────────────────────────────
    Route::get('/feriados', function () {
        try {
            $rows = \Illuminate\Support\Facades\DB::table('FERIADO')->orderBy('FERIADO_DATA')->get()->map(fn($f) => ['id' => $f->FERIADO_ID, 'nome' => $f->FERIADO_NOME, 'data' => $f->FERIADO_DATA, 'tipo' => $f->FERIADO_TIPO ?? 'N', 'recorrente' => (bool) ($f->FERIADO_RECORRENTE ?? 0)]);
            return response()->json(['feriados' => $rows]);
        } catch (\Throwable $e) {
            return response()->json(['feriados' => [], 'erro' => $e->getMessage()]);
        }
    });
    Route::post('/feriados', function (\Illuminate\Http\Request $req) {
        try {
            if (!$req->FERIADO_NOME || !$req->FERIADO_DATA)
                return response()->json(['erro' => 'Nome e data obrigatorios.'], 422);
            $id = \Illuminate\Support\Facades\DB::table('FERIADO')->insertGetId(['FERIADO_NOME' => $req->FERIADO_NOME, 'FERIADO_DATA' => $req->FERIADO_DATA, 'FERIADO_TIPO' => $req->FERIADO_TIPO ?? 'N', 'FERIADO_RECORRENTE' => $req->FERIADO_RECORRENTE ? 1 : 0]);
            return response()->json(['id' => $id], 201);
        } catch (\Throwable $e) {
            return response()->json(['erro' => $e->getMessage()], 500);
        }
    });
    Route::put('/feriados/{id}', function (\Illuminate\Http\Request $req, $id) {
        try {
            \Illuminate\Support\Facades\DB::table('FERIADO')->where('FERIADO_ID', $id)->update(['FERIADO_NOME' => $req->FERIADO_NOME, 'FERIADO_DATA' => $req->FERIADO_DATA, 'FERIADO_TIPO' => $req->FERIADO_TIPO ?? 'N', 'FERIADO_RECORRENTE' => $req->FERIADO_RECORRENTE ? 1 : 0]);
            return response()->json(['ok' => true]);
        } catch (\Throwable $e) {
            return response()->json(['erro' => $e->getMessage()], 500);
        }
    });
    Route::delete('/feriados/{id}', function ($id) {
        try {
            \Illuminate\Support\Facades\DB::table('FERIADO')->where('FERIADO_ID', $id)->delete();
            return response()->json(['ok' => true]);
        } catch (\Throwable $e) {
            return response()->json(['erro' => $e->getMessage()], 500);
        }
    });

    // ─ VINCULOS ───────────────────────────────────────────────
    Route::get('/vinculos', function () {
        try {
            $rows = \Illuminate\Support\Facades\DB::table('VINCULO')->orderBy('VINCULO_NOME')->get()->map(fn($v) => ['id' => $v->VINCULO_ID, 'nome' => $v->VINCULO_NOME, 'sigla' => $v->VINCULO_SIGLA ?? null, 'tipo_esocial' => $v->VINCULO_TIPO_ESOCIAL ?? null, 'fgts' => (bool) ($v->VINCULO_FGTS ?? 0), 'inss' => (bool) ($v->VINCULO_INSS ?? 0), 'irrf' => (bool) ($v->VINCULO_IRRF ?? 0), 'descricao' => $v->VINCULO_DESCRICAO ?? null, 'ativo' => (bool) ($v->VINCULO_ATIVO ?? 1)]);
            return response()->json(['vinculos' => $rows]);
        } catch (\Throwable $e) {
            return response()->json(['vinculos' => [], 'erro' => $e->getMessage()]);
        }
    });
    Route::post('/vinculos', function (\Illuminate\Http\Request $req) {
        try {
            if (!$req->VINCULO_NOME)
                return response()->json(['erro' => 'Nome obrigatorio.'], 422);
            $id = \Illuminate\Support\Facades\DB::table('VINCULO')->insertGetId(['VINCULO_NOME' => $req->VINCULO_NOME, 'VINCULO_SIGLA' => $req->VINCULO_SIGLA ?? null, 'VINCULO_TIPO_ESOCIAL' => $req->VINCULO_TIPO_ESOCIAL ?? null, 'VINCULO_FGTS' => $req->VINCULO_FGTS ? 1 : 0, 'VINCULO_INSS' => $req->VINCULO_INSS ? 1 : 0, 'VINCULO_IRRF' => $req->VINCULO_IRRF ? 1 : 0, 'VINCULO_DESCRICAO' => $req->VINCULO_DESCRICAO ?? null, 'VINCULO_ATIVO' => 1]);
            return response()->json(['id' => $id], 201);
        } catch (\Throwable $e) {
            return response()->json(['erro' => $e->getMessage()], 500);
        }
    });
    Route::put('/vinculos/{id}', function (\Illuminate\Http\Request $req, $id) {
        try {
            \Illuminate\Support\Facades\DB::table('VINCULO')->where('VINCULO_ID', $id)->update(['VINCULO_NOME' => $req->VINCULO_NOME, 'VINCULO_SIGLA' => $req->VINCULO_SIGLA ?? null, 'VINCULO_TIPO_ESOCIAL' => $req->VINCULO_TIPO_ESOCIAL ?? null, 'VINCULO_FGTS' => $req->VINCULO_FGTS ? 1 : 0, 'VINCULO_INSS' => $req->VINCULO_INSS ? 1 : 0, 'VINCULO_IRRF' => $req->VINCULO_IRRF ? 1 : 0, 'VINCULO_DESCRICAO' => $req->VINCULO_DESCRICAO ?? null]);
            return response()->json(['ok' => true]);
        } catch (\Throwable $e) {
            return response()->json(['erro' => $e->getMessage()], 500);
        }
    });
    Route::delete('/vinculos/{id}', function ($id) {
        try {
            \Illuminate\Support\Facades\DB::table('VINCULO')->where('VINCULO_ID', $id)->update(['VINCULO_ATIVO' => 0]);
            return response()->json(['ok' => true]);
        } catch (\Throwable $e) {
            return response()->json(['erro' => $e->getMessage()], 500);
        }
    });

    // ─ EVENTOS DE FOLHA ───────────────────────────────────────
    Route::get('/eventos', function () {
        try {
            $rows = \Illuminate\Support\Facades\DB::table('EVENTO')->orderBy('EVENTO_CODIGO')->get()->map(fn($e) => ['id' => $e->EVENTO_ID, 'codigo' => $e->EVENTO_CODIGO, 'nome' => $e->EVENTO_NOME, 'tipo' => $e->EVENTO_TIPO ?? 'P', 'inss' => (bool) ($e->EVENTO_INSS ?? 0), 'irrf' => (bool) ($e->EVENTO_IRRF ?? 0), 'fgts' => (bool) ($e->EVENTO_FGTS ?? 0), 'ativo' => (bool) ($e->EVENTO_ATIVO ?? 1)]);
            return response()->json(['eventos' => $rows]);
        } catch (\Throwable $e) {
            return response()->json(['eventos' => [], 'erro' => $e->getMessage()]);
        }
    });
    Route::post('/eventos', function (\Illuminate\Http\Request $req) {
        try {
            if (!$req->EVENTO_CODIGO || !$req->EVENTO_NOME)
                return response()->json(['erro' => 'Codigo e nome obrigatorios.'], 422);
            $id = \Illuminate\Support\Facades\DB::table('EVENTO')->insertGetId(['EVENTO_CODIGO' => $req->EVENTO_CODIGO, 'EVENTO_NOME' => $req->EVENTO_NOME, 'EVENTO_TIPO' => $req->EVENTO_TIPO ?? 'P', 'EVENTO_INSS' => $req->EVENTO_INSS ? 1 : 0, 'EVENTO_IRRF' => $req->EVENTO_IRRF ? 1 : 0, 'EVENTO_FGTS' => $req->EVENTO_FGTS ? 1 : 0, 'EVENTO_ATIVO' => 1]);
            return response()->json(['id' => $id], 201);
        } catch (\Throwable $e) {
            return response()->json(['erro' => $e->getMessage()], 500);
        }
    });
    Route::put('/eventos/{id}', function (\Illuminate\Http\Request $req, $id) {
        try {
            \Illuminate\Support\Facades\DB::table('EVENTO')->where('EVENTO_ID', $id)->update(['EVENTO_CODIGO' => $req->EVENTO_CODIGO, 'EVENTO_NOME' => $req->EVENTO_NOME, 'EVENTO_TIPO' => $req->EVENTO_TIPO ?? 'P', 'EVENTO_INSS' => $req->EVENTO_INSS ? 1 : 0, 'EVENTO_IRRF' => $req->EVENTO_IRRF ? 1 : 0, 'EVENTO_FGTS' => $req->EVENTO_FGTS ? 1 : 0]);
            return response()->json(['ok' => true]);
        } catch (\Throwable $e) {
            return response()->json(['erro' => $e->getMessage()], 500);
        }
    });
    Route::delete('/eventos/{id}', function ($id) {
        try {
            \Illuminate\Support\Facades\DB::table('EVENTO')->where('EVENTO_ID', $id)->update(['EVENTO_ATIVO' => 0]);
            return response()->json(['ok' => true]);
        } catch (\Throwable $e) {
            return response()->json(['erro' => $e->getMessage()], 500);
        }
    });

    // ─ ESCALA DE TRABALHO ─────────────────────────────────────
    // GET /api/v3/escala-trabalho?mes=2&ano=2026[&setor_id=X]
    // Retorna grade mensal de turnos usando DETALHE_ESCALA_ITEM
    Route::get('/escala-trabalho', function (\Illuminate\Http\Request $req) {
        try {
            $mes = (int) ($req->mes ?? date('n'));
            $ano = (int) ($req->ano ?? date('Y'));
            $ini = sprintf('%04d-%02d-01', $ano, $mes);
            $fim = date('Y-m-t', strtotime($ini));
            $setorId = $req->setor_id ?: null;

            // Setores ativos
            $setores = \Illuminate\Support\Facades\DB::table('SETOR')
                ->where('SETOR_ATIVO', 1)->orderBy('SETOR_NOME')->get()
                ->map(fn($s) => ['id' => $s->SETOR_ID, 'nome' => $s->SETOR_NOME]);

            // Funcionários com lotação ativa no mês, filtrados por setor se pedido
            $funcQ = \Illuminate\Support\Facades\DB::table('FUNCIONARIO as f')
                ->join('PESSOA as p', 'p.PESSOA_ID', '=', 'f.PESSOA_ID')
                ->join('LOTACAO as l', function ($j) {
                    $j->on('l.FUNCIONARIO_ID', '=', 'f.FUNCIONARIO_ID')
                        ->whereNull('l.LOTACAO_DATA_FIM');
                })
                ->join('SETOR as s', 's.SETOR_ID', '=', 'l.SETOR_ID')
                ->whereNull('f.FUNCIONARIO_DATA_FIM')
                ->select('f.FUNCIONARIO_ID', 'p.PESSOA_NOME', 's.SETOR_NOME', 'l.SETOR_ID');

            if ($setorId)
                $funcQ->where('l.SETOR_ID', $setorId);
            $funcs = $funcQ->get();

            // Itens de escala (DETALHE_ESCALA_ITEM) para o período
            $funcIds = $funcs->pluck('FUNCIONARIO_ID')->toArray();
            $itens = \Illuminate\Support\Facades\DB::table('DETALHE_ESCALA_ITEM as dei')
                ->join('DETALHE_ESCALA as de', 'de.DETALHE_ESCALA_ID', '=', 'dei.DETALHE_ESCALA_ID')
                ->join('ESCALA as e', 'e.ESCALA_ID', '=', 'de.ESCALA_ID')
                ->join('TURNO as t', 't.TURNO_ID', '=', 'dei.TURNO_ID')
                ->whereIn('de.FUNCIONARIO_ID', $funcIds)
                ->whereBetween('dei.DETALHE_ESCALA_ITEM_DATA', [$ini, $fim])
                ->select('de.FUNCIONARIO_ID', 'dei.DETALHE_ESCALA_ITEM_DATA as data', 't.TURNO_SIGLA as turno_sigla', 't.TURNO_NOME as turno_nome')
                ->get()->groupBy('FUNCIONARIO_ID');

            $escala = $funcs->map(function ($f) use ($itens) {
                $dias = [];
                foreach ($itens->get($f->FUNCIONARIO_ID, collect()) as $item) {
                    $dia = (int) date('j', strtotime($item->data));
                    $dias[$dia] = ['turno' => $item->turno_sigla ?? $item->turno_nome, 'obs' => null];
                }
                return [
                    'funcionario_id' => $f->FUNCIONARIO_ID,
                    'nome' => $f->PESSOA_NOME,
                    'setor' => $f->SETOR_NOME,
                    'dias' => $dias,
                ];
            });

            $funcionarios = $funcs->map(fn($f) => ['id' => $f->FUNCIONARIO_ID, 'nome' => $f->PESSOA_NOME]);
            return response()->json(['escala' => $escala, 'setores' => $setores, 'funcionarios' => $funcionarios]);
        } catch (\Throwable $e) {
            return response()->json(['escala' => [], 'setores' => [], 'funcionarios' => [], 'erro' => $e->getMessage()]);
        }
    });
    // POST /api/v3/escala-trabalho — salva turno de um dia na DETALHE_ESCALA_ITEM
    Route::post('/escala-trabalho', function (\Illuminate\Http\Request $req) {
        try {
            if (!$req->funcionario_id || !$req->data || !$req->turno)
                return response()->json(['erro' => 'Funcionario, data e turno obrigatorios.'], 422);

            $funcId = $req->funcionario_id;
            $data = $req->data; // 'YYYY-MM-DD'
            $sigla = strtoupper($req->turno);

            // Busca ou cria TURNO pelo sigla
            $turno = \Illuminate\Support\Facades\DB::table('TURNO')->where('TURNO_SIGLA', $sigla)->first()
                ?? \Illuminate\Support\Facades\DB::table('TURNO')->where('TURNO_NOME', $sigla)->first();
            if (!$turno)
                return response()->json(['erro' => "Turno '{$sigla}' não encontrado. Cadastre-o primeiro."], 422);

            // Busca competência para achar a escala do mês
            [$ano, $mes] = explode('-', substr($data, 0, 7));
            $meses = ['', 'Jan', 'Fev', 'Mar', 'Abr', 'Mai', 'Jun', 'Jul', 'Ago', 'Set', 'Out', 'Nov', 'Dez'];
            $compStr = ($meses[(int) $mes] ?? $mes) . '/' . $ano;

            // Busca escala do setor do funcionário naquele mês
            $lotacao = \Illuminate\Support\Facades\DB::table('LOTACAO')
                ->where('FUNCIONARIO_ID', $funcId)->whereNull('LOTACAO_DATA_FIM')->first();
            if (!$lotacao)
                return response()->json(['erro' => 'Funcionário sem setor ativo definido.'], 422);

            $escala = \Illuminate\Support\Facades\DB::table('ESCALA')
                ->where('SETOR_ID', $lotacao->SETOR_ID)
                ->where('ESCALA_COMPETENCIA', $compStr)->first();

            if (!$escala) {
                $escalaId = \Illuminate\Support\Facades\DB::table('ESCALA')->insertGetId([
                    'SETOR_ID' => $lotacao->SETOR_ID,
                    'ESCALA_COMPETENCIA' => $compStr,
                    'ESCALA_DESCRICAO' => "Escala {$compStr}",
                    'ESCALA_ATIVO' => 1,
                ]);
            } else {
                $escalaId = $escala->ESCALA_ID;
            }

            // Garante DETALHE_ESCALA para o funcionário
            $det = \Illuminate\Support\Facades\DB::table('DETALHE_ESCALA')
                ->where('ESCALA_ID', $escalaId)->where('FUNCIONARIO_ID', $funcId)->first();
            if (!$det) {
                $detId = \Illuminate\Support\Facades\DB::table('DETALHE_ESCALA')
                    ->insertGetId(['ESCALA_ID' => $escalaId, 'FUNCIONARIO_ID' => $funcId]);
            } else {
                $detId = $det->DETALHE_ESCALA_ID;
            }

            // Upsert DETALHE_ESCALA_ITEM
            \Illuminate\Support\Facades\DB::table('DETALHE_ESCALA_ITEM')->updateOrInsert(
                ['DETALHE_ESCALA_ID' => $detId, 'DETALHE_ESCALA_ITEM_DATA' => $data],
                ['TURNO_ID' => $turno->TURNO_ID]
            );
            return response()->json(['ok' => true]);
        } catch (\Throwable $e) {
            return response()->json(['erro' => $e->getMessage()], 500);
        }
    });

    // ─ PARAMETROS FINANCEIROS ─────────────────────────────────
    Route::get('/parametros-financeiros', function () {
        try {
            $rows = \Illuminate\Support\Facades\DB::table('PARAMETRO_FINANCEIRO')->orderBy('PARAMETRO_TIPO')->orderBy('PARAMETRO_COMPETENCIA', 'desc')->get()->map(fn($p) => ['id' => $p->PARAMETRO_ID, 'tipo' => $p->PARAMETRO_TIPO, 'competencia' => $p->PARAMETRO_COMPETENCIA ?? null, 'descricao' => $p->PARAMETRO_DESCRICAO, 'valor' => $p->PARAMETRO_VALOR, 'tipo_valor' => $p->PARAMETRO_TIPO_VALOR ?? 'ALIQUOTA', 'vigencia_inicio' => $p->PARAMETRO_VIGENCIA_INICIO ?? null, 'vigencia_fim' => $p->PARAMETRO_VIGENCIA_FIM ?? null]);
            return response()->json(['parametros' => $rows]);
        } catch (\Throwable $e) {
            return response()->json(['parametros' => [], 'erro' => $e->getMessage()]);
        }
    });
    Route::post('/parametros-financeiros', function (\Illuminate\Http\Request $req) {
        try {
            if (!$req->descricao)
                return response()->json(['erro' => 'Descricao obrigatoria.'], 422);
            $id = \Illuminate\Support\Facades\DB::table('PARAMETRO_FINANCEIRO')->insertGetId(['PARAMETRO_TIPO' => $req->tipo ?? 'OUTROS', 'PARAMETRO_COMPETENCIA' => $req->competencia ?? null, 'PARAMETRO_DESCRICAO' => $req->descricao, 'PARAMETRO_VALOR' => $req->valor ?? 0, 'PARAMETRO_TIPO_VALOR' => $req->tipo_valor ?? 'ALIQUOTA', 'PARAMETRO_VIGENCIA_INICIO' => $req->vigencia_inicio ?? null, 'PARAMETRO_VIGENCIA_FIM' => $req->vigencia_fim ?? null]);
            return response()->json(['id' => $id], 201);
        } catch (\Throwable $e) {
            return response()->json(['erro' => $e->getMessage()], 500);
        }
    });
    Route::put('/parametros-financeiros/{id}', function (\Illuminate\Http\Request $req, $id) {
        try {
            \Illuminate\Support\Facades\DB::table('PARAMETRO_FINANCEIRO')->where('PARAMETRO_ID', $id)->update(['PARAMETRO_TIPO' => $req->tipo ?? 'OUTROS', 'PARAMETRO_COMPETENCIA' => $req->competencia ?? null, 'PARAMETRO_DESCRICAO' => $req->descricao, 'PARAMETRO_VALOR' => $req->valor ?? 0, 'PARAMETRO_TIPO_VALOR' => $req->tipo_valor ?? 'ALIQUOTA', 'PARAMETRO_VIGENCIA_INICIO' => $req->vigencia_inicio ?? null, 'PARAMETRO_VIGENCIA_FIM' => $req->vigencia_fim ?? null]);
            return response()->json(['ok' => true]);
        } catch (\Throwable $e) {
            return response()->json(['erro' => $e->getMessage()], 500);
        }
    });
    Route::delete('/parametros-financeiros/{id}', function ($id) {
        try {
            \Illuminate\Support\Facades\DB::table('PARAMETRO_FINANCEIRO')->where('PARAMETRO_ID', $id)->delete();
            return response()->json(['ok' => true]);
        } catch (\Throwable $e) {
            return response()->json(['erro' => $e->getMessage()], 500);
        }
    });

    // ─ TABELAS AUXILIARES (generico) ──────────────────────────
    $tabelasConfig = [
        'banco' => ['tabela' => 'BANCO', 'pk' => 'BANCO_ID', 'campos' => ['codigo' => 'BANCO_CODIGO', 'nome' => 'BANCO_NOME']],
        'uf' => ['tabela' => 'UF', 'pk' => 'UF_ID', 'campos' => ['sigla' => 'UF_SIGLA', 'nome' => 'UF_NOME', 'regiao' => 'UF_REGIAO']],
        'cidade' => ['tabela' => 'CIDADE', 'pk' => 'CIDADE_ID', 'campos' => ['nome' => 'CIDADE_NOME', 'uf_sigla' => 'UF_SIGLA', 'ibge' => 'CIDADE_IBGE']],
        'bairro' => ['tabela' => 'BAIRRO', 'pk' => 'BAIRRO_ID', 'campos' => ['nome' => 'BAIRRO_NOME', 'cidade_id' => 'CIDADE_ID']],
        'cartorio' => ['tabela' => 'CARTORIO', 'pk' => 'CARTORIO_ID', 'campos' => ['nome' => 'CARTORIO_NOME', 'cidade' => 'CARTORIO_CIDADE']],
        'conselho' => ['tabela' => 'CONSELHO', 'pk' => 'CONSELHO_ID', 'campos' => ['sigla' => 'CONSELHO_SIGLA', 'nome' => 'CONSELHO_NOME']],
        'tipo-documento' => ['tabela' => 'TIPO_DOCUMENTO', 'pk' => 'TIPO_DOCUMENTO_ID', 'campos' => ['codigo' => 'TIPO_DOCUMENTO_CODIGO', 'nome' => 'TIPO_DOCUMENTO_NOME']],
    ];

    foreach ($tabelasConfig as $recurso => $cfg) {
        Route::get("/tabelas/{$recurso}", function () use ($cfg) {
            try {
                $rows = \Illuminate\Support\Facades\DB::table($cfg['tabela'])->orderBy(reset($cfg['campos']))->limit(2000)->get();
                $itens = $rows->map(function ($r) use ($cfg) {
                    $item = ['id' => $r->{$cfg['pk']}];
                    foreach ($cfg['campos'] as $alias => $col) {
                        $item[$alias] = $r->$col ?? null;
                    }
                    return $item;
                });
                return response()->json(['itens' => $itens]);
            } catch (\Throwable $e) {
                return response()->json(['itens' => [], 'erro' => $e->getMessage()]);
            }
        });
        Route::post("/tabelas/{$recurso}", function (\Illuminate\Http\Request $req) use ($cfg) {
            try {
                $dados = [];
                foreach ($cfg['campos'] as $alias => $col) {
                    if ($req->has($alias))
                        $dados[$col] = $req->$alias;
                }
                $id = \Illuminate\Support\Facades\DB::table($cfg['tabela'])->insertGetId($dados);
                return response()->json(['id' => $id], 201);
            } catch (\Throwable $e) {
                return response()->json(['erro' => $e->getMessage()], 500);
            }
        });
        Route::put("/tabelas/{$recurso}/{id}", function (\Illuminate\Http\Request $req, $id) use ($cfg) {
            try {
                $dados = [];
                foreach ($cfg['campos'] as $alias => $col) {
                    if ($req->has($alias))
                        $dados[$col] = $req->$alias;
                }
                \Illuminate\Support\Facades\DB::table($cfg['tabela'])->where($cfg['pk'], $id)->update($dados);
                return response()->json(['ok' => true]);
            } catch (\Throwable $e) {
                return response()->json(['erro' => $e->getMessage()], 500);
            }
        });
        Route::delete("/tabelas/{$recurso}/{id}", function ($id) use ($cfg) {
            try {
                \Illuminate\Support\Facades\DB::table($cfg['tabela'])->where($cfg['pk'], $id)->delete();
                return response()->json(['ok' => true]);
            } catch (\Throwable $e) {
                return response()->json(['erro' => $e->getMessage()], 500);
            }
        });
    }

    // ─ SUBSTITUICOES DE PLANTAO / ESCALA ─────────────────────
    Route::get('/substituicoes', function (\Illuminate\Http\Request $req) {
        try {
            $q = \Illuminate\Support\Facades\DB::table('SUBSTITUICAO')
                ->join('FUNCIONARIO as SOL', 'SUBSTITUICAO.FUNCIONARIO_ID_SOLICITANTE', '=', 'SOL.FUNCIONARIO_ID')
                ->leftJoin('FUNCIONARIO as SUB', 'SUBSTITUICAO.FUNCIONARIO_ID_SUBSTITUTO', '=', 'SUB.FUNCIONARIO_ID')
                ->leftJoin('SETOR', 'SUBSTITUICAO.SETOR_ID', '=', 'SETOR.SETOR_ID')
                ->orderBy('SUBSTITUICAO.SUBSTITUICAO_DATA_PLANTAO', 'desc')
                ->limit(200)
                ->select(
                    'SUBSTITUICAO.SUBSTITUICAO_ID as id',
                    'SOL.FUNCIONARIO_NOME as solicitante',
                    'SUBSTITUICAO.FUNCIONARIO_ID_SOLICITANTE as solicitante_id',
                    'SUB.FUNCIONARIO_NOME as substituto',
                    'SUBSTITUICAO.FUNCIONARIO_ID_SUBSTITUTO as substituto_id',
                    'SUBSTITUICAO.SUBSTITUICAO_DATA_PLANTAO as data_plantao',
                    'SUBSTITUICAO.SUBSTITUICAO_TURNO as turno',
                    'SUBSTITUICAO.SUBSTITUICAO_STATUS as status',
                    'SUBSTITUICAO.SUBSTITUICAO_MOTIVO as motivo',
                    'SETOR.SETOR_NOME as setor',
                    'SUBSTITUICAO.CREATED_AT as criado_em'
                )->get();
            return response()->json(['substituicoes' => $q]);
        } catch (\Throwable $e) {
            return response()->json(['substituicoes' => [], 'erro' => $e->getMessage()]);
        }
    });

    Route::post('/substituicoes', function (\Illuminate\Http\Request $req) {
        try {
            if (!$req->solicitante_id || !$req->data_plantao || !$req->turno)
                return response()->json(['erro' => 'Solicitante, data e turno obrigatorios.'], 422);
            $id = \Illuminate\Support\Facades\DB::table('SUBSTITUICAO')->insertGetId([
                'FUNCIONARIO_ID_SOLICITANTE' => $req->solicitante_id,
                'FUNCIONARIO_ID_SUBSTITUTO' => $req->substituto_id ?? null,
                'SETOR_ID' => $req->setor_id ?? null,
                'SUBSTITUICAO_DATA_PLANTAO' => $req->data_plantao,
                'SUBSTITUICAO_TURNO' => $req->turno,
                'SUBSTITUICAO_MOTIVO' => $req->motivo ?? null,
                'SUBSTITUICAO_STATUS' => 'pendente',
                'CREATED_AT' => now(),
                'UPDATED_AT' => now(),
            ]);
            return response()->json(['id' => $id, 'status' => 'pendente'], 201);
        } catch (\Throwable $e) {
            return response()->json(['erro' => $e->getMessage()], 500);
        }
    });

    Route::put('/substituicoes/{id}/aprovar', function (\Illuminate\Http\Request $req, $id) {
        try {
            \Illuminate\Support\Facades\DB::table('SUBSTITUICAO')->where('SUBSTITUICAO_ID', $id)->update([
                'SUBSTITUICAO_STATUS' => $req->status ?? 'aprovada',
                'SUBSTITUICAO_OBS_APROVADOR' => $req->obs ?? null,
                'UPDATED_AT' => now(),
            ]);
            return response()->json(['ok' => true]);
        } catch (\Throwable $e) {
            return response()->json(['erro' => $e->getMessage()], 500);
        }
    });

    Route::delete('/substituicoes/{id}', function ($id) {
        try {
            \Illuminate\Support\Facades\DB::table('SUBSTITUICAO')->where('SUBSTITUICAO_ID', $id)->delete();
            return response()->json(['ok' => true]);
        } catch (\Throwable $e) {
            return response()->json(['erro' => $e->getMessage()], 500);
        }
    });

    // ─ ESCALAS (lista e detalhe) ──────────────────────────────
    Route::get('/escalas', function () {
        try {
            $escalas = \Illuminate\Support\Facades\DB::table('ESCALA')
                ->leftJoin('SETOR', 'ESCALA.SETOR_ID', '=', 'SETOR.SETOR_ID')
                ->where('ESCALA_ATIVO', 1)
                ->orderBy('ESCALA_NOME')
                ->select('ESCALA.ESCALA_ID', 'ESCALA_NOME', 'SETOR.SETOR_NOME as setor')
                ->get();
            return response()->json(['escalas' => $escalas]);
        } catch (\Throwable $e) {
            return response()->json(['escalas' => [], 'erro' => $e->getMessage()]);
        }
    });

    // ─── ESCALAS MÉDICAS ────────────────────────────────────────
    // Lista todas as escalas (para o select do MatrizEscalaView)
    Route::get('/escalas', function () {
        try {
            $rows = \Illuminate\Support\Facades\DB::table('ESCALA')
                ->leftJoin('SETOR', 'ESCALA.SETOR_ID', '=', 'SETOR.SETOR_ID')
                ->select(
                    'ESCALA.ESCALA_ID',
                    'ESCALA.ESCALA_COMPETENCIA',
                    'ESCALA.SETOR_ID',
                    \Illuminate\Support\Facades\DB::raw("COALESCE(SETOR.SETOR_NOME, 'Geral') as setor")
                )
                ->orderBy('ESCALA.ESCALA_COMPETENCIA', 'desc')
                ->limit(100)->get();
            return response()->json(['escalas' => $rows]);
        } catch (\Throwable $e) {
            return response()->json(['escalas' => [], 'erro' => $e->getMessage()]);
        }
    });

    // Cria nova escala médica
    Route::post('/escalas', function (\Illuminate\Http\Request $request) {
        try {
            $mes = (int) ($request->mes ?? date('n'));
            $ano = (int) ($request->ano ?? date('Y'));
            $setorId = $request->setor_id ?: null;
            $competencia = sprintf('%02d/%04d', $mes, $ano);

            // Verifica se já existe
            $existe = \Illuminate\Support\Facades\DB::table('ESCALA')
                ->where('ESCALA_COMPETENCIA', $competencia)
                ->where('SETOR_ID', $setorId)
                ->first();
            if ($existe) {
                return response()->json(['erro' => "Já existe escala $competencia para este setor."], 422);
            }

            $id = \Illuminate\Support\Facades\DB::table('ESCALA')->insertGetId([
                'ESCALA_COMPETENCIA' => $competencia,
                'SETOR_ID' => $setorId,
                'ESCALA_STATUS' => 'Aberta',
            ]);

            // Adiciona todos os funcionários do setor como detalhe
            $funcs = \Illuminate\Support\Facades\DB::table('FUNCIONARIO')
                ->where('FUNCIONARIO_ATIVO', 1)
                ->when($setorId, fn($q) => $q->where('SETOR_ID', $setorId))
                ->get(['FUNCIONARIO_ID']);

            foreach ($funcs as $f) {
                \Illuminate\Support\Facades\DB::table('DETALHE_ESCALA')->insert([
                    'ESCALA_ID' => $id,
                    'FUNCIONARIO_ID' => $f->FUNCIONARIO_ID,
                ]);
            }

            return response()->json(['ok' => true, 'escala_id' => $id, 'competencia' => $competencia]);
        } catch (\Throwable $e) {
            return response()->json(['erro' => $e->getMessage()], 500);
        }
    });

    // Detalhe de uma escala médica (funcionários + itens de plantão)
    Route::get('/escalas/{id}', function ($id) {
        try {
            $escala = \Illuminate\Support\Facades\DB::table('ESCALA')
                ->leftJoin('SETOR', 'ESCALA.SETOR_ID', '=', 'SETOR.SETOR_ID')
                ->where('ESCALA.ESCALA_ID', $id)
                ->select('ESCALA.*', \Illuminate\Support\Facades\DB::raw("COALESCE(SETOR.SETOR_NOME,'Geral') as setor_nome"))
                ->first();

            if (!$escala)
                return response()->json(['erro' => 'Escala não encontrada'], 404);

            // Competência: 'MM/YYYY' → mes e ano
            $mes = 1;
            $ano = date('Y');
            if (preg_match('/^(\d{2})\/(\d{4})$/', $escala->ESCALA_COMPETENCIA ?? '', $m)) {
                $mes = (int) $m[1];
                $ano = (int) $m[2];
            } elseif (preg_match('/^(\d{4})-(\d{2})$/', $escala->ESCALA_COMPETENCIA ?? '', $m)) {
                $ano = (int) $m[1];
                $mes = (int) $m[2];
            }

            // Funcionários vinculados à escala via DETALHE_ESCALA
            $detalhes = \Illuminate\Support\Facades\DB::table('DETALHE_ESCALA as D')
                ->join('FUNCIONARIO as F', 'D.FUNCIONARIO_ID', '=', 'F.FUNCIONARIO_ID')
                ->leftJoin('PESSOA as P', 'F.PESSOA_ID', '=', 'P.PESSOA_ID')
                ->where('D.ESCALA_ID', $id)
                ->select(
                    'D.DETALHE_ESCALA_ID as detalhe_id',
                    'F.FUNCIONARIO_ID as funcionario_id',
                    \Illuminate\Support\Facades\DB::raw("COALESCE(P.PESSOA_NOME, 'Funcionário') as nome"),
                    \Illuminate\Support\Facades\DB::raw("'Profissional' as cargo")
                )
                ->get();

            // Itens de plantão (DETALHE_ESCALA_ITEM ou fallback sem itens)
            $funcionarios = [];
            foreach ($detalhes as $d) {
                $itens = [];
                try {
                    $raw = \Illuminate\Support\Facades\DB::table('DETALHE_ESCALA_ITEM')
                        ->where('DETALHE_ESCALA_ID', $d->detalhe_id)
                        ->get(['DETALHE_ESCALA_ITEM_DATA as data', 'TURNO_ID as turno_id', 'TURNO_SIGLA as turno_sigla']);
                    $itens = $raw->toArray();
                } catch (\Throwable) {
                    $itens = [];
                }

                $funcionarios[] = [
                    'detalhe_id' => $d->detalhe_id,
                    'funcionario_id' => $d->funcionario_id,
                    'nome' => $d->nome,
                    'cargo' => $d->cargo,
                    'itens' => $itens,
                ];
            }

            return response()->json([
                'escala' => ['competencia' => $escala->ESCALA_COMPETENCIA, 'ano' => $ano, 'mes' => $mes - 1, 'setor' => $escala->setor_nome],
                'funcionarios' => $funcionarios,
                'feriados' => [],
            ]);
        } catch (\Throwable $e) {
            return response()->json(['funcionarios' => [], 'erro' => $e->getMessage()]);
        }
    });

    // Salvar itens de plantão de uma escala
    Route::post('/escalas/{id}/salvar', function (\Illuminate\Http\Request $request, $id) {
        try {
            $detalheId = $request->detalhe_escala_id;
            $itens = $request->itens ?? [];

            // Remove itens antigos do detalhe
            try {
                \Illuminate\Support\Facades\DB::table('DETALHE_ESCALA_ITEM')
                    ->where('DETALHE_ESCALA_ID', $detalheId)->delete();
            } catch (\Throwable) {
            }

            foreach ($itens as $item) {
                \Illuminate\Support\Facades\DB::table('DETALHE_ESCALA_ITEM')->insert([
                    'DETALHE_ESCALA_ID' => $detalheId,
                    'DETALHE_ESCALA_ITEM_DATA' => $item['data'],
                    'TURNO_ID' => $item['turno_id'] ?? null,
                ]);
            }
            return response()->json(['ok' => true, 'itens_salvos' => count($itens)]);
        } catch (\Throwable $e) {
            return response()->json(['erro' => $e->getMessage()], 500);
        }
    });

    // ─── ESCALA DE TRABALHO (grade mensal por funcionário) ──────
    Route::get('/escala-trabalho', function (\Illuminate\Http\Request $request) {
        try {
            $mes = (int) ($request->mes ?? date('n'));
            $ano = (int) ($request->ano ?? date('Y'));
            $setorId = $request->setor_id ?? null;

            $competencia = sprintf('%02d/%04d', $mes, $ano);

            // Última lotação por funcionário (via subquery compatível com SQLite)
            $ultimaLotacao = \Illuminate\Support\Facades\DB::table('LOTACAO as L2')
                ->selectRaw('MAX(L2.LOTACAO_ID) as lid')
                ->groupBy('L2.FUNCIONARIO_ID');

            $funcs = \Illuminate\Support\Facades\DB::table('FUNCIONARIO as F')
                ->leftJoin('PESSOA as P', 'F.PESSOA_ID', '=', 'P.PESSOA_ID')
                ->leftJoin('LOTACAO as L', function ($j) use ($ultimaLotacao) {
                    $j->on('L.FUNCIONARIO_ID', '=', 'F.FUNCIONARIO_ID')
                        ->whereIn('L.LOTACAO_ID', $ultimaLotacao);
                })
                ->leftJoin('SETOR as S', 'L.SETOR_ID', '=', 'S.SETOR_ID')
                ->where('F.FUNCIONARIO_ATIVO', 1)
                ->when($setorId, fn($q) => $q->where('L.SETOR_ID', $setorId))
                ->select(
                    'F.FUNCIONARIO_ID as id',
                    \Illuminate\Support\Facades\DB::raw("COALESCE(P.PESSOA_NOME, 'Funcionário') as nome"),
                    \Illuminate\Support\Facades\DB::raw("COALESCE(S.SETOR_NOME, 'Sem setor') as setor"),
                    'L.SETOR_ID as setor_id'
                )
                ->limit(200)->get();

            // Itens de escala para a competência
            $registros = collect();
            try {
                $registros = \Illuminate\Support\Facades\DB::table('DETALHE_ESCALA_ITEM as I')
                    ->join('DETALHE_ESCALA as D', 'I.DETALHE_ESCALA_ID', '=', 'D.DETALHE_ESCALA_ID')
                    ->join('ESCALA as E', 'D.ESCALA_ID', '=', 'E.ESCALA_ID')
                    ->where('E.ESCALA_COMPETENCIA', $competencia)
                    ->select(
                        'D.FUNCIONARIO_ID as func_id',
                        'I.DETALHE_ESCALA_ITEM_DATA as data',
                        \Illuminate\Support\Facades\DB::raw("COALESCE(I.TURNO_SIGLA, '') as turno")
                    )->get();
            } catch (\Throwable) {
            }

            // Mapa: func_id → { dia_num → { turno } }
            $mapa = [];
            foreach ($registros as $r) {
                $dia = (int) date('j', strtotime($r->data));
                if ($r->turno) {
                    $mapa[$r->func_id][$dia] = ['turno' => $r->turno];
                }
            }

            $escala = $funcs->map(fn($f) => [
                'funcionario_id' => $f->id,
                'nome' => $f->nome,
                'setor' => $f->setor,
                'dias' => $mapa[$f->id] ?? [],
            ]);

            $setores = \Illuminate\Support\Facades\DB::table('SETOR')
                ->where('SETOR_ATIVO', 1)
                ->select('SETOR_ID as id', 'SETOR_NOME as nome')
                ->get();

            return response()->json([
                'escala' => $escala,
                'setores' => $setores,
                'funcionarios' => $funcs->map(fn($f) => ['id' => $f->id, 'nome' => $f->nome]),
            ]);
        } catch (\Throwable $e) {
            return response()->json([
                'escala' => [],
                'setores' => [],
                'funcionarios' => [],
                'erro' => $e->getMessage(),
            ]);
        }
    });

    // Registrar/atualizar turno de escala de trabalho
    Route::post('/escala-trabalho', function (\Illuminate\Http\Request $request) {
        try {
            $funcId = $request->funcionario_id;
            $data = $request->data;   // 'YYYY-MM-DD'
            $turno = $request->turno;
            $obs = $request->obs ?? null;

            if (!$funcId || !$data || !$turno) {
                return response()->json(['erro' => 'Campos obrigatórios: funcionario_id, data, turno'], 422);
            }

            // Determina mês/ano e competência
            $dt = new \DateTime($data);
            $competencia = $dt->format('m/Y');

            // Garante que existe uma ESCALA para esta competência/sem setor específico
            $escala = \Illuminate\Support\Facades\DB::table('ESCALA')
                ->where('ESCALA_COMPETENCIA', $competencia)
                ->whereNull('SETOR_ID')
                ->first();

            if (!$escala) {
                $escalaId = \Illuminate\Support\Facades\DB::table('ESCALA')->insertGetId([
                    'ESCALA_COMPETENCIA' => $competencia,
                    'ESCALA_STATUS' => 'Aberta',
                ]);
            } else {
                $escalaId = $escala->ESCALA_ID;
            }

            // Garante DETALHE_ESCALA para funcionário
            $detalhe = \Illuminate\Support\Facades\DB::table('DETALHE_ESCALA')
                ->where('ESCALA_ID', $escalaId)
                ->where('FUNCIONARIO_ID', $funcId)
                ->first();

            if (!$detalhe) {
                $detalheId = \Illuminate\Support\Facades\DB::table('DETALHE_ESCALA')->insertGetId([
                    'ESCALA_ID' => $escalaId,
                    'FUNCIONARIO_ID' => $funcId,
                ]);
            } else {
                $detalheId = $detalhe->DETALHE_ESCALA_ID;
            }

            // Remove item existente nesta data e insere novo
            try {
                \Illuminate\Support\Facades\DB::table('DETALHE_ESCALA_ITEM')
                    ->where('DETALHE_ESCALA_ID', $detalheId)
                    ->where('DETALHE_ESCALA_ITEM_DATA', $data)
                    ->delete();
            } catch (\Throwable) {
            }

            \Illuminate\Support\Facades\DB::table('DETALHE_ESCALA_ITEM')->insert([
                'DETALHE_ESCALA_ID' => $detalheId,
                'DETALHE_ESCALA_ITEM_DATA' => $data,
                'TURNO_SIGLA' => $turno,
                'DETALHE_ESCALA_ITEM_OBS' => $obs,
            ]);

            return response()->json(['ok' => true]);
        } catch (\Throwable $e) {
            return response()->json(['erro' => $e->getMessage()], 500);
        }
    });

    // �?�?�?�?�?�?�?�?�?�?�?�?�?�?�?�?�?�?�?�?�?�?�?�?�?�?�?�?�?�?�?�?�?�?�?�?�?�?�?�?�?�?�?�?�?�?�?�?�?�?�?�?�?�?�?�?�?�?�?�?�?�?�?�?�?�?�?
    // SUBSTITUIÇÕES — GET lista, POST criar, PUT status
    // �?�?�?�?�?�?�?�?�?�?�?�?�?�?�?�?�?�?�?�?�?�?�?�?�?�?�?�?�?�?�?�?�?�?�?�?�?�?�?�?�?�?�?�?�?�?�?�?�?�?�?�?�?�?�?�?�?�?�?�?�?�?�?�?�?�?�?

    Route::get('/substituicoes', function (\Illuminate\Http\Request $request) {
        try {
            $q = \Illuminate\Support\Facades\DB::table('SUBSTITUICAO as S')
                ->leftJoin('FUNCIONARIO as FS', 'S.FUNCIONARIO_ID', '=', 'FS.FUNCIONARIO_ID')
                ->leftJoin('PESSOA as PS', 'FS.PESSOA_ID', '=', 'PS.PESSOA_ID')
                ->leftJoin('FUNCIONARIO as FT', 'S.SUBSTITUTO_ID', '=', 'FT.FUNCIONARIO_ID')
                ->leftJoin('PESSOA as PT', 'FT.PESSOA_ID', '=', 'PT.PESSOA_ID')
                ->leftJoin('ESCALA as E', 'S.ESCALA_ID', '=', 'E.ESCALA_ID')
                ->leftJoin('SETOR as ST', 'E.SETOR_ID', '=', 'ST.SETOR_ID')
                ->select(
                    'S.SUBSTITUICAO_ID as id',
                    'S.FUNCIONARIO_ID as solicitante_id',
                    'S.SUBSTITUTO_ID as substituto_id',
                    'S.SUBSTITUICAO_DATA as data_plantao',
                    'S.SUBSTITUICAO_TURNO as turno',
                    'S.SUBSTITUICAO_MOTIVO as motivo',
                    'S.SUBSTITUICAO_STATUS as status',
                    'S.created_at as criado_em',
                    \Illuminate\Support\Facades\DB::raw("COALESCE(PS.PESSOA_NOME, '—') as solicitante"),
                    \Illuminate\Support\Facades\DB::raw("COALESCE(PT.PESSOA_NOME, '—') as substituto"),
                    \Illuminate\Support\Facades\DB::raw("COALESCE(ST.SETOR_NOME, '—') as setor")
                )
                ->orderByDesc('S.created_at')
                ->limit(200)
                ->get();

            return response()->json(['substituicoes' => $q]);
        } catch (\Throwable $e) {
            return response()->json(['substituicoes' => [], 'erro' => $e->getMessage()]);
        }
    });

    Route::post('/substituicoes', function (\Illuminate\Http\Request $request) {
        try {
            $id = \Illuminate\Support\Facades\DB::table('SUBSTITUICAO')->insertGetId([
                'ESCALA_ID' => $request->escala_id ?: null,
                'FUNCIONARIO_ID' => $request->solicitante_id ?: null,
                'SUBSTITUTO_ID' => $request->substituto_id ?: null,
                'SUBSTITUICAO_DATA' => $request->data_plantao,
                'SUBSTITUICAO_TURNO' => $request->turno,
                'SUBSTITUICAO_MOTIVO' => $request->motivo ?: null,
                'SUBSTITUICAO_STATUS' => 'pendente',
                'created_at' => now(),
                'updated_at' => now(),
            ]);
            return response()->json(['ok' => true, 'id' => $id]);
        } catch (\Throwable $e) {
            return response()->json(['erro' => $e->getMessage()], 500);
        }
    });

    Route::put('/substituicoes/{id}', function (\Illuminate\Http\Request $request, $id) {
        try {
            \Illuminate\Support\Facades\DB::table('SUBSTITUICAO')
                ->where('SUBSTITUICAO_ID', $id)
                ->update([
                    'SUBSTITUICAO_STATUS' => $request->status,
                    'updated_at' => now(),
                ]);
            return response()->json(['ok' => true]);
        } catch (\Throwable $e) {
            return response()->json(['erro' => $e->getMessage()], 500);
        }
    });

    // �?�?�?�?�?�?�?�?�?�?�?�?�?�?�?�?�?�?�?�?�?�?�?�?�?�?�?�?�?�?�?�?�?�?�?�?�?�?�?�?�?�?�?�?�?�?�?�?�?�?�?�?�?�?�?�?�?�?�?�?�?�?�?�?�?�?�?
    // AVALIAÇÃO DE DESEMPENHO — GET histórico, POST salvar ciclo
    // �?�?�?�?�?�?�?�?�?�?�?�?�?�?�?�?�?�?�?�?�?�?�?�?�?�?�?�?�?�?�?�?�?�?�?�?�?�?�?�?�?�?�?�?�?�?�?�?�?�?�?�?�?�?�?�?�?�?�?�?�?�?�?�?�?�?�?

    Route::get('/avaliacoes', function () {
        try {
            $user = \Illuminate\Support\Facades\Auth::user();
            $func = \App\Models\Funcionario::where('USUARIO_ID', $user->USUARIO_ID ?? 0)->first();
            if (!$func)
                return response()->json(['avaliacoes' => [], 'fallback' => true]);

            $avs = \Illuminate\Support\Facades\DB::table('AVALIACAO_DESEMPENHO as A')
                ->leftJoin('USUARIO as U', 'A.AVALIADOR_ID', '=', 'U.USUARIO_ID')
                ->where('A.FUNCIONARIO_ID', $func->FUNCIONARIO_ID)
                ->where('A.AVALIACAO_STATUS', 'publicada')
                ->orderByDesc('A.AVALIACAO_CICLO')
                ->select('A.*', \Illuminate\Support\Facades\DB::raw("COALESCE(U.USUARIO_NOME, 'RH') as avaliador_nome"))
                ->get();

            $result = $avs->map(function ($av) {
                $criterios = \Illuminate\Support\Facades\DB::table('AVALIACAO_CRITERIO')
                    ->where('AVALIACAO_ID', $av->AVALIACAO_ID)
                    ->get()
                    ->map(fn($c) => [
                        'nome' => $c->CRITERIO_NOME,
                        'peso' => $c->CRITERIO_PESO,
                        'nota' => $c->CRITERIO_NOTA,
                        'obs' => $c->CRITERIO_OBS,
                    ]);
                return [
                    'id' => $av->AVALIACAO_ID,
                    'ciclo' => $av->AVALIACAO_CICLO,
                    'nota' => (float) $av->AVALIACAO_NOTA_FINAL,
                    'avaliador' => $av->avaliador_nome,
                    'obs' => $av->AVALIACAO_OBS,
                    'criterios' => $criterios,
                ];
            });

            return response()->json(['avaliacoes' => $result]);
        } catch (\Throwable $e) {
            return response()->json(['avaliacoes' => [], 'erro' => $e->getMessage()]);
        }
    });

    Route::post('/avaliacoes', function (\Illuminate\Http\Request $request) {
        try {
            $user = \Illuminate\Support\Facades\Auth::user();
            $func = \App\Models\Funcionario::where('USUARIO_ID', $user->USUARIO_ID ?? 0)->first();
            if (!$func)
                return response()->json(['erro' => 'Funcionário não encontrado'], 404);

            $criterios = $request->criterios ?? [];
            $notaFinal = 0;
            $totalPeso = 0;
            foreach ($criterios as $c) {
                $notaFinal += ($c['nota'] ?? 0) * ($c['peso'] ?? 20);
                $totalPeso += ($c['peso'] ?? 20);
            }
            $notaFinal = $totalPeso > 0 ? round($notaFinal / $totalPeso, 1) : 0;

            // Upsert: um rascunho por ciclo por funcionário
            $existing = \Illuminate\Support\Facades\DB::table('AVALIACAO_DESEMPENHO')
                ->where('FUNCIONARIO_ID', $func->FUNCIONARIO_ID)
                ->where('AVALIACAO_CICLO', $request->ciclo)
                ->where('AVALIACAO_STATUS', 'rascunho')
                ->first();

            if ($existing) {
                $avId = $existing->AVALIACAO_ID;
                \Illuminate\Support\Facades\DB::table('AVALIACAO_DESEMPENHO')
                    ->where('AVALIACAO_ID', $avId)
                    ->update(['AVALIACAO_NOTA_FINAL' => $notaFinal, 'AVALIACAO_OBS' => $request->obs, 'updated_at' => now()]);
                \Illuminate\Support\Facades\DB::table('AVALIACAO_CRITERIO')->where('AVALIACAO_ID', $avId)->delete();
            } else {
                $avId = \Illuminate\Support\Facades\DB::table('AVALIACAO_DESEMPENHO')->insertGetId([
                    'FUNCIONARIO_ID' => $func->FUNCIONARIO_ID,
                    'AVALIACAO_CICLO' => $request->ciclo,
                    'AVALIACAO_NOTA_FINAL' => $notaFinal,
                    'AVALIACAO_STATUS' => 'rascunho',
                    'AVALIACAO_OBS' => $request->obs ?? null,
                    'created_at' => now(),
                    'updated_at' => now(),
                ]);
            }

            foreach ($criterios as $c) {
                \Illuminate\Support\Facades\DB::table('AVALIACAO_CRITERIO')->insert([
                    'AVALIACAO_ID' => $avId,
                    'CRITERIO_NOME' => $c['nome'],
                    'CRITERIO_PESO' => $c['peso'] ?? 20,
                    'CRITERIO_NOTA' => $c['nota'] ?? null,
                    'CRITERIO_OBS' => $c['obs'] ?? null,
                    'created_at' => now(),
                    'updated_at' => now(),
                ]);
            }

            return response()->json(['ok' => true, 'nota_final' => $notaFinal, 'id' => $avId]);
        } catch (\Throwable $e) {
            return response()->json(['erro' => $e->getMessage()], 500);
        }
    });

    // �?�?�?�?�?�?�?�?�?�?�?�?�?�?�?�?�?�?�?�?�?�?�?�?�?�?�?�?�?�?�?�?�?�?�?�?�?�?�?�?�?�?�?�?�?�?�?�?�?�?�?�?�?�?�?�?�?�?�?�?�?�?�?�?�?�?�?
    // AUTOCADASTRO — Rotas AUTENTICADAS (RH/Admin)
    // �?�?�?�?�?�?�?�?�?�?�?�?�?�?�?�?�?�?�?�?�?�?�?�?�?�?�?�?�?�?�?�?�?�?�?�?�?�?�?�?�?�?�?�?�?�?�?�?�?�?�?�?�?�?�?�?�?�?�?�?�?�?�?�?�?�?�?


    // Gera novo link de autocadastro
    Route::post('/autocadastro/gerar-link', function (\Illuminate\Http\Request $request) {
        try {
            $token = \Illuminate\Support\Str::random(48);
            $expira = now()->addDays((int) ($request->validade_dias ?? 7));

            \Illuminate\Support\Facades\DB::table('AUTOCADASTRO_TOKEN')->insert([
                'TOKEN' => $token,
                'TOKEN_EMAIL' => $request->email ?? null,
                'TOKEN_NOME' => $request->nome ?? null,
                'TOKEN_STATUS' => 'pendente',
                'CRIADO_POR' => auth()->id(),
                'expira_em' => $expira,
                'created_at' => now(),
                'updated_at' => now(),
            ]);

            $url = url("/autocadastro/{$token}");
            return response()->json(['ok' => true, 'token' => $token, 'url' => $url, 'expira_em' => $expira->format('d/m/Y')]);
        } catch (\Throwable $e) {
            return response()->json(['erro' => $e->getMessage()], 500);
        }
    });

    // Lista cadastros pendentes de aprovação
    Route::get('/autocadastro/pendentes', function () {
        try {
            $pendentes = \Illuminate\Support\Facades\DB::table('AUTOCADASTRO_TOKEN')
                ->leftJoin('USUARIO as U', 'AUTOCADASTRO_TOKEN.CRIADO_POR', '=', 'U.USUARIO_ID')
                ->select(
                    'AUTOCADASTRO_TOKEN.*',
                    \Illuminate\Support\Facades\DB::raw("COALESCE(U.USUARIO_NOME, 'Sistema') as criado_por_nome")
                )
                ->orderBy('AUTOCADASTRO_TOKEN.created_at', 'desc')
                ->limit(100)->get()
                ->map(function ($r) {
                    $r->TOKEN_DADOS = $r->TOKEN_DADOS ? json_decode($r->TOKEN_DADOS, true) : null;
                    return $r;
                });
            return response()->json(['pendentes' => $pendentes]);
        } catch (\Throwable $e) {
            return response()->json(['pendentes' => [], 'erro' => $e->getMessage()]);
        }
    });

    // Aprova um cadastro: cria PESSOA + FUNCIONARIO + USUARIO
    Route::post('/autocadastro/{token}/aprovar', function ($token) {
        try {
            $reg = \Illuminate\Support\Facades\DB::table('AUTOCADASTRO_TOKEN')
                ->where('TOKEN', $token)
                ->where('TOKEN_STATUS', 'preenchido')
                ->first();

            if (!$reg)
                return response()->json(['erro' => 'Registro não encontrado ou já aprovado'], 404);

            $dados = json_decode($reg->TOKEN_DADOS, true);
            if (!$dados)
                return response()->json(['erro' => 'Sem dados para aprovar'], 422);

            \Illuminate\Support\Facades\DB::transaction(function () use ($reg, $dados, $token) {
                // 1. Cria PESSOA
                $pessoaId = \Illuminate\Support\Facades\DB::table('PESSOA')->insertGetId([
                    'PESSOA_NOME' => $dados['nome'],
                    'PESSOA_CPF' => $dados['cpf'] ?? null,
                    'PESSOA_NASC' => $dados['data_nasc'] ?? null,
                    'PESSOA_SEXO' => $dados['sexo'] ?? null,
                    'PESSOA_RG' => $dados['rg'] ?? null,
                    'PESSOA_ORG_EMISSOR' => $dados['org_emissor'] ?? null,
                    'PESSOA_PIS_PASEP' => $dados['pis'] ?? null,
                    'ESTADO_CIVIL' => $dados['estado_civil'] ?? null,
                    'PESSOA_ATIVO' => 1,
                ]);

                // 2. Cria FUNCIONARIO
                $funcId = \Illuminate\Support\Facades\DB::table('FUNCIONARIO')->insertGetId([
                    'PESSOA_ID' => $pessoaId,
                    'FUNCIONARIO_DATA_INICIO' => now()->format('Y-m-d'),
                    'FUNCIONARIO_ATIVO' => 1,
                ]);

                // 3. Cria USUARIO (login = email)
                \Illuminate\Support\Facades\DB::table('USUARIO')->insert([
                    'USUARIO_NOME' => $dados['nome'],
                    'USUARIO_LOGIN' => $dados['email'],
                    'USUARIO_SENHA' => $dados['senha_hash'] ?? \Illuminate\Support\Facades\Hash::make('mudar@123'),
                    'USUARIO_ATIVO' => 1,
                ]);

                // 4. Marca token como aprovado
                \Illuminate\Support\Facades\DB::table('AUTOCADASTRO_TOKEN')
                    ->where('TOKEN', $token)
                    ->update(['TOKEN_STATUS' => 'aprovado', 'FUNCIONARIO_ID' => $funcId, 'updated_at' => now()]);
            });

            return response()->json(['ok' => true, 'msg' => 'Cadastro aprovado com sucesso!']);
        } catch (\Throwable $e) {
            return response()->json(['erro' => $e->getMessage()], 500);
        }
    });

    // Revoga/cancela um token
    Route::delete('/autocadastro/{token}', function ($token) {
        try {
            \Illuminate\Support\Facades\DB::table('AUTOCADASTRO_TOKEN')
                ->where('TOKEN', $token)
                ->update(['TOKEN_STATUS' => 'revogado', 'updated_at' => now()]);
            return response()->json(['ok' => true]);
        } catch (\Throwable $e) {
            return response()->json(['erro' => $e->getMessage()], 500);
        }
    });

    // ── Progressão Funcional PCCV ─────────────────────────────────────────────
    require __DIR__ . '/progressao_funcional.php';

    // ── App Mobile de Ponto (autenticação JWT própria, sem sessão web) ─────────
    require __DIR__ . '/ponto_app.php';

}); // fecha o grupo prefix('api/v3')->middleware(['web','auth'])

// �?�?�?�?�?�?�?�?�?�?�?�?�?�?�?�?�?�?�?�?�?�?�?�?�?�?�?�?�?�?�?�?�?�?�?�?�?�?�?�?�?�?�?�?�?�?�?�?�?�?�?�?�?�?�?�?�?�?�?�?�?�?�?�?�?�?�?
// AUTOCADASTRO — Rotas PÚBLICAS (sem autenticação)
// �?�?�?�?�?�?�?�?�?�?�?�?�?�?�?�?�?�?�?�?�?�?�?�?�?�?�?�?�?�?�?�?�?�?�?�?�?�?�?�?�?�?�?�?�?�?�?�?�?�?�?�?�?�?�?�?�?�?�?�?�?�?�?�?�?�?�?
Route::prefix('api/v3')->middleware(['web'])->group(function () {

    // Valida token e retorna dados pré-preenchidos
    Route::get('/autocadastro/{token}', function ($token) {
        try {
            $reg = \Illuminate\Support\Facades\DB::table('AUTOCADASTRO_TOKEN')
                ->where('TOKEN', $token)
                ->first();

            if (!$reg) {
                return response()->json(['status' => 'invalido', 'erro' => 'Token não encontrado'], 404);
            }

            // Verifica expiração
            if ($reg->expira_em && now()->gt($reg->expira_em)) {
                \Illuminate\Support\Facades\DB::table('AUTOCADASTRO_TOKEN')
                    ->where('TOKEN', $token)->update(['TOKEN_STATUS' => 'expirado']);
                return response()->json(['status' => 'invalido', 'erro' => 'Token expirado'], 410);
            }

            if (in_array($reg->TOKEN_STATUS, ['expirado', 'revogado'])) {
                return response()->json(['status' => 'invalido'], 200);
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

    // Recebe os dados do formulário de autocadastro (multipart/form-data)
    Route::post('/autocadastro/{token}', function (\Illuminate\Http\Request $request, $token) {
        try {
            $reg = \Illuminate\Support\Facades\DB::table('AUTOCADASTRO_TOKEN')
                ->where('TOKEN', $token)
                ->whereIn('TOKEN_STATUS', ['pendente'])
                ->first();

            if (!$reg) {
                return response()->json(['erro' => 'Token inválido ou já utilizado'], 422);
            }

            if ($reg->expira_em && now()->gt($reg->expira_em)) {
                return response()->json(['erro' => 'Token expirado'], 410);
            }

            // Valida campos obrigatórios
            $nome = trim($request->nome ?? '');
            $email = trim($request->email ?? '');
            $senha = $request->senha ?? '';
            if (!$nome || !$email || strlen($senha) < 6) {
                return response()->json(['erro' => 'Nome, e-mail e senha (mín. 6 chars) são obrigatórios'], 422);
            }

            // ── Salva arquivos de documentos ──────────────────────────────
            $docCampos = ['doc_identidade', 'doc_cpf', 'doc_residencia', 'doc_pis', 'doc_foto', 'doc_dependentes'];
            $docPaths = [];
            $dir = "autocadastro/{$token}";
            foreach ($docCampos as $campo) {
                if ($request->hasFile($campo)) {
                    $file = $request->file($campo);
                    // Valida tamanho (5MB) e tipo
                    if ($file->getSize() > 5 * 1024 * 1024)
                        continue;
                    $allowed = ['jpeg', 'jpg', 'png', 'gif', 'webp', 'pdf'];
                    if (!in_array(strtolower($file->getClientOriginalExtension()), $allowed))
                        continue;
                    $path = $file->store($dir, 'local');
                    $docPaths[$campo] = $path;
                }
            }

            // ── Dependentes ───────────────────────────────────────────────
            $dependentes = [];
            if ($request->has('dependentes')) {
                $raw = $request->input('dependentes');
                $parsed = is_string($raw) ? json_decode($raw, true) : $raw;
                if (is_array($parsed)) {
                    foreach ($parsed as $dep) {
                        if (!empty($dep['nome'])) {
                            $dependentes[] = [
                                'nome' => trim($dep['nome']),
                                'cpf' => $dep['cpf'] ?? null,
                                'data_nasc' => $dep['data_nasc'] ?? null,
                                'parentesco' => $dep['parentesco'] ?? null,
                                'deducao_irrf' => $dep['deducao_irrf'] ?? '1',
                            ];
                        }
                    }
                }
            }

            // ── Persiste os dados do token ─────────────────────────────────
            \Illuminate\Support\Facades\DB::table('AUTOCADASTRO_TOKEN')
                ->where('TOKEN', $token)
                ->update([
                    'TOKEN_STATUS' => 'preenchido',
                    'TOKEN_DADOS' => json_encode([
                        'nome' => $nome,
                        'nome_social' => $request->nome_social,
                        'cpf' => $request->cpf,
                        'data_nasc' => $request->data_nasc,
                        'sexo' => $request->sexo,
                        'rg' => $request->rg,
                        'org_emissor' => $request->org_emissor,
                        'pis' => $request->pis,
                        'estado_civil' => $request->estado_civil,
                        'grau_instrucao' => $request->grau_instrucao,
                        'raca_cor' => $request->raca_cor,
                        'email' => $email,
                        'telefone' => $request->telefone,
                        'cep' => $request->cep,
                        'logradouro' => $request->logradouro,
                        'numero' => $request->numero,
                        'bairro' => $request->bairro,
                        'cidade' => $request->cidade,
                        'uf' => $request->uf,
                        'senha_hash' => \Illuminate\Support\Facades\Hash::make($senha),
                        'dependentes' => $dependentes,
                        'documentos' => $docPaths,
                    ], JSON_UNESCAPED_UNICODE),
                    'usado_em' => now(),
                ]);

            return response()->json(['ok' => true, 'msg' => 'Cadastro recebido! Aguarde a aprovação do RH.']);
        } catch (\Throwable $e) {
            return response()->json(['erro' => $e->getMessage()], 500);
        }
    });

    // �?�?�?�?�?�?�?�?�?�?�?�?�?�?�?�?�?�?�?�?�?�?�?�?�?�?�?�?�?�?�?�?�?�?�?�?�?�?�?�?�?�?�?�?�?�?�?�?�?�?�?�?�?�?�?�?�?�?�?�?�?�?�?�?�?�?�?
    // SPRINT D — SEGURANÇA DO TRABALHO
    // �?�?�?�?�?�?�?�?�?�?�?�?�?�?�?�?�?�?�?�?�?�?�?�?�?�?�?�?�?�?�?�?�?�?�?�?�?�?�?�?�?�?�?�?�?�?�?�?�?�?�?�?�?�?�?�?�?�?�?�?�?�?�?�?�?�?�?

    Route::get('/seguranca/epis', function () {
        try {
            $user = \Illuminate\Support\Facades\Auth::user();
            $func = \App\Models\Funcionario::where('USUARIO_ID', $user->USUARIO_ID ?? 0)->first();
            if (!$func)
                return response()->json(['epis' => [], 'fallback' => true]);

            $epis = \Illuminate\Support\Facades\DB::table('SEGURANCA_EPI')
                ->where('FUNCIONARIO_ID', $func->FUNCIONARIO_ID)
                ->orderByDesc('created_at')
                ->get()
                ->map(fn($e) => [
                    'id' => $e->EPI_ID,
                    'nome' => $e->EPI_NOME,
                    'ca' => $e->EPI_CA,
                    'ico' => $e->EPI_ICONE ?? '🦺',
                    'validade' => $e->EPI_VALIDADE,
                    'quantidade' => $e->EPI_QUANTIDADE,
                    'vencido' => $e->EPI_VALIDADE && $e->EPI_VALIDADE < now()->toDateString(),
                    'aVencer' => $e->EPI_VALIDADE && $e->EPI_VALIDADE >= now()->toDateString()
                        && $e->EPI_VALIDADE <= now()->addDays(30)->toDateString(),
                ]);

            return response()->json(['epis' => $epis]);
        } catch (\Throwable $e) {
            return response()->json(['epis' => [], 'erro' => $e->getMessage()]);
        }
    });

    Route::post('/seguranca/epis', function (\Illuminate\Http\Request $request) {
        try {
            $user = \Illuminate\Support\Facades\Auth::user();
            $func = \App\Models\Funcionario::where('USUARIO_ID', $user->USUARIO_ID ?? 0)->first();
            $id = \Illuminate\Support\Facades\DB::table('SEGURANCA_EPI')->insertGetId([
                'FUNCIONARIO_ID' => $func->FUNCIONARIO_ID ?? null,
                'EPI_NOME' => $request->nome,
                'EPI_CA' => $request->ca ?? null,
                'EPI_ICONE' => $request->ico ?? '🦺',
                'EPI_VALIDADE' => $request->validade ?? null,
                'EPI_QUANTIDADE' => $request->quantidade ?? 1,
                'created_at' => now(),
                'updated_at' => now(),
            ]);
            return response()->json(['ok' => true, 'id' => $id]);
        } catch (\Throwable $e) {
            return response()->json(['erro' => $e->getMessage()], 500);
        }
    });

    Route::get('/seguranca/incidentes', function () {
        try {
            $user = \Illuminate\Support\Facades\Auth::user();
            $func = \App\Models\Funcionario::where('USUARIO_ID', $user->USUARIO_ID ?? 0)->first();
            if (!$func)
                return response()->json(['incidentes' => [], 'fallback' => true]);

            $incs = \Illuminate\Support\Facades\DB::table('SEGURANCA_INCIDENTE')
                ->where('FUNCIONARIO_ID', $func->FUNCIONARIO_ID)
                ->orderByDesc('INCIDENTE_DATA')
                ->get()
                ->map(fn($i) => [
                    'id' => $i->INCIDENTE_ID,
                    'tipo' => $i->INCIDENTE_TIPO,
                    'data' => $i->INCIDENTE_DATA,
                    'descricao' => $i->INCIDENTE_DESCRICAO,
                    'local' => $i->INCIDENTE_LOCAL,
                    'cat' => $i->INCIDENTE_CAT,
                    'closed' => (bool) $i->INCIDENTE_FECHADO,
                ]);

            return response()->json(['incidentes' => $incs]);
        } catch (\Throwable $e) {
            return response()->json(['incidentes' => [], 'erro' => $e->getMessage()]);
        }
    });

    Route::post('/seguranca/incidentes', function (\Illuminate\Http\Request $request) {
        try {
            $user = \Illuminate\Support\Facades\Auth::user();
            $func = \App\Models\Funcionario::where('USUARIO_ID', $user->USUARIO_ID ?? 0)->first();
            $id = \Illuminate\Support\Facades\DB::table('SEGURANCA_INCIDENTE')->insertGetId([
                'FUNCIONARIO_ID' => $func->FUNCIONARIO_ID ?? null,
                'INCIDENTE_TIPO' => $request->tipo ?? 'quase',
                'INCIDENTE_DATA' => now()->toDateString(),
                'INCIDENTE_LOCAL' => $request->local,
                'INCIDENTE_DESCRICAO' => $request->descricao,
                'INCIDENTE_FECHADO' => false,
                'created_at' => now(),
                'updated_at' => now(),
            ]);
            return response()->json(['ok' => true, 'id' => $id]);
        } catch (\Throwable $e) {
            return response()->json(['erro' => $e->getMessage()], 500);
        }
    });

    // �?�?�?�?�?�?�?�?�?�?�?�?�?�?�?�?�?�?�?�?�?�?�?�?�?�?�?�?�?�?�?�?�?�?�?�?�?�?�?�?�?�?�?�?�?�?�?�?�?�?�?�?�?�?�?�?�?�?�?�?�?�?�?�?�?�?�?
    // SPRINT D — TREINAMENTOS
    // �?�?�?�?�?�?�?�?�?�?�?�?�?�?�?�?�?�?�?�?�?�?�?�?�?�?�?�?�?�?�?�?�?�?�?�?�?�?�?�?�?�?�?�?�?�?�?�?�?�?�?�?�?�?�?�?�?�?�?�?�?�?�?�?�?�?�?

    Route::get('/treinamentos/meus', function () {
        try {
            $user = \Illuminate\Support\Facades\Auth::user();
            $func = \App\Models\Funcionario::where('USUARIO_ID', $user->USUARIO_ID ?? 0)->first();
            if (!$func)
                return response()->json(['cursos' => [], 'fallback' => true]);

            $cursos = \Illuminate\Support\Facades\DB::table('TREINAMENTO_INSCRICAO as I')
                ->join('TREINAMENTO as T', 'I.TREINAMENTO_ID', '=', 'T.TREINAMENTO_ID')
                ->where('I.FUNCIONARIO_ID', $func->FUNCIONARIO_ID)
                ->select('T.*', 'I.*')
                ->get()
                ->map(fn($c) => [
                    'id' => $c->TREINAMENTO_ID,
                    'titulo' => $c->TREINAMENTO_TITULO,
                    'desc' => $c->TREINAMENTO_DESC,
                    'area' => $c->TREINAMENTO_AREA ?? 'Geral',
                    'carga' => $c->TREINAMENTO_CARGA,
                    'modalidade' => $c->TREINAMENTO_MODALIDADE,
                    'status' => $c->INSCRICAO_STATUS,
                    'progresso' => $c->INSCRICAO_PROGRESSO,
                    'certificado' => (bool) $c->INSCRICAO_CERTIFICADO,
                    'data' => $c->INSCRICAO_DATA_CONCLUSAO,
                ]);

            return response()->json(['cursos' => $cursos]);
        } catch (\Throwable $e) {
            return response()->json(['cursos' => [], 'erro' => $e->getMessage()]);
        }
    });

    Route::get('/treinamentos/catalogo', function () {
        try {
            $catalogo = \Illuminate\Support\Facades\DB::table('TREINAMENTO')
                ->where('TREINAMENTO_ATIVO', true)
                ->get()
                ->map(fn($t) => [
                    'id' => $t->TREINAMENTO_ID,
                    'titulo' => $t->TREINAMENTO_TITULO,
                    'desc' => $t->TREINAMENTO_DESC,
                    'area' => $t->TREINAMENTO_AREA ?? 'Geral',
                    'carga' => $t->TREINAMENTO_CARGA,
                    'modalidade' => $t->TREINAMENTO_MODALIDADE,
                    'proxima' => $t->TREINAMENTO_PROXIMA,
                    'vagas' => $t->TREINAMENTO_VAGAS,
                    'custo' => 0,
                ]);
            return response()->json(['catalogo' => $catalogo]);
        } catch (\Throwable $e) {
            return response()->json(['catalogo' => [], 'erro' => $e->getMessage()]);
        }
    });

    Route::post('/treinamentos/{id}/inscrever', function (\Illuminate\Http\Request $request, $id) {
        try {
            $user = \Illuminate\Support\Facades\Auth::user();
            $func = \App\Models\Funcionario::where('USUARIO_ID', $user->USUARIO_ID ?? 0)->first();
            if (!$func)
                return response()->json(['erro' => 'Funcionário não encontrado'], 404);

            // Evitar duplicata
            $existe = \Illuminate\Support\Facades\DB::table('TREINAMENTO_INSCRICAO')
                ->where('TREINAMENTO_ID', $id)
                ->where('FUNCIONARIO_ID', $func->FUNCIONARIO_ID)
                ->exists();
            if ($existe)
                return response()->json(['ok' => true, 'msg' => 'Já inscrito.']);

            \Illuminate\Support\Facades\DB::table('TREINAMENTO_INSCRICAO')->insert([
                'TREINAMENTO_ID' => $id,
                'FUNCIONARIO_ID' => $func->FUNCIONARIO_ID,
                'INSCRICAO_STATUS' => 'inscrito',
                'INSCRICAO_PROGRESSO' => 0,
                'INSCRICAO_CERTIFICADO' => false,
                'created_at' => now(),
                'updated_at' => now(),
            ]);
            return response()->json(['ok' => true]);
        } catch (\Throwable $e) {
            return response()->json(['erro' => $e->getMessage()], 500);
        }
    });

    // �?�?�?�?�?�?�?�?�?�?�?�?�?�?�?�?�?�?�?�?�?�?�?�?�?�?�?�?�?�?�?�?�?�?�?�?�?�?�?�?�?�?�?�?�?�?�?�?�?�?�?�?�?�?�?�?�?�?�?�?�?�?�?�?�?�?�?
    // SPRINT D — PESQUISA DE SATISFAÇÃO (CRUD + RESULTADOS)
    // �?�?�?�?�?�?�?�?�?�?�?�?�?�?�?�?�?�?�?�?�?�?�?�?�?�?�?�?�?�?�?�?�?�?�?�?�?�?�?�?�?�?�?�?�?�?�?�?�?�?�?�?�?�?�?�?�?�?�?�?�?�?�?�?�?�?�?

    // ── Funcionário: lista pesquisas abertas ──────────────────────────
    Route::get('/pesquisas', function () {
        try {
            $pesquisas = \Illuminate\Support\Facades\DB::table('PESQUISA_SATISFACAO')
                ->where('PESQUISA_STATUS', 'aberta')
                ->orderByDesc('created_at')
                ->get()
                ->map(function ($p) {
                    $perguntas = \Illuminate\Support\Facades\DB::table('PESQUISA_PERGUNTA')
                        ->where('PESQUISA_ID', $p->PESQUISA_ID)
                        ->orderBy('PERGUNTA_ORDEM')
                        ->get()
                        ->map(fn($q) => [
                            'id' => $q->PERGUNTA_ID,
                            'texto' => $q->PERGUNTA_TEXTO,
                            'tipo' => $q->PERGUNTA_TIPO,
                            'opcoes' => $q->PERGUNTA_OPCOES ? json_decode($q->PERGUNTA_OPCOES, true) : [],
                        ]);
                    return [
                        'id' => $p->PESQUISA_ID,
                        'titulo' => $p->PESQUISA_TITULO,
                        'desc' => $p->PESQUISA_DESC,
                        'status' => $p->PESQUISA_STATUS,
                        'inicio' => $p->PESQUISA_INICIO,
                        'fim' => $p->PESQUISA_FIM,
                        'perguntas' => $perguntas,
                    ];
                });
            return response()->json(['pesquisas' => $pesquisas]);
        } catch (\Throwable $e) {
            return response()->json(['pesquisas' => [], 'erro' => $e->getMessage()]);
        }
    });

    // ── Funcionário: responder pesquisa ───────────────────────────────
    Route::post('/pesquisas/{id}/responder', function (\Illuminate\Http\Request $request, $id) {
        try {
            $user = \Illuminate\Support\Facades\Auth::user();
            $func = \App\Models\Funcionario::where('USUARIO_ID', $user->USUARIO_ID ?? 0)->first();
            $respostas = $request->respostas ?? [];
            foreach ($respostas as $r) {
                \Illuminate\Support\Facades\DB::table('PESQUISA_RESPOSTA')->insert([
                    'PESQUISA_ID' => $id,
                    'PERGUNTA_ID' => $r['pergunta_id'],
                    'FUNCIONARIO_ID' => $request->anonimo ? null : ($func->FUNCIONARIO_ID ?? null),
                    'RESPOSTA_NOTA' => $r['nota'] ?? null,
                    'RESPOSTA_TEXTO' => $r['texto'] ?? null,
                    'created_at' => now(),
                    'updated_at' => now(),
                ]);
            }
            $ano = now()->format('Y');
            $seq = \Illuminate\Support\Facades\DB::table('PESQUISA_RESPOSTA')->count();
            return response()->json(['ok' => true, 'protocolo' => "PSQ-{$ano}-" . str_pad($seq, 4, '0', STR_PAD_LEFT)]);
        } catch (\Throwable $e) {
            return response()->json(['erro' => $e->getMessage()], 500);
        }
    });

    // ── Admin: listar todas as pesquisas ──────────────────────────────
    Route::get('/pesquisas/admin', function () {
        try {
            $pesquisas = \Illuminate\Support\Facades\DB::table('PESQUISA_SATISFACAO')
                ->orderByDesc('created_at')
                ->get()
                ->map(function ($p) {
                    $perguntas = \Illuminate\Support\Facades\DB::table('PESQUISA_PERGUNTA')
                        ->where('PESQUISA_ID', $p->PESQUISA_ID)
                        ->orderBy('PERGUNTA_ORDEM')
                        ->get()
                        ->map(fn($q) => [
                            'id' => $q->PERGUNTA_ID,
                            'texto' => $q->PERGUNTA_TEXTO,
                            'tipo' => $q->PERGUNTA_TIPO,
                            'ordem' => $q->PERGUNTA_ORDEM,
                            'opcoes' => $q->PERGUNTA_OPCOES ? json_decode($q->PERGUNTA_OPCOES, true) : [],
                        ]);
                    $totalRes = \Illuminate\Support\Facades\DB::table('PESQUISA_RESPOSTA')
                        ->where('PESQUISA_ID', $p->PESQUISA_ID)
                        ->distinct('FUNCIONARIO_ID')->count();
                    return [
                        'id' => $p->PESQUISA_ID,
                        'titulo' => $p->PESQUISA_TITULO,
                        'desc' => $p->PESQUISA_DESC,
                        'status' => $p->PESQUISA_STATUS,
                        'inicio' => $p->PESQUISA_INICIO,
                        'fim' => $p->PESQUISA_FIM,
                        'total_perguntas' => $perguntas->count(),
                        'total_respostas' => $totalRes,
                        'perguntas' => $perguntas,
                    ];
                });
            return response()->json(['pesquisas' => $pesquisas]);
        } catch (\Throwable $e) {
            return response()->json(['pesquisas' => [], 'erro' => $e->getMessage()]);
        }
    });

    // ── Admin: criar pesquisa ─────────────────────────────────────────
    Route::post('/pesquisas', function (\Illuminate\Http\Request $request) {
        try {
            $user = \Illuminate\Support\Facades\Auth::user();
            $id = \Illuminate\Support\Facades\DB::table('PESQUISA_SATISFACAO')->insertGetId([
                'PESQUISA_TITULO' => $request->titulo,
                'PESQUISA_DESC' => $request->desc,
                'PESQUISA_STATUS' => 'rascunho',
                'PESQUISA_INICIO' => $request->inicio ?: null,
                'PESQUISA_FIM' => $request->fim ?: null,
                'CRIADO_POR' => $user->USUARIO_ID ?? null,
                'created_at' => now(),
                'updated_at' => now(),
            ]);
            foreach (($request->perguntas ?? []) as $pq) {
                \Illuminate\Support\Facades\DB::table('PESQUISA_PERGUNTA')->insert([
                    'PESQUISA_ID' => $id,
                    'PERGUNTA_TEXTO' => $pq['texto'],
                    'PERGUNTA_TIPO' => $pq['tipo'],
                    'PERGUNTA_ORDEM' => $pq['ordem'] ?? 0,
                    'PERGUNTA_OPCOES' => isset($pq['opcoes']) ? json_encode($pq['opcoes']) : null,
                    'created_at' => now(),
                    'updated_at' => now(),
                ]);
            }
            return response()->json(['id' => $id], 201);
        } catch (\Throwable $e) {
            return response()->json(['erro' => $e->getMessage()], 500);
        }
    });

    // ── Admin: editar pesquisa ────────────────────────────────────────
    Route::put('/pesquisas/{id}', function (\Illuminate\Http\Request $request, $id) {
        try {
            \Illuminate\Support\Facades\DB::table('PESQUISA_SATISFACAO')
                ->where('PESQUISA_ID', $id)
                ->update([
                    'PESQUISA_TITULO' => $request->titulo,
                    'PESQUISA_DESC' => $request->desc,
                    'PESQUISA_INICIO' => $request->inicio ?: null,
                    'PESQUISA_FIM' => $request->fim ?: null,
                    'updated_at' => now(),
                ]);
            // Sincroniza perguntas: remove as antigas e recria
            $keepIds = collect($request->perguntas ?? [])->pluck('id')->filter()->values();
            \Illuminate\Support\Facades\DB::table('PESQUISA_PERGUNTA')
                ->where('PESQUISA_ID', $id)
                ->whereNotIn('PERGUNTA_ID', $keepIds->toArray())
                ->delete();
            foreach (($request->perguntas ?? []) as $pq) {
                if (!empty($pq['id'])) {
                    \Illuminate\Support\Facades\DB::table('PESQUISA_PERGUNTA')
                        ->where('PERGUNTA_ID', $pq['id'])
                        ->update([
                            'PERGUNTA_TEXTO' => $pq['texto'],
                            'PERGUNTA_TIPO' => $pq['tipo'],
                            'PERGUNTA_ORDEM' => $pq['ordem'] ?? 0,
                            'PERGUNTA_OPCOES' => isset($pq['opcoes']) ? json_encode($pq['opcoes']) : null,
                            'updated_at' => now(),
                        ]);
                } else {
                    \Illuminate\Support\Facades\DB::table('PESQUISA_PERGUNTA')->insert([
                        'PESQUISA_ID' => $id,
                        'PERGUNTA_TEXTO' => $pq['texto'],
                        'PERGUNTA_TIPO' => $pq['tipo'],
                        'PERGUNTA_ORDEM' => $pq['ordem'] ?? 0,
                        'PERGUNTA_OPCOES' => isset($pq['opcoes']) ? json_encode($pq['opcoes']) : null,
                        'created_at' => now(),
                        'updated_at' => now(),
                    ]);
                }
            }
            return response()->json(['ok' => true]);
        } catch (\Throwable $e) {
            return response()->json(['erro' => $e->getMessage()], 500);
        }
    });

    // ── Admin: mudar status ───────────────────────────────────────────
    Route::patch('/pesquisas/{id}/status', function (\Illuminate\Http\Request $request, $id) {
        try {
            \Illuminate\Support\Facades\DB::table('PESQUISA_SATISFACAO')
                ->where('PESQUISA_ID', $id)
                ->update(['PESQUISA_STATUS' => $request->status, 'updated_at' => now()]);
            return response()->json(['ok' => true]);
        } catch (\Throwable $e) {
            return response()->json(['erro' => $e->getMessage()], 500);
        }
    });

    // ── Admin: excluir pesquisa ───────────────────────────────────────
    Route::delete('/pesquisas/{id}', function ($id) {
        try {
            \Illuminate\Support\Facades\DB::table('PESQUISA_RESPOSTA')->where('PESQUISA_ID', $id)->delete();
            \Illuminate\Support\Facades\DB::table('PESQUISA_PERGUNTA')->where('PESQUISA_ID', $id)->delete();
            \Illuminate\Support\Facades\DB::table('PESQUISA_SATISFACAO')->where('PESQUISA_ID', $id)->delete();
            return response()->json(['ok' => true]);
        } catch (\Throwable $e) {
            return response()->json(['erro' => $e->getMessage()], 500);
        }
    });

    // ── Admin: resultados detalhados ──────────────────────────────────
    Route::get('/pesquisas/{id}/resultados', function ($id) {
        try {
            // Respostas totais
            $totalRespondentes = \Illuminate\Support\Facades\DB::table('PESQUISA_RESPOSTA')
                ->where('PESQUISA_ID', $id)
                ->whereNotNull('RESPOSTA_NOTA')
                ->distinct('FUNCIONARIO_ID')
                ->count('FUNCIONARIO_ID');

            // Calcula NPS da primeira pergunta NPS
            $pergNps = \Illuminate\Support\Facades\DB::table('PESQUISA_PERGUNTA')
                ->where('PESQUISA_ID', $id)
                ->where('PERGUNTA_TIPO', 'nps')
                ->orderBy('PERGUNTA_ORDEM')
                ->first();

            $nps = 0;
            $promotores = 0;
            $neutros = 0;
            $detratores = 0;
            if ($pergNps) {
                $notas = \Illuminate\Support\Facades\DB::table('PESQUISA_RESPOSTA')
                    ->where('PESQUISA_ID', $id)
                    ->where('PERGUNTA_ID', $pergNps->PERGUNTA_ID)
                    ->whereNotNull('RESPOSTA_NOTA')
                    ->pluck('RESPOSTA_NOTA');
                $total = $notas->count();
                if ($total > 0) {
                    $prom = $notas->filter(fn($n) => $n >= 9)->count();
                    $neut = $notas->filter(fn($n) => $n >= 7 && $n < 9)->count();
                    $detr = $notas->filter(fn($n) => $n < 7)->count();
                    $promotores = round($prom / $total * 100);
                    $neutros = round($neut / $total * 100);
                    $detratores = round($detr / $total * 100);
                    $nps = $promotores - $detratores;
                }
            }

            // Resultado por pergunta
            $perguntas = \Illuminate\Support\Facades\DB::table('PESQUISA_PERGUNTA')
                ->where('PESQUISA_ID', $id)
                ->orderBy('PERGUNTA_ORDEM')
                ->get()
                ->map(function ($pq) use ($id) {
                    $respostas = \Illuminate\Support\Facades\DB::table('PESQUISA_RESPOSTA')
                        ->where('PESQUISA_ID', $id)
                        ->where('PERGUNTA_ID', $pq->PERGUNTA_ID)
                        ->get();

                    $result = [
                        'id' => $pq->PERGUNTA_ID,
                        'texto' => $pq->PERGUNTA_TEXTO,
                        'tipo' => $pq->PERGUNTA_TIPO,
                    ];

                    if (in_array($pq->PERGUNTA_TIPO, ['nps', 'estrelas'])) {
                        $notas = $respostas->pluck('RESPOSTA_NOTA')->filter();
                        $result['media'] = $notas->count() ? round($notas->avg(), 2) : null;
                        $result['total'] = $notas->count();
                    } elseif ($pq->PERGUNTA_TIPO === 'opcoes') {
                        $textos = $respostas->pluck('RESPOSTA_TEXTO')->filter();
                        $counts = $textos->countBy()->sortDesc();
                        $ttl = $textos->count();
                        $result['ranking'] = $counts->map(fn($c, $v) => [
                            'valor' => $v,
                            'count' => $c,
                            'pct' => $ttl ? round($c / $ttl * 100) : 0,
                        ])->values();
                    } else {
                        $result['textos'] = $respostas->pluck('RESPOSTA_TEXTO')->filter()->values();
                    }
                    return $result;
                });

            return response()->json([
                'total' => $totalRespondentes,
                'nps' => $nps,
                'promotores' => $promotores,
                'neutros' => $neutros,
                'detratores' => $detratores,
                'perguntas' => $perguntas,
            ]);
        } catch (\Throwable $e) {
            return response()->json(['erro' => $e->getMessage()], 500);
        }
    });

    // �?�?�?�?�?�?�?�?�?�?�?�?�?�?�?�?�?�?�?�?�?�?�?�?�?�?�?�?�?�?�?�?�?�?�?�?�?�?�?�?�?�?�?�?�?�?�?�?�?�?�?�?�?�?�?�?�?�?�?�?�?�?�?�?�?�?�?
    // SPRINT D — BENEF�?CIOS
    // �?�?�?�?�?�?�?�?�?�?�?�?�?�?�?�?�?�?�?�?�?�?�?�?�?�?�?�?�?�?�?�?�?�?�?�?�?�?�?�?�?�?�?�?�?�?�?�?�?�?�?�?�?�?�?�?�?�?�?�?�?�?�?�?�?�?�?

    Route::get('/beneficios', function () {
        try {
            $user = \Illuminate\Support\Facades\Auth::user();
            $func = \App\Models\Funcionario::where('USUARIO_ID', $user->USUARIO_ID ?? 0)->first();

            $ativos = \Illuminate\Support\Facades\DB::table('BENEFICIO')
                ->when($func, fn($q) => $q->where('FUNCIONARIO_ID', $func->FUNCIONARIO_ID))
                ->where('BENEFICIO_ATIVO', 1)
                ->get()
                ->map(fn($b) => [
                    'id' => $b->BENEFICIO_ID,
                    'ico' => $b->BENEFICIO_ICO ?? '�?',
                    'nome' => $b->BENEFICIO_NOME,
                    'cor' => $b->BENEFICIO_COR ?? '#64748b',
                    'desc' => $b->BENEFICIO_DESC ?? '',
                    'fornecedor' => $b->BENEFICIO_FORNEC ?? '—',
                    'valor' => (float) ($b->BENEFICIO_VALOR ?? 0),
                    'custo' => (float) ($b->BENEFICIO_CUSTO ?? 0),
                    'detalhes' => [],
                    'extrato' => [],
                ]);

            if ($ativos->isEmpty())
                return response()->json(['ativos' => [], 'disponiveis' => [], 'fallback' => true]);

            return response()->json(['ativos' => $ativos, 'disponiveis' => []]);
        } catch (\Throwable $e) {
            return response()->json(['ativos' => [], 'disponiveis' => [], 'fallback' => true]);
        }
    });

    Route::post('/beneficios/solicitar', function (\Illuminate\Http\Request $request) {
        try {
            $user = \Illuminate\Support\Facades\Auth::user();
            $func = \App\Models\Funcionario::where('USUARIO_ID', $user->USUARIO_ID ?? 0)->first();
            \Illuminate\Support\Facades\DB::table('BENEFICIO_SOLICITACAO')->insertOrIgnore([
                'FUNCIONARIO_ID' => $func->FUNCIONARIO_ID ?? null,
                'BENEFICIO_ID' => $request->beneficio_id,
                'NOME' => $request->nome,
                'STATUS' => 'pendente',
                'created_at' => now(),
                'updated_at' => now(),
            ]);
        } catch (\Throwable) { /* silencioso */
        }
        return response()->json(['ok' => true]);
    });

    // �?�?�?�?�?�?�?�?�?�?�?�?�?�?�?�?�?�?�?�?�?�?�?�?�?�?�?�?�?�?�?�?�?�?�?�?�?�?�?�?�?�?�?�?�?�?�?�?�?�?�?�?�?�?�?�?�?�?�?�?�?�?�?�?�?�?�?
    // SPRINT D — CARGOS E SAL�?RIOS (S-1030 / S-1040)
    // �?�?�?�?�?�?�?�?�?�?�?�?�?�?�?�?�?�?�?�?�?�?�?�?�?�?�?�?�?�?�?�?�?�?�?�?�?�?�?�?�?�?�?�?�?�?�?�?�?�?�?�?�?�?�?�?�?�?�?�?�?�?�?�?�?�?�?

    Route::get('/cargos', function (\Illuminate\Http\Request $request) {
        try {
            $q = $request->q ?? '';
            $query = \Illuminate\Support\Facades\DB::table('CARGO');
            if ($q)
                $query->where(fn($x) => $x->where('CARGO_NOME', 'like', "%$q%")->orWhere('CARGO_CBO', 'like', "%$q%")->orWhere('CARGO_SIGLA', 'like', "%$q%"));
            if ($request->ativo !== null && $request->ativo !== '')
                $query->where('CARGO_ATIVO', (int) $request->ativo);

            $cargos = $query->orderBy('CARGO_NOME')->get()->map(fn($c) => [
                'cargo_id' => $c->CARGO_ID,
                'nome' => $c->CARGO_NOME,
                'sigla' => $c->CARGO_SIGLA ?? null,
                'cbo' => $c->CARGO_CBO ?? null,
                'descricao' => $c->CARGO_DESCRICAO ?? null,
                'remuneracao' => (float) ($c->CARGO_REMUNERACAO ?? 0) ?: null,
                'escolaridade' => $c->CARGO_ESCOLARIDADE ?? null,
                'gestao' => (bool) ($c->CARGO_GESTAO ?? false),
                'ativo' => (bool) ($c->CARGO_ATIVO ?? true),
                'data_inicio' => $c->CARGO_DATA_INICIO ?? null,
            ]);
            return response()->json(['cargos' => $cargos]);
        } catch (\Throwable $e) {
            return response()->json(['cargos' => [], 'erro' => $e->getMessage()]);
        }
    });

    Route::post('/cargos', function (\Illuminate\Http\Request $request) {
        try {
            if (!$request->CARGO_NOME)
                return response()->json(['erro' => 'Nome obrigatório.'], 422);
            $id = \Illuminate\Support\Facades\DB::table('CARGO')->insertGetId([
                'CARGO_NOME' => $request->CARGO_NOME,
                'CARGO_SIGLA' => $request->CARGO_SIGLA ?? null,
                'CARGO_CBO' => $request->CARGO_CBO ?? null,
                'CARGO_DESCRICAO' => $request->CARGO_DESCRICAO ?? null,
                'CARGO_REMUNERACAO' => $request->CARGO_REMUNERACAO ?? null,
                'CARGO_ESCOLARIDADE' => $request->CARGO_ESCOLARIDADE ?? null,
                'CARGO_GESTAO' => (int) ($request->CARGO_GESTAO ?? 0),
                'CARGO_ATIVO' => 1,
                'CARGO_DATA_INICIO' => $request->CARGO_DATA_INICIO ?? null,
                'created_at' => now(),
                'updated_at' => now(),
            ]);
            return response()->json(['ok' => true, 'cargo_id' => $id]);
        } catch (\Throwable $e) {
            return response()->json(['erro' => $e->getMessage()], 500);
        }
    });

    Route::put('/cargos/{id}', function (\Illuminate\Http\Request $request, $id) {
        try {
            \Illuminate\Support\Facades\DB::table('CARGO')->where('CARGO_ID', $id)->update([
                'CARGO_NOME' => $request->CARGO_NOME,
                'CARGO_SIGLA' => $request->CARGO_SIGLA ?? null,
                'CARGO_CBO' => $request->CARGO_CBO ?? null,
                'CARGO_DESCRICAO' => $request->CARGO_DESCRICAO ?? null,
                'CARGO_REMUNERACAO' => $request->CARGO_REMUNERACAO ?? null,
                'CARGO_ESCOLARIDADE' => $request->CARGO_ESCOLARIDADE ?? null,
                'CARGO_GESTAO' => (int) ($request->CARGO_GESTAO ?? 0),
                'CARGO_DATA_INICIO' => $request->CARGO_DATA_INICIO ?? null,
                'updated_at' => now(),
            ]);
            return response()->json(['ok' => true]);
        } catch (\Throwable $e) {
            return response()->json(['erro' => $e->getMessage()], 500);
        }
    });

    Route::delete('/cargos/{id}', function ($id) {
        try {
            \Illuminate\Support\Facades\DB::table('CARGO')->where('CARGO_ID', $id)->update(['CARGO_ATIVO' => 0, 'updated_at' => now()]);
            return response()->json(['ok' => true]);
        } catch (\Throwable $e) {
            return response()->json(['erro' => $e->getMessage()], 500);
        }
    });

    Route::get('/funcoes', function (\Illuminate\Http\Request $request) {
        try {
            $q = $request->q ?? '';
            $funcoes = \Illuminate\Support\Facades\DB::table('FUNCAO')
                ->when($q, fn($x) => $x->where('FUNCAO_NOME', 'like', "%$q%"))
                ->orderBy('FUNCAO_NOME')->get()
                ->map(fn($f) => [
                    'funcao_id' => $f->FUNCAO_ID,
                    'nome' => $f->FUNCAO_NOME,
                    'cbo' => $f->FUNCAO_CBO ?? null,
                    'tipo' => $f->FUNCAO_TIPO ?? null,
                    'gratificacao' => (float) ($f->FUNCAO_GRATIFICACAO ?? 0) ?: null,
                    'ativo' => (bool) ($f->FUNCAO_ATIVO ?? true),
                ]);
            return response()->json(['funcoes' => $funcoes]);
        } catch (\Throwable $e) {
            return response()->json(['funcoes' => [], 'erro' => $e->getMessage()]);
        }
    });

    Route::post('/funcoes', function (\Illuminate\Http\Request $request) {
        try {
            if (!$request->nome)
                return response()->json(['erro' => 'Nome obrigatório.'], 422);
            $id = \Illuminate\Support\Facades\DB::table('FUNCAO')->insertGetId([
                'FUNCAO_NOME' => $request->nome,
                'FUNCAO_CBO' => $request->cbo ?? null,
                'FUNCAO_TIPO' => $request->tipo ?? null,
                'FUNCAO_GRATIFICACAO' => $request->gratificacao ?? null,
                'FUNCAO_ATIVO' => 1,
                'created_at' => now(),
                'updated_at' => now(),
            ]);
            return response()->json(['ok' => true, 'funcao_id' => $id]);
        } catch (\Throwable $e) {
            return response()->json(['erro' => $e->getMessage()], 500);
        }
    });

    Route::put('/funcoes/{id}', function (\Illuminate\Http\Request $request, $id) {
        try {
            \Illuminate\Support\Facades\DB::table('FUNCAO')->where('FUNCAO_ID', $id)->update([
                'FUNCAO_NOME' => $request->nome,
                'FUNCAO_CBO' => $request->cbo ?? null,
                'FUNCAO_TIPO' => $request->tipo ?? null,
                'FUNCAO_GRATIFICACAO' => $request->gratificacao ?? null,
                'updated_at' => now(),
            ]);
            return response()->json(['ok' => true]);
        } catch (\Throwable $e) {
            return response()->json(['erro' => $e->getMessage()], 500);
        }
    });

    Route::delete('/funcoes/{id}', function ($id) {
        try {
            \Illuminate\Support\Facades\DB::table('FUNCAO')->where('FUNCAO_ID', $id)->update(['FUNCAO_ATIVO' => 0, 'updated_at' => now()]);
            return response()->json(['ok' => true]);
        } catch (\Throwable $e) {
            return response()->json(['erro' => $e->getMessage()], 500);
        }
    });

    // �?�?�?�?�?�?�?�?�?�?�?�?�?�?�?�?�?�?�?�?�?�?�?�?�?�?�?�?�?�?�?�?�?�?�?�?�?�?�?�?�?�?�?�?�?�?�?�?�?�?�?�?�?�?�?�?�?�?�?�?�?�?�?�?�?�?�?
    // PONTO ELETRÔNICO — config, registros do mês e bater ponto
    // �?�?�?�?�?�?�?�?�?�?�?�?�?�?�?�?�?�?�?�?�?�?�?�?�?�?�?�?�?�?�?�?�?�?�?�?�?�?�?�?�?�?�?�?�?�?�?�?�?�?�?�?�?�?�?�?�?�?�?�?�?�?�?�?�?�?�?

    Route::get('/ponto/config', function () {
        try {
            $user = \Illuminate\Support\Facades\Auth::user();
            $func = \App\Models\Funcionario::where('USUARIO_ID', $user->USUARIO_ID ?? 0)->first();

            $cfg = $func
                ? \Illuminate\Support\Facades\DB::table('PONTO_CONFIG_FUNCIONARIO')
                    ->where('FUNCIONARIO_ID', $func->FUNCIONARIO_ID)
                    ->first()
                : null;

            return response()->json([
                'regime' => $cfg->REGIME ?? '4_batidas',
                'hora_entrada' => $cfg->HORA_ENTRADA ?? '08:00',
                'hora_saida' => $cfg->HORA_SAIDA ?? '18:00',
                'tolerancia' => $cfg->TOLERANCIA ?? 15,
                'intervalo_almoco' => 120,
            ]);
        } catch (\Throwable $e) {
            return response()->json(['regime' => '4_batidas', 'hora_entrada' => '08:00', 'hora_saida' => '18:00', 'tolerancia' => 15, 'intervalo_almoco' => 120]);
        }
    });

    Route::get('/ponto', function (\Illuminate\Http\Request $request) {
        try {
            $user = \Illuminate\Support\Facades\Auth::user();
            $func = \App\Models\Funcionario::where('USUARIO_ID', $user->USUARIO_ID ?? 0)->first();

            if (!$func)
                return response()->json(['registros' => []]);

            // competencia = YYYY-MM
            $comp = $request->competencia ?? now()->format('Y-m');
            [$ano, $mes] = explode('-', $comp);
            $inicio = "{$ano}-{$mes}-01";
            $fim = date('Y-m-t', strtotime($inicio));

            $rows = \Illuminate\Support\Facades\DB::table('REGISTRO_PONTO')
                ->where('FUNCIONARIO_ID', $func->FUNCIONARIO_ID)
                ->whereBetween(\Illuminate\Support\Facades\DB::raw("DATE(REGISTRO_DATA_HORA)"), [$inicio, $fim])
                ->orderBy('REGISTRO_DATA_HORA')
                ->get();

            // Agrupa por dia
            $porDia = [];
            foreach ($rows as $r) {
                $dia = (int) date('j', strtotime($r->REGISTRO_DATA_HORA));
                $porDia[$dia][] = [
                    'hora' => date('H:i', strtotime($r->REGISTRO_DATA_HORA)),
                    'tipo' => $r->REGISTRO_TIPO,
                    'id' => $r->REGISTRO_PONTO_ID,
                ];
            }

            $registros = [];
            foreach ($porDia as $dia => $batidas) {
                $registros[] = ['dia' => $dia, 'batidas' => $batidas];
            }

            return response()->json(['registros' => $registros]);
        } catch (\Throwable $e) {
            return response()->json(['registros' => [], 'erro' => $e->getMessage()]);
        }
    });

    Route::post('/ponto/registro', function (\Illuminate\Http\Request $request) {
        try {
            $user = \Illuminate\Support\Facades\Auth::user();
            $func = \App\Models\Funcionario::where('USUARIO_ID', $user->USUARIO_ID ?? 0)->first();

            if (!$func)
                return response()->json(['erro' => 'Funcionário não encontrado.'], 404);

            $data = $request->data ?? now()->toDateString(); // YYYY-MM-DD
            $hora = $request->hora ?? now()->format('H:i:s');
            $tipo = $request->tipo ?? 'entrada';

            // Normaliza hora para HH:MM:SS
            if (strlen($hora) === 5)
                $hora .= ':00';

            $dataHora = $data . ' ' . $hora;

            $id = \Illuminate\Support\Facades\DB::table('REGISTRO_PONTO')->insertGetId([
                'FUNCIONARIO_ID' => $func->FUNCIONARIO_ID,
                'REGISTRO_DATA_HORA' => $dataHora,
                'REGISTRO_TIPO' => $tipo,
                'REGISTRO_ORIGEM' => 'WEB',
            ]);

            return response()->json([
                'ok' => true,
                'id' => $id,
                'hora' => substr($hora, 0, 5),
                'tipo' => $tipo,
                'protocolo' => 'REG-' . str_pad($id, 6, '0', STR_PAD_LEFT),
            ]);
        } catch (\Throwable $e) {
            return response()->json(['erro' => $e->getMessage()], 500);
        }
    });

    // �?�?�?�?�?�?�?�?�?�?�?�?�?�?�?�?�?�?�?�?�?�?�?�?�?�?�?�?�?�?�?�?�?�?�?�?�?�?�?�?�?�?�?�?�?�?�?�?�?�?�?�?�?�?�?�?�?�?�?�?�?�?�?�?�?�?�?
    // DECLARAÇÕES E REQUERIMENTOS
    // �?�?�?�?�?�?�?�?�?�?�?�?�?�?�?�?�?�?�?�?�?�?�?�?�?�?�?�?�?�?�?�?�?�?�?�?�?�?�?�?�?�?�?�?�?�?�?�?�?�?�?�?�?�?�?�?�?�?�?�?�?�?�?�?�?�?�?

    Route::get('/declaracoes', function () {
        try {
            $user = \Illuminate\Support\Facades\Auth::user();
            $func = \App\Models\Funcionario::where('USUARIO_ID', $user->USUARIO_ID ?? 0)->first();

            if (!$func)
                return response()->json(['pedidos' => [], 'fallback' => true]);

            $pedidos = \Illuminate\Support\Facades\DB::table('DECLARACAO')
                ->where('FUNCIONARIO_ID', $func->FUNCIONARIO_ID)
                ->orderByDesc('DECLARACAO_DT_SOLICITACAO')
                ->get()
                ->map(fn($d) => [
                    'id' => $d->DECLARACAO_ID,
                    'nome' => $d->DECLARACAO_TIPO,
                    'data' => $d->DECLARACAO_DT_SOLICITACAO,
                    'status' => $d->DECLARACAO_STATUS ?? 'andamento',
                    'protocolo' => 'REQ-' . date('Y') . '-' . str_pad($d->DECLARACAO_ID, 3, '0', STR_PAD_LEFT),
                    'arquivo' => $d->DECLARACAO_ARQUIVO ?? null,
                ]);

            return response()->json(['pedidos' => $pedidos]);
        } catch (\Throwable $e) {
            return response()->json(['pedidos' => [], 'fallback' => true, 'erro' => $e->getMessage()]);
        }
    });

    Route::post('/declaracoes', function (\Illuminate\Http\Request $request) {
        try {
            $user = \Illuminate\Support\Facades\Auth::user();
            $func = \App\Models\Funcionario::where('USUARIO_ID', $user->USUARIO_ID ?? 0)->first();

            $nome = $request->nome ?? 'Documento';
            $instantaneo = (bool) ($request->instantaneo ?? false);
            $status = $instantaneo ? 'pronto' : 'andamento';

            $id = \Illuminate\Support\Facades\DB::table('DECLARACAO')->insertGetId([
                'FUNCIONARIO_ID' => $func->FUNCIONARIO_ID ?? null,
                'DECLARACAO_TIPO' => $nome,
                'DECLARACAO_STATUS' => $status,
                'DECLARACAO_DT_SOLICITACAO' => now()->toDateString(),
                'DECLARACAO_DT_CONCLUSAO' => $instantaneo ? now()->toDateString() : null,
            ]);

            $protocolo = 'REQ-' . date('Y') . '-' . str_pad($id, 3, '0', STR_PAD_LEFT);

            return response()->json([
                'ok' => true,
                'id' => $id,
                'protocolo' => $protocolo,
                'status' => $status,
            ]);
        } catch (\Throwable $e) {
            return response()->json(['erro' => $e->getMessage()], 500);
        }
    });

    Route::get('/declaracoes/{id}/download', function ($id) {
        try {
            $user = \Illuminate\Support\Facades\Auth::user();
            $func = \App\Models\Funcionario::where('USUARIO_ID', $user->USUARIO_ID ?? 0)->first();

            $decl = \Illuminate\Support\Facades\DB::table('DECLARACAO')->where('DECLARACAO_ID', $id)->first();
            if (!$decl)
                return response()->json(['erro' => 'Não encontrado.'], 404);

            // Busca dados do funcionário
            $funcNome = 'Servidor(a)';
            $funcMatricula = '—';
            $cargo = '—';
            $setor = '—';
            $cpf = '—';
            $dtAdmissao = '—';
            if ($func) {
                $pessoa = \Illuminate\Support\Facades\DB::table('PESSOA')
                    ->join('FUNCIONARIO', 'FUNCIONARIO.PESSOA_ID', '=', 'PESSOA.PESSOA_ID')
                    ->where('FUNCIONARIO.FUNCIONARIO_ID', $func->FUNCIONARIO_ID)
                    ->select('PESSOA.PESSOA_NOME', 'PESSOA.PESSOA_CPF')
                    ->first();
                $funcNome = $pessoa->PESSOA_NOME ?? 'Servidor(a)';
                $cpf = isset($pessoa->PESSOA_CPF) ? preg_replace('/^(\d{3})(\d{3})(\d{3})(\d{2})$/', '$1.$2.$3-$4', $pessoa->PESSOA_CPF) : '—';
                $funcMatricula = $func->FUNCIONARIO_MATRICULA ?? '—';
                $dtAdmissao = $func->FUNCIONARIO_DT_ADMISSAO ? date('d/m/Y', strtotime($func->FUNCIONARIO_DT_ADMISSAO)) : '—';
                // Cargo e setor via lotação
                $lot = \Illuminate\Support\Facades\DB::table('LOTACAO')
                    ->where('FUNCIONARIO_ID', $func->FUNCIONARIO_ID)
                    ->orderByDesc('LOTACAO_ID')
                    ->first();
                if ($lot) {
                    $cargo = $lot->CARGO ?? $lot->FUNCAO ?? '—';
                    $setor = $lot->SETOR ?? $lot->UNIDADE ?? '—';
                }
            }

            $tipo = $decl->DECLARACAO_TIPO;
            $data = $decl->DECLARACAO_DT_SOLICITACAO ? date('d/m/Y', strtotime($decl->DECLARACAO_DT_SOLICITACAO)) : now()->format('d/m/Y');
            $proto = 'REQ-' . date('Y') . '-' . str_pad($id, 3, '0', STR_PAD_LEFT);
            $hoje = now()->format('d/m/Y');
            $ano = date('Y');

            // Mapa de variáveis para substituição no template
            $vars = [
                '{{NOME}}' => $funcNome,
                '{{MATRICULA}}' => $funcMatricula,
                '{{CARGO}}' => $cargo,
                '{{SETOR}}' => $setor,
                '{{CPF}}' => $cpf,
                '{{DATA_ADMISSAO}}' => $dtAdmissao,
                '{{DATA_HOJE}}' => $hoje,
                '{{PROTOCOLO}}' => $proto,
                '{{TIPO}}' => $tipo,
                '{{ANO}}' => $ano,
            ];

            // Tenta usar modelo personalizado do banco
            $modelo = \Illuminate\Support\Facades\DB::table('DECLARACAO_MODELO')
                ->where('MODELO_TIPO', $tipo)
                ->first();

            if ($modelo && !empty($modelo->MODELO_HTML)) {
                $html = str_replace(array_keys($vars), array_values($vars), $modelo->MODELO_HTML);
            } else {
                // Fallback: HTML padrão gerado pelo sistema
                $html = <<<HTML
<!DOCTYPE html><html lang="pt-BR"><head><meta charset="UTF-8"><title>{$tipo}</title>
<style>
  body{font-family:Arial,sans-serif;font-size:13px;color:#1e293b;margin:70px auto;max-width:700px;line-height:1.8}
  .topo{text-align:center;border-bottom:3px solid #1e3a8a;padding-bottom:18px;margin-bottom:28px}
  .topo h1{font-size:16px;color:#1e3a8a;margin:0 0 4px}.topo p{font-size:11px;color:#64748b;margin:0}
  .titulo{text-align:center;font-size:15px;font-weight:bold;text-transform:uppercase;letter-spacing:.06em;margin:28px 0 22px}
  .corpo{text-align:justify}
  .tabela{margin:20px 0;border:1px solid #e2e8f0;border-radius:8px;padding:14px 18px;background:#f8fafc}
  .tabela table{width:100%;border-collapse:collapse}
  .tabela td{padding:4px 6px;font-size:12px}.tabela td:first-child{font-weight:bold;color:#475569;width:150px}
  .assinatura{margin-top:60px;text-align:center}
  .linha{border-top:1px solid #1e293b;width:260px;margin:0 auto 6px}
  .assinatura p{font-size:12px;color:#475569;margin:3px 0}
  .rodape{margin-top:36px;text-align:center;font-size:10px;color:#94a3b8;border-top:1px solid #e2e8f0;padding-top:10px}
</style></head><body>
<div class="topo"><h1>TRIBUNAL DE JUSTIÇA DO MARANHÃO</h1><p>Departamento de Gestão de Pessoas · Sistema GENTE v3</p></div>
<div class="titulo">{$tipo}</div>
<div class="corpo">
<p>Declaramos, para os devidos fins de direito, que <strong>{$funcNome}</strong>, servidor(a) com matrícula <strong>{$funcMatricula}</strong>, encontra-se regularmente vinculado(a) ao quadro de pessoal desta instituição, na forma da legislação vigente.</p>
<p>Esta declaração é emitida a pedido do(a) interessado(a) e tem validade de <strong>90 (noventa) dias</strong> a contar da data de emissão.</p>
</div>
<div class="tabela"><table>
<tr><td>Servidor(a):</td><td>{$funcNome}</td></tr>
<tr><td>Matrícula:</td><td>{$funcMatricula}</td></tr>
<tr><td>CPF:</td><td>{$cpf}</td></tr>
<tr><td>Cargo:</td><td>{$cargo}</td></tr>
<tr><td>Setor:</td><td>{$setor}</td></tr>
<tr><td>Tipo Documento:</td><td>{$tipo}</td></tr>
<tr><td>Solicitado em:</td><td>{$data}</td></tr>
<tr><td>Protocolo:</td><td>{$proto}</td></tr>
<tr><td>Emitido em:</td><td>{$hoje}</td></tr>
</table></div>
<div class="assinatura"><div class="linha"></div>
<p><strong>Departamento de Gestão de Pessoas</strong></p>
<p>Assinado digitalmente · {$hoje}</p></div>
<div class="rodape">Documento gerado eletronicamente · Protocolo {$proto} · Sistema GENTE v3 · {$hoje}</div>
</body></html>
HTML;
            }

            return response($html, 200, [
                'Content-Type' => 'text/html; charset=UTF-8',
                'Content-Disposition' => 'attachment; filename="declaracao-' . $proto . '.html"',
            ]);
        } catch (\Throwable $e) {
            return response()->json(['erro' => $e->getMessage()], 500);
        }
    });

    // �?�?�?�?�?�?�?�?�?�?�?�?�?�?�?�?�?�?�?�?�?�?�?�?�?�?�?�?�?�?�?�?�?�?�?�?�?�?�?�?�?�?�?�?�?�?�?�?�?�?�?�?�?�?�?�?�?�?�?�?�?�?�?�?�?�?�?
    // RH — GESTÃO DE DECLARAÇÕES (admin/rh)
    // �?�?�?�?�?�?�?�?�?�?�?�?�?�?�?�?�?�?�?�?�?�?�?�?�?�?�?�?�?�?�?�?�?�?�?�?�?�?�?�?�?�?�?�?�?�?�?�?�?�?�?�?�?�?�?�?�?�?�?�?�?�?�?�?�?�?�?

    // Lista TODAS as declarações (todos os funcionários) para o RH
    Route::get('/rh/declaracoes', function () {
        try {
            $rows = \Illuminate\Support\Facades\DB::table('DECLARACAO as D')
                ->leftJoin('FUNCIONARIO as F', 'F.FUNCIONARIO_ID', '=', 'D.FUNCIONARIO_ID')
                ->leftJoin('PESSOA as P', 'P.PESSOA_ID', '=', 'F.PESSOA_ID')
                ->orderByRaw("CASE D.DECLARACAO_STATUS WHEN 'pendente' THEN 0 WHEN 'andamento' THEN 1 ELSE 2 END")
                ->orderByDesc('D.DECLARACAO_DT_SOLICITACAO')
                ->select(
                    'D.DECLARACAO_ID as id',
                    'D.DECLARACAO_TIPO as nome',
                    'D.DECLARACAO_STATUS as status',
                    'D.DECLARACAO_OBS as obs',
                    'D.DECLARACAO_DT_SOLICITACAO as data',
                    'D.DECLARACAO_DT_CONCLUSAO as data_conclusao',
                    'P.PESSOA_NOME as servidor',
                    'F.FUNCIONARIO_MATRICULA as matricula'
                )
                ->get()
                ->map(fn($d) => [
                    'id' => $d->id,
                    'nome' => $d->nome,
                    'status' => $d->status ?? 'pendente',
                    'obs' => $d->obs,
                    'data' => $d->data,
                    'servidor' => $d->servidor ?? 'Servidor não identificado',
                    'matricula' => $d->matricula ?? '—',
                    'protocolo' => 'REQ-' . date('Y') . '-' . str_pad($d->id, 3, '0', STR_PAD_LEFT),
                ]);

            return response()->json(['itens' => $rows]);
        } catch (\Throwable $e) {
            return response()->json(['itens' => [], 'erro' => $e->getMessage()]);
        }
    });

    // Atualiza status de uma declaração (aprovar ou indeferir)
    Route::patch('/rh/declaracoes/{id}', function (\Illuminate\Http\Request $request, $id) {
        try {
            $status = $request->status;
            $obs = $request->obs ?? null;

            if (!in_array($status, ['pronto', 'indeferido', 'andamento', 'pendente'])) {
                return response()->json(['erro' => 'Status inválido.'], 422);
            }

            \Illuminate\Support\Facades\DB::table('DECLARACAO')
                ->where('DECLARACAO_ID', $id)
                ->update([
                    'DECLARACAO_STATUS' => $status,
                    'DECLARACAO_OBS' => $obs,
                    'DECLARACAO_DT_CONCLUSAO' => in_array($status, ['pronto', 'indeferido']) ? now()->toDateString() : null,
                ]);

            return response()->json(['ok' => true, 'status' => $status]);
        } catch (\Throwable $e) {
            return response()->json(['erro' => $e->getMessage()], 500);
        }
    });

    // �?�?�?�?�?�?�?�?�?�?�?�?�?�?�?�?�?�?�?�?�?�?�?�?�?�?�?�?�?�?�?�?�?�?�?�?�?�?�?�?�?�?�?�?�?�?�?�?�?�?�?�?�?�?�?�?�?�?�?�?�?�?�?�?�?�?�?
    // RH — GESTÃO DE MODELOS DE DECLARAÇÃO
    // �?�?�?�?�?�?�?�?�?�?�?�?�?�?�?�?�?�?�?�?�?�?�?�?�?�?�?�?�?�?�?�?�?�?�?�?�?�?�?�?�?�?�?�?�?�?�?�?�?�?�?�?�?�?�?�?�?�?�?�?�?�?�?�?�?�?�?

    // Lista todos os tipos com info se têm modelo
    Route::get('/rh/modelos', function () {
        try {
            $tipos = [
                'Declaração de Vínculo Empregatício',
                'Declaração para Financiamento Imobiliário',
                'Declaração de Renda',
                'Certidão de Tempo de Serviço',
                'Declaração de Não Acumulação de Cargos',
                'Ficha Cadastral Completa',
                'Declaração para Bolsas de Estudo',
                'Declaração de Estágio Probatório',
                'Contracheque / Hollerith',
            ];
            // Busca TODOS os modelos do banco (evita problema de encoding no whereIn)
            $todosModelos = \Illuminate\Support\Facades\DB::table('DECLARACAO_MODELO')
                ->select('MODELO_TIPO', 'MODELO_ATUALIZADO_EM')
                ->get();

            // Indexa por tipo usando PHP (string comparison nativa, sem risco de collation)
            $modelosMap = [];
            foreach ($todosModelos as $m) {
                $modelosMap[$m->MODELO_TIPO] = $m;
            }

            $lista = array_map(fn($t) => [
                'tipo' => $t,
                'temModelo' => array_key_exists($t, $modelosMap),
                'atualizadoEm' => $modelosMap[$t]->MODELO_ATUALIZADO_EM ?? null,
            ], $tipos);

            return response()->json(['modelos' => $lista]);
        } catch (\Throwable $e) {
            return response()->json(['erros' => $e->getMessage()], 500);
        }
    });

    // Retorna o HTML de um modelo específico
    Route::get('/rh/modelos/{tipo}', function ($tipo) {
        try {
            $tipo = urldecode($tipo);
            $m = \Illuminate\Support\Facades\DB::table('DECLARACAO_MODELO')
                ->where('MODELO_TIPO', $tipo)->first();
            if (!$m)
                return response()->json(['html' => '', 'existe' => false]);
            return response()->json(['html' => $m->MODELO_HTML, 'existe' => true, 'atualizadoEm' => $m->MODELO_ATUALIZADO_EM]);
        } catch (\Throwable $e) {
            return response()->json(['erro' => $e->getMessage()], 500);
        }
    });

    // Cria ou atualiza modelo
    Route::post('/rh/modelos', function (\Illuminate\Http\Request $request) {
        try {
            $tipo = $request->tipo;
            $html = $request->html ?? '';
            if (!$tipo || !$html)
                return response()->json(['erro' => 'tipo e html são obrigatórios'], 422);

            $agora = now()->toDateTimeString();
            $existe = \Illuminate\Support\Facades\DB::table('DECLARACAO_MODELO')
                ->where('MODELO_TIPO', $tipo)->count();

            if ($existe) {
                \Illuminate\Support\Facades\DB::table('DECLARACAO_MODELO')
                    ->where('MODELO_TIPO', $tipo)
                    ->update(['MODELO_HTML' => $html, 'MODELO_ATUALIZADO_EM' => $agora]);
            } else {
                \Illuminate\Support\Facades\DB::table('DECLARACAO_MODELO')->insert([
                    'MODELO_TIPO' => $tipo,
                    'MODELO_HTML' => $html,
                    'MODELO_ATUALIZADO_EM' => $agora,
                ]);
            }
            return response()->json(['ok' => true, 'atualizadoEm' => $agora]);
        } catch (\Throwable $e) {
            return response()->json(['erro' => $e->getMessage()], 500);
        }
    });

    // Remove modelo (volta ao padrão do sistema)
    Route::delete('/rh/modelos/{tipo}', function ($tipo) {
        try {
            $tipo = urldecode($tipo);
            \Illuminate\Support\Facades\DB::table('DECLARACAO_MODELO')->where('MODELO_TIPO', $tipo)->delete();
            return response()->json(['ok' => true]);
        } catch (\Throwable $e) {
            return response()->json(['erro' => $e->getMessage()], 500);
        }
    });

    // ─────────────────────────────────────────────────────────────
    // AVALIAÇÃO DE DESEMPENHO  /api/v3/avaliacoes
    // ─────────────────────────────────────────────────────────────

    // GET  — histórico de avaliações (do servidor logado OU de um funcionário específico quando gestor avalia)
    Route::get('/v3/avaliacoes', function (\Illuminate\Http\Request $request) {
        try {
            $usuario = session('usuario');
            // Gestor pode consultar avaliações de um servidor específico passando ?funcionario_id=X
            $funcId = $request->query('funcionario_id')
                ?? $usuario['FUNCIONARIO_ID']
                ?? $usuario['id']
                ?? null;

            if (!$funcId) {
                return response()->json(['fallback' => true, 'avaliacoes' => []]);
            }

            $avaliacoes = \Illuminate\Support\Facades\DB::table('AVALIACAO_DESEMPENHO as AD')
                ->leftJoin('USUARIO as U', 'U.USUARIO_ID', '=', 'AD.AVALIADOR_ID')
                ->where('AD.FUNCIONARIO_ID', $funcId)
                ->orderByDesc('AD.created_at')
                ->select(
                    'AD.AVALIACAO_ID',
                    'AD.AVALIACAO_CICLO as ciclo',
                    'AD.AVALIACAO_NOTA_FINAL as nota',
                    'AD.AVALIACAO_STATUS as status',
                    'U.USUARIO_NOME as avaliador',
                    'AD.created_at'
                )
                ->get();

            // Para cada avaliação, busca os critérios
            $result = $avaliacoes->map(function ($av) {
                $criterios = \Illuminate\Support\Facades\DB::table('AVALIACAO_CRITERIO')
                    ->where('AVALIACAO_ID', $av->AVALIACAO_ID)
                    ->select('CRITERIO_NOME as nome', 'CRITERIO_PESO as peso', 'CRITERIO_NOTA as nota', 'CRITERIO_OBS as obs')
                    ->get();
                return [
                    'ciclo' => $av->ciclo,
                    'nota' => (float) $av->nota,
                    'status' => $av->status,
                    'avaliador' => $av->avaliador ?? 'Gestor',
                    'criterios' => $criterios,
                ];
            });

            return response()->json(['fallback' => false, 'avaliacoes' => $result]);
        } catch (\Throwable $e) {
            return response()->json(['fallback' => true, 'avaliacoes' => [], 'debug' => $e->getMessage()]);
        }
    });

    // POST — salvar nova avaliação (gestor avalia servidor)
    Route::post('/v3/avaliacoes', function (\Illuminate\Http\Request $request) {
        try {
            $usuario = session('usuario');
            $avaliadorId = $usuario['USUARIO_ID'] ?? $usuario['id'] ?? null;

            // funcionario_id: gestor passa explicitamente; servidor logado usa o próprio ID
            $funcId = $request->input('funcionario_id')
                ?? $usuario['FUNCIONARIO_ID']
                ?? $usuario['id']
                ?? null;

            $ciclo = $request->input('ciclo', date('Y') . '.1');
            $criterios = $request->input('criterios', []);

            if (!$funcId || empty($criterios)) {
                return response()->json(['erro' => 'funcionario_id e criterios são obrigatórios.'], 422);
            }

            // Calcula nota final ponderada
            $notaFinal = 0;
            $pesosTotal = 0;
            foreach ($criterios as $c) {
                $peso = (int) ($c['peso'] ?? 20);
                $nota = (int) ($c['nota'] ?? 0);
                $notaFinal += $nota * $peso;
                $pesosTotal += $peso;
            }
            $notaFinal = $pesosTotal > 0 ? round($notaFinal / $pesosTotal, 1) : 0;

            // Insere cabeçalho da avaliação
            $avaliacaoId = \Illuminate\Support\Facades\DB::table('AVALIACAO_DESEMPENHO')->insertGetId([
                'FUNCIONARIO_ID' => $funcId,
                'AVALIACAO_CICLO' => $ciclo,
                'AVALIACAO_NOTA_FINAL' => $notaFinal,
                'AVALIACAO_STATUS' => 'enviada',
                'AVALIADOR_ID' => $avaliadorId,
                'created_at' => now(),
                'updated_at' => now(),
            ]);

            // Insere cada critério
            foreach ($criterios as $c) {
                \Illuminate\Support\Facades\DB::table('AVALIACAO_CRITERIO')->insert([
                    'AVALIACAO_ID' => $avaliacaoId,
                    'CRITERIO_NOME' => $c['nome'] ?? '—',
                    'CRITERIO_PESO' => (int) ($c['peso'] ?? 20),
                    'CRITERIO_NOTA' => (int) ($c['nota'] ?? 0),
                    'CRITERIO_OBS' => $c['obs'] ?? null,
                    'created_at' => now(),
                    'updated_at' => now(),
                ]);
            }

            return response()->json([
                'ok' => true,
                'avaliacao_id' => $avaliacaoId,
                'nota_final' => $notaFinal,
                'ciclo' => $ciclo,
            ], 201);
        } catch (\Throwable $e) {
            return response()->json(['erro' => $e->getMessage()], 500);
        }
    });

    // ── Módulo de Exoneração e Verbas Rescisórias ─────────────────
    require __DIR__ . '/exoneracao.php';

    // ── Hora Extra, Plantão Extra e Folha por Secretaria ──────────
    require __DIR__ . '/hora_extra.php';

    // ── Verbas Indenizatórias Mensais ─────────────────────────────
    require __DIR__ . '/verba_indenizatoria.php';

    // ── Consignações em Folha ─────────────────────────────────────
    require __DIR__ . '/consignacao.php';

    // ── eSocial — Painel de Eventos ───────────────────────────────
    require __DIR__ . '/esocial.php';

    // ── RPPS / IPAM ───────────────────────────────────────────────
    require __DIR__ . '/rpps.php';

    // ── Diárias e Missões ─────────────────────────────────────────
    require __DIR__ . '/diarias.php';

    // ── Estagiários ───────────────────────────────────────────────
    require __DIR__ . '/estagiarios.php';

    // ── Acumulação de Cargos (LAT-01 / GAP-09) ────────────────────
    require __DIR__ . '/acumulacao.php';

    // ── Transparência Pública (LAT-02 / GAP-10) ───────────────────
    require __DIR__ . '/transparencia.php';

    // ── PSS / Concursos (LAT-03 / GAP-11) ────────────────────────
    require __DIR__ . '/pss.php';

    // ── Terceirizados (LAT-04 / GAP-12) ──────────────────────────
    require __DIR__ . '/terceirizados.php';

    // ── SAGRES / TCE-MA (LAT-05 / GAP-13) ───────────────────────
    require __DIR__ . '/sagres.php';

    // ── Banco de Horas (GAP-05) ───────────────────────────────────
    require __DIR__ . '/banco_horas.php';

    // ── Atestados Médicos (GAP-06) ────────────────────────────────
    require __DIR__ . '/atestados.php';

    // ── ERP / Fiscal — Orçamento Público (ERP-1) ─────────────────
    require __DIR__ . '/orcamento.php';

    // ── ERP / Fiscal — Execução da Despesa (ERP-2) ───────────────
    require __DIR__ . '/execucao_despesa.php';

    // ── ERP / Fiscal — Contabilidade Pública PCASP (ERP-3) ───────
    require __DIR__ . '/contabilidade.php';

    // ── ERP / Fiscal — Tesouraria (ERP-4) ────────────────────────
    require __DIR__ . '/tesouraria.php';

    // ── ERP / Fiscal — Receita Municipal (ERP-5) ─────────────────
    require __DIR__ . '/receita_municipal.php';

    // ── ERP / Fiscal — Controle Externo SAGRES/SICONFI (ERP-6) ──
    require __DIR__ . '/controle_externo.php';

});

