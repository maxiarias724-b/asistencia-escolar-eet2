{{--
    Menú de usuario (botón de tres líneas) para la barra de navegación.
    Estructura: Configuración ▸ Apariencia (tema y tamaño de texto) … y al final "Cerrar sesión".
    Para agregar nuevas opciones de configuración, añadir otro bloque dentro de <div x-show="config"> (ver "Próximamente").
    Requiere Alpine (ya cargado en el layout).
--}}
@php $menuUser = auth()->user(); @endphp
<div x-data="{ open: false, config: false, appearance: false }"
     @click.outside="open = false"
     @keydown.escape.window="open = false"
     class="relative print:hidden">

    {{-- Botón de tres líneas horizontales --}}
    <button type="button" @click="open = !open" :aria-expanded="open.toString()" aria-haspopup="true"
            aria-label="Menú de usuario" title="Menú de usuario"
            class="flex h-9 w-9 items-center justify-center rounded-md bg-slate-800 text-slate-200 ring-1 ring-slate-700 transition hover:bg-slate-700 hover:text-white focus:outline-none focus-visible:ring-2 focus-visible:ring-amber-300">
        <svg xmlns="http://www.w3.org/2000/svg" class="h-5 w-5" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" aria-hidden="true">
            <path d="M4 6h16M4 12h16M4 18h16" />
        </svg>
    </button>

    {{-- Panel desplegable --}}
    <div x-show="open" x-cloak x-transition.origin.top.right role="menu"
         class="absolute right-0 z-50 mt-2 w-72 max-w-[calc(100vw-1.5rem)] overflow-hidden rounded-lg border border-slate-700 bg-slate-800 text-slate-100 shadow-xl">

        {{-- Cabecera con el usuario (en pantallas chicas el nombre no se ve en la barra) --}}
        <div class="flex items-center gap-3 border-b border-slate-700 px-4 py-3 sm:hidden">
            <span class="flex h-9 w-9 shrink-0 items-center justify-center rounded-full bg-amber-500 text-sm font-extrabold text-slate-950">
                {{ mb_strtoupper(mb_substr($menuUser->name, 0, 1)) }}
            </span>
            <div class="min-w-0">
                <p class="truncate text-sm font-semibold text-white">{{ $menuUser->name }}</p>
                <p class="text-xs text-amber-300">{{ $menuUser->role->label() }}</p>
            </div>
        </div>

        <div class="p-1.5">
            {{-- Configuración --}}
            <button type="button" @click="config = !config" :aria-expanded="config.toString()" role="menuitem"
                    class="flex w-full items-center gap-3 rounded-md px-3 py-2 text-left text-sm font-medium text-slate-100 transition hover:bg-slate-700">
                <svg xmlns="http://www.w3.org/2000/svg" class="h-4 w-4 shrink-0 text-slate-300" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round" aria-hidden="true">
                    <path d="M12 15a3 3 0 100-6 3 3 0 000 6z" />
                    <path d="M19.4 15a1.7 1.7 0 00.34 1.87l.06.06a2 2 0 11-2.83 2.83l-.06-.06a1.7 1.7 0 00-1.87-.34 1.7 1.7 0 00-1 1.55V21a2 2 0 11-4 0v-.09a1.7 1.7 0 00-1.1-1.55 1.7 1.7 0 00-1.87.34l-.06.06a2 2 0 11-2.83-2.83l.06-.06a1.7 1.7 0 00.34-1.87 1.7 1.7 0 00-1.55-1H3a2 2 0 110-4h.09a1.7 1.7 0 001.55-1.1 1.7 1.7 0 00-.34-1.87l-.06-.06a2 2 0 112.83-2.83l.06.06a1.7 1.7 0 001.87.34h.01a1.7 1.7 0 001-1.55V3a2 2 0 114 0v.09a1.7 1.7 0 001 1.55 1.7 1.7 0 001.87-.34l.06-.06a2 2 0 112.83 2.83l-.06.06a1.7 1.7 0 00-.34 1.87v.01a1.7 1.7 0 001.55 1H21a2 2 0 110 4h-.09a1.7 1.7 0 00-1.55 1z" />
                </svg>
                <span class="flex-1">Configuración</span>
                <span class="text-[10px] text-slate-400 transition-transform" :class="config ? 'rotate-180' : ''" aria-hidden="true">▾</span>
            </button>

            <div x-show="config" x-cloak x-transition.opacity.duration.150ms class="ml-4 border-l border-slate-700 pl-2">
                {{-- Apariencia --}}
                <button type="button" @click="appearance = !appearance" :aria-expanded="appearance.toString()" role="menuitem"
                        class="mt-0.5 flex w-full items-center gap-3 rounded-md px-3 py-2 text-left text-sm text-slate-200 transition hover:bg-slate-700">
                    <svg xmlns="http://www.w3.org/2000/svg" class="h-4 w-4 shrink-0 text-slate-300" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round" aria-hidden="true">
                        <circle cx="12" cy="12" r="9" />
                        <path d="M12 3v18" />
                        <path d="M12 3a9 9 0 010 18z" fill="currentColor" />
                    </svg>
                    <span class="flex-1">Apariencia</span>
                    <span class="text-[10px] text-slate-400 transition-transform" :class="appearance ? 'rotate-180' : ''" aria-hidden="true">▾</span>
                </button>

                <div x-show="appearance" x-cloak x-transition.opacity.duration.150ms class="px-3 pb-3 pt-1">
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

                {{-- Próximamente: más opciones de configuración --}}
            </div>
        </div>

        {{-- Cerrar sesión --}}
        <div class="border-t border-slate-700 p-1.5">
            <form method="POST" action="{{ route('logout') }}">
                @csrf
                <button type="submit" role="menuitem"
                        class="flex w-full items-center gap-3 rounded-md px-3 py-2 text-left text-sm font-medium text-red-300 transition hover:bg-red-600 hover:text-white">
                    <svg xmlns="http://www.w3.org/2000/svg" class="h-4 w-4 shrink-0" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round" aria-hidden="true">
                        <path d="M9 21H5a2 2 0 01-2-2V5a2 2 0 012-2h4" />
                        <path d="M16 17l5-5-5-5" />
                        <path d="M21 12H9" />
                    </svg>
                    Cerrar sesión
                </button>
            </form>
        </div>
    </div>
</div>
