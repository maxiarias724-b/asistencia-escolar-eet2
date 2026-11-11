<?php

namespace App\Http\Controllers;

use App\Enums\AttendanceStatus;
use App\Http\Requests\Attendance\TeacherAttendanceRequest;
use App\Models\Activity;
use App\Models\Shift;
use App\Models\Teacher;
use App\Models\TeacherAttendance;
use App\Services\SchoolCalendar;
use Carbon\Carbon;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;

/**
 * Registro diario de Asistencia de Docentes por Fecha y Turno (con Actividad opcional y Observación).
 */
class TeacherAttendanceController extends Controller
{
    public function __construct(private readonly SchoolCalendar $calendar)
    {
    }

    public function index(Request $request)
    {
        $request->validate([
            'date' => ['nullable', 'date_format:Y-m-d'],
            'shift_id' => ['nullable', 'integer', 'exists:shifts,id'],
            'activity_id' => ['nullable', 'integer', 'exists:activities,id'],
            'q' => ['nullable', 'string', 'max:100'],
        ]);

        $date = $request->filled('date')
            ? Carbon::createFromFormat('Y-m-d', $request->string('date')->toString())->startOfDay()
            : now()->startOfDay();

        $shifts = Shift::active()->ordered()->get();
        $selectedShift = $request->filled('shift_id')
            ? $shifts->firstWhere('id', $request->integer('shift_id'))
            : $shifts->first();

        $selectedActivityId = $request->integer('activity_id') ?: null;
        $q = $request->string('q')->trim()->toString();

        $teachers = Teacher::query()
            ->active()
            ->when($q !== '', function ($query) use ($q) {
                $query->where(fn ($sub) => $sub
                    ->where('last_name', 'like', "%{$q}%")
                    ->orWhere('first_name', 'like', "%{$q}%")
                    ->orWhere('dni', 'like', "%{$q}%")
                    ->orWhere('specialty_area', 'like', "%{$q}%"));
            })
            ->alphabetical()
            ->get();

        $existingByTeacher = $selectedShift
            ? TeacherAttendance::query()
                ->where('date', $date->toDateString())
                ->where('shift_id', $selectedShift->id)
                ->when($selectedActivityId, fn ($query) => $query->where('activity_id', $selectedActivityId), fn ($query) => $query->whereNull('activity_id'))
                ->with('recorder')
                ->get()
                ->keyBy('teacher_id')
            : collect();

        $isFuture = $date->gt(now()->startOfDay());
        $isWorkingDay = $this->calendar->isWorkingDay($date);
        $nonWorkingReason = ! $isWorkingDay
            ? ($date->isWeekend() ? 'Fin de semana (sábado o domingo)' : ($this->calendar->nonWorkingReason($date) ?? 'Día feriado / sin actividad'))
            : null;

        return view('attendance.teachers', [
            'date' => $date,
            'today' => now()->startOfDay(),
            'canEditDay' => ! $isFuture && $isWorkingDay,
            'isFuture' => $isFuture,
            'nonWorkingReason' => $nonWorkingReason,
            'shifts' => $shifts,
            'selectedShift' => $selectedShift,
            'activities' => Activity::with('scopeRef')->active()->ordered()->get(),
            'selectedActivityId' => $selectedActivityId,
            'q' => $q,
            'teachers' => $teachers,
            'existingByTeacher' => $existingByTeacher,
        ]);
    }

    public function store(TeacherAttendanceRequest $request): RedirectResponse
    {
        $data = $request->validated();
        $date = Carbon::createFromFormat('Y-m-d', $data['date'])->startOfDay();
        $shiftId = (int) $data['shift_id'];
        $activityId = ! empty($data['activity_id']) ? (int) $data['activity_id'] : null;

        $savedCount = 0;

        DB::transaction(function () use ($data, $date, $shiftId, $activityId, $request, &$savedCount) {
            foreach ($data['records'] as $rec) {
                $status = AttendanceStatus::from($rec['status']);

                TeacherAttendance::updateOrCreate(
                    [
                        'teacher_id' => (int) $rec['teacher_id'],
                        'date' => $date->toDateString(),
                        'shift_id' => $shiftId,
                        'activity_id' => $activityId,
                    ],
                    [
                        'recorded_by' => $request->user()->id,
                        'status' => $status->value,
                        'observation' => filled($rec['observation'] ?? null) ? trim((string) $rec['observation']) : null,
                    ]
                );

                $savedCount++;
            }
        });

        return redirect()->route('attendance.teachers.index', array_filter([
            'date' => $date->toDateString(),
            'shift_id' => $shiftId,
            'activity_id' => $activityId,
        ]))->with('status', "Asistencia docente registrada correctamente ({$savedCount} docentes · {$date->format('d/m/Y')}).");
    }
}
