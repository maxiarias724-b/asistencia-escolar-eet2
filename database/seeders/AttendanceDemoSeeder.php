<?php

namespace Database\Seeders;

use App\Enums\AttendanceStatus;
use App\Models\Activity;
use App\Models\Attendance;
use App\Models\Course;
use App\Models\Justification;
use App\Models\Scope;
use App\Models\Shift;
use App\Models\Teacher;
use App\Models\TeacherAttendance;
use App\Models\User;
use App\Services\SchoolCalendar;
use Carbon\CarbonPeriod;
use Illuminate\Database\Seeder;
use Illuminate\Support\Facades\Storage;

class AttendanceDemoSeeder extends Seeder
{
    private const DEMO_PNG = 'iVBORw0KGgoAAAANSUhEUgAAAAEAAAABCAYAAAAfFcSJAAAADUlEQVR42mP8z8BQDwAEhQGAhKmMIQAAAABJRU5ErkJggg==';

    /**
     * Carga asistencias realistas del mes en curso (días hábiles hasta hoy) para:
     *  - 3° 1° · Clases           (por preceptor_clases)
     *  - 5° 1° · Clases           (por preceptor_clases)
     *  - 1° 1° · Educación Física (por profe_edfisica)
     *  - Plantel docente          (por admin)
     */
    public function run(): void
    {
        mt_srand(2026);

        $scopeClases = Scope::where('slug', 'clase')->firstOrFail();
        $scopeEdFisica = Scope::where('slug', 'educacion_fisica')->firstOrFail();

        $preceptor = User::where('username', 'preceptor_clases')->firstOrFail();
        $edFisica = User::where('username', 'profe_edfisica')->firstOrFail();
        $admin = User::where('username', 'admin')->firstOrFail();

        $this->seedCourse(3, '1', $scopeClases, $preceptor);
        $this->seedCourse(5, '1', $scopeClases, $preceptor);
        $this->seedCourse(1, '1', $scopeEdFisica, $edFisica);
        $this->seedTeachers($admin);
    }

    private function seedCourse(int $year, string $division, Scope $scope, User $taker): void
    {
        $course = Course::where('year', $year)->where('division', $division)->firstOrFail();
        $activity = Activity::where('scope_id', $scope->id)->first();
        $students = $course->students()->active()->alphabetical()->get();
        $days = collect(CarbonPeriod::create(now()->startOfMonth(), now()->startOfDay()))
            ->filter(fn ($d) => app(SchoolCalendar::class)->isWorkingDay($d));

        $absences = collect();

        foreach ($days as $day) {
            foreach ($students as $student) {
                $roll = mt_rand(1, 100);
                $status = match (true) {
                    $roll <= 80 => AttendanceStatus::Presente,
                    $roll <= 88 => AttendanceStatus::Ausente,
                    $roll <= 94 => AttendanceStatus::Tardanza,
                    default => AttendanceStatus::MediaFalta,
                };

                $observation = null;
                if ($status === AttendanceStatus::Presente && mt_rand(1, 100) <= 3) {
                    $observation = 'Durante el día presentó dolores de cabeza y se tuvo que retirar a su casa';
                }

                $attendance = Attendance::firstOrCreate(
                    ['student_id' => $student->id, 'date' => $day->toDateString(), 'activity_type' => $scope->slug],
                    [
                        'course_id' => $course->id,
                        'scope_id' => $scope->id,
                        'activity_id' => $activity?->id,
                        'shift_id' => $course->shift_id,
                        'user_id' => $taker->id,
                        'status' => $status->value,
                        'observation' => $observation,
                    ]
                );

                if ($status === AttendanceStatus::Ausente) {
                    $absences->push($attendance);
                }
            }
        }

        $this->justifyDemo($absences, $taker);
    }

    private function seedTeachers(User $recorder): void
    {
        $shift = Shift::where('slug', 'manana')->firstOrFail();
        $activity = Activity::first();
        $teachers = Teacher::active()->alphabetical()->get();
        $days = collect(CarbonPeriod::create(now()->startOfMonth(), now()->startOfDay()))
            ->filter(fn ($d) => app(SchoolCalendar::class)->isWorkingDay($d))
            ->take(5);

        foreach ($days as $day) {
            foreach ($teachers as $teacher) {
                $roll = mt_rand(1, 100);
                $status = match (true) {
                    $roll <= 85 => AttendanceStatus::Presente,
                    $roll <= 93 => AttendanceStatus::Tardanza,
                    default => AttendanceStatus::Ausente,
                };

                TeacherAttendance::firstOrCreate(
                    [
                        'teacher_id' => $teacher->id,
                        'date' => $day->toDateString(),
                        'shift_id' => $shift->id,
                    ],
                    [
                        'activity_id' => $activity?->id,
                        'recorded_by' => $recorder->id,
                        'status' => $status->value,
                        'observation' => $status === AttendanceStatus::Tardanza ? 'Ingresó 15 minutos tarde por corte de ruta' : null,
                    ]
                );
            }
        }
    }

    private function justifyDemo($absences, User $by): void
    {
        $withText = $absences->get(0);
        $withFile = $absences->get(1);

        if ($withText) {
            $withText->update([
                'status' => AttendanceStatus::Justificada->value,
                'observation' => 'Tuvo un accidente',
            ]);
            Justification::updateOrCreate(['attendance_id' => $withText->id], [
                'justified_by' => $by->id,
                'reason_text' => 'El alumno tuvo un viaje imprevisto por cuestiones familiares.',
                'certificate_path' => null,
                'justified_at' => now()->subDays(2),
            ]);
        }

        if ($withFile) {
            $path = "certificados/demo-{$withFile->id}.png";
            Storage::disk('local')->put($path, base64_decode(self::DEMO_PNG));

            $withFile->update(['status' => AttendanceStatus::Justificada->value]);
            Justification::updateOrCreate(['attendance_id' => $withFile->id], [
                'justified_by' => $by->id,
                'reason_text' => null,
                'certificate_path' => $path,
                'justified_at' => now()->subDay(),
            ]);
        }
    }
}
