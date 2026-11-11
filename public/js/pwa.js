/*
 * PWA: registro del Service Worker y botón "Instalar aplicación".
 * Los elementos [data-pwa-install] (contenedores con atributo hidden) se muestran
 * solo cuando el navegador permite instalar la app.
 * El Service Worker únicamente se registra en HTTPS o en localhost / 127.0.0.1
 * (requisito de los navegadores).
 */
(function () {
    'use strict';

    var script = document.currentScript;
    var swUrl = script ? script.getAttribute('data-sw') : null;
    var host = window.location.hostname;
    var secure = window.location.protocol === 'https:' || host === 'localhost' || host === '127.0.0.1' || host === '[::1]';

    if (swUrl && secure && 'serviceWorker' in navigator) {
        window.addEventListener('load', function () {
            navigator.serviceWorker.register(swUrl).catch(function () { /* sin SW la app funciona igual */ });
        });
    }

    var deferredPrompt = null;

    function toggleInstallUi(show) {
        var items = document.querySelectorAll('[data-pwa-install]');
        for (var i = 0; i < items.length; i++) {
            items[i].hidden = !show;
        }
    }

    window.addEventListener('beforeinstallprompt', function (event) {
        event.preventDefault();
        deferredPrompt = event;
        toggleInstallUi(true);
    });

    // El evento puede dispararse antes de que exista el menú: sincronizar al terminar de cargar.
    document.addEventListener('DOMContentLoaded', function () { toggleInstallUi(!!deferredPrompt); });

    window.addEventListener('appinstalled', function () {
        deferredPrompt = null;
        toggleInstallUi(false);
    });

    document.addEventListener('click', function (event) {
        var target = event.target && event.target.closest ? event.target.closest('[data-pwa-install-action]') : null;
        if (!target || !deferredPrompt) { return; }
        var promptEvent = deferredPrompt;
        deferredPrompt = null;
        toggleInstallUi(false);
        promptEvent.prompt();
    });
})();
