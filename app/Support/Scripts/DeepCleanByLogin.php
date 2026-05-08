<?php

namespace App\Support\Scripts;

use App\Models\Usuario;
use App\Support\LoginLookupNormalizer;
use App\Support\UsuarioLoginResolver;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Schema;

/**
 * Unificação forçada por USUARIO_LOGIN e USUARIO_EMAIL (laboratório: ti@) — ignora CPF/FID.
 * Inclui também o login "admin" quando o USUARIO_EMAIL coincide com o e-mail alvo (ex.: e-mail
 * repartido entre admin e ti@) — caso o hard_cleanup (fantasmas por FID) não tivesse visto o registo.
 */
final class DeepCleanByLogin
{
    /**
     * @return array{ok: bool, email: string, login_key: string, ids_encontrados: list<int>, removidos: int, removido_ids: list<int>, detalhe: list<string>, erro?: string}
     */
    public static function run(string $email, ?int $keeperId = null, bool $dryRun = true, bool $force = false): array
    {
        if (app()->environment('production') && ! $force) {
            Log::warning('deep_clean.bloqueado_em_producao_sem_force', ['email' => $email]);
            return self::err('Execução bloqueada em produção. Use --force conscientemente.', $email, LoginLookupNormalizer::forDatabaseLookup($email), []);
        }

        $key = LoginLookupNormalizer::forDatabaseLookup($email);
        if ($key === '' || ! Schema::hasTable('USUARIO')) {
            return self::err('Tabela USUARIO em falta ou e-mail vazio', $email, $key, []);
        }

        $q = Usuario::query();
        if (Schema::hasColumn('USUARIO', 'USUARIO_EMAIL')) {
            $q->where(function ($w) use ($key, $email) {
                $w->where('USUARIO_LOGIN', $key);
                if (in_array(\Illuminate\Support\Facades\DB::connection()->getDriverName(), ['sqlsrv', 'dblib', 'odbc'], true)) {
                    $w->orWhereRaw('LOWER(LTRIM(RTRIM(USUARIO_EMAIL))) = LOWER(?)', [trim($email)]);
                } else {
                    $w->orWhereRaw('LOWER(TRIM(USUARIO_EMAIL)) = ?', [strtolower(trim($email))]);
                }
                $w->orWhere(function ($a) use ($email) {
                    $a->where('USUARIO_LOGIN', 'admin');
                    if (in_array(\Illuminate\Support\Facades\DB::connection()->getDriverName(), ['sqlsrv', 'dblib', 'odbc'], true)) {
                        $a->whereRaw('LOWER(LTRIM(RTRIM(USUARIO_EMAIL))) = LOWER(?)', [trim($email)]);
                    } else {
                        $a->whereRaw('LOWER(TRIM(USUARIO_EMAIL)) = ?', [strtolower(trim($email))]);
                    }
                });
            });
        } else {
            $q->where('USUARIO_LOGIN', $key);
        }

        $all = $q->orderBy('USUARIO_ID')->get();
        $ids = $all->pluck('USUARIO_ID')->map(fn ($id) => (int) $id)->values()->all();

        $removidoIds = [];
        $detalhe = [];
        if ($all->count() <= 1) {
            $detalhe[] = "Nada a fazer: {$all->count()} registro(s) com login/Email \"{$email}\" (ids: " . implode(', ', $ids) . ').';
            if ($all->isEmpty() && $dryRun) {
                $detalhe[] = 'Base sem linhas a casar; verifique o normalizador e o valor em USUARIO_LOGIN/EMAIL.';
            }
            Log::info('deep_clean.sem_duplicidade', [
                'email' => $email, 'ids' => $ids, 'keeper' => $keeperId,
            ]);

            return [
                'ok' => true,
                'email' => $email,
                'login_key' => $key,
                'ids_encontrados' => $ids,
                'removidos' => 0,
                'removido_ids' => $removidoIds,
                'detalhe' => $detalhe,
            ];
        }

        if ($keeperId !== null) {
            $keeper = $all->firstWhere('USUARIO_ID', $keeperId);
            if (! $keeper) {
                return self::err("Keeper USUARIO_ID={$keeperId} não encontrado no conjunto candidato.", $email, $key, $ids);
            }
        } else {
            $keeper = UsuarioLoginResolver::pickBestUser($all);
            $keeperId = (int) $keeper->USUARIO_ID;
        }
        $detalhe[] = "Keeper canónico selecionado dinamicamente: USUARIO_ID={$keeperId}";

        foreach ($all as $row) {
            $id = (int) $row->USUARIO_ID;
            if ($id === $keeperId) {
                continue;
            }
            $removidoIds[] = $id;
            if ($dryRun) {
                $detalhe[] = "Seria fundido: USUARIO_ID={$id} → keeper={$keeperId}";
                Log::info('deep_clean.fantasma_seria_removido', [
                    'de_id' => $id, 'para_keeper' => $keeperId, 'email' => $email, 'login_key' => $key,
                ]);
            } else {
                UnificarUsuarios::mergeOneIntoKeeper($keeperId, $id, false);
                $detalhe[] = "Fundido e removido: USUARIO_ID={$id} → {$keeperId}";
                Log::warning('deep_clean.fantasma_removido_do_banco', [
                    'removido_usuario_id' => $id, 'keeper_usuario_id' => $keeperId, 'email' => $email, 'login_key' => $key,
                ]);
            }
        }

        return [
            'ok' => true,
            'email' => $email,
            'login_key' => $key,
            'ids_encontrados' => $ids,
            'removidos' => count($removidoIds),
            'removido_ids' => $removidoIds,
            'detalhe' => $detalhe,
        ];
    }

    /**
     * @param  list<int>  $ids
     */
    private static function err(string $msg, string $email, string $key, array $ids): array
    {
        Log::error('deep_clean.erro', ['msg' => $msg, 'email' => $email, 'ids' => $ids]);

        return [
            'ok' => false,
            'erro' => $msg,
            'email' => $email,
            'login_key' => $key,
            'ids_encontrados' => $ids,
            'removidos' => 0,
            'removido_ids' => [],
            'detalhe' => [$msg],
        ];
    }

}
