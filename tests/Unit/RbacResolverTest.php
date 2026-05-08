<?php

namespace Tests\Unit;

use App\Support\GenteTenantType;
use App\Support\RbacResolver;
use Carbon\Carbon;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;
use Tests\TestCase;

class RbacResolverTest extends TestCase
{
    public function test_permission_slugs_e_can_por_tenant(): void
    {
        if (! Schema::hasTable('USUARIO')
            || ! Schema::hasTable('GENTE_ASSIGNMENT')
            || ! Schema::hasTable('GENTE_ROLE')
            || ! Schema::hasTable('GENTE_PERMISSION')
            || ! Schema::hasTable('GENTE_ROLE_PERMISSION')) {
            $this->markTestSkipped('Tabelas RBAC/USUARIO indisponíveis.');
        }

        DB::beginTransaction();
        try {
            $login = 'rbac_resolver_test_'.uniqid('', true);
            $usuarioId = (int) DB::table('USUARIO')->insertGetId([
                'USUARIO_NOME' => 'RBAC Test',
                'USUARIO_LOGIN' => $login,
                'USUARIO_SENHA' => bcrypt('x'),
                'PERFIL_ID' => null,
                'USUARIO_ATIVO' => 1,
            ]);

            $roleId = (int) DB::table('GENTE_ROLE')->insertGetId([
                'ROLE_SLUG' => 'rbac_test_role_'.uniqid(),
                'ROLE_NOME' => 'RBAC Test Role',
                'CAMADA' => 'operacional',
                'ORGAO_TENANT' => 'SEMED',
                'ROLE_ATIVO' => 1,
                'DESCRICAO' => null,
                'created_at' => now(),
                'updated_at' => now(),
            ]);

            $permId = (int) DB::table('GENTE_PERMISSION')->insertGetId([
                'PERM_SLUG' => 'rbac.test.perm_'.uniqid(),
                'PERM_RECURSO' => 'test',
                'PERM_ACAO' => 'x',
                'PERM_DESCRICAO' => null,
                'PERM_ATIVO' => 1,
                'created_at' => now(),
                'updated_at' => now(),
            ]);

            DB::table('GENTE_ROLE_PERMISSION')->insert([
                'GENTE_ROLE_ID' => $roleId,
                'GENTE_PERMISSION_ID' => $permId,
            ]);

            $tenantId = 900000001;
            DB::table('GENTE_ASSIGNMENT')->insert([
                'USUARIO_ID' => $usuarioId,
                'GENTE_ROLE_ID' => $roleId,
                'TENANT_TYPE' => GenteTenantType::UNIDADE,
                'TENANT_ID' => $tenantId,
                'VIGENCIA_INICIO' => '2020-01-01',
                'VIGENCIA_FIM' => null,
                'ASSIGNMENT_ATIVO' => 1,
                'ORIGEM' => 'seed',
                'METADADOS_JSON' => null,
                'created_at' => now(),
                'updated_at' => now(),
            ]);

            $permSlug = DB::table('GENTE_PERMISSION')->where('GENTE_PERMISSION_ID', $permId)->value('PERM_SLUG');
            $resolver = new RbacResolver(Carbon::parse('2025-06-01'));

            $slugs = $resolver->permissionSlugsForUsuario($usuarioId);
            $this->assertContains((string) $permSlug, $slugs);

            $this->assertTrue($resolver->can($usuarioId, (string) $permSlug));
            $this->assertTrue($resolver->can($usuarioId, (string) $permSlug, GenteTenantType::UNIDADE, $tenantId));
            $this->assertFalse($resolver->can($usuarioId, (string) $permSlug, GenteTenantType::UNIDADE, $tenantId + 1));
        } finally {
            DB::rollBack();
        }
    }
}
