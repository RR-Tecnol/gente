<?php

namespace App\Support;

use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

/**
 * Corrente de integridade: HASH_n = sha256( conteúdo_canónico + HASH_{n-1} ).
 * Pró-bloco (estilo cadeia): alterar o meio quebra a verificação a partir do próximo.
 */
final class AuditLogChainer
{
    private const GENESIS = 'GENTE_AUDIT_CHAIN_GENESIS_V1';

    public static function enabled(): bool
    {
        if (! (bool) config('gente.audit_log.chain_enabled', true)) {
            return false;
        }

        return Schema::hasTable('AUDIT_LOG') && Schema::hasColumn('AUDIT_LOG', 'HASH_CONCAT');
    }

    public static function previousHash(): string
    {
        if (! Schema::hasTable('AUDIT_LOG')) {
            return hash('sha256', self::GENESIS);
        }
        $h = DB::table('AUDIT_LOG')
            ->orderByDesc('id')
            ->value('HASH_CONCAT');
        if (is_string($h) && strlen($h) === 64) {
            return $h;
        }

        return hash('sha256', self::GENESIS);
    }

    /**
     * @param  array<string, mixed>  $row  Colunas a persistir (sem HASH_CONCAT ainda)
     */
    public static function computeRowHash(array $row): string
    {
        $body = self::canonizeRow($row);
        $prev = self::previousHash();

        return hash('sha256', $body . $prev);
    }

    /**
     * @param  array<string, mixed>  $row
     */
    public static function canonizeRow(array $row): string
    {
        ksort($row, SORT_STRING);
        $normalized = [];
        foreach ($row as $k => $v) {
            if (strtoupper((string) $k) === 'HASH_CONCAT') {
                continue;
            }
            if (is_scalar($v) || $v === null) {
                $normalized[$k] = $v;
            } else {
                $normalized[$k] = json_encode($v, JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES);
            }
        }

        return json_encode($normalized, JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES);
    }

    /**
     * Inserção bruta alinhada à cadeia (usada onde ainda se usa DB::table).
     *
     * @param  array<string, mixed>  $row
     * @return int ID inserido, ou 0
     */
    public static function insertChainedRow(array $row): int
    {
        if (! Schema::hasTable('AUDIT_LOG')) {
            return 0;
        }
        if (self::enabled()) {
            $row['HASH_CONCAT'] = self::computeRowHash($row);
        }

        return (int) DB::table('AUDIT_LOG')->insertGetId($row, 'id');
    }
}
