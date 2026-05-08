<?php

namespace Database\Seeders;

use Illuminate\Database\Seeder;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;
use Symfony\Component\Yaml\Yaml;

/**
 * Lê database/rbac/rbac_matrix.v1.yaml (ou GENTE_RBAC_MATRIX_YAML) e materializa GENTE_ROLE, GENTE_PERMISSION, GENTE_ROLE_PERMISSION.
 */
class RbacMatrixSeeder extends Seeder
{
    /** @var array<string, list<string>> */
    private const CRITICAL_PERMISSION_POLICY = [
        'global.mde.25' => ['global_semed_secretario', 'analista_executivo_sagep', 'auditoria_matriz_semad', 'auditor_homologacao_ti'],
        'rh.progressao.lei4928' => ['global_semed_secretario', 'analista_executivo_sagep', 'auditor_homologacao_ti'],
        'escala.override.sudo_grade' => ['global_semed_secretario', 'analista_executivo_sagep', 'auditor_homologacao_ti'],
        'financeiro.previdencia.ipam' => ['global_semed_secretario', 'analista_executivo_sagep', 'auditor_homologacao_ti'],
    ];

    public function run(): void
    {
        if (! Schema::hasTable('GENTE_ROLE') || ! Schema::hasTable('GENTE_PERMISSION') || ! Schema::hasTable('GENTE_ROLE_PERMISSION')) {
            return;
        }

        $path = (string) config('gente.rbac.matrix_yaml');
        if ($path === '' || ! is_readable($path)) {
            $this->command && $this->command->warn('RbacMatrixSeeder: ficheiro YAML inacessível: '.$path);

            return;
        }

        $data = Yaml::parseFile($path);
        if (! is_array($data)) {
            return;
        }

        $roles = $data['roles'] ?? [];
        $permissions = $data['permissions'] ?? [];
        $rolePermissions = $data['role_permissions'] ?? [];

        DB::transaction(function () use ($roles, $permissions, $rolePermissions) {
            $now = now();
            foreach ($roles as $row) {
                if (! is_array($row) || empty($row['slug'])) {
                    continue;
                }
                $slug = (string) $row['slug'];
                $camada = (string) ($row['camada'] ?? ($row['layer'] ?? 'operacional'));
                $level = isset($row['level']) && is_numeric($row['level']) ? (int) $row['level'] : null;
                $descricao = isset($row['descricao']) ? (string) $row['descricao'] : null;
                if ($level !== null) {
                    $descricao = trim(($descricao ? $descricao.' ' : '').'[level='.$level.']');
                }
                $payload = [
                    'ROLE_NOME' => (string) ($row['nome'] ?? $slug),
                    'CAMADA' => $camada,
                    'ORGAO_TENANT' => (string) ($row['orgao'] ?? 'SEMED'),
                    'ROLE_ATIVO' => 1,
                    'DESCRICAO' => $descricao,
                    'updated_at' => $now,
                ];
                $exists = DB::table('GENTE_ROLE')->where('ROLE_SLUG', $slug)->exists();
                if (! $exists) {
                    $payload['created_at'] = $now;
                }
                DB::table('GENTE_ROLE')->updateOrInsert(['ROLE_SLUG' => $slug], $payload);
            }

            foreach ($permissions as $row) {
                if (! is_array($row) || empty($row['slug'])) {
                    continue;
                }
                $slug = (string) $row['slug'];
                $payload = [
                    'PERM_RECURSO' => (string) ($row['recurso'] ?? ''),
                    'PERM_ACAO' => (string) ($row['acao'] ?? ''),
                    'PERM_DESCRICAO' => isset($row['descricao']) ? (string) $row['descricao'] : null,
                    'PERM_ATIVO' => 1,
                    'updated_at' => $now,
                ];
                $exists = DB::table('GENTE_PERMISSION')->where('PERM_SLUG', $slug)->exists();
                if (! $exists) {
                    $payload['created_at'] = $now;
                }
                DB::table('GENTE_PERMISSION')->updateOrInsert(['PERM_SLUG' => $slug], $payload);
            }

            $roleIdsBySlug = [];
            foreach (array_keys($rolePermissions) as $roleSlug) {
                $roleSlug = (string) $roleSlug;
                $id = DB::table('GENTE_ROLE')->where('ROLE_SLUG', $roleSlug)->value('GENTE_ROLE_ID');
                if ($id !== null) {
                    $roleIdsBySlug[$roleSlug] = (int) $id;
                }
            }
            $desiredPairs = [];
            foreach ($rolePermissions as $roleSlug => $permList) {
                $roleSlug = (string) $roleSlug;
                if (! isset($roleIdsBySlug[$roleSlug]) || ! is_array($permList)) {
                    continue;
                }
                $roleId = $roleIdsBySlug[$roleSlug];
                foreach ($permList as $permSlug) {
                    $permSlug = (string) $permSlug;
                    $permId = DB::table('GENTE_PERMISSION')->where('PERM_SLUG', $permSlug)->value('GENTE_PERMISSION_ID');
                    if ($permId === null) {
                        continue;
                    }
                    $desiredPairs[] = [
                        'GENTE_ROLE_ID' => $roleId,
                        'GENTE_PERMISSION_ID' => (int) $permId,
                        'created_at' => $now,
                        'updated_at' => $now,
                    ];
                }
            }

            if ($roleIdsBySlug !== []) {
                $targetRoleIds = array_values($roleIdsBySlug);
                $existingPairs = DB::table('GENTE_ROLE_PERMISSION')
                    ->whereIn('GENTE_ROLE_ID', $targetRoleIds)
                    ->get(['GENTE_ROLE_ID', 'GENTE_PERMISSION_ID']);
                $desiredKeySet = [];
                foreach ($desiredPairs as $pair) {
                    $desiredKeySet[$pair['GENTE_ROLE_ID'].':'.$pair['GENTE_PERMISSION_ID']] = true;
                }
                foreach ($existingPairs as $pair) {
                    $key = ((int) $pair->GENTE_ROLE_ID).':'.((int) $pair->GENTE_PERMISSION_ID);
                    if (! isset($desiredKeySet[$key])) {
                        DB::table('GENTE_ROLE_PERMISSION')
                            ->where('GENTE_ROLE_ID', (int) $pair->GENTE_ROLE_ID)
                            ->where('GENTE_PERMISSION_ID', (int) $pair->GENTE_PERMISSION_ID)
                            ->delete();
                    }
                }
            }

            foreach ($desiredPairs as $pair) {
                DB::table('GENTE_ROLE_PERMISSION')->updateOrInsert(
                    [
                        'GENTE_ROLE_ID' => $pair['GENTE_ROLE_ID'],
                        'GENTE_PERMISSION_ID' => $pair['GENTE_PERMISSION_ID'],
                    ],
                    ['created_at' => $now, 'updated_at' => $now]
                );
            }

            $this->assertCriticalPolicy();
        });
    }

    private function assertCriticalPolicy(): void
    {
        foreach (self::CRITICAL_PERMISSION_POLICY as $permSlug => $allowedRoleSlugs) {
            $permId = DB::table('GENTE_PERMISSION')
                ->where('PERM_SLUG', $permSlug)
                ->value('GENTE_PERMISSION_ID');
            if ($permId === null) {
                throw new \RuntimeException('RBAC critical permission ausente: '.$permSlug);
            }
            $owners = DB::table('GENTE_ROLE_PERMISSION as rp')
                ->join('GENTE_ROLE as r', 'r.GENTE_ROLE_ID', '=', 'rp.GENTE_ROLE_ID')
                ->where('rp.GENTE_PERMISSION_ID', (int) $permId)
                ->pluck('r.ROLE_SLUG')
                ->map(static function ($v) {
                    return (string) $v;
                })
                ->values()
                ->all();

            if ($owners === []) {
                throw new \RuntimeException('RBAC orphan permission sem dono: '.$permSlug);
            }

            foreach ($owners as $roleSlug) {
                if (! in_array($roleSlug, $allowedRoleSlugs, true)) {
                    throw new \RuntimeException(
                        'RBAC critical permission atribuída a role não permitida: '.$permSlug.' -> '.$roleSlug
                    );
                }
            }
        }
    }
}
