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
    // ── INSS RGPS 2024 (alíquotas progressivas — DOU 29/12/2023) ─────────────
    private const INSS_RGPS = [
        // [limite superior, alíquota, parcela a deduzir]
        [1412.00, 0.075, 0.00],
        [2666.68, 0.09, 21.18],
        [4000.03, 0.12, 101.18],
        [7786.02, 0.14, 181.18],
    ];

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

    private const DEDUCAO_DEPENDENTE = 226.86;  // por dependente, 2025

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

        // Teto do salário de contribuição RGPS 2024: R$ 7.786,02
        return round(min($desconto, 7786.02 * 0.14), 2);
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
