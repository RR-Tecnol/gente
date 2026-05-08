<?php

namespace App\Support;

/**
 * Contrato TENANT_TYPE em GENTE_ASSIGNMENT (Fase 3A).
 *
 * @see config/gente.php rbac.tenant_types
 */
final class GenteTenantType
{
    public const SECRETARIA = 'SECRETARIA';

    public const UNIDADE = 'UNIDADE';

    public const POLO = 'POLO';

    public const GLOBAL_SEMED = 'GLOBAL_SEMED';

    public const GLOBAL_SEMAD = 'GLOBAL_SEMAD';

    /** @return list<string> */
    public static function canonical(): array
    {
        return [
            self::SECRETARIA,
            self::UNIDADE,
            self::POLO,
            self::GLOBAL_SEMED,
            self::GLOBAL_SEMAD,
        ];
    }

    public static function isValid(string $tenantType): bool
    {
        return in_array($tenantType, self::canonical(), true);
    }

    public static function assertValid(string $tenantType): void
    {
        if (! self::isValid($tenantType)) {
            throw new \InvalidArgumentException('TENANT_TYPE inválido: '.$tenantType);
        }
    }
}
