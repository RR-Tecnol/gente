<?php

namespace App\Services;

/**
 * Tabelas de Imposto vigentes 2025.
 *
 * INSS RPPS  → alíquota única de 14% (Regime Próprio — Servidor Estatutário)
 * INSS RGPS  → alíquotas progressivas (Regime Geral — Cargo em Comissão)
 * IRRF       → tabela progressiva mensal 2025 (MP 1.206/2024 + Lei 14.848/2024)
 *              Isenção: até R$ 2.824,00 (deduzida a dedução simplificada)
 *              Dedução por dependente: R$ 226,86/mês
 */
class TabelasImpostoService
{
    // ── INSS RGPS 2026 (alíquotas progressivas — Portaria Interministerial MPS/MF nº 13, de 09/01/2026) ─────────────
    // Faixas oficiais:
    //   até R$ 1.621,00         → 7,5%
    //   R$ 1.621,01 a 2.902,84  → 9%
    //   R$ 2.902,85 a 4.354,27  → 12%
    //   R$ 4.354,28 a 8.475,55  → 14% (teto)
    private const INSS_RGPS = [
        // [limite superior, alíquota, parcela a deduzir (calculada para o regime simplificado)]
        [1621.00, 0.075, 0.00],
        [2902.84, 0.09, 24.32],   // (2902,84 × 0,09) − (1621,00 × 0,075) acumulado
        [4354.27, 0.12, 111.40],
        [8475.55, 0.14, 198.49],
    ];

    // Teto INSS RGPS 2026
    private const INSS_RGPS_TETO = 8475.55;

    // ── INSS RPPS 2024 ────────────────────────────────────────────────────────
    private const INSS_RPPS_ALIQUOTA = 0.14;   // 14% — Lei 9.717/98, portaria local

    // ── IRRF 2025 (mensal — MP 1.206/2024 + Lei 14.848/2024) ─────────────────
    // BUG-S2 corrigido: isenção até R$ 2.824,00 (era R$ 2.259,20 em 2024)
    private const IRRF_TABELA = [
        // [limite superior, alíquota, parcela a deduzir]
        [2824.00, 0.00, 0.00],     // isento (inclui dedução simplificada R$ 564,80)
        [3751.05, 0.075, 211.80],
        [4664.68, 0.15, 493.05],
        [7083.49, 0.225, 843.16],
        [INF, 0.275, 1197.58],
    ];

    // Dedução por dependente IRRF mensal: R$ 189,59 (mantida desde 2024 — IN RFB 2.020/2021).
    // Atenção: O valor R$ 226,86 que aparecia anteriormente NÃO é dedução por dependente — pode ter
    // sido confusão com desconto simplificado mensal (R$ 564,80) ou erro de transcrição. Corrigido.
    private const DEDUCAO_DEPENDENTE = 189.59;

    // =========================================================================
    // INSS
    // =========================================================================

    /**
     * Calcula INSS do Regime Geral (RGPS) — progressivo.
     * Usado para Cargos em Comissão.
     */
    public function calcularInssRgps(float $salarioBruto): float
    {
        $desconto = 0.0;
        $baseRestante = $salarioBruto;
        $faixaAnterior = 0.0;

        foreach (self::INSS_RGPS as [$teto, $aliquota, $_]) {
            if ($baseRestante <= 0)
                break;

            $faixaTeto = min($teto, $salarioBruto);
            $baseNaFaixa = max(0, $faixaTeto - $faixaAnterior);

            $desconto += $baseNaFaixa * $aliquota;
            $faixaAnterior = $teto;

            if ($salarioBruto <= $teto)
                break;
        }

        // Teto INSS RGPS 2026: R$ 8.475,55 × 14% ≈ R$ 1.186,58 (limite efetivo de desconto)
        return round(min($desconto, self::INSS_RGPS_TETO * 0.14), 2);
    }

    /**
     * Calcula INSS do Regime Próprio (RPPS) — alíquota única.
     * Usado para Servidores Públicos Estatutários.
     */
    public function calcularInssRpps(float $salarioBruto): float
    {
        return round($salarioBruto * self::INSS_RPPS_ALIQUOTA, 2);
    }

    /**
     * Calcula IRRF mensal progressivo.
     *
     * @param float $baseCalculo  Salário bruto − INSS − deduções legais
     * @param int   $dependentes  Número de dependentes declarados
     */
    public function calcularIrrf(float $baseCalculo, int $dependentes = 0): float
    {
        $base = $baseCalculo - ($dependentes * self::DEDUCAO_DEPENDENTE);

        if ($base <= 0)
            return 0.0;

        foreach (self::IRRF_TABELA as [$teto, $aliquota, $parcela]) {
            if ($base <= $teto) {
                $imposto = ($base * $aliquota) - $parcela;
                return round(max(0, $imposto), 2);
            }
        }

        return 0.0;
    }

    /**
     * Retorna a alíquota efetiva de INSS RGPS para exibição/relatório.
     */
    public function aliquotaEfetivaRgps(float $salarioBruto): float
    {
        if ($salarioBruto <= 0)
            return 0.0;
        return round(($this->calcularInssRgps($salarioBruto) / $salarioBruto) * 100, 2);
    }
}
