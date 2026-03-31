<?php

namespace App\Services;

use Illuminate\Support\Facades\DB;

/**
 * MotorFolhaService — Motor de Cálculo de Folha de Pagamento GENTE v3
 *
 * Arquitetura 3 camadas:
 *   C1 — Proventos estruturais (vencimento base + anuênio)
 *   C2 — Adicionais permanentes (ADICIONAL_SERVIDOR)
 *   C3 — Lançamentos variáveis mensais (LANCAMENTO_FOLHA)
 *
 * Princípios: ZERO queries dentro do loop — batch em memória antes do cálculo.
 * Sprint 3 GENTE v3 — Prefeitura de São Luís / RR TECNOL
 */
class MotorFolhaService
{
    // Salário mínimo 2025 (idealmente buscar de CONFIGURACAO_SISTEMA)
    private const SALARIO_MIN_2025 = 1518.00;

    // Vínculos que têm direito ao piso salarial mínimo
    private const VINCULOS_PISO = ['servico_prestado', 'pss', 'comissao_puro'];

    // =========================================================================
    // MÉTODO PRINCIPAL
    // =========================================================================

    public function calcularFolha(int $folhaId): array
    {
        $folha = DB::table('FOLHA')->where('FOLHA_ID', $folhaId)->first();
        if (!$folha) {
            return ['ok' => false, 'erro' => "Folha {$folhaId} não encontrada."];
        }
        $competencia = $folha->FOLHA_COMPETENCIA; // AAAA-MM

        // ── PASSO 1: Carregar TUDO em memória antes do loop ──────────────────

        // Servidores ativos com vínculo e tabela salarial
        $servidores = DB::table('FUNCIONARIO as f')
            ->join('PESSOA as p', 'p.PESSOA_ID', '=', 'f.PESSOA_ID')
            ->leftJoin('VINCULO as v', 'v.VINCULO_ID', '=', 'f.VINCULO_ID')
            ->leftJoin('TABELA_SALARIAL as ts', function ($j) {
                $j->on('ts.CARREIRA_ID', '=', 'f.CARREIRA_ID')
                    ->on('ts.TABELA_CLASSE', '=', 'f.FUNCIONARIO_CLASSE')
                    ->on('ts.TABELA_REFERENCIA', '=', 'f.FUNCIONARIO_REFERENCIA');
            })
            ->leftJoin('PROGRESSAO_CONFIG as pc', 'pc.CARREIRA_ID', '=', 'f.CARREIRA_ID')
            ->where('f.FUNCIONARIO_ATIVO', 1)
            ->select([
                'f.FUNCIONARIO_ID',
                'f.FUNCIONARIO_DATA_INICIO',
                'f.FUNCIONARIO_REGIME_PREV',
                'p.PESSOA_DEPENDENTES_IRRF',
                'v.VINCULO_TIPO',
                'v.VINCULO_REGIME',
                'v.VINCULO_FGTS',
                'v.VINCULO_INSS',
                'v.VINCULO_IRRF',
                'v.VINCULO_ANUENIO_PCT',
                'ts.TABELA_VENCIMENTO_BASE',
                'pc.CONFIG_ANUENIO_PCT',
            ])
            ->get()
            ->keyBy('FUNCIONARIO_ID');

        if ($servidores->isEmpty()) {
            return ['ok' => false, 'erro' => 'Nenhum servidor ativo encontrado.'];
        }

        $funcIds = $servidores->keys()->toArray();

        // Adicionais permanentes ativos (C2)
        $adicionais = DB::table('ADICIONAL_SERVIDOR as ads')
            ->whereIn('ads.FUNCIONARIO_ID', $funcIds)
            ->where(function ($q) {
                $q->whereNull('ads.ADICIONAL_VIGENCIA_FIM')
                    ->orWhere('ads.ADICIONAL_VIGENCIA_FIM', '>=', now()->toDateString());
            })
            ->select('ads.*')
            ->get()
            ->groupBy('FUNCIONARIO_ID');

        // Lançamentos variáveis desta competência (C3)
        $lancamentos = DB::table('LANCAMENTO_FOLHA')
            ->where('FOLHA_ID', $folhaId)
            ->whereIn('FUNCIONARIO_ID', $funcIds)
            ->get()
            ->groupBy('FUNCIONARIO_ID');

        // Consignações ativas na competência
        $compFormatada = substr($competencia, 0, 7); // AAAA-MM
        $consignacoes = DB::table('CONSIG_PARCELA as cp')
            ->join('CONSIG_CONTRATO as cc', 'cc.CONTRATO_ID', '=', 'cp.CONTRATO_ID')
            ->where('cp.COMPETENCIA', $compFormatada)
            ->where('cp.STATUS', 'PENDENTE')
            ->where('cc.STATUS', 'ATIVO')
            ->whereIn('cc.FUNCIONARIO_ID', $funcIds)
            ->select('cc.FUNCIONARIO_ID', DB::raw('SUM(cp.VALOR_PARCELA) as total_consig'))
            ->groupBy('cc.FUNCIONARIO_ID')
            ->get()
            ->keyBy('FUNCIONARIO_ID');

        // Alíquota RPPS — tenta buscar config, usa 14% como fallback (SQLite não tem RPPS_CONFIG)
        try {
            $aliqRPPS = DB::table('RPPS_CONFIG')
                ->orderByDesc('VIGENCIA_INICIO')
                ->value('ALIQUOTA_SERVIDOR') ?? 14;
        } catch (\Throwable $e) {
            $aliqRPPS = 14;
        }
        $aliqRPPS = $aliqRPPS / 100;

        $salarioMin = self::SALARIO_MIN_2025;

        // ── PASSO 2: Loop de cálculo — ZERO queries adicionais ───────────────
        $resultados = [];

        foreach ($servidores as $funcId => $s) {
            $vinculoTipo = $s->VINCULO_TIPO ?? 'efetivo';
            $vencBase = (float) ($s->TABELA_VENCIMENTO_BASE ?? 0);

            // C1 — Provênto base diferenciado por modalidade de vínculo
            switch ($vinculoTipo) {
                case 'comissao_puro':
                    // Recebe apenas o valor do CC — sem progressão, sem anuênio
                    $provC1 = $vencBase;
                    $anuenioVal = 0.0;
                    break;

                case 'efetivo_cc_m1':
                    // Recebe o MAIOR entre vencimento efetivo e CC — nunca os dois
                    // TODO Sprint 4 avançado: buscar valor do CC e comparar
                    $provC1 = $vencBase;
                    $anuenioVal = 0.0; // progressão suspensa enquanto em CC
                    break;

                case 'efetivo_cc_m2':
                    // Vencimento efetivo + 55% do CC via ADICIONAL_SERVIDOR (C2)
                    $aliqAnuenio = (float) ($s->VINCULO_ANUENIO_PCT ?? $s->CONFIG_ANUENIO_PCT ?? 1.00) / 100;
                    $anoServ = $s->FUNCIONARIO_DATA_INICIO
                        ? now()->diffInYears(\Carbon\Carbon::parse($s->FUNCIONARIO_DATA_INICIO))
                        : 0;
                    $anuenioVal = $vencBase * $aliqAnuenio * $anoServ;
                    $provC1 = $vencBase + $anuenioVal;
                    break;

                case 'funcao_confianca':
                    // Efetivo + gratificação de função (gratificação entra via C2)
                    $aliqAnuenio = (float) ($s->VINCULO_ANUENIO_PCT ?? $s->CONFIG_ANUENIO_PCT ?? 1.00) / 100;
                    $anoServ = $s->FUNCIONARIO_DATA_INICIO
                        ? now()->diffInYears(\Carbon\Carbon::parse($s->FUNCIONARIO_DATA_INICIO))
                        : 0;
                    $anuenioVal = $vencBase * $aliqAnuenio * $anoServ;
                    $provC1 = $vencBase + $anuenioVal;
                    break;

                default:
                    // efetivo, servico_prestado, pss, professor, guarda — regra padrão
                    $aliqAnuenio = (float) ($s->VINCULO_ANUENIO_PCT ?? $s->CONFIG_ANUENIO_PCT ?? 1.00) / 100;
                    $anoServ = $s->FUNCIONARIO_DATA_INICIO
                        ? now()->diffInYears(\Carbon\Carbon::parse($s->FUNCIONARIO_DATA_INICIO))
                        : 0;
                    $anuenioVal = $vencBase * $aliqAnuenio * $anoServ;
                    $provC1 = $vencBase + $anuenioVal;
                    break;
            }

            // C2 — Adicionais permanentes
            $provC2 = 0.0;
            $basePrev = $provC1; // começa com C1
            foreach (($adicionais[$funcId] ?? collect()) as $ad) {
                $val = match ($ad->ADICIONAL_TIPO) {
                    'fixo' => (float) $ad->ADICIONAL_VALOR,
                    'percentual' => $vencBase * ((float) $ad->ADICIONAL_VALOR / 100),
                    'percentual_sm' => $salarioMin * ((float) $ad->ADICIONAL_VALOR / 100),
                    default => 0.0,
                };
                $provC2 += $val;

                // Acumula base previdência apenas se o adicional incide
                if ($ad->ADICIONAL_INCIDE_PREV) {
                    $basePrev += $val;
                }
            }

            // C3 — Lançamentos variáveis
            $provC3 = 0.0;
            $descC3 = 0.0;
            foreach (($lancamentos[$funcId] ?? collect()) as $lanc) {
                $total = (float) $lanc->LANCAMENTO_VALOR_TOTAL;
                if ($lanc->LANCAMENTO_TIPO === 'P') {
                    $provC3 += $total;
                    if ($lanc->LANCAMENTO_INCIDE_PREV)
                        $basePrev += $total;
                } else {
                    $descC3 += $total;
                }
            }

            $bruto = $provC1 + $provC2 + $provC3;

            // Piso salarial — ANTES de calcular descontos
            $complementoSM = 0.0;
            if (in_array($vinculoTipo, self::VINCULOS_PISO) && $bruto < $salarioMin) {
                $complementoSM = round($salarioMin - $bruto, 2);
                $bruto = $salarioMin;
            }

            // Desconto previdência
            $descPrev = 0.0;
            $incideInss = $s->VINCULO_INSS ?? true;
            if ($incideInss) {
                $regime = $s->VINCULO_REGIME ?? $s->FUNCIONARIO_REGIME_PREV ?? 'RPPS';
                $descPrev = ($regime === 'RPPS')
                    ? $basePrev * $aliqRPPS
                    : $this->calcularInssRgps($basePrev);
            }

            // Base IRRF = bruto − INSS − dependentes
            $dep = (int) ($s->PESSOA_DEPENDENTES_IRRF ?? 0);
            $baseIrrf = $bruto - $descPrev - ($dep * 226.86); // dedução 2025
            $descIRRF = ($s->VINCULO_IRRF ?? true) ? $this->calcularIrrf($baseIrrf) : 0.0;

            // Consignações
            $descConsig = (float) ($consignacoes[$funcId]->total_consig ?? 0);

            $descOutros = $descC3 + $descConsig;
            $liquido = $bruto - $descPrev - $descIRRF - $descOutros;

            // DETALHE_FOLHA não tem timestamps — omitir updated_at/created_at
            $resultados[$funcId] = [
                'FUNCIONARIO_ID' => $funcId,
                'FOLHA_ID' => $folhaId,
                'DETALHE_FOLHA_PROVENTOS' => round($bruto, 2),
                'DETALHE_FOLHA_DESCONTOS' => round($descPrev + $descIRRF + $descOutros, 2),
                'DETALHE_FOLHA_LIQUIDO' => round($liquido, 2),
                'DETALHE_BASE_PREV' => round($basePrev, 2),
                'DETALHE_BASE_IRRF' => round(max(0, $baseIrrf), 2),
                'DETALHE_DESC_PREV' => round($descPrev, 2),
                'DETALHE_DESC_IRRF' => round($descIRRF, 2),
                'DETALHE_DESC_OUTROS' => round($descOutros, 2),
                'DETALHE_VINCULO_TIPO' => $vinculoTipo,
                'DETALHE_COMPLEMENTO_SM' => $complementoSM,
            ];
        }

        // ── PASSO 3: Gravar em batch (chunks de 500) ─────────────────────────
        foreach (array_chunk($resultados, 500) as $chunk) {
            // Tenta upsert; se não suportado (SQLite antigo), faz merge manual
            try {
                DB::table('DETALHE_FOLHA')->upsert(
                    $chunk,
                    ['FUNCIONARIO_ID', 'FOLHA_ID'],
                    array_diff(array_keys(reset($chunk)), ['FUNCIONARIO_ID', 'FOLHA_ID'])
                );
            } catch (\Exception $e) {
                // Fallback: updateOrInsert individual
                foreach ($chunk as $row) {
                    DB::table('DETALHE_FOLHA')->updateOrInsert(
                        ['FUNCIONARIO_ID' => $row['FUNCIONARIO_ID'], 'FOLHA_ID' => $row['FOLHA_ID']],
                        $row
                    );
                }
            }
        }

        $col = collect($resultados);
        return [
            'ok' => true,
            'folha_id' => $folhaId,
            'competencia' => $competencia,
            'servidores' => count($resultados),
            'total_proventos' => round($col->sum('DETALHE_FOLHA_PROVENTOS'), 2),
            'total_descontos' => round($col->sum('DETALHE_FOLHA_DESCONTOS'), 2),
            'total_liquido' => round($col->sum('DETALHE_FOLHA_LIQUIDO'), 2),
            'total_comp_sm' => round($col->sum('DETALHE_COMPLEMENTO_SM'), 2),
        ];
    }

    // =========================================================================
    // TABELAS FISCAIS 2025
    // =========================================================================

    /** INSS RGPS 2025 — progressivo por faixa */
    private function calcularInssRgps(float $base): float
    {
        // [teto da faixa, alíquota]
        $faixas = [
            [1518.00, 0.075],
            [2666.68, 0.09],
            [4000.03, 0.12],
            [7786.02, 0.14],
        ];
        $desconto = 0.0;
        $anterior = 0.0;
        foreach ($faixas as [$teto, $aliq]) {
            if ($base <= $anterior)
                break;
            $faixa = min($base, $teto) - $anterior;
            $desconto += $faixa * $aliq;
            $anterior = $teto;
            if ($base <= $teto)
                break;
        }
        return round(min($desconto, $base * 0.14), 2);
    }

    /** IRRF 2025 — tabela progressiva (MP 1.206/2024 + Lei 14.848/2024) */
    private function calcularIrrf(float $base): float
    {
        if ($base <= 0)
            return 0.0;
        if ($base <= 2824.00)
            return 0.0;           // isento
        if ($base <= 3751.05)
            return round($base * 0.075 - 211.80, 2);
        if ($base <= 4664.68)
            return round($base * 0.15 - 493.05, 2);
        if ($base <= 7083.49)
            return round($base * 0.225 - 843.16, 2);
        return round($base * 0.275 - 1197.58, 2);
    }
}
