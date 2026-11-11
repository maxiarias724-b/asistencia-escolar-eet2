<!DOCTYPE html>
<html lang="es" class="h-full bg-slate-100">
<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <meta name="csrf-token" content="{{ csrf_token() }}">
    <title>@yield('title', 'Panel') · EET N° 2</title>

    @if (file_exists(public_path('build/manifest.json')) || file_exists(public_path('hot')))
        @vite(['resources/css/app.css', 'resources/js/app.js'])
    @else
        <script src="https://cdn.tailwindcss.com"></script>
        <script defer src="https://cdn.jsdelivr.net/npm/alpinejs@3.x.x/dist/cdn.min.js"></script>
    @endif
    @include('partials.theme-head')
    <style>[x-cloak]{display:none !important} @media print { @page { size: landscape; margin: 8mm } }</style>
</head>
<body class="flex min-h-screen flex-col text-slate-800 antialiased">

    {{-- Navbar Institucional Oscuro (EET N° 2) --}}
    <nav x-data="{ mobileOpen: false }" class="border-b border-slate-800 bg-slate-900 text-slate-100 shadow print:hidden">
        <div class="mx-auto max-w-7xl px-4">
            <div class="flex h-14 items-center justify-between gap-4">
                <div class="flex items-center gap-5">
                    <a href="{{ route('home') }}" class="flex items-center gap-2 font-bold tracking-tight text-white hover:text-amber-300">
                        <span class="rounded bg-amber-500 px-2 py-0.5 text-xs font-extrabold uppercase text-slate-950">EET N° 2</span>
                        <span class="hidden text-sm font-semibold text-slate-200 xl:inline">Prof. Jesús Raúl Salazar</span>
                    </a>

                    @auth
                        @php $u = auth()->user(); @endphp
                        <div class="hidden items-center gap-1 text-sm lg:flex">
                            {{-- Dashboard --}}
                            <a href="{{ route('home') }}"
                               class="rounded px-3 py-1.5 font-medium transition {{ request()->routeIs('director.dashboard', 'operator.home', 'docente.home') ? 'bg-slate-800 text-amber-300' : 'text-slate-200 hover:bg-slate-800 hover:text-white' }}">
                                Dashboard
                            </a>

                            {{-- Asistencia --}}
                            @if ($u->isDirector() || $u->isOperador())
                                <div x-data="{ open: false }" @click.outside="open = false" class="relative">
                                    <button type="button" @click="open = !open"
                                            class="flex items-center gap-1 rounded px-3 py-1.5 font-medium transition {{ request()->routeIs('attendance.*') ? 'bg-slate-800 text-amber-300' : 'text-slate-200 hover:bg-slate-800 hover:text-white' }}">
                                        <span>Asistencia</span>
                                        <span class="text-[10px] opacity-70">▾</span>
                                    </button>
                                    <div x-show="open" x-cloak x-transition
                                         class="absolute left-0 z-50 mt-1 w-60 rounded-md border border-slate-700 bg-slate-800 py-1 shadow-lg">
                                        @if ($u->isOperador())
                                            <a href="{{ route('operator.home') }}" class="block px-4 py-2 text-xs text-slate-200 hover:bg-slate-700 hover:text-white">Mis cursos asignados</a>
                                        @endif
                                        <a href="{{ route('attendance.daily') }}" class="block px-4 py-2 text-xs text-slate-200 hover:bg-slate-700 hover:text-white">Toma diaria (Estudiantes)</a>
                                        <a href="{{ route('attendance.index') }}" class="block px-4 py-2 text-xs text-slate-200 hover:bg-slate-700 hover:text-white">Grilla mensual SINIDE</a>
                                        <a href="{{ route('attendance.teachers.index') }}" class="block px-4 py-2 text-xs text-slate-200 hover:bg-slate-700 hover:text-white">Asistencia de Docentes</a>
                                        <div class="my-1 border-t border-slate-700"></div>
                                        <a href="{{ route('attendance.consult') }}" class="block px-4 py-2 text-xs text-slate-200 hover:bg-slate-700 hover:text-white">Consultar asistencias</a>
                                        <a href="{{ route('attendance.history') }}" class="block px-4 py-2 text-xs text-slate-200 hover:bg-slate-700 hover:text-white">Historial por estudiante</a>
                                    </div>
                                </div>

                                {{-- Reportes --}}
                                <div x-data="{ open: false }" @click.outside="open = false" class="relative">
                                    <button type="button" @click="open = !open"
                                            class="flex items-center gap-1 rounded px-3 py-1.5 font-medium transition {{ request()->routeIs('reports.*') ? 'bg-slate-800 text-amber-300' : 'text-slate-200 hover:bg-slate-800 hover:text-white' }}">
                                        <span>Reportes</span>
                                        <span class="text-[10px] opacity-70">▾</span>
                                    </button>
                                    <div x-show="open" x-cloak x-transition
                                         class="absolute left-0 z-50 mt-1 w-64 rounded-md border border-slate-700 bg-slate-800 py-1 shadow-lg">
                                        <a href="{{ route('reports.index') }}" class="block px-4 py-2 text-xs text-slate-200 hover:bg-slate-700 hover:text-white">Consulta histórica y exportaciones</a>
                                        <a href="{{ route('reports.monthly-sheet') }}" class="block px-4 py-2 text-xs text-slate-200 hover:bg-slate-700 hover:text-white">Planilla mensual (01 al 31)</a>
                                        <a href="{{ route('reports.ranking') }}" class="block px-4 py-2 text-xs text-slate-200 hover:bg-slate-700 hover:text-white">Ranking de inasistencias</a>
                                        <a href="{{ route('reports.student-search') }}" class="block px-4 py-2 text-xs text-slate-200 hover:bg-slate-700 hover:text-white">Ficha individual del estudiante</a>
                                        @if ($u->isDirector())
                                            <div class="my-1 border-t border-slate-700"></div>
                                            <a href="{{ route('director.audit.index') }}" class="block px-4 py-2 text-xs text-amber-300 hover:bg-slate-700 hover:text-amber-200">Auditoría y Certificados Médicos</a>
                                        @endif
                                    </div>
                                </div>

                                {{-- Personas --}}
                                <div x-data="{ open: false }" @click.outside="open = false" class="relative">
                                    <button type="button" @click="open = !open"
                                            class="flex items-center gap-1 rounded px-3 py-1.5 font-medium transition {{ request()->routeIs('people.*', 'roster.*') ? 'bg-slate-800 text-amber-300' : 'text-slate-200 hover:bg-slate-800 hover:text-white' }}">
                                        <span>Personas</span>
                                        <span class="text-[10px] opacity-70">▾</span>
                                    </button>
                                    <div x-show="open" x-cloak x-transition
                                         class="absolute left-0 z-50 mt-1 w-60 rounded-md border border-slate-700 bg-slate-800 py-1 shadow-lg">
                                        <a href="{{ route('people.students.index') }}" class="block px-4 py-2 text-xs text-slate-200 hover:bg-slate-700 hover:text-white">Estudiantes (Padrón SINIDE)</a>
                                        @if ($u->isDirector())
                                            <a href="{{ route('people.teachers.index') }}" class="block px-4 py-2 text-xs text-slate-200 hover:bg-slate-700 hover:text-white">Docentes</a>
                                        @endif
                                        <a href="{{ route('people.import.index') }}" class="block px-4 py-2 text-xs text-slate-200 hover:bg-slate-700 hover:text-white">Importar Excel / CSV (9 pasos)</a>
                                        <div class="my-1 border-t border-slate-700"></div>
                                        <a href="{{ route('roster.landing') }}" class="block px-4 py-2 text-xs text-slate-300 hover:bg-slate-700 hover:text-white">Nómina rápida por curso</a>
                                    </div>
                                </div>
                            @endif

                            @if ($u->isDirector())
                                {{-- Organización --}}
                                <div x-data="{ open: false }" @click.outside="open = false" class="relative">
                                    <button type="button" @click="open = !open"
                                            class="flex items-center gap-1 rounded px-3 py-1.5 font-medium transition {{ request()->routeIs('organization.*', 'director.calendar.*') ? 'bg-slate-800 text-amber-300' : 'text-slate-200 hover:bg-slate-800 hover:text-white' }}">
                                        <span>Organización</span>
                                        <span class="text-[10px] opacity-70">▾</span>
                                    </button>
                                    <div x-show="open" x-cloak x-transition
                                         class="absolute left-0 z-50 mt-1 w-60 rounded-md border border-slate-700 bg-slate-800 py-1 shadow-lg">
                                        <a href="{{ route('organization.courses.index') }}" class="block px-4 py-2 text-xs text-slate-200 hover:bg-slate-700 hover:text-white">Cursos y Divisiones</a>
                                        <a href="{{ route('organization.specialties.index') }}" class="block px-4 py-2 text-xs text-slate-200 hover:bg-slate-700 hover:text-white">Especialidades</a>
                                        <a href="{{ route('organization.scopes.index') }}" class="block px-4 py-2 text-xs text-slate-200 hover:bg-slate-700 hover:text-white">Ámbitos de asistencia</a>
                                        <a href="{{ route('organization.activities.index') }}" class="block px-4 py-2 text-xs text-slate-200 hover:bg-slate-700 hover:text-white">Materias y Actividades</a>
                                        <a href="{{ route('organization.shifts.index') }}" class="block px-4 py-2 text-xs text-slate-200 hover:bg-slate-700 hover:text-white">Turnos</a>
                                        <div class="my-1 border-t border-slate-700"></div>
                                        <a href="{{ route('director.calendar.index') }}" class="block px-4 py-2 text-xs text-slate-200 hover:bg-slate-700 hover:text-white">Calendario Escolar (Feriados)</a>
                                    </div>
                                </div>

                                {{-- Preceptores (Asignaciones) --}}
                                <a href="{{ route('director.assignments.index') }}"
                                   class="rounded px-3 py-1.5 font-medium transition {{ request()->routeIs('director.assignments.*') ? 'bg-slate-800 text-amber-300' : 'text-slate-200 hover:bg-slate-800 hover:text-white' }}">
                                    Preceptores
                                </a>

                                {{-- Administración --}}
                                <div x-data="{ open: false }" @click.outside="open = false" class="relative">
                                    <button type="button" @click="open = !open"
                                            class="flex items-center gap-1 rounded px-3 py-1.5 font-medium transition {{ request()->routeIs('director.users.*', 'director.audit.*', 'director.audit-logs.*') ? 'bg-slate-800 text-amber-300' : 'text-slate-200 hover:bg-slate-800 hover:text-white' }}">
                                        <span>Administración</span>
                                        <span class="text-[10px] opacity-70">▾</span>
                                    </button>
                                    <div x-show="open" x-cloak x-transition
                                         class="absolute left-0 z-50 mt-1 w-60 rounded-md border border-slate-700 bg-slate-800 py-1 shadow-lg">
                                        <a href="{{ route('director.users.index') }}" class="block px-4 py-2 text-xs text-slate-200 hover:bg-slate-700 hover:text-white">Usuarios, roles y credenciales</a>
                                        <a href="{{ route('director.audit.index') }}" class="block px-4 py-2 text-xs text-slate-200 hover:bg-slate-700 hover:text-white">Auditoría de asistencias y certificados</a>
                                        <a href="{{ route('director.audit-logs.index') }}" class="block px-4 py-2 text-xs text-slate-200 hover:bg-slate-700 hover:text-white">Bitácora de operaciones (Audit Logs)</a>
                                    </div>
                                </div>
                            @endif
                        </div>
                    @endauth
                </div>

                @auth
                    <div class="flex items-center gap-3 text-sm">
                        <div class="hidden items-center gap-2 sm:flex">
                            <span class="font-medium text-slate-200">{{ auth()->user()->name }}</span>
                            <span class="rounded border border-slate-700 bg-slate-800 px-2 py-0.5 text-xs text-amber-300">
                                {{ auth()->user()->role->label() }}
                            </span>
                        </div>
                        <button type="button" @click="mobileOpen = !mobileOpen" class="rounded px-2.5 py-1.5 text-xs font-medium text-slate-200 ring-1 ring-slate-700 hover:bg-slate-800 lg:hidden" aria-label="Secciones del sistema">
                            Secciones <span class="text-[10px] opacity-70">▾</span>
                        </button>
                        @include('partials.user-menu')
                    </div>
                @endauth
            </div>

            {{-- Menú móvil colapsable --}}
            @auth
                @php $u = auth()->user(); @endphp
                <div x-show="mobileOpen" x-cloak class="space-y-1 border-t border-slate-800 pb-3 pt-2 text-sm lg:hidden">
                    <a href="{{ route('home') }}" class="block rounded px-3 py-1.5 hover:bg-slate-800">Dashboard</a>
                    @if ($u->isOperador())
                        <a href="{{ route('operator.home') }}" class="block rounded px-3 py-1.5 hover:bg-slate-800">Mis cursos asignados</a>
                    @endif
                    @if ($u->isDirector() || $u->isOperador())
                        <a href="{{ route('attendance.daily') }}" class="block rounded px-3 py-1.5 hover:bg-slate-800">Toma diaria (Estudiantes)</a>
                        <a href="{{ route('attendance.index') }}" class="block rounded px-3 py-1.5 hover:bg-slate-800">Grilla mensual SINIDE</a>
                        <a href="{{ route('attendance.teachers.index') }}" class="block rounded px-3 py-1.5 hover:bg-slate-800">Asistencia de Docentes</a>
                        <a href="{{ route('reports.index') }}" class="block rounded px-3 py-1.5 hover:bg-slate-800">Consultas y Exportaciones</a>
                        <a href="{{ route('reports.monthly-sheet') }}" class="block rounded px-3 py-1.5 hover:bg-slate-800">Planilla mensual (01 al 31)</a>
                        <a href="{{ route('reports.ranking') }}" class="block rounded px-3 py-1.5 hover:bg-slate-800">Ranking de inasistencias</a>
                        <a href="{{ route('people.students.index') }}" class="block rounded px-3 py-1.5 hover:bg-slate-800">Estudiantes (Padrón SINIDE)</a>
                        <a href="{{ route('people.import.index') }}" class="block rounded px-3 py-1.5 hover:bg-slate-800">Importar Excel / CSV</a>
                    @endif
                    @if ($u->isDirector())
                        <a href="{{ route('people.teachers.index') }}" class="block rounded px-3 py-1.5 hover:bg-slate-800">Docentes</a>
                        <a href="{{ route('organization.courses.index') }}" class="block rounded px-3 py-1.5 hover:bg-slate-800">Organización Escolar</a>
                        <a href="{{ route('director.assignments.index') }}" class="block rounded px-3 py-1.5 hover:bg-slate-800">Preceptores (Asignaciones)</a>
                        <a href="{{ route('director.users.index') }}" class="block rounded px-3 py-1.5 hover:bg-slate-800">Usuarios y credenciales</a>
                        <a href="{{ route('director.calendar.index') }}" class="block rounded px-3 py-1.5 hover:bg-slate-800">Calendario escolar</a>
                        <a href="{{ route('director.audit.index') }}" class="block rounded px-3 py-1.5 hover:bg-slate-800">Auditoría de asistencias</a>
                        <a href="{{ route('director.audit-logs.index') }}" class="block rounded px-3 py-1.5 hover:bg-slate-800">Bitácora de operaciones</a>
                    @endif
                </div>
            @endauth
        </div>
    </nav>

    <main class="w-full flex-1 @yield('main_class', 'mx-auto max-w-7xl px-4 py-6')">
        @if (session('status'))
            <div class="mb-4 rounded border border-emerald-300 bg-emerald-50 px-4 py-3 text-sm text-emerald-800">{{ session('status') }}</div>
        @endif

        @foreach (['delete', 'roster'] as $bag)
            @if ($errors->has($bag))
                <div class="mb-4 rounded border border-red-300 bg-red-50 px-4 py-3 text-sm text-red-800">{{ $errors->first($bag) }}</div>
            @endif
        @endforeach

        @yield('content')
    </main>

    <footer class="border-t border-slate-200 bg-white py-3 text-center text-xs text-slate-500 print:hidden">
        EET N° 2 "Prof. Jesús Raúl Salazar" — Sistema de Asistencia
    </footer>

@stack('scripts')
</body>
</html>
