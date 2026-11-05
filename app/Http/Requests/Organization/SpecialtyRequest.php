<?php

namespace App\Http\Requests\Organization;

use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class SpecialtyRequest extends FormRequest
{
    public function authorize(): bool
    {
        return $this->user()?->isDirector() ?? false;
    }

    public function rules(): array
    {
        $specialty = $this->route('specialty');

        return [
            'name' => ['required', 'string', 'max:120', Rule::unique('specialties', 'name')->ignore($specialty)],
            'code' => ['nullable', 'string', 'max:30'],
            'active' => ['sometimes', 'boolean'],
        ];
    }

    public function messages(): array
    {
        return [
            'name.required' => 'El nombre de la especialidad es obligatorio.',
            'name.unique' => 'Ya existe una especialidad con ese nombre.',
        ];
    }
}
