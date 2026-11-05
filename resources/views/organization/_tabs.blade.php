<div class="mb-6 border-b border-slate-200">
    <nav class="-mb-px flex flex-wrap gap-1 text-sm font-medium">
        <a href="{{ route('organization.courses.index') }}"
           class="rounded-t border-b-2 px-4 py-2.5 transition {{ request()->routeIs('organization.courses.*') ? 'border-indigo-600 bg-white text-indigo-700' : 'border-transparent text-slate-600 hover:border-slate-300 hover:text-slate-900' }}">
            Cursos y Divisiones
        </a>
        <a href="{{ route('organization.specialties.index') }}"
           class="rounded-t border-b-2 px-4 py-2.5 transition {{ request()->routeIs('organization.specialties.*') ? 'border-indigo-600 bg-white text-indigo-700' : 'border-transparent text-slate-600 hover:border-slate-300 hover:text-slate-900' }}">
            Especialidades
        </a>
        <a href="{{ route('organization.scopes.index') }}"
           class="rounded-t border-b-2 px-4 py-2.5 transition {{ request()->routeIs('organization.scopes.*') ? 'border-indigo-600 bg-white text-indigo-700' : 'border-transparent text-slate-600 hover:border-slate-300 hover:text-slate-900' }}">
            Ámbitos
        </a>
        <a href="{{ route('organization.activities.index') }}"
           class="rounded-t border-b-2 px-4 py-2.5 transition {{ request()->routeIs('organization.activities.*') ? 'border-indigo-600 bg-white text-indigo-700' : 'border-transparent text-slate-600 hover:border-slate-300 hover:text-slate-900' }}">
            Materias y Actividades
        </a>
        <a href="{{ route('organization.shifts.index') }}"
           class="rounded-t border-b-2 px-4 py-2.5 transition {{ request()->routeIs('organization.shifts.*') ? 'border-indigo-600 bg-white text-indigo-700' : 'border-transparent text-slate-600 hover:border-slate-300 hover:text-slate-900' }}">
            Turnos
        </a>
        <a href="{{ route('director.calendar.index') }}"
           class="rounded-t border-b-2 px-4 py-2.5 transition {{ request()->routeIs('director.calendar.*') ? 'border-indigo-600 bg-white text-indigo-700' : 'border-transparent text-slate-600 hover:border-slate-300 hover:text-slate-900' }}">
            Calendario Escolar
        </a>
    </nav>
</div>
