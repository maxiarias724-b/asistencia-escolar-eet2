<?php

namespace App\Http\Controllers\Docente;

use App\Http\Controllers\Controller;
use App\Models\Course;
use App\Models\Scope;
use App\Models\Student;
use App\Models\Teacher;
use App\Models\TeacherClassObservation;
use App\Services\AttendanceStatistics;
use Illuminate\Http\Request;

class HomeController extends Controller
{
    /** Panel inicial para el Rol Docente (consulta de asistencia y acceso a observaciones de clase). */
    public function __invoke(Request $request)
    {
        $user = $request->user();
        $stats = new AttendanceStatistics();

        $assignedCourses = Course::with(['specialty', 'shiftRef'])
            ->whereIn('id', $user->assignedCourseIds())
            ->withCount(['students as active_students_count' => fn ($q) => $q->active()])
            ->active()
            ->ordered()
            ->get();

        $myTodayObservationsCount = TeacherClassObservation::query()
            ->where('teacher_id', $user->id)
            ->forDate(now())
            ->count();

        return view('docente.home', [
            'todayTotals' => $stats->todayTotals(),
            'totals' => $stats->totals(),
            'byCourse' => $stats->breakdown('course_id'),
            'assignedCourses' => $assignedCourses,
            'myTodayObservationsCount' => $myTodayObservationsCount,
            'counters' => [
                'courses' => Course::active()->count(),
                'students' => Student::active()->count(),
                'teachers' => Teacher::active()->count(),
                'scopes' => Scope::active()->count(),
            ],
        ]);
    }
}
