<?php

namespace App\Support;

use App\Models\Usuario;
use Illuminate\Database\Query\Builder;
use Illuminate\Http\Exceptions\HttpResponseException;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

/**
 * Isolamento por unidade: GENTE_ASSIGNMENT (RBAC) + USUARIO_UNIDADE (legado) + hierarquia de setores.
 * Visão global: Gate bypass-tenant (whitelist ou RBAC break-glass) + cabeçalho Sudo.
 */
class UnidadeEscopoUsuario
{
    /**
     * UNIDADE_ID a partir só de USUARIO_UNIDADE (legado).
     *
     * @return list<int>
     */
    private static function unidadeIdsLegado(?Usuario $user): array
    {
        if (! $user) {
            return [];
        }
        $qU = $user->usuarioUnidades();
        if (Schema::hasTable('USUARIO_UNIDADE') && Schema::hasColumn('USUARIO_UNIDADE', 'USUARIO_UNIDADE_ATIVO')) {
            $qU->where('USUARIO_UNIDADE_ATIVO', 1);
        }

        return $qU->pluck('UNIDADE_ID')->map(function ($v) {
            return (int) $v;
        })->unique()->values()->all();
    }

    /**
     * RBAC (polo / unidade / secretaria global) ∪ legado.
     *
     * @return list<int>
     */
    private static function unidadeIdsEscopoUnificado(?Usuario $user): array
    {
        if (! $user) {
            return [];
        }
        $uid = (int) $user->getAttribute('USUARIO_ID');
        $legado = self::unidadeIdsLegado($user);
        if ($uid <= 0) {
            return $legado;
        }
        $rbac = new RbacResolver();
        $doRbac = $rbac->unidadeIdsDoEscopoOperacional($uid);

        return array_values(array_unique(array_merge($doRbac, $legado)));
    }

    /**
     * Todos os setores ativos das unidades do usuário (teto antes de âncora/hierarquia).
     *
     * @return list<int>
     */
    public static function poolSetorIdsDasUnidades(?Usuario $user): array
    {
        if (! $user) {
            return [];
        }
        $unidadeIds = self::unidadeIdsEscopoUnificado($user);
        if ($unidadeIds === [] || ! Schema::hasTable('SETOR')) {
            return [];
        }
        $q = DB::table('SETOR')->whereIn('UNIDADE_ID', $unidadeIds);
        if (Schema::hasColumn('SETOR', 'SETOR_ATIVO')) {
            $q->where('SETOR_ATIVO', 1);
        }

        return $q->pluck('SETOR_ID')->map(fn ($v) => (int) $v)->unique()->values()->all();
    }

    /**
     * @return list<int>|null  null = visão global (Sudo+Gate); vazio = sem setores permitidos
     */
    public static function setorIdsPermitidos(?Usuario $user, ?Request $request = null): ?array
    {
        if (! $user) {
            return [];
        }
        if ($request && GenteSudoGlobalView::podeUsarVisaoGlobal($user, $request)) {
            GenteSudoGlobalView::auditarAcessoGlobalSeAplicavel($user, $request);

            return null;
        }

        $pool = self::poolSetorIdsDasUnidades($user);
        if ($pool === []) {
            return [];
        }

        $anchors = SetorHierarquiaEscopo::anchorsParaUsuario($user);
        if ($anchors === null) {
            return $pool;
        }

        $anchorsNoPool = array_values(array_intersect($anchors, $pool));

        return SetorHierarquiaEscopo::expandDescendentesNoPool($anchorsNoPool, $pool);
    }

    /**
     * @deprecated Use Gate::allows('bypass-tenant') + GenteSudoGlobalView. Mantido p/ leitura legada.
     */
    public static function temVisaoGlobal(Usuario $user): bool
    {
        if (! GenteSudoGlobalView::isEnabledInConfig()) {
            return false;
        }

        return GenteSudoGlobalView::usuarioNaWhitelistInviolavel($user);
    }

    /**
     * @return list<int>
     */
    public static function perfilIdsVisaoGlobalEscala(): array
    {
        $raw = config('gente.escala.visao_global_perfil_ids', [1, 2, 7, 13]);
        $out = is_array($raw) ? $raw : [];
        if ($out === []) {
            return [1, 2, 7, 13];
        }

        return $out;
    }

    public static function podeAcessarSetor(?Usuario $user, int $setorId, ?Request $request = null): bool
    {
        if (! $user) {
            return false;
        }
        $req = $request ?? request();
        if ($req && GenteSudoGlobalView::podeUsarVisaoGlobal($user, $req)) {
            return true;
        }
        $permitidos = self::setorIdsPermitidos($user, $request);
        if ($permitidos === null) {
            return true;
        }
        if ($permitidos === []) {
            return false;
        }

        return in_array($setorId, $permitidos, true);
    }

    public static function restringeQuerySetor(Builder $query, ?Usuario $user, string $setorColuna = 'SETOR_ID', ?Request $request = null): void
    {
        $permitidos = self::setorIdsPermitidos($user, $request);
        if ($permitidos === null) {
            return;
        }
        if ($permitidos === []) {
            $query->whereRaw('0 = 1');

            return;
        }
        $query->whereIn($setorColuna, $permitidos);
    }

    public static function abortoSeSetorNaoAutorizado(?Usuario $user, int $setorId, ?Request $request = null): void
    {
        $r = $request ?? request();
        if (self::podeAcessarSetor($user, $setorId, $r)) {
            return;
        }
        throw new HttpResponseException(
            response()->json([
                'ok' => false,
                'erro' => 'Acesso negado: o setor não está no seu escopo (USUARIO_UNIDADE e/ou RBAC polo/unidade). Para visão global, envie o cabeçalho '.\App\Support\GenteSudoGlobalView::headerName().' se tiver permissão de bypass (super_admin ou RBAC SAGEP).',
            ], 403)
        );
    }
}
