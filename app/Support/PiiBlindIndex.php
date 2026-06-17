<?php

namespace App\Support;

/**
 * Blind index (HMAC) para busca exata de PII sem expor o valor no armazenamento.
 * O segredo é independente de APP_KEY (GENTE_PII_BLIND_SALT) — rotação do salt requer reindexar hashes.
 */
final class PiiBlindIndex
{
    public static function secret(): string
    {
        $s = (string) config('gente.pii.blind_salt', '');

        return $s !== '' ? $s : (string) config('app.key', '');
    }

    /**
     * CPF — apenas dígitos, 11 posições (completar com zeros à esquerda se o schema legado tiver 10).
     */
    public static function normalizeCpf(string $value): string
    {
        $d = preg_replace('/\D+/', '', $value) ?? '';

        return str_pad(substr($d, 0, 11), 11, '0', STR_PAD_LEFT);
    }

    /**
     * Hex de 64 caracteres (HMAC-SHA256 do CPF normalizado com salt de aplicação).
     */
    public static function cpfHash(string $plainCpf): string
    {
        $n = self::normalizeCpf($plainCpf);

        return hash_hmac('sha256', $n, self::secret());
    }
}
