<?php

namespace App\Domain\Escala;

/**
 * Política de domínio: valida alterações de escala com base na regra canónica (código)
 * e usa o banco como espelho (FK, listagem, ambientes com dados legados).
 */
final class MotivoAlteracaoPolicy
{
    /**
     * Documento de referência obrigatório: regra vinda do domínio se houver SIGLA; senão, coluna do BD.
     */
    public static function exigeDocumentoReferencia(object $motivoRow): bool
    {
        if (property_exists($motivoRow, 'SIGLA') && is_string($motivoRow->SIGLA) && trim($motivoRow->SIGLA) !== '') {
            $r = MotivoAlteracaoEscala::regraPorSigla($motivoRow->SIGLA);

            if ($r !== null) {
                return (bool) $r['exige_documento'];
            }
        }

        return (bool) ($motivoRow->EXIGE_DOCUMENTO ?? false);
    }

    /**
     * @throws \RuntimeException
     */
    public static function assertDocumentoReferencia(?object $motivoRow, string $documentoReferencia): void
    {
        if (! $motivoRow) {
            return;
        }
        if (self::exigeDocumentoReferencia($motivoRow) && trim($documentoReferencia) === '') {
            throw new \RuntimeException('O motivo selecionado exige nº de portaria, CI, processo ou ofício (campo "Documento de referência") — regra de domínio (tipificação).');
        }
    }

    /**
     * Título e flags para exibição: prioriza a regra de código se SIGLA bater; senão, título do BD.
     */
    public static function tituloParaExibicao(object $motivoRow): string
    {
        if (property_exists($motivoRow, 'SIGLA') && is_string($motivoRow->SIGLA) && trim($motivoRow->SIGLA) !== '') {
            $r = MotivoAlteracaoEscala::regraPorSigla($motivoRow->SIGLA);
            if ($r !== null) {
                return $r['titulo'];
            }
        }

        return trim((string) ($motivoRow->TITULO ?? 'Motivo'));
    }

    /**
     * @return array{base_legal: string|null, impacto_financeiro: string|null}
     */
    public static function metadadosNormativos(object $motivoRow): array
    {
        if (property_exists($motivoRow, 'SIGLA') && is_string($motivoRow->SIGLA) && trim($motivoRow->SIGLA) !== '') {
            $r = MotivoAlteracaoEscala::regraPorSigla($motivoRow->SIGLA);
            if ($r !== null) {
                return [
                    'base_legal' => $r['base_legal'],
                    'impacto_financeiro' => $r['impacto_financeiro'],
                ];
            }
        }

        return [
            'base_legal' => null,
            'impacto_financeiro' => null,
        ];
    }
}
