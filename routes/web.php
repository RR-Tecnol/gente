<?php

use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Route;

// â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€
// Os controllers legados do Vue 2 foram removidos em Mar/2026.
// Todas as funcionalidades estÃ£o agora no gente-v3 (Vue 3 SPA)
// consumindo os endpoints /api/v3/* definidos abaixo.
// â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€

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

// â•?â•?â•?â•?â•?â•?â•?â•?â•?â•?â•?â•?â•?â•?â•?â•?â•?â•?â•?â•?â•?â•?â•?â•?â•?â•?â•?â•?â•?â•?â•?â•?â•?â•?â•?â•?â•?â•?â•?â•?â•?â•?â•?â•?â•?â•?â•?â•?â•?â•?â•?â•?â•?â•?â•?â•?â•?â•?â•?â•?â•?â•?
// CSRF cookie â€” necessÃ¡rio para SPA inicializar sessÃ£o
// â•?â•?â•?â•?â•?â•?â•?â•?â•?â•?â•?â•?â•?â•?â•?â•?â•?â•?â•?â•?â•?â•?â•?â•?â•?â•?â•?â•?â•?â•?â•?â•?â•?â•?â•?â•?â•?â•?â•?â•?â•?â•?â•?â•?â•?â•?â•?â•?â•?â•?â•?â•?â•?â•?â•?â•?â•?â•?â•?â•?â•?â•?
Route::get('/csrf-cookie', function () {
    return response()->json(['csrfToken' => csrf_token()]);
})->middleware('web');

// â•?â•?â•?â•?â•?â•?â•?â•?â•?â•?â•?â•?â•?â•?â•?â•?â•?â•?â•?â•?â•?â•?â•?â•?â•?â•?â•?â•?â•?â•?â•?â•?â•?â•?â•?â•?â•?â•?â•?â•?â•?â•?â•?â•?â•?â•?â•?â•?â•?â•?â•?â•?â•?â•?â•?â•?â•?â•?â•?â•?â•?â•?
// API DE AUTENTICAÃ‡ÃƒO â€” GENTE V3 SPA (Vue 3)
// Endpoints JSON consumidos pelo frontend Vue via axios.
// Usam sessÃ£o Laravel (cookie-based), sem JWT/Sanctum.
// â•?â•?â•?â•?â•?â•?â•?â•?â•?â•?â•?â•?â•?â•?â•?â•?â•?â•?â•?â•?â•?â•?â•?â•?â•?â•?â•?â•?â•?â•?â•?â•?â•?â•?â•?â•?â•?â•?â•?â•?â•?â•?â•?â•?â•?â•?â•?â•?â•?â•?â•?â•?â•?â•?â•?â•?â•?â•?â•?â•?â•?â•?
Route::prefix('api/auth')->middleware(['web', 'throttle:10,1'])->group(function () { // SEC-05: rate limit login

    // GET /api/auth/me â€” Retorna o usuÃ¡rio autenticado ou 401
    Route::get('/me', function (Request $request) {
        if (!Auth::check()) {
            return response()->json(['error' => 'NÃ£o autenticado'], 401);
        }
        $user = Auth::user();
        $funcionario = $user->FUNCIONARIO_ID
            ? \App\Models\Funcionario::with('pessoa')->find($user->FUNCIONARIO_ID)
            : null;
        // UsuÃ¡rio 'admin' sempre tem acesso total, independente da relaÃ§Ã£o de perfis
        if (strtolower($user->USUARIO_LOGIN) === 'admin') {
            $perfilNome = 'admin';
        } else {
            $perfilNome = optional($user->usuarioPerfis()->with('perfil')->first())->perfil->PERFIL_NOME ?? null;
            if (!$perfilNome || strtolower(trim($perfilNome)) === 'usuÃ¡rio') {
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

    // POST /api/auth/login â€” Autentica e inicia sessÃ£o
    Route::post('/login', function (Request $request) {
        $login = $request->input('USUARIO_LOGIN');
        $password = $request->input('USUARIO_SENHA');

        if (!$login || !$password) {
            return response()->json(['error' => 'Credenciais não informadas'], 422);
        }

        // SEC-PROD-03: verificar bloqueio por IP
        $ip = $request->ip();
        $janela = now()->subMinutes(15);
        $tentativas = \Illuminate\Support\Facades\DB::table('LOGIN_ATTEMPTS')
            ->where('IP', $ip)
            ->where('SUCESSO', false)
            ->where('TENTATIVA_EM', '>=', $janela)
            ->count();

        if ($tentativas >= 5) {
            \Illuminate\Support\Facades\Log::channel('security')->warning('login_bloqueado_ip', ['ip' => $ip, 'tentativas' => $tentativas]);
            return response()->json([
                'error' => 'Muitas tentativas incorretas. Aguarde 15 minutos.',
                'bloqueado_ate' => now()->addMinutes(15)->toIso8601String(),
            ], 429);
        }

        // SEC-PROD-02: verificar reCAPTCHA v3 (score >= 0.5 = humano)
        if ($request->has('recaptcha_token') && app()->isProduction()) {
            $resp = \Illuminate\Support\Facades\Http::asForm()->post('https://www.google.com/recaptcha/api/siteverify', [
                'secret'   => env('RECAPTCHA_SECRET_KEY'),
                'response' => $request->input('recaptcha_token'),
                'remoteip' => $request->ip(),
            ])->json();
            if (!($resp['success'] ?? false) || ($resp['score'] ?? 0) < 0.5) {
                return response()->json(['error' => 'Verificação de segurança falhou (bot detectado).'], 422);
            }
        }

        // Sanitiza CPF (mantÃ©m admin e 'admin')
        if ($login !== 'admin') {
            $login = preg_replace('/[^0-9]/', '', $login);
        }

        $user = \App\Models\Usuario::where('USUARIO_LOGIN', $login)
            ->where('USUARIO_ATIVO', 1)
            ->first();

        if (!$user) {
            \Illuminate\Support\Facades\DB::table('LOGIN_ATTEMPTS')->insert(['IP' => $ip, 'LOGIN' => $login, 'SUCESSO' => false, 'TENTATIVA_EM' => now()]);
            return response()->json(['error' => 'Credenciais invÃ¡lidas ou usuÃ¡rio inativo'], 401);
        }

        // MigraÃ§Ã£o transparente MD5 â†’ bcrypt
        if ($user->USUARIO_SENHA === md5($password)) {
            $user->USUARIO_SENHA = bcrypt($password);
            $user->USUARIO_ALTERAR_SENHA = 1;
            $user->save();
        }

        if (!\Hash::check($password, $user->USUARIO_SENHA)) {
            \Illuminate\Support\Facades\DB::table('LOGIN_ATTEMPTS')->insert(['IP' => $ip, 'LOGIN' => $login, 'SUCESSO' => false, 'TENTATIVA_EM' => now()]);
            return response()->json(['error' => 'Senha incorreta'], 401);
        }

        if ($user->USUARIO_VIGENCIA && $user->USUARIO_VIGENCIA < date('Y-m-d')) {
            return response()->json(['error' => 'Acesso expirado'], 401);
        }

        \Illuminate\Support\Facades\DB::table('LOGIN_ATTEMPTS')->insert(['IP' => $ip, 'LOGIN' => $login, 'SUCESSO' => true, 'TENTATIVA_EM' => now()]);

        Auth::login($user, false);
        \Illuminate\Support\Facades\Log::channel('security')->info('login_sucesso', ['usuario' => $login, 'ip' => $request->ip()]);
        $request->session()->regenerate();
        try {
            $user->USUARIO_ULTIMO_ACESSO = now();
            $user->save();
        } catch (\Throwable $e) {
        }

        $funcionario = $user->FUNCIONARIO_ID
            ? \App\Models\Funcionario::with('pessoa')->find($user->FUNCIONARIO_ID)
            : null;
        // UsuÃ¡rio 'admin' sempre tem acesso total, independente da relaÃ§Ã£o de perfis
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

    // POST /api/auth/logout â€” Encerra sessÃ£o
    Route::post('/logout', function (Request $request) {
        Auth::logout();
        $request->session()->invalidate();
        $request->session()->regenerateToken();
        return response()->json(['ok' => true]);
    });

    // POST /api/auth/change-password â€” Troca de senha
    Route::post('/change-password', function (Request $request) {
        if (!Auth::check()) {
            return response()->json(['error' => 'NÃ£o autenticado'], 401);
        }
        $senhaAtual = $request->input('senha_atual');
        $senhaNova = $request->input('senha_nova');

        if (!$senhaAtual || !$senhaNova) {
            return response()->json(['error' => 'Informe a senha atual e a nova senha'], 422);
        }

        $validador = \Illuminate\Support\Facades\Validator::make($request->all(), [
            'senha_nova' => [
                'required',
                'string',
                'min:8',
                'regex:/[A-Z]/',
                'regex:/[0-9]/',
                'regex:/[!@#$%^&*]/',
                'not_regex:/^(.)\1+$/'
            ]
        ], [
            'senha_nova.min' => 'A senha deve ter no mínimo 8 caracteres.',
            'senha_nova.regex' => 'A senha não atende aos critérios de complexidade.',
            'senha_nova.not_regex' => 'A senha não pode ser uma repetição do mesmo caractere.',
        ]);

        if ($validador->fails()) {
            return response()->json(['error' => $validador->errors()->first()], 422);
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

// Serve o SPA Vue para o link de autocadastro â€” rota PÃšBLICA (fora do grupo dev)
Route::get('/autocadastro/{token}', function () {
    return view('v3.app');
});

// [DEV-ONLY] Rotas de diagnÃ³stico â€” disponÃ­veis APENAS em ambiente local/dev
// âš ï¸? SEC-02: usar isLocal() â€” nÃ£o depende de APP_ENV=production para proteger
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

        // GENTE 2.0 (VUE 3) Rota PÃºblica TemporÃ¡ria para Testes de UI
        Route::get('/v3', function () {
            return view('v3.app');
        });


        // [DEV-ONLY] DiagnÃ³stico de conexÃ£o e login
// â•?â•?â•?â•?â•?â•?â•?â•?â•?â•?â•?â•?â•?â•?â•?â•?â•?â•?â•?â•?â•?â•?â•?â•?â•?â•?â•?â•?â•?â•?â•?â•?â•?â•?â•?â•?â•?â•?â•?â•?â•?â•?â•?â•?â•?â•?â•?â•?â•?â•?â•?â•?â•?â•?â•?â•?â•?â•?â•?
// API V3 â€” GENTE SPA (Vue 3) â€” AÃ§Ãµes do Perfil do FuncionÃ¡rio
// â•?â•?â•?â•?â•?â•?â•?â•?â•?â•?â•?â•?â•?â•?â•?â•?â•?â•?â•?â•?â•?â•?â•?â•?â•?â•?â•?â•?â•?â•?â•?â•?â•?â•?â•?â•?â•?â•?â•?â•?â•?â•?â•?â•?â•?â•?â•?â•?â•?â•?â•?â•?â•?â•?â•?â•?â•?â•?â•?
        Route::prefix('api/v3')->middleware(['web', 'auth'])->group(function () {

            // â”€â”€ FuncionÃ¡rio: buscar perfil completo para o SPA â”€â”€â”€â”€â”€â”€â”€â”€
            Route::get('/funcionarios/{id}', function ($id) {
                $func = \App\Models\Funcionario::with([
                    'pessoa',
                    'lotacoes.setor.unidade',
                    'lotacoes.atribuicaoLotacoes.atribuicao',
                    'lotacoes.vinculo',
                ])->find($id);

                if (!$func)
                    return response()->json(['message' => 'FuncionÃ¡rio nÃ£o encontrado'], 404);

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

            // â”€â”€ FuncionÃ¡rio: atualizar dados pessoais â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€
            Route::put('/funcionarios/{id}', function ($id, \Illuminate\Http\Request $request) {
                $func = \App\Models\Funcionario::with('pessoa')->find($id);
                if (!$func)
                    return response()->json(['message' => 'FuncionÃ¡rio nÃ£o encontrado'], 404);

                // Dados do funcionÃ¡rio
                $func->fill($request->only(['FUNCIONARIO_MATRICULA', 'FUNCIONARIO_DATA_INICIO', 'FUNCIONARIO_DATA_FIM', 'FUNCIONARIO_OBSERVACAO']));
                $func->save();

                // Dados da pessoa â€” aceita flat (como o formulÃ¡rio Vue envia) ou aninhado em 'pessoa'
                $pessoaData = $request->has('pessoa') ? $request->input('pessoa', []) : $request->all();

                if ($func->pessoa) {
                    // Campos com cast correto via fill()
                    $func->pessoa->fill(array_intersect_key($pessoaData, array_flip([
                        'PESSOA_NOME',
                        'PESSOA_CPF_NUMERO',
                        'PESSOA_DATA_NASCIMENTO',
                        'PESSOA_SEXO',
                        'PESSOA_ESTADO_CIVIL',
                        // PESSOA_ESCOLARIDADE â€” coluna nÃ£o existe na tabela PESSOA
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

                    // Campos que precisam ser salvos como texto (UF sigla e nome de municÃ­pio)
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

            // â”€â”€ Documentos do funcionÃ¡rio (por PESSOA_ID) â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€
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

            // â”€â”€ HistÃ³rico funcional: lotaÃ§Ãµes, fÃ©rias, afastamentos â”€â”€â”€
            Route::get('/funcionarios/{id}/historico', function ($id) {
                $func = \App\Models\Funcionario::with([
                    'lotacoes.setor',
                    'lotacoes.vinculo',
                    'lotacoes.atribuicaoLotacoes.atribuicao',
                    'ferias',
                    'afastamentos',
                ])->find($id);

                if (!$func)
                    return response()->json(['message' => 'NÃ£o encontrado'], 404);

                $lotacoes = $func->lotacoes->map(fn($l) => [
                    'tipo' => 'lotacao',
                    'setor' => optional($l->setor)->SETOR_NOME ?? 'â€”',
                    'cargo' => optional($l->atribuicaoLotacoes->last()?->atribuicao)->ATRIBUICAO_NOME ?? 'â€”',
                    'vinculo' => optional($l->vinculo)->VINCULO_NOME ?? 'â€”',
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

            // â”€â”€ Escalas do funcionÃ¡rio â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€
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

            // â”€â”€ Listagem de funcionÃ¡rios com busca (para FuncionariosView) â”€â”€
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

            // â”€â”€ Dependentes do funcionÃ¡rio (IRRF) â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€
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
                    // Tabela pode nÃ£o existir ainda â€” retorna vazio
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

        // âš ï¸? SEC-01: /dev/set-senha DELETADO DEFINITIVAMENTE â€” nÃ£o recriar

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
                return response()->json(['erro' => 'UsuÃ¡rio admin nÃ£o encontrado. Acesse /dev/criar-admin primeiro.'], 400);

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

// â•?â•?â•? API V3 â€” Endpoints para o SPA Vue 3 â•?â•?â•?â•?â•?â•?â•?â•?â•?â•?â•?â•?â•?â•?â•?â•?â•?â•?â•?â•?â•?â•?â•?â•?â•?â•?â•?â•?â•?â•?â•?
Route::prefix('api/v3')->middleware(['web', 'auth'])->group(function () {


    // â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€
    // ARQ-01: mÃ³dulos extraÃ­dos do web.php (rotas /funcionarios, /ponto, /folhas)
    // â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€
    require __DIR__ . '/funcionarios.php';
    require __DIR__ . '/folha.php';
    require __DIR__ . '/motor.php'; // Sprint 3 — endpoints Motor de Folha (vínculos, rubricas, adicionais)

    // GET /api/v3/escalas â€” Lista escalas para o seletor da MatrizEscalaView
    Route::get('/escalas', function (\Illuminate\Http\Request $request) {
        $escalas = App\Models\Escala::with(['setor'])
            ->orderBy('ESCALA_COMPETENCIA', 'desc')
            ->limit(60)
            ->get()
            ->map(fn($e) => [
                'ESCALA_ID' => $e->ESCALA_ID,
                'ESCALA_COMPETENCIA' => $e->ESCALA_COMPETENCIA,
                'setor' => $e->setor?->SETOR_NOME ?? 'â€”',
                'situacao' => $e->ESCALA_SITUACAO ?? null,
            ]);
        return response()->json($escalas);
    });

    // GET /api/v3/escalas/{id} â€” Grade completa de uma escala
    Route::get('/escalas/{id}', function (int $id) {
        $escala = App\Models\Escala::with([
            'setor',
            'detalheEscalas.funcionario.pessoa',
            'detalheEscalas.detalheEscalaItens.turno',
            'detalheEscalas.atribuicao',
        ])->findOrFail($id);

        // Calcula ano/mÃªs da competÃªncia (formato "MM/YYYY")
        [$mes, $ano] = explode('/', $escala->ESCALA_COMPETENCIA . '/2026');
        $ano = (int) ($ano ?? 2026);
        $mes = (int) ($mes ?? 1) - 1; // 0-index para o Vue

        // Feriados do mÃªs
        $mesAno = \Carbon\Carbon::createFromDate($ano, $mes + 1, 1)->format('Y-m-d');
        $feriados = App\Models\Feriado::buscarFeriadoMesAno($mesAno)
            ->map(fn($f) => ['data' => $f->FERIADO_DATA])->values();

        $funcionarios = $escala->detalheEscalas->map(function ($de) {
            return [
                'detalhe_id' => $de->DETALHE_ESCALA_ID,
                'funcionario_id' => $de->FUNCIONARIO_ID,
                'nome' => $de->funcionario?->pessoa?->PESSOA_NOME ?? 'FuncionÃ¡rio',
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

    // â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€
    // MÃ³dulos externos (cada arquivo herda prefix/middleware do grupo)
    // â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€
    require __DIR__ . '/esocial.php';
    require __DIR__ . '/consignacao.php';
    require __DIR__ . '/diarias.php';
    require __DIR__ . '/rpps.php';
    require __DIR__ . '/exoneracao.php';
    require __DIR__ . '/hora_extra.php';
    require __DIR__ . '/verba_indenizatoria.php';
    require __DIR__ . '/pesquisa.php';
    require __DIR__ . '/ouvidoria_admin.php';
    require __DIR__ . '/relatorios.php';
    require __DIR__ . '/estagiarios.php';
    // Sprint 6 â€” novos mÃ³dulos
    require __DIR__ . '/acumulacao.php';
    require __DIR__ . '/transparencia.php';
    require __DIR__ . '/pss.php';
    require __DIR__ . '/terceirizados.php';
    require __DIR__ . '/sagres.php';
    // Sprint 5 â€” banco de horas e atestados
    require __DIR__ . '/banco_horas.php';
    require __DIR__ . '/atestados.php';
    // Refatoracao 30/03/2026 - blocos extraidos do web.php
    require __DIR__ . '/cargos_salarios.php';
    require __DIR__ . '/ferias_v3.php';
    require __DIR__ . '/comunicados.php';
    require __DIR__ . '/meu_perfil.php';
    require __DIR__ . '/ponto_eletronico.php';
    require __DIR__ . '/plantoes_sobreaviso.php';
    require __DIR__ . '/atestados_v3.php';
    require __DIR__ . '/contratos_v3.php';
    require __DIR__ . '/medicina.php';
    require __DIR__ . '/declaracoes.php';
    require __DIR__ . '/ouvidoria.php';
    require __DIR__ . '/gestor.php';
    require __DIR__ . '/organograma_v3.php';
    require __DIR__ . '/beneficios.php';
    require __DIR__ . '/medicina_admin.php';
    require __DIR__ . '/seguranca_trabalho.php';
    require __DIR__ . '/treinamentos.php';
    require __DIR__ . '/consignatarias.php'; // Bloco B — Gestão de Consignatárias
    require __DIR__ . '/compras.php'; // Bloco D — ERP Administrativo
    require __DIR__ . '/almoxarifado.php';
    require __DIR__ . '/patrimonio.php';
    require __DIR__ . '/contratos_admin.php'; // Bloco D4
    require __DIR__ . '/frotas.php'; // Bloco D5
    require __DIR__ . '/escala_saude.php'; // GAP-ESCALA-SAUDE — furos de cobertura
    require __DIR__ . '/decimo_terceiro.php'; // GAP-13 — 13º Salário
    require __DIR__ . '/quadro_vagas.php'; // GAP-QV — Quadro de Vagas
    require __DIR__ . '/simulador_folha.php'; // GAP-SIM + GAP-LRF
    require __DIR__ . '/caged.php'; // GAP-CAG — CAGED MTE
    require __DIR__ . '/sefip.php'; // GAP-GFP — SEFIP/GFIP CEF
    require __DIR__ . '/dirf.php'; // GAP-DIR — DIRF Receita Federal
    require __DIR__ . '/rais.php'; // GAP-RAS — RAIS MTE
    require __DIR__ . '/siconfi.php'; // GAP-SIC — SICONFI STN/LRF
    require __DIR__ . '/ponto_terceirizado.php'; // GAP-PONT — Ponto Terceirizados

    // GAP-OSS — Monitor OSS (admin-only, mock PoC)
    Route::middleware('perfil:ADMIN')->group(function () {
        require __DIR__ . '/oss.php';
    });



    // â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€
    // GAP-01: NotificaÃ§Ãµes â€” stub para parar os 404 do polling
    // â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€
    Route::get('/notificacoes', function () {
        return response()->json(['notificacoes' => [], 'nao_lidas' => 0]);
    });
    Route::post('/notificacoes/{id}/ler', function ($id) {
        return response()->json(['ok' => true]);
    });
    Route::post('/notificacoes/ler-todas', function () {
        return response()->json(['ok' => true]);
    });

    // â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€
    // GAP-03: Endpoint centralizado de busca de servidor
    // Todas as views devem usar /servidores/buscar (nÃ£o /exoneracao/buscar)
    // â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€
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

    // â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€
    // GAP-04: Lookup de secretarias (unidades ativas)
    // Usado por FolhaPagamentoView e outros mÃ³dulos
    // â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€
    Route::get('/secretarias', function () {
        return response()->json([
            'unidades' => \Illuminate\Support\Facades\DB::table('UNIDADE')
                ->where('UNIDADE_ATIVO', 1)
                ->orderBy('UNIDADE_NOME')
                ->get(['UNIDADE_ID', 'UNIDADE_NOME'])
        ]);
    });

    // â”€â”€ GAP-07: Holerite em PDF (print-view HTML â€” DomPDF bloqueado por conflito PHP 8.1)
    // Rota pÃºblica dentro do grupo auth que retorna HTML com @media print otimizado
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
                abort(404, 'Holerite nÃ£o encontrado.');
            }

            // SeguranÃ§a: servidor sÃ³ pode ver o prÃ³prio holerite
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
                if (!$isAdmin) {
                    \Illuminate\Support\Facades\Log::channel('security')->warning('acesso_negado', ['usuario' => Auth::id(), 'rota' => request()->path(), 'ip' => request()->ip()]);
                    abort(403, 'Acesso nÃ£o autorizado.');
                }
            }

            $comp = $detalhe->competencia;
            $compFormatado = strlen($comp) === 6
                ? date('m/Y', mktime(0, 0, 0, substr($comp, 4, 2), 1, substr($comp, 0, 4)))
                : $comp;

            $servidor = [
                'nome' => $detalhe->nome,
                'matricula' => $detalhe->matricula,
                'cpf' => $detalhe->cpf ? substr_replace(preg_replace('/\D/', '', $detalhe->cpf), '***', 3, 6) : 'â€”',
                'cargo' => $detalhe->cargo,
                'lotacao' => $detalhe->lotacao . ($detalhe->unidade ? ' / ' . $detalhe->unidade : ''),
                'regime_prev' => $detalhe->regime_prev,
                'banco' => 'â€”',
                'agencia' => 'â€”',
                'conta' => 'â€”',
            ];

            $totalProventos = floatval($detalhe->DETALHE_FOLHA_PROVENTOS ?? 0);
            $totalDescontos = floatval($detalhe->DETALHE_FOLHA_DESCONTOS ?? 0);
            $liquido = floatval($detalhe->DETALHE_FOLHA_LIQUIDO) ?: ($totalProventos - $totalDescontos);

            // Rubricas â€” se houver tabela DETALHE_FOLHA_RUBRICA (opcional)
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
                // Fallback: linhas sintÃ©ticas com os totais
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

    // â”€â”€ GAP-08: Totais da folha por secretaria (SEC-08 aware) â”€â”€â”€â”€â”€
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
            } catch (\Throwable $ex) { /* tabela nÃ£o existe */
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

    // ApuraÃ§Ã£o e Justificativas
    Route::get('/apuracao', [App\Http\Controllers\PontoEletronicoController::class, "listarApuracao"]);
    Route::get('/justificativas', [App\Http\Controllers\PontoEletronicoController::class, "listarJustificativas"]);
    Route::post('/justificativas/{id}/aprovar', [App\Http\Controllers\PontoEletronicoController::class, "aprovarJustificativa"]);
    Route::post('/justificativas/{id}/rejeitar', [App\Http\Controllers\PontoEletronicoController::class, "rejeitarJustificativa"]);

    // Terminais
    Route::get('/terminais', [App\Http\Controllers\PontoEletronicoController::class, "listarTerminais"]);
});

// â”€â”€ MÃ³dulo 3: Quiosque Ponto EletrÃ´nico (Acesso PÃºblico com Token) â”€â”€
Route::get('/quiosque/{token}', [App\Http\Controllers\PontoEletronicoController::class, "quiosqueView"])->name('quiosque.view');
Route::post('/quiosque/{token}/bater', [App\Http\Controllers\PontoEletronicoController::class, "registrarQuiosque"]);

Auth::routes();

Route::middleware(['auth', 'web', 'CompartilharVariaveis', 'usuario.externo'])->group(function () {
    // Rota legado /home removida 15/03/2026

    // Rota de Acesso ao Holerite CidadÃ£o pelo SPA
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
    // SubstitÃºÃ­das pelas rotas /api/v3/abono-faltas e /api/v3/abonos-gestao.

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

    /** RELATÃ“RIOS */
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

    // â”€â”€ Holerite / Contra-cheque (PDF) â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€
    Route::prefix('holerite')->group(function () {
        Route::get('pdf/{detalheFolhaId}', [App\Http\Controllers\HoleriteController::class, 'pdf'])
            ->name('holerite.pdf');
    });

    // â”€â”€ Alertas de RH â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€
    Route::get('ferias/alerta-vencer', [FeriasController::class, 'alertaVencer'])
        ->name('ferias.alerta-vencer');
    Route::get('afastamento/alerta-expirar', [AfastamentoController::class, 'alertaExpirar'])
        ->name('afastamento.alerta-expirar');

    // â”€â”€ Remessa BancÃ¡ria CNAB 240 â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€
    Route::prefix('remessa')->group(function () {
        Route::get('/', [App\Http\Controllers\RemessaBancariaController::class, 'view'])->name('remessa.view');
        Route::get('folhas', [App\Http\Controllers\RemessaBancariaController::class, 'folhas'])->name('remessa.folhas');
        Route::post('gerar/{folhaId}', [App\Http\Controllers\RemessaBancariaController::class, 'gerar'])->name('remessa.gerar');
        Route::get('resumo/{folhaId}', [App\Http\Controllers\RemessaBancariaController::class, 'resumo'])->name('remessa.resumo');
    });

    // â”€â”€ ConfiguraÃ§Ãµes do Sistema â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€
    Route::prefix('configuracoes')->group(function () {
        Route::get('/', [App\Http\Controllers\ConfiguracaoSistemaController::class, 'index'])->name('configuracoes.index');
        Route::get('api', [App\Http\Controllers\ConfiguracaoSistemaController::class, 'api'])->name('configuracoes.api');
        Route::put('{chave}', [App\Http\Controllers\ConfiguracaoSistemaController::class, 'update'])->name('configuracoes.update');
    });

    // â”€â”€ Ponto EletrÃ´nico (opcional â€” habilitado via CONFIGURACAO_SISTEMA) â”€â”€â”€â”€
    Route::middleware('modulo.ativo:MODULO_PONTO_ATIVO')->prefix('ponto')->group(function () {
        Route::get('view', fn() => view('ponto.index'))->name('ponto.view');

        // Registros de ponto
        Route::get('/', [App\Http\Controllers\RegistroPontoController::class, 'index'])->name('ponto.registros.index');
        Route::post('registros', [App\Http\Controllers\RegistroPontoController::class, 'store'])->name('ponto.registros.store');
        Route::post('registros/afd', [App\Http\Controllers\RegistroPontoController::class, 'importarAfd'])->name('ponto.registros.afd');
        Route::delete('registros/{id}', [App\Http\Controllers\RegistroPontoController::class, 'destroy'])->name('ponto.registros.destroy');

        // ApuraÃ§Ã£o
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

// â•?â•?â•?â•?â•?â•?â•?â•?â•?â•?â•?â•?â•?â•?â•?â•?â•?â•?â•?â•?â•?â•?â•?â•?â•?â•?â•?â•?â•?â•?â•?â•?â•?â•?â•?â•?â•?â•?â•?â•?â•?â•?â•?â•?â•?â•?â•?â•?â•?â•?â•?â•?â•?â•?â•?â•?â•?â•?â•?â•?â•?â•?â•?
//  API v3 â€” Vue SPA (autenticado via sessÃ£o Web do Laravel)
// â•?â•?â•?â•?â•?â•?â•?â•?â•?â•?â•?â•?â•?â•?â•?â•?â•?â•?â•?â•?â•?â•?â•?â•?â•?â•?â•?â•?â•?â•?â•?â•?â•?â•?â•?â•?â•?â•?â•?â•?â•?â•?â•?â•?â•?â•?â•?â•?â•?â•?â•?â•?â•?â•?â•?â•?â•?â•?â•?â•?â•?â•?â•?
Route::prefix('api/v3')->middleware(['web', 'auth'])->group(function () {

    // â”€â”€ Dashboard KPIs â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€
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
                'total_funcionarios' => 'â€”',
                'abonos_pendentes' => 0,
                'folha_status' => 'Aberta',
                'folha_competencia' => now()->format('Y-m'),
            ]);
        }
    });

    // â”€â”€ Holerites do usuÃ¡rio logado â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€
    Route::get('/meus-holerites', function () {
        $user = Auth::user();
        if (!$user)
            return response()->json(['erro' => 'NÃ£o autenticado'], 401);

        // Busca o funcionÃ¡rio vinculado ao usuÃ¡rio
        $funcionario = optional($user)->funcionario
            ?? \App\Models\Funcionario::where('USUARIO_ID', $user->USUARIO_ID)->first();

        if (!$funcionario) {
            return response()->json(['erro' => 'Nenhum funcionÃ¡rio vinculado a este usuÃ¡rio.'], 404);
        }

        // Busca detalhe das folhas do funcionÃ¡rio
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

    // â”€â”€ Admin: VÃ­nculos (configuraÃ§Ã£o do motor de folha) â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€
    Route::get('/admin/vinculos', function () {
        return response()->json(
            \App\Models\Vinculo::orderBy('VINCULO_NOME')
                ->get()
                ->map(fn($v) => [
                    'VINCULO_ID' => $v->VINCULO_ID,
                    'VINCULO_DESCRICAO' => $v->VINCULO_NOME,   // mapeia nome real â†’ alias front
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

    // â”€â”€ FuncionÃ¡rios (listagem paginada) â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€
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

    // â”€â”€ Perfil de um funcionÃ¡rio â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€
    Route::get('/funcionarios/{id}', function ($id) {
        $f = \App\Models\Funcionario::with(['pessoa', 'lotacoes.setor', 'lotacoes.vinculo', 'lotacoes.atribuicaoLotacoes.atribuicao'])
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

        // Busca o e-mail do usuÃ¡rio vinculado ao funcionÃ¡rio
        $usuario = \App\Models\Usuario::where('FUNCIONARIO_ID', $f->FUNCIONARIO_ID)->first();

        $funcionarioArray = $f->toArray();
        $funcionarioArray['email'] = $usuario?->USUARIO_EMAIL ?? null;

        return response()->json([
            'funcionario' => $funcionarioArray,
            'lotacao' => $ultimaLotacao,
            'holerites' => $detalhes,
        ]);
    });

    // â”€â”€ Dados de apoio (selectboxes do modal) â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€
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

    // â”€â”€ Criar novo funcionÃ¡rio â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€
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

            // Campos que precisam ser salvos como texto (sigla UF e nome de municÃ­pio)
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

            // 2. Cria o FuncionÃ¡rio
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

            // 3. Cria a LotaÃ§Ã£o (se setor ou vÃ­nculo foi informado)
            if ($request->filled('SETOR_ID') || $request->filled('VINCULO_ID')) {
                $lotacao = new \App\Models\Lotacao();
                $lotacao->fill([
                    'FUNCIONARIO_ID' => $funcionario->FUNCIONARIO_ID,
                    'SETOR_ID' => $request->SETOR_ID,
                    'VINCULO_ID' => $request->VINCULO_ID,
                    'LOTACAO_DATA_INICIO' => $request->FUNCIONARIO_DATA_INICIO ?? now()->toDateString(),
                ]);
                $lotacao->save();

                // 4. Cria AtribuicaoLotacao se atribuiÃ§Ã£o foi informada
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
                'message' => 'FuncionÃ¡rio cadastrado com sucesso.',
                'funcionario_id' => $funcionario->FUNCIONARIO_ID,
                'pessoa_id' => $pessoa->PESSOA_ID,
            ], 201);

        } catch (\Throwable $e) {
            \Illuminate\Support\Facades\DB::rollBack();
            \Illuminate\Support\Facades\Log::error('Erro ao cadastrar funcionÃ¡rio: ' . $e->getMessage());
            return response()->json(['erro' => 'Erro ao cadastrar: ' . $e->getMessage()], 500);
        }
    });

    // â”€â”€ Atualizar funcionÃ¡rio (completo) â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€
    Route::put('/funcionarios/{id}', function ($id, \Illuminate\Http\Request $request) {
        \Illuminate\Support\Facades\DB::beginTransaction();
        try {
            $f = \App\Models\Funcionario::with(['pessoa', 'lotacoes'])->findOrFail($id);

            // Atualiza dados do FuncionÃ¡rio
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

                // Campos permitidos â€” mescla do sub-objeto com campos de raiz (retrocompatibilidade)
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


                // Campos que precisam ser salvos como texto (sigla UF e nome de municÃ­pio)
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

            // Atualiza ou cria lotaÃ§Ã£o ativa
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

            // Atualiza e-mail do usuÃ¡rio vinculado
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

    // â”€â”€ Inativar funcionÃ¡rio (soft delete) â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€
    Route::delete('/funcionarios/{id}', function ($id, \Illuminate\Http\Request $request) {
        $f = \App\Models\Funcionario::findOrFail($id);
        $f->FUNCIONARIO_DATA_FIM = $request->FUNCIONARIO_DATA_FIM ?? now()->toDateString();
        $f->FUNCIONARIO_TIPO_SAIDA = $request->FUNCIONARIO_TIPO_SAIDA ?? null;
        $f->save();

        // Fecha lotaÃ§Ãµes ativas
        \App\Models\Lotacao::where('FUNCIONARIO_ID', $id)
            ->whereNull('LOTACAO_DATA_FIM')
            ->update(['LOTACAO_DATA_FIM' => $f->FUNCIONARIO_DATA_FIM]);

        return response()->json(['message' => 'FuncionÃ¡rio inativado com sucesso.']);
    });

    // â”€â”€ Documentos â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€
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

    // â”€â”€ HistÃ³rico funcional â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€
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
            'label' => 'FÃ©rias',
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

    // â”€â”€ Escalas â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€
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

    // â”€â”€ Escalas â€” listagem de todas as escalas para o MatrizEscalaView â”€â”€â”€â”€â”€â”€â”€â”€â”€
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

    // â”€â”€ Escalas â€” grade de uma escala especÃ­fica (funcionÃ¡rios + itens) â”€â”€â”€â”€â”€â”€â”€â”€â”€
    Route::get('/escalas/{id}', function ($id) {
        try {
            $escala = \Illuminate\Support\Facades\DB::table('ESCALA')->where('ESCALA_ID', $id)->first();
            if (!$escala)
                return response()->json(['escala' => null, 'funcionarios' => [], 'feriados' => []]);

            // Detecta ano/mÃªs a partir de ESCALA_COMPETENCIA (ex: "2026-02" ou "Fev/2026")
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
                    'nome' => $d->nome ?? 'FuncionÃ¡rio',
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

    // â”€â”€ Escalas â€” salvar grade (substitui /escala/salvar-matriz legado) â”€â”€â”€â”€â”€â”€â”€â”€â”€
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

            // CompetÃªncia: YYYY-MM ou mÃªs/ano corrente
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

            // ApuraÃ§Ã£o do mÃªs
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


    // â”€â”€ Faltas e Atrasos (visÃ£o RH/gestor) â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€
    // â”€â”€ Atestados MÃ©dicos â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€
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
    })->middleware('upload.safe');

    Route::delete('/atestados/{id}', function ($id) {
        try {
            \Illuminate\Support\Facades\DB::table('AFASTAMENTO')->where('AFASTAMENTO_ID', $id)->delete();
            return response()->json(['message' => 'Removido.']);
        } catch (\Throwable $e) {
            return response()->json(['erro' => $e->getMessage()], 500);
        }
    });

    // â”€â”€ PlantÃµes Extras â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€
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

    // â”€â”€ Escala de Sobreaviso â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€
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

    // â”€â”€ RelatÃ³rios â€” stats do hero â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€
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

    // â”€â”€ Agenda â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€
    // â”€â”€ Medicina do Trabalho â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€
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
            return response()->json(['message' => 'SolicitaÃ§Ã£o registrada.'], 201);
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

            // Setor atual do funcionÃ¡rio (lotaÃ§Ã£o sem data_fim)
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
                        // 2. Eventos do setor do funcionÃ¡rio
                        ->orWhere(function ($q2) use ($setorId) {
                        $q2->where('AGENDA_ESCOPO', 'setor')
                            ->where('AGENDA_SETOR_ID', $setorId);
                    })
                        // 3. Eventos pessoais prÃ³prios
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

            // Verifica se Ã© gestor (CARGO_GESTAO = 1) ou admin (PERFIL_ID â‰¤ 2)
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


    // â”€â”€ Banco de Horas â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€
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

    // â”€â”€ Contratos / VÃ­nculos â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€
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
                    'regime' => $h->HISTORICO_REGIME ?? 'EstatutÃ¡rio',
                    'cargo' => $h->HISTORICO_CARGO ?? ($f->CARGO_NOME ?? 'â€”'),
                    'setor' => $h->HISTORICO_SETOR ?? ($f->SETOR_NOME ?? 'â€”'),
                    'inicio' => $h->HISTORICO_DATA_INICIO,
                    'fim' => $h->HISTORICO_DATA_FIM,
                    'ativo' => is_null($h->HISTORICO_DATA_FIM),
                ]);

            return response()->json([
                'fallback' => false,
                'contrato' => [
                    'cargo' => $f->CARGO_NOME ?? 'â€”',
                    'setor' => $f->SETOR_NOME ?? 'â€”',
                    'admissao' => $f->FUNCIONARIO_DATA_ADMISSAO ?? null,
                    'matricula' => $f->FUNCIONARIO_MATRICULA ?? 'â€”',
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

    // â”€â”€ ProgressÃ£o Funcional â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€
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
                    'referencia' => $p->PROGRESSAO_REFERENCIA ?? 'â€”',
                    'salario' => (float) ($p->PROGRESSAO_SALARIO ?? 0),
                    'data' => $p->PROGRESSAO_DATA,
                    'tipo' => $p->PROGRESSAO_TIPO ?? 'ProgressÃ£o',
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

    // â”€â”€ DeclaraÃ§Ãµes / Requerimentos â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€
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

    // â”€â”€ Ouvidoria Admin (RH/Admin) â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€
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
    })->middleware('upload.safe');

    // â”€â”€ Ouvidoria â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€
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

    // â”€â”€ Faltas e Atrasos (visÃ£o RH/gestor) â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€
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

            // Filtra pelo perÃ­odo, mas SEMPRE inclui pendentes de qualquer mÃªs
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
                'funcionario' => $a->funcionario ?? 'FuncionÃ¡rio #' . ($a->funcionario_id ?? '?'),
                'funcionario_id' => $a->funcionario_id,
                'cargo' => '',
                'setor' => '',
                'tipo' => 'falta', // Todos os abonos sÃ£o de falta; ABONO_FALTA_TIPO Ã© o subtipo
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

    // â”€â”€ Abono de Faltas â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€
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
            return response()->json(['erro' => 'FuncionÃ¡rio nÃ£o encontrado.'], 404);

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
    })->middleware('upload.safe');

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
        return response()->json(['message' => 'ExcluÃ­do com sucesso.']);
    });

    // â”€â”€ FÃ©rias e LicenÃ§as do usuÃ¡rio logado â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€
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

    // BUG-EST-14: GET /ferias/sobreposicao
    Route::get('/ferias/sobreposicao', function (\Illuminate\Http\Request $request) {
        $inicio = $request->inicio;
        $fim    = $request->fim;
        if (!$inicio || !$fim) {
            return response()->json(['sobreposicao' => false, 'membros' => [], 'pct' => 0]);
        }
        $user = Auth::user();
        $func = \App\Models\Funcionario::where('USUARIO_ID', $user->USUARIO_ID)->first();
        if (!$func) return response()->json(['sobreposicao' => false, 'membros' => [], 'pct' => 0]);
        $setorId = DB::table('LOTACAO')->where('FUNCIONARIO_ID', $func->FUNCIONARIO_ID)->whereNull('LOTACAO_DATA_FIM')->value('SETOR_ID');
        if (!$setorId) return response()->json(['sobreposicao' => false, 'membros' => [], 'pct' => 0]);
        $totalSetor = DB::table('LOTACAO as l')->join('FUNCIONARIO as f', 'f.FUNCIONARIO_ID', '=', 'l.FUNCIONARIO_ID')->where('l.SETOR_ID', $setorId)->whereNull('l.LOTACAO_DATA_FIM')->whereNull('f.FUNCIONARIO_DATA_FIM')->count();
        $emFerias = DB::table('FERIAS as frs')
            ->join('FUNCIONARIO as f', 'f.FUNCIONARIO_ID', '=', 'frs.FUNCIONARIO_ID')
            ->join('PESSOA as p', 'p.PESSOA_ID', '=', 'f.PESSOA_ID')
            ->join('LOTACAO as l', function ($j) { $j->on('l.FUNCIONARIO_ID', '=', 'f.FUNCIONARIO_ID')->whereNull('l.LOTACAO_DATA_FIM'); })
            ->where('l.SETOR_ID', $setorId)
            ->where('frs.FUNCIONARIO_ID', '<>', $func->FUNCIONARIO_ID)
            ->where('frs.FERIAS_DATA_INICIO', '<=', $fim)
            ->where('frs.FERIAS_DATA_FIM', '>=', $inicio)
            ->select('p.PESSOA_NOME as nome', 'frs.FERIAS_DATA_INICIO as inicio', 'frs.FERIAS_DATA_FIM as fim')
            ->get();
        $pct = $totalSetor > 0 ? round(($emFerias->count() / $totalSetor) * 100) : 0;
        return response()->json(['sobreposicao' => $emFerias->count() > 0, 'membros' => $emFerias, 'total_setor' => $totalSetor, 'pct' => $pct]);
    });

    // â”€â”€ Faltas e Atrasos do usuÃ¡rio logado â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€
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

    // â”€â”€ Banco de Horas do usuÃ¡rio logado â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€
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

    // â”€â”€ Folhas de Pagamento (listagem) â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€
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

    // â”€â”€ SubstituiÃ§Ãµes de escala â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€
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

    // â”€â”€ Escalas (listagem resumida) â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€
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

    // â”€â”€ Escala individual (detalhada para o Vue) â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€
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

});  // fim do bloco dashboard api/v3

Route::prefix('api/v3')->middleware(['web'])->group(function () {

    // Valida token e retorna dados prÃ©-preenchidos
    Route::get('/autocadastro/{token}', function ($token) {
        try {
            $reg = \Illuminate\Support\Facades\DB::table('AUTOCADASTRO_TOKEN')
                ->where('TOKEN', $token)
                ->first();

            if (!$reg) {
                return response()->json(['status' => 'invalido', 'erro' => 'Token nÃ£o encontrado'], 404);
            }

            // Verifica expiraÃ§Ã£o
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

    // Recebe os dados do formulÃ¡rio de autocadastro (multipart/form-data)
    Route::post('/autocadastro/{token}', function (\Illuminate\Http\Request $request, $token) {
        try {
            $reg = \Illuminate\Support\Facades\DB::table('AUTOCADASTRO_TOKEN')
                ->where('TOKEN', $token)
                ->whereIn('TOKEN_STATUS', ['pendente'])
                ->first();

            if (!$reg) {
                return response()->json(['erro' => 'Token invÃ¡lido ou jÃ¡ utilizado'], 422);
            }

            if ($reg->expira_em && now()->gt($reg->expira_em)) {
                return response()->json(['erro' => 'Token expirado'], 410);
            }

            // Valida campos obrigatÃ³rios
            $nome = trim($request->nome ?? '');
            $email = trim($request->email ?? '');
            $senha = $request->senha ?? '';
            if (!$nome || !$email || strlen($senha) < 6) {
                return response()->json(['erro' => 'Nome, e-mail e senha (mÃ­n. 6 chars) sÃ£o obrigatÃ³rios'], 422);
            }

            // â”€â”€ Salva arquivos de documentos â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€
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

            // â”€â”€ Dependentes â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€
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

            // â”€â”€ Persiste os dados do token â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€
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

            return response()->json(['ok' => true, 'msg' => 'Cadastro recebido! Aguarde a aprovaÃ§Ã£o do RH.']);
        } catch (\Throwable $e) {
            return response()->json(['erro' => $e->getMessage()], 500);
        }
    });

    // â•?â•?â•?â•?â•?â•?â•?â•?â•?â•?â•?â•?â•?â•?â•?â•?â•?â•?â•?â•?â•?â•?â•?â•?â•?â•?â•?â•?â•?â•?â•?â•?â•?â•?â•?â•?â•?â•?â•?â•?â•?â•?â•?â•?â•?â•?â•?â•?â•?â•?â•?â•?â•?â•?â•?â•?â•?â•?â•?â•?â•?â•?â•?â•?â•?â•?â•?
    // SPRINT D â€” SEGURANÃ‡A DO TRABALHO
    // â•?â•?â•?â•?â•?â•?â•?â•?â•?â•?â•?â•?â•?â•?â•?â•?â•?â•?â•?â•?â•?â•?â•?â•?â•?â•?â•?â•?â•?â•?â•?â•?â•?â•?â•?â•?â•?â•?â•?â•?â•?â•?â•?â•?â•?â•?â•?â•?â•?â•?â•?â•?â•?â•?â•?â•?â•?â•?â•?â•?â•?â•?â•?â•?â•?â•?â•?





    // â•?â•?â•?â•?â•?â•?â•?â•?â•?â•?â•?â•?â•?â•?â•?â•?â•?â•?â•?â•?â•?â•?â•?â•?â•?â•?â•?â•?â•?â•?â•?â•?â•?â•?â•?â•?â•?â•?â•?â•?â•?â•?â•?â•?â•?â•?â•?â•?â•?â•?â•?â•?â•?â•?â•?â•?â•?â•?â•?â•?â•?â•?â•?â•?â•?â•?â•?
    // SPRINT D â€” TREINAMENTOS
    // â•?â•?â•?â•?â•?â•?â•?â•?â•?â•?â•?â•?â•?â•?â•?â•?â•?â•?â•?â•?â•?â•?â•?â•?â•?â•?â•?â•?â•?â•?â•?â•?â•?â•?â•?â•?â•?â•?â•?â•?â•?â•?â•?â•?â•?â•?â•?â•?â•?â•?â•?â•?â•?â•?â•?â•?â•?â•?â•?â•?â•?â•?â•?â•?â•?â•?â•?




    // â•?â•?â•?â•?â•?â•?â•?â•?â•?â•?â•?â•?â•?â•?â•?â•?â•?â•?â•?â•?â•?â•?â•?â•?â•?â•?â•?â•?â•?â•?â•?â•?â•?â•?â•?â•?â•?â•?â•?â•?â•?â•?â•?â•?â•?â•?â•?â•?â•?â•?â•?â•?â•?â•?â•?â•?â•?â•?â•?â•?â•?â•?â•?â•?â•?â•?â•?
    // SPRINT D â€” PESQUISA DE SATISFAÃ‡ÃƒO (CRUD + RESULTADOS)
    // â•?â•?â•?â•?â•?â•?â•?â•?â•?â•?â•?â•?â•?â•?â•?â•?â•?â•?â•?â•?â•?â•?â•?â•?â•?â•?â•?â•?â•?â•?â•?â•?â•?â•?â•?â•?â•?â•?â•?â•?â•?â•?â•?â•?â•?â•?â•?â•?â•?â•?â•?â•?â•?â•?â•?â•?â•?â•?â•?â•?â•?â•?â•?â•?â•?â•?â•?

    // â”€â”€ FuncionÃ¡rio: lista pesquisas abertas â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€
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

    // â”€â”€ FuncionÃ¡rio: responder pesquisa â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€
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

    // â”€â”€ Admin: listar todas as pesquisas â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€
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

    // â”€â”€ Admin: criar pesquisa â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€
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

    // â”€â”€ Admin: editar pesquisa â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€
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

    // â”€â”€ Admin: mudar status â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€
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

    // â”€â”€ Admin: excluir pesquisa â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€
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

    // â”€â”€ Admin: resultados detalhados â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€
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

    // â•?â•?â•?â•?â•?â•?â•?â•?â•?â•?â•?â•?â•?â•?â•?â•?â•?â•?â•?â•?â•?â•?â•?â•?â•?â•?â•?â•?â•?â•?â•?â•?â•?â•?â•?â•?â•?â•?â•?â•?â•?â•?â•?â•?â•?â•?â•?â•?â•?â•?â•?â•?â•?â•?â•?â•?â•?â•?â•?â•?â•?â•?â•?â•?â•?â•?â•?
    // SPRINT D â€” CARGOS E SALÃ?RIOS (S-1030 / S-1040)
    // â•?â•?â•?â•?â•?â•?â•?â•?â•?â•?â•?â•?â•?â•?â•?â•?â•?â•?â•?â•?â•?â•?â•?â•?â•?â•?â•?â•?â•?â•?â•?â•?â•?â•?â•?â•?â•?â•?â•?â•?â•?â•?â•?â•?â•?â•?â•?â•?â•?â•?â•?â•?â•?â•?â•?â•?â•?â•?â•?â•?â•?â•?â•?â•?â•?â•?â•?

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
                return response()->json(['erro' => 'Nome obrigatÃ³rio.'], 422);
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
                return response()->json(['erro' => 'Nome obrigatÃ³rio.'], 422);
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

    // â•?â•?â•?â•?â•?â•?â•?â•?â•?â•?â•?â•?â•?â•?â•?â•?â•?â•?â•?â•?â•?â•?â•?â•?â•?â•?â•?â•?â•?â•?â•?â•?â•?â•?â•?â•?â•?â•?â•?â•?â•?â•?â•?â•?â•?â•?â•?â•?â•?â•?â•?â•?â•?â•?â•?â•?â•?â•?â•?â•?â•?â•?â•?â•?â•?â•?â•?
    // PONTO ELETRÃ”NICO â€” config, registros do mÃªs e bater ponto
    // â•?â•?â•?â•?â•?â•?â•?â•?â•?â•?â•?â•?â•?â•?â•?â•?â•?â•?â•?â•?â•?â•?â•?â•?â•?â•?â•?â•?â•?â•?â•?â•?â•?â•?â•?â•?â•?â•?â•?â•?â•?â•?â•?â•?â•?â•?â•?â•?â•?â•?â•?â•?â•?â•?â•?â•?â•?â•?â•?â•?â•?â•?â•?â•?â•?â•?â•?

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
                'intervalo_almoco' => isset($cfg->INTERVALO_ALMOCO) ? (int) $cfg->INTERVALO_ALMOCO : null,
                'jornada_financeira_horas' => isset($cfg->JORNADA_FINANCEIRA_HORAS) ? (float) $cfg->JORNADA_FINANCEIRA_HORAS : null,
            ]);
        } catch (\Throwable $e) {
            return response()->json(['erro' => $e->getMessage()], 500);
        }
    });

    Route::put('/ponto/config', function (\Illuminate\Http\Request $request) {
        try {
            $user = \Illuminate\Support\Facades\Auth::user();
            $func = \App\Models\Funcionario::where('USUARIO_ID', $user->USUARIO_ID ?? 0)->first();
            if (!$func) return response()->json(['erro' => 'Sem funcionario'], 404);

            $id = $func->FUNCIONARIO_ID;
            $dados = $request->only(['regime', 'hora_entrada', 'hora_saida', 'tolerancia']);

            $update = [
                'REGIME' => $dados['regime'] ?? '4_batidas',
                'HORA_ENTRADA' => $dados['hora_entrada'] ?? '08:00',
                'HORA_SAIDA' => $dados['hora_saida'] ?? '18:00',
                'TOLERANCIA' => $dados['tolerancia'] ?? 15,
                'updated_at' => now(),
            ];

            \Illuminate\Support\Facades\DB::table('PONTO_CONFIG_FUNCIONARIO')->updateOrInsert(
                ['FUNCIONARIO_ID' => $id],
                $update
            );
            return response()->json(['ok' => true]);
        } catch (\Throwable $e) {
            return response()->json(['erro' => $e->getMessage()], 500);
        }
    });

    // Endpoints Admin
    Route::get('/ponto/config/funcionarios', function () {
        try {
            $user = \Illuminate\Support\Facades\Auth::user();
            if (!in_array($user->PERFIL, ['admin', 'rh'])) {
                return response()->json(['erro' => 'Nao autorizado'], 403);
            }

            $rows = \Illuminate\Support\Facades\DB::table('PONTO_CONFIG_FUNCIONARIO as C')
                ->join('FUNCIONARIO as F', 'C.FUNCIONARIO_ID', '=', 'F.FUNCIONARIO_ID')
                ->join('PESSOA as P', 'F.PESSOA_ID', '=', 'P.PESSOA_ID')
                ->select(
                    'C.FUNCIONARIO_ID',
                    'P.PESSOA_NOME as NOME',
                    'C.REGIME',
                    'C.HORA_ENTRADA',
                    'C.HORA_SAIDA',
                    'C.TOLERANCIA',
                    'C.INTERVALO_ALMOCO',
                    'C.JORNADA_FINANCEIRA_HORAS',
                    'C.JORNADA_FINANCEIRA_OBS'
                )
                ->get();

            return response()->json(['configs' => $rows]);
        } catch (\Throwable $e) {
            return response()->json(['erro' => $e->getMessage()], 500);
        }
    });

    Route::put('/ponto/config/funcionarios/{id}', function (\Illuminate\Http\Request $request, $id) {
        try {
            $user = \Illuminate\Support\Facades\Auth::user();
            if (!in_array($user->PERFIL, ['admin', 'rh'])) {
                return response()->json(['erro' => 'Nao autorizado'], 403);
            }

            $dados = $request->all();
            
            // Regra 4: JORNADA_FINANCEIRA_HORAS = s贸 admin
            if (array_key_exists('JORNADA_FINANCEIRA_HORAS', $dados)) {
                if ($user->PERFIL !== 'admin') {
                    return response()->json(['erro' => 'Apenas admins podem configurar jornada financeira.'], 403);
                }
                if ($dados['JORNADA_FINANCEIRA_HORAS'] !== null && empty($dados['JORNADA_FINANCEIRA_OBS'])) {
                    return response()->json(['erro' => 'Observação é obrigatória ao definir jornada financeira.'], 422);
                }
            }

            $update = [];
            if (isset($dados['REGIME'])) $update['REGIME'] = $dados['REGIME'];
            if (isset($dados['HORA_ENTRADA'])) $update['HORA_ENTRADA'] = $dados['HORA_ENTRADA'];
            if (isset($dados['HORA_SAIDA'])) $update['HORA_SAIDA'] = $dados['HORA_SAIDA'];
            if (isset($dados['TOLERANCIA'])) $update['TOLERANCIA'] = $dados['TOLERANCIA'];
            
            if (array_key_exists('INTERVALO_ALMOCO', $dados)) {
                $update['INTERVALO_ALMOCO'] = $dados['INTERVALO_ALMOCO'];
            }
            if (array_key_exists('JORNADA_FINANCEIRA_HORAS', $dados)) {
                $update['JORNADA_FINANCEIRA_HORAS'] = $dados['JORNADA_FINANCEIRA_HORAS'];
            }
            if (array_key_exists('JORNADA_FINANCEIRA_OBS', $dados)) {
                $update['JORNADA_FINANCEIRA_OBS'] = $dados['JORNADA_FINANCEIRA_OBS'];
            }
            $update['updated_at'] = now();

            \Illuminate\Support\Facades\DB::table('PONTO_CONFIG_FUNCIONARIO')->updateOrInsert(
                ['FUNCIONARIO_ID' => $id],
                $update
            );

            return response()->json(['ok' => true]);
        } catch (\Throwable $e) {
            return response()->json(['erro' => $e->getMessage()], 500);
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
                return response()->json(['erro' => 'FuncionÃ¡rio nÃ£o encontrado.'], 404);

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

    // â•?â•?â•?â•?â•?â•?â•?â•?â•?â•?â•?â•?â•?â•?â•?â•?â•?â•?â•?â•?â•?â•?â•?â•?â•?â•?â•?â•?â•?â•?â•?â•?â•?â•?â•?â•?â•?â•?â•?â•?â•?â•?â•?â•?â•?â•?â•?â•?â•?â•?â•?â•?â•?â•?â•?â•?â•?â•?â•?â•?â•?â•?â•?â•?â•?â•?â•?
    // DECLARAÃ‡Ã•ES E REQUERIMENTOS
    // â•?â•?â•?â•?â•?â•?â•?â•?â•?â•?â•?â•?â•?â•?â•?â•?â•?â•?â•?â•?â•?â•?â•?â•?â•?â•?â•?â•?â•?â•?â•?â•?â•?â•?â•?â•?â•?â•?â•?â•?â•?â•?â•?â•?â•?â•?â•?â•?â•?â•?â•?â•?â•?â•?â•?â•?â•?â•?â•?â•?â•?â•?â•?â•?â•?â•?â•?

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
    })->middleware('upload.safe');

    Route::get('/declaracoes/{id}/download', function ($id) {
        try {
            $user = \Illuminate\Support\Facades\Auth::user();
            $func = \App\Models\Funcionario::where('USUARIO_ID', $user->USUARIO_ID ?? 0)->first();

            $decl = \Illuminate\Support\Facades\DB::table('DECLARACAO')->where('DECLARACAO_ID', $id)->first();
            if (!$decl)
                return response()->json(['erro' => 'NÃ£o encontrado.'], 404);

            // Busca dados do funcionÃ¡rio
            $funcNome = 'Servidor(a)';
            $funcMatricula = 'â€”';
            $cargo = 'â€”';
            $setor = 'â€”';
            $cpf = 'â€”';
            $dtAdmissao = 'â€”';
            if ($func) {
                $pessoa = \Illuminate\Support\Facades\DB::table('PESSOA')
                    ->join('FUNCIONARIO', 'FUNCIONARIO.PESSOA_ID', '=', 'PESSOA.PESSOA_ID')
                    ->where('FUNCIONARIO.FUNCIONARIO_ID', $func->FUNCIONARIO_ID)
                    ->select('PESSOA.PESSOA_NOME', 'PESSOA.PESSOA_CPF')
                    ->first();
                $funcNome = $pessoa->PESSOA_NOME ?? 'Servidor(a)';
                $cpf = isset($pessoa->PESSOA_CPF) ? preg_replace('/^(\d{3})(\d{3})(\d{3})(\d{2})$/', '$1.$2.$3-$4', $pessoa->PESSOA_CPF) : 'â€”';
                $funcMatricula = $func->FUNCIONARIO_MATRICULA ?? 'â€”';
                $dtAdmissao = $func->FUNCIONARIO_DT_ADMISSAO ? date('d/m/Y', strtotime($func->FUNCIONARIO_DT_ADMISSAO)) : 'â€”';
                // Cargo e setor via lotaÃ§Ã£o
                $lot = \Illuminate\Support\Facades\DB::table('LOTACAO')
                    ->where('FUNCIONARIO_ID', $func->FUNCIONARIO_ID)
                    ->orderByDesc('LOTACAO_ID')
                    ->first();
                if ($lot) {
                    $cargo = $lot->CARGO ?? $lot->FUNCAO ?? 'â€”';
                    $setor = $lot->SETOR ?? $lot->UNIDADE ?? 'â€”';
                }
            }

            $tipo = $decl->DECLARACAO_TIPO;
            $data = $decl->DECLARACAO_DT_SOLICITACAO ? date('d/m/Y', strtotime($decl->DECLARACAO_DT_SOLICITACAO)) : now()->format('d/m/Y');
            $proto = 'REQ-' . date('Y') . '-' . str_pad($id, 3, '0', STR_PAD_LEFT);
            $hoje = now()->format('d/m/Y');
            $ano = date('Y');

            // Mapa de variÃ¡veis para substituiÃ§Ã£o no template
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
                // Fallback: HTML padrÃ£o gerado pelo sistema
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
<div class="topo"><h1>TRIBUNAL DE JUSTIÃ‡A DO MARANHÃƒO</h1><p>Departamento de GestÃ£o de Pessoas Â· Sistema GENTE v3</p></div>
<div class="titulo">{$tipo}</div>
<div class="corpo">
<p>Declaramos, para os devidos fins de direito, que <strong>{$funcNome}</strong>, servidor(a) com matrÃ­cula <strong>{$funcMatricula}</strong>, encontra-se regularmente vinculado(a) ao quadro de pessoal desta instituiÃ§Ã£o, na forma da legislaÃ§Ã£o vigente.</p>
<p>Esta declaraÃ§Ã£o Ã© emitida a pedido do(a) interessado(a) e tem validade de <strong>90 (noventa) dias</strong> a contar da data de emissÃ£o.</p>
</div>
<div class="tabela"><table>
<tr><td>Servidor(a):</td><td>{$funcNome}</td></tr>
<tr><td>MatrÃ­cula:</td><td>{$funcMatricula}</td></tr>
<tr><td>CPF:</td><td>{$cpf}</td></tr>
<tr><td>Cargo:</td><td>{$cargo}</td></tr>
<tr><td>Setor:</td><td>{$setor}</td></tr>
<tr><td>Tipo Documento:</td><td>{$tipo}</td></tr>
<tr><td>Solicitado em:</td><td>{$data}</td></tr>
<tr><td>Protocolo:</td><td>{$proto}</td></tr>
<tr><td>Emitido em:</td><td>{$hoje}</td></tr>
</table></div>
<div class="assinatura"><div class="linha"></div>
<p><strong>Departamento de GestÃ£o de Pessoas</strong></p>
<p>Assinado digitalmente Â· {$hoje}</p></div>
<div class="rodape">Documento gerado eletronicamente Â· Protocolo {$proto} Â· Sistema GENTE v3 Â· {$hoje}</div>
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

    // â•?â•?â•?â•?â•?â•?â•?â•?â•?â•?â•?â•?â•?â•?â•?â•?â•?â•?â•?â•?â•?â•?â•?â•?â•?â•?â•?â•?â•?â•?â•?â•?â•?â•?â•?â•?â•?â•?â•?â•?â•?â•?â•?â•?â•?â•?â•?â•?â•?â•?â•?â•?â•?â•?â•?â•?â•?â•?â•?â•?â•?â•?â•?â•?â•?â•?â•?
    // RH â€” GESTÃƒO DE DECLARAÃ‡Ã•ES (admin/rh)
    // â•?â•?â•?â•?â•?â•?â•?â•?â•?â•?â•?â•?â•?â•?â•?â•?â•?â•?â•?â•?â•?â•?â•?â•?â•?â•?â•?â•?â•?â•?â•?â•?â•?â•?â•?â•?â•?â•?â•?â•?â•?â•?â•?â•?â•?â•?â•?â•?â•?â•?â•?â•?â•?â•?â•?â•?â•?â•?â•?â•?â•?â•?â•?â•?â•?â•?â•?

    // Lista TODAS as declaraÃ§Ãµes (todos os funcionÃ¡rios) para o RH
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
                    'servidor' => $d->servidor ?? 'Servidor nÃ£o identificado',
                    'matricula' => $d->matricula ?? 'â€”',
                    'protocolo' => 'REQ-' . date('Y') . '-' . str_pad($d->id, 3, '0', STR_PAD_LEFT),
                ]);

            return response()->json(['itens' => $rows]);
        } catch (\Throwable $e) {
            return response()->json(['itens' => [], 'erro' => $e->getMessage()]);
        }
    });

    // Atualiza status de uma declaraÃ§Ã£o (aprovar ou indeferir)
    Route::patch('/rh/declaracoes/{id}', function (\Illuminate\Http\Request $request, $id) {
        try {
            $status = $request->status;
            $obs = $request->obs ?? null;

            if (!in_array($status, ['pronto', 'indeferido', 'andamento', 'pendente'])) {
                return response()->json(['erro' => 'Status invÃ¡lido.'], 422);
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

    // â•?â•?â•?â•?â•?â•?â•?â•?â•?â•?â•?â•?â•?â•?â•?â•?â•?â•?â•?â•?â•?â•?â•?â•?â•?â•?â•?â•?â•?â•?â•?â•?â•?â•?â•?â•?â•?â•?â•?â•?â•?â•?â•?â•?â•?â•?â•?â•?â•?â•?â•?â•?â•?â•?â•?â•?â•?â•?â•?â•?â•?â•?â•?â•?â•?â•?â•?
    // RH â€” GESTÃƒO DE MODELOS DE DECLARAÃ‡ÃƒO
    // â•?â•?â•?â•?â•?â•?â•?â•?â•?â•?â•?â•?â•?â•?â•?â•?â•?â•?â•?â•?â•?â•?â•?â•?â•?â•?â•?â•?â•?â•?â•?â•?â•?â•?â•?â•?â•?â•?â•?â•?â•?â•?â•?â•?â•?â•?â•?â•?â•?â•?â•?â•?â•?â•?â•?â•?â•?â•?â•?â•?â•?â•?â•?â•?â•?â•?â•?

    // Lista todos os tipos com info se tÃªm modelo
    Route::get('/rh/modelos', function () {
        try {
            $tipos = [
                'Declaração de Vínculo Empregêtício',
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

    // Retorna o HTML de um modelo especÃ­fico
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
                return response()->json(['erro' => 'tipo e html sÃ£o obrigatÃ³rios'], 422);

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

    // Remove modelo (volta ao padrÃ£o do sistema)
    Route::delete('/rh/modelos/{tipo}', function ($tipo) {
        try {
            $tipo = urldecode($tipo);
            \Illuminate\Support\Facades\DB::table('DECLARACAO_MODELO')->where('MODELO_TIPO', $tipo)->delete();
            return response()->json(['ok' => true]);
        } catch (\Throwable $e) {
            return response()->json(['erro' => $e->getMessage()], 500);
        }
    });

    // â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€
    // AVALIAÃ‡ÃƒO DE DESEMPENHO  /api/v3/avaliacoes
    // â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€

    // GET  â€” histÃ³rico de avaliaÃ§Ãµes (do servidor logado OU de um funcionÃ¡rio especÃ­fico quando gestor avalia)
    Route::get('/avaliacoes', function (\Illuminate\Http\Request $request) {
        try {
            $usuario = session('usuario');
            // Gestor pode consultar avaliaÃ§Ãµes de um servidor especÃ­fico passando ?funcionario_id=X
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

            // Para cada avaliaÃ§Ã£o, busca os critÃ©rios
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

    // POST â€” salvar nova avaliaÃ§Ã£o (gestor avalia servidor)
    Route::post('/avaliacoes', function (\Illuminate\Http\Request $request) {
        try {
            $usuario = session('usuario');
            $avaliadorId = $usuario['USUARIO_ID'] ?? $usuario['id'] ?? null;

            // funcionario_id: gestor passa explicitamente; servidor logado usa o prÃ³prio ID
            $funcId = $request->input('funcionario_id')
                ?? $usuario['FUNCIONARIO_ID']
                ?? $usuario['id']
                ?? null;

            $ciclo = $request->input('ciclo', date('Y') . '.1');
            $criterios = $request->input('criterios', []);

            if (!$funcId || empty($criterios)) {
                return response()->json(['erro' => 'funcionario_id e criterios sÃ£o obrigatÃ³rios.'], 422);
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

            // Insere cabeÃ§alho da avaliaÃ§Ã£o
            $avaliacaoId = \Illuminate\Support\Facades\DB::table('AVALIACAO_DESEMPENHO')->insertGetId([
                'FUNCIONARIO_ID' => $funcId,
                'AVALIACAO_CICLO' => $ciclo,
                'AVALIACAO_NOTA_FINAL' => $notaFinal,
                'AVALIACAO_STATUS' => 'enviada',
                'AVALIADOR_ID' => $avaliadorId,
                'created_at' => now(),
                'updated_at' => now(),
            ]);

            // Insere cada critÃ©rio
            foreach ($criterios as $c) {
                \Illuminate\Support\Facades\DB::table('AVALIACAO_CRITERIO')->insert([
                    'AVALIACAO_ID' => $avaliacaoId,
                    'CRITERIO_NOME' => $c['nome'] ?? 'â€”',
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

    // â”€â”€ MÃ³dulo de ExoneraÃ§Ã£o e Verbas RescisÃ³rias â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€
    require __DIR__ . '/exoneracao.php';

    // â”€â”€ Hora Extra, PlantÃ£o Extra e Folha por Secretaria â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€
    require __DIR__ . '/hora_extra.php';

    // â”€â”€ Verbas IndenizatÃ³rias Mensais â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€
    require __DIR__ . '/verba_indenizatoria.php';

    // â”€â”€ ConsignaÃ§Ãµes em Folha â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€
    require __DIR__ . '/consignacao.php';

    // â”€â”€ eSocial â€” Painel de Eventos â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€
    require __DIR__ . '/esocial.php';

    // â”€â”€ RPPS / IPAM â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€
    require __DIR__ . '/rpps.php';

    // â”€â”€ DiÃ¡rias e MissÃµes â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€
    require __DIR__ . '/diarias.php';

    // â”€â”€ EstagiÃ¡rios â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€
    require __DIR__ . '/estagiarios.php';

    // â”€â”€ AcumulaÃ§Ã£o de Cargos (LAT-01 / GAP-09) â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€
    require __DIR__ . '/acumulacao.php';

    // â”€â”€ TransparÃªncia PÃºblica (LAT-02 / GAP-10) â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€
    require __DIR__ . '/transparencia.php';

    // â”€â”€ PSS / Concursos (LAT-03 / GAP-11) â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€
    require __DIR__ . '/pss.php';

    // â”€â”€ Terceirizados (LAT-04 / GAP-12) â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€
    require __DIR__ . '/terceirizados.php';

    // â”€â”€ SAGRES / TCE-MA (LAT-05 / GAP-13) â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€
    require __DIR__ . '/sagres.php';

    // â”€â”€ Banco de Horas (GAP-05) â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€
    require __DIR__ . '/banco_horas.php';

    // â”€â”€ Atestados MÃ©dicos (GAP-06) â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€
    require __DIR__ . '/atestados.php';

    // â”€â”€ ERP / Fiscal â€” OrÃ§amento PÃºblico (ERP-1) â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€
    require __DIR__ . '/orcamento.php';

    // â”€â”€ ERP / Fiscal â€” ExecuÃ§Ã£o da Despesa (ERP-2) â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€
    require __DIR__ . '/execucao_despesa.php';

    // â”€â”€ ERP / Fiscal â€” Contabilidade PÃºblica PCASP (ERP-3) â”€â”€â”€â”€â”€â”€â”€
    require __DIR__ . '/contabilidade.php';

    // â”€â”€ ERP / Fiscal â€” Tesouraria (ERP-4) â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€
    require __DIR__ . '/tesouraria.php';

    // â”€â”€ ERP / Fiscal â€” Receita Municipal (ERP-5) â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€
    require __DIR__ . '/receita_municipal.php';

    // â”€â”€ ERP / Fiscal â€” Controle Externo SAGRES/SICONFI (ERP-6) â”€â”€
    require __DIR__ . '/controle_externo.php';

});



// ═══════════════════════════════════════════════════════════════════════
// AUTOCADASTRO — Rotas PÚBLICAS (sem autenticação)
// Devem ficar FORA do grupo auth — o candidato não tem login ainda.
// ═══════════════════════════════════════════════════════════════════════
Route::prefix('api/v3')->middleware(['web'])->group(function () {

    // Valida token e retorna dados pré-preenchidos (nome, email)
    Route::get('/autocadastro/{token}', function ($token) {
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
    Route::post('/autocadastro/{token}', function (\Illuminate\Http\Request $request, $token) {
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
});
