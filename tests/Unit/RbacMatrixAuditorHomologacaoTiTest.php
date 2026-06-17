<?php

namespace Tests\Unit;

use Symfony\Component\Yaml\Yaml;
use Tests\TestCase;

/**
 * Invariante anti-drift: todo PERM_SLUG declarado em permissions: deve constar no papel auditor_homologacao_ti.
 */
class RbacMatrixAuditorHomologacaoTiTest extends TestCase
{
    public function test_auditor_homologacao_ti_covers_all_permission_slugs(): void
    {
        $path = (string) config('gente.rbac.matrix_yaml');
        $this->assertNotSame('', $path, 'gente.rbac.matrix_yaml deve apontar para o YAML da matriz.');
        $this->assertFileExists($path, 'Ficheiro da matriz RBAC inacessível: '.$path);

        $data = Yaml::parseFile($path);
        $this->assertIsArray($data);

        $permSlugs = [];
        foreach ($data['permissions'] ?? [] as $row) {
            if (is_array($row) && ! empty($row['slug'])) {
                $permSlugs[] = (string) $row['slug'];
            }
        }
        $this->assertNotEmpty($permSlugs, 'Secção permissions: vazia ou inválida.');

        $rolePerms = $data['role_permissions']['auditor_homologacao_ti'] ?? null;
        $this->assertIsArray($rolePerms, 'role_permissions.auditor_homologacao_ti ausente no YAML.');

        $expected = array_values(array_unique($permSlugs));
        sort($expected);
        $actual = array_values(array_unique(array_map('strval', $rolePerms)));
        sort($actual);

        $missing = array_values(array_diff($expected, $actual));
        $extra = array_values(array_diff($actual, $expected));

        $this->assertSame(
            $expected,
            $actual,
            'auditor_homologacao_ti deve listar exactamente os slugs de permissions:. '
            .(count($missing) ? ' Faltam: '.implode(', ', $missing).'.' : '')
            .(count($extra) ? ' Extra não declarados em permissions:: '.implode(', ', $extra).'.' : '')
        );
    }
}
