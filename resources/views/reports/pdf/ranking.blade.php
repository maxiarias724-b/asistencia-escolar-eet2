<!DOCTYPE html>
<html lang="es">
<head>
    <meta charset="utf-8">
    <title>Ranking de Inasistencias · EET N° 2</title>
    <style>
        @page { margin: 14mm 12mm; }
        body { font-family: DejaVu Sans, sans-serif; font-size: 9px; color: #1e293b; }
        .header { border-bottom: 2px solid #0f172a; padding-bottom: 6px; margin-bottom: 10px; }
        .school { font-size: 11px; font-weight: bold; text-transform: uppercase; color: #0f172a; }
        .subtitle { font-size: 10px; font-weight: bold; margin-top: 2px; }
        .meta { font-size: 8px; color: #475569; margin-top: 3px; }
        table { width: 100%; border-collapse: collapse; }
        th, td { border: 1px solid #94a3b8; padding: 4px 5px; vertical-align: middle; }
        th { background-color: #e2e8f0; font-weight: bold; text-transform: uppercase; font-size: 8px; text-align: left; }
        .center { text-align: center; }
        .right { text-align: right; }
        .signatures { margin-top: 26px; width: 100%; border: none; }
        .signatures td { border: none; text-align: center; padding-top: 24px; width: 50%; }
        .sig-line { border-top: 1px solid #334155; width: 170px; margin: 0 auto; padding-top: 3px; font-size: 8px; font-weight: bold; }
    </style>
</head>
<body>
    <div class="header">
        <div class="school">ESCUELA DE EDUCACIÓN TÉCNICA N° 2 «PROF. JESÚS RAÚL SALAZAR» — SAN SALVADOR DE JUJUY</div>
        <div class="subtitle">RANKING INSTITUCIONAL DE ESTUDIANTES POR INASISTENCIAS ACUMULADAS</div>
        <div class="meta">
            Período evaluado: <strong>{{ \Carbon\Carbon::parse($filters['from'])->format('d/m/Y') }}</strong> al
            <strong>{{ \Carbon\Carbon::parse($filters['to'])->format('d/m/Y') }}</strong>
            &nbsp;|&nbsp; Estudiantes listados: <strong>{{ $ranking->count() }}</strong>
            &nbsp;|&nbsp; Emitido: {{ $generatedAt->format('d/m/Y H:i') }}
        </div>
    </div>

    <table>
        <thead>
            <tr>
                <th class="center" style="width: 24px;">#</th>
                <th style="width: 62px;">DNI</th>
                <th>Apellido y Nombres</th>
                <th style="width: 72px;">Curso</th>
                <th style="width: 60px;">Turno</th>
                <th class="right" style="width: 52px;">Injustif.</th>
                <th class="right" style="width: 50px;">Justif.</th>
                <th class="right" style="width: 52px;">Total</th>
                <th class="center" style="width: 38px;">Tard.</th>
                <th class="center" style="width: 90px;">Nivel de Alerta</th>
            </tr>
        </thead>
        <tbody>
            @forelse ($ranking as $idx => $item)
                @php $st = $item['student']; @endphp
                <tr>
                    <td class="center"><strong>{{ $idx + 1 }}</strong></td>
                    <td>{{ $st->dni ?: 'S/D' }}</td>
                    <td>{{ $st->full_name }}</td>
                    <td>{{ $st->course?->name ?: '-' }}</td>
                    <td>{{ $st->course?->shiftRef?->name ?? $st->course?->shift?->label() ?? '-' }}</td>
                    <td class="right">{{ number_format($item['unjustified'], 2) }}</td>
                    <td class="right">{{ number_format($item['justified'], 2) }}</td>
                    <td class="right"><strong>{{ number_format($item['total'], 2) }}</strong></td>
                    <td class="center">{{ $item['tardanzas'] }}</td>
                    <td class="center">{{ $item['risk_label'] }}</td>
                </tr>
            @empty
                <tr>
                    <td colspan="10" class="center">No se registran inasistencias en el período consultado.</td>
                </tr>
            @endforelse
        </tbody>
    </table>

    <table class="signatures">
        <tr>
            <td><div class="sig-line">FIRMA PRECEPTORÍA</div></td>
            <td><div class="sig-line">V° B° DIRECCIÓN</div></td>
        </tr>
    </table>
</body>
</html>
