<?php

namespace App\Support;

use Illuminate\Auth\AuthenticationException;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Schema;

/**
 * Escritas com trilha jurídica: toda mutação com auditoria deve vincular USUARIO_ID
 * a um CPF/identificador de sessão válido.
 */
class GenteAuditWriter
{
    public static function requireAuthenticatedUserId(): int
    {
        $id = Auth::id();
        if ($id === null) {
            throw new AuthenticationException('Ação requer sessão de usuário autenticada para rastreabilidade (AUDIT_LOG / USUARIO_ID).');
        }

        return (int) $id;
    }

    /**
     * Frente 2 (HMAC): null = funcionalidade desligada ou rota isenta; true/false = registo.
     */
    public static function assinaturaValidadaParaAudit(): ?bool
    {
        if (! RequestSigning::enabled()) {
            return null;
        }
        $r = request();
        if (! $r->attributes->has('gente.assinatura_validada')) {
            return null;
        }
        $v = $r->attributes->get('gente.assinatura_validada');

        return $v === null ? null : (bool) $v;
    }

    /**
     * @param  array<string, mixed>  $row
     * @return array<string, mixed>
     */
    public static function mergeAssinaturaValidadaColumn(array $row): array
    {
        $v = self::assinaturaValidadaParaAudit();
        if ($v === null || ! Schema::hasTable('AUDIT_LOG')) {
            return $row;
        }
        foreach (Schema::getColumnListing('AUDIT_LOG') as $c) {
            if (strtoupper($c) === 'ASSINATURA_VALIDADA') {
                $row[$c] = $v ? 1 : 0;

                return $row;
            }
        }

        return $row;
    }

    /**
     * Inserção em AUDIT_LOG com HASH_CONCAT (Frente 4) e coluna de assinatura HMAC quando ativa.
     *
     * @param  array<string, mixed>  $row
     */
    public static function insertChainedRow(array $row): int
    {
        if (! Schema::hasTable('AUDIT_LOG')) {
            return 0;
        }
        $row = self::mergeAssinaturaValidadaColumn($row);
        $row = self::fillTimestampColumnsIfMissing($row);
        return AuditLogChainer::insertChainedRow($row);
    }

    /**
     * @param  array<string, mixed>  $row
     * @return array<string, mixed>
     */
    private static function fillTimestampColumnsIfMissing(array $row): array
    {
        $cols = Schema::getColumnListing('AUDIT_LOG');
        $now = now();
        foreach (['created_at', 'updated_at'] as $logical) {
            $foundCol = null;
            foreach ($cols as $c) {
                if (strcasecmp((string) $c, $logical) === 0) {
                    $foundCol = $c;
                    break;
                }
            }
            if ($foundCol === null) {
                continue;
            }
            if (! array_key_exists($foundCol, $row)) {
                $row[$foundCol] = $now;
            }
        }

        return $row;
    }
}
