<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Models\Usuario;
use App\Support\LoginLookupNormalizer;
use App\Support\SpaAuthPayloadBuilder;
use App\Support\UsuarioLoginResolver;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Facades\RateLimiter;
use Illuminate\Support\Str;

class SpaAuthController extends Controller
{
    /**
     * Login dedicado para o SPA Vue 3.
     * Usa Auth::attempt manual e retorna sempre JSON (nunca Redirect).
     */
    public function login(Request $request)
    {
        try {
            // Garante leitura do JSON body independente do Content-Type
            if ($request->isJson() || $request->getContent()) {
                $json = json_decode($request->getContent(), true);
                if (is_array($json)) {
                    $request->merge($json);
                }
            }

            // Validação básica
            $request->validate([
                'USUARIO_LOGIN' => 'required|string',
                'USUARIO_SENHA' => 'required|string',
            ]);

            $loginBruto = (string) $request->input('USUARIO_LOGIN');
            $password = $request->input('USUARIO_SENHA');

            if (config('app.debug')) {
                \Illuminate\Support\Facades\Log::info('SpaAuthController.login USUARIO_LOGIN', [
                    'bruto_recebido' => $loginBruto,
                    'apos_lookup' => LoginLookupNormalizer::forDatabaseLookup($loginBruto),
                ]);
            }

            // Rate limiting básico por IP
            $throttleKey = Str::lower($loginBruto) . '|' . $request->ip();
            if (RateLimiter::tooManyAttempts($throttleKey, 5)) {
                $seconds = RateLimiter::availableIn($throttleKey);
                return response()->json([
                    'message' => "Muitas tentativas. Aguarde {$seconds} segundos.",
                ], 429);
            }

            // CPF só-dígitos (legado) ou e-mail em USUARIO_LOGIN (personas Lab)
            $login = LoginLookupNormalizer::forDatabaseLookup($loginBruto);

            $user = UsuarioLoginResolver::resolveByNormalizedLogin($login);

            if (!$user) {
                RateLimiter::hit($throttleKey, 60 * 30);
                return response()->json(['message' => 'Usuário não encontrado ou inativo.'], 401);
            }

            // Migração transparente MD5 → bcrypt
            if ($user->USUARIO_SENHA === md5($password)) {
                $user->USUARIO_SENHA = bcrypt($password);
                $user->USUARIO_ALTERAR_SENHA = 1;
                $user->save();
            }

            // Verifica a senha
            if (!Hash::check($password, $user->USUARIO_SENHA)) {
                RateLimiter::hit($throttleKey, 60 * 30);
                return response()->json(['message' => 'Senha incorreta.'], 401);
            }

            // Verifica vigência
            if ($user->USUARIO_VIGENCIA !== null && $user->USUARIO_VIGENCIA < date('Y-m-d')) {
                return response()->json(['message' => 'Usuário com acesso expirado.'], 403);
            }

            // Loga o usuário na sessão Web (stateful)
            Auth::login($user, false);
            $request->session()->regenerate();

            // Atualiza último acesso (ignora erro caso coluna não exista)
            try {
                $user->USUARIO_ULTIMO_ACESSO = date('Y-m-d H:i:s');
                $user->save();
            } catch (\Exception $ex) {
                \Log::warning('SpaAuth: não foi possível atualizar USUARIO_ULTIMO_ACESSO: ' . $ex->getMessage());
            }

            RateLimiter::clear($throttleKey);

            return response()->json([
                'message' => 'Autenticado com sucesso.',
                'ok' => true,
                'user' => SpaAuthPayloadBuilder::forAuthenticatedUser($user),
            ], 200);

        } catch (\Illuminate\Validation\ValidationException $ve) {
            // Re-lança para que o Laravel retorne 422 com os erros de validação
            \Log::warning('SpaAuth: ValidationException: ' . json_encode($ve->errors()));
            throw $ve;

        } catch (\Exception $e) {
            \Log::error('SpaAuthController@login falhou: ' . $e->getMessage(), [
                'file' => $e->getFile(),
                'line' => $e->getLine(),
                'input' => $request->only('USUARIO_LOGIN'),
            ]);

            return response()->json([
                'message' => 'Erro interno no servidor.',
                'debug' => config('app.debug') ? $e->getMessage() : null,
            ], 500);
        }
    }

    /**
     * Logout da sessão SPA.
     */
    public function logout(Request $request)
    {
        Auth::logout();
        $request->session()->invalidate();
        $request->session()->regenerateToken();

        return response()->json(['message' => 'Sessão encerrada.'], 200);
    }

    /**
     * Retorna o usuário autenticado atual (para o Pinia/Store).
     */
    public function me(Request $request)
    {
        if (!Auth::check()) {
            return response()->json(['message' => 'Não autenticado.'], 401);
        }

        $user = Auth::user();

        return response()->json(SpaAuthPayloadBuilder::forAuthenticatedUser($user));
    }

    /**
     * Chamado pelo login em routes/web.php e por {@see login()} aqui.
     * Em produção não altera dados (no-op).
     */
    public function applyDevAdminFuncionarioVinculo(Usuario $user): void
    {
        // Compatibilidade retroativa: bypass desativado por segurança.
    }

    private function ensureAdminFuncionarioVinculo(Usuario $user): void
    {
        // Bypass removido por segurança: vínculo técnico automático desativado.
        return;
    }

    private function ensureAdminLotacao(int $funcionarioId): void
    {
        if (
            $funcionarioId <= 0 ||
            !\Illuminate\Support\Facades\Schema::hasTable('LOTACAO') ||
            !\Illuminate\Support\Facades\Schema::hasTable('SETOR')
        ) {
            return;
        }

        $lotAtiva = \Illuminate\Support\Facades\DB::table('LOTACAO')
            ->where('FUNCIONARIO_ID', $funcionarioId)
            ->whereNull('LOTACAO_DATA_FIM')
            ->exists();
        if ($lotAtiva) {
            // Trava de segurança: se houver duplicidade ativa legada, mantém somente a mais recente.
            $ativos = \Illuminate\Support\Facades\DB::table('LOTACAO')
                ->where('FUNCIONARIO_ID', $funcionarioId)
                ->whereNull('LOTACAO_DATA_FIM')
                ->orderByDesc('LOTACAO_DATA_INICIO')
                ->orderByDesc('LOTACAO_ID')
                ->get(['LOTACAO_ID']);
            if ($ativos->count() > 1) {
                $keeperId = (int) ($ativos->first()->LOTACAO_ID ?? 0);
                if ($keeperId > 0) {
                    $payload = ['LOTACAO_DATA_FIM' => now()->toDateString()];
                    if (\Illuminate\Support\Facades\Schema::hasColumn('LOTACAO', 'LOTACAO_OBSERVACAO')) {
                        $payload['LOTACAO_OBSERVACAO'] = 'SANEAMENTO AUTOMÁTICO: DUPLICIDADE DE LOTAÇÃO ATIVA';
                    }
                    \Illuminate\Support\Facades\DB::table('LOTACAO')
                        ->where('FUNCIONARIO_ID', $funcionarioId)
                        ->whereNull('LOTACAO_DATA_FIM')
                        ->where('LOTACAO_ID', '<>', $keeperId)
                        ->update($payload);
                }
            }
            return;
        }

        $setorId = \Illuminate\Support\Facades\DB::table('SETOR')->value('SETOR_ID');
        if (!$setorId) {
            return;
        }

        $dados = ['FUNCIONARIO_ID' => $funcionarioId, 'SETOR_ID' => $setorId];
        $lotCols = \Illuminate\Support\Facades\Schema::getColumnListing('LOTACAO');
        if (in_array('LOTACAO_DATA_INICIO', $lotCols, true))
            $dados['LOTACAO_DATA_INICIO'] = now()->toDateString();
        if (in_array('LOTACAO_DATA_CADASTRO', $lotCols, true))
            $dados['LOTACAO_DATA_CADASTRO'] = now()->toDateString();
        if (in_array('LOTACAO_DATA_ATUALIZACAO', $lotCols, true))
            $dados['LOTACAO_DATA_ATUALIZACAO'] = now()->toDateString();

        \Illuminate\Support\Facades\DB::table('LOTACAO')->insert($dados);
    }
}
