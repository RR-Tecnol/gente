<?php

namespace Database\Seeders;

use Illuminate\Database\Seeder;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Facades\Log;

/**
 * SEC-07 — Detecta e converte senhas com hash MD5 (inseguro) para bcrypt
 *
 * Por que: O sistema legado armazenava USUARIO_SENHA como MD5($senha).
 * Bcrypt é obrigatório para atender ao requisito de segurança.
 *
 * Como usar:
 *   php artisan db:seed --class=MigrarSenhasMd5Seeder
 *
 * Ação: Senhas MD5 são substituídas por bcrypt de uma senha temporária
 * no formato "Gente@{matricula}2024!". Um e-mail de aviso deve ser enviado.
 */
class MigrarSenhasMd5Seeder extends Seeder
{
    public function run()
    {
        // MD5 tem exatamente 32 chars hex — bcrypt começa com $2y$
        $usuariosMd5 = DB::table('USUARIO')
            ->where(function ($q) {
                $q->whereRaw('LENGTH(USUARIO_SENHA) = 32')
                    ->whereRaw("USUARIO_SENHA NOT LIKE '\$2y\$%'")
                    ->whereRaw("USUARIO_SENHA NOT LIKE '\$2a\$%'");
            })
            ->select('USUARIO_ID', 'USUARIO_LOGIN', 'USUARIO_NOME')
            ->get();

        $convertidos = 0;
        foreach ($usuariosMd5 as $user) {
            // Monta senha temporária baseada na matrícula ou login
            $senhaTemp = 'Gente@' . substr($user->USUARIO_LOGIN, 0, 6) . '2024!';
            $bcrypt = Hash::make($senhaTemp);

            DB::table('USUARIO')
                ->where('USUARIO_ID', $user->USUARIO_ID)
                ->update([
                    'USUARIO_SENHA' => $bcrypt,
                    'DEVE_TROCAR_SENHA' => 1, // obriga troca no próximo login
                    'updated_at' => now(),
                ]);

            // Registrar no canal de segurança (SEC-06)
            Log::channel('security')->info('senha_md5_convertida', [
                'usuario_id' => $user->USUARIO_ID,
                'usuario_login' => $user->USUARIO_LOGIN,
                'acao' => 'md5->bcrypt + deve_trocar=1',
                'data' => now()->toIso8601String(),
            ]);

            // TODO: disparar notificação por e-mail (config Brevo)
            // Mail::to($user->USUARIO_EMAIL)->send(new SenhaTemporariaNotification($user, $senhaTemp));

            $convertidos++;
        }

        $this->command->info("SEC-07: {$convertidos} senhas MD5 convertidas para bcrypt.");
        $this->command->warn("⚠️  Cada usuário receberá senha temporária no formato Gente@{login}2024!");
        $this->command->warn("⚠️  Execute o envio de e-mails manualmente após configurar Brevo no .env");
    }
}
