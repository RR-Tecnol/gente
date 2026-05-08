<?php

use App\Models\Usuario;
use App\Support\ChangePasswordSessionResolver;
use App\Support\RequestSigning;
use App\Support\SpaAuthPayloadBuilder;
use App\Support\UsuarioLoginResolver;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Route;
use Illuminate\Support\Str;
use App\Events\HoneytokenTriggered;

// Controllers legados — importados automaticamente (fix_controllers_use.php)
use App\Http\Controllers\AfastamentoController;
use App\Http\Controllers\AnexoAfastamentoController;
use App\Http\Controllers\AnexoFeriasController;
use App\Http\Controllers\AplicacaoController;
use App\Http\Controllers\AtribuicaoConfigController;
use App\Http\Controllers\AtribuicaoController;
use App\Http\Controllers\AtribuicaoLotacaoController;
use App\Http\Controllers\AtribuicaoLotacaoEventoController;
use App\Http\Controllers\BairroController;
use App\Http\Controllers\BancoController;
use App\Http\Controllers\CargoController;
use App\Http\Controllers\CepController;
use App\Http\Controllers\CidadeController;
use App\Http\Controllers\ComentarioController;
use App\Http\Controllers\ConselhoController;
use App\Http\Controllers\ContatoController;
use App\Http\Controllers\DependenteController;
use App\Http\Controllers\DetalheEscalaAutorizaController;
use App\Http\Controllers\DetalheEscalaController;
use App\Http\Controllers\DetalheEscalaItemController;
use App\Http\Controllers\DocumentoController;
use App\Http\Controllers\DossieController;
use App\Http\Controllers\EscalaController;
use App\Http\Controllers\EventoController;
use App\Http\Controllers\EventoVinculoController;
use App\Http\Controllers\FaltaAtrasoController;
use App\Http\Controllers\FeriadoController;
use App\Http\Controllers\FeriasAfastamentoController;
use App\Http\Controllers\FeriasController;
use App\Http\Controllers\FimLotacaoController;
use App\Http\Controllers\FolhaController;
use App\Http\Controllers\FuncaoController;
use App\Http\Controllers\FuncionarioController;
use App\Http\Controllers\HistoricoEscalaController;
use App\Http\Controllers\HistoricoEventoController;
use App\Http\Controllers\HistoricoParametroController;
use App\Http\Controllers\LotacaoController;
use App\Http\Controllers\LotacaoEventoController;
use App\Http\Controllers\OcupacaoController;
use App\Http\Controllers\ParametroFinanceiroController;
use App\Http\Controllers\PerfilController;
use App\Http\Controllers\PessoaBancoController;
use App\Http\Controllers\PessoaConselhoController;
use App\Http\Controllers\PessoaController;
use App\Http\Controllers\PessoaOcupacaoController;
use App\Http\Controllers\PessoaProfissaoController;
use App\Http\Controllers\PreCadastroController;
use App\Http\Controllers\ProgramaController;
use App\Http\Controllers\RelatorioController;
use App\Http\Controllers\ScriptController;
use App\Http\Controllers\SetorAtribuicaoController;
use App\Http\Controllers\SetorController;
use App\Http\Controllers\SubstituicaoEscalaController;
use App\Http\Controllers\TabelaGenericaController;
use App\Http\Controllers\TabelaImpostoController;
use App\Http\Controllers\TermoController;
use App\Http\Controllers\TermoUsuarioController;
use App\Http\Controllers\TipoAlertaController;
use App\Http\Controllers\TipoDocumentoController;
use App\Http\Controllers\TributacaoController;
use App\Http\Controllers\TurnoController;
use App\Http\Controllers\UnidadeController;
use App\Http\Controllers\UsuarioController;
use App\Http\Controllers\UsuarioPerfilController;
use App\Http\Controllers\UsuarioSetorController;
use App\Http\Controllers\UsuarioUnidadeController;
use App\Http\Controllers\UfController;
use App\Http\Controllers\VigenciaImpostoController;
use App\Http\Controllers\VinculoController;


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

// Verificação pública da autenticidade de Portarias (Fase 1: hash + QR).
Route::get('/verificar-portaria/{hash}', function (string $hash) {
    try {
        $hash = trim($hash);
        if ($hash === '' || !\Illuminate\Support\Facades\Schema::hasTable('DOCUMENTOS_SERVIDOR')) {
            return response()->view('portarias.verificacao', ['valido' => false, 'motivo' => 'Hash inválido.'], 404);
        }

        $q = \Illuminate\Support\Facades\DB::table('DOCUMENTOS_SERVIDOR as d')
            ->leftJoin('FUNCIONARIO as f', 'f.FUNCIONARIO_ID', '=', 'd.FUNCIONARIO_ID')
            ->leftJoin('PESSOA as p', 'p.PESSOA_ID', '=', 'f.PESSOA_ID')
            ->where('d.TIPO_DOCUMENTO', 'PORTARIA_LOTACAO');

        if (\Illuminate\Support\Facades\Schema::hasColumn('DOCUMENTOS_SERVIDOR', 'HASH_AUTENTICIDADE')) {
            $q->where('d.HASH_AUTENTICIDADE', $hash);
        } else {
            return response()->view('portarias.verificacao', ['valido' => false, 'motivo' => 'Verificação por hash não habilitada neste ambiente.'], 404);
        }

        $doc = $q->orderByDesc('d.created_at')
            ->select('d.DOCUMENTO_SERVIDOR_ID', 'd.FUNCIONARIO_ID', 'd.METADADOS_JSON', 'd.ARQUIVO_PATH', 'd.created_at', 'p.PESSOA_NOME')
            ->first();

        if (! $doc) {
            return response()->view('portarias.verificacao', ['valido' => false, 'motivo' => 'Documento não encontrado para este hash.'], 404);
        }

        $meta = json_decode((string) ($doc->METADADOS_JSON ?? '{}'), true);
        if (!is_array($meta)) {
            $meta = [];
        }

        return response()->view('portarias.verificacao', [
            'valido' => true,
            'hash' => $hash,
            'documento_id' => $doc->DOCUMENTO_SERVIDOR_ID,
            'funcionario_id' => $doc->FUNCIONARIO_ID,
            'funcionario_nome' => $doc->PESSOA_NOME,
            'origem_unidade' => $meta['origem_unidade'] ?? null,
            'origem_setor' => $meta['origem_setor'] ?? null,
            'destino_unidade' => $meta['destino_unidade'] ?? null,
            'destino_setor' => $meta['destino_setor'] ?? null,
            'justificativa' => $meta['justificativa'] ?? null,
            'arquivo_path' => $doc->ARQUIVO_PATH,
            'emitido_em' => $doc->created_at,
        ]);
    } catch (\Throwable $e) {
        return response()->view('portarias.verificacao', ['valido' => false, 'motivo' => 'Falha ao validar autenticidade.'], 500);
    }
});

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
Route::prefix('api/auth')->middleware(['web'])->group(function () { // SEC-05: rate limit aplicado apenas no login

    // GET /api/auth/me — Usuário autenticado + matriz de perfis + escopo organizacional (RBAC / SoD)
    Route::get('/me', function (Request $request) {
        if (!Auth::check()) {
            return response()->json(['error' => 'Não autenticado'], 401);
        }
        $user = Auth::user();

        return response()->json(SpaAuthPayloadBuilder::forAuthenticatedUser($user));
    });

    // POST /api/auth/login â€” Autentica e inicia sessÃ£o
    Route::post('/login', function (Request $request) {
        $loginBruto = (string) $request->input('USUARIO_LOGIN');
        $login = $loginBruto;
        $password = $request->input('USUARIO_SENHA');

        if (!$login || !$password) {
            return response()->json(['error' => 'Credenciais não informadas'], 422);
        }

        // Diagnóstico: confirma que o backend recebe o mesmo texto que o front enviou (sem senha).
        if (config('app.debug')) {
            \Illuminate\Support\Facades\Log::info('api.auth.login USUARIO_LOGIN', [
                'bruto_recebido' => $loginBruto,
                'apos_lookup' => \App\Support\LoginLookupNormalizer::forDatabaseLookup($loginBruto),
            ]);
        }

        // SEC-PROD-03: verificar bloqueio por IP
        // Em ambiente local/dev usamos limite alto para evitar lock involuntário durante testes.
        $maxTentativas = app()->isProduction() ? 5 : 100;
        $janelaMinutos = app()->isProduction() ? 15 : 5;
        $ip = $request->ip();
        $janela = now()->subMinutes($janelaMinutos);
        $tentativas = \Illuminate\Support\Facades\DB::table('LOGIN_ATTEMPTS')
            ->where('IP', $ip)
            ->where('SUCESSO', false)
            ->where('TENTATIVA_EM', '>=', $janela)
            ->count();

        if ($tentativas >= $maxTentativas) {
            \Illuminate\Support\Facades\Log::channel('security')->warning('login_bloqueado_ip', ['ip' => $ip, 'tentativas' => $tentativas]);
            return response()->json([
                'error' => "Muitas tentativas incorretas. Aguarde {$janelaMinutos} minutos.",
                'bloqueado_ate' => now()->addMinutes($janelaMinutos)->toIso8601String(),
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

        // CPF (legado) ou e-mail (personas); ver App\Support\LoginLookupNormalizer
        $login = \App\Support\LoginLookupNormalizer::forDatabaseLookup((string) $login);

        $user = UsuarioLoginResolver::resolveByNormalizedLogin($login);

        if (!$user) {
            \Illuminate\Support\Facades\DB::table('LOGIN_ATTEMPTS')->insert(['IP' => $ip, 'LOGIN' => $login, 'SUCESSO' => false, 'TENTATIVA_EM' => now()]);
            return response()->json(['error' => 'Credenciais inválidas ou usuário inativo'], 401);
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
        if (RequestSigning::enabled()) {
            RequestSigning::ensureSessionSecret($request);
        }
        try {
            $user->USUARIO_ULTIMO_ACESSO = now();
            $user->save();
        } catch (\Throwable $e) {
        }

        return response()->json([
            'ok' => true,
            'user' => SpaAuthPayloadBuilder::forAuthenticatedUser($user),
        ]);
    })->middleware('throttle:20,1');

    // POST /api/auth/logout â€” Encerra sessÃ£o
    Route::post('/logout', function (Request $request) {
        Auth::logout();
        $request->session()->invalidate();
        $request->session()->regenerateToken();
        return response()->json(['ok' => true]);
    })->name('api.auth.logout');

    // POST /api/auth/change-password â€” Troca de senha
    Route::post('/change-password', function (Request $request) {
        if (!Auth::check()) {
            return response()->json(['error' => 'Não autenticado'], 401);
        }
        $sessionUser = Auth::user();
        if (! $sessionUser instanceof Usuario) {
            return response()->json(['error' => 'Sessão de usuário inválida'], 401);
        }

        [$user, $idsCandidatos, $sessaoReapontada] = ChangePasswordSessionResolver::ensureCanonical($sessionUser);
        Log::info('api.auth.change_password.usuario_canonico_resolvido', [
            'session_usuario_id' => (int) $sessionUser->USUARIO_ID,
            'usuario_id_efetivo' => (int) $user->USUARIO_ID,
            'ids_candidatos' => $idsCandidatos,
            'sessao_reapontada' => (bool) $sessaoReapontada,
        ]);

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

        if (!\Hash::check($senhaAtual, $user->USUARIO_SENHA)) {
            return response()->json(['error' => 'Senha atual incorreta'], 401);
        }

        $user->USUARIO_SENHA = bcrypt($senhaNova);
        $user->USUARIO_ALTERAR_SENHA = 0;
        if (\Illuminate\Support\Facades\Schema::hasColumn('USUARIO', 'USUARIO_PRIMEIRO_ACESSO')) {
            $user->USUARIO_PRIMEIRO_ACESSO = 0;
        }
        $user->save();
        $user->refresh();

        return response()->json([
            'ok' => true,
            'message' => 'Senha alterada com sucesso',
            'user' => SpaAuthPayloadBuilder::forAuthenticatedUser($user),
        ]);
    });

});

// Frente 3: rota canário (não exposta no Vue) — scans automatizados disparam alarme e blocklist
Route::get('/api/v3/admin/dump-database-config', function (Request $request) {
    event(new HoneytokenTriggered('canary_route', $request, null));

    return response()->json(['erro' => 'Acesso proibido.', 'code' => 'GENTE_TRIPWIRE'], 403);
})->middleware('web');

// Sentinela de Integridade (Área de TI): status dos probes críticos.
Route::prefix('api/v3')->middleware(['web', 'auth', 'alterar.senha', 'honey.tripwire', 'verify.request.signature', 'tenant.scope', 'audit'])->group(function () {
    Route::get('/health', [\App\Http\Controllers\Api\IntegritySentinelController::class, 'show'])->name('api.v3.health');
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
        Route::prefix('api/v3')->middleware(['web', 'auth', 'alterar.senha', 'honey.tripwire', 'verify.request.signature', 'tenant.scope', 'audit'])->group(function () {

            // â”€â”€ FuncionÃ¡rio: buscar perfil completo para o SPA â”€â”€â”€â”€â”€â”€â”€â”€
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
                    'funcionario' => [
                        'FUNCIONARIO_ID' => $func->FUNCIONARIO_ID,
                        'PESSOA_ID' => $func->PESSOA_ID,
                        'USUARIO_ID' => $func->USUARIO_ID,
                        'FUNCIONARIO_MATRICULA' => $func->FUNCIONARIO_MATRICULA,
                        'FUNCIONARIO_DATA_INICIO' => $func->FUNCIONARIO_DATA_INICIO,
                        'FUNCIONARIO_DATA_FIM' => $func->FUNCIONARIO_DATA_FIM,
                        'FUNCIONARIO_OBSERVACAO' => $func->FUNCIONARIO_OBSERVACAO,
                        'pessoa' => $func->pessoa ? [
                            'PESSOA_NOME' => $func->pessoa->PESSOA_NOME,
                            'PESSOA_CPF_NUMERO' => $func->pessoa->PESSOA_CPF_NUMERO,
                            'PESSOA_NASCIMENTO' => $func->pessoa->PESSOA_NASCIMENTO,
                            'PESSOA_EMAIL' => $func->pessoa->PESSOA_EMAIL,
                            'PESSOA_CELULAR' => $func->pessoa->PESSOA_CELULAR,
                        ] : null,
                        'setor' => optional($lotacaoAtiva?->setor)->SETOR_NOME,
                        'unidade' => optional($lotacaoAtiva?->setor?->unidade)->UNIDADE_NOME,
                        'atribuicao' => optional($lotacaoAtiva?->atribuicaoLotacoes->last()?->atribuicao)->ATRIBUICAO_NOME,
                        'vinculo' => optional($lotacaoAtiva?->vinculo)->VINCULO_NOME,
                    ],
                    'holerites' => $holerites,
                ]);
            });

            // â”€â”€ FuncionÃ¡rio: atualizar dados pessoais â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€
            Route::put('/funcionarios/{id}', function ($id, \Illuminate\Http\Request $request) {
                $func = \App\Models\Funcionario::with('pessoa')->find($id);
                if (!$func)
                    return response()->json(['message' => 'Funcionário não encontrado'], 404);

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
                    return response()->json(['message' => 'Não encontrado'], 404);

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
                $hoje = now()->toDateString();
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
                        'ativo' => empty($f->FUNCIONARIO_DATA_FIM) || $f->FUNCIONARIO_DATA_FIM > $hoje,
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
                    $deleted = \Illuminate\Support\Facades\DB::table('PESSOA_DEPENDENTE')
                        ->where('PESSOA_DEPENDENTE_ID', $depId)
                        ->where('FUNCIONARIO_ID', $id)
                        ->delete();
                    if (!$deleted) {
                        return response()->json(['erro' => 'Dependente não encontrado para este funcionário.'], 404);
                    }
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

// App mobile de ponto — JWT (login sem sessão Laravel; demais rotas com Bearer)
Route::prefix('api/v3')->middleware(['web'])->group(function () {
    require __DIR__ . '/ponto_app.php';
});

// â•?â•?â•? API V3 â€” Endpoints para o SPA Vue 3 â•?â•?â•?â•?â•?â•?â•?â•?â•?â•?â•?â•?â•?â•?â•?â•?â•?â•?â•?â•?â•?â•?â•?â•?â•?â•?â•?â•?â•?â•?â•?
Route::prefix('api/v3')->middleware(['web', 'auth', 'alterar.senha', 'honey.tripwire', 'verify.request.signature', 'tenant.scope', 'audit'])->group(function () {
    require __DIR__ . '/api_v3_auth_part1.php';
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

    Route::prefix('perfil')->middleware('perfil:ADMINISTRADOR,GESTOR,Administrador')->group(function () {
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

    /*
     * FASE-4-COMENTADO 08/05/2026 (decisão 7.a do MAPA): rotas tabela_generica legadas comentadas.
     * SPA Vue 3 consome /api/v3/tabelas-genericas/* (definido em api_v3_*.php).
     * TabelaGenericaController preservado em app/Http/Controllers/ para reaproveitamento futuro.
     * Para reativar, descomentar este bloco.
     *
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
    */

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

    /*
     * FASE-4-COMENTADO 08/05/2026 (decisão 7.b do MAPA): rotas aplicacao legadas comentadas.
     * Cadastro de Aplicação RBAC v1 (Vue 2). Substituído por /api/v3/admin/* (RBAC v3).
     * AplicacaoController preservado em app/Http/Controllers/ para reaproveitamento futuro.
     *
    Route::prefix('aplicacao')->middleware('perfil:ADMINISTRADOR,Administrador')->group(function () {
        Route::get('/', [AplicacaoController::class, "view"]);
        Route::get('view', [AplicacaoController::class, "view"])->name('aplicacao.view');
        Route::get('search', [AplicacaoController::class, "search"]);
        Route::post('create', [AplicacaoController::class, "create"]);
        Route::delete('delete', [AplicacaoController::class, "delete"]);
        Route::put('update', [AplicacaoController::class, "update"]);
        Route::match(['get', 'post'], 'list', [AplicacaoController::class, "list"]);
    });
    */

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

    // FASE-4-REMOVIDO 08/05/2026 (decisão 7.c do MAPA): bloco Route::prefix('programa') removido.
    // Tabela PROGRAMA criada apenas para import legado. Sem view, sem consumer SPA.
    // ProgramaController preservado em app/Http/Controllers/ caso surja necessidade futura.

    Route::prefix('pre-cadastro')->group(function () {
        Route::get('/', [PreCadastroController::class, 'view'])->name('view.pre-cadastro');
        ;
        Route::get('view', [PreCadastroController::class, "view"]);
        Route::post('inserir', [PreCadastroController::class, "inserir"]);
        Route::put('alterar', [PreCadastroController::class, "alterar"]);
    });

    // FASE-4-REMOVIDO 08/05/2026 (decisão 7.d do MAPA): bloco Route::prefix('script') removido.
    // Editor SQL ad-hoc legado — vetor de SQL injection sem ACL nova.
    // Decisão de segurança: remover endpoint mesmo mantendo ScriptController.

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

    // FASE-4-COMENTADO 08/05/2026 (decisão 6 do MAPA): rota CEP legada removida.
    // SPA Vue 3 (AutocadastroView) consome viacep.com.br diretamente.
    // CepController preservado em app/Http/Controllers/CepController.php para uso futuro.
    // Route::get('cep/{cep}', [CepController::class, 'service']);

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
Route::prefix('api/v3')->middleware(['web', 'auth', 'alterar.senha', 'honey.tripwire', 'verify.request.signature', 'tenant.scope', 'audit'])->group(function () {
    require __DIR__ . '/api_v3_auth_part2.php';
});

Route::prefix('api/v3')->middleware(['web'])->group(function () {
    require __DIR__ . '/api_v3_web_part1.php';
});



// ═══════════════════════════════════════════════════════════════════════
// AUTOCADASTRO — Rotas PÚBLICAS (sem autenticação)
// Devem ficar FORA do grupo auth — o candidato não tem login ainda.
// ═══════════════════════════════════════════════════════════════════════

Route::prefix('api/v3')->middleware(['web'])->group(function () {
    require __DIR__ . '/api_v3_autocadastro_public_legacy.php';
});

