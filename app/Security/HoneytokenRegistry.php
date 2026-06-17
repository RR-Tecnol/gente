<?php

namespace App\Security;

use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

/**
 * Iscas (honeytokens): servidores marcados com FUNCIONARIO_HONEYTOKEN = 1.
 * IDs são descobertos por consulta; cache 5 min.
 */
final class HoneytokenRegistry
{
    public static function cacheTtlSec(): int
    {
        return (int) config('gente.honeytokens.id_cache_sec', 300);
    }

    /**
     * @return list<int>
     */
    public static function honeyFuncionarioIds(): array
    {
        if (! (bool) config('gente.honeytokens.enabled', true)) {
            return [];
        }
        if (! Schema::hasTable('FUNCIONARIO') || ! Schema::hasColumn('FUNCIONARIO', 'FUNCIONARIO_HONEYTOKEN')) {
            return [];
        }

        $ttl = self::cacheTtlSec();

        return Cache::remember('gente_honeytoken_func_ids', $ttl, function () {
            return DB::table('FUNCIONARIO')
                ->where('FUNCIONARIO_HONEYTOKEN', 1)
                ->pluck('FUNCIONARIO_ID')
                ->map(fn ($v) => (int) $v)
                ->values()
                ->all();
        });
    }

    public static function isHoneyFuncionarioId(int $id): bool
    {
        if ($id <= 0) {
            return false;
        }

        return in_array($id, self::honeyFuncionarioIds(), true);
    }

    public static function forgetCache(): void
    {
        Cache::forget('gente_honeytoken_func_ids');
    }
}
