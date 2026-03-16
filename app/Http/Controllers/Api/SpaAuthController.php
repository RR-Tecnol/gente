<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Models\Usuario;
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

            $login = $request->input('USUARIO_LOGIN');
            $password = $request->input('USUARIO_SENHA');

            // Rate limiting básico por IP
            $throttleKey = Str::lower($login) . '|' . $request->ip();
            if (RateLimiter::tooManyAttempts($throttleKey, 5)) {
                $seconds = RateLimiter::availableIn($throttleKey);
                return response()->json([
                    'message' => "Muitas tentativas. Aguarde {$seconds} segundos.",
                ], 429);
            }

            // Normaliza: remover não-numéricos exceto para "admin"
            if ($login !== 'admin') {
                $login = preg_replace('/[^0-9]/', '', $login);
            }

            // Busca o usuário ativo
            $user = Usuario::where('USUARIO_LOGIN', $login)
                ->where('USUARIO_ATIVO', 1)
                ->first();

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
                'user' => [
                    'id' => $user->USUARIO_ID,
                    'nome' => $user->USUARIO_NOME,
                    'login' => $user->USUARIO_LOGIN,
                ],
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

        // Busca o perfil via relacionamento
        $perfilNome = null;
        try {
            $perfilNome = optional($user->usuarioPerfis()->with('perfil')->first())->perfil->PERFIL_NOME ?? null;
        } catch (\Exception $e) {
            // Ignora erros de relacionamento
        }

        // Fallback: usa o login para inferir o perfil quando não cadastrado
        if (!$perfilNome || strtolower(trim($perfilNome)) === 'usuário' || strtolower(trim($perfilNome)) === 'usuario') {
            $perfilNome = strtolower($user->USUARIO_LOGIN) === 'admin' ? 'admin' : 'funcionario';
        }

        return response()->json([
            'id' => $user->USUARIO_ID,
            'nome' => $user->USUARIO_NOME,
            'login' => $user->USUARIO_LOGIN,
            'email' => $user->USUARIO_EMAIL,
            'perfil' => $perfilNome,
            'alterar_senha' => (bool) $user->USUARIO_ALTERAR_SENHA,
        ]);
    }
}
