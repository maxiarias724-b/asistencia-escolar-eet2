<?php

namespace App\Http\Requests;

use Illuminate\Foundation\Http\FormRequest;

class JustificationRequest extends FormRequest
{
    public const MIN_REASON_LENGTH = 10;

    public function authorize(): bool
    {
        return $this->user() !== null;
    }

    public function rules(): array
    {
        return [
            'student_id' => ['required', 'integer'],
            'date' => ['required', 'date_format:Y-m-d'],
            'reason_text' => ['nullable', 'string', 'max:2000'],
            'certificate' => ['nullable', 'file', 'mimes:png,jpg,jpeg,pdf', 'max:5120'],
            'remove_certificate' => ['nullable', 'boolean'],
            'create_absence_if_missing' => ['nullable', 'boolean'],
        ];
    }

    public function messages(): array
    {
        return [
            'certificate.mimes' => 'El certificado debe ser una imagen PNG/JPG/JPEG o un PDF.',
            'certificate.max' => 'El certificado no puede superar los 5 MB.',
            'certificate.file' => 'El certificado no se pudo subir correctamente.',
            'reason_text.max' => 'El texto justificativo no puede superar los 2000 caracteres.',
        ];
    }

    public function normalizedReason(): ?string
    {
        $reason = trim((string) $this->input('reason_text', ''));

        return $reason === '' ? null : $reason;
    }
}
