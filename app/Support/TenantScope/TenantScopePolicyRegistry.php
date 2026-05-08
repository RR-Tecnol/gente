<?php

namespace App\Support\TenantScope;

use Illuminate\Http\Request;
use Illuminate\Support\Str;

/**
 * Determina o anel (ring) activo a partir do path e da configuração.
 */
final class TenantScopePolicyRegistry
{
    /**
     * Path normalizado (sem barra inicial).
     */
    public static function normalizePath(Request $request): string
    {
        return ltrim((string) $request->path(), '/');
    }

    /**
     * @return array{key: string, ring: array<string, mixed>}|null
     */
    public function matchRing(Request $request): ?array
    {
        $path = self::normalizePath($request);
        $rings = (array) config('gente_tenant_rings.rings', []);

        foreach ($rings as $key => $ring) {
            if (! is_string($key) || ! is_array($ring)) {
                continue;
            }
            $prefixes = (array) ($ring['path_prefixes'] ?? []);
            foreach ($prefixes as $prefix) {
                $p = ltrim((string) $prefix, '/');
                if ($p !== '' && Str::startsWith($path, $p)) {
                    return ['key' => $key, 'ring' => $ring];
                }
            }
        }

        return null;
    }

    public function isExcludedPath(Request $request): bool
    {
        $path = self::normalizePath($request);
        foreach ((array) config('gente_tenant_rings.exclude_path_prefixes', []) as $prefix) {
            $p = ltrim((string) $prefix, '/');
            if ($p !== '' && Str::startsWith($path, $p)) {
                return true;
            }
        }

        return false;
    }
}
