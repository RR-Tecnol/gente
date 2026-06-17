<?php

namespace App\Http\Middleware;

use Closure;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;

class AlterarSenha
{
    public function handle(Request $request, Closure $next)
    {
        if (! Auth::check()) {
            return $next($request);
        }

        $user = Auth::user();
        $primeiroAcesso = (int) ($user->USUARIO_PRIMEIRO_ACESSO ?? 0) === 1;
        $alterarSenha = (int) ($user->USUARIO_ALTERAR_SENHA ?? 0) === 1;

        if (! $primeiroAcesso && ! $alterarSenha) {
            return $next($request);
        }

        // Exceções obrigatórias para concluir o fluxo de troca.
        if (
            $request->is('api/auth/me')
            || $request->is('api/auth/logout')
            || $request->is('api/auth/change-password')
        ) {
            return $next($request);
        }

        return response()->json(['error' => 'PASSWORD_CHANGE_REQUIRED'], 412);
    }
}
