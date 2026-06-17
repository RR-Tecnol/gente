<?php

namespace App\Support\Scripts;

use App\Models\Usuario;
use App\Support\LoginLookupNormalizer;
use App\Support\UsuarioLoginResolver;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Schema;

/**
 * 1) Repõe o e-mail do registo "admin" que ainda partilhe o e-mail de TI.
 * 2) Funde em USUARIO_ID = keeper todos os restantes com mesmo e-mail (login) que o TI, exceto o keeper.
 */
final class NuclearTiDuplicados
{
    public const DEFAULT_EMAIL = 'ti@saoluis.ma.gov.br';

    public const ADMIN_FAKE_EMAIL = 'admin@sisfolha.legacy';

    /**
     * @return array{ok: bool, admin_email_ajustado: bool, removido_ids: list<int>, detalhe: list<string>, erro?: string}
     */
    public static function run(
        string $email = self::DEFAULT_EMAIL,
        ?int $keeperId = null,
        bool $dryRun = true,
        bool $force = false
    ): array {
        if (app()->environment('production') && ! $force) {
            Log::warning('nuclear_ti.bloqueado_em_producao_sem_force', ['email' => $email]);
            return ['ok' => false, 'erro' => 'Execução bloqueada em produção. Use --force conscientemente.', 'admin_email_ajustado' => false, 'removido_ids' => [], 'detalhe' => []];
        }
        if (! Schema::hasTable('USUARIO')) {
            return ['ok' => false, 'erro' => 'Tabela USUARIO inexistente', 'admin_email_ajustado' => false, 'removido_ids' => [], 'detalhe' => []];
        }
        if (! Schema::hasColumn('USUARIO', 'USUARIO_EMAIL')) {
            return ['ok' => false, 'erro' => 'USUARIO_EMAIL inexistente', 'admin_email_ajustado' => false, 'removido_ids' => [], 'detalhe' => []];
        }

        $key = LoginLookupNormalizer::forDatabaseLookup($email);
        $removidoIds = [];
        $detalhe = [];
        $adminAjustou = false;

        $qAdmin = Usuario::query()
            ->where('USUARIO_LOGIN', 'admin');
        if (in_array(\Illuminate\Support\Facades\DB::connection()->getDriverName(), ['sqlsrv', 'dblib', 'odbc'], true)) {
            $qAdmin->whereRaw('LOWER(LTRIM(RTRIM(USUARIO_EMAIL))) = LOWER(?)', [trim($email)]);
        } else {
            $qAdmin->whereRaw('LOWER(TRIM(USUARIO_EMAIL)) = ?', [strtolower(trim($email))]);
        }
        $adm = $qAdmin->first();
        if ($adm && (int) $adm->USUARIO_ID !== $keeperId) {
            $ida = (int) $adm->USUARIO_ID;
            if ($dryRun) {
                $detalhe[] = "Seria alterado: USUARIO_ID={$ida} (admin) USUARIO_EMAIL → " . self::ADMIN_FAKE_EMAIL;
            } else {
                $adm->USUARIO_EMAIL = self::ADMIN_FAKE_EMAIL;
                $adm->save();
                $adminAjustou = true;
                $detalhe[] = "Admin (ID {$ida}): e-mail redefinido para " . self::ADMIN_FAKE_EMAIL;
            }
            Log::warning('nuclear_ti.admin_email_afastado_do_ti', [
                'usario_id' => $ida, 'de_email' => $email, 'para' => self::ADMIN_FAKE_EMAIL, 'dry_run' => $dryRun,
            ]);
        }

        $candidatos = Usuario::query();
        if (in_array(\Illuminate\Support\Facades\DB::connection()->getDriverName(), ['sqlsrv', 'dblib', 'odbc'], true)) {
            $candidatos->where(function ($w) use ($key, $email) {
                $w->where('USUARIO_LOGIN', $key);
                $w->orWhereRaw('LOWER(LTRIM(RTRIM(USUARIO_EMAIL))) = LOWER(?)', [trim($email)]);
            });
        } else {
            $candidatos->where(function ($w) use ($key, $email) {
                $w->where('USUARIO_LOGIN', $key);
                $w->orWhereRaw('LOWER(TRIM(USUARIO_EMAIL)) = ?', [strtolower(trim($email))]);
            });
        }
        $all = $candidatos->orderBy('USUARIO_ID')->get();
        if ($all->count() <= 1) {
            $detalhe[] = 'Apenas um registo com este login/e-mail de TI — nada a fundir.';

            return [
                'ok' => true,
                'admin_email_ajustado' => $adminAjustou,
                'removido_ids' => $removidoIds,
                'detalhe' => $detalhe,
            ];
        }

        $allNaoAdmin = $all->filter(function (Usuario $u) {
            return strtolower((string) $u->USUARIO_LOGIN) !== 'admin';
        })->values();
        if ($allNaoAdmin->isEmpty()) {
            $detalhe[] = 'Apenas login admin associado ao e-mail alvo após filtro; nenhuma fusão executada.';
            return [
                'ok' => true,
                'admin_email_ajustado' => $adminAjustou,
                'removido_ids' => $removidoIds,
                'detalhe' => $detalhe,
            ];
        }

        if ($keeperId !== null) {
            $keeper = $allNaoAdmin->firstWhere('USUARIO_ID', $keeperId);
            if (! $keeper) {
                return ['ok' => false, 'erro' => "USUARIO_ID={$keeperId} (keeper) não encontrado no conjunto candidato", 'admin_email_ajustado' => $adminAjustou, 'removido_ids' => [], 'detalhe' => $detalhe];
            }
        } else {
            $keeper = UsuarioLoginResolver::pickBestUser($allNaoAdmin);
            $keeperId = (int) $keeper->USUARIO_ID;
        }
        $detalhe[] = "Keeper canónico selecionado dinamicamente: USUARIO_ID={$keeperId}";

        foreach ($all as $u) {
            $id = (int) $u->USUARIO_ID;
            if ($id === $keeperId) {
                continue;
            }
            if (strtolower((string) $u->USUARIO_LOGIN) === 'admin') {
                continue;
            }
            if ($dryRun) {
                $removidoIds[] = $id;
                $detalhe[] = "Seria fundido e removido: USUARIO_ID={$id} → {$keeperId}";
            } else {
                UnificarUsuarios::mergeOneIntoKeeper($keeperId, $id, false);
                $removidoIds[] = $id;
                $detalhe[] = "Fundido e removido: USUARIO_ID={$id} → {$keeperId}";
                Log::warning('nuclear_ti.fantasma_removido', [
                    'removido_usuario_id' => $id, 'keeper_usuario_id' => $keeperId, 'email' => $email, 'login_key' => $key,
                ]);
            }
        }

        return [
            'ok' => true,
            'admin_email_ajustado' => $adminAjustou,
            'removido_ids' => $removidoIds,
            'detalhe' => $detalhe,
        ];
    }
}
