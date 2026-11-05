<?php

namespace App\Http\Requests;

use App\Enums\AttendanceStatus;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class StoreAttendanceRequest extends FormRequest
{
    public function authorize(): bool
    {
        return $this->user() !== null;
    }

    public function rules(): array
    {
        return [
            'student_id' => ['required', 'integer'],
            'date' => ['required', 'date_format:Y-m-d'],
            'status' => ['required', Rule::in(array_column(AttendanceStatus::cases(), 'value'))],
            'observation' => ['nullable', 'string', 'max:1000'],
            'confirm_unjustify' => ['nullable', 'boolean'],
        ];
    }
}
