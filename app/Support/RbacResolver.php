<?php

namespace App\Support;

use Carbon\Carbon;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

/**
 * Resolve permissões efetivas a partir de GENTE_ASSIGNMENT + GENTE_ROLE + GENTE_PERMISSION.
 */
class RbacResolver
{
    /** @var Carbon */
    protected $asOf;

    public function __construct(?Carbon $asOf = null)
    {
        $this->asOf = $asOf ?: Carbon::today();
    }

    public function asOfDate(): Carbon
    {
        return $this->asOf;
    }

    /**
     * UNIDADE_ID da âncora GLOBAL_SEMED (migration 100450 + config).
     */
    public static function resolveGlobalSemedUnidadeId(): ?int
    {
        return self::resolveUnidadeIdByNome((string) config('gente.rbac.anchor_unidade_nome_global_semed'));
    }

    /**
     * UNIDADE_ID da âncora GLOBAL_SEMAD.
     */
    public static function resolveGlobalSemadUnidadeId(): ?int
    {
        return self::resolveUnidadeIdByNome((string) config('gente.rbac.anchor_unidade_nome_global_semad'));
    }

    private static function resolveUnidadeIdByNome(string $nome): ?int
    {
        if ($nome === '' || ! Schema::hasTable('UNIDADE')) {
            return null;
        }
        $id = DB::table('UNIDADE')->where('UNIDADE_NOME', $nome)->value('UNIDADE_ID');

        return $id !== null ? (int) $id : null;
    }

    /**
     * Slugs de permissão ativos para o usuário (todas as assignments vigentes).
     *
     * @return list<string>
     */
    public function permissionSlugsForUsuario(int $usuarioId): array
    {
        if (! $this->rbacTablesPresent()) {
            return [];
        }

        $asOf = $this->asOf->toDateString();

        $rows = DB::table('GENTE_ASSIGNMENT as a')
            ->join('GENTE_ROLE as r', 'r.GENTE_ROLE_ID', '=', 'a.GENTE_ROLE_ID')
            ->join('GENTE_ROLE_PERMISSION as rp', 'rp.GENTE_ROLE_ID', '=', 'r.GENTE_ROLE_ID')
            ->join('GENTE_PERMISSION as p', 'p.GENTE_PERMISSION_ID', '=', 'rp.GENTE_PERMISSION_ID')
            ->where('a.USUARIO_ID', $usuarioId)
            ->where('a.ASSIGNMENT_ATIVO', 1)
            ->where('r.ROLE_ATIVO', 1)
            ->where('p.PERM_ATIVO', 1)
            ->where('a.VIGENCIA_INICIO', '<=', $asOf)
            ->where(function ($q) use ($asOf) {
                $q->whereNull('a.VIGENCIA_FIM')->orWhere('a.VIGENCIA_FIM', '>=', $asOf);
            })
            ->distinct()
            ->pluck('p.PERM_SLUG')
            ->map(function ($s) {
                return (string) $s;
            })
            ->values()
            ->all();

        return $rows;
    }

    /**
     * @param string|null $tenantType filtro; null = qualquer assignment
     * @param int|null    $tenantId   filtro; null se tenantType for null
     */
    public function can(int $usuarioId, string $permSlug, ?string $tenantType = null, ?int $tenantId = null): bool
    {
        if (! $this->rbacTablesPresent()) {
            return false;
        }

        if ($tenantType !== null) {
            GenteTenantType::assertValid($tenantType);
        }
        if ($tenantType !== null && ($tenantId === null || $tenantId <= 0)) {
            return false;
        }

        $asOf = $this->asOf->toDateString();

        $q = DB::table('GENTE_ASSIGNMENT as a')
            ->join('GENTE_ROLE as r', 'r.GENTE_ROLE_ID', '=', 'a.GENTE_ROLE_ID')
            ->join('GENTE_ROLE_PERMISSION as rp', 'rp.GENTE_ROLE_ID', '=', 'r.GENTE_ROLE_ID')
            ->join('GENTE_PERMISSION as p', 'p.GENTE_PERMISSION_ID', '=', 'rp.GENTE_PERMISSION_ID')
            ->where('a.USUARIO_ID', $usuarioId)
            ->where('a.ASSIGNMENT_ATIVO', 1)
            ->where('r.ROLE_ATIVO', 1)
            ->where('p.PERM_ATIVO', 1)
            ->where('p.PERM_SLUG', $permSlug)
            ->where('a.VIGENCIA_INICIO', '<=', $asOf)
            ->where(function ($query) use ($asOf) {
                $query->whereNull('a.VIGENCIA_FIM')->orWhere('a.VIGENCIA_FIM', '>=', $asOf);
            });

        if ($tenantType !== null) {
            $q->where('a.TENANT_TYPE', $tenantType)->where('a.TENANT_ID', $tenantId);
        }

        return $q->exists();
    }

    /**
     * Utilizador com assignment vigente ao papel SEMAD de auditoria (somente leitura na API de escala).
     */
    public function usuarioTemPapelSemadAuditoria(int $usuarioId): bool
    {
        if (! $this->rbacTablesPresent()) {
            return false;
        }
        $slug = (string) config('gente.rbac.role_slug_semad_auditor', 'auditoria_matriz_semad');
        $asOf = $this->asOf->toDateString();

        return DB::table('GENTE_ASSIGNMENT as a')
            ->join('GENTE_ROLE as r', 'r.GENTE_ROLE_ID', '=', 'a.GENTE_ROLE_ID')
            ->where('a.USUARIO_ID', $usuarioId)
            ->where('a.ASSIGNMENT_ATIVO', 1)
            ->where('r.ROLE_ATIVO', 1)
            ->where('r.ROLE_SLUG', $slug)
            ->where('a.VIGENCIA_INICIO', '<=', $asOf)
            ->where(function ($q) use ($asOf) {
                $q->whereNull('a.VIGENCIA_FIM')->orWhere('a.VIGENCIA_FIM', '>=', $asOf);
            })
            ->exists();
    }

    /**
     * Forçar UI read-only na manta (escala/frequência, etc.) quando há auditor SEMAD sem capacidade
     * de edição de escala em assignment GLOBAL_SEMED (âncora). Chapéu duplo TI+SEMAD: false.
     */
    public function semadMantaUiEnforceReadonly(int $usuarioId): bool
    {
        if (! $this->usuarioTemPapelSemadAuditoria($usuarioId)) {
            return false;
        }
        $anchor = self::resolveGlobalSemedUnidadeId();
        if ($anchor === null || $anchor <= 0) {
            return true;
        }
        $perm = (string) config('gente.rbac.perm_slug_edit_grade_semed', 'escala.grade.editar');
        if ($this->can($usuarioId, $perm, GenteTenantType::GLOBAL_SEMED, $anchor)) {
            return false;
        }

        return true;
    }

    /**
     * Capability RBAC para o Gate bypass-tenant (sem cabeçalho): override em assignment GLOBAL_SEMED + âncora.
     */
    public static function possuiCapacidadeBreakGlassRbac(int $usuarioId): bool
    {
        $anchor = self::resolveGlobalSemedUnidadeId();
        if ($anchor === null || $anchor <= 0) {
            return false;
        }
        $perm = (string) config('gente.rbac.perm_slug_override_grade', 'escala.override.sudo_grade');
        $r = new self();

        return $r->can($usuarioId, $perm, GenteTenantType::GLOBAL_SEMED, $anchor);
    }

    /**
     * Assignment usado na intervenção break-glass (auditoria): cabeçalho global + GLOBAL_SEMED + permissão.
     */
    public function assignmentIdParaIntervencaoGrade(int $usuarioId, ?Request $request): ?int
    {
        if (! $request || ! GenteSudoGlobalView::cabecalhoSolicitaVisaoGlobal($request)) {
            return null;
        }
        if (! $this->rbacTablesPresent()) {
            return null;
        }
        $anchor = self::resolveGlobalSemedUnidadeId();
        if ($anchor === null || $anchor <= 0) {
            return null;
        }
        $perm = (string) config('gente.rbac.perm_slug_override_grade', 'escala.override.sudo_grade');
        $asOf = $this->asOf->toDateString();

        $id = DB::table('GENTE_ASSIGNMENT as a')
            ->join('GENTE_ROLE as r', 'r.GENTE_ROLE_ID', '=', 'a.GENTE_ROLE_ID')
            ->join('GENTE_ROLE_PERMISSION as rp', 'rp.GENTE_ROLE_ID', '=', 'r.GENTE_ROLE_ID')
            ->join('GENTE_PERMISSION as p', 'p.GENTE_PERMISSION_ID', '=', 'rp.GENTE_PERMISSION_ID')
            ->where('a.USUARIO_ID', $usuarioId)
            ->where('a.ASSIGNMENT_ATIVO', 1)
            ->where('r.ROLE_ATIVO', 1)
            ->where('p.PERM_ATIVO', 1)
            ->where('a.TENANT_TYPE', GenteTenantType::GLOBAL_SEMED)
            ->where('a.TENANT_ID', $anchor)
            ->where('p.PERM_SLUG', $perm)
            ->where('a.VIGENCIA_INICIO', '<=', $asOf)
            ->where(function ($q) use ($asOf) {
                $q->whereNull('a.VIGENCIA_FIM')->orWhere('a.VIGENCIA_FIM', '>=', $asOf);
            })
            ->orderBy('a.GENTE_ASSIGNMENT_ID')
            ->value('a.GENTE_ASSIGNMENT_ID');

        return $id !== null ? (int) $id : null;
    }

    /**
     * União de UNIDADE_ID visíveis pelos assignments vigentes (Fase 3B).
     * GLOBAL_SEMED / SECRETARIA / GLOBAL_SEMAD: todas as UNIDADE ativas (refino por organograma = evolução futura).
     *
     * @return list<int>
     */
    public function unidadeIdsDoEscopoOperacional(int $usuarioId): array
    {
        if (! $this->rbacTablesPresent() || ! Schema::hasTable('UNIDADE')) {
            return [];
        }
        $asOf = $this->asOf->toDateString();
        $rows = DB::table('GENTE_ASSIGNMENT as a')
            ->join('GENTE_ROLE as r', 'r.GENTE_ROLE_ID', '=', 'a.GENTE_ROLE_ID')
            ->where('a.USUARIO_ID', $usuarioId)
            ->where('a.ASSIGNMENT_ATIVO', 1)
            ->where('r.ROLE_ATIVO', 1)
            ->where('a.VIGENCIA_INICIO', '<=', $asOf)
            ->where(function ($q) use ($asOf) {
                $q->whereNull('a.VIGENCIA_FIM')->orWhere('a.VIGENCIA_FIM', '>=', $asOf);
            })
            ->select(['a.TENANT_TYPE', 'a.TENANT_ID'])
            ->distinct()
            ->get();

        $ids = [];
        $expandirTodasUnidades = false;
        foreach ($rows as $row) {
            $type = (string) $row->TENANT_TYPE;
            $tid = (int) $row->TENANT_ID;
            if ($type === GenteTenantType::UNIDADE && $tid > 0) {
                $ids[] = $tid;
            } elseif ($type === GenteTenantType::POLO && $tid > 0 && Schema::hasTable('UNIDADE_POLO')) {
                $poloIds = DB::table('UNIDADE_POLO')
                    ->where('POLO_ID', $tid)
                    ->where('VINCULO_ATIVO', 1)
                    ->where('VIGENCIA_INICIO', '<=', $asOf)
                    ->where(function ($q) use ($asOf) {
                        $q->whereNull('VIGENCIA_FIM')->orWhere('VIGENCIA_FIM', '>=', $asOf);
                    })
                    ->pluck('UNIDADE_ID')
                    ->map(function ($v) {
                        return (int) $v;
                    })
                    ->all();
                foreach ($poloIds as $pid) {
                    $ids[] = $pid;
                }
            } elseif (in_array($type, [GenteTenantType::GLOBAL_SEMED, GenteTenantType::SECRETARIA, GenteTenantType::GLOBAL_SEMAD], true)) {
                $expandirTodasUnidades = true;
            }
        }
        if ($expandirTodasUnidades) {
            foreach ($this->unidadeIdsTodasAtivas() as $u) {
                $ids[] = $u;
            }
        }

        return array_values(array_unique(array_filter($ids, static function ($v) {
            return (int) $v > 0;
        })));
    }

    /**
     * @return list<int>
     */
    private function unidadeIdsTodasAtivas(): array
    {
        $q = DB::table('UNIDADE');
        if (Schema::hasColumn('UNIDADE', 'UNIDADE_ATIVA')) {
            $q->where('UNIDADE_ATIVA', 1);
        } elseif (Schema::hasColumn('UNIDADE', 'UNIDADE_ATIVO')) {
            $q->where('UNIDADE_ATIVO', 1);
        }

        return $q->pluck('UNIDADE_ID')->map(function ($v) {
            return (int) $v;
        })->values()->all();
    }

    private function rbacTablesPresent(): bool
    {
        return Schema::hasTable('GENTE_ASSIGNMENT')
            && Schema::hasTable('GENTE_ROLE')
            && Schema::hasTable('GENTE_ROLE_PERMISSION')
            && Schema::hasTable('GENTE_PERMISSION');
    }
}
