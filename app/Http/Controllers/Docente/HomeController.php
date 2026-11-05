<?php

namespace App\Http\Controllers\Docente;

use App\Http\Controllers\Controller;
use App\Models\Course;
use App\Models\Scope;
use App\Models\Student;
use App\Models\Teacher;
use App\Services\AttendanceStatistics;
use Illuminate\Http\Request;

class HomeController extends Controller
{
    /** Panel inicial de consulta para el Rol Docente (solo lectura, sin permisos de carga). */
    public function __invoke(Request $request)
    {
        $stats = new AttendanceStatistics();

        return view('docente.home', [
            'todayTotals' => $stats->todayTotals(),
            'totals' => $stats->totals(),
            'byCourse' => $stats->breakdown('course_id'),
            'counters' => [
                'courses' => Course::active()->count(),
                'students' => Student::active()->count(),
                'teachers' => Teacher::active()->count(),
                'scopes' => Scope::active()->count(),
            ],
        ]);
    }
}
