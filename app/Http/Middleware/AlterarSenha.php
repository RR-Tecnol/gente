<?php

namespace App\Http\Middleware;

use Closure;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Route;

class AlterarSenha
{
    /**
     * Handle an incoming request.
     *
     * @param  \Illuminate\Http\Request  $request
     * @param  \Closure  $next
     * @return mixed
     */
    public function handle(Request $request, Closure $next)
    {
        if (Auth::user()->USUARIO_SENHA == md5('SISGEP123') ) {
            if (route('usuario.alteracao_senha') != route(Route::currentRouteName()))
                return redirect(route('usuario.alteracao_senha'));
        }
        return $next($request);
    }
}
