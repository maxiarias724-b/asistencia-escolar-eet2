<?php

namespace App\Http\Requests\Organization;

use App\Models\Shift;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\ValidationException;

class CourseRequest extends FormRequest
{
    public function authorize(): bool
    {
        return $this->user()?->isDirector() ?? false;
    }

    protected function prepareForValidation(): void
    {
        if ($this->filled('division')) {
            $this->merge(['division' => trim((string) $this->input('division'))]);
        }
    }

    public function rules(): array
    {
        return [
            'year' => ['required', 'integer', 'between:1,7'],
            'division' => ['required', 'string', 'max:10', 'regex:/^[1-9][0-9]*$/'],
            'specialty_id' => ['required', 'integer', 'exists:specialties,id'],
            'shift_id' => ['required', 'integer', 'exists:shifts,id'],
            'active' => ['sometimes', 'boolean'],
        ];
    }

    public function messages(): array
    {
        return [
            'year.between' => 'El año debe estar entre 1° y 7°.',
            'division.required' => 'Indicá el número de división (ej.: 1, 2, 3).',
            'division.regex' => 'La división debe ser numérica (ej.: 1, 2, 3).',
            'specialty_id.required' => 'Seleccioná la especialidad del curso.',
            'shift_id.required' => 'Seleccioná el turno del curso.',
        ];
    }

    public function ensureUniqueCombination(?\App\Models\Course $ignoring = null): Shift
    {
        $shift = Shift::findOrFail((int) $this->validated('shift_id'));

        $exists = \App\Models\Course::query()
            ->where('year', (int) $this->validated('year'))
            ->where('division', (string) $this->validated('division'))
            ->where(fn ($q) => $q->where('shift_id', $shift->id)->orWhere('shift', $shift->slug))
            ->when($ignoring, fn ($q) => $q->where('id', '!=', $ignoring->id))
            ->exists();

        if ($exists) {
            throw ValidationException::withMessages([
                'division' => "Ya existe el curso {$this->validated('year')}° {$this->validated('division')}° en el turno {$shift->name}.",
            ]);
        }

        return $shift;
    }
}
