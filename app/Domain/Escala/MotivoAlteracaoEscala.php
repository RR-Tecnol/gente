<?php

namespace App\Domain\Escala;

/**
 * Contrato de regras de negócio — motivos de alteração de escala (Escala de Trabalho).
 *
 * Fonte normativa: Lei Orgânica do Município (continuidade do serviço / educação) e
 * trilha TCE-MA. O banco espelha estes códigos via SIGLA; a validação ativa usa sempre
 * estes campos, não o que estiver "solto" só no BD.
 *
 * Compatível com PHP 7.3+ (sem native enum 8.1).
 */
final class MotivoAlteracaoEscala
{
    public const ERRO_LANCAMENTO = 'ERRO_LANCAMENTO';

    public const AJUSTE_OPERACIONAL = 'AJUSTE_OPERACIONAL';

    /** Substituição de caráter emergencial / cobertura de aula (continuidade). */
    public const SUBSTITUICAO_EMERGENCIAL = 'SUBSTITUICAO_EMERGENCIAL';

    public const DOBRA_TURNO = 'DOBRA_TURNO';

    public const LICENCA_AFASTAMENTO_LEGAL = 'LICENCA_AFASTAMENTO_LEGAL';

    public const HOMOLOGACAO_RETROATIVA = 'HOMOLOGACAO_RETROATIVA';

    /**
     * Definições canónicas: ordem importa apenas para o seeder / telas.
     *
     * @return list<array{sigla: string, titulo: string, descricao: string, exige_documento: bool, base_legal: string|null, impacto_financeiro: string|null}>
     */
    public static function definicoesCanonicas(): array
    {
        return [
            [
                'sigla' => self::ERRO_LANCAMENTO,
                'titulo' => 'Erro de Lançamento',
                'descricao' => 'Correção de dado inserido incorretamente na planilha ou no sistema.',
                'exige_documento' => false,
                'base_legal' => null,
                'impacto_financeiro' => 'Sem impacto de rubrica se apenas corrigir o planejamento; conferir SISFOLHA se a folha já foi processada no período.',
            ],
            [
                'sigla' => self::AJUSTE_OPERACIONAL,
                'titulo' => 'Ajuste Operacional',
                'descricao' => 'Mudança alinhada a reunião ou diretriz do setor (recomendável registrar ata ou ofício).',
                'exige_documento' => false,
                'base_legal' => 'Responsabilidade do gestor pela operação (Lei nº 8.666/1993, princípios de boa-fé administrativa).',
                'impacto_financeiro' => 'Dependente do tipo de ajuste; verificar geração de extra ou substituição na folha.',
            ],
            [
                'sigla' => self::SUBSTITUICAO_EMERGENCIAL,
                'titulo' => 'Substituição emergencial / cobertura de aula',
                'descricao' => 'Cobertura de ausência, convocação excepcional ou reequilíbrio de quadro para manter a aula.',
                'exige_documento' => true,
                'base_legal' => 'LO Municipal art. 135 (continuidade do serviço público e oferta de ensino) — vinculado à garantia de aula dada na rede.',
                'impacto_financeiro' => 'Pode gerar rubricas de substituição / plantão conforme PCCV (Magistério) e regras SISFOLHA.',
            ],
            [
                'sigla' => self::DOBRA_TURNO,
                'titulo' => 'Dobra de turno',
                'descricao' => 'Dobra de jornada ou plantão excepcional conforme regimento escolar ou unidade de saúde (SEMED/SEMUS).',
                'exige_documento' => false,
                'base_legal' => 'LO art. 135 (continuidade) e regramento interno da secretaria; documentar se houver portaria local.',
                'impacto_financeiro' => 'Hora extra / adicional de sobreaviso pode incidir; conferir tabela e vínculo no SISFOLHA.',
            ],
            [
                'sigla' => self::LICENCA_AFASTAMENTO_LEGAL,
                'titulo' => 'Licença / afastamento legal',
                'descricao' => 'Ausência com fundamento em licença, atestado ou registro em processo de pessoal.',
                'exige_documento' => true,
                'base_legal' => 'Estatuto do servidor (Lei 4.615/2006, regime geral) e normas de saúde / PCCV de magistério (Lei 4.928/2008) quando docente.',
                'impacto_financeiro' => 'Desconto ou abono conforme evento; não gerar vencimento indevido de regência inexistente.',
            ],
            [
                'sigla' => self::HOMOLOGACAO_RETROATIVA,
                'titulo' => 'Homologação ou correção retroativa',
                'descricao' => 'Ajuste após homologação de escala, auditoria interna ou determinação de autoridade competente.',
                'exige_documento' => true,
                'base_legal' => 'Transparência e rastreabilidade perante o ordenador (TCE-MA / LRF) — exige ato ou CI que embasa o retrotivo.',
                'impacto_financeiro' => 'Retificar competência e rubricas no encerramento da folha; evitar pagamento fora do período de direito.',
            ],
        ];
    }

    /**
     * @return array{sigla: string, titulo: string, descricao: string, exige_documento: bool, base_legal: string|null, impacto_financeiro: string|null}|null
     */
    public static function regraPorSigla(string $sigla): ?array
    {
        $k = strtoupper(trim($sigla));
        foreach (self::definicoesCanonicas() as $d) {
            if ($d['sigla'] === $k) {
                return $d;
            }
        }

        return null;
    }

    public static function exigeDocumentoPorSigla(string $sigla): bool
    {
        $r = self::regraPorSigla($sigla);

        return $r ? (bool) $r['exige_documento'] : false;
    }
}
