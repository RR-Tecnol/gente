<?php

namespace App\Services\Pccv;

use Carbon\Carbon;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

/**
 * Compara carga horária contratual com a soma de TURNO_CARGA_HORARIA na semana (mesma competência e escala).
 */
final class PccvValidatorService
{
    /** @var array<int, float> */
    private $turnoHorasCache = [];

    public function isEnabled()
    {
        return (bool) config('gente.pccv.enabled', true);
    }

    /**
     * @return PccvJornadaViolation|null
     */
    public function validarCelulaEscala(
        $funcionarioId,
        $escalaId,
        $competenciaYmd,
        $dataCelulaYmd,
        $turnoIdNovo
    ) {
        if (! $this->isEnabled() || ! Schema::hasTable('TURNO')) {
            return null;
        }
        $carga = $this->resolverCargaSemanalContrato((int) $funcionarioId);
        if ($carga === null || $carga <= 0) {
            return null;
        }
        $data = Carbon::parse($dataCelulaYmd);
        if ((bool) config('gente.pccv.semana_iso', true)) {
            $inicio = $data->copy()->startOfWeek(Carbon::MONDAY)->toDateString();
            $fim = $data->copy()->endOfWeek(Carbon::SUNDAY)->toDateString();
        } else {
            $inicio = $data->copy()->startOfWeek(Carbon::SUNDAY)->toDateString();
            $fim = $data->copy()->endOfWeek(Carbon::SATURDAY)->toDateString();
        }
        $horas = $this->somaHorasSemanaAposAlteracao(
            (int) $escalaId,
            (int) $funcionarioId,
            (string) $competenciaYmd,
            $inicio,
            $fim,
            (string) $dataCelulaYmd,
            $turnoIdNovo
        );
        $tol = (float) config('gente.pccv.tolerancia_horas', 0.25);
        if ($horas <= (float) $carga + $tol) {
            return null;
        }

        return new PccvJornadaViolation(
            $carga,
            $horas,
            $inicio,
            $fim,
            ''
        );
    }

    /**
     * @return int|null
     */
    private function resolverCargaSemanalContrato($funcionarioId)
    {
        if (Schema::hasColumn('FUNCIONARIO', 'FUNCIONARIO_CARGA_HORARIA')) {
            $c = DB::table('FUNCIONARIO')->where('FUNCIONARIO_ID', $funcionarioId)->value('FUNCIONARIO_CARGA_HORARIA');
            if ($c !== null && (int) $c > 0) {
                return $this->normalizarCargaSemanal((int) $c);
            }
        }
        if (Schema::hasTable('CARGO')
            && Schema::hasColumn('FUNCIONARIO', 'CARGO_ID')
            && Schema::hasColumn('CARGO', 'CARGO_CARGA_HORARIA')) {
            $c = DB::table('FUNCIONARIO as f')
                ->leftJoin('CARGO as c', 'c.CARGO_ID', '=', 'f.CARGO_ID')
                ->where('f.FUNCIONARIO_ID', $funcionarioId)
                ->value('c.CARGO_CARGA_HORARIA');
            if ($c !== null && (int) $c > 0) {
                return $this->normalizarCargaSemanal((int) $c);
            }
        }

        return null;
    }

    /**
     * Valores > 60 tratam-se como carga mensal (ex. 220h) e convertem-se para teto semanal.
     */
    private function normalizarCargaSemanal($c)
    {
        $c = (int) $c;
        if ($c <= 60) {
            return $c;
        }
        $refMes = (int) config('gente.pccv.carga_mensal_referencia', 220);
        $semana40 = (int) config('gente.pccv.carga_semanal_ref_mes_220', 44);
        if ($refMes < 1) {
            $refMes = 220;
        }
        if ($c >= 80 && $c <= 300) {
            return (int) max(1, (int) round($c * $semana40 / $refMes));
        }

        return (int) max(1, min(60, $c));
    }

    private function somaHorasSemanaAposAlteracao(
        $escalaId,
        $funcionarioId,
        $competenciaYmd,
        $weekStart,
        $weekEnd,
        $dataCelulaYmd,
        $turnoIdNovo
    ) {
        if (! Schema::hasTable('DETALHE_ESCALA_ITEM')) {
            return 0.0;
        }
        $rows = DB::table('DETALHE_ESCALA_ITEM as i')
            ->join('DETALHE_ESCALA as de', 'de.DETALHE_ESCALA_ID', '=', 'i.DETALHE_ESCALA_ID')
            ->join('ESCALA as e', 'e.ESCALA_ID', '=', 'de.ESCALA_ID')
            ->where('e.ESCALA_ID', $escalaId)
            ->where('de.FUNCIONARIO_ID', $funcionarioId)
            ->where('e.ESCALA_COMPETENCIA', $competenciaYmd)
            ->whereBetween('i.DETALHE_ESCALA_ITEM_DATA', [$weekStart, $weekEnd])
            ->get(['i.DETALHE_ESCALA_ITEM_DATA as d', 'i.TURNO_ID as tid']);

        $byDate = [];
        foreach ($rows as $r) {
            $d = (string) $r->d;
            $byDate[$d] = $r->tid !== null ? (int) $r->tid : null;
        }
        if ($turnoIdNovo === null) {
            unset($byDate[$dataCelulaYmd]);
        } else {
            $byDate[$dataCelulaYmd] = (int) $turnoIdNovo;
        }

        $soma = 0.0;
        foreach ($byDate as $tid) {
            if ($tid !== null && (int) $tid > 0) {
                $soma += $this->horasDoTurnoId((int) $tid);
            }
        }

        return $soma;
    }

    private function horasDoTurnoId($turnoId)
    {
        $turnoId = (int) $turnoId;
        if (isset($this->turnoHorasCache[$turnoId])) {
            return $this->turnoHorasCache[$turnoId];
        }
        $h = 0.0;
        if (Schema::hasColumn('TURNO', 'TURNO_CARGA_HORARIA')) {
            $v = DB::table('TURNO')->where('TURNO_ID', $turnoId)->value('TURNO_CARGA_HORARIA');
            if ($v !== null && (float) $v > 0) {
                $h = (float) $v;
            }
        }
        $this->turnoHorasCache[$turnoId] = $h;

        return $h;
    }
}
