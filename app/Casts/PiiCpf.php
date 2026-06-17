<?php

namespace App\Casts;

use App\Support\PiiBlindIndex;
use Illuminate\Contracts\Database\Eloquent\CastsAttributes;
use Illuminate\Support\Facades\Crypt;

/**
 * CPF: armazenamento em claro (11 dígitos) ou cifrado (Laravel Crypt = AES-256-CBC com chave derivada de APP_KEY).
 * Quando cifrado, o blind index (PESSOA_CPF_HASH) continua a permitir WHERE = em buscas.
 */
class PiiCpf implements CastsAttributes
{
    public function get($model, string $key, $value, array $attributes)
    {
        if ($value === null || $value === '') {
            return null;
        }
        if (! config('gente.pii.cpf_field_encrypted', false)) {
            return is_string($value) ? PiiBlindIndex::normalizeCpf($value) : $value;
        }
        try {
            return Crypt::decryptString((string) $value);
        } catch (\Throwable $e) {
            // Legado: texto claro no banco antes da migração
            return PiiBlindIndex::normalizeCpf((string) $value);
        }
    }

    public function set($model, string $key, $value, array $attributes)
    {
        if ($value === null || $value === '') {
            return null;
        }
        $digits = PiiBlindIndex::normalizeCpf((string) $value);
        if (config('gente.pii.cpf_field_encrypted', false)) {
            return Crypt::encryptString($digits);
        }

        return $digits;
    }
}
