<?php

namespace App\Http\Requests\Organization;

use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class ActivityRequest extends FormRequest
{
    public function authorize(): bool
    {
        return $this->user()?->isDirector() ?? false;
    }

    public function rules(): array
    {
        $activity = $this->route('activity');

        return [
            'scope_id' => ['required', 'integer', 'exists:scopes,id'],
            'name' => [
                'required', 'string', 'max:150',
                Rule::unique('activities', 'name')
                    ->where('scope_id', $this->input('scope_id'))
                    ->ignore($activity),
            ],
            'active' => ['sometimes', 'boolean'],
        ];
    }

    public function messages(): array
    {
        return [
            'scope_id.required' => 'Seleccioná el ámbito al que pertenece la actividad.',
            'name.required' => 'El nombre de la materia o actividad es obligatorio.',
            'name.unique' => 'Esa actividad ya existe dentro del ámbito elegido.',
        ];
    }
}
