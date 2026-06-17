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
            "script-src 'self' 'unsafe-inline' 'unsafe-eval' https://www.google.com https://www.gstatic.com; " .
            "style-src 'self' 'unsafe-inline' https://fonts.googleapis.com https://cdn.jsdelivr.net; " .
            "style-src-elem 'self' 'unsafe-inline' https://fonts.googleapis.com https://cdn.jsdelivr.net; " .
            "img-src 'self' data: blob: https://api.dicebear.com https://*.tile.openstreetmap.org; " .
            "font-src 'self' data: https://fonts.gstatic.com https://cdn.jsdelivr.net; " .
            "connect-src 'self' https://viacep.com.br https://www.google.com; " .
            "frame-src 'self' https://www.google.com; " .
            "frame-ancestors 'self';"
        );

        // Remove header que revela versão do servidor
        $response->headers->remove('X-Powered-By');
        $response->headers->remove('Server');

        return $response;
    }
}
