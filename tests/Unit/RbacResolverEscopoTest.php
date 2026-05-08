<?php

namespace Tests\Unit;

use App\Support\GenteTenantType;
use App\Support\RbacResolver;
use Carbon\Carbon;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;
use Tests\TestCase;

class RbacResolverEscopoTest extends TestCase
{
    public function test_unidade_ids_inclui_unidades_do_polo(): void
    {
        if (! Schema::hasTable('GENTE_ASSIGNMENT')
            || ! Schema::hasTable('UNIDADE_POLO')
            || ! Schema::hasTable('POLO_EDUCACIONAL')
            || ! Schema::hasTable('UNIDADE')) {
            $this->markTestSkipped('Tabelas de escopo RBAC indisponíveis.');
        }

        DB::beginTransaction();
        try {
            $nomeU1 = 'RBAC escopo U1 '.uniqid('', true);
            $nomeU2 = 'RBAC escopo U2 '.uniqid('', true);
            $payloadU = static function (string $nome, string $sigla): array {
                $p = [
                    'UNIDADE_NOME' => $nome,
                    'UNIDADE_SIGLA' => $sigla,
                ];
                if (Schema::hasColumn('UNIDADE', 'UNIDADE_ATIVA')) {
                    $p['UNIDADE_ATIVA'] = 1;
                }
                if (Schema::hasColumn('UNIDADE', 'UNIDADE_ATIVO')) {
                    $p['UNIDADE_ATIVO'] = 1;
                }

                return $p;
            };
            $u1 = (int) DB::table('UNIDADE')->insertGetId($payloadU($nomeU1, 'E1'));
            $u2 = (int) DB::table('UNIDADE')->insertGetId($payloadU($nomeU2, 'E2'));
            $poloId = (int) DB::table('POLO_EDUCACIONAL')->insertGetId([
                'POLO_NOME' => 'Polo teste '.uniqid(),
                'POLO_SIGLA' => 'PT',
                'POLO_ATIVO' => 1,
                'UNIDADE_ID' => null,
                'POLO_OBSERVACAO' => null,
                'created_at' => now(),
                'updated_at' => now(),
            ]);
            DB::table('UNIDADE_POLO')->insert([
                'UNIDADE_ID' => $u1,
                'POLO_ID' => $poloId,
                'VIGENCIA_INICIO' => '2020-01-01',
                'VIGENCIA_FIM' => null,
                'VINCULO_ATIVO' => 1,
                'created_at' => now(),
                'updated_at' => now(),
            ]);
            DB::table('UNIDADE_POLO')->insert([
                'UNIDADE_ID' => $u2,
                'POLO_ID' => $poloId,
                'VIGENCIA_INICIO' => '2020-01-01',
                'VIGENCIA_FIM' => null,
                'VINCULO_ATIVO' => 1,
                'created_at' => now(),
                'updated_at' => now(),
            ]);

            $login = 'rbac_escopo_'.uniqid('', true);
            $usuarioId = (int) DB::table('USUARIO')->insertGetId([
                'USUARIO_NOME' => 'Escopo Polo',
                'USUARIO_LOGIN' => $login,
                'USUARIO_SENHA' => bcrypt('x'),
                'PERFIL_ID' => null,
                'USUARIO_ATIVO' => 1,
            ]);

            $roleId = (int) DB::table('GENTE_ROLE')->insertGetId([
                'ROLE_SLUG' => 'rbac_escopo_polo_role_'.uniqid(),
                'ROLE_NOME' => 'Coord test',
                'CAMADA' => 'intermediaria',
                'ORGAO_TENANT' => 'SEMED',
                'ROLE_ATIVO' => 1,
                'DESCRICAO' => null,
                'created_at' => now(),
                'updated_at' => now(),
            ]);

            DB::table('GENTE_ASSIGNMENT')->insert([
                'USUARIO_ID' => $usuarioId,
                'GENTE_ROLE_ID' => $roleId,
                'TENANT_TYPE' => GenteTenantType::POLO,
                'TENANT_ID' => $poloId,
                'VIGENCIA_INICIO' => '2020-01-01',
                'VIGENCIA_FIM' => null,
                'ASSIGNMENT_ATIVO' => 1,
                'ORIGEM' => 'seed',
                'METADADOS_JSON' => null,
                'created_at' => now(),
                'updated_at' => now(),
            ]);

            $resolver = new RbacResolver(Carbon::parse('2025-06-15'));
            $ids = $resolver->unidadeIdsDoEscopoOperacional($usuarioId);
            sort($ids);
            $this->assertEquals([$u1, $u2], $ids);
        } finally {
            DB::rollBack();
        }
    }

    public function test_usuario_tem_papel_semad_auditoria(): void
    {
        if (! Schema::hasTable('GENTE_ASSIGNMENT')) {
            $this->markTestSkipped();
        }

        DB::beginTransaction();
        try {
            $slug = (string) config('gente.rbac.role_slug_semad_auditor', 'auditoria_matriz_semad');
            $roleId = DB::table('GENTE_ROLE')->where('ROLE_SLUG', $slug)->value('GENTE_ROLE_ID');
            if ($roleId === null) {
                $roleId = DB::table('GENTE_ROLE')->insertGetId([
                    'ROLE_SLUG' => $slug,
                    'ROLE_NOME' => 'Auditoria SEMAD test',
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

            $login = 'semad_aud_'.uniqid('', true);
            $usuarioId = (int) DB::table('USUARIO')->insertGetId([
                'USUARIO_NOME' => 'SEMAD Aud',
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

            $resolver = new RbacResolver();
            $this->assertTrue($resolver->usuarioTemPapelSemadAuditoria($usuarioId));
        } finally {
            DB::rollBack();
        }
    }
}
