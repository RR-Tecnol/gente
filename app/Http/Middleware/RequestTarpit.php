<?php

namespace App\Http\Middleware;

use Closure;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Cache;
use Symfony\Component\HttpFoundation\Response;

/**
 * Frente 4: atraso progressivo após muitos 4xx em janela (aparência de sobrecarga, não bloqueio seco).
 */
class RequestTarpit
{
    public function handle(Request $request, Closure $next)
    {
        if (! (bool) config('gente.tarpit.enabled', true)) {
            return $next($request);
        }
        if ($this->shouldSkipPath($request)) {
            return $next($request);
        }
        $ip = (string) $request->ip();
        $level = (int) Cache::get($this->levelKey($ip), 0);
        if ($level > 0) {
            $sec = $this->sleepSecondsForLevel($level);
            if ($sec > 0) {
                sleep($sec);
            }
        }

        return $next($request);
    }

    public function terminate(Request $request, $response): void
    {
        if (! (bool) config('gente.tarpit.enabled', true)) {
            return;
        }
        if ($this->shouldSkipPath($request)) {
            return;
        }
        if (! $response instanceof Response) {
            return;
        }
        $code = $response->getStatusCode();
        $ip = (string) $request->ip();

        if ($code >= 200 && $code < 300) {
            $this->decayLevel($ip);

            return;
        }
        if ($code < 400 || $code >= 500) {
            return;
        }

        $this->register4xx($ip);
    }

    private function shouldSkipPath(Request $request): bool
    {
        $path = '/' . ltrim($request->path(), '/');
        $prefixes = (array) config('gente.tarpit.skip_path_prefixes', []);
        foreach ($prefixes as $p) {
            $p = trim((string) $p, '/');
            if ($p === '') {
                continue;
            }
            if ($path === '/'.$p || strpos($path, '/'.$p.'/') === 0) {
                return true;
            }
        }

        return false;
    }

    private function register4xx(string $ip): void
    {
        $k = $this->counterKey($ip);
        $window = max(10, (int) config('gente.tarpit.window_sec', 60));
        $threshold = max(1, (int) config('gente.tarpit.4xx_threshold', 5));

        $c = (int) Cache::get($k, 0) + 1;
        Cache::put($k, $c, $window);
        if ($c < $threshold) {
            return;
        }

        $lk = $this->levelKey($ip);
        $cur = (int) Cache::get($lk, 0);
        $newLevel = $c === $threshold ? max(1, $cur + 1) : min(8, $cur + 1);
        $penaltyTtl = max(60, (int) config('gente.tarpit.penalty_ttl_sec', 600));
        Cache::put($lk, $newLevel, $penaltyTtl);
    }

    private function decayLevel(string $ip): void
    {
        $lk = $this->levelKey($ip);
        if (! Cache::has($lk)) {
            return;
        }
        $cur = (int) Cache::get($lk, 0);
        if ($cur <= 1) {
            Cache::forget($lk);

            return;
        }
        $penaltyTtl = max(60, (int) config('gente.tarpit.penalty_ttl_sec', 600));
        Cache::put($lk, $cur - 1, $penaltyTtl);
    }

    private function sleepSecondsForLevel(int $level): int
    {
        $max = max(1, (int) config('gente.tarpit.max_sleep_sec', 16));
        if ($level <= 0) {
            return 0;
        }
        $raw = 1 << ($level - 1);

        return min($max, $raw);
    }

    private function counterKey(string $ip): string
    {
        return 'gente_tarpit_4xx_'.md5('v1|'.$ip);
    }

    private function levelKey(string $ip): string
    {
        return 'gente_tarpit_lvl_'.md5('v1|'.$ip);
    }
}
