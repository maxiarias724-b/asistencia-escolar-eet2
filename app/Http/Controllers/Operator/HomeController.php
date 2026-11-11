<?php

namespace App\Http\Controllers\Operator;

use App\Casts\DynamicValue;
use App\Enums\ActivityType;
use App\Enums\StudentStatus;
use App\Http\Controllers\Controller;
use App\Models\Attendance;
use App\Models\Scope;
use App\Models\Student;
use App\Services\AttendanceStatistics;
use Illuminate\Http\Request;

class HomeController extends Controller
{
    /** Muestra únicamente los cursos y ámbitos asignados por el Director al Preceptor/Operador. */
    public function __invoke(Request $request)
    {
        $assignments = $request->user()
            ->assignments()
            ->with(['course.specialty', 'course.shiftRef', 'scopeRef'])
            ->get()
            ->sortBy(fn ($a) => sprintf('%s-%02d-%s', $a->activity_type->value, $a->course->year, $a->course->division));

        $groups = $assignments->groupBy(fn ($a) => $a->activity_type->value);

        $scopeLabels = Scope::pluck('name', 'slug');
        $activities = $groups->keys()->map(function (string $slug) use ($scopeLabels) {
            return new DynamicValue(
                $slug,
                $scopeLabels[$slug] ?? ActivityType::tryFrom($slug)?->label() ?? ucfirst(str_replace(['_', '-'], ' ', $slug))
            );
        })->values()->all();

        $today = now()->toDateString();
        $courseIds = $assignments->pluck('course_id')->unique()->values();
        $todayRecords = $courseIds->isEmpty()
            ? 0
            : Attendance::whereIn('course_id', $courseIds)->where('date', $today)->count();

        $studentCountsByCourse = $courseIds->isEmpty()
            ? collect()
            : Student::query()
                ->whereIn('course_id', $courseIds)
                ->where('status', StudentStatus::Activo->value)
                ->where('active', true)
                ->selectRaw('course_id, COUNT(*) as total')
                ->groupBy('course_id')
                ->pluck('total', 'course_id');

        $todayAttendances = $courseIds->isEmpty()
            ? collect()
            : Attendance::query()
                ->whereIn('course_id', $courseIds)
                ->where('date', $today)
                ->with('recorder')
                ->get()
                ->groupBy(function (Attendance $row) {
                    $slug = (string) ($row->getRawOriginal('activity_type') ?? $row->activity_type->value ?? $row->activity_type);

                    return "{$row->course_id}:{$slug}";
                });

        $statusByAssignment = [];
        $takenCoursesCount = 0;
        $pendingCoursesCount = 0;

        foreach ($assignments as $assignment) {
            $slug = $assignment->activity_type->value;
            $key = "{$assignment->course_id}:{$slug}";
            $rowsForKey = $todayAttendances->get($key, collect());
            $recorded = $rowsForKey->count();
            $totalStudents = (int) ($studentCountsByCourse[$assignment->course_id] ?? 0);
            $taken = $recorded > 0;
            $recorders = $rowsForKey
                ->map(fn (Attendance $r) => $r->recorder ? ($r->recorder->name.' ('.$r->recorder->role->label().')') : null)
                ->filter()
                ->unique()
                ->values()
                ->all();

            $statusByAssignment[$key] = [
                'taken' => $taken,
                'recorded' => $recorded,
                'total_students' => $totalStudents,
                'recorders' => $recorders,
            ];

            if ($taken) {
                $takenCoursesCount++;
            } else {
                $pendingCoursesCount++;
            }
        }

        return view('operator.home', [
            'groups' => $groups,
            'activities' => $activities,
            'todayRecords' => $todayRecords,
            'assignedCount' => $assignments->count(),
            'statusByAssignment' => $statusByAssignment,
            'takenCoursesCount' => $takenCoursesCount,
            'pendingCoursesCount' => $pendingCoursesCount,
            'teacherToday' => (new AttendanceStatistics())->todayTeacherSummary(),
        ]);
    }
}
