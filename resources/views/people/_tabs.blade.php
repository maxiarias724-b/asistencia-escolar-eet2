@php $u = auth()->user(); @endphp
<div class="mb-6 border-b border-slate-200">
    <nav class="-mb-px flex flex-wrap gap-1 text-sm font-medium">
        <a href="{{ route('people.students.index') }}"
           class="rounded-t border-b-2 px-4 py-2.5 transition {{ request()->routeIs('people.students.*') ? 'border-indigo-600 bg-white text-indigo-700' : 'border-transparent text-slate-600 hover:border-slate-300 hover:text-slate-900' }}">
            Estudiantes (Nómina SINIDE)
        </a>
        @if ($u->isDirector())
            <a href="{{ route('people.teachers.index') }}"
               class="rounded-t border-b-2 px-4 py-2.5 transition {{ request()->routeIs('people.teachers.*') ? 'border-indigo-600 bg-white text-indigo-700' : 'border-transparent text-slate-600 hover:border-slate-300 hover:text-slate-900' }}">
                Padrón de Docentes
            </a>
        @endif
        <a href="{{ route('people.import.index') }}"
           class="rounded-t border-b-2 px-4 py-2.5 transition {{ request()->routeIs('people.import.*') ? 'border-indigo-600 bg-white text-indigo-700' : 'border-transparent text-slate-600 hover:border-slate-300 hover:text-slate-900' }}">
            Importar Excel / CSV (9 pasos)
        </a>
    </nav>
</div>
