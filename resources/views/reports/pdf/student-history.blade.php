<!DOCTYPE html>
<html lang="es">
<head>
    <meta charset="utf-8">
    <title>Historial de Asistencia · {{ $student->full_name }}</title>
    <style>
        @page { margin: 14mm 12mm; }
        body { font-family: DejaVu Sans, sans-serif; font-size: 9px; color: #1e293b; }
        .header { border-bottom: 2px solid #0f172a; padding-bottom: 6px; margin-bottom: 10px; }
        .school { font-size: 11px; font-weight: bold; text-transform: uppercase; color: #0f172a; }
        .subtitle { font-size: 10px; font-weight: bold; margin-top: 2px; }
        .meta { font-size: 8px; color: #475569; margin-top: 3px; }
        .box { border: 1px solid #94a3b8; background: #f8fafc; padding: 6px 8px; margin-bottom: 10px; }
        .section-title { font-size: 9px; font-weight: bold; text-transform: uppercase; margin: 10px 0 4px 0; color: #0f172a; }
        table { width: 100%; border-collapse: collapse; margin-bottom: 8px; }
        th, td { border: 1px solid #94a3b8; padding: 3.5px 5px; vertical-align: top; }
        th { background-color: #e2e8f0; font-weight: bold; text-transform: uppercase; font-size: 8px; text-align: left; }
        .center { text-align: center; }
        .right { text-align: right; }
        .signatures { margin-top: 24px; width: 100%; border: none; }
        .signatures td { border: none; text-align: center; padding-top: 24px; width: 50%; }
        .sig-line { border-top: 1px solid #334155; width: 170px; margin: 0 auto; padding-top: 3px; font-size: 8px; font-weight: bold; }
    </style>
</head>
<body>
    <div class="header">
        <div class="school">ESCUELA DE EDUCACIÓN TÉCNICA N° 2 «PROF. JESÚS RAÚL SALAZAR» — SAN SALVADOR DE JUJUY</div>
        <div class="subtitle">FICHA DE HISTORIAL INDIVIDUAL DE ASISTENCIA DEL ESTUDIANTE</div>
        <div class="meta">
            Período: <strong>{{ \Carbon\Carbon::parse($from)->format('d/m/Y') }}</strong> al
            <strong>{{ \Carbon\Carbon::parse($to)->format('d/m/Y') }}</strong>
            &nbsp;|&nbsp; Emitido: {{ $generatedAt->format('d/m/Y H:i') }}
        </div>
    </div>

    <div class="box">
        <strong>Estudiante:</strong> {{ $student->full_name }} &nbsp;|&nbsp;
        <strong>DNI:</strong> {{ $student->dni ?: 'S/D' }} &nbsp;|&nbsp;
        <strong>Legajo:</strong> {{ $student->file_number ?: 'S/L' }} &nbsp;|&nbsp;
        <strong>Curso:</strong> {{ $student->course?->full_name ?: '-' }} &nbsp;|&nbsp;
        <strong>Estado:</strong> {{ $student->active && $student->status->value === 'activo' ? 'ACTIVO' : 'INACTIVO ('.$student->status->label().')' }}
    </div>

    <table>
        <thead>
            <tr>
                <th class="center">Registros</th>
                <th class="center">Presentes (P)</th>
                <th class="center">% Presencia</th>
                <th class="center">Injustificadas</th>
                <th class="center">Justificadas (IJ)</th>
                <th class="center">Total Faltas</th>
                <th class="center">Tardanzas (T)</th>
            </tr>
        </thead>
        <tbody>
            <tr>
                <td class="center">{{ $summary['total_records'] }}</td>
                <td class="center">{{ $summary['present_count'] }}</td>
                <td class="center">{{ $summary['percent_present'] }}%</td>
                <td class="center">{{ number_format($summary['unjustified_sum'], 2) }}</td>
                <td class="center">{{ number_format($summary['justified_sum'], 2) }}</td>
                <td class="center"><strong>{{ number_format($summary['total_absences'], 2) }}</strong></td>
                <td class="center">{{ $summary['tardanzas_count'] }}</td>
            </tr>
        </tbody>
    </table>

    @if (! empty($by_scope))
        <div class="section-title">Resumen por Ámbito</div>
        <table>
            <thead>
                <tr>
                    <th>Ámbito</th>
                    <th class="right">Registros</th>
                    <th class="right">Presentes</th>
                    <th class="right">Injustificadas</th>
                    <th class="right">Justificadas</th>
                    <th class="right">Total</th>
                </tr>
            </thead>
            <tbody>
                @foreach ($by_scope as $scopeName => $info)
                    <tr>
                        <td>{{ $scopeName }}</td>
                        <td class="right">{{ $info['total_records'] }}</td>
                        <td class="right">{{ $info['present'] }}</td>
                        <td class="right">{{ number_format($info['unjustified'], 2) }}</td>
                        <td class="right">{{ number_format($info['justified'], 2) }}</td>
                        <td class="right"><strong>{{ number_format($info['justified'] + $info['unjustified'], 2) }}</strong></td>
                    </tr>
                @endforeach
            </tbody>
        </table>
    @endif

    <div class="section-title">Detalle Cronológico</div>
    <table>
        <thead>
            <tr>
                <th style="width: 60px;">Fecha</th>
                <th style="width: 85px;">Ámbito</th>
                <th class="center" style="width: 72px;">Estado</th>
                <th class="right" style="width: 42px;">Valor</th>
                <th>Observación / Motivo de Justificación</th>
                <th style="width: 90px;">Cargado por</th>
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
                    <td>{{ $a->scopeRef?->name ?? $a->activity_type->label() }}</td>
                    <td class="center"><strong>{{ $sigla }}</strong> ({{ $a->status->label() }})</td>
                    <td class="right">{{ number_format((float) $a->absence_value, 2) }}</td>
                    <td>
                        {{ $a->observation }}
                        @if ($a->justification)
                            {{ $a->observation ? ' · ' : '' }}Justif.: {{ $a->justification->reason_text ?: 'Certificado adjunto' }}
                        @endif
                    </td>
                    <td>{{ $a->user?->name ?? 'Sistema' }}</td>
                </tr>
            @empty
                <tr>
                    <td colspan="6" class="center">Sin registros de asistencia en el período consultado.</td>
                </tr>
            @endforelse
        </tbody>
    </table>

    <table class="signatures">
        <tr>
            <td><div class="sig-line">FIRMA PRECEPTOR / TUTOR</div></td>
            <td><div class="sig-line">V° B° DIRECCIÓN</div></td>
        </tr>
    </table>
</body>
</html>
