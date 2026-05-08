<?php

namespace App\Support;

use App\Models\Usuario;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Log;

/**
 * Troca de senha: a sessão deve sempre atuar no USUARIO canónico se houver duplicados
 * (mesmo USUARIO_LOGIN e/ou mesmo FUNCIONARIO_ID) — nunca retornar lista ao front.
 */
final class ChangePasswordSessionResolver
{
    /**
     * Regista os IDs em conflito, alinha Auth ao registo preferido (UsuarioLoginResolver) e devolve o model a usar.
     *
     * @return array{0: \App\Models\Usuario, 1: list<int>, 2: bool} [usuario, ids_encontrados, trocou_sessao]
     */
    public static function ensureCanonical(Usuario $sessionUser): array
    {
        $candidates = self::collectDuplicateCandidates($sessionUser);
        $ids = $candidates->pluck('USUARIO_ID')->map(fn ($id) => (int) $id)->values()->all();

        Log::info('api.auth.change_password.usuario_ids_encontrados', [
            'session_usuario_id' => (int) $sessionUser->USUARIO_ID,
            'ids_candidatos' => $ids,
            'duplicado' => $candidates->count() > 1,
            'login' => (string) $sessionUser->USUARIO_LOGIN,
        ]);

        if ($candidates->count() <= 1) {
            return [$sessionUser, $ids, false];
        }

        $canonical = UsuarioLoginResolver::pickBestUser($candidates);
        if ((int) $canonical->USUARIO_ID === (int) $sessionUser->USUARIO_ID) {
            return [$sessionUser, $ids, false];
        }

        Log::warning('api.auth.change_password.sessao_reapontada_para_id_canonico', [
            'de_id' => (int) $sessionUser->USUARIO_ID,
            'para_id' => (int) $canonical->USUARIO_ID,
        ]);

        Auth::login($canonical, false);
        $fresh = Auth::user();
        if (! $fresh instanceof Usuario) {
            return [$canonical, $ids, true];
        }

        return [$fresh, $ids, true];
    }

    /**
     * @return \Illuminate\Support\Collection<int, \App\Models\Usuario>
     */
    private static function collectDuplicateCandidates(Usuario $u): \Illuminate\Support\Collection
    {
        $byLogin = Usuario::query()
            ->where('USUARIO_LOGIN', $u->USUARIO_LOGIN)
            ->where('USUARIO_ATIVO', 1)
            ->get();
        $merged = collect();
        $merged = $merged->merge($byLogin);

        if (! empty($u->FUNCIONARIO_ID) && (int) $u->FUNCIONARIO_ID > 0) {
            $byF = Usuario::query()
                ->where('FUNCIONARIO_ID', (int) $u->FUNCIONARIO_ID)
                ->where('USUARIO_ATIVO', 1)
                ->get();
            $merged = $merged->merge($byF);
        }

        $unique = $merged->unique('USUARIO_ID')->values();
        if ($u->USUARIO_CPF) {
            $c = trim((string) $u->USUARIO_CPF);
            if ($c !== '' && \Illuminate\Support\Facades\Schema::hasColumn('USUARIO', 'USUARIO_CPF')) {
                $byCpf = Usuario::query()
                    ->where('USUARIO_CPF', $c)
                    ->where('USUARIO_ATIVO', 1)
                    ->get();
                $unique = $unique->merge($byCpf)->unique('USUARIO_ID')->values();
            }
        }

        if ($unique->isEmpty()) {
            return collect([$u]);
        }

        if (! $unique->contains('USUARIO_ID', $u->USUARIO_ID)) {
            $unique = $unique->push($u)->unique('USUARIO_ID')->values();
        }

        return $unique;
    }
}
