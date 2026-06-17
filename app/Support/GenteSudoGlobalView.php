<?php

namespace App\Support;

use App\Models\Usuario;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Gate;
use Illuminate\Support\Facades\Schema;
use Illuminate\Support\Str;

/**
 * Visão global (bypass de tenant) só com Gate + cabeçalho explícito (Zero Trust / “Sudo mode”).
 */
final class GenteSudoGlobalView
{
    public const ACAO_AUDITORIA = 'ACESSO_GLOBAL_VISUALIZADO';

    public static function isEnabledInConfig(): bool
    {
        return (bool) config('gente.sudo_global_view.enabled', true);
    }

    public static function headerName(): string
    {
        $h = (string) config('gente.sudo_global_view.header', 'X-Gente-Global-View');

        return $h !== '' ? $h : 'X-Gente-Global-View';
    }

    public static function cabecalhoSolicitaVisaoGlobal(?Request $request): bool
    {
        if (! $request) {
            return false;
        }
        $v = (string) $request->header(self::headerName(), '');
        if ($v === '') {
            return false;
        }
        $n = strtolower(trim($v));

        return in_array($n, ['true', '1', 'yes', 'on'], true);
    }

    /**
     * Whitelist por USUARIO_ID/Login no .env (GENTE_SUPER_*) — não confiar só em tabela de perfis.
     */
    public static function usuarioNaWhitelistInviolavel(Usuario $user): bool
    {
        $ids = (array) config('gente.super_admins.usuario_ids', []);
        $pk = (int) $user->getAttribute('USUARIO_ID');
        if ($pk > 0 && in_array($pk, array_map('intval', $ids), true)) {
            return true;
        }
        $emails = (array) config('gente.super_admins.emails', []);
        $login = strtolower(trim((string) $user->getAttribute('USUARIO_LOGIN')));

        foreach ($emails as $e) {
            if ($e === null) {
                continue;
            }
            if ($login === strtolower(trim((string) $e))) {
                return true;
            }
        }

        return false;
    }

    public static function podeUsarVisaoGlobal(?Usuario $user, ?Request $request): bool
    {
        if (! $user || ! $request) {
            return false;
        }
        if (! self::cabecalhoSolicitaVisaoGlobal($request)) {
            return false;
        }

        return Gate::forUser($user)->allows('bypass-tenant');
    }

    /**
     * Uma trilha por requisição HTTP.
     */
    public static function auditarAcessoGlobalSeAplicavel(?Usuario $user, Request $request): void
    {
        if (! $user || ! self::podeUsarVisaoGlobal($user, $request)) {
            return;
        }
        if ((bool) $request->attributes->get('gente.sudo_global_audited', false)) {
            return;
        }
        if (! Schema::hasTable('AUDIT_LOG')) {
            $request->attributes->set('gente.sudo_global_audited', true);

            return;
        }
        $request->attributes->set('gente.sudo_global_audited', true);
        $cols = Schema::getColumnListing('AUDIT_LOG');
        $by = [];
        foreach ($cols as $c) {
            $by[strtolower($c)] = $c;
        }
        $pick = function (string ...$names) use ($by) {
            foreach ($names as $n) {
                if (isset($by[strtolower($n)])) {
                    return $by[strtolower($n)];
                }
            }

            return null;
        };
        $ctx = [
            'header' => self::headerName(),
            'path' => (string) $request->path(),
        ];
        if (Schema::hasTable('USUARIO')) {
            $id = (int) $user->getAttribute('USUARIO_ID');
            if ($id > 0) {
                $ctx['usuario_id'] = $id;
            }
        }
        $row = [];
        if ($c = $pick('ACAO', 'acao')) {
            $row[$c] = self::ACAO_AUDITORIA;
        }
        if ($c = $pick('TABELA', 'tabela')) {
            $row[$c] = 'TENANT_SCOPE';
        }
        if ($c = $pick('DADOS_NOVOS', 'dados_novos', 'contexto')) {
            $row[$c] = json_encode($ctx, JSON_UNESCAPED_UNICODE);
        } elseif ($c = $pick('DADOS_ANTIGOS', 'dados_antigos')) {
            $row[$c] = json_encode($ctx, JSON_UNESCAPED_UNICODE);
        }
        if ($c = $pick('USUARIO_ID', 'usuario_id')) {
            $row[$c] = (int) GenteAuditWriter::requireAuthenticatedUserId();
        }
        if ($c = $pick('IP')) {
            $row[$c] = (string) $request->ip();
        }
        if ($c = $pick('USER_AGENT', 'user_agent')) {
            $row[$c] = Str::limit((string) $request->userAgent(), 255, '');
        }
        if ($c = $pick('evento', 'EVENTO', 'event_type')) {
            $row[$c] = 'SUDO_GLOBAL_VIEW';
        }
        if ($c = $pick('created_at', 'CREATED_AT', 'DATA_HORA')) {
            $row[$c] = now();
        }
        if ($c = $pick('updated_at', 'UPDATED_AT')) {
            $row[$c] = now();
        }
        if ($row === []) {
            return;
        }
        try {
            GenteAuditWriter::insertChainedRow($row);
        } catch (\Throwable $e) {
            // não quebrar a requisição
        }
    }
}
