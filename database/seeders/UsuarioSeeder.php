<?php

namespace Database\Seeders;

use Illuminate\Database\Seeder;
use Illuminate\Support\Facades\DB;

/**
 * Cria o usuário Administrador padrão do sistema.
 * Login: admin | Senha: admin123 (MD5) | Perfis: 2 (Admin) + 1 (Dev)
 * Idempotente — não duplica se já existir.
 */
class UsuarioSeeder extends Seeder
{
    public function run(): void
    {
        $login = 'admin';
        $nome = 'Administrador do Sistema';
        $senha = md5('admin123');

        $usuarioId = DB::table('USUARIO')->where('USUARIO_LOGIN', $login)->value('USUARIO_ID');
        if (!$usuarioId) {
            $usuarioId = DB::table('USUARIO')->insertGetId([
                'USUARIO_LOGIN' => $login,
                'USUARIO_SENHA' => $senha,
                'USUARIO_NOME' => $nome,
                'USUARIO_ATIVO' => 1,
            ]);
        }

        DB::table('USUARIO_PERFIL')->updateOrInsert(
            ['USUARIO_ID' => $usuarioId, 'PERFIL_ID' => 2],
            ['USUARIO_PERFIL_ATIVO' => 1]
        );
        DB::table('USUARIO_PERFIL')->updateOrInsert(
            ['USUARIO_ID' => $usuarioId, 'PERFIL_ID' => 1],
            ['USUARIO_PERFIL_ATIVO' => 1]
        );

        $this->command->info("Usuário 'admin' criado/verificado (ID: {$usuarioId}).");
        $this->command->warn("  ⚠  Troque a senha admin123 após o primeiro acesso!");
    }
}
