<?php

namespace App\Support;

use Illuminate\Support\Facades\Log;

/**
 * Redação de PII em contexto de log e extensão segura do Log.
 */
final class GentePii
{
    /** @var list<string> */
    private static $sensitiveKeys = [
        'PESSOA_CPF', 'PESSOA_CPF_NUMERO', 'cpf', 'CPF', 'PESSOA_RG', 'PESSOA_NOME', 'PESSOA_CPF_HASH',
        'USUARIO_CPF', 'password', 'senha', 'PESSOA_PIS_PASEP',
    ];

    /**
     * @param  array<string, mixed>  $context
     * @return array<string, mixed>
     */
    public static function redactForLog(array $context): array
    {
        $out = [];
        foreach ($context as $k => $v) {
            if (is_string($k) && self::isSensitiveKey($k)) {
                $out[$k] = '***';
            } elseif (is_array($v)) {
                $out[$k] = self::redactForLog($v);
            } else {
                $out[$k] = $v;
            }
        }

        return $out;
    }

    public static function isSensitiveKey(string $key): bool
    {
        $uk = strtoupper($key);
        foreach (self::$sensitiveKeys as $s) {
            $us = strtoupper($s);
            if ($uk === $us || strpos($uk, $us) !== false) {
                return true;
            }
        }

        return false;
    }

    public static function info(string $message, array $context = []): void
    {
        Log::info($message, self::redactForLog($context));
    }
}
