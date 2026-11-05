<?php

namespace App\Http\Requests\People;

use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class TeacherRequest extends FormRequest
{
    public function authorize(): bool
    {
        return $this->user()?->isDirector() ?? false;
    }

    protected function prepareForValidation(): void
    {
        $dni = preg_replace('/\D+/', '', (string) $this->input('dni')) ?? '';
        $cuilDigits = preg_replace('/\D+/', '', (string) $this->input('cuil')) ?? '';

        $cuilFormatted = strlen($cuilDigits) === 11
            ? substr($cuilDigits, 0, 2).'-'.substr($cuilDigits, 2, 8).'-'.substr($cuilDigits, 10, 1)
            : ($cuilDigits !== '' ? $cuilDigits : null);

        $this->merge([
            'dni' => $dni !== '' ? $dni : null,
            'cuil_digits' => $cuilDigits !== '' ? $cuilDigits : null,
            'cuil' => $cuilFormatted,
        ]);
    }

    public function rules(): array
    {
        $teacher = $this->route('teacher');

        return [
            'dni' => ['required', 'digits_between:7,9', Rule::unique('teachers', 'dni')->ignore($teacher)],
            'cuil_digits' => ['nullable', 'digits:11'],
            'cuil' => ['nullable', 'string', 'max:14', Rule::unique('teachers', 'cuil')->ignore($teacher)],
            'file_number' => ['nullable', 'string', 'max:50'],
            'last_name' => ['required', 'string', 'max:120'],
            'first_name' => ['required', 'string', 'max:120'],
            'email' => ['nullable', 'email', 'max:255'],
            'phone' => ['nullable', 'string', 'max:50'],
            'specialty_area' => ['nullable', 'string', 'max:150'],
            'active' => ['sometimes', 'boolean'],
        ];
    }

    public function messages(): array
    {
        return [
            'dni.required' => 'El DNI del docente es obligatorio.',
            'dni.digits_between' => 'El DNI debe contener entre 7 y 9 dígitos.',
            'dni.unique' => 'Ya existe un docente registrado con ese DNI.',
            'cuil_digits.digits' => 'El CUIL debe contener exactamente 11 dígitos.',
            'cuil.unique' => 'Ya existe un docente registrado con ese CUIL.',
            'last_name.required' => 'El apellido del docente es obligatorio.',
            'first_name.required' => 'El nombre del docente es obligatorio.',
            'email.email' => 'El correo electrónico ingresado no es válido.',
        ];
    }
}
