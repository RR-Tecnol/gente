<?php

namespace Database\Seeders;

use App\Models\Usuario;
use Illuminate\Database\Seeder;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Schema;

/**
 * AdminProdSeeder — cria usuário administrador para ambiente de produção.
 *
 * Diferença para o /dev/criar-admin (que só roda em isLocal()):
 * - Roda em production sem precisar de rota dev exposta
 * - Idempotente: se o admin já existe, atualiza apenas senha+vigência
 * - Forçar troca de senha no primeiro acesso (USUARIO_ALTERAR_SENHA=1)
 * - Audita criação no log
 *
 * Uso em produção:
 *   sudo -u www-data php artisan db:seed --class=AdminProdSeeder --force
 *
 * Credenciais geradas:
 *   USUARIO_LOGIN: admin
 *   USUARIO_SENHA: GenteAdmin@2026!PMSL
 *
 * IMPORTANTE: senha deve ser trocada no primeiro acesso pela equipe PMSL.
 */
class AdminProdSeeder extends Seeder
{
    public function run(): void
    {
        $loginAdmin = 'admin';
        $senhaInicial = 'GenteAdmin@2026!PMSL';

        $existing = Usuario::where('USUARIO_LOGIN', $loginAdmin)->first();

        $payload = [
            'USUARIO_LOGIN'           => $loginAdmin,
            'USUARIO_NOME'            => 'Administrador GENTE — PMSL São Luís',
            'USUARIO_SENHA'           => Hash::make($senhaInicial),
            'USUARIO_EMAIL'           => 'admin@sistemagente.com',
            'USUARIO_ATIVO'           => 1,
            'USUARIO_VIGENCIA'        => null,
            'USUARIO_ALTERAR_SENHA'   => 1,
        ];

        // USUARIO_PRIMEIRO_ACESSO existe na tabela? (migration tardia)
        if (Schema::hasColumn('USUARIO', 'USUARIO_PRIMEIRO_ACESSO')) {
            $payload['USUARIO_PRIMEIRO_ACESSO'] = 1;
        }

        if (! $existing) {
            $user = Usuario::create($payload);
            Log::info('AdminProdSeeder: admin criado', [
                'usuario_id' => $user->USUARIO_ID,
                'login' => $loginAdmin,
            ]);
            $this->command->info("Admin criado: USUARIO_ID={$user->USUARIO_ID}");
            $this->command->warn("Senha inicial: {$senhaInicial} — DEVE SER TROCADA NO PRIMEIRO ACESSO");
        } else {
            // Idempotente: atualiza senha + reseta flags de troca
            $existing->USUARIO_SENHA = Hash::make($senhaInicial);
            $existing->USUARIO_ALTERAR_SENHA = 1;
            $existing->USUARIO_ATIVO = 1;
            $existing->USUARIO_VIGENCIA = null;
            if (Schema::hasColumn('USUARIO', 'USUARIO_PRIMEIRO_ACESSO')) {
                $existing->USUARIO_PRIMEIRO_ACESSO = 1;
            }
            $existing->save();
            Log::info('AdminProdSeeder: admin atualizado', [
                'usuario_id' => $existing->USUARIO_ID,
            ]);
            $this->command->info("Admin atualizado: USUARIO_ID={$existing->USUARIO_ID}");
            $this->command->warn("Senha resetada para: {$senhaInicial} — DEVE SER TROCADA NO PRIMEIRO ACESSO");
        }

        // Atribuir role admin no RBAC v3 (gente_assignment) se tabela existir
        if (Schema::hasTable('GENTE_ASSIGNMENT') && Schema::hasTable('GENTE_ROLE')) {
            $user = Usuario::where('USUARIO_LOGIN', $loginAdmin)->first();
            $adminRole = DB::table('GENTE_ROLE')->where('ROLE_SLUG', 'admin')->first();

            if ($user && $adminRole) {
                $exists = DB::table('GENTE_ASSIGNMENT')
                    ->where('USUARIO_ID', $user->USUARIO_ID)
                    ->where('GENTE_ROLE_ID', $adminRole->GENTE_ROLE_ID)
                    ->exists();

                if (! $exists) {
                    DB::table('GENTE_ASSIGNMENT')->insert([
                        'USUARIO_ID' => $user->USUARIO_ID,
                        'GENTE_ROLE_ID' => $adminRole->GENTE_ROLE_ID,
                        'TENANT_TYPE' => 'GLOBAL',
                        'TENANT_ID' => 0,
                        'VIGENCIA_INICIO' => now()->format('Y-m-d'),
                        'ORIGEM' => 'SEEDER',
                        'created_at' => now(),
                        'updated_at' => now(),
                    ]);
                    $this->command->info("Role admin atribuída em GENTE_ASSIGNMENT");
                } else {
                    $this->command->info("Role admin já atribuída em GENTE_ASSIGNMENT (idempotente)");
                }
            } else {
                $this->command->warn("Tabelas RBAC v3 vazias ou admin role inexistente — role admin NÃO atribuída automaticamente. Cadastre via UI.");
            }
        }
    }
}
