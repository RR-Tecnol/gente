<?php

namespace Database\Seeders;

use Illuminate\Database\Seeder;
use Illuminate\Support\Facades\DB;

/**
 * Cria o usuário Administrador padrão do sistema.
 *
 * Login: admin
 * Senha: admin123  (armazenada como MD5)
 * Perfil: ADMINISTRADOR (PERFIL_ID = 2)
 *
 * Idempotente: não duplica se já existir.
 *
 * ⚠️  Troque a senha imediatamente após o primeiro acesso em produção!
 */
class UsuarioSeeder extends Seeder
{
    public function run(): void
    {
        $login = 'admin';
        $nome = 'Administrador do Sistema';
        $senha = md5('admin123');   // MD5 — padrão do sistema (ver Usuario::setPasswordAttribute)

        // ── USUARIO ──────────────────────────────────────────────────────────
        $sql = "
            IF NOT EXISTS (SELECT 1 FROM USUARIO WHERE USUARIO_LOGIN = '{$login}')
                INSERT INTO USUARIO (USUARIO_LOGIN, USUARIO_SENHA, USUARIO_NOME, USUARIO_ATIVO)
                VALUES (N'{$login}', N'{$senha}', N'{$nome}', 1);
        ";
        DB::unprepared($sql);

        // Busca o ID recém inserido (ou já existente)
        $usuario = DB::selectOne("SELECT USUARIO_ID FROM USUARIO WHERE USUARIO_LOGIN = '{$login}'");
        if (!$usuario)
            return;

        $usuarioId = $usuario->USUARIO_ID;

        // ── USUARIO_PERFIL (Administrador = PERFIL_ID 2) ──────────────────────
        $sqlPerfil = "
            IF NOT EXISTS (SELECT 1 FROM USUARIO_PERFIL WHERE USUARIO_ID = {$usuarioId} AND PERFIL_ID = 2)
                INSERT INTO USUARIO_PERFIL (USUARIO_ID, PERFIL_ID, USUARIO_PERFIL_ATIVO)
                VALUES ({$usuarioId}, 2, 1);
        ";
        DB::unprepared($sqlPerfil);

        // ── USUARIO_PERFIL (Desenvolvedor = PERFIL_ID 1) para ter acesso total ─
        $sqlPerfilDev = "
            IF NOT EXISTS (SELECT 1 FROM USUARIO_PERFIL WHERE USUARIO_ID = {$usuarioId} AND PERFIL_ID = 1)
                INSERT INTO USUARIO_PERFIL (USUARIO_ID, PERFIL_ID, USUARIO_PERFIL_ATIVO)
                VALUES ({$usuarioId}, 1, 1);
        ";
        DB::unprepared($sqlPerfilDev);

        $this->command->info("Usuário 'admin' criado/verificado (ID: {$usuarioId}).");
        $this->command->warn("  ⚠  Troque a senha admin123 após o primeiro acesso!");
    }
}
