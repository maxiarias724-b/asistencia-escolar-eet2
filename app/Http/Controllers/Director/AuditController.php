<?php

namespace App\Http\Controllers\Director;

use App\Casts\DynamicValue;
use App\Enums\ActivityType;
use App\Enums\AttendanceStatus;
use App\Http\Controllers\Controller;
use App\Models\Attendance;
use App\Models\Course;
use App\Models\Scope;
use App\Models\User;
use Illuminate\Http\Request;

class AuditController extends Controller
{
    /** Visor institucional de Auditoría de Asistencias, Observaciones y Certificados Médicos (exclusivo Director). */
    public function index(Request $request)
    {
        $scopes = Scope::ordered()->get();
        $validActivitySlugs = array_values(array_unique(array_merge(
            array_column(ActivityType::cases(), 'value'),
            $scopes->pluck('slug')->all(),
        )));

        $f = $request->validate([
            'from' => ['nullable', 'date'],
            'to' => ['nullable', 'date', 'after_or_equal:from'],
            'course_id' => ['nullable', 'integer', 'exists:courses,id'],
            'activity_type' => ['nullable', 'in:'.implode(',', $validActivitySlugs)],
            'status' => ['nullable', 'in:'.implode(',', array_column(AttendanceStatus::cases(), 'value'))],
            'user_id' => ['nullable', 'integer', 'exists:users,id'],
            'with_observation' => ['nullable', 'boolean'],
            'only_certificates' => ['nullable', 'boolean'],
            'q' => ['nullable', 'string', 'max:100'],
        ]);

        $from = $f['from'] ?? now()->startOfMonth()->toDateString();
        $to = $f['to'] ?? now()->toDateString();

        $baseQuery = Attendance::query()
            ->whereBetween('date', [$from, $to])
            ->when($f['course_id'] ?? null, fn ($q, $v) => $q->where('course_id', $v))
            ->when($f['activity_type'] ?? null, fn ($q, $v) => $q->where('activity_type', $v))
            ->when($f['status'] ?? null, fn ($q, $v) => $q->where('status', $v))
            ->when($f['user_id'] ?? null, fn ($q, $v) => $q->where('user_id', $v))
            ->when($request->boolean('with_observation'), fn ($q) => $q->whereNotNull('observation'))
            ->when($request->boolean('only_certificates'), fn ($q) => $q->whereHas('justification', fn ($jq) => $jq->whereNotNull('certificate_path')))
            ->when($f['q'] ?? null, function ($q, $term) {
                $q->whereHas('student', function ($s) use ($term) {
                    $s->where('last_name', 'like', "%{$term}%")
                        ->orWhere('first_name', 'like', "%{$term}%")
                        ->orWhere('dni', 'like', "%{$term}%");
                });
            });

        $stats = [
            'total' => (clone $baseQuery)->count(),
            'justified' => (clone $baseQuery)->where('status', AttendanceStatus::Justificada->value)->count(),
            'certificates' => (clone $baseQuery)->whereHas('justification', fn ($jq) => $jq->whereNotNull('certificate_path'))->count(),
            'observations' => (clone $baseQuery)->whereNotNull('observation')->count(),
        ];

        $attendances = $baseQuery
            ->with(['student', 'course.shiftRef', 'scopeRef', 'user', 'justification.justifiedBy'])
            ->orderByDesc('date')->orderBy('course_id')->orderByDesc('id')
            ->paginate(25)
            ->withQueryString();

        $attendances->getCollection()->each(function (Attendance $a) {
            $j = $a->justification;
            $a->justification_payload = $j ? [
                'student' => $a->student->full_name,
                'date' => $a->date->format('d/m/Y'),
                'course' => $a->course->full_name,
                'reason' => $j->reason_text,
                'by' => $j->justifiedBy?->name,
                'at' => $j->justified_at?->format('d/m/Y H:i') ?? '—',
                'certificate_url' => $j->hasCertificate() && $j->certificateExists()
                    ? route('director.justifications.certificate', $j)
                    : null,
                'certificate_missing' => $j->hasCertificate() && ! $j->certificateExists(),
                'certificate_is_pdf' => $j->hasCertificate()
                    && strtolower(pathinfo($j->certificate_path, PATHINFO_EXTENSION)) === 'pdf',
            ] : null;
        });

        $activities = $scopes->isNotEmpty()
            ? $scopes->map(fn (Scope $s) => new DynamicValue($s->slug, $s->name))->all()
            : ActivityType::cases();

        return view('director.audit.index', [
            'attendances' => $attendances,
            'stats' => $stats,
            'courses' => Course::with('shiftRef')->ordered()->get(),
            'operators' => User::orderBy('name')->get(['id', 'name']),
            'activities' => $activities,
            'statuses' => AttendanceStatus::cases(),
            'filters' => array_merge($f, [
                'from' => $from,
                'to' => $to,
                'with_observation' => $request->boolean('with_observation'),
                'only_certificates' => $request->boolean('only_certificates'),
            ]),
        ]);
    }
}
