<?php

namespace App\Http\Requests\People;

use App\Enums\StudentStatus;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class StudentRequest extends FormRequest
{
    public function authorize(): bool
    {
        $user = $this->user();

        return $user !== null && ($user->isDirector() || $user->isOperador());
    }

    protected function prepareForValidation(): void
    {
        $dni = preg_replace('/\D+/', '', (string) $this->input('dni')) ?? '';
        $cuilDigits = preg_replace('/\D+/', '', (string) $this->input('cuil')) ?? '';

        $cuilFormatted = strlen($cuilDigits) === 11
            ? substr($cuilDigits, 0, 2).'-'.substr($cuilDigits, 2, 8).'-'.substr($cuilDigits, 10, 1)
            : ($cuilDigits !== '' ? $cuilDigits : null);

        $statusInput = $this->input('status', StudentStatus::Activo->value);
        $activeInput = $this->has('active') ? $this->boolean('active') : ($statusInput === StudentStatus::Activo->value);

        if (! $activeInput && $statusInput === StudentStatus::Activo->value) {
            $statusInput = StudentStatus::Baja->value;
        } elseif ($activeInput && ! $this->filled('status')) {
            $statusInput = StudentStatus::Activo->value;
        }

        $this->merge([
            'dni' => $dni !== '' ? $dni : null,
            'cuil_digits' => $cuilDigits !== '' ? $cuilDigits : null,
            'cuil' => $cuilFormatted,
            'status' => $statusInput,
            'active' => $statusInput === StudentStatus::Activo->value,
        ]);
    }

    public function rules(): array
    {
        $student = $this->route('student');

        return [
            'dni' => ['required', 'digits_between:7,9', Rule::unique('students', 'dni')->ignore($student)],
            'cuil_digits' => ['nullable', 'digits:11'],
            'cuil' => ['nullable', 'string', 'max:14', Rule::unique('students', 'cuil')->ignore($student)],
            'file_number' => ['nullable', 'string', 'max:50'],
            'sinide_code' => ['nullable', 'string', 'max:50'],
            'last_name' => ['required', 'string', 'max:255'],
            'first_name' => ['required', 'string', 'max:255'],
            'birth_date' => ['nullable', 'date', 'before:today'],
            'course_id' => ['required', 'integer', 'exists:courses,id'],
            'status' => ['required', Rule::enum(StudentStatus::class)],
            'active' => ['boolean'],
        ];
    }

    public function messages(): array
    {
        return [
            'dni.required' => 'El DNI del estudiante es obligatorio.',
            'dni.digits_between' => 'El DNI debe contener entre 7 y 9 dígitos numéricos.',
            'dni.unique' => 'Ya existe un estudiante registrado con ese DNI.',
            'cuil_digits.digits' => 'El CUIL debe contener exactamente 11 dígitos.',
            'cuil.unique' => 'Ya existe un estudiante registrado con ese CUIL.',
            'last_name.required' => 'El apellido es obligatorio.',
            'first_name.required' => 'El nombre es obligatorio.',
            'birth_date.before' => 'La fecha de nacimiento debe ser anterior a hoy.',
            'course_id.required' => 'Seleccioná el curso y división del estudiante.',
            'course_id.exists' => 'El curso seleccionado no existe.',
        ];
    }
}
