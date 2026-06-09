# BRIEFING ANTYGRAVITY — Fase 6 D11: RBAC Bootstrap + Vínculo Admin

**Data:** 08/05/2026 23h00
**Branch:** `producao-pmsl`
**Contexto:** O `AdminProdSeeder` rodou em produção e criou o `USUARIO_ID=1` (login `admin`, senha `GenteAdmin@2026!PMSL`). O usuário consegue logar e ver o dashboard, MAS ele não tem nenhum perfil legado (`USUARIO_PERFIL`) nem nenhuma role RBAC v3 (`GENTE_ASSIGNMENT`). Isso significa que:

1. `isAdmin` no front Vue 3 retorna `false` — ele tem acesso aos menus por mero acaso (caminho do meio do código que não exige role)
2. Tela "Meu Perfil" mostra "Dados pessoais não disponíveis" + "Invalid Date" — esperado porque admin não é funcionário, mas o front não trata isso bem
3. `rbac_permission_slugs` no payload `/api/auth/me` vem vazio
4. Qualquer tela protegida por `meta.roles` ou `requiredAnySlugs` vai bloquear admin

A última saída do `AdminProdSeeder` em produção mostrou:
```
Tabelas RBAC v3 vazias ou admin role inexistente — role admin NÃO atribuída automaticamente. Cadastre via UI.
```

Confirmado: as tabelas `PERFIL`, `GENTE_ROLE`, `GENTE_PERMISSION`, `GENTE_ROLE_PERMISSION` estão vazias em produção. Os seeders existem (`PerfilSeeder`, `RbacMatrixSeeder`) mas nunca rodaram em PMSL.

## Tarefa única: criar `AdminProdBootstrapSeeder`

**Arquivo a CRIAR:** `database/seeders/AdminProdBootstrapSeeder.php`

**Conteúdo:**

```php
<?php

namespace Database\Seeders;

use App\Models\Usuario;
use Illuminate\Database\Seeder;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

/**
 * AdminProdBootstrapSeeder — bootstrap RBAC + vínculo do admin canônico.
 *
 * Diferença para AdminProdSeeder:
 * - AdminProdSeeder cria/atualiza apenas o registro em USUARIO + senha
 * - Este seeder vincula o admin aos perfis legados (USUARIO_PERFIL) e
 *   às roles RBAC v3 (GENTE_ASSIGNMENT), populando antes as tabelas-base
 *   se necessário.
 *
 * Ordem de execução (idempotente):
 *   1. Garante PERFIL populado (chama PerfilSeeder se PERFIL.count() == 0)
 *   2. Garante RBAC v3 populado (chama RbacMatrixSeeder se GENTE_ROLE.count() == 0)
 *   3. Cria/atualiza vínculo USUARIO_PERFIL: admin → PERFIL_ID=2 (Administrador) ATIVO
 *   4. Cria/atualiza vínculo GENTE_ASSIGNMENT: admin → role 'auditor_homologacao_ti'
 *      (matriz completa de permissões — único role com todas as 17 PERM_SLUG)
 *
 * Uso em produção (após AdminProdSeeder):
 *   sudo -u www-data php artisan db:seed --class=AdminProdBootstrapSeeder --force
 *
 * IMPORTANTE: Este seeder NÃO cria o Usuario admin — esse é trabalho do
 * AdminProdSeeder. Roda este DEPOIS do AdminProdSeeder.
 */
class AdminProdBootstrapSeeder extends Seeder
{
    public function run(): void
    {
        $loginAdmin = 'admin';

        $admin = Usuario::where('USUARIO_LOGIN', $loginAdmin)->first();
        if (! $admin) {
            $this->command->error("Usuario '{$loginAdmin}' não encontrado. Rode AdminProdSeeder primeiro.");
            return;
        }

        $usuarioId = (int) $admin->USUARIO_ID;
        $this->command->info("Bootstrap RBAC para USUARIO_ID={$usuarioId} (login={$loginAdmin})");

        // ── 1) PERFIL legado ─────────────────────────────────────────────
        if (Schema::hasTable('PERFIL')) {
            $perfilCount = (int) DB::table('PERFIL')->count();
            if ($perfilCount === 0) {
                $this->command->info('PERFIL vazio — chamando PerfilSeeder...');
                $this->call(PerfilSeeder::class);
            } else {
                $this->command->info("PERFIL já populado ({$perfilCount} registros) — preservando");
            }
        } else {
            $this->command->warn('Tabela PERFIL inexistente — pulando passo 1');
        }

        // ── 2) RBAC v3 ───────────────────────────────────────────────────
        if (Schema::hasTable('GENTE_ROLE') && Schema::hasTable('GENTE_PERMISSION')) {
            $roleCount = (int) DB::table('GENTE_ROLE')->count();
            if ($roleCount === 0) {
                $this->command->info('GENTE_ROLE vazio — chamando RbacMatrixSeeder...');
                $this->call(RbacMatrixSeeder::class);
            } else {
                $this->command->info("GENTE_ROLE já populado ({$roleCount} roles) — preservando");
            }
        } else {
            $this->command->warn('Tabelas RBAC v3 inexistentes — pulando passo 2');
        }

        // ── 3) Vínculo legado USUARIO_PERFIL → Administrador ─────────────
        if (Schema::hasTable('PERFIL') && Schema::hasTable('USUARIO_PERFIL')) {
            $perfilAdmin = DB::table('PERFIL')->where('PERFIL_NOME', 'Administrador')->first();
            if (! $perfilAdmin) {
                $this->command->error('PERFIL "Administrador" não encontrado após PerfilSeeder. Abortando passo 3.');
            } else {
                $perfilId = (int) $perfilAdmin->PERFIL_ID;
                $existing = DB::table('USUARIO_PERFIL')
                    ->where('USUARIO_ID', $usuarioId)
                    ->where('PERFIL_ID', $perfilId)
                    ->first();

                if (! $existing) {
                    DB::table('USUARIO_PERFIL')->insert([
                        'USUARIO_ID' => $usuarioId,
                        'PERFIL_ID' => $perfilId,
                        'USUARIO_PERFIL_ATIVO' => 1,
                    ]);
                    $this->command->info("Vínculo USUARIO_PERFIL criado: USUARIO_ID={$usuarioId} → PERFIL_ID={$perfilId} (Administrador)");
                } else {
                    DB::table('USUARIO_PERFIL')
                        ->where('USUARIO_PERFIL_ID', $existing->USUARIO_PERFIL_ID)
                        ->update(['USUARIO_PERFIL_ATIVO' => 1]);
                    $this->command->info("Vínculo USUARIO_PERFIL já existe (USUARIO_PERFIL_ID={$existing->USUARIO_PERFIL_ID}) — reativado");
                }
            }
        } else {
            $this->command->warn('Tabelas PERFIL/USUARIO_PERFIL inexistentes — pulando passo 3');
        }

        // ── 4) RBAC v3 GENTE_ASSIGNMENT → auditor_homologacao_ti ─────────
        if (Schema::hasTable('GENTE_ROLE') && Schema::hasTable('GENTE_ASSIGNMENT')) {
            $roleSlug = 'auditor_homologacao_ti';
            $role = DB::table('GENTE_ROLE')->where('ROLE_SLUG', $roleSlug)->first();

            if (! $role) {
                $this->command->error("Role RBAC '{$roleSlug}' não encontrada. Abortando passo 4.");
            } else {
                $roleId = (int) $role->GENTE_ROLE_ID;

                $existing = DB::table('GENTE_ASSIGNMENT')
                    ->where('USUARIO_ID', $usuarioId)
                    ->where('GENTE_ROLE_ID', $roleId)
                    ->first();

                if (! $existing) {
                    DB::table('GENTE_ASSIGNMENT')->insert([
                        'USUARIO_ID' => $usuarioId,
                        'GENTE_ROLE_ID' => $roleId,
                        'TENANT_TYPE' => 'GLOBAL_SEMED',
                        'TENANT_ID' => 0,
                        'VIGENCIA_INICIO' => now()->format('Y-m-d'),
                        'VIGENCIA_FIM' => null,
                        'ASSIGNMENT_ATIVO' => 1,
                        'ORIGEM' => 'SEEDER',
                        'created_at' => now(),
                        'updated_at' => now(),
                    ]);
                    $this->command->info("GENTE_ASSIGNMENT criado: USUARIO_ID={$usuarioId} → role '{$roleSlug}' em GLOBAL_SEMED");
                } else {
                    DB::table('GENTE_ASSIGNMENT')
                        ->where('GENTE_ASSIGNMENT_ID', $existing->GENTE_ASSIGNMENT_ID)
                        ->update([
                            'ASSIGNMENT_ATIVO' => 1,
                            'VIGENCIA_FIM' => null,
                            'updated_at' => now(),
                        ]);
                    $this->command->info("GENTE_ASSIGNMENT já existe (ID={$existing->GENTE_ASSIGNMENT_ID}) — reativado");
                }
            }
        } else {
            $this->command->warn('Tabelas RBAC v3 inexistentes — pulando passo 4');
        }

        $this->command->info('Bootstrap RBAC concluído.');
        $this->command->warn('Faça LOGOUT + LOGIN no SPA para que o /api/auth/me retorne os novos vínculos.');
    }
}
```

## Validação

Antes do commit:

```bash
php -l database/seeders/AdminProdBootstrapSeeder.php
```

## NÃO fazer

- ❌ NÃO incluir esse seeder no `DatabaseSeeder.php` (mesma regra do AdminProdSeeder — só roda explicitamente)
- ❌ NÃO criar Funcionario/Pessoa para o admin — admin é entidade de sistema, não servidor PMSL
- ❌ NÃO mexer em `PerfilSeeder.php` ou `RbacMatrixSeeder.php` — são chamados, não modificados
- ❌ NÃO rodar `composer install/update`

## Commit sugerido

`feat(seed): AdminProdBootstrapSeeder — popula RBAC e vincula admin a roles legada (Administrador) + RBAC v3 (auditor_homologacao_ti)`

## Push

```bash
git push origin producao-pmsl
```

NÃO mexer em master, NÃO usar `--force`.

## Reportar para Ronaldo

1. SHA do commit criado
2. Output do `php -l`
3. Confirmação de que NÃO mexeu em outros arquivos
