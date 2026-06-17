<?php

namespace App\Support;

/**
 * O legado grava USUARIO_LOGIN como CPF só-dígitos. Personas de homologação usam e-mail em USUARIO_LOGIN.
 * Só aplica a limpeza numérica quando o valor não for e-mail explícito (contém @).
 */
final class LoginLookupNormalizer
{
    public static function forDatabaseLookup(string $login): string
    {
        $login = trim($login);
        if (mb_strtolower($login, 'UTF-8') === 'admin') {
            return 'admin';
        }
        if (str_contains($login, '@')) {
            return mb_strtolower($login, 'UTF-8');
        }

        return preg_replace('/[^0-9]/', '', $login);
    }

    /**
     * Valor canónico para persistência em USUARIO_LOGIN/USUARIO_EMAIL.
     * E-mail -> trim + lowercase; login não-email -> trim (mantendo regra legada para "admin").
     */
    public static function forStorage(string $value): string
    {
        $value = trim($value);
        if (str_contains($value, '@')) {
            return mb_strtolower($value, 'UTF-8');
        }
        if (mb_strtolower($value, 'UTF-8') === 'admin') {
            return 'admin';
        }

        return $value;
    }
}
