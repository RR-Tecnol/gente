<?php

namespace App\Support;

use Illuminate\Support\Collection;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

final class SubstituicaoFrequenciaService
{
    /**
     * Gancho de integração com Frequência/Ponto.
     *
     * Busca substituições confirmadas em datas anteriores a hoje
     * e devolve a lista de candidatas para marcação futura de
     * "falta_substituicao" quando a integração de presença estiver ativa.
     *
     * @return Collection<int, object>
     */
    public static function processarFaltaSubstituicao(): Collection
    {
        if (!Schema::hasTable('SUBSTITUICAO_ESCALA')) {
            return collect();
        }

        $cols = Schema::getColumnListing('SUBSTITUICAO_ESCALA');
        $statusCol = in_array('SUBSTITUICAO_ESCALA_STATUS', $cols, true)
            ? 'SUBSTITUICAO_ESCALA_STATUS'
            : (in_array('STATUS', $cols, true) ? 'STATUS' : null);
        if (!$statusCol || !in_array('SUBSTITUICAO_ESCALA_DATA', $cols, true)) {
            return collect();
        }

        return DB::table('SUBSTITUICAO_ESCALA')
            ->whereDate('SUBSTITUICAO_ESCALA_DATA', '<', now()->toDateString())
            ->where(function ($q) use ($statusCol) {
                $q->whereRaw("LOWER(CAST({$statusCol} as varchar(50))) = ?", ['confirmada'])
                    ->orWhereRaw("LOWER(CAST({$statusCol} as varchar(50))) = ?", ['aprovada']);
            })
            ->orderByDesc('SUBSTITUICAO_ESCALA_DATA')
            ->get();
    }
}

