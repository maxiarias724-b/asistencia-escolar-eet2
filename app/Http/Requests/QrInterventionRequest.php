<?php

namespace App\Http\Requests;

use App\Enums\QrInterventionStatus;
use App\Models\QrIntervention;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Support\Facades\Gate;
use Illuminate\Validation\Rule;

class QrInterventionRequest extends FormRequest
{
    public function authorize(): bool
    {
        $user = $this->user();
        if (! $user) {
            return false;
        }

        $intervention = $this->route('qrIntervention');
        if ($intervention instanceof QrIntervention) {
            return Gate::forUser($user)->allows('update', $intervention);
        }

        return Gate::forUser($user)->allows('create', QrIntervention::class);
    }

    /**
     * @return array<string, mixed>
     */
    public function rules(): array
    {
        return [
            'student_id' => ['required', 'integer', 'exists:students,id'],
            'detection_date' => ['required', 'date', 'before_or_equal:today'],
            'consecutive_absences_flag' => ['required', 'integer', 'min:1', 'max:365'],
            'territorial_team_contact' => ['nullable', 'string', 'max:255'],
            'home_visit_date' => ['nullable', 'date'],
            'pedagogical_support_details' => ['nullable', 'string', 'max:4000'],
            'status' => ['required', Rule::enum(QrInterventionStatus::class)],
        ];
    }

    /**
     * @return array<string, string>
     */
    public function attributes(): array
    {
        return [
            'student_id' => 'estudiante',
            'detection_date' => 'fecha de detección',
            'consecutive_absences_flag' => 'cantidad de faltas detonantes',
            'territorial_team_contact' => 'referente del equipo territorial',
            'home_visit_date' => 'fecha de visita domiciliaria',
            'pedagogical_support_details' => 'acciones de apoyo pedagógico',
            'status' => 'estado del expediente',
        ];
    }

    /**
     * @return array<string, string>
     */
    public function messages(): array
    {
        return [
            'detection_date.before_or_equal' => 'La fecha de detección del caso no puede ser futura.',
            'consecutive_absences_flag.min' => 'Indique al menos 1 inasistencia como detonante de la alerta.',
        ];
    }
}
