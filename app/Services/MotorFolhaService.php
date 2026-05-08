<?php

namespace App\Services;

use App\Jobs\ProcessarLoteFolhaJob;
use App\Models\Funcionario;
use App\Services\MotorFolha\MotorFolhaLoteContext;
use Illuminate\Bus\Batch;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\Bus;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Schema;

/**
 * MotorFolhaService — Motor de Cálculo de Folha de Pagamento GENTE v3
 *
 * Arquitetura 3 camadas:
 *   C1 — Proventos estruturais (vencimento base + anuênio)
 *   C2 — Adicionais permanentes (ADICIONAL_SERVIDOR)
 *   C3 — Lançamentos variáveis mensais (LANCAMENTO_FOLHA)
 *
 * Fase 13: lotes por FUNCIONARIO_ID + {@see MotorFolhaLoteContext} injectado (sem N+1 em AFASTAMENTO /
 * AVALIACAO_DESEMPENHO / CARGO). Persistência por lote em {@see DB::transaction} + upsert.
 *
 * Produção assíncrona: QUEUE_CONNECTION=database|redis + worker (ver PERFORMANCE_BACKLOG / comentários na API).
 */
class MotorFolhaService
{
    private const CHUNK_SIZE = 500;

    // Salário mínimo 2025 (idealmente buscar de CONFIGURACAO_SISTEMA)
    private const SALARIO_MIN_2025 = 1518.00;

    // Vínculos que têm direito ao piso salarial mínimo
    private const VINCULOS_PISO = ['servico_prestado', 'pss', 'comissao_puro'];

    /**
     * Filtro de servidores elegíveis ao motor: usa FUNCIONARIO_ATIVO apenas se a coluna existir;
     * senão, replica a primeira parte de {@see Funcionario::scopeAtivosNoEscopo} (DATA_FIM / DATA_DEMISSAO),
     * sem filtro territorial de lotação. Se nenhuma coluna existir, não restringe (esquema legado).
     *
     * @param  \Illuminate\Database\Eloquent\Builder<\App\Models\Funcionario>|\Illuminate\Database\Query\Builder  $query
     */
    private static function aplicarFiltroServidorAtivoParaMotor($query, string $tablePrefix = ''): void
    {
        if (Schema::hasColumn('FUNCIONARIO', 'FUNCIONARIO_ATIVO')) {
            $query->where($tablePrefix.'FUNCIONARIO_ATIVO', 1);

            return;
        }

        $hoje = now()->toDateString();
        $temFim = Schema::hasColumn('FUNCIONARIO', 'FUNCIONARIO_DATA_FIM');
        $temDemissao = Schema::hasColumn('FUNCIONARIO', 'FUNCIONARIO_DATA_DEMISSAO');
        if (! $temFim && ! $temDemissao) {
            return;
        }

        $colFim = $tablePrefix.'FUNCIONARIO_DATA_FIM';
        $colDem = $tablePrefix.'FUNCIONARIO_DATA_DEMISSAO';

        $query->where(function ($outer) use ($hoje, $temFim, $temDemissao, $colFim, $colDem) {
            if ($temFim) {
                $outer->where(function ($w) use ($hoje, $colFim) {
                    $w->whereNull($colFim)
                        ->orWhere($colFim, '>', $hoje);
                });
            }
            if ($temDemissao) {
                $outer->where(function ($w) use ($hoje, $colDem) {
                    $w->whereNull($colDem)
                        ->orWhere($colDem, '>', $hoje);
                });
            }
        });
    }

    /**
     * Pré-carrega relações por lote (uma query com eager load) para injectar no motor.
     */
    public static function prepararContextoLote(int $folhaId, array $funcionarioIds): MotorFolhaLoteContext
    {
        $folha = DB::table('FOLHA')->where('FOLHA_ID', $folhaId)->first();
        if (! $folha) {
            throw new \InvalidArgumentException("Folha {$folhaId} não encontrada.");
        }
        $competencia = (string) $folha->FOLHA_COMPETENCIA;

        $ids = array_values(array_unique(array_map('intval', $funcionarioIds)));
        if ($ids === []) {
            return new MotorFolhaLoteContext($competencia, [], [], []);
        }

        $eager = [];
        if (Schema::hasTable('AFASTAMENTO')) {
            $eager[] = 'afastamentos';
        }
        if (Schema::hasTable('CARGO')) {
            $eager[] = 'cargo';
        }
        if (Schema::hasTable('AVALIACAO_DESEMPENHO')) {
            $eager[] = 'avaliacoesDesempenho';
        }

        $funcionarios = Funcionario::query()
            ->whereIn('FUNCIONARIO_ID', $ids)
            ->with($eager)
            ->get()
            ->keyBy('FUNCIONARIO_ID');

        $cargoSalario = [];
        $temSalario = Schema::hasColumn('CARGO', 'CARGO_SALARIO');
        $temSalarioBase = Schema::hasColumn('CARGO', 'CARGO_SALARIO_BASE');

        foreach ($ids as $fid) {
            /** @var Funcionario|null $f */
            $f = $funcionarios->get($fid);
            $valor = 0.0;
            if ($f && $f->relationLoaded('cargo')) {
                $c = $f->getRelation('cargo');
                if ($c) {
                    if ($temSalario) {
                        $valor = (float) ($c->CARGO_SALARIO ?? 0);
                    } elseif ($temSalarioBase) {
                        $valor = (float) ($c->CARGO_SALARIO_BASE ?? 0);
                    }
                }
            }
            $cargoSalario[$fid] = $valor;
        }

        $afastMap = [];
        $avalMap = [];
        $datasContratuais = []; // GAP-MF-03: snapshot de DATA_INICIO/FIM para pró-rata
        foreach ($ids as $fid) {
            /** @var Funcionario|null $f */
            $f = $funcionarios->get($fid);
            $afastMap[$fid] = ($f && $f->relationLoaded('afastamentos')) ? $f->afastamentos : collect();
            $avalMap[$fid] = ($f && $f->relationLoaded('avaliacoesDesempenho')) ? $f->avaliacoesDesempenho : collect();
            $datasContratuais[$fid] = [
                'inicio' => $f?->FUNCIONARIO_DATA_INICIO,
                'fim' => $f?->FUNCIONARIO_DATA_FIM,
            ];
        }

        return new MotorFolhaLoteContext($competencia, $cargoSalario, $afastMap, $avalMap, $datasContratuais);
    }

    /**
     * Despacha um batch de jobs (500 servidores por job). Requer driver de fila não-sync para 202 real.
     */
    public function despacharProcessamentoAssincrono(int $folhaId, ?int $createdByUsuarioId = null): Batch
    {
        $folha = DB::table('FOLHA')->where('FOLHA_ID', $folhaId)->first();
        if (! $folha) {
            throw new \InvalidArgumentException("Folha {$folhaId} não encontrada.");
        }
        $competencia = (string) $folha->FOLHA_COMPETENCIA;

        $jobs = [];
        $q = Funcionario::query();
        self::aplicarFiltroServidorAtivoParaMotor($q);
        $q->orderBy('FUNCIONARIO_ID')
            ->chunkById(self::CHUNK_SIZE, function (Collection $chunk) use ($folhaId, &$jobs) {
                $ids = $chunk->pluck('FUNCIONARIO_ID')->map(fn ($v) => (int) $v)->values()->all();
                if ($ids !== []) {
                    $jobs[] = new ProcessarLoteFolhaJob($folhaId, $ids);
                }
            });

        if ($jobs === []) {
            throw new \RuntimeException('Nenhum servidor ativo encontrado para processar a folha.');
        }

        $pending = Bus::batch($jobs)
            ->name('Fecho de Folha - ' . $competencia);

        if ($createdByUsuarioId !== null) {
            $pending->withOption('created_by', $createdByUsuarioId);
        }

        return $pending->allowFailures(false)->dispatch();
    }

    /**
     * Caminho síncrono legado: processa por chunk em memória + mesmo {@see prepararContextoLote} que o Job.
     */
    public function calcularFolha(int $folhaId): array
    {
        $folha = DB::table('FOLHA')->where('FOLHA_ID', $folhaId)->first();
        if (! $folha) {
            return ['ok' => false, 'erro' => "Folha {$folhaId} não encontrada."];
        }
        $competencia = $folha->FOLHA_COMPETENCIA;

        $totalServidores = 0;
        $sumProv = 0.0;
        $sumDesc = 0.0;
        $sumLiq = 0.0;
        $sumSm = 0.0;
        $algum = false;

        $q = Funcionario::query();
        self::aplicarFiltroServidorAtivoParaMotor($q);
        $q->orderBy('FUNCIONARIO_ID')
            ->chunkById(self::CHUNK_SIZE, function (Collection $chunk) use (
                $folhaId,
                &$totalServidores,
                &$sumProv,
                &$sumDesc,
                &$sumLiq,
                &$sumSm,
                &$algum
            ) {
                $ids = $chunk->pluck('FUNCIONARIO_ID')->map(fn ($v) => (int) $v)->values()->all();
                if ($ids === []) {
                    return;
                }
                $ctx = self::prepararContextoLote($folhaId, $ids);
                $out = $this->calcularLoteParaFuncionarios($folhaId, $ids, $ctx);
                if (! ($out['ok'] ?? false)) {
                    throw new \RuntimeException($out['erro'] ?? 'Erro ao calcular lote da folha.');
                }
                $algum = true;
                $totalServidores += (int) ($out['servidores'] ?? 0);
                $sumProv += (float) ($out['total_proventos'] ?? 0);
                $sumDesc += (float) ($out['total_descontos'] ?? 0);
                $sumLiq += (float) ($out['total_liquido'] ?? 0);
                $sumSm += (float) ($out['total_comp_sm'] ?? 0);
            });

        if (! $algum) {
            return ['ok' => false, 'erro' => 'Nenhum servidor ativo encontrado.'];
        }

        return [
            'ok' => true,
            'folha_id' => $folhaId,
            'competencia' => $competencia,
            'servidores' => $totalServidores,
            'total_proventos' => round($sumProv, 2),
            'total_descontos' => round($sumDesc, 2),
            'total_liquido' => round($sumLiq, 2),
            'total_comp_sm' => round($sumSm, 2),
        ];
    }

    /**
     * Calcula e persiste um lote; leitura de AFASTAMENTO / avaliação / cargo apenas via $contexto.
     *
     * @return array{ok: bool, erro?: string, servidores?: int, total_proventos?: float, total_descontos?: float, total_liquido?: float, total_comp_sm?: float}
     */
    public function calcularLoteParaFuncionarios(int $folhaId, array $funcionarioIds, MotorFolhaLoteContext $contexto): array
    {
        $folha = DB::table('FOLHA')->where('FOLHA_ID', $folhaId)->first();
        if (! $folha) {
            return ['ok' => false, 'erro' => "Folha {$folhaId} não encontrada."];
        }
        $competencia = (string) $folha->FOLHA_COMPETENCIA;

        $ids = array_values(array_unique(array_map('intval', $funcionarioIds)));
        if ($ids === []) {
            return ['ok' => true, 'servidores' => 0, 'total_proventos' => 0.0, 'total_descontos' => 0.0, 'total_liquido' => 0.0, 'total_comp_sm' => 0.0];
        }

        // GAP-MF-04: incluir HE/Plantão aprovados como LANCAMENTO_FOLHA antes de ler.
        // Idempotente: re-execução não duplica (verifica STATUS).
        try {
            app(\App\Services\Folha\InclusaoHorasExtrasService::class)
                ->incluirParaFolha($folhaId, $ids, (string) $competencia);
        } catch (\Throwable $e) {
            \Illuminate\Support\Facades\Log::error('[MotorFolha] falha ao incluir HE/Plantão', [
                'folha_id' => $folhaId,
                'erro' => $e->getMessage(),
            ]);
            // Não fail-fast: prosseguir o cálculo da folha mesmo se a inclusão de HE falhar.
            // O auditor (Claude) vai detectar pelo log e abrir bug separado.
        }

        $servidoresQuery = DB::table('FUNCIONARIO as f')
            ->join('PESSOA as p', 'p.PESSOA_ID', '=', 'f.PESSOA_ID')
            ->leftJoin('VINCULO as v', 'v.VINCULO_ID', '=', 'f.VINCULO_ID')
            ->leftJoin('TABELA_SALARIAL as ts', function ($j) {
                $j->on('ts.CARREIRA_ID', '=', 'f.CARREIRA_ID')
                    ->on('ts.TABELA_CLASSE', '=', 'f.FUNCIONARIO_CLASSE')
                    ->on('ts.TABELA_REFERENCIA', '=', 'f.FUNCIONARIO_REFERENCIA');
            })
            ->leftJoin('PROGRESSAO_CONFIG as pc', 'pc.CARREIRA_ID', '=', 'f.CARREIRA_ID');
        self::aplicarFiltroServidorAtivoParaMotor($servidoresQuery, 'f.');
        $servidores = $servidoresQuery
            ->whereIn('f.FUNCIONARIO_ID', $ids)
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
            return ['ok' => true, 'servidores' => 0, 'total_proventos' => 0.0, 'total_descontos' => 0.0, 'total_liquido' => 0.0, 'total_comp_sm' => 0.0];
        }

        $funcIds = $servidores->keys()->map(fn ($k) => (int) $k)->all();

        $adicionais = DB::table('ADICIONAL_SERVIDOR as ads')
            ->whereIn('ads.FUNCIONARIO_ID', $funcIds)
            ->where(function ($q) {
                $q->whereNull('ads.ADICIONAL_VIGENCIA_FIM')
                    ->orWhere('ads.ADICIONAL_VIGENCIA_FIM', '>=', now()->toDateString());
            })
            ->select('ads.*')
            ->get()
            ->groupBy('FUNCIONARIO_ID');

        $lancamentos = DB::table('LANCAMENTO_FOLHA')
            ->where('FOLHA_ID', $folhaId)
            ->whereIn('FUNCIONARIO_ID', $funcIds)
            ->get()
            ->groupBy('FUNCIONARIO_ID');

        $compFormatada = substr($competencia, 0, 7);
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

        $aliqRPPS = $this->resolverAliquotaRpps();
        $salarioMin = self::SALARIO_MIN_2025;

        $resultados = [];

        foreach ($servidores as $funcId => $s) {
            $funcId = (int) $funcId;
            $vinculoTipo = $s->VINCULO_TIPO ?? 'efetivo';
            $vencBaseIntegral = (float) ($s->TABELA_VENCIMENTO_BASE ?? 0);
            $cargoSal = $contexto->getCargoSalario($funcId);
            if ($vencBaseIntegral <= 0 && $cargoSal > 0) {
                $vencBaseIntegral = $cargoSal;
            }

            // GAP-MF-01/03: aplicar pró-rata por dias contratuais no mês (admissão/exoneração).
            // GAP-MF-06 (parcial): denominador = dias reais do mês de competência (28/29/30/31).
            $razao = $contexto->razaoProporcionalVencimento($funcId);
            $vencBase = $vencBaseIntegral * $razao;

            // GAP-MF-02: dias abonados (LM/LMA/etc.) — informativo, já contabilizados em dias trabalhados.
            $diasAbonados = $contexto->diasAbonadosNoMes($funcId);

            // Dados injectados (sem query): competência × afastamento / desempenho
            $contexto->possuiAfastamentoSobrepostoNaCompetencia($funcId);
            $contexto->melhorNotaFinal($funcId);
            $fatorDesempenho = $contexto->fatorProgressaoPorDesempenho($funcId);

            switch ($vinculoTipo) {
                case 'comissao_puro':
                    $provC1 = $vencBase;
                    $anuenioVal = 0.0;
                    break;

                case 'efetivo_cc_m1':
                    $provC1 = $vencBase;
                    $anuenioVal = 0.0;
                    break;

                case 'efetivo_cc_m2':
                    $aliqAnuenio = (float) ($s->VINCULO_ANUENIO_PCT ?? $s->CONFIG_ANUENIO_PCT ?? 1.00) / 100;
                    $anoServ = $s->FUNCIONARIO_DATA_INICIO
                        ? now()->diffInYears(\Carbon\Carbon::parse($s->FUNCIONARIO_DATA_INICIO))
                        : 0;
                    $anuenioVal = $vencBase * $aliqAnuenio * $anoServ * $fatorDesempenho;
                    $provC1 = $vencBase + $anuenioVal;
                    break;

                case 'funcao_confianca':
                    $aliqAnuenio = (float) ($s->VINCULO_ANUENIO_PCT ?? $s->CONFIG_ANUENIO_PCT ?? 1.00) / 100;
                    $anoServ = $s->FUNCIONARIO_DATA_INICIO
                        ? now()->diffInYears(\Carbon\Carbon::parse($s->FUNCIONARIO_DATA_INICIO))
                        : 0;
                    $anuenioVal = $vencBase * $aliqAnuenio * $anoServ * $fatorDesempenho;
                    $provC1 = $vencBase + $anuenioVal;
                    break;

                default:
                    $aliqAnuenio = (float) ($s->VINCULO_ANUENIO_PCT ?? $s->CONFIG_ANUENIO_PCT ?? 1.00) / 100;
                    $anoServ = $s->FUNCIONARIO_DATA_INICIO
                        ? now()->diffInYears(\Carbon\Carbon::parse($s->FUNCIONARIO_DATA_INICIO))
                        : 0;
                    $anuenioVal = $vencBase * $aliqAnuenio * $anoServ * $fatorDesempenho;
                    $provC1 = $vencBase + $anuenioVal;
                    break;
            }

            $provC2 = 0.0;
            $basePrev = $provC1;
            foreach (($adicionais[$funcId] ?? collect()) as $ad) {
                $val = match ($ad->ADICIONAL_TIPO) {
                    'fixo' => (float) $ad->ADICIONAL_VALOR,
                    'percentual' => $vencBase * ((float) $ad->ADICIONAL_VALOR / 100),
                    'percentual_sm' => $salarioMin * ((float) $ad->ADICIONAL_VALOR / 100),
                    default => 0.0,
                };
                $provC2 += $val;

                if ($ad->ADICIONAL_INCIDE_PREV) {
                    $basePrev += $val;
                }
            }

            $provC3 = 0.0;
            $descC3 = 0.0;
            foreach (($lancamentos[$funcId] ?? collect()) as $lanc) {
                $total = (float) $lanc->LANCAMENTO_VALOR_TOTAL;
                if ($lanc->LANCAMENTO_TIPO === 'P') {
                    $provC3 += $total;
                    if ($lanc->LANCAMENTO_INCIDE_PREV) {
                        $basePrev += $total;
                    }
                } else {
                    $descC3 += $total;
                }
            }

            $bruto = $provC1 + $provC2 + $provC3;

            $complementoSM = 0.0;
            if (in_array($vinculoTipo, self::VINCULOS_PISO) && $bruto < $salarioMin) {
                $complementoSM = round($salarioMin - $bruto, 2);
                $bruto = $salarioMin;
            }

            $descPrev = 0.0;
            $incideInss = $s->VINCULO_INSS ?? true;
            if ($incideInss) {
                $regime = $s->VINCULO_REGIME ?? $s->FUNCIONARIO_REGIME_PREV ?? 'RPPS';
                $descPrev = ($regime === 'RPPS')
                    ? $basePrev * $aliqRPPS
                    : $this->calcularInssRgps($basePrev);
            }

            $dep = (int) ($s->PESSOA_DEPENDENTES_IRRF ?? 0);
            $baseIrrf = $bruto - $descPrev - ($dep * 226.86);
            $descIRRF = ($s->VINCULO_IRRF ?? true) ? $this->calcularIrrf($baseIrrf) : 0.0;

            $descConsig = (float) ($consignacoes[$funcId]->total_consig ?? 0);

            $descOutros = $descC3 + $descConsig;
            $liquido = $bruto - $descPrev - $descIRRF - $descOutros;

            \Illuminate\Support\Facades\Log::info('[MotorFolha] cálculo lote', [
                'folha_id' => $folhaId,
                'funcionario_id' => $funcId,
                'razao_proporcional' => round($razao, 4),
                'dias_abonados' => $diasAbonados,
                'venc_base_integral' => round($vencBaseIntegral, 2),
                'venc_base_proporcional' => round($vencBase, 2),
                'bruto' => round($bruto, 2),
                'liquido' => round($liquido, 2),
            ]);

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

        $this->persistirDetalhesLoteEmTransacao($resultados);

        $col = collect($resultados);

        return [
            'ok' => true,
            'servidores' => count($resultados),
            'total_proventos' => round($col->sum('DETALHE_FOLHA_PROVENTOS'), 2),
            'total_descontos' => round($col->sum('DETALHE_FOLHA_DESCONTOS'), 2),
            'total_liquido' => round($col->sum('DETALHE_FOLHA_LIQUIDO'), 2),
            'total_comp_sm' => round($col->sum('DETALHE_COMPLEMENTO_SM'), 2),
        ];
    }

    /**
     * @param  array<int, array<string, mixed>>  $resultados
     */
    private function persistirDetalhesLoteEmTransacao(array $resultados): void
    {
        if ($resultados === []) {
            return;
        }

        $chunks = array_chunk($resultados, self::CHUNK_SIZE);

        DB::transaction(function () use ($chunks) {
            foreach ($chunks as $chunk) {
                $first = reset($chunk);
                if ($first === false) {
                    continue;
                }
                $update = array_values(array_diff(array_keys($first), ['FUNCIONARIO_ID', 'FOLHA_ID']));
                DB::table('DETALHE_FOLHA')->upsert(
                    $chunk,
                    ['FUNCIONARIO_ID', 'FOLHA_ID'],
                    $update
                );
            }
        });
    }

    private function resolverAliquotaRpps(): float
    {
        try {
            $aliqRPPS = DB::table('RPPS_CONFIG')
                ->orderByDesc('VIGENCIA_INICIO')
                ->value('ALIQUOTA_SERVIDOR') ?? 14;
        } catch (\Throwable $e) {
            $aliqRPPS = 14;
        }

        return $aliqRPPS / 100;
    }

    private function calcularInssRgps(float $base): float
    {
        $faixas = [
            [1518.00, 0.075],
            [2666.68, 0.09],
            [4000.03, 0.12],
            [7786.02, 0.14],
        ];
        $desconto = 0.0;
        $anterior = 0.0;
        foreach ($faixas as [$teto, $aliq]) {
            if ($base <= $anterior) {
                break;
            }
            $faixa = min($base, $teto) - $anterior;
            $desconto += $faixa * $aliq;
            $anterior = $teto;
            if ($base <= $teto) {
                break;
            }
        }

        return round(min($desconto, $base * 0.14), 2);
    }

    private function calcularIrrf(float $base): float
    {
        if ($base <= 0) {
            return 0.0;
        }
        if ($base <= 2824.00) {
            return 0.0;
        }
        if ($base <= 3751.05) {
            return round($base * 0.075 - 211.80, 2);
        }
        if ($base <= 4664.68) {
            return round($base * 0.15 - 493.05, 2);
        }
        if ($base <= 7083.49) {
            return round($base * 0.225 - 843.16, 2);
        }

        return round($base * 0.275 - 1197.58, 2);
    }
}
