<?php

namespace App\Support;

use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

/**
 * Integridade polimórfica de GENTE_ASSIGNMENT (sem FK multi-tabela).
 */
final class GenteAssignmentValidator
{
    /**
     * @throws \InvalidArgumentException
     */
    public static function assertTenantRefExists(string $tenantType, int $tenantId): void
    {
        GenteTenantType::assertValid($tenantType);
        if ($tenantId <= 0) {
            throw new \InvalidArgumentException('TENANT_ID deve ser positivo.');
        }

        switch ($tenantType) {
            case GenteTenantType::UNIDADE:
            case GenteTenantType::SECRETARIA:
                self::assertRowExists('UNIDADE', 'UNIDADE_ID', $tenantId);

                return;
            case GenteTenantType::POLO:
                self::assertRowExists('POLO_EDUCACIONAL', 'POLO_ID', $tenantId);

                return;
            case GenteTenantType::GLOBAL_SEMED:
                self::assertMatchesAnchorUnidade($tenantId, (string) config('gente.rbac.anchor_unidade_nome_global_semed'));

                return;
            case GenteTenantType::GLOBAL_SEMAD:
                self::assertMatchesAnchorUnidade($tenantId, (string) config('gente.rbac.anchor_unidade_nome_global_semad'));

                return;
        }
    }

    private static function assertRowExists(string $table, string $pk, int $id): void
    {
        if (! Schema::hasTable($table)) {
            throw new \InvalidArgumentException('Tabela '.$table.' inexistente.');
        }
        $ok = DB::table($table)->where($pk, $id)->exists();
        if (! $ok) {
            throw new \InvalidArgumentException(sprintf('Referência inexistente: %s.%s=%d', $table, $pk, $id));
        }
    }

    private static function assertMatchesAnchorUnidade(int $tenantId, string $nomeAncora): void
    {
        if (! Schema::hasTable('UNIDADE')) {
            throw new \InvalidArgumentException('Tabela UNIDADE inexistente.');
        }
        $row = DB::table('UNIDADE')->where('UNIDADE_NOME', $nomeAncora)->where('UNIDADE_ID', $tenantId)->first();
        if (! $row) {
            throw new \InvalidArgumentException(
                'TENANT_ID de âncora GLOBAL_* deve ser o UNIDADE_ID da unidade âncora ('.$nomeAncora.').'
            );
        }
    }
}
