<?php

namespace App\Support\Scripts;

use App\Models\Usuario;
use App\Support\LoginLookupNormalizer;
use App\Support\UsuarioLoginResolver;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Schema;

/**
 * Remove registos USUARIO "fantasma" (sem nome) alinhados ao mesmo funcionário/CPF
 * de um email de referência, migrando permissões para o registo preferido.
 */
final class HardCleanupUsuariosFantasmas
{
    /**
     * @return array{keeper_id: int|null, removidos: int, detalhe: list<array<string, mixed>>}
     */
    public static function run(string $emailLogin, bool $dryRun = true, bool $force = false): array
    {
        if (app()->environment('production') && ! $force) {
            Log::warning('hard_cleanup.bloqueado_em_producao_sem_force', ['email' => $emailLogin]);
            return ['keeper_id' => null, 'removidos' => 0, 'detalhe' => [['erro' => 'Execução bloqueada em produção. Use --force conscientemente.']]];
        }

        $key = LoginLookupNormalizer::forDatabaseLookup($emailLogin);
        if ($key === '' || ! Schema::hasTable('USUARIO')) {
            return ['keeper_id' => null, 'removidos' => 0, 'detalhe' => [['erro' => 'login vazio ou tabela USUARIO inexistente']]];
        }

        $keeper = Usuario::query()
            ->where('USUARIO_LOGIN', $key)
            ->where('USUARIO_ATIVO', 1)
            ->orderByDesc('USUARIO_ID')
            ->get();

        if ($keeper->isEmpty()) {
            return ['keeper_id' => null, 'removidos' => 0, 'detalhe' => [['erro' => "nenhum USUARIO ativo com login {$key}"]]];
        }
        if ($keeper->count() > 1) {
            $keeper = UsuarioLoginResolver::pickBestUser($keeper);
        } else {
            $keeper = $keeper->first();
        }

        $keeperId = (int) $keeper->USUARIO_ID;
        $detalhe = [];
        $removidos = 0;

        $ghosts = self::queryGhostsForKeeper($keeper);
        foreach ($ghosts as $g) {
            $gid = (int) $g->USUARIO_ID;
            if ($gid === $keeperId) {
                continue;
            }
            $detalhe[] = [
                'acao' => $dryRun ? 'seria_removido' : 'removido',
                'de_id' => $gid,
                'para_keeper' => $keeperId,
            ];
            if (! $dryRun) {
                UnificarUsuarios::mergeOneIntoKeeper($keeperId, $gid, false);
            }
            $removidos++;
        }

        return [
            'keeper_id' => $keeperId,
            'removidos' => $removidos,
            'detalhe' => $detalhe,
        ];
    }

    /**
     * @return \Illuminate\Database\Eloquent\Collection<int, \App\Models\Usuario>
     */
    private static function queryGhostsForKeeper(Usuario $keeper)
    {
        $q = Usuario::query()
            ->where('USUARIO_ATIVO', 1)
            ->where('USUARIO_ID', '!=', (int) $keeper->USUARIO_ID)
            ->where(function ($w) {
                $w->whereNull('USUARIO_NOME')->orWhere('USUARIO_NOME', '');
            });

        $q->where(function ($w) use ($keeper) {
            $fid = ! empty($keeper->FUNCIONARIO_ID) && (int) $keeper->FUNCIONARIO_ID > 0
                ? (int) $keeper->FUNCIONARIO_ID
                : 0;
            $cpf = (Schema::hasColumn('USUARIO', 'USUARIO_CPF') && $keeper->USUARIO_CPF)
                ? trim((string) $keeper->USUARIO_CPF)
                : '';
            if ($fid <= 0 && $cpf === '') {
                $w->whereRaw('0 = 1');

                return;
            }
            if ($fid > 0) {
                $w->where('FUNCIONARIO_ID', $fid);
            }
            if ($cpf !== '') {
                if ($fid > 0) {
                    $w->orWhere('USUARIO_CPF', $cpf);
                } else {
                    $w->where('USUARIO_CPF', $cpf);
                }
            }
        });

        return $q->get();
    }
}
