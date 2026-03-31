<?php

namespace App\Http\Middleware;

use Illuminate\Foundation\Http\Middleware\VerifyCsrfToken as Middleware;

class VerifyCsrfToken extends Middleware
{
    /**
     * The URIs that should be excluded from CSRF verification.
     * As rotas da SPA Vue são protegidas via sessão (Auth::login),
     * não precisam de CSRF token pois o Axios já envia withCredentials.
     *
     * @var array
     */
    protected $except = [
        'api/auth/login',
        'api/auth/logout',
        'api/auth/me',
        // Autocadastro: rotas públicas — candidato não tem sessão/CSRF
        'api/v3/autocadastro/*',
    ];
}
