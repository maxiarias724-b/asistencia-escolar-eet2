/*
 * Service Worker de la PWA (Asistencia Escolar · EET N° 2).
 *
 * Política deliberadamente conservadora, porque el sistema maneja datos personales
 * de estudiantes y se usa en equipos compartidos:
 *   - Las páginas HTML (navegación) NUNCA se guardan en caché: siempre van a la red.
 *     Si no hay conexión se muestra una página "sin conexión" estática.
 *   - Solo se cachean archivos estáticos propios (css, js, íconos) con la estrategia
 *     "stale-while-revalidate".
 *   - Los POST/PUT/DELETE y cualquier petición a otro origen no se interceptan.
 *
 * Subir la versión de CACHE_VERSION cuando se modifique este archivo o la lista PRECACHE.
 */
const CACHE_VERSION = 'v3';
const CACHE_NAME = 'eet2-static-' + CACHE_VERSION;

// El scope del SW es la carpeta donde está este archivo (funciona en la raíz o en subcarpetas, ej. XAMPP).
const SCOPE_URL = new URL(self.registration.scope);
const BASE_PATH = SCOPE_URL.pathname.endsWith('/') ? SCOPE_URL.pathname : SCOPE_URL.pathname + '/';
const OFFLINE_URL = new URL('offline.html', SCOPE_URL).href;

const PRECACHE = [
    OFFLINE_URL,
    new URL('icons/icon-192.png', SCOPE_URL).href,
    new URL('icons/icon-512.png', SCOPE_URL).href,
    new URL('css/theme.css', SCOPE_URL).href,
    new URL('css/pwa.css', SCOPE_URL).href,
    new URL('js/theme.js', SCOPE_URL).href,
];

const STATIC_PREFIXES = ['css/', 'js/', 'icons/', 'build/'].map((p) => BASE_PATH + p);

self.addEventListener('install', (event) => {
    event.waitUntil(
        caches.open(CACHE_NAME)
            // Si un recurso falla (p. ej. aún no existe) no se aborta la instalación.
            .then((cache) => Promise.all(PRECACHE.map((url) => cache.add(url).catch(() => null))))
            .then(() => self.skipWaiting())
    );
});

self.addEventListener('activate', (event) => {
    event.waitUntil(
        caches.keys()
            .then((keys) => Promise.all(
                keys.filter((k) => k.startsWith('eet2-static-') && k !== CACHE_NAME).map((k) => caches.delete(k))
            ))
            .then(() => self.clients.claim())
    );
});

self.addEventListener('fetch', (event) => {
    const request = event.request;
    if (request.method !== 'GET') { return; }

    const url = new URL(request.url);
    if (url.origin !== self.location.origin) { return; }

    // Navegación: siempre red; sin conexión -> página offline.
    if (request.mode === 'navigate') {
        event.respondWith(
            fetch(request).catch(() => caches.match(OFFLINE_URL).then((r) => r || Response.error()))
        );
        return;
    }

    // Estáticos propios: stale-while-revalidate.
    if (STATIC_PREFIXES.some((prefix) => url.pathname.startsWith(prefix))) {
        event.respondWith(
            caches.open(CACHE_NAME).then((cache) =>
                cache.match(request).then((cached) => {
                    const network = fetch(request)
                        .then((response) => {
                            if (response && response.ok) { cache.put(request, response.clone()); }
                            return response;
                        })
                        .catch(() => cached || Response.error());
                    return cached || network;
                })
            )
        );
    }
});
