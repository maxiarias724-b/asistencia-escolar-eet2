<?php

namespace App\Http\Requests\Attendance;

use App\Enums\AttendanceStatus;
use App\Services\SchoolCalendar;
use Carbon\Carbon;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;
use Illuminate\Validation\Validator;

class TeacherAttendanceRequest extends FormRequest
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
            'shift_id' => ['required', 'integer', 'exists:shifts,id'],
            'activity_id' => ['nullable', 'integer', 'exists:activities,id'],
            'records' => ['required', 'array', 'min:1'],
            'records.*.teacher_id' => ['required', 'integer', 'exists:teachers,id'],
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
                    $validator->errors()->add('date', 'No se puede registrar asistencia docente en una fecha futura.');

                    return;
                }

                if (! app(SchoolCalendar::class)->isWorkingDay($date)) {
                    $validator->errors()->add('date', 'Ese día no es hábil según el calendario escolar.');

                    return;
                }

                foreach ($this->input('records', []) as $idx => $rec) {
                    if (($rec['status'] ?? null) === AttendanceStatus::Justificada->value) {
                        $obs = trim((string) ($rec['observation'] ?? ''));
                        if (mb_strlen($obs) < 10) {
                            $validator->errors()->add(
                                "records.{$idx}.observation",
                                'Para marcar «Inasistencia Justificada (IJ)» en un docente debés indicar artículo/motivo en Observación (mín. 10 caracteres).'
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
            'date.required' => 'Indicá la fecha de asistencia docente.',
            'shift_id.required' => 'Seleccioná el turno.',
            'records.required' => 'No se recibieron registros de docentes.',
        ];
    }
}
