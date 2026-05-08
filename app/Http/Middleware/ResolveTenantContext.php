<?php

namespace App\Http\Middleware;

use App\Support\Tenancy\TenantContext;
use Closure;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Log;

class ResolveTenantContext
{
    public function handle(Request $request, Closure $next)
    {
        if (!(bool) config('tenancy.enabled', false)) {
            app()->instance(TenantContext::class, new TenantContext(null, 'disabled'));
            return $next($request);
        }

        $resolver = (string) config('tenancy.resolver', 'subdomain');
        $tenantId = null;
        $source = $resolver;

        if ($resolver === 'header') {
            $header = (string) config('tenancy.header_name', 'X-Tenant-Id');
            $tenantId = trim((string) $request->header($header));
        } else {
            $host = (string) $request->getHost();
            $parts = explode('.', $host);
            $first = strtolower((string) ($parts[0] ?? ''));
            $reserved = array_map('strtolower', (array) config('tenancy.reserved_subdomains', []));
            if ($first !== '' && !in_array($first, $reserved, true)) {
                $tenantId = $first;
            }
        }

        $tenantId = $tenantId !== '' ? $tenantId : null;
        $context = new TenantContext($tenantId, $source);
        app()->instance(TenantContext::class, $context);

        if ($context->resolved()) {
            Log::info('tenant_context_resolved', [
                'tenant_id' => $context->tenantId,
                'source' => $context->source,
                'path' => $request->path(),
            ]);
        }

        return $next($request);
    }
}

