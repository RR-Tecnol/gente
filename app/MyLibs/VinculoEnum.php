<?php

namespace App\MyLibs;

/**
 * Constantes de identificação de Vínculos de trabalho.
 *
 * O método resolveVinculo() detecta o tipo via sigla ou descrição do banco,
 * tornando-o resiliente independentemente dos IDs cadastrados.
 *
 * Tipos suportados pelo motor de folha:
 *  - SERVIDOR_EFETIVO   : Servidor público estatutário (RPPS)
 *  - CARGO_COMISSAO     : Cargo em comissão (RGPS – livre nomeação)
 *  - ESTAGIARIO         : Estagiário (sem INSS/FGTS patronal)
 *  - OUTRO              : Qualquer outro (sem regras específicas)
 */
class VinculoEnum
{
    const SERVIDOR_EFETIVO = 'SERVIDOR_EFETIVO';
    const CARGO_COMISSAO = 'CARGO_COMISSAO';
    const ESTAGIARIO = 'ESTAGIARIO';
    const OUTRO = 'OUTRO';

    /**
     * Palavras-chave que identificam cada tipo (case-insensitive).
     * Basta conter qualquer uma das palavras para classificar o vínculo.
     */
    private const MAPA = [
        self::SERVIDOR_EFETIVO => [
            'efetivo',
            'estatutário',
            'estatutario',
            'rpps',
            'concursado',
            'regime próprio',
            'regime proprio',
        ],
        self::CARGO_COMISSAO => [
            'comissão',
            'comissao',
            'das',
            'cds',
            'cargo comissionado',
            'livre nomeação',
            'livre nomeacao',
            'nomeado',
        ],
        self::ESTAGIARIO => [
            'estágio',
            'estagio',
            'estagiário',
            'estagiario',
        ],
    ];

    /**
     * Resolve o tipo de vínculo a partir da sigla e/ou descrição.
     *
     * @param string|null $sigla      VINCULO_SIGLA
     * @param string|null $descricao  VINCULO_DESCRICAO
     * @return string  Uma das constantes desta classe
     */
    public static function resolveVinculo(?string $sigla, ?string $descricao): string
    {
        $texto = strtolower(($sigla ?? '') . ' ' . ($descricao ?? ''));

        foreach (self::MAPA as $tipo => $termos) {
            foreach ($termos as $termo) {
                if (str_contains($texto, $termo)) {
                    return $tipo;
                }
            }
        }

        return self::OUTRO;
    }
}
