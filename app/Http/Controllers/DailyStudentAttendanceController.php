<?php

namespace App\Http\Controllers;

use App\Enums\AttendanceStatus;
use App\Enums\StudentStatus;
use App\Http\Requests\Attendance\DailyStudentAttendanceRequest;
use App\Models\Activity;
use App\Models\Attendance;
use App\Models\Course;
use App\Models\Scope;
use App\Models\Shift;
use App\Models\Student;
use App\Services\SchoolCalendar;
use Carbon\Carbon;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;

/**
 * Toma de Asistencia Diaria Contextualizada de Estudiantes:
 * Filtro encadenado `Fecha -> Turno -> Ámbito -> Curso/División -> Actividad`,
 * sincronizado con el reloj del servidor y protegido por `CheckCourseAssignment`.
 */
class DailyStudentAttendanceController extends Controller
{
    public function __construct(private readonly SchoolCalendar $calendar)
    {
    }

    public function index(Request $request)
    {
        $request->validate([
            'date' => ['nullable', 'date_format:Y-m-d'],
            'shift_id' => ['nullable', 'integer'],
            'scope_id' => ['nullable', 'integer'],
            'course_id' => ['nullable', 'integer'],
            'activity_id' => ['nullable', 'integer'],
        ]);

        $user = $request->user();
        $date = $request->filled('date')
            ? Carbon::createFromFormat('Y-m-d', $request->string('date')->toString())->startOfDay()
            : now()->startOfDay();

        $catalog = $this->buildChainedCatalog($user, $date);

        $selectedCourseId = $request->integer('course_id') ?: ($catalog['combinations'][0]['course_id'] ?? null);
        $selectedScopeId = $request->integer('scope_id') ?: ($catalog['combinations'][0]['scope_id'] ?? null);
        $selectedShiftId = $request->integer('shift_id') ?: null;
        $selectedActivityId = $request->integer('activity_id') ?: null;

        $course = $selectedCourseId ? Course::with(['specialty', 'shiftRef'])->find($selectedCourseId) : null;
        $scope = $selectedScopeId ? Scope::find($selectedScopeId) : null;

        if ($course && ! $selectedShiftId) {
            $selectedShiftId = $course->shift_id;
        }

        if ($scope && ! $selectedActivityId) {
            $selectedActivityId = Activity::active()->where('scope_id', $scope->id)->orderBy('id')->value('id');
        }

        $students = collect();
        $existingByStudent = collect();

        if ($course && $scope) {
            $dateYmd = $date->toDateString();

            $students = Student::query()
                ->where('course_id', $course->id)
                ->where('status', StudentStatus::Activo->value)
                ->where('active', true)
                ->with([
                    'teacherClassObservations' => function ($q) use ($dateYmd): void {
                        $q->where('date', $dateYmd)
                            ->with(['teacher:id,name', 'activity:id,name'])
                            ->orderByDesc('id');
                    },
                ])
                ->alphabetical()
                ->get();

            $existingByStudent = Attendance::query()
                ->where('course_id', $course->id)
                ->where('activity_type', $scope->slug)
                ->where('date', $dateYmd)
                ->with(['justification.justifiedBy', 'recorder'])
                ->get()
                ->keyBy('student_id');
        }

        $isFuture = $date->gt(now()->startOfDay());
        $isWorkingDay = $this->calendar->isWorkingDay($date);
        $nonWorkingReason = ! $isWorkingDay
            ? ($date->isWeekend() ? 'Fin de semana (sábado o domingo)' : ($this->calendar->nonWorkingReason($date) ?? 'Día no laborable / feriado'))
            : null;

        return view('attendance.daily-students', [
            'date' => $date,
            'fechaActual' => $date,
            'today' => now()->startOfDay(),
            'isFuture' => $isFuture,
            'isWorkingDay' => $isWorkingDay,
            'nonWorkingReason' => $nonWorkingReason,
            'canEditDay' => ! $isFuture && $isWorkingDay,
            'catalog' => $catalog,
            'selected' => [
                'shift_id' => $selectedShiftId,
                'scope_id' => $scope?->id,
                'course_id' => $course?->id,
                'activity_id' => $selectedActivityId,
            ],
            'course' => $course,
            'scope' => $scope,
            'students' => $students,
            'existingByStudent' => $existingByStudent,
        ]);
    }

    public function store(DailyStudentAttendanceRequest $request): RedirectResponse
    {
        $data = $request->validated();
        $date = Carbon::createFromFormat('Y-m-d', $data['date'])->startOfDay();
        $course = Course::findOrFail((int) $data['course_id']);
        $scope = Scope::findOrFail((int) $data['scope_id']);
        $activityId = ! empty($data['activity_id']) ? (int) $data['activity_id'] : null;
        $shiftId = ! empty($data['shift_id']) ? (int) $data['shift_id'] : $course->shift_id;

        $savedCount = 0;

        DB::transaction(function () use ($data, $date, $course, $scope, $activityId, $shiftId, $request, &$savedCount) {
            $validStudentIds = Student::query()
                ->where('course_id', $course->id)
                ->where('status', StudentStatus::Activo->value)
                ->pluck('id')
                ->all();

            foreach ($data['records'] as $rec) {
                $studentId = (int) $rec['student_id'];
                if (! in_array($studentId, $validStudentIds, true)) {
                    continue;
                }

                $newStatus = AttendanceStatus::from($rec['status']);
                $normalizedObs = filled($rec['observation'] ?? null) ? trim((string) $rec['observation']) : null;
                $existing = Attendance::query()
                    ->where('student_id', $studentId)
                    ->where('date', $date->toDateString())
                    ->where('activity_type', $scope->slug)
                    ->with('justification')
                    ->first();

                if ($existing?->status === AttendanceStatus::Justificada && $newStatus !== AttendanceStatus::Justificada) {
                    $existing->justification?->delete();
                }

                $hasRowChanged = ! $existing
                    || $existing->status !== $newStatus
                    || $existing->observation !== $normalizedObs;

                $recorderUserId = ($hasRowChanged || ! $existing?->user_id)
                    ? $request->user()->id
                    : $existing->user_id;

                Attendance::updateOrCreate(
                    [
                        'student_id' => $studentId,
                        'date' => $date->toDateString(),
                        'activity_type' => $scope->slug,
                    ],
                    [
                        'course_id' => $course->id,
                        'scope_id' => $scope->id,
                        'activity_id' => $activityId,
                        'shift_id' => $shiftId,
                        'user_id' => $recorderUserId,
                        'status' => $newStatus->value,
                        'observation' => $normalizedObs,
                    ]
                );

                $savedCount++;
            }
        });

        return redirect()->route('attendance.daily', [
            'date' => $date->toDateString(),
            'shift_id' => $shiftId,
            'scope_id' => $scope->id,
            'course_id' => $course->id,
            'activity_id' => $activityId,
        ])->with('status', "Asistencia diaria guardada ({$savedCount} estudiantes de {$course->name} · {$scope->name} · {$date->format('d/m/Y')}).");
    }

    /**
     * Construye el catálogo de combinaciones válidas (`Turno -> Ámbito -> Curso -> Actividades`)
     * respetando estrictamente las asignaciones del Operador o habilitando todo para el Director.
     */
    private function buildChainedCatalog($user, Carbon $date): array
    {
        $activitiesByScope = Activity::active()->ordered()->get()->groupBy('scope_id')->map(
            fn ($items) => $items->map(fn (Activity $a) => ['id' => $a->id, 'name' => $a->name])->values()->all()
        )->all();

        $combinations = [];

        if ($user->isDirector()) {
            $courses = Course::with(['shiftRef', 'specialty'])->active()->ordered()->get();
            $scopes = Scope::active()->ordered()->get();

            foreach ($courses as $c) {
                foreach ($scopes as $s) {
                    $combinations[] = [
                        'shift_id' => $c->shift_id,
                        'shift_name' => $c->shiftRef?->name ?? $c->shift->label(),
                        'scope_id' => $s->id,
                        'scope_slug' => $s->slug,
                        'scope_name' => $s->name,
                        'course_id' => $c->id,
                        'course_name' => $c->full_name,
                    ];
                }
            }
        } else {
            $assignments = $user->assignments()
                ->with(['course.shiftRef', 'course.specialty', 'scopeRef', 'shift'])
                ->get();

            foreach ($assignments as $a) {
                if (! $a->course || ! $a->course->active) {
                    continue;
                }
                $scope = $a->scopeRef ?? Scope::where('slug', $a->activity_type->value)->first();
                if (! $scope || ! $scope->active) {
                    continue;
                }

                $shiftId = $a->shift_id ?: $a->course->shift_id;
                $shiftName = $a->shift?->name ?? $a->course->shiftRef?->name ?? $a->course->shift->label();

                $combinations[] = [
                    'shift_id' => $shiftId,
                    'shift_name' => $shiftName,
                    'scope_id' => $scope->id,
                    'scope_slug' => $scope->slug,
                    'scope_name' => $scope->name,
                    'course_id' => $a->course->id,
                    'course_name' => $a->course->full_name,
                ];
            }
        }

        $courseIds = collect($combinations)->pluck('course_id')->unique()->values();
        $recordedByCourseActivity = $courseIds->isEmpty()
            ? collect()
            : Attendance::query()
                ->whereIn('course_id', $courseIds)
                ->where('date', $date->toDateString())
                ->selectRaw('course_id, activity_type, COUNT(*) as recorded')
                ->groupBy('course_id', 'activity_type')
                ->get()
                ->mapWithKeys(function ($row) {
                    $slug = (string) ($row->getRawOriginal('activity_type') ?? $row->activity_type->value ?? $row->activity_type);

                    return ["{$row->course_id}:{$slug}" => (int) $row->recorded];
                });

        foreach ($combinations as &$combo) {
            $key = "{$combo['course_id']}:{$combo['scope_slug']}";
            $recordedCount = (int) ($recordedByCourseActivity[$key] ?? 0);
            $combo['taken'] = $recordedCount > 0;
            $combo['recorded_count'] = $recordedCount;
        }
        unset($combo);

        return [
            'combinations' => $combinations,
            'activities_by_scope' => $activitiesByScope,
            'shifts' => Shift::active()->ordered()->get(['id', 'name'])->toArray(),
        ];
    }
}
