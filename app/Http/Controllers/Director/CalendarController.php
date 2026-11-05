<?php

namespace App\Http\Controllers\Director;

use App\Http\Controllers\Controller;
use App\Models\NonWorkingDay;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;

/** Calendario escolar: feriados, recesos y días sin actividad (no cuentan como días hábiles). */
class CalendarController extends Controller
{
    public function index(Request $request)
    {
        $request->validate(['year' => ['nullable', 'integer', 'between:2020,2100']]);
        $year = $request->integer('year') ?: now()->year;

        return view('director.calendar.index', [
            'year' => $year,
            'days' => NonWorkingDay::whereBetween('date', ["{$year}-01-01", "{$year}-12-31"])->orderBy('date')->get(),
        ]);
    }

    public function store(Request $request): RedirectResponse
    {
        $data = $request->validate([
            'date' => ['required', 'date_format:Y-m-d', 'unique:non_working_days,date'],
            'date_to' => ['nullable', 'date_format:Y-m-d', 'after_or_equal:date'],
            'reason' => ['required', 'string', 'max:255'],
        ], [
            'date.unique' => 'Esa fecha ya está cargada.',
            'date_to.after_or_equal' => 'La fecha final no puede ser anterior a la inicial.',
            'reason.required' => 'Indicá el motivo (feriado, receso, etc.).',
        ]);

        // Permite cargar un rango (p. ej. receso invernal). Los fines de semana se omiten: nunca son hábiles.
        $from = \Carbon\Carbon::parse($data['date']);
        $to = \Carbon\Carbon::parse($data['date_to'] ?? $data['date']);
        abort_if($from->diffInDays($to) > 60, 422, 'El rango no puede superar los 60 días.');

        $created = 0;
        for ($d = $from->copy(); $d->lte($to); $d->addDay()) {
            if ($d->isWeekend() || NonWorkingDay::where('date', $d->toDateString())->exists()) {
                continue;
            }
            NonWorkingDay::create(['date' => $d->toDateString(), 'reason' => $data['reason']]);
            $created++;
        }

        return redirect()->route('director.calendar.index', ['year' => $from->year])
            ->with('status', $created ? "Se cargaron {$created} día(s) sin actividad." : 'No se cargó ningún día (eran fin de semana o ya existían).');
    }

    public function destroy(NonWorkingDay $day): RedirectResponse
    {
        $year = $day->date->year;
        $day->delete();

        return redirect()->route('director.calendar.index', ['year' => $year])->with('status', 'Día eliminado del calendario.');
    }
}
