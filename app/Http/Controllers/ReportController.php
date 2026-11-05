<?php

namespace App\Http\Controllers;

use App\Enums\AttendanceStatus;
use App\Http\Middleware\CheckCourseAssignment;
use App\Models\Course;
use App\Models\Scope;
use App\Models\Shift;
use App\Models\Specialty;
use App\Models\Student;
use App\Services\AttendanceReportService;
use Carbon\Carbon;
use Illuminate\Http\Request;

/**
 * Centro de Consultas, Historial Individual, Ranking de Inasistencias,
 * Planilla Mensual (01 al 31) y Exportaciones a Excel (.xlsx), CSV (.csv) y PDF (.pdf).
 */
class ReportController extends Controller
{
    public function __construct(private readonly AttendanceReportService $reports)
    {
    }

    /** 1. Consulta Histórica con filtros combinados y exportación Excel/CSV/PDF. */
    public function index(Request $request)
    {
        $user = $request->user();
        $scopes = Scope::active()->ordered()->get();

        $filters = $request->validate([
            'from' => ['nullable', 'date'],
            'to' => ['nullable', 'date', 'after_or_equal:from'],
            'shift_id' => ['nullable', 'integer', 'exists:shifts,id'],
            'scope' => ['nullable', 'string', 'max:50'],
            'course_id' => ['nullable', 'integer', 'exists:courses,id'],
            'specialty_id' => ['nullable', 'integer', 'exists:specialties,id'],
            'status' => ['nullable', 'in:'.implode(',', array_column(AttendanceStatus::cases(), 'value'))],
            'q' => ['nullable', 'string', 'max:100'],
            'export' => ['nullable', 'in:xlsx,csv,pdf'],
        ]);

        if (! empty($filters['course_id']) && ! $this->reports->canAccessCourse($user, (int) $filters['course_id'], $filters['scope'] ?? null)) {
            return $this->deny($request);
        }

        $filters['from'] = $filters['from'] ?? now()->startOfMonth()->toDateString();
        $filters['to'] = $filters['to'] ?? now()->toDateString();

        $baseQuery = $this->reports->historicalBaseQuery($filters, $user);

        if (! empty($filters['export'])) {
            return $this->reports->exportHistorical($filters['export'], $baseQuery, $filters);
        }

        $kpis = $this->reports->historicalKpis($baseQuery);
        $records = $baseQuery->orderByDesc('date')->orderBy('course_id')->paginate(25)->withQueryString();

        return view('reports.index', [
            'records' => $records,
            'kpis' => $kpis,
            'filters' => $filters,
            'courses' => $this->accessibleCourses($user),
            'scopes' => $scopes,
            'shifts' => Shift::active()->ordered()->get(),
            'specialties' => Specialty::active()->ordered()->get(),
            'statuses' => AttendanceStatus::cases(),
        ]);
    }

    /** 2. Ranking de Estudiantes con mayor cantidad de inasistencias + exportación Excel/CSV/PDF. */
    public function ranking(Request $request)
    {
        $user = $request->user();
        $schoolYearStart = now()->month >= 3
            ? now()->copy()->month(3)->startOfMonth()->toDateString()
            : now()->copy()->subYear()->month(3)->startOfMonth()->toDateString();

        $filters = $request->validate([
            'from' => ['nullable', 'date'],
            'to' => ['nullable', 'date', 'after_or_equal:from'],
            'course_id' => ['nullable', 'integer', 'exists:courses,id'],
            'specialty_id' => ['nullable', 'integer', 'exists:specialties,id'],
            'shift_id' => ['nullable', 'integer', 'exists:shifts,id'],
            'scope' => ['nullable', 'string', 'max:50'],
            'export' => ['nullable', 'in:xlsx,csv,pdf'],
        ]);

        if (! empty($filters['course_id']) && ! $this->reports->canAccessCourse($user, (int) $filters['course_id'], $filters['scope'] ?? null)) {
            return $this->deny($request);
        }

        $filters['from'] = $filters['from'] ?? $schoolYearStart;
        $filters['to'] = $filters['to'] ?? now()->toDateString();

        $ranking = $this->reports->buildAbsenceRanking($filters, $user);

        if (! empty($filters['export'])) {
            return $this->reports->exportRanking($filters['export'], $ranking, $filters);
        }

        return view('reports.ranking', [
            'ranking' => $ranking,
            'filters' => $filters,
            'courses' => $this->accessibleCourses($user),
            'scopes' => Scope::active()->ordered()->get(),
            'shifts' => Shift::active()->ordered()->get(),
            'specialties' => Specialty::active()->ordered()->get(),
        ]);
    }

    /** 3A. Buscador / selector para acceder al Historial Individual de un Estudiante. */
    public function studentHistorySearch(Request $request)
    {
        $user = $request->user();
        $allowed = $this->reports->allowedCourseIds($user);
        $q = $request->string('q')->trim()->toString();
        $courseId = $request->integer('course_id') ?: null;

        if ($courseId && ! $this->reports->canAccessCourse($user, $courseId)) {
            return $this->deny($request);
        }

        $students = Student::query()
            ->with(['course.specialty', 'course.shiftRef'])
            ->withCount('attendances')
            ->when($allowed !== null, fn ($query) => $query->whereIn('course_id', $allowed))
            ->when($courseId, fn ($query) => $query->where('course_id', $courseId))
            ->when($q !== '', function ($query) use ($q) {
                $query->where(fn ($sub) => $sub
                    ->where('last_name', 'like', "%{$q}%")
                    ->orWhere('first_name', 'like', "%{$q}%")
                    ->orWhere('dni', 'like', "%{$q}%")
                    ->orWhere('file_number', 'like', "%{$q}%"));
            })
            ->alphabetical()
            ->paginate(20)
            ->withQueryString();

        return view('reports.student-search', [
            'students' => $students,
            'courses' => $this->accessibleCourses($user),
            'q' => $q,
            'courseId' => $courseId,
        ]);
    }

    /** 3B. Ficha de Historial Individual del Estudiante + exportación Excel/CSV/PDF. */
    public function studentHistory(Request $request, Student $student)
    {
        $user = $request->user();
        if (! $this->reports->canAccessCourse($user, (int) $student->course_id)) {
            return $this->deny($request);
        }

        $schoolYearStart = now()->month >= 3
            ? now()->copy()->month(3)->startOfMonth()->toDateString()
            : now()->copy()->subYear()->month(3)->startOfMonth()->toDateString();

        $filters = $request->validate([
            'from' => ['nullable', 'date'],
            'to' => ['nullable', 'date', 'after_or_equal:from'],
            'scope' => ['nullable', 'string', 'max:50'],
            'export' => ['nullable', 'in:xlsx,csv,pdf'],
        ]);

        $from = $filters['from'] ?? $schoolYearStart;
        $to = $filters['to'] ?? now()->toDateString();
        $scopeSlug = $filters['scope'] ?? null;

        $history = $this->reports->buildStudentHistory($student, $from, $to, $scopeSlug);

        if (! empty($filters['export'])) {
            return $this->reports->exportStudentHistory($filters['export'], $history, $from, $to);
        }

        return view('reports.student-history', $history + [
            'from' => $from,
            'to' => $to,
            'scope' => $scopeSlug,
            'scopes' => Scope::active()->ordered()->get(),
        ]);
    }

    /** 4. Planilla Mensual de Asistencia (01 al 31 con siglas P, A, T, IJ / SINIDE) + exportación Excel/CSV/PDF. */
    public function monthlySheet(Request $request)
    {
        $user = $request->user();

        $data = $request->validate([
            'month' => ['nullable', 'date_format:Y-m'],
            'course_id' => ['nullable', 'integer', 'exists:courses,id'],
            'scope' => ['nullable', 'string', 'max:50'],
            'notation' => ['nullable', 'in:patij,sinide'],
            'export' => ['nullable', 'in:xlsx,csv,pdf'],
        ]);

        $courses = $this->accessibleCourses($user);
        $scopes = Scope::active()->ordered()->get();

        $selectedCourse = ! empty($data['course_id'])
            ? Course::with(['specialty', 'shiftRef'])->find((int) $data['course_id'])
            : $courses->first();

        $selectedScope = ! empty($data['scope'])
            ? Scope::where('slug', $data['scope'])->first()
            : $scopes->first();

        if ($selectedCourse && ! $this->reports->canAccessCourse($user, $selectedCourse->id, $selectedScope?->slug)) {
            return $this->deny($request);
        }

        $month = ! empty($data['month'])
            ? Carbon::createFromFormat('Y-m-d', $data['month'].'-01')->startOfDay()
            : now()->startOfMonth()->startOfDay();

        $notation = $data['notation'] ?? 'patij';

        if (! empty($data['export']) && $selectedCourse && $selectedScope) {
            return $this->reports->exportMonthlySheet($data['export'], $selectedCourse, $selectedScope, $month, $notation);
        }

        $sheet = ($selectedCourse && $selectedScope)
            ? $this->reports->buildMonthlySheet($selectedCourse, $selectedScope, $month, $notation)
            : null;

        return view('reports.monthly-sheet', [
            'courses' => $courses,
            'scopes' => $scopes,
            'selectedCourse' => $selectedCourse,
            'selectedScope' => $selectedScope,
            'month' => $month,
            'notation' => $notation,
            'sheet' => $sheet,
        ]);
    }

    private function accessibleCourses($user)
    {
        $allowed = $this->reports->allowedCourseIds($user);

        return Course::with(['specialty', 'shiftRef'])
            ->active()
            ->when($allowed !== null, fn ($q) => $q->whereIn('id', $allowed))
            ->ordered()
            ->get();
    }

    private function deny(Request $request)
    {
        if ($request->expectsJson()) {
            return response()->json(['message' => CheckCourseAssignment::DENIED_MESSAGE], 403);
        }

        return response()->view('errors.access-denied', ['message' => CheckCourseAssignment::DENIED_MESSAGE], 403);
    }
}
