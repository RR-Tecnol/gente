<?php

namespace App\Support;

use App\Models\Usuario;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\Log;

/**
 * Garante um único registro USUARIO ativo por credencial (e resolve colisão por FUNCIONARIO_ID / login).
 */
final class UsuarioLoginResolver
{
    /**
     * Busca USUARIO ativo pelo USUARIO_LOGIN normalizado. Se houver colisão, escolhe o mais completo.
     */
    public static function resolveByNormalizedLogin(string $normalizedLogin): ?Usuario
    {
        $query = Usuario::query()->where('USUARIO_ATIVO', 1);
        if (str_contains($normalizedLogin, '@')) {
            $query->whereRaw('LOWER(LTRIM(RTRIM(USUARIO_LOGIN))) = ?', [strtolower(trim($normalizedLogin))]);
        } else {
            $query->where('USUARIO_LOGIN', $normalizedLogin);
        }
        $candidates = $query->get();

        if ($candidates->isEmpty()) {
            return null;
        }
        if ($candidates->count() === 1) {
            return $candidates->first();
        }

        Log::warning('usuario.login.duplicado_mesma_credencial', [
            'USUARIO_LOGIN' => $normalizedLogin,
            'ids' => $candidates->pluck('USUARIO_ID')->all(),
        ]);

        return self::pickBestUser($candidates);
    }

    /**
     * @param  Collection<int, Usuario>  $users
     */
    public static function pickBestUser(Collection $users): Usuario
    {
        $scored = $users->map(function (Usuario $u) {
            return [
                'user' => $u,
                'score' => self::completenessScore($u),
            ];
        })->sortByDesc('score');

        $best = $scored->first();
        if ($best === null) {
            return $users->first();
        }

        return $best['user'];
    }

    public static function completenessScore(Usuario $u): float
    {
        $withPerfis = $u->usuarioPerfis()->where('USUARIO_PERFIL_ATIVO', 1)->count();
        $nome = trim((string) $u->USUARIO_NOME);
        $email = trim((string) ($u->USUARIO_EMAIL ?? ''));
        $login = (string) $u->USUARIO_LOGIN;

        $s = 0.0;
        $s += min(500, $withPerfis * 100);
        $s += $nome !== '' ? strlen($nome) * 0.1 : 0.0;
        $s += $email !== '' ? 40.0 : 0.0;
        if (str_contains($login, '@')) {
            $s += 30.0;
        }
        $s += ((int) $u->USUARIO_ATIVO) * 1_000.0;
        $s += ((int) $u->USUARIO_ID) / 1_000_000.0;

        return $s;
    }
}
