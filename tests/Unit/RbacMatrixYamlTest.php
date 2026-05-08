<?php

namespace Tests\Unit;

use PHPUnit\Framework\TestCase;
use Symfony\Component\Yaml\Yaml;

class RbacMatrixYamlTest extends TestCase
{
    public function test_yaml_estrutura_e_slugs_unicos(): void
    {
        $path = dirname(__DIR__, 2).'/database/rbac/rbac_matrix.v1.yaml';
        $this->assertFileExists($path);

        $data = Yaml::parseFile($path);
        $this->assertIsArray($data);
        $this->assertSame(1, (int) ($data['version'] ?? 0));

        $roleSlugs = [];
        foreach ($data['roles'] as $r) {
            $this->assertArrayHasKey('slug', $r);
            $roleSlugs[] = $r['slug'];
        }
        $this->assertSame(count($roleSlugs), count(array_unique($roleSlugs)));

        $permSlugs = [];
        foreach ($data['permissions'] as $p) {
            $this->assertArrayHasKey('slug', $p);
            $permSlugs[] = $p['slug'];
        }
        $this->assertSame(count($permSlugs), count(array_unique($permSlugs)));

        $this->assertIsArray($data['role_permissions']);
        foreach ($data['role_permissions'] as $roleSlug => $list) {
            $this->assertContains($roleSlug, $roleSlugs, 'role_permissions referencia role inexistente');
            foreach ($list as $ps) {
                $this->assertContains($ps, $permSlugs, 'role_permissions referencia permission inexistente');
            }
        }
    }
}
