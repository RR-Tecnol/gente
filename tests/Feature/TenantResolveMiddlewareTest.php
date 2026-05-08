<?php

namespace Tests\Feature;

use App\Support\Tenancy\TenantContext;
use Illuminate\Support\Facades\Route;
use Tests\TestCase;

class TenantResolveMiddlewareTest extends TestCase
{
    protected function setUp(): void
    {
        parent::setUp();

        Route::middleware('tenant.resolve')->get('/__tenant-context-test', function () {
            /** @var TenantContext $ctx */
            $ctx = app(TenantContext::class);

            return response()->json([
                'resolved' => $ctx->resolved(),
                'tenant_id' => $ctx->tenantId,
                'source' => $ctx->source,
            ]);
        });
    }

    public function test_resolve_tenant_por_subdominio(): void
    {
        config()->set('tenancy.enabled', true);
        config()->set('tenancy.resolver', 'subdomain');
        config()->set('tenancy.reserved_subdomains', ['www', 'api', 'admin']);

        $response = $this
            ->getJson('http://slz.gente.local/__tenant-context-test');

        $response->assertOk()
            ->assertJson([
                'resolved' => true,
                'tenant_id' => 'slz',
                'source' => 'subdomain',
            ]);
    }

    public function test_resolve_tenant_por_header(): void
    {
        config()->set('tenancy.enabled', true);
        config()->set('tenancy.resolver', 'header');
        config()->set('tenancy.header_name', 'X-Tenant-Id');

        $response = $this
            ->withHeader('X-Tenant-Id', 'saoluis')
            ->getJson('/__tenant-context-test');

        $response->assertOk()
            ->assertJson([
                'resolved' => true,
                'tenant_id' => 'saoluis',
                'source' => 'header',
            ]);
    }

    public function test_nao_vaza_contexto_tenant_entre_requisicoes(): void
    {
        config()->set('tenancy.enabled', true);
        config()->set('tenancy.resolver', 'header');
        config()->set('tenancy.header_name', 'X-Tenant-Id');

        $primeira = $this
            ->withHeader('X-Tenant-Id', 'tenant-a')
            ->getJson('/__tenant-context-test');

        $primeira->assertOk()
            ->assertJson([
                'resolved' => true,
                'tenant_id' => 'tenant-a',
                'source' => 'header',
            ]);

        $segunda = $this
            ->withHeader('X-Tenant-Id', '')
            ->getJson('/__tenant-context-test');

        $segunda->assertOk()
            ->assertJson([
                'resolved' => false,
                'tenant_id' => null,
                'source' => 'header',
            ]);
    }
}

