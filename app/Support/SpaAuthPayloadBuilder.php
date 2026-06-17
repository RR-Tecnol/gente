<?php

namespace App\Support;

use App\Models\Funcionario;
use App\Models\Lotacao;
use App\Models\Usuario;
use App\Models\UsuarioUnidade;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Gate;
use Illuminate\Support\Facades\Request;
use Illuminate\Support\Facades\Schema;
use Illuminate\Support\Str;

/**
 * Resposta canónica de sessão (GET /api/auth/me e enriquecimento de POST /api/auth/login).
 * Uma identidade, todos os USUARIO_PERFIL ativos em "perfis" e "perfil_chaves".
 */
final class SpaAuthPayloadBuilder
{
    private static function resolverFuncionarioDoUsuario(Usuario $user): ?Funcionario
    {
        if (Schema::hasColumn('USUARIO', 'FUNCIONARIO_ID')) {
            $fid = (int) ($user->getAttribute('FUNCIONARIO_ID') ?? 0);
            if ($fid > 0) {
                return Funcionario::with('pessoa')->find($fid);
            }
        }

        if (Schema::hasColumn('FUNCIONARIO', 'USUARIO_ID')) {
            $byUsuarioId = Funcionario::with('pessoa')
                ->where('USUARIO_ID', (int) $user->USUARIO_ID)
                ->orderByDesc('FUNCIONARIO_ID')
                ->first();
            if ($byUsuarioId) {
                return $byUsuarioId;
            }
        }

        $cpfRaw = null;
        if (Schema::hasColumn('USUARIO', 'USUARIO_CPF')) {
            $cpfRaw = (string) ($user->getAttribute('USUARIO_CPF') ?? '');
        }
        if (! $cpfRaw) {
            $cpfRaw = (string) ($user->getAttribute('USUARIO_LOGIN') ?? '');
        }
        $cpf = preg_replace('/\D+/', '', $cpfRaw ?? '');
        if (! is_string($cpf) || strlen($cpf) !== 11 || ! Schema::hasTable('PESSOA')) {
            return null;
        }

        $cpfCol = null;
        foreach (['PESSOA_CPF_NUMERO', 'PESSOA_CPF'] as $c) {
            if (Schema::hasColumn('PESSOA', $c)) {
                $cpfCol = $c;
                break;
            }
        }
        if (! $cpfCol) {
            return null;
        }

        $pessoaId = (int) (DB::table('PESSOA')->where($cpfCol, $cpf)->value('PESSOA_ID') ?? 0);
        if ($pessoaId <= 0) {
            return null;
        }

        $q = Funcionario::with('pessoa')->where('PESSOA_ID', $pessoaId);
        if (Schema::hasColumn('FUNCIONARIO', 'FUNCIONARIO_DATA_FIM')) {
            $hoje = now()->toDateString();
            $q->orderByRaw("CASE WHEN FUNCIONARIO_DATA_FIM IS NULL OR FUNCIONARIO_DATA_FIM > ? THEN 0 ELSE 1 END", [$hoje]);
        }

        return $q->orderByDesc('FUNCIONARIO_ID')->first();
    }

    /**
     * Vínculo principal respeitando cabeçalho HTTP de contexto (Fase 8A), com validação de posse.
     */
    private static function resolverFuncionarioComContexto(Usuario $user, int $contextFuncionarioIdHeader): ?Funcionario
    {
        $fallback = self::resolverFuncionarioDoUsuario($user);
        if ($contextFuncionarioIdHeader <= 0) {
            return $fallback;
        }
        $candidato = Funcionario::with('pessoa')->find($contextFuncionarioIdHeader);
        if (! $candidato) {
            return $fallback;
        }
        if (Schema::hasColumn('FUNCIONARIO', 'USUARIO_ID')
            && (int) $candidato->getAttribute('USUARIO_ID') === (int) $user->USUARIO_ID) {
            return $candidato;
        }

        return $fallback;
    }

    /**
     * Todos os vínculos (FUNCIONARIO) associados ao utilizador — acúmulo legal (mesmo CPF / USUARIO_ID).
     *
     * @return list<array{id: int, matricula: string|null, nome: string|null, lotacao_ativa: array|null, vinculo_tipo: string|null}>
     */
    public static function listarVinculosUsuario(Usuario $user): array
    {
        $out = [];
        if (Schema::hasColumn('FUNCIONARIO', 'USUARIO_ID')) {
            $lista = Funcionario::with('pessoa')
                ->where('USUARIO_ID', (int) $user->USUARIO_ID)
                ->orderBy('FUNCIONARIO_ID')
                ->get();
            foreach ($lista as $f) {
                $out[] = self::montarVinculoPayload($f);
            }
        }
        if ($out !== []) {
            return $out;
        }
        $unico = self::resolverFuncionarioDoUsuario($user);

        return $unico ? [self::montarVinculoPayload($unico)] : [];
    }

    /**
     * @return array{id: int, matricula: string|null, nome: string|null, lotacao_ativa: array|null, vinculo_tipo: string|null}
     */
    private static function montarVinculoPayload(Funcionario $f): array
    {
        $vinTipo = null;
        if (Schema::hasColumn('FUNCIONARIO', 'VINCULO_ID') && $f->getAttribute('VINCULO_ID')) {
            $vid = (int) $f->getAttribute('VINCULO_ID');
            if ($vid > 0 && Schema::hasTable('VINCULO')) {
                $vinTipo = DB::table('VINCULO')->where('VINCULO_ID', $vid)->value('VINCULO_TIPO');
                $vinTipo = $vinTipo !== null ? (string) $vinTipo : null;
            }
        }

        return [
            'id' => (int) $f->FUNCIONARIO_ID,
            'matricula' => $f->FUNCIONARIO_MATRICULA,
            'nome' => optional($f->pessoa)->PESSOA_NOME,
            'lotacao_ativa' => self::lotacaoAtivaDoFuncionario($f),
            'vinculo_tipo' => $vinTipo,
        ];
    }

    private static function lotacaoAtivaDoFuncionario(?Funcionario $funcionario): ?array
    {
        if (! $funcionario) {
            return null;
        }
        $laQ = Lotacao::query()
            ->with(['setor.unidade'])
            ->where('FUNCIONARIO_ID', $funcionario->FUNCIONARIO_ID);
        if (Schema::hasColumn('LOTACAO', 'LOTACAO_DATA_FIM')) {
            $hoje = now()->toDateString();
            $laQ->where(function ($q) use ($hoje) {
                $q->whereNull('LOTACAO_DATA_FIM')
                    ->orWhere('LOTACAO_DATA_FIM', '>', $hoje);
            });
        }
        $la = $laQ->orderByDesc('LOTACAO_ID')->first();
        if (! $la) {
            return null;
        }
        $setor = $la->setor;
        $unid = $setor ? $setor->unidade : null;

        return [
            'lotacao_id' => $la->LOTACAO_ID,
            'vinculo_id' => $la->VINCULO_ID,
            'setor_id' => $la->SETOR_ID,
            'setor_nome' => $setor->SETOR_NOME ?? null,
            'setor_sigla' => $setor->SETOR_SIGLA ?? null,
            'unidade_id' => $unid->UNIDADE_ID ?? ($setor->UNIDADE_ID ?? null),
            'unidade_nome' => $unid->UNIDADE_NOME ?? null,
            'unidade_sigla' => $unid->UNIDADE_SIGLA ?? null,
        ];
    }

    public static function forAuthenticatedUser(Usuario $user): array
    {
        $ctxHeader = (string) config('gente.funcionario_context.header', 'X-Gente-Funcionario-Context-Id');
        $ctxHeaderVal = Request::hasHeader($ctxHeader) ? (int) Request::header($ctxHeader) : 0;
        $funcionario = self::resolverFuncionarioComContexto($user, $ctxHeaderVal);

        $vinculos = $user->usuarioPerfis()
            ->where('USUARIO_PERFIL_ATIVO', 1)
            ->with('perfil')
            ->get()
            ->filter(function ($up) {
                return $up->perfil !== null;
            })
            ->sortBy('PERFIL_ID')
            ->values();

        $perfisPayload = [];
        $perfilIds = [];
        $perfilChaves = [];
        foreach ($vinculos as $up) {
            $p = $up->perfil;
            $nome = (string) $p->PERFIL_NOME;
            $id = (int) $p->PERFIL_ID;
            $chave = strtoupper(str_replace('-', '_', Str::slug($nome, '_')));
            if ($chave === '') {
                $chave = 'PERFIL_' . $id;
            }
            $perfilIds[] = $id;
            $perfilChaves[] = $chave;
            $perfisPayload[] = [
                'id' => $id,
                'nome' => $nome,
                'chave' => $chave,
            ];
        }
        $perfilIds = array_values(array_unique($perfilIds));
        $perfilChaves = array_values(array_unique($perfilChaves));

        $primeiro = $vinculos->first();
        $perfilNome = $primeiro && $primeiro->perfil
            ? (string) $primeiro->perfil->PERFIL_NOME
            : null;
        if (! $perfilNome || mb_strtolower(trim($perfilNome), 'UTF-8') === 'usuário') {
            $perfilNome = 'funcionario';
        }

        $lotacaoAtiva = self::lotacaoAtivaDoFuncionario($funcionario);
        $funcionarioVinculos = self::listarVinculosUsuario($user);
        $funcionarioContextId = $funcionario ? (int) $funcionario->FUNCIONARIO_ID : null;

        $unidadesEscopo = UsuarioUnidade::query()
            ->with('unidade')
            ->where('USUARIO_ID', $user->USUARIO_ID)
            ->where('USUARIO_UNIDADE_ATIVO', 1)
            ->get()
            ->map(function (UsuarioUnidade $uu) {
                $u = $uu->unidade;

                return [
                    'usuario_unidade_id' => $uu->USUARIO_UNIDADE_ID,
                    'unidade_id' => $uu->UNIDADE_ID,
                    'fiscal' => (bool) $uu->USUARIO_UNIDADE_FISCAL,
                    'unidade_nome' => $u->UNIDADE_NOME ?? null,
                ];
            })
            ->values()
            ->all();

        $primeiroAcesso = (int) ($user->USUARIO_PRIMEIRO_ACESSO ?? 0) === 1;
        $alterarSenha = (int) ($user->USUARIO_ALTERAR_SENHA ?? 0) === 1;
        $forceChange = $primeiroAcesso || $alterarSenha;

        $canBypassTenant = Gate::forUser($user)->allows('bypass-tenant');
        $podeRbacBreakGlass = RbacResolver::possuiCapacidadeBreakGlassRbac((int) $user->getAttribute('USUARIO_ID'));

        $rbacResolver = new RbacResolver();
        $usuarioPk = (int) $user->getAttribute('USUARIO_ID');
        $rbacPermissionSlugs = $rbacResolver->permissionSlugsForUsuario($usuarioPk);
        sort($rbacPermissionSlugs);
        $semadAuditorReadonly = $rbacResolver->usuarioTemPapelSemadAuditoria($usuarioPk);
        $semadMantaUiReadonly = $rbacResolver->semadMantaUiEnforceReadonly($usuarioPk);

        $tenantScopeRingsPublic = [];
        foreach ((array) config('gente_tenant_rings.rings', []) as $ringKey => $ring) {
            if (! is_string($ringKey) || $ringKey === '' || ! is_array($ring)) {
                continue;
            }
            $tenantScopeRingsPublic[$ringKey] = [
                'path_prefixes' => array_values(array_map('strval', (array) ($ring['path_prefixes'] ?? []))),
                'semad_block_mutations' => (bool) ($ring['semad_block_mutations'] ?? false),
            ];
        }
        ksort($tenantScopeRingsPublic);

        $payload = [
            'id' => $user->USUARIO_ID,
            'login' => $user->USUARIO_LOGIN,
            'nome' => $user->USUARIO_NOME,
            'email' => $user->USUARIO_EMAIL,
            'perfil' => $perfilNome,
            'perfis' => $perfisPayload,
            'perfil_ids' => $perfilIds,
            'perfil_chaves' => $perfilChaves,
            'lotacao_ativa' => $lotacaoAtiva,
            'unidades_escopo' => $unidadesEscopo,
            'alterar_senha' => $alterarSenha,
            'primeiro_acesso' => $primeiroAcesso,
            'force_password_change' => $forceChange,
            /** Gate bypass-tenant + whitelist .env: o SPA só mostra o toggle se true */
            'can_bypass_tenant' => $canBypassTenant,
            /** RBAC: permissão escala.override.sudo_grade em assignment GLOBAL_SEMED (âncora) */
            'pode_rbac_break_glass_global' => $podeRbacBreakGlass,
            /** Slugs GENTE_PERMISSION vigentes (Fase 4 — sidebar / router) */
            'rbac_permission_slugs' => $rbacPermissionSlugs,
            /** Papel SEMAD auditoria matriz: UI somente leitura nas áreas da Manta */
            'semad_auditor_readonly' => $semadAuditorReadonly,
            /** Manta operacional: read-only na UI só se auditor SEMAD sem escala.grade.editar na âncora SEMED */
            'semad_manta_ui_readonly' => $semadMantaUiReadonly,
            /** Anéis tenant scope (prefixos API públicos; sem flags de enforce) */
            'tenant_scope_rings_public' => $tenantScopeRingsPublic,
            /** Nome do cabeçalho HTTP a enviar (alinhar ao GENTE_SUDO_GLOBAL_HEADER no backend) */
            'sudo_global_view_header' => GenteSudoGlobalView::headerName(),
            'funcionario' => $funcionario ? [
                'id' => $funcionario->FUNCIONARIO_ID,
                'matricula' => $funcionario->FUNCIONARIO_MATRICULA,
                'nome' => optional($funcionario->pessoa)->PESSOA_NOME ?? $user->USUARIO_NOME,
            ] : null,
            /** Acúmulo (Fase 8A): lista de matrículas / vínculos do mesmo login */
            'funcionario_vinculos' => $funcionarioVinculos,
            /** FUNCIONARIO_ID escolhido (cabeçalho HTTP ou vínculo principal) */
            'funcionario_context_id' => $funcionarioContextId,
            /** Nome do cabeçalho que o SPA deve enviar para forçar contexto */
            'funcionario_context_header' => $ctxHeader,
        ];

        if (RequestSigning::enabled()) {
            $k = (string) config('gente.request_signature.session_key', 'gente_request_signing_secret');
            $sec = session($k);
            if (! is_string($sec) || strlen($sec) < 32) {
                $sec = Str::random(64);
                session([$k => $sec]);
            }
            $payload['request_signing_enabled'] = true;
            $payload['request_signing_secret'] = $sec;
        } else {
            $payload['request_signing_enabled'] = false;
        }

        return $payload;
    }
}
