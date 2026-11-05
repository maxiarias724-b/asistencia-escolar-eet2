<?php

namespace App\Http\Requests\Organization;

use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Support\Str;
use Illuminate\Validation\Rule;

class ShiftRequest extends FormRequest
{
    public function authorize(): bool
    {
        return $this->user()?->isDirector() ?? false;
    }

    protected function prepareForValidation(): void
    {
        $slug = $this->filled('slug')
            ? Str::slug($this->string('slug')->toString(), '_')
            : Str::slug($this->string('name')->toString(), '_');

        $this->merge(['slug' => $slug]);
    }

    public function rules(): array
    {
        $shift = $this->route('shift');

        return [
            'name' => ['required', 'string', 'max:80', Rule::unique('shifts', 'name')->ignore($shift)],
            'slug' => ['required', 'string', 'max:40', 'regex:/^[a-z0-9_]+$/', Rule::unique('shifts', 'slug')->ignore($shift)],
            'active' => ['sometimes', 'boolean'],
        ];
    }

    public function messages(): array
    {
        return [
            'name.required' => 'El nombre del turno es obligatorio.',
            'name.unique' => 'Ya existe un turno con ese nombre.',
            'slug.unique' => 'El identificador del turno ya está en uso.',
        ];
    }
}
