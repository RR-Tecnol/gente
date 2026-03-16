<?php

namespace App\Services;

/**
 * Tabelas de Imposto vigentes 2024.
 *
 * INSS RPPS  → alíquota única de 14% (Regime Próprio — Servidor Estatutário)
 * INSS RGPS  → alíquotas progressivas (Regime Geral — Cargo em Comissão)
 * IRRF       → tabela progressiva mensal 2024 (dedução por dependente: R$ 189,59)
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

    // ── IRRF 2024 (mensal — Lei 14.663/2023) ─────────────────────────────────
    private const IRRF_TABELA = [
        // [limite superior, alíquota, parcela a deduzir]
        [2259.20, 0.00, 0.00],
        [2826.65, 0.075, 169.44],
        [3751.05, 0.15, 381.44],
        [4664.68, 0.225, 662.77],
        [INF, 0.275, 896.00],
    ];

    private const DEDUCAO_DEPENDENTE = 189.59;  // por dependente, 2024

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
