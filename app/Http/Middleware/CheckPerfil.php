<?php

namespace App\Http\Middleware;

use Closure;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;

class CheckPerfil
{
    /**
     * Handle an incoming request.
     *
     * @param  \Illuminate\Http\Request  $request
     * @param  \Closure  $next
     * @param  mixed  ...$perfis
     * @return mixed
     */
    public function handle(Request $request, Closure $next, ...$perfis)
    {
        if (!Auth::check()) {
            return redirect('login');
        }

        $user = Auth::user();
        $user->load('usuarioPerfis.perfil');

        $temAcesso = false;

        foreach ($user->usuarioPerfis as $usuarioPerfil) {
            // Verifica se o vínculo do perfil está ativo para o usuário
            if ($usuarioPerfil->USUARIO_PERFIL_ATIVO == 1 && $usuarioPerfil->perfil) {
                // Checa se o nome do perfil está na lista de perfis permitidos
                if (in_array($usuarioPerfil->perfil->PERFIL_NOME, $perfis)) {
                    $temAcesso = true;
                    break;
                }

                // Se nenhum perfil for passado como parâmetro, o usuário 
                // precisa apenas ter algum perfil ativo.
                if (empty($perfis)) {
                    $temAcesso = true;
                    break;
                }
            }
        }

        if (!$temAcesso) {
            if ($request->expectsJson()) {
                return response()->json(['erro' => 'Acesso negado. Perfil não autorizado.'], 403);
            }
            abort(403, 'Acesso negado. Você não tem permissão para acessar este recurso.');
        }

        return $next($request);
    }
}
