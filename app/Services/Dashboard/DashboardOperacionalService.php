<?php

namespace App\Services\Dashboard;

use Carbon\Carbon;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

/**
 * KPIs agregados para GET /api/v3/dashboard/operacional (Fase 9A).
 * Critério de “furo” alinhado a routes/escala_saude.php (ausência com LM/afastamento sem substituto).
 */
final class DashboardOperacionalService
{
    /**
     * @return array<string, mixed>
     */
    public function build(string $dataYmd, ?string $regiao): array
    {
        $dataYmd = $this->normalizarData($dataYmd);
        $regiao = $regiao !== null && trim($regiao) !== '' ? trim($regiao) : null;

        $vmde = $this->vmdePayload();

        return [
            'refreshed_at' => now()->toIso8601String(),
            'data_referencia' => $dataYmd,
            'regiao' => $regiao,
            'total_servidores_ativos' => $this->totalServidoresAtivos(),
            'taxa_furo_escala' => $this->taxaFuroEscala($dataYmd, $regiao),
            'indice_mde_elegivel' => $this->indiceMdeElegivel(),
            'vmde' => $vmde,
        ];
    }

    private function normalizarData(string $dataYmd): string
    {
        try {
            return Carbon::parse($dataYmd)->toDateString();
        } catch (\Throwable) {
            return now()->toDateString();
        }
    }

    private function totalServidoresAtivos(): int
    {
        if (! Schema::hasTable('FUNCIONARIO')) {
            return 0;
        }
        $q = DB::table('FUNCIONARIO');
        if (Schema::hasColumn('FUNCIONARIO', 'FUNCIONARIO_DATA_FIM')) {
            $hoje = now()->toDateString();
            $q->where(function ($w) use ($hoje) {
                $w->whereNull('FUNCIONARIO_DATA_FIM')->orWhere('FUNCIONARIO_DATA_FIM', '>', $hoje);
            });
        }
        if (Schema::hasColumn('FUNCIONARIO', 'FUNCIONARIO_ATIVO')) {
            $q->where('FUNCIONARIO_ATIVO', 1);
        }

        return (int) $q->count();
    }

    /**
     * @return array{taxa: float|null, total_furos: int, total_slots_escala: int, competencia: string|null, mock: bool}
     */
    private function taxaFuroEscala(string $dataYmd, ?string $regiao): array
    {
        if ((bool) config('gente_executive_dashboard.mock_taxa_furo', false)) {
            $v = (float) config('gente_executive_dashboard.mock_taxa_furo_valor', 0.0);

            return [
                'taxa' => max(0.0, min(1.0, $v)),
                'total_furos' => 0,
                'total_slots_escala' => 0,
                'competencia' => null,
                'mock' => true,
            ];
        }

        if (! Schema::hasTable('ESCALA') || ! Schema::hasTable('DETALHE_ESCALA') || ! Schema::hasTable('DETALHE_ESCALA_ITEM')) {
            return [
                'taxa' => null,
                'total_furos' => 0,
                'total_slots_escala' => 0,
                'competencia' => null,
                'mock' => false,
            ];
        }

        $competencia = date('m/Y', strtotime($dataYmd));

        $escalasQuery = DB::table('ESCALA as e')
            ->join('DETALHE_ESCALA as de', 'de.ESCALA_ID', '=', 'e.ESCALA_ID')
            ->join('DETALHE_ESCALA_ITEM as dei', 'dei.DETALHE_ESCALA_ID', '=', 'de.DETALHE_ESCALA_ID')
            ->join('FUNCIONARIO as f', 'f.FUNCIONARIO_ID', '=', 'de.FUNCIONARIO_ID')
            ->join('PESSOA as p', 'p.PESSOA_ID', '=', 'f.PESSOA_ID')
            ->leftJoin('LOTACAO as l', function ($j) {
                $j->on('l.FUNCIONARIO_ID', '=', 'f.FUNCIONARIO_ID');
                if (Schema::hasColumn('LOTACAO', 'LOTACAO_DATA_FIM')) {
                    $j->whereNull('l.LOTACAO_DATA_FIM');
                }
            })
            ->leftJoin('SETOR as s', 's.SETOR_ID', '=', 'l.SETOR_ID')
            ->where('e.ESCALA_COMPETENCIA', $competencia)
            ->where('dei.DETALHE_ESCALA_ITEM_DATA', $dataYmd);

        if ($regiao !== null && Schema::hasColumn('SETOR', 'SETOR_REGIAO')) {
            $escalasQuery->where('s.SETOR_REGIAO', $regiao);
        }

        $itensEscala = $escalasQuery->select(
            'dei.DETALHE_ESCALA_ITEM_ID as item_id',
            'dei.DETALHE_ESCALA_ITEM_DATA as data',
            'de.FUNCIONARIO_ID as funcionario_id',
        )->get();

        $totalSlots = $itensEscala->count();
        if ($totalSlots === 0) {
            return [
                'taxa' => null,
                'total_furos' => 0,
                'total_slots_escala' => 0,
                'competencia' => $competencia,
                'mock' => false,
            ];
        }

        $funcionarioIds = $itensEscala->pluck('funcionario_id')->unique()->values();
        $periodoInicio = $dataYmd;
        $periodoFim = $dataYmd;

        $atestados = $this->buscarAtestadosPeriodo($funcionarioIds, $periodoInicio, $periodoFim);
        $afastamentos = $this->buscarAfastamentosPeriodo($funcionarioIds, $periodoInicio, $periodoFim);
        $substituicoes = $this->buscarSubstituicoesItemIds($competencia);

        $furos = 0;
        foreach ($itensEscala as $item) {
            if (isset($substituicoes[$item->item_id])) {
                continue;
            }
            $dataItem = $item->data;
            $funcId = (int) $item->funcionario_id;
            $ausente = $this->funcionarioAusenteNoDia($funcId, (string) $dataItem, $atestados, $afastamentos);
            if ($ausente) {
                $furos++;
            }
        }

        $taxa = $totalSlots > 0 ? round($furos / $totalSlots, 6) : null;

        return [
            'taxa' => $taxa,
            'total_furos' => $furos,
            'total_slots_escala' => $totalSlots,
            'competencia' => $competencia,
            'mock' => false,
        ];
    }

    private function buscarAtestadosPeriodo(Collection $funcionarioIds, string $periodoInicio, string $periodoFim): Collection
    {
        if (! Schema::hasTable('ATESTADO_MEDICO') || $funcionarioIds->isEmpty()) {
            return collect();
        }
        try {
            // R57: removido `date(col, '+' || col || ' days')` SQLite-only.
            // Filtramos atestados que começam até o fim do período (limite máximo razoável: 365d antes).
            // O filtro fino "atestado cobre o dia" é feito em funcionarioAusenteNoDia() no PHP.
            $limiteInicioBusca = Carbon::parse($periodoInicio)->subDays(365)->toDateString();

            return DB::table('ATESTADO_MEDICO')
                ->whereIn('FUNCIONARIO_ID', $funcionarioIds->all())
                ->where('STATUS', 'VALIDADO')
                ->where('ATESTADO_DATA', '>=', $limiteInicioBusca)
                ->where('ATESTADO_DATA', '<=', $periodoFim)
                ->select('FUNCIONARIO_ID', 'ATESTADO_DATA', 'ATESTADO_DIAS')
                ->get();
        } catch (\Throwable) {
            return collect();
        }
    }

    private function buscarAfastamentosPeriodo(Collection $funcionarioIds, string $periodoInicio, string $periodoFim): Collection
    {
        if (! Schema::hasTable('AFASTAMENTO') || $funcionarioIds->isEmpty()) {
            return collect();
        }
        try {
            return DB::table('AFASTAMENTO')
                ->whereIn('FUNCIONARIO_ID', $funcionarioIds->all())
                ->whereIn('AFASTAMENTO_STATUS', ['APROVADO', 'VALIDADO', 'aprovado'])
                ->where('AFASTAMENTO_DATA_INICIO', '<=', $periodoFim)
                ->where('AFASTAMENTO_DATA_FIM', '>=', $periodoInicio)
                ->select('FUNCIONARIO_ID', 'AFASTAMENTO_DATA_INICIO', 'AFASTAMENTO_DATA_FIM')
                ->get();
        } catch (\Throwable) {
            return collect();
        }
    }

    /**
     * @return array<int, true>
     */
    private function buscarSubstituicoesItemIds(string $competencia): array
    {
        if (! Schema::hasTable('SUBSTITUICAO_ESCALA')) {
            return [];
        }
        try {
            return DB::table('SUBSTITUICAO_ESCALA')
                ->where('ESCALA_COMPETENCIA', $competencia)
                ->whereNotNull('SUBSTITUTO_ID')
                ->pluck('DETALHE_ESCALA_ITEM_ID')
                ->flip()
                ->all();
        } catch (\Throwable) {
            return [];
        }
    }

    private function funcionarioAusenteNoDia(int $funcId, string $dataItem, Collection $atestados, Collection $afastamentos): bool
    {
        foreach ($atestados->where('FUNCIONARIO_ID', $funcId) as $at) {
            $fim = date('Y-m-d', strtotime($at->ATESTADO_DATA.' +'.($at->ATESTADO_DIAS - 1).' days'));
            if ($dataItem >= $at->ATESTADO_DATA && $dataItem <= $fim) {
                return true;
            }
        }
        foreach ($afastamentos->where('FUNCIONARIO_ID', $funcId) as $af) {
            if ($dataItem >= $af->AFASTAMENTO_DATA_INICIO && $dataItem <= $af->AFASTAMENTO_DATA_FIM) {
                return true;
            }
        }

        return false;
    }

    private function indiceMdeElegivel(): int
    {
        $siglas = (array) config('gente_executive_dashboard.mde_unidade_siglas', ['SEMED']);
        $siglas = array_values(array_filter(array_map('strval', $siglas)));
        if ($siglas === [] || ! Schema::hasTable('UNIDADE') || ! Schema::hasTable('LOTACAO') || ! Schema::hasTable('SETOR') || ! Schema::hasTable('FUNCIONARIO')) {
            return 0;
        }

        $sigCol = Schema::hasColumn('UNIDADE', 'UNIDADE_SIGLA') ? 'UNIDADE_SIGLA' : null;
        if (! $sigCol) {
            return 0;
        }

        $unidadeIds = DB::table('UNIDADE')->whereIn($sigCol, $siglas)->pluck('UNIDADE_ID')->map(fn ($id) => (int) $id)->filter(fn ($id) => $id > 0)->values();
        if ($unidadeIds->isEmpty()) {
            return 0;
        }

        $q = DB::table('FUNCIONARIO as f')
            ->join('LOTACAO as l', 'l.FUNCIONARIO_ID', '=', 'f.FUNCIONARIO_ID')
            ->join('SETOR as s', 's.SETOR_ID', '=', 'l.SETOR_ID')
            ->whereIn('s.UNIDADE_ID', $unidadeIds->all());

        if (Schema::hasColumn('LOTACAO', 'LOTACAO_DATA_FIM')) {
            $hoje = now()->toDateString();
            $q->where(function ($w) use ($hoje) {
                $w->whereNull('l.LOTACAO_DATA_FIM')->orWhere('l.LOTACAO_DATA_FIM', '>', $hoje);
            });
        }

        if (Schema::hasColumn('FUNCIONARIO', 'FUNCIONARIO_DATA_FIM')) {
            $hoje = now()->toDateString();
            $q->where(function ($w) use ($hoje) {
                $w->whereNull('f.FUNCIONARIO_DATA_FIM')->orWhere('f.FUNCIONARIO_DATA_FIM', '>', $hoje);
            });
        }
        if (Schema::hasColumn('FUNCIONARIO', 'FUNCIONARIO_ATIVO')) {
            $q->where('f.FUNCIONARIO_ATIVO', 1);
        }

        return (int) $q->selectRaw('COUNT(DISTINCT f.FUNCIONARIO_ID) as aggregate')->value('aggregate');
    }

    /**
     * @return array{t_municipais: ?float, t_transferidos: ?float, vmde: ?float, formula: string, nota_fonte: string}
     */
    private function vmdePayload(): array
    {
        $formula = 'V_MDE = 0,25 × (T_municipais + T_transferidos) — Art. 139 / segregação de despesas de educação.';

        return [
            't_municipais' => null,
            't_transferidos' => null,
            'vmde' => null,
            'formula' => $formula,
            'nota_fonte' => 'Valores contábeis T_municipais / T_transferidos não integrados neste endpoint; KPI executivo foca em contagens operacionais (lotação SEMED). Ver docs/davi/BUSINESS_RULES.md.',
        ];
    }
}
