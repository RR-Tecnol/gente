<?php

namespace App\Http\Middleware;

use App\Security\IpBlocklistService;
use Closure;
use Illuminate\Http\Request;

class BlockBlacklistedIp
{
    public function handle(Request $request, Closure $next)
    {
        if (! (bool) config('gente.honeytokens.blocklist_enforce', true)) {
            return $next($request);
        }
        $ip = (string) $request->ip();
        if (IpBlocklistService::isBlocked($ip)) {
            return response()->json([
                'erro' => 'Acesso restrito (política de segurança).',
                'code' => 'GENTE_IP_BLOCKED',
            ], 403);
        }

        return $next($request);
    }
}
