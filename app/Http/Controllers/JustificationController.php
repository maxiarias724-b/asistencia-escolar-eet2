<?php

namespace App\Http\Controllers;

use App\Enums\AttendanceStatus;
use App\Enums\StudentStatus;
use App\Http\Requests\JustificationRequest;
use App\Models\Attendance;
use App\Models\Course;
use App\Models\Justification;
use App\Models\Scope;
use App\Models\Student;
use App\Services\MonthlyRegister;
use App\Services\SchoolCalendar;
use App\Support\CertificateResponse;
use Carbon\Carbon;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Storage;
use Illuminate\Validation\ValidationException;

/**
 * Inasistencia Justificada (en tiempo real y retroactiva).
 *
 * REGLA CRÍTICA: no se puede justificar "en vacío". Debe existir al menos uno de:
 *   - un certificado adjunto (png, jpg, jpeg, pdf, máx. 5 MB), y/o
 *   - un texto justificativo (mín. 10 caracteres).
 */
class JustificationController extends Controller
{
    public const MIN_REASON_LENGTH = JustificationRequest::MIN_REASON_LENGTH;

    public function __construct(
        private readonly MonthlyRegister $register,
        private readonly SchoolCalendar $calendar,
    ) {
    }

    public function store(JustificationRequest $request, string $activity, int $course): JsonResponse
    {
        $activity = Scope::resolveSlug($activity) ?? abort(404);
        $course = Course::findOrFail($course);
        $data = $request->validated();

        $date = Carbon::createFromFormat('Y-m-d', $data['date'])->startOfDay();
        $student = Student::where('course_id', $course->id)->find($data['student_id']);

        if (! $student || $student->status !== StudentStatus::Activo) {
            throw ValidationException::withMessages(['student_id' => 'El alumno no pertenece al curso o no se encuentra activo.']);
        }

        $attendance = Attendance::query()
            ->where('course_id', $course->id)
            ->where('student_id', $student->id)
            ->where('activity_type', $activity->value)
            ->where('date', $date->toDateString())
            ->with('justification')
            ->first();

        $createIfMissing = $request->boolean('create_absence_if_missing');

        if (! $attendance && ! $createIfMissing) {
            throw ValidationException::withMessages(['date' => 'Primero registrá la inasistencia de ese día; después podés justificarla.']);
        }

        if (! $attendance && $createIfMissing) {
            if ($date->gt(now()->startOfDay()) || ! $this->calendar->isWorkingDay($date)) {
                throw ValidationException::withMessages(['date' => 'No se puede registrar una inasistencia justificada en día futuro o inhábil.']);
            }
        }

        if ($attendance && ! $attendance->status->canBeJustified()) {
            throw ValidationException::withMessages(['date' => 'Un alumno presente no tiene inasistencia para justificar.']);
        }

        $existing = $attendance?->justification;
        $reason = $request->normalizedReason();
        $newFile = $request->file('certificate');
        $keepsExistingFile = $existing?->hasCertificate() && ! $request->boolean('remove_certificate');

        // --- Regla crítica en backend: al menos un respaldo válido ------------------------
        if ($reason === null && ! $newFile && ! $keepsExistingFile) {
            throw ValidationException::withMessages([
                'reason_text' => 'Para justificar tenés que adjuntar el certificado (PNG, JPG o PDF) o escribir un texto justificativo. No se puede guardar vacío.',
            ]);
        }

        if ($reason !== null && mb_strlen($reason) < self::MIN_REASON_LENGTH) {
            throw ValidationException::withMessages([
                'reason_text' => 'El texto justificativo debe ser una explicación de al menos '.self::MIN_REASON_LENGTH.' caracteres.',
            ]);
        }

        $storedPath = null;
        $oldPath = $existing?->certificate_path;

        try {
            DB::transaction(function () use ($request, &$attendance, $student, $course, $activity, $existing, $reason, $newFile, $keepsExistingFile, $date, &$storedPath) {
                $path = $keepsExistingFile ? $existing->certificate_path : null;

                if ($newFile) {
                    $folder = 'certificados/'.$date->format('Y/m');
                    $path = $storedPath = $newFile->store($folder, 'local');

                    // Espejo en `storage/app/public/certificados` (enlazado vía `php artisan storage:link`).
                    Storage::disk('public')->put($path, Storage::disk('local')->get($path));
                }

                if (! $attendance) {
                    $attendance = Attendance::create([
                        'student_id' => $student->id,
                        'course_id' => $course->id,
                        'user_id' => $request->user()->id,
                        'date' => $date->toDateString(),
                        'activity_type' => $activity->value,
                        'status' => AttendanceStatus::Justificada->value,
                        'absence_value' => 1.00,
                    ]);
                } else {
                    $attendance->status = AttendanceStatus::Justificada;
                    $attendance->user_id = $request->user()->id;
                    $attendance->save();
                }

                Justification::updateOrCreate(
                    ['attendance_id' => $attendance->id],
                    [
                        'justified_by' => $request->user()->id,
                        'reason_text' => $reason,
                        'certificate_path' => $path,
                        'justified_at' => now(),
                    ]
                );
            });
        } catch (\Throwable $e) {
            if ($storedPath) {
                Storage::disk('local')->delete($storedPath);
                Storage::disk('public')->delete($storedPath);
            }
            throw $e;
        }

        // Si se reemplazó o quitó el archivo anterior, se limpia de ambos discos.
        if ($oldPath && $oldPath !== $attendance->fresh()->justification?->certificate_path) {
            Storage::disk('local')->delete($oldPath);
            Storage::disk('public')->delete($oldPath);
        }

        $freshJustification = $attendance->fresh()->load('justification.justifiedBy')->justification;

        return response()->json([
            'message' => 'Inasistencia justificada.',
            'register' => $this->register->build($course, $activity, $date),
            'attendance' => [
                'id' => $attendance->id,
                'student_id' => $student->id,
                'status' => AttendanceStatus::Justificada->value,
                'reason_text' => $freshJustification?->reason_text,
                'has_certificate' => (bool) $freshJustification?->hasCertificate(),
                'certificate_url' => $freshJustification?->hasCertificate()
                    ? route('attendance.certificate', ['activity' => $activity->value, 'course' => $course->id, 'attendance' => $attendance->id])
                    : null,
            ],
        ]);
    }

    /** Certificado de una justificación, accesible para quien administra ese curso y actividad. */
    public function certificate(Request $request, string $activity, int $course, int $attendance)
    {
        $activity = Scope::resolveSlug($activity) ?? abort(404);

        $attendance = Attendance::where('course_id', $course)
            ->where('activity_type', $activity->value)
            ->with('justification')
            ->findOrFail($attendance);

        abort_unless($attendance->justification, 404);

        return CertificateResponse::for($attendance->justification);
    }
}
