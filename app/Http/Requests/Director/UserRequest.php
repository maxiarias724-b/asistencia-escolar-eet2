<?php

namespace App\Http\Requests\Director;

use App\Enums\ActivityType;
use App\Enums\Role;
use App\Models\Scope;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;
use Illuminate\Validation\Validator;

class UserRequest extends FormRequest
{
    public function authorize(): bool
    {
        return $this->user()?->isDirector() ?? false;
    }

    protected function prepareForValidation(): void
    {
        // Permite enviar `name` directo o `first_name` + `last_name` separados.
        if (! $this->filled('name') && ($this->filled('first_name') || $this->filled('last_name'))) {
            $this->merge([
                'name' => trim($this->string('first_name')->toString().' '.$this->string('last_name')->toString()),
            ]);
        }

        // Nunca se escala a `director` por formulario: solo `operador` o `docente` (por defecto `operador`).
        $requestedRole = $this->input('account_role');
        $safeRole = in_array($requestedRole, [Role::Operador->value, Role::Docente->value], true)
            ? $requestedRole
            : Role::Operador->value;

        $this->merge(['account_role' => $safeRole]);
    }

    public function rules(): array
    {
        $user = $this->route('user');
        $creating = $user === null;

        return [
            'name' => ['required', 'string', 'max:255'],
            'first_name' => ['nullable', 'string', 'max:120'],
            'last_name' => ['nullable', 'string', 'max:120'],
            'username' => [
                'required', 'string', 'min:4', 'max:50', 'regex:/^[A-Za-z0-9._-]+$/',
                Rule::unique('users', 'username')->ignore($user),
            ],
            'email' => ['nullable', 'email', 'max:255', Rule::unique('users', 'email')->ignore($user)],
            'password' => [$creating ? 'required' : 'nullable', 'string', 'min:8', 'max:100'],
            'account_role' => ['required', Rule::in([Role::Operador->value, Role::Docente->value])],
            'active' => ['sometimes', 'boolean'],
            'assignments' => ['nullable', 'array'],
            'assignments.*' => ['array'],
            'assignments.*.*' => ['integer', 'exists:courses,id'],
        ];
    }

    public function after(): array
    {
        return [
            function (Validator $validator) {
                $dbSlugs = Scope::active()->pluck('slug')->all();
                $valid = array_values(array_unique(array_merge(
                    array_column(ActivityType::cases(), 'value'),
                    $dbSlugs
                )));

                foreach (array_keys($this->input('assignments', [])) as $activity) {
                    if (! in_array($activity, $valid, true)) {
                        $validator->errors()->add('assignments', "Ámbito inválido: {$activity}.");
                    }
                }
            },
        ];
    }

    public function messages(): array
    {
        return [
            'name.required' => 'El nombre es obligatorio.',
            'username.required' => 'El usuario es obligatorio.',
            'username.min' => 'El usuario debe tener al menos 4 caracteres.',
            'username.regex' => 'El usuario solo puede tener letras, números, punto, guion y guion bajo.',
            'username.unique' => 'Ese usuario ya existe.',
            'email.email' => 'El email no es válido.',
            'email.unique' => 'Ese email ya está registrado.',
            'password.required' => 'Definí una contraseña para entregar al usuario.',
            'password.min' => 'La contraseña debe tener al menos 8 caracteres.',
            'assignments.*.*.exists' => 'Se seleccionó un curso inexistente.',
        ];
    }

    /**
     * Pares [course_id, scope_slug] solicitados, sin duplicados.
     * Si el rol elegido es `docente`, no recibe asignaciones operativas de carga.
     *
     * @return array<int, array{0:int,1:string}>
     */
    public function assignmentPairs(): array
    {
        if ($this->input('account_role') === Role::Docente->value) {
            return [];
        }

        $pairs = [];
        foreach ($this->input('assignments', []) as $activity => $courseIds) {
            foreach (array_unique((array) $courseIds) as $courseId) {
                $pairs[] = [(int) $courseId, (string) $activity];
            }
        }

        return $pairs;
    }
}
