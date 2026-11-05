<?php

namespace App\Http\Requests\Attendance;

use App\Enums\AttendanceStatus;
use App\Models\Attendance;
use App\Models\Scope;
use App\Services\SchoolCalendar;
use Carbon\Carbon;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;
use Illuminate\Validation\Validator;

class DailyStudentAttendanceRequest extends FormRequest
{
    public function authorize(): bool
    {
        $user = $this->user();

        return $user !== null && ($user->isDirector() || $user->isOperador());
    }

    public function rules(): array
    {
        return [
            'date' => ['required', 'date_format:Y-m-d'],
            'shift_id' => ['nullable', 'integer', 'exists:shifts,id'],
            'scope_id' => ['required', 'integer', 'exists:scopes,id'],
            'course_id' => ['required', 'integer', 'exists:courses,id'],
            'activity_id' => ['nullable', 'integer', 'exists:activities,id'],
            'records' => ['required', 'array', 'min:1'],
            'records.*.student_id' => ['required', 'integer', 'exists:students,id'],
            'records.*.status' => ['required', Rule::in(array_column(AttendanceStatus::cases(), 'value'))],
            'records.*.observation' => ['nullable', 'string', 'max:1000'],
        ];
    }

    public function after(): array
    {
        return [
            function (Validator $validator) {
                if ($validator->errors()->isNotEmpty()) {
                    return;
                }

                $date = Carbon::createFromFormat('Y-m-d', (string) $this->input('date'))->startOfDay();
                if ($date->gt(now()->startOfDay())) {
                    $validator->errors()->add('date', 'No se puede cargar asistencia en una fecha futura.');

                    return;
                }

                if (! app(SchoolCalendar::class)->isWorkingDay($date)) {
                    $validator->errors()->add('date', 'Ese día no es hábil (fin de semana, feriado o día sin actividad).');

                    return;
                }

                $scope = Scope::find((int) $this->input('scope_id'));
                if (! $scope) {
                    return;
                }

                // Bloqueo estricto en backend: prohibido guardar `justificada` sin respaldo en `justifications`.
                foreach ($this->input('records', []) as $idx => $rec) {
                    if (($rec['status'] ?? null) === AttendanceStatus::Justificada->value) {
                        $hasSupport = Attendance::query()
                            ->where('student_id', (int) $rec['student_id'])
                            ->where('course_id', (int) $this->input('course_id'))
                            ->where('activity_type', $scope->slug)
                            ->where('date', $date->toDateString())
                            ->whereHas('justification')
                            ->exists();

                        if (! $hasSupport) {
                            $validator->errors()->add(
                                "records.{$idx}.status",
                                'No se puede guardar «Inasistencia Justificada (IJ)» sin respaldo. Usá el modal obligatorio «Justificar» adjuntando certificado o motivo (mín. 10 caracteres).'
                            );
                        }
                    }
                }
            },
        ];
    }

    public function messages(): array
    {
        return [
            'date.required' => 'Seleccioná la fecha de toma de asistencia.',
            'scope_id.required' => 'Seleccioná el ámbito de asistencia.',
            'course_id.required' => 'Seleccioná el curso y división.',
            'records.required' => 'No se recibieron registros de estudiantes para guardar.',
        ];
    }
}
