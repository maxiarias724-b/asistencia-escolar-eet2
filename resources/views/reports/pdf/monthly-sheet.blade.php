<!DOCTYPE html>
<html lang="es">
<head>
    <meta charset="utf-8">
    <title>Planilla Mensual · {{ $course->name }} · {{ $sheet['month_label'] }}</title>
    <style>
        @page { margin: 8mm 7mm; }
        body { font-family: DejaVu Sans, sans-serif; font-size: 7.5px; color: #0f172a; }
        .school { font-size: 10px; font-weight: bold; text-transform: uppercase; }
        .subtitle { font-size: 9px; font-weight: bold; text-transform: uppercase; margin-top: 1px; }
        .meta { font-size: 8px; margin: 3px 0 6px 0; }
        table.grid { width: 100%; border-collapse: collapse; }
        table.grid th, table.grid td { border: 1px solid #334155; padding: 1.8px 2px; vertical-align: middle; }
        table.grid th { background-color: #e2e8f0; font-weight: bold; text-align: center; font-size: 7px; }
        .name-col { text-align: left !important; padding-left: 4px !important; width: 135px; }
        .obs-col { text-align: left !important; padding-left: 4px !important; width: 115px; font-size: 6.5px; }
        .day-col { width: 12px; text-align: center; }
        .non-working { background-color: #f1f5f9; color: #64748b; }
        .num-col { width: 28px; text-align: right; padding-right: 3px !important; }
        .footnote { font-size: 7px; color: #475569; margin-top: 4px; }
        table.footer-layout { width: 100%; border-collapse: collapse; margin-top: 8px; }
        table.footer-layout > tbody > tr > td { vertical-align: bottom; border: none; }
        table.summary-box { border-collapse: collapse; width: 185px; font-size: 7.5px; }
        table.summary-box td { border: 1px solid #334155; padding: 2px 5px; }
        .sig-line { border-top: 1px solid #0f172a; width: 145px; margin: 0 auto; padding-top: 3px; text-align: center; font-weight: bold; font-size: 7.5px; }
        .annex-title { font-size: 8px; font-weight: bold; text-transform: uppercase; margin: 10px 0 3px 0; }
        table.annex { width: 100%; border-collapse: collapse; font-size: 7px; }
        table.annex th, table.annex td { border: 1px solid #334155; padding: 2px 4px; text-align: left; vertical-align: top; }
        table.annex th { background-color: #e2e8f0; font-weight: bold; text-transform: uppercase; font-size: 6.8px; }
    </style>
</head>
<body>
    <div class="school">ESCUELA DE EDUCACIÓN TÉCNICA N° 2 «PROF. JESÚS RAÚL SALAZAR»</div>
    <div class="subtitle">
        REGISTRO DE ASISTENCIA MENSUAL CORRESPONDIENTE AL {{ $course->name }}
        · {{ $course->shiftRef?->name ?? $course->shift->label() }} — {{ $scope->name }}
    </div>
    <div class="meta">
        <strong>Mes de {{ $sheet['month_label'] }}</strong> &nbsp;&nbsp;
        Días hábiles: <strong>{{ $sheet['working_days'] }}</strong>
        @if ($sheet['base_days'] !== $sheet['working_days'])
            (computados: {{ $sheet['base_days'] }})
        @endif
        &nbsp;&nbsp;|&nbsp;&nbsp;
        Nomenclatura: <strong>{{ ($sheet['notation'] ?? 'patij') === 'sinide' ? 'SINIDE Oficial (P, C, Q, M, J)' : 'Institucional (P, A, T, M, IJ)' }}</strong>
        &nbsp;&nbsp;|&nbsp;&nbsp; Emitido: {{ $generatedAt->format('d/m/Y H:i') }}
    </div>

    <table class="grid">
        <thead>
            <tr>
                <th rowspan="2" class="name-col">Apellido y Nombre</th>
                @foreach ($sheet['days'] as $d)
                    <th rowspan="2" class="day-col {{ ! $d['working'] ? 'non-working' : '' }}">{{ $d['n'] }}</th>
                @endforeach
                <th rowspan="2" class="num-col">ASIST.</th>
                <th colspan="2">INASIST. DEL MES</th>
                <th rowspan="2" class="num-col">Anterior*</th>
                <th rowspan="2" class="num-col">TOTAL</th>
                <th rowspan="2" class="obs-col">OBSERVACIONES</th>
            </tr>
            <tr>
                <th class="num-col">J.</th>
                <th class="num-col">INJ.</th>
            </tr>
        </thead>
        <tbody>
            @forelse ($sheet['rows'] as $row)
                <tr>
                    <td class="name-col">
                        {{ $row['name'] }}{{ ! $row['active'] ? ' ('.$row['status_label'].')' : '' }}
                    </td>
                    @foreach ($sheet['days'] as $d)
                        @php
                            $cell = $row['cells'][$d['n']] ?? null;
                            $code = ! $d['working'] ? '-' : ($cell['display_code'] ?? '');
                        @endphp
                        <td class="day-col {{ ! $d['working'] ? 'non-working' : '' }}">{{ $code }}</td>
                    @endforeach
                    <td class="num-col">{{ number_format($row['asist'], 2) }}</td>
                    <td class="num-col">{{ number_format($row['j'], 2) }}</td>
                    <td class="num-col">{{ number_format($row['inj'], 2) }}</td>
                    <td class="num-col">{{ number_format($row['anterior'], 2) }}</td>
                    <td class="num-col"><strong>{{ number_format($row['total'], 2) }}</strong></td>
                    <td class="obs-col">{{ $row['observations'] }}</td>
                </tr>
            @empty
                <tr>
                    <td colspan="{{ count($sheet['days']) + 7 }}" style="text-align: center; padding: 10px;">
                        Sin estudiantes registrados para este curso.
                    </td>
                </tr>
            @endforelse
        </tbody>
    </table>

    <div class="footnote">* Inasistencias acumuladas hasta el mes anterior.</div>

    <table class="footer-layout">
        <tbody>
            <tr>
                <td style="width: 210px;">
                    <table class="summary-box">
                        <tr><td>Alumnos/as Inscriptos</td><td style="text-align: right;"><strong>{{ $sheet['footer']['enrolled'] }}</strong></td></tr>
                        <tr><td>Alumnos/as Entrada</td><td style="text-align: right;"><strong>{{ $sheet['footer']['entered'] }}</strong></td></tr>
                        <tr><td>Alumnos/as Salida</td><td style="text-align: right;"><strong>{{ $sheet['footer']['left'] }}</strong></td></tr>
                        <tr><td>Quedan el último día</td><td style="text-align: right;"><strong>{{ $sheet['footer']['remaining'] }}</strong></td></tr>
                    </table>
                </td>
                <td style="width: 210px;">
                    <table class="summary-box">
                        <tr><td>Total de Asistencias</td><td style="text-align: right;"><strong>{{ number_format($sheet['footer']['total_asist'], 2) }}</strong></td></tr>
                        <tr><td>Total de Inasistencias</td><td style="text-align: right;"><strong>{{ number_format($sheet['footer']['total_inasist'], 2) }}</strong></td></tr>
                        <tr><td>Asistencia Media</td><td style="text-align: right;"><strong>{{ number_format($sheet['footer']['media'], 2) }}</strong></td></tr>
                        <tr><td>% de Asistencia</td><td style="text-align: right;"><strong>{{ number_format($sheet['footer']['percent'], 2) }}%</strong></td></tr>
                    </table>
                </td>
                <td style="padding-top: 26px;">
                    <div class="sig-line">FIRMA</div>
                </td>
                <td style="padding-top: 26px;">
                    <div class="sig-line">V° B°</div>
                </td>
            </tr>
        </tbody>
    </table>

    <div class="annex-title">ANEXO INSTITUCIONAL: OBSERVACIONES DOCENTES DE CLASE (INCIDENCIAS POR MATERIA)</div>
    <table class="annex">
        <thead>
            <tr>
                <th style="width: 58px;">Fecha</th>
                <th style="width: 165px;">Alumno</th>
                <th style="width: 135px;">Materia</th>
                <th style="width: 135px;">Docente</th>
                <th>Observación</th>
            </tr>
        </thead>
        <tbody>
            @forelse ($sheet['teacher_observations'] ?? [] as $tObs)
                <tr>
                    <td>{{ $tObs['date_formatted'] }}</td>
                    <td><strong>{{ $tObs['student_name'] }}</strong></td>
                    <td>{{ $tObs['activity_name'] }}</td>
                    <td>{{ $tObs['teacher_name'] }}</td>
                    <td>{{ $tObs['observation_text'] }}</td>
                </tr>
            @empty
                <tr>
                    <td colspan="5" style="text-align: center; color: #64748b;">
                        Sin observaciones docentes registradas en el período.
                    </td>
                </tr>
            @endforelse
        </tbody>
    </table>
</body>
</html>
