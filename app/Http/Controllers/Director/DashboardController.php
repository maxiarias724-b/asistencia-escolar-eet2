<?php

namespace App\Http\Controllers\Director;

use App\Casts\DynamicValue;
use App\Enums\ActivityType;
use App\Enums\Role;
use App\Http\Controllers\Controller;
use App\Models\Course;
use App\Models\Scope;
use App\Models\Student;
use App\Models\Teacher;
use App\Models\User;
use App\Services\AttendanceStatistics;
use Illuminate\Http\Request;
use Illuminate\Validation\Rule;

class DashboardController extends Controller
{
    /** Dashboard Global: métricas institucionales en tiempo real, resumen del día y estadísticas SINIDE. */
    public function __invoke(Request $request)
    {
        $scopes = Scope::active()->ordered()->get();
        $scopeList = $scopes->isNotEmpty()
            ? $scopes->map(fn (Scope $s) => new DynamicValue($s->slug, $s->name))->all()
            : ActivityType::cases();

        $allowedSlugs = array_map(fn ($s) => $s->value, $scopeList);

        $filters = $request->validate([
            'month' => ['nullable', 'date_format:Y-m'],
            'course_id' => ['nullable', 'integer', 'exists:courses,id'],
            'activity_type' => ['nullable', 'string', Rule::in($allowedSlugs)],
        ]);

        $stats = new AttendanceStatistics(
            $filters['month'] ?? null,
            isset($filters['course_id']) ? (int) $filters['course_id'] : null,
            $filters['activity_type'] ?? null,
        );

        return view('director.dashboard', [
            'stats' => $stats,
            'todayTotals' => $stats->todayTotals(),
            'teacherToday' => $stats->todayTeacherSummary(),
            'totals' => $stats->totals(),
            'byCourse' => $stats->breakdown('course_id'),
            'byActivity' => $stats->breakdown('activity_type'),
            'topUnjustified' => $stats->topUnjustified(),
            'recentObservations' => $stats->recentObservations(),
            'recentJustifications' => $stats->recentJustifications(),
            'courses' => Course::with(['specialty', 'shiftRef'])->ordered()->get(),
            'activities' => $scopeList,
            'filters' => $filters + ['month' => $stats->from->format('Y-m')],
            'counters' => [
                'courses' => Course::active()->count(),
                'students' => Student::active()->count(),
                'teachers' => Teacher::active()->count(),
                'operators' => User::where('role', Role::Operador->value)->where('active', true)->count(),
            ],
        ]);
    }
}
