<div class="mb-5 flex flex-wrap items-center justify-between gap-3 border-b border-slate-200 pb-3 print:hidden">
    <div>
        <h1 class="text-xl font-bold text-slate-900">Consultas, Planilla Mensual y Exportaciones</h1>
        <p class="text-xs text-slate-500">EET N.º 2 «Prof. Jesús Raúl Salazar» · Reportes institucionales en pantalla, Excel (.xlsx), CSV (.csv) y PDF (.pdf).</p>
    </div>

    <div class="flex flex-wrap gap-1.5 text-xs font-medium">
        <a href="{{ route('reports.index') }}"
           class="rounded-md px-3 py-2 transition {{ request()->routeIs('reports.index', 'attendance.consult') ? 'bg-slate-900 text-amber-300 shadow-sm' : 'border border-slate-300 bg-white text-slate-700 hover:bg-slate-50' }}">
            🔎 Consulta Histórica
        </a>
        <a href="{{ route('reports.monthly-sheet') }}"
           class="rounded-md px-3 py-2 transition {{ request()->routeIs('reports.monthly-sheet') ? 'bg-slate-900 text-amber-300 shadow-sm' : 'border border-slate-300 bg-white text-slate-700 hover:bg-slate-50' }}">
            📅 Planilla Mensual (01–31)
        </a>
        <a href="{{ route('reports.ranking') }}"
           class="rounded-md px-3 py-2 transition {{ request()->routeIs('reports.ranking') ? 'bg-slate-900 text-amber-300 shadow-sm' : 'border border-slate-300 bg-white text-slate-700 hover:bg-slate-50' }}">
            📊 Ranking de Inasistencias
        </a>
        <a href="{{ route('reports.student-search') }}"
           class="rounded-md px-3 py-2 transition {{ request()->routeIs('reports.student-search', 'reports.student-history', 'attendance.history') ? 'bg-slate-900 text-amber-300 shadow-sm' : 'border border-slate-300 bg-white text-slate-700 hover:bg-slate-50' }}">
            🎓 Historial por Estudiante
        </a>
    </div>
</div>
