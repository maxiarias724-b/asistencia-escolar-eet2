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
    @include('partials.pwa-head')
    <style>
        [x-cloak]{display:none !important}
        @media print { @page { size: landscape; margin: 8mm } }
        .eet-navbar {
            font-size: 13px;
            line-height: 1.25;
            background-color: rgba(17, 19, 24, 0.97);
            border-bottom: 2px solid #dc2626;
            box-shadow: 0 1px 0 0 rgba(140, 240, 100, 0.25), 0 8px 20px -4px rgba(0, 0, 0, 0.45);
        }
        .eet-navbar .eet-nav-item {
            display: inline-flex; align-items: center; gap: 0.3rem;
            white-space: nowrap; flex-shrink: 0;
            padding: 0.375rem 0.55rem; border-radius: 0.375rem;
            font-size: 12.5px; font-weight: 600; line-height: 1.2;
            transition: background-color 150ms ease, color 150ms ease, box-shadow 150ms ease;
        }
        @media (min-width: 1280px) {
            .eet-navbar .eet-nav-item { padding: 0.4rem 0.7rem; font-size: 13px; gap: 0.35rem; }
        }
        .eet-navbar .eet-nav-item-active {
            background-color: rgba(220, 38, 38, 0.20); color: #8cf064;
            box-shadow: inset 0 -2px 0 0 #dc2626;
        }
        .eet-navbar .eet-nav-item-idle { color: #e2e8f0; }
        .eet-navbar .eet-nav-item-idle:hover { background-color: rgba(255, 255, 255, 0.08); color: #8cf064; }
        .eet-navbar .eet-dropdown-link {
            display: flex; align-items: center; justify-content: space-between; gap: 0.5rem;
            padding: 0.5rem 0.875rem; font-size: 12.5px; line-height: 1.3;
            color: #e2e8f0; white-space: nowrap;
            transition: background-color 120ms ease, color 120ms ease, padding-left 120ms ease;
        }
        .eet-navbar .eet-dropdown-link:hover { background-color: rgba(220, 38, 38, 0.16); color: #8cf064; padding-left: 1rem; }
        .eet-navbar .eet-dropdown-link-active { background-color: rgba(220, 38, 38, 0.24); color: #8cf064; font-weight: 600; }
    </style>
</head>
<body class="flex min-h-screen flex-col text-slate-800 antialiased">

    {{-- Navbar Institucional Fijo al Scrollear (EET N° 2) --}}
    <nav x-data="{
            mobileOpen: false,
            activeMenu: null,
            toggleMenu(name) { this.activeMenu = (this.activeMenu === name ? null : name); },
            hoverMenu(name) { if (this.activeMenu !== null) { this.activeMenu = name; } }
         }"
         @click.outside="activeMenu = null"
         @keydown.escape.window="activeMenu = null; mobileOpen = false"
         class="eet-navbar sticky top-0 z-40 text-slate-100 backdrop-blur print:hidden">
        <div class="mx-auto max-w-[1440px] px-3 sm:px-5">
            <div class="flex h-14 items-center justify-between gap-3">

                {{-- Marca Institucional (Escudo sin fondo blanco + Nombre) + Navegación por Rol --}}
                <div class="flex shrink-0 items-center gap-2 xl:gap-3">
                    <a href="{{ route('home') }}"
                       class="group flex shrink-0 items-center gap-2.5 rounded-md py-1 pr-1.5 transition focus:outline-none focus-visible:ring-2 focus-visible:ring-[#8cf064]">
                        <img src="{{ asset('icons/logo-eet2.png') }}"
                             alt="Escudo EET N° 2"
                             width="36" height="44"
                             class="h-10 w-auto shrink-0 object-contain drop-shadow-[0_1px_3px_rgba(0,0,0,0.7)] transition-transform duration-150 group-hover:scale-105">
                        <span class="flex flex-col leading-tight">
                            <span class="whitespace-nowrap text-[13px] font-extrabold tracking-wide text-white transition group-hover:text-[#8cf064]">
                                EET N° 2
                            </span>
                            <span class="hidden whitespace-nowrap text-[10.5px] font-semibold text-[#8cf064] sm:block">
                                Prof. Jesús Raúl Salazar
                            </span>
                        </span>
                    </a>

                    @auth
                        @php $u = auth()->user(); @endphp
                        <div class="hidden h-6 w-px shrink-0 bg-slate-700/80 lg:block" aria-hidden="true"></div>

                        <div class="hidden shrink-0 items-center gap-1 lg:flex">
                            {{-- Dashboard (exclusivo de cada rol) --}}
                            <a href="{{ route('home') }}"
                               @mouseenter="activeMenu = null"
                               class="eet-nav-item {{ request()->routeIs('director.dashboard', 'operator.home', 'docente.home') ? 'eet-nav-item-active' : 'eet-nav-item-idle' }}">
                                <span>Dashboard</span>
                            </a>

                            {{-- Nómina y Observaciones de Clase (Exclusivo Rol Docente en barra principal) --}}
                            @if ($u->isDocente())
                                <a href="{{ route('teacher-observations.index') }}"
                                   @mouseenter="activeMenu = null"
                                   class="eet-nav-item {{ request()->routeIs('teacher-observations.*') ? 'eet-nav-item-active' : 'eet-nav-item-idle' }}">
                                    <span>Observaciones de Clase</span>
                                </a>
                            @endif

                            {{-- Amonestaciones (Disciplina: Director, Preceptor/Operador y Docente según Policy) --}}
                            @can('viewAny', \App\Models\DisciplinaryRecord::class)
                                <a href="{{ route('disciplinary.index') }}"
                                   @mouseenter="activeMenu = null"
                                   class="eet-nav-item {{ request()->routeIs('disciplinary.*') ? 'eet-nav-item-active' : 'eet-nav-item-idle' }}">
                                    <span>Amonestaciones</span>
                                </a>
                            @endcan

                            {{-- Programa QR (Quiero Regresar: exclusivo Director y Preceptor/Operador; oculto para Docente) --}}
                            @can('viewAny', \App\Models\QrIntervention::class)
                                <a href="{{ route('qr.index') }}"
                                   @mouseenter="activeMenu = null"
                                   class="eet-nav-item {{ request()->routeIs('qr.*') ? 'eet-nav-item-active' : 'eet-nav-item-idle' }}">
                                    <span class="h-1.5 w-1.5 shrink-0 rounded-full bg-[#8cf064]" aria-hidden="true"></span>
                                    <span>Programa QR</span>
                                </a>
                            @endcan

                            {{-- Asistencia (Exclusivo Director y Preceptor/Operador) --}}
                            @if ($u->isDirector() || $u->isOperador())
                                <div class="relative shrink-0" @mouseenter="hoverMenu('attendance')">
                                    <button type="button"
                                            @click="toggleMenu('attendance')"
                                            :aria-expanded="(activeMenu === 'attendance').toString()"
                                            class="eet-nav-item {{ request()->routeIs('attendance.*') ? 'eet-nav-item-active' : 'eet-nav-item-idle' }}">
                                        <span>Asistencia</span>
                                        <span class="text-[10px] opacity-75 transition-transform duration-150"
                                              :class="activeMenu === 'attendance' ? 'rotate-180 text-[#8cf064]' : ''">▾</span>
                                    </button>
                                    <div x-show="activeMenu === 'attendance'" x-cloak
                                         x-transition:enter="transition ease-out duration-150"
                                         x-transition:enter-start="opacity-0 translate-y-1"
                                         x-transition:enter-end="opacity-100 translate-y-0"
                                         x-transition:leave="transition ease-in duration-100"
                                         x-transition:leave-start="opacity-100 translate-y-0"
                                         x-transition:leave-end="opacity-0 translate-y-1"
                                         class="absolute left-0 z-50 mt-1.5 w-64 overflow-hidden rounded-lg border border-[#2b303c] bg-[#161922] py-1.5 shadow-xl">
                                        @if ($u->isOperador())
                                            <a href="{{ route('operator.home') }}"
                                               class="eet-dropdown-link {{ request()->routeIs('operator.home') ? 'eet-dropdown-link-active' : '' }}">
                                                <span>Mis cursos asignados</span>
                                            </a>
                                        @endif
                                        <a href="{{ route('attendance.daily') }}"
                                           class="eet-dropdown-link {{ request()->routeIs('attendance.daily') ? 'eet-dropdown-link-active' : '' }}">
                                            <span>Toma diaria (Estudiantes)</span>
                                        </a>
                                        <a href="{{ route('attendance.index') }}"
                                           class="eet-dropdown-link {{ request()->routeIs('attendance.index', 'attendance.show') ? 'eet-dropdown-link-active' : '' }}">
                                            <span>Grilla mensual SINIDE</span>
                                        </a>
                                        <a href="{{ route('attendance.teachers.index') }}"
                                           class="eet-dropdown-link {{ request()->routeIs('attendance.teachers.*') ? 'eet-dropdown-link-active' : '' }}">
                                            <span>Asistencia de Docentes</span>
                                        </a>
                                        <div class="my-1 border-t border-white/10"></div>
                                        <a href="{{ route('attendance.consult') }}"
                                           class="eet-dropdown-link {{ request()->routeIs('attendance.consult') ? 'eet-dropdown-link-active' : '' }}">
                                            <span>Consultar asistencias</span>
                                        </a>
                                        <a href="{{ route('attendance.history') }}"
                                           class="eet-dropdown-link {{ request()->routeIs('attendance.history') ? 'eet-dropdown-link-active' : '' }}">
                                            <span>Historial por estudiante</span>
                                        </a>
                                    </div>
                                </div>

                                {{-- Reportes (Exclusivo Director y Preceptor/Operador) --}}
                                <div class="relative shrink-0" @mouseenter="hoverMenu('reports')">
                                    <button type="button"
                                            @click="toggleMenu('reports')"
                                            :aria-expanded="(activeMenu === 'reports').toString()"
                                            class="eet-nav-item {{ request()->routeIs('reports.*') ? 'eet-nav-item-active' : 'eet-nav-item-idle' }}">
                                        <span>Reportes</span>
                                        <span class="text-[10px] opacity-75 transition-transform duration-150"
                                              :class="activeMenu === 'reports' ? 'rotate-180 text-[#8cf064]' : ''">▾</span>
                                    </button>
                                    <div x-show="activeMenu === 'reports'" x-cloak
                                         x-transition:enter="transition ease-out duration-150"
                                         x-transition:enter-start="opacity-0 translate-y-1"
                                         x-transition:enter-end="opacity-100 translate-y-0"
                                         x-transition:leave="transition ease-in duration-100"
                                         x-transition:leave-start="opacity-100 translate-y-0"
                                         x-transition:leave-end="opacity-0 translate-y-1"
                                         class="absolute left-0 z-50 mt-1.5 w-64 overflow-hidden rounded-lg border border-[#2b303c] bg-[#161922] py-1.5 shadow-xl">
                                        <a href="{{ route('reports.index') }}"
                                           class="eet-dropdown-link {{ request()->routeIs('reports.index') ? 'eet-dropdown-link-active' : '' }}">
                                            <span>Consulta histórica y exportaciones</span>
                                        </a>
                                        <a href="{{ route('reports.monthly-sheet') }}"
                                           class="eet-dropdown-link {{ request()->routeIs('reports.monthly-sheet') ? 'eet-dropdown-link-active' : '' }}">
                                            <span>Planilla mensual (01 al 31)</span>
                                        </a>
                                        <a href="{{ route('reports.ranking') }}"
                                           class="eet-dropdown-link {{ request()->routeIs('reports.ranking') ? 'eet-dropdown-link-active' : '' }}">
                                            <span>Ranking de inasistencias</span>
                                        </a>
                                        <a href="{{ route('reports.student-search') }}"
                                           class="eet-dropdown-link {{ request()->routeIs('reports.student-search', 'reports.student-history') ? 'eet-dropdown-link-active' : '' }}">
                                            <span>Ficha individual del estudiante</span>
                                        </a>
                                        @if ($u->isDirector())
                                            <div class="my-1 border-t border-white/10"></div>
                                            <a href="{{ route('director.audit.index') }}"
                                               class="eet-dropdown-link text-[#8cf064] {{ request()->routeIs('director.audit.index') ? 'eet-dropdown-link-active' : '' }}">
                                                <span>Auditoría y Certificados Médicos</span>
                                            </a>
                                        @endif
                                    </div>
                                </div>

                                {{-- Personas (Exclusivo Director y Preceptor/Operador) --}}
                                <div class="relative shrink-0" @mouseenter="hoverMenu('people')">
                                    <button type="button"
                                            @click="toggleMenu('people')"
                                            :aria-expanded="(activeMenu === 'people').toString()"
                                            class="eet-nav-item {{ request()->routeIs('people.*', 'roster.*') ? 'eet-nav-item-active' : 'eet-nav-item-idle' }}">
                                        <span>Personas</span>
                                        <span class="text-[10px] opacity-75 transition-transform duration-150"
                                              :class="activeMenu === 'people' ? 'rotate-180 text-[#8cf064]' : ''">▾</span>
                                    </button>
                                    <div x-show="activeMenu === 'people'" x-cloak
                                         x-transition:enter="transition ease-out duration-150"
                                         x-transition:enter-start="opacity-0 translate-y-1"
                                         x-transition:enter-end="opacity-100 translate-y-0"
                                         x-transition:leave="transition ease-in duration-100"
                                         x-transition:leave-start="opacity-100 translate-y-0"
                                         x-transition:leave-end="opacity-0 translate-y-1"
                                         class="absolute left-0 z-50 mt-1.5 w-64 overflow-hidden rounded-lg border border-[#2b303c] bg-[#161922] py-1.5 shadow-xl">
                                        <a href="{{ route('people.students.index') }}"
                                           class="eet-dropdown-link {{ request()->routeIs('people.students.*') ? 'eet-dropdown-link-active' : '' }}">
                                            <span>Estudiantes (Padrón SINIDE)</span>
                                        </a>
                                        @if ($u->isDirector())
                                            <a href="{{ route('people.teachers.index') }}"
                                               class="eet-dropdown-link {{ request()->routeIs('people.teachers.*') ? 'eet-dropdown-link-active' : '' }}">
                                                <span>Docentes</span>
                                            </a>
                                        @endif
                                        <a href="{{ route('people.import.index') }}"
                                           class="eet-dropdown-link {{ request()->routeIs('people.import.*') ? 'eet-dropdown-link-active' : '' }}">
                                            <span>Importar Excel / CSV (9 pasos)</span>
                                        </a>
                                        <div class="my-1 border-t border-white/10"></div>
                                        <a href="{{ route('roster.landing') }}"
                                           class="eet-dropdown-link {{ request()->routeIs('roster.*') ? 'eet-dropdown-link-active' : '' }}">
                                            <span>Nómina rápida por curso</span>
                                        </a>
                                    </div>
                                </div>
                            @endif

                            {{-- Secciones exclusivas de Dirección General --}}
                            @if ($u->isDirector())
                                {{-- Organización --}}
                                <div class="relative shrink-0" @mouseenter="hoverMenu('organization')">
                                    <button type="button"
                                            @click="toggleMenu('organization')"
                                            :aria-expanded="(activeMenu === 'organization').toString()"
                                            class="eet-nav-item {{ request()->routeIs('organization.*', 'director.calendar.*') ? 'eet-nav-item-active' : 'eet-nav-item-idle' }}">
                                        <span>Organización</span>
                                        <span class="text-[10px] opacity-75 transition-transform duration-150"
                                              :class="activeMenu === 'organization' ? 'rotate-180 text-[#8cf064]' : ''">▾</span>
                                    </button>
                                    <div x-show="activeMenu === 'organization'" x-cloak
                                         x-transition:enter="transition ease-out duration-150"
                                         x-transition:enter-start="opacity-0 translate-y-1"
                                         x-transition:enter-end="opacity-100 translate-y-0"
                                         x-transition:leave="transition ease-in duration-100"
                                         x-transition:leave-start="opacity-100 translate-y-0"
                                         x-transition:leave-end="opacity-0 translate-y-1"
                                         class="absolute left-0 z-50 mt-1.5 w-64 overflow-hidden rounded-lg border border-[#2b303c] bg-[#161922] py-1.5 shadow-xl">
                                        <a href="{{ route('organization.courses.index') }}"
                                           class="eet-dropdown-link {{ request()->routeIs('organization.courses.*') ? 'eet-dropdown-link-active' : '' }}">
                                            <span>Cursos y Divisiones</span>
                                        </a>
                                        <a href="{{ route('organization.specialties.index') }}"
                                           class="eet-dropdown-link {{ request()->routeIs('organization.specialties.*') ? 'eet-dropdown-link-active' : '' }}">
                                            <span>Especialidades</span>
                                        </a>
                                        <a href="{{ route('organization.scopes.index') }}"
                                           class="eet-dropdown-link {{ request()->routeIs('organization.scopes.*') ? 'eet-dropdown-link-active' : '' }}">
                                            <span>Ámbitos de asistencia</span>
                                        </a>
                                        <a href="{{ route('organization.activities.index') }}"
                                           class="eet-dropdown-link {{ request()->routeIs('organization.activities.*') ? 'eet-dropdown-link-active' : '' }}">
                                            <span>Materias y Actividades</span>
                                        </a>
                                        <a href="{{ route('organization.shifts.index') }}"
                                           class="eet-dropdown-link {{ request()->routeIs('organization.shifts.*') ? 'eet-dropdown-link-active' : '' }}">
                                            <span>Turnos</span>
                                        </a>
                                        <div class="my-1 border-t border-white/10"></div>
                                        <a href="{{ route('director.calendar.index') }}"
                                           class="eet-dropdown-link {{ request()->routeIs('director.calendar.*') ? 'eet-dropdown-link-active' : '' }}">
                                            <span>Calendario Escolar (Feriados)</span>
                                        </a>
                                    </div>
                                </div>

                                {{-- Preceptores (Asignaciones) --}}
                                <a href="{{ route('director.assignments.index') }}"
                                   @mouseenter="activeMenu = null"
                                   class="eet-nav-item {{ request()->routeIs('director.assignments.*') ? 'eet-nav-item-active' : 'eet-nav-item-idle' }}">
                                    <span>Preceptores</span>
                                </a>

                                {{-- Administración --}}
                                <div class="relative shrink-0" @mouseenter="hoverMenu('admin')">
                                    <button type="button"
                                            @click="toggleMenu('admin')"
                                            :aria-expanded="(activeMenu === 'admin').toString()"
                                            class="eet-nav-item {{ request()->routeIs('director.users.*', 'director.audit.*', 'director.audit-logs.*') ? 'eet-nav-item-active' : 'eet-nav-item-idle' }}">
                                        <span>Administración</span>
                                        <span class="text-[10px] opacity-75 transition-transform duration-150"
                                              :class="activeMenu === 'admin' ? 'rotate-180 text-[#8cf064]' : ''">▾</span>
                                    </button>
                                    <div x-show="activeMenu === 'admin'" x-cloak
                                         x-transition:enter="transition ease-out duration-150"
                                         x-transition:enter-start="opacity-0 translate-y-1"
                                         x-transition:enter-end="opacity-100 translate-y-0"
                                         x-transition:leave="transition ease-in duration-100"
                                         x-transition:leave-start="opacity-100 translate-y-0"
                                         x-transition:leave-end="opacity-0 translate-y-1"
                                         class="absolute right-0 z-50 mt-1.5 w-64 overflow-hidden rounded-lg border border-[#2b303c] bg-[#161922] py-1.5 shadow-xl">
                                        <a href="{{ route('director.users.index') }}"
                                           class="eet-dropdown-link {{ request()->routeIs('director.users.*') ? 'eet-dropdown-link-active' : '' }}">
                                            <span>Usuarios, roles y credenciales</span>
                                        </a>
                                        <a href="{{ route('director.audit.index') }}"
                                           class="eet-dropdown-link {{ request()->routeIs('director.audit.*') ? 'eet-dropdown-link-active' : '' }}">
                                            <span>Auditoría de asistencias y certificados</span>
                                        </a>
                                        <a href="{{ route('director.audit-logs.index') }}"
                                           class="eet-dropdown-link {{ request()->routeIs('director.audit-logs.*') ? 'eet-dropdown-link-active' : '' }}">
                                            <span>Bitácora de operaciones (Audit Logs)</span>
                                        </a>
                                    </div>
                                </div>
                            @endif
                        </div>
                    @endauth
                </div>

                {{-- Botón Unificado de Usuario y Menú (sin duplicar texto largo en la barra) --}}
                @auth
                    <div class="flex shrink-0 items-center gap-2">
                        <button type="button" @click="mobileOpen = !mobileOpen"
                                class="whitespace-nowrap rounded-md px-2.5 py-1.5 text-xs font-medium text-slate-200 ring-1 ring-slate-700 hover:bg-slate-800 lg:hidden"
                                aria-label="Secciones del sistema">
                            Secciones <span class="text-[10px] opacity-70">▾</span>
                        </button>

                        @include('partials.user-menu')
                    </div>
                @endauth
            </div>

            {{-- Menú móvil colapsable --}}
            @auth
                @php $u = auth()->user(); @endphp
                <div x-show="mobileOpen" x-cloak
                     x-transition:enter="transition ease-out duration-150"
                     x-transition:enter-start="opacity-0 -translate-y-1"
                     x-transition:enter-end="opacity-100 translate-y-0"
                     class="max-h-[calc(100vh-3.75rem)] space-y-1 overflow-y-auto border-t border-slate-800 pb-3 pt-2 text-sm lg:hidden">
                    <a href="{{ route('home') }}" class="block rounded px-3 py-1.5 hover:bg-slate-800">Dashboard</a>
                    @if ($u->isDocente())
                        <a href="{{ route('teacher-observations.index') }}" class="block rounded px-3 py-1.5 hover:bg-slate-800">Observaciones de Clase</a>
                    @endif
                    @can('viewAny', \App\Models\DisciplinaryRecord::class)
                        <a href="{{ route('disciplinary.index') }}" class="block rounded px-3 py-1.5 hover:bg-slate-800">Amonestaciones (Disciplina)</a>
                    @endcan
                    @can('viewAny', \App\Models\QrIntervention::class)
                        <a href="{{ route('qr.index') }}" class="block rounded px-3 py-1.5 hover:bg-slate-800">Programa QR (Quiero Regresar)</a>
                    @endcan
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
        <div class="inline-flex items-center justify-center gap-2">
            <img src="{{ asset('icons/logo-eet2.png') }}" alt="" class="h-4 w-auto object-contain">
            <span>EET N° 2 "Prof. Jesús Raúl Salazar" — Sistema de Asistencia</span>
        </div>
    </footer>

@stack('scripts')
</body>
</html>
