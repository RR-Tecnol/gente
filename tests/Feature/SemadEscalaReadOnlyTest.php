<?php

namespace Tests\Feature;

use App\Models\Usuario;
use App\Support\GenteTenantType;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;
use Tests\TestCase;

class SemadEscalaReadOnlyTest extends TestCase
{
    public function test_post_escala_trabalho_retorna_403_para_auditor_semad(): void
    {
        if (! Schema::hasTable('GENTE_ASSIGNMENT') || ! Schema::hasTable('USUARIO')) {
            $this->markTestSkipped();
        }

        DB::beginTransaction();
        try {
            $slug = (string) config('gente.rbac.role_slug_semad_auditor', 'auditoria_matriz_semad');
            $roleId = DB::table('GENTE_ROLE')->where('ROLE_SLUG', $slug)->value('GENTE_ROLE_ID');
            if ($roleId === null) {
                $roleId = DB::table('GENTE_ROLE')->insertGetId([
                    'ROLE_SLUG' => $slug,
                    'ROLE_NOME' => 'Auditoria SEMAD',
                    'CAMADA' => 'estrategica',
                    'ORGAO_TENANT' => 'SEMAD',
                    'ROLE_ATIVO' => 1,
                    'DESCRICAO' => null,
                    'created_at' => now(),
                    'updated_at' => now(),
                ]);
            } else {
                $roleId = (int) $roleId;
            }

            $login = 'semad_api_'.uniqid('', true);
            $usuarioId = (int) DB::table('USUARIO')->insertGetId([
                'USUARIO_NOME' => 'Auditor SEMAD API',
                'USUARIO_LOGIN' => $login,
                'USUARIO_SENHA' => bcrypt('x'),
                'PERFIL_ID' => null,
                'USUARIO_ATIVO' => 1,
            ]);

            DB::table('GENTE_ASSIGNMENT')->insert([
                'USUARIO_ID' => $usuarioId,
                'GENTE_ROLE_ID' => $roleId,
                'TENANT_TYPE' => GenteTenantType::GLOBAL_SEMAD,
                'TENANT_ID' => 1,
                'VIGENCIA_INICIO' => '2020-01-01',
                'VIGENCIA_FIM' => null,
                'ASSIGNMENT_ATIVO' => 1,
                'ORIGEM' => 'seed',
                'METADADOS_JSON' => null,
                'created_at' => now(),
                'updated_at' => now(),
            ]);

            $user = Usuario::query()->findOrFail($usuarioId);
            $response = $this->actingAs($user, 'web')
                ->postJson('/api/v3/escala-trabalho', []);

            $response->assertStatus(403);
            $this->assertStringContainsStringIgnoringCase('SEMAD', (string) ($response->json('erro') ?? ''));
        } finally {
            DB::rollBack();
        }
    }
}
