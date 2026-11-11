{{-- Menú de apariencia para la barra de navegación (requiere Alpine, ya cargado en el layout). --}}
<div x-data="{ open: false }" @click.outside="open = false" @keydown.escape.window="open = false" class="relative print:hidden">
    <button type="button" @click="open = !open" :aria-expanded="open.toString()"
            class="rounded bg-slate-800 px-2.5 py-1.5 text-xs font-medium text-slate-200 ring-1 ring-slate-700 transition hover:bg-slate-700 hover:text-white"
            aria-label="Apariencia" title="Apariencia: tema y tamaño de texto">
        🎨 <span class="hidden md:inline">Apariencia</span>
    </button>
    <div x-show="open" x-cloak x-transition
         class="absolute right-0 z-50 mt-1 w-56 rounded-md border border-slate-700 bg-slate-800 p-3 shadow-lg">
        <p class="mb-1.5 text-[11px] font-semibold uppercase tracking-wide text-slate-400">Tema</p>
        <div class="grid grid-cols-3 gap-1.5">
            <button type="button" class="eet-opt" data-theme-set="light" aria-pressed="true">☀ Claro</button>
            <button type="button" class="eet-opt" data-theme-set="dark" aria-pressed="false">🌙 Oscuro</button>
            <button type="button" class="eet-opt" data-theme-set="system" aria-pressed="false" title="Sigue la configuración del equipo">🖥 Auto</button>
        </div>
        <p class="mb-1.5 mt-3 text-[11px] font-semibold uppercase tracking-wide text-slate-400">Tamaño de texto</p>
        <div class="grid grid-cols-2 gap-1.5">
            <button type="button" class="eet-opt" data-font-set="md" aria-pressed="true">Normal</button>
            <button type="button" class="eet-opt" data-font-set="lg" aria-pressed="false">Grande</button>
        </div>
        <p class="mt-3 text-[11px] leading-snug text-slate-400">Se guarda en este navegador. Los reportes impresos y los PDF siempre salen en modo claro.</p>
    </div>
</div>
