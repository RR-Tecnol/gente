<?php

namespace App\Support;

use Illuminate\Http\Request;
use Illuminate\Support\Str;

/**
 * HMAC de integridade: método + path + timestamp (ms) + corpo bruto.
 * O segredo por sessão evita reutilizar assinaturas entre utilizadores; o timestamp (±30s) atenua replay.
 */
final class RequestSigning
{
    public static function enabled(): bool
    {
        return (bool) config('gente.request_signature.enabled', false);
    }

    public static function leewayMs(): int
    {
        return max(5_000, (int) config('gente.request_signature.leeway_ms', 30_000));
    }

    /**
     * Gera ou reutiliza segredo de assinatura na sessão (após auth).
     */
    public static function ensureSessionSecret(Request $request): string
    {
        $k = (string) config('gente.request_signature.session_key', 'gente_request_signing_secret');
        $s = $request->session()->get($k);
        if (is_string($s) && strlen($s) >= 32) {
            return $s;
        }
        $s = Str::random(64);
        $request->session()->put($k, $s);

        return $s;
    }

    public static function expectedHexSignature(string $method, string $path, string $timestampMs, string $rawBody, string $secret): string
    {
        $method = strtoupper($method);
        $payload = $method . "\n" . $path . "\n" . $timestampMs . "\n" . $rawBody;

        return hash_hmac('sha256', $payload, $secret, false);
    }
}
