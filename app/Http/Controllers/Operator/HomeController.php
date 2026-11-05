<?php

namespace App\Http\Controllers\Operator;

use App\Casts\DynamicValue;
use App\Enums\ActivityType;
use App\Http\Controllers\Controller;
use App\Models\Attendance;
use App\Models\Scope;
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

        return view('operator.home', [
            'groups' => $groups,
            'activities' => $activities,
            'todayRecords' => $todayRecords,
            'assignedCount' => $assignments->count(),
        ]);
    }
}
