<?php

namespace App\Services;

use App\Models\NonWorkingDay;
use Carbon\Carbon;
use Carbon\CarbonInterface;

/**
 * Calendario escolar: sábados, domingos y los días cargados en `non_working_days`
 * (feriados, recesos, etc.) no son hábiles.
 */
class SchoolCalendar
{
    /** @return array<string,string> fecha (Y-m-d) => motivo */
    public function nonWorkingBetween(CarbonInterface $from, CarbonInterface $to): array
    {
        return NonWorkingDay::query()
            ->whereBetween('date', [$from->toDateString(), $to->toDateString()])
            ->get()
            ->mapWithKeys(fn (NonWorkingDay $d) => [$d->date->toDateString() => $d->reason])
            ->all();
    }

    /**
     * Todos los días del mes con su condición de hábil.
     *
     * @return array<int, array{n:int, date:string, working:bool, reason:?string, weekday:string}>
     */
    public function days(CarbonInterface $month): array
    {
        $start = Carbon::instance($month)->startOfMonth()->startOfDay();
        $end = $start->copy()->endOfMonth()->startOfDay();
        $exceptions = $this->nonWorkingBetween($start, $end);

        $letters = ['D', 'L', 'M', 'X', 'J', 'V', 'S'];
        $days = [];

        for ($d = $start->copy(); $d->lte($end); $d->addDay()) {
            $key = $d->toDateString();
            $weekend = $d->isWeekend();

            $days[] = [
                'n' => $d->day,
                'date' => $key,
                'working' => ! $weekend && ! isset($exceptions[$key]),
                'reason' => $weekend ? null : ($exceptions[$key] ?? null),
                'weekday' => $letters[$d->dayOfWeek],
            ];
        }

        return $days;
    }

    public function isWorkingDay(CarbonInterface $date): bool
    {
        if ($date->isWeekend()) {
            return false;
        }

        return ! NonWorkingDay::where('date', $date->toDateString())->exists();
    }
}
