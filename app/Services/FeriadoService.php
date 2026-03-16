<?php

namespace App\Services;

use App\Models\Feriado;
use Carbon\Carbon;
use Carbon\CarbonPeriod;

class FeriadoService
{
    public function getFeriadosAno($ano)
    {
        return Feriado::buscarPorAno($ano);
    }

    public function getEntreDatas($dataInicial, $dataFinal)
    {
        return Feriado::buscarEntreDatas($dataInicial, $dataFinal);
    }

    public function getProximoFeriado($data)
    {
        return Feriado::buscarProximoFeriado($data);
    }

    public function getFeriadoAnterior($data)
    {
        return Feriado::buscarFeriadoAnterior($data);
    }

    public function getFeriadoMesAno($mesAno)
    {
        return Feriado::buscarFeriadoMesAno($mesAno);
    }

    public function getCalendario($mesAno): array
    {
        $dataInicial = Carbon::parse($mesAno)->startOfMonth();
        $dataFinal = Carbon::parse($mesAno)->endOfMonth();
        $periodos = CarbonPeriod::create($dataInicial, $dataFinal);
        $datas = [];
        foreach ($periodos as $data) {
            $dataFeriado = Feriado::buscarDataFeriado($data->format('Y-m-d'));
            $isHoliday = $dataFeriado != null;
            $datas[] = [
                "date" => $data->format('Y-m-d'),
                "dayName" => $data->format('l'),
                "isWeekend" => $data->isWeekend(),
                "isHoliday" => $isHoliday,
                "holiday" => $isHoliday ? $dataFeriado->toArray() : null,
            ];
        }
        return $datas;
    }

    public function getFeriado($data): array
    {
        $dataParsed = Carbon::parse($data);
        $dataFeriado = Feriado::buscarDataFeriado($dataParsed->format('Y-m-d'));
        if ($dataFeriado != null) {
            return [
                "date" => $dataParsed->format('Y-m-d'),
                "dayName" => $dataParsed->format('l'),
                "isWeekend" => $dataParsed->isWeekend(),
                "isHoliday" => true,
                "holiday" => $dataFeriado->toArray()
            ];
        } else {
            return [
                "date" => $dataParsed->format('Y-m-d'),
                "dayName" => $dataParsed->format('l'),
                "isWeekend" => $dataParsed->isWeekend(),
                "isHoliday" => false,
                "holiday" => null
            ];
        }
    }
}
