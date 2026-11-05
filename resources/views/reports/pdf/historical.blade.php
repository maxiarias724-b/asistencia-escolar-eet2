<!DOCTYPE html>
<html lang="es">
<head>
    <meta charset="utf-8">
    <title>Consulta Histórica de Asistencia · EET N° 2</title>
    <style>
        @page { margin: 12mm 10mm; }
        body { font-family: DejaVu Sans, sans-serif; font-size: 9px; color: #1e293b; }
        .header { border-bottom: 2px solid #0f172a; padding-bottom: 6px; margin-bottom: 10px; }
        .school { font-size: 11px; font-weight: bold; text-transform: uppercase; color: #0f172a; }
        .subtitle { font-size: 10px; font-weight: bold; margin-top: 2px; }
        .meta { font-size: 8px; color: #475569; margin-top: 3px; }
        table { width: 100%; border-collapse: collapse; }
        th, td { border: 1px solid #94a3b8; padding: 3px 5px; vertical-align: top; }
        th { background-color: #e2e8f0; font-weight: bold; text-transform: uppercase; font-size: 8px; text-align: left; }
        .center { text-align: center; }
        .right { text-align: right; }
        .signatures { margin-top: 24px; width: 100%; border: none; }
        .signatures td { border: none; text-align: center; padding-top: 24px; width: 50%; }
        .sig-line { border-top: 1px solid #334155; width: 180px; margin: 0 auto; padding-top: 3px; font-size: 8px; font-weight: bold; }
    </style>
</head>
<body>
    <div class="header">
        <div class="school">ESCUELA DE EDUCACIÓN TÉCNICA N° 2 «PROF. JESÚS RAÚL SALAZAR» — SAN SALVADOR DE JUJUY</div>
        <div class="subtitle">REPORTE DE CONSULTA HISTÓRICA DE ASISTENCIA ESCOLAR</div>
        <div class="meta">
            Período: <strong>{{ \Carbon\Carbon::parse($filters['from'])->format('d/m/Y') }}</strong> al
            <strong>{{ \Carbon\Carbon::parse($filters['to'])->format('d/m/Y') }}</strong>
            &nbsp;|&nbsp; Total exportado: <strong>{{ $records->count() }}</strong> registros
            &nbsp;|&nbsp; Emitido: {{ $generatedAt->format('d/m/Y H:i') }}
        </div>
    </div>

    <table>
        <thead>
            <tr>
                <th style="width: 62px;">Fecha</th>
                <th style="width: 65px;">DNI</th>
                <th>Estudiante</th>
                <th style="width: 75px;">Curso</th>
                <th style="width: 65px;">Turno</th>
                <th style="width: 85px;">Ámbito</th>
                <th class="center" style="width: 65px;">Estado</th>
                <th class="right" style="width: 42px;">Valor</th>
                <th>Observación / Respaldo</th>
                <th style="width: 95px;">Registrado por</th>
            </tr>
        </thead>
        <tbody>
            @forelse ($records as $a)
                @php
                    $sigla = match ($a->status->value) {
                        'presente' => 'P',
                        'ausente' => 'A',
                        'tardanza' => 'T',
                        'media_falta' => 'M',
                        'justificada' => 'IJ',
                        default => '-',
                    };
                @endphp
                <tr>
                    <td>{{ $a->date->format('d/m/Y') }}</td>
                    <td>{{ $a->student?->dni ?: 'S/D' }}</td>
                    <td>{{ $a->student?->full_name ?: '-' }}</td>
                    <td>{{ $a->course?->name ?: '-' }}</td>
                    <td>{{ $a->course?->shiftRef?->name ?? $a->course?->shift?->label() ?? '-' }}</td>
                    <td>{{ $a->scopeRef?->name ?? $a->activity_type->label() }}</td>
                    <td class="center"><strong>{{ $sigla }}</strong> ({{ $a->status->label() }})</td>
                    <td class="right">{{ number_format((float) $a->absence_value, 2) }}</td>
                    <td>
                        {{ $a->observation }}
                        @if ($a->justification)
                            {{ $a->observation ? ' · ' : '' }}Justif.: {{ $a->justification->reason_text ?: 'Certificado médico' }}
                        @endif
                    </td>
                    <td>{{ $a->user?->name ?? 'Sistema' }}</td>
                </tr>
            @empty
                <tr>
                    <td colspan="10" class="center">Sin registros para los filtros seleccionados.</td>
                </tr>
            @endforelse
        </tbody>
    </table>

    <table class="signatures">
        <tr>
            <td><div class="sig-line">FIRMA RESPONSABLE</div></td>
            <td><div class="sig-line">V° B° DIRECCIÓN</div></td>
        </tr>
    </table>
</body>
</html>
