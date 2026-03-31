<?php

namespace App\Http\Middleware;

use Closure;
use Illuminate\Http\Request;

class SecurityHeaders
{
    public function handle(Request $request, Closure $next)
    {
        $response = $next($request);

        // Impede que o site seja carregado em iframe (proteção clickjacking)
        $response->headers->set('X-Frame-Options', 'SAMEORIGIN');

        // Impede MIME sniffing — browser obedece o Content-Type declarado
        $response->headers->set('X-Content-Type-Options', 'nosniff');

        // Força HTTPS por 1 ano (ativar SÓ após HTTPS estar configurado na VPS)
        // Remover o comentário quando o deploy estiver em produção com SSL
        // $response->headers->set('Strict-Transport-Security', 'max-age=31536000; includeSubDomains');

        // Política de referrer — não vaza URL completa para sites externos
        $response->headers->set('Referrer-Policy', 'strict-origin-when-cross-origin');

        // Desativa funcionalidades perigosas do browser
        $response->headers->set('Permissions-Policy',
            'geolocation=(), camera=(), microphone=(), payment=(), usb=()');

        // Content Security Policy — permite apenas recursos do próprio domínio
        // Nonces de script são geridos pelo Vue no build — esta política básica
        // cobre o backend Laravel (views Blade + API responses)
        $response->headers->set('Content-Security-Policy',
            "default-src 'self'; " .
            "script-src 'self' 'unsafe-inline' 'unsafe-eval'; " .
            "style-src 'self' 'unsafe-inline'; " .
            "img-src 'self' data: blob:; " .
            "font-src 'self' data:; " .
            "connect-src 'self'; " .
            "frame-ancestors 'self';"
        );

        // Remove header que revela versão do servidor
        $response->headers->remove('X-Powered-By');
        $response->headers->remove('Server');

        return $response;
    }
}
