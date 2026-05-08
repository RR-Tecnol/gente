<?php

namespace App\Services;

use Carbon\Carbon;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

class HolidayCalendarService
{
    public function getBaseHolidaysForYear(int $year): array
    {
        $fixos = [
            ['Confraternização Universal', "$year-01-01", 'national', false, 2.0],
            ['Tiradentes', "$year-04-21", 'national', false, 2.0],
            ['Dia do Trabalhador', "$year-05-01", 'national', false, 2.0],
            ['Independência do Brasil', "$year-09-07", 'national', false, 2.0],
            ['Nossa Senhora Aparecida', "$year-10-12", 'national', false, 2.0],
            ['Finados', "$year-11-02", 'national', false, 2.0],
            ['Proclamação da República', "$year-11-15", 'national', false, 2.0],
            ['Dia da Consciência Negra', "$year-11-20", 'national', false, 2.0],
            ['Natal', "$year-12-25", 'national', false, 2.0],
            ['São Pedro (São Luís)', "$year-06-29", 'municipal', false, 2.0],
            ['Adesão do Maranhão', "$year-07-28", 'state', false, 2.0],
            ['Aniversário de São Luís', "$year-09-08", 'municipal', false, 2.0],
            ['Nossa Senhora da Conceição', "$year-12-08", 'municipal', false, 2.0],
        ];

        $pascoa = Carbon::createFromTimestamp(easter_date($year))->startOfDay();
        $moveis = [
            ['Carnaval (Segunda-feira)', $pascoa->copy()->subDays(48)->toDateString(), 'national', true, 1.0],
            ['Carnaval (Terça-feira)', $pascoa->copy()->subDays(47)->toDateString(), 'national', true, 1.0],
            ['Quarta-feira de Cinzas (até 14h)', $pascoa->copy()->subDays(46)->toDateString(), 'national', true, 1.0],
            ['Sexta-feira Santa', $pascoa->copy()->subDays(2)->toDateString(), 'national', false, 2.0],
            ['Corpus Christi', $pascoa->copy()->addDays(60)->toDateString(), 'national', true, 1.0],
        ];

        $todos = array_merge($fixos, $moveis);

        return array_map(fn($item) => [
            'title' => $item[0],
            'date' => $item[1],
            'scope' => $item[2],
            'type' => 'holiday',
            'is_point_facultative' => $item[3],
            'pay_multiplier' => $item[4],
            'source' => 'base',
            'target_id' => null,
            'impacts_bank_of_hours' => true,
        ], $todos);
    }

    public function getOverridesBetween(string $inicio, string $fim): Collection
    {
        if (!Schema::hasTable('calendar_overrides')) {
            return collect();
        }

        return DB::table('calendar_overrides')
            ->whereBetween('date', [$inicio, $fim])
            ->orderBy('date')
            ->get();
    }

    public function getEffectiveHolidayDatesForMonth(
        int $year,
        int $month,
        ?int $funcionarioId,
        ?int $setorId
    ): array {
        $inicio = sprintf('%04d-%02d-01', $year, $month);
        $fim = Carbon::parse($inicio)->endOfMonth()->toDateString();
        $base = collect($this->getBaseHolidaysForYear($year))
            ->filter(fn($f) => str_starts_with($f['date'], sprintf('%04d-%02d-', $year, $month)))
            ->pluck('date');

        $overrides = $this->getOverridesBetween($inicio, $fim)
            ->filter(function ($ovr) use ($funcionarioId, $setorId) {
                $scope = strtolower((string) ($ovr->scope ?? 'global'));
                if ($scope === 'global') {
                    return true;
                }
                if ($scope === 'sector') {
                    return (int) ($ovr->target_id ?? 0) > 0 && (int) $ovr->target_id === (int) $setorId;
                }
                if ($scope === 'user') {
                    return (int) ($ovr->target_id ?? 0) > 0 && (int) $ovr->target_id === (int) $funcionarioId;
                }
                return false;
            })
            ->pluck('date');

        return $base
            ->concat($overrides)
            ->map(fn($d) => (string) $d)
            ->unique()
            ->values()
            ->all();
    }
}
