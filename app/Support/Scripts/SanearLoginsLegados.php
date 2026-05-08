<?php

namespace App\Support\Scripts;

use App\Support\LoginLookupNormalizer;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Schema;

/**
 * Saneia USUARIO_LOGIN / USUARIO_EMAIL legados com trim + lowercase para e-mail.
 */
final class SanearLoginsLegados
{
    /**
     * @return array{total: int, corrigidos: int, login_corrigidos: int, email_corrigidos: int}
     */
    public static function run(bool $dryRun = false): array
    {
        if (! Schema::hasTable('USUARIO')) {
            return ['total' => 0, 'corrigidos' => 0, 'login_corrigidos' => 0, 'email_corrigidos' => 0];
        }

        $temEmail = Schema::hasColumn('USUARIO', 'USUARIO_EMAIL');
        $cols = ['USUARIO_ID', 'USUARIO_LOGIN'];
        if ($temEmail) {
            $cols[] = 'USUARIO_EMAIL';
        }

        $total = 0;
        $corrigidos = 0;
        $loginCorrigidos = 0;
        $emailCorrigidos = 0;

        DB::table('USUARIO')
            ->select($cols)
            ->orderBy('USUARIO_ID')
            ->chunk(500, function ($rows) use ($dryRun, $temEmail, &$total, &$corrigidos, &$loginCorrigidos, &$emailCorrigidos) {
                foreach ($rows as $r) {
                    $total++;
                    $novoLogin = LoginLookupNormalizer::forStorage((string) $r->USUARIO_LOGIN);
                    $novoEmail = null;
                    $emailAtual = null;

                    if ($temEmail) {
                        $emailAtual = $r->USUARIO_EMAIL;
                        if ($emailAtual !== null && $emailAtual !== '') {
                            $novoEmail = LoginLookupNormalizer::forStorage((string) $emailAtual);
                        } elseif ($emailAtual === '') {
                            $novoEmail = '';
                        }
                    }

                    $mudancas = [];
                    if ($novoLogin !== (string) $r->USUARIO_LOGIN) {
                        $mudancas['USUARIO_LOGIN'] = $novoLogin;
                        $loginCorrigidos++;
                    }

                    if ($temEmail) {
                        $emailAtualStr = $emailAtual === null ? null : (string) $emailAtual;
                        if ($novoEmail !== $emailAtualStr) {
                            $mudancas['USUARIO_EMAIL'] = $novoEmail;
                            $emailCorrigidos++;
                        }
                    }

                    if ($mudancas === []) {
                        continue;
                    }

                    $corrigidos++;
                    if (! $dryRun) {
                        DB::table('USUARIO')
                            ->where('USUARIO_ID', (int) $r->USUARIO_ID)
                            ->update($mudancas);
                    }
                }
            });

        Log::info('usuario.saneamento_logins_legados', [
            'dry_run' => $dryRun,
            'total' => $total,
            'corrigidos' => $corrigidos,
            'login_corrigidos' => $loginCorrigidos,
            'email_corrigidos' => $emailCorrigidos,
        ]);

        return [
            'total' => $total,
            'corrigidos' => $corrigidos,
            'login_corrigidos' => $loginCorrigidos,
            'email_corrigidos' => $emailCorrigidos,
        ];
    }
}

