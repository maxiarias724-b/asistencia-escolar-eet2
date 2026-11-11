<?php

namespace App\Http\Requests;

use App\Models\DisciplinaryRecord;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Support\Facades\Gate;

class DisciplinaryRecordRequest extends FormRequest
{
    public function authorize(): bool
    {
        $user = $this->user();
        if (! $user) {
            return false;
        }

        $record = $this->route('disciplinaryRecord');
        if ($record instanceof DisciplinaryRecord) {
            return Gate::forUser($user)->allows('update', $record);
        }

        return Gate::forUser($user)->allows('create', DisciplinaryRecord::class);
    }

    /**
     * @return array<string, mixed>
     */
    public function rules(): array
    {
        return [
            'student_id' => ['required', 'integer', 'exists:students,id'],
            'date' => ['required', 'date', 'before_or_equal:today'],
            'amount' => ['required', 'integer', 'min:1', 'max:25'],
            'reason' => ['required', 'string', 'min:5', 'max:2000'],
            'active' => ['nullable', 'boolean'],
        ];
    }

    /**
     * @return array<string, string>
     */
    public function attributes(): array
    {
        return [
            'student_id' => 'estudiante',
            'date' => 'fecha de la sanción',
            'amount' => 'cantidad de amonestaciones',
            'reason' => 'motivo de la sanción',
            'active' => 'estado del registro',
        ];
    }

    /**
     * @return array<string, string>
     */
    public function messages(): array
    {
        return [
            'date.before_or_equal' => 'La fecha de la amonestación no puede ser futura.',
            'amount.min' => 'La cantidad mínima de amonestaciones a aplicar es 1.',
            'amount.max' => 'La cantidad máxima por sanción no puede superar 25 amonestaciones.',
            'reason.min' => 'Describa el motivo de la sanción con al menos 5 caracteres.',
        ];
    }
}
