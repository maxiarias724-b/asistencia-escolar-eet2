/*
 * Gestor de apariencia: tema (claro / oscuro / automático) y tamaño de texto.
 * Se carga en <head> de forma síncrona para aplicar la preferencia antes de
 * pintar la página y evitar el "parpadeo" blanco. Guarda la elección solo en
 * el navegador (localStorage); no toca el servidor ni la base de datos.
 */
(function () {
    'use strict';

    var THEME_KEY = 'eet-theme';
    var FONT_KEY = 'eet-font';
    var THEMES = ['light', 'dark', 'system'];
    var FONTS = ['md', 'lg'];
    var LABELS = { light: '☀ Claro', dark: '🌙 Oscuro', system: '🖥 Automático' };

    var root = document.documentElement;
    var media = window.matchMedia ? window.matchMedia('(prefers-color-scheme: dark)') : null;

    function read(key, fallback, allowed) {
        try {
            var value = window.localStorage.getItem(key);
            return allowed.indexOf(value) !== -1 ? value : fallback;
        } catch (e) {
            return fallback;
        }
    }

    function write(key, value) {
        try { window.localStorage.setItem(key, value); } catch (e) { /* almacenamiento bloqueado */ }
    }

    var theme = read(THEME_KEY, 'light', THEMES);
    var font = read(FONT_KEY, 'md', FONTS);

    function syncControls() {
        var i, el;
        var themeButtons = document.querySelectorAll('[data-theme-set]');
        for (i = 0; i < themeButtons.length; i++) {
            el = themeButtons[i];
            el.setAttribute('aria-pressed', el.getAttribute('data-theme-set') === theme ? 'true' : 'false');
        }
        var fontButtons = document.querySelectorAll('[data-font-set]');
        for (i = 0; i < fontButtons.length; i++) {
            el = fontButtons[i];
            el.setAttribute('aria-pressed', el.getAttribute('data-font-set') === font ? 'true' : 'false');
        }
        var labels = document.querySelectorAll('[data-theme-label]');
        for (i = 0; i < labels.length; i++) {
            labels[i].textContent = LABELS[theme];
        }
    }

    function apply() {
        var dark = theme === 'dark' || (theme === 'system' && media && media.matches);
        if (dark) { root.classList.add('dark'); } else { root.classList.remove('dark'); }
        root.setAttribute('data-theme', theme);
        root.setAttribute('data-font', font);
        syncControls();
    }

    function setTheme(value) {
        if (THEMES.indexOf(value) === -1) { return; }
        theme = value;
        write(THEME_KEY, theme);
        apply();
    }

    function setFont(value) {
        if (FONTS.indexOf(value) === -1) { return; }
        font = value;
        write(FONT_KEY, font);
        apply();
    }

    document.addEventListener('click', function (event) {
        var target = event.target && event.target.closest ? event.target : null;
        if (!target) { return; }

        var themeBtn = target.closest('[data-theme-set]');
        if (themeBtn) { setTheme(themeBtn.getAttribute('data-theme-set')); return; }

        var fontBtn = target.closest('[data-font-set]');
        if (fontBtn) { setFont(fontBtn.getAttribute('data-font-set')); return; }

        if (target.closest('[data-theme-cycle]')) {
            setTheme(THEMES[(THEMES.indexOf(theme) + 1) % THEMES.length]);
        }
    });

    // En modo automático, seguir los cambios del sistema operativo.
    if (media) {
        var onSystemChange = function () { if (theme === 'system') { apply(); } };
        if (media.addEventListener) { media.addEventListener('change', onSystemChange); }
        else if (media.addListener) { media.addListener(onSystemChange); }
    }

    // Mantener sincronizadas las pestañas abiertas.
    window.addEventListener('storage', function (event) {
        if (event.key === THEME_KEY || event.key === FONT_KEY) {
            theme = read(THEME_KEY, 'light', THEMES);
            font = read(FONT_KEY, 'md', FONTS);
            apply();
        }
    });

    document.addEventListener('DOMContentLoaded', syncControls);

    apply();
    window.EETTheme = { setTheme: setTheme, setFont: setFont };
})();
