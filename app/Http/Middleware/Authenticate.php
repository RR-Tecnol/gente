<?php

namespace App\Http\Middleware;

use Illuminate\Auth\Middleware\Authenticate as Middleware;

class Authenticate extends Middleware
{
    /**
     * Get the path the user should be redirected to when they are not authenticated.
     *
     * @param  \Illuminate\Http\Request  $request
     * @return string|null
     */
    protected function redirectTo($request)
    {
        if (! $request->expectsJson()) {
            // Rota raiz '/' agora serve o SPA Vue 3 que resolve /login via Vue Router
            return '/';
        }
        // JSON requests: retorna 401 sem redirect (SPA trata via axios interceptor)
        return null;
    }
}
