<?php

namespace App\Support;

use App\Models\Justification;
use Illuminate\Support\Facades\Storage;
use Symfony\Component\HttpFoundation\Response;

/** Sirve un certificado médico/justificativo validando MIME y permisos del solicitante. */
class CertificateResponse
{
    public const ALLOWED_MIMES = ['image/png', 'image/jpeg', 'application/pdf'];

    public static function for(Justification $justification): Response
    {
        abort_unless($justification->certificateExists(), 404, 'El certificado no existe.');

        $path = $justification->certificate_path;
        $disk = Storage::disk('local')->exists($path) ? 'local' : 'public';
        $mime = Storage::disk($disk)->mimeType($path) ?: 'application/octet-stream';

        // Defensa en profundidad: solo se muestran inline los tipos permitidos.
        abort_unless(in_array($mime, self::ALLOWED_MIMES, true), 415);

        return Storage::disk($disk)->response($path, basename($path), [
            'Content-Type' => $mime,
            'X-Content-Type-Options' => 'nosniff',
        ]);
    }
}
