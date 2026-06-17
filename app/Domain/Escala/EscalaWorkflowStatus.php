<?php

namespace App\Domain\Escala;

/**
 * Estados canônicos do workflow da escala v3 (competência + setor).
 */
final class EscalaWorkflowStatus
{
    public const RASCUNHO = 'RASCUNHO';

    /** Em validação pela Superintendência (valor curto: limite legado VARCHAR(20) em alguns bancos). */
    public const EM_VALIDACAO_SUPERINTENDENCIA = 'EM_VAL_SUPER';

    /** Devolvida ao setor para correção (valor curto). */
    public const DEVOLVIDA_PARA_AJUSTE = 'DEVOLVIDA_AJUSTE';

    /** Homologada pela SAGEP (valor curto). */
    public const HOMOLOGADO_SAGEP = 'HOMOLOG_SAGEP';

    /**
     * @return list<string>
     */
    public static function todos(): array
    {
        return [
            self::RASCUNHO,
            self::EM_VALIDACAO_SUPERINTENDENCIA,
            self::DEVOLVIDA_PARA_AJUSTE,
            self::HOMOLOGADO_SAGEP,
        ];
    }

    /**
     * Estados em que a grade mensal pode ser editada (turnos/dias futuros).
     */
    public static function permiteEdicaoGrade(?string $status): bool
    {
        $s = strtoupper(trim((string) $status));

        return in_array($s, [self::RASCUNHO, self::DEVOLVIDA_PARA_AJUSTE], true);
    }
}
