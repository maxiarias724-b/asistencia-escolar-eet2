@if (! empty($teacherToday))
    <section class="mb-6 rounded-lg border border-slate-200 bg-white p-4 shadow-sm">
        <div class="mb-4 flex flex-wrap items-center justify-between gap-3 border-b border-slate-100 pb-3">
            <div>
                <h2 class="text-lg font-bold text-slate-900">
                    Asistencia docente del día ({{ now()->translatedFormat('l d/m/Y') }})
                </h2>
                <p class="text-xs text-slate-500">
                    Padrón docente activo: <strong>{{ $teacherToday['active_teachers'] }}</strong> ·
                    Registros cargados hoy: <strong>{{ $teacherToday['counts']['total'] }}</strong>
                </p>
            </div>
            <div class="flex flex-wrap items-center gap-2 text-xs">
                <a href="{{ route('attendance.teachers.index') }}"
                   class="rounded bg-indigo-600 px-3.5 py-2 font-semibold text-white shadow-sm hover:bg-indigo-700">
                    📋 Tomar / Editar asistencia docente
                </a>
            </div>
        </div>

        {{-- Contadores del día por estado (Docentes) --}}
        <div class="mb-4 grid grid-cols-2 gap-2.5 text-center sm:grid-cols-5">
            <div class="rounded border border-emerald-200 bg-emerald-50 p-2.5">
                <div class="text-xs font-medium text-emerald-700">Presentes (P)</div>
                <div class="mt-0.5 text-2xl font-bold text-emerald-800">{{ $teacherToday['counts']['presente'] }}</div>
            </div>
            <div class="rounded border border-red-200 bg-red-50 p-2.5">
                <div class="text-xs font-medium text-red-700">Ausentes (C)</div>
                <div class="mt-0.5 text-2xl font-bold text-red-800">{{ $teacherToday['counts']['ausente'] }}</div>
            </div>
            <div class="rounded border border-amber-200 bg-amber-50 p-2.5">
                <div class="text-xs font-medium text-amber-700">Tardanzas (Q)</div>
                <div class="mt-0.5 text-2xl font-bold text-amber-800">{{ $teacherToday['counts']['tardanza'] }}</div>
            </div>
            <div class="rounded border border-orange-200 bg-orange-50 p-2.5">
                <div class="text-xs font-medium text-orange-700">½ Falta (M)</div>
                <div class="mt-0.5 text-2xl font-bold text-orange-800">{{ $teacherToday['counts']['media_falta'] }}</div>
            </div>
            <div class="rounded border border-sky-200 bg-sky-50 p-2.5">
                <div class="text-xs font-medium text-sky-700">Justificadas (J)</div>
                <div class="mt-0.5 text-2xl font-bold text-sky-800">{{ $teacherToday['counts']['justificada'] }}</div>
            </div>
        </div>

        {{-- Estado de toma docente por turno --}}
        @if ($teacherToday['by_shift']->isNotEmpty())
            <div class="mb-4">
                <div class="mb-2 text-xs font-semibold uppercase tracking-wide text-slate-500">Estado de registro docente por turno</div>
                <div class="grid gap-3 sm:grid-cols-3">
                    @foreach ($teacherToday['by_shift'] as $shiftRow)
                        <div class="flex flex-col justify-between rounded-lg border {{ $shiftRow['taken'] ? 'border-emerald-300 bg-emerald-50/70' : 'border-slate-200 bg-slate-50' }} p-3">
                            <div>
                                <div class="flex items-center justify-between gap-2">
                                    <span class="font-bold text-slate-900">Turno {{ $shiftRow['shift_name'] }}</span>
                                    @if ($shiftRow['taken'])
                                        <span class="inline-flex items-center gap-1 rounded border border-emerald-300 bg-emerald-100 px-2 py-0.5 text-[11px] font-semibold text-emerald-800">
                                            ✓ Asistencia tomada ({{ $shiftRow['recorded'] }})
                                        </span>
                                    @else
                                        <span class="inline-flex items-center gap-1 rounded border border-amber-300 bg-amber-100 px-2 py-0.5 text-[11px] font-semibold text-amber-800">
                                            ⏳ Registro pendiente
                                        </span>
                                    @endif
                                </div>
                                @if ($shiftRow['taken'])
                                    <div class="mt-2 text-xs text-slate-600">
                                        P: <strong>{{ $shiftRow['presente'] }}</strong> ·
                                        A: <strong class="text-red-700">{{ $shiftRow['ausente'] }}</strong> ·
                                        T: <strong class="text-amber-700">{{ $shiftRow['tardanza'] }}</strong> ·
                                        M: <strong class="text-orange-700">{{ $shiftRow['media_falta'] }}</strong> ·
                                        J: <strong class="text-sky-700">{{ $shiftRow['justificada'] }}</strong>
                                    </div>
                                @else
                                    <div class="mt-2 text-xs text-slate-500">
                                        Sin registros docentes cargados hoy en este turno.
                                    </div>
                                @endif
                            </div>
                            <div class="mt-3">
                                <a href="{{ route('attendance.teachers.index', ['shift_id' => $shiftRow['shift_id']]) }}"
                                   class="block w-full rounded {{ $shiftRow['taken'] ? 'border border-emerald-300 bg-white text-emerald-800 hover:bg-emerald-50' : 'bg-emerald-600 text-white hover:bg-emerald-700' }} px-3 py-1.5 text-center text-xs font-semibold transition">
                                    {{ $shiftRow['taken'] ? '✓ Ver / Editar turno ' . $shiftRow['shift_name'] : 'Tomar asistencia · Turno ' . $shiftRow['shift_name'] }}
                                </a>
                            </div>
                        </div>
                    @endforeach
                </div>
            </div>
        @endif

        {{-- Nómina de registros docentes cargados en el día --}}
        <div>
            <div class="mb-2 text-xs font-semibold uppercase tracking-wide text-slate-500">
                Detalle nominal de docentes registrados hoy
            </div>
            @if ($teacherToday['records']->isNotEmpty())
                <div class="overflow-x-auto rounded border border-slate-200">
                    <table class="min-w-full text-sm">
                        <thead class="bg-slate-100 text-left text-xs uppercase text-slate-600">
                            <tr>
                                <th class="px-3 py-2">Docente</th>
                                <th class="px-3 py-2">Área / Especialidad</th>
                                <th class="px-3 py-2">Turno / Actividad</th>
                                <th class="px-3 py-2">Estado</th>
                                <th class="px-3 py-2">Observación</th>
                                <th class="px-3 py-2">Registrado por</th>
                            </tr>
                        </thead>
                        <tbody class="divide-y divide-slate-100">
                            @foreach ($teacherToday['records'] as $rec)
                                <tr class="hover:bg-slate-50/70">
                                    <td class="px-3 py-2">
                                        <div class="font-semibold text-slate-900">{{ $rec->teacher?->full_name ?? 'Docente #'.$rec->teacher_id }}</div>
                                        <div class="font-mono text-xs text-slate-500">
                                            DNI {{ $rec->teacher?->dni ?? '—' }}
                                            @if ($rec->teacher?->file_number)
                                                · Leg. {{ $rec->teacher->file_number }}
                                            @endif
                                        </div>
                                    </td>
                                    <td class="px-3 py-2 text-xs text-slate-600">
                                        {{ $rec->teacher?->specialty_area ?: '—' }}
                                    </td>
                                    <td class="px-3 py-2 text-xs text-slate-700">
                                        <span class="font-medium">{{ $rec->shift?->name ?? 'General' }}</span>
                                        @if ($rec->activity)
                                            <span class="text-slate-400">·</span> {{ $rec->activity->name }}
                                        @endif
                                    </td>
                                    <td class="px-3 py-2">
                                        <x-status-badge :status="$rec->status" />
                                    </td>
                                    <td class="px-3 py-2 text-xs text-slate-600">
                                        {{ $rec->observation ?: '—' }}
                                    </td>
                                    <td class="px-3 py-2 text-xs text-slate-500">
                                        {{ $rec->recorder?->name ?? '—' }}
                                    </td>
                                </tr>
                            @endforeach
                        </tbody>
                    </table>
                </div>
            @else
                <div class="rounded border border-amber-200 bg-amber-50 px-4 py-3 text-xs text-amber-800">
                    ⏳ Aún no se registraron asistencias docentes en el día de la fecha ({{ now()->format('d/m/Y') }}).
                    <a href="{{ route('attendance.teachers.index') }}" class="ml-1 font-semibold underline">Ir a toma de asistencia docente →</a>
                </div>
            @endif
        </div>
    </section>
@endif
