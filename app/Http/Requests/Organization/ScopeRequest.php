<?php

namespace App\Http\Requests\Organization;

use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Support\Str;
use Illuminate\Validation\Rule;

class ScopeRequest extends FormRequest
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
        $scope = $this->route('scope');

        return [
            'name' => ['required', 'string', 'max:100', Rule::unique('scopes', 'name')->ignore($scope)],
            'slug' => ['required', 'string', 'max:50', 'regex:/^[a-z0-9_]+$/', Rule::unique('scopes', 'slug')->ignore($scope)],
            'active' => ['sometimes', 'boolean'],
        ];
    }

    public function messages(): array
    {
        return [
            'name.required' => 'El nombre del ámbito es obligatorio.',
            'name.unique' => 'Ya existe un ámbito con ese nombre.',
            'slug.unique' => 'El código interno (slug) del ámbito ya existe.',
        ];
    }
}
