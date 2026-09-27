// Service worker de MIA.
//
// Regla de seguridad: NUNCA se guardan en caché páginas HTML ni archivos protegidos
// (/archivos/...), porque contienen datos del usuario y el token CSRF, y en computadores
// compartidos de los colegios quedarían visibles después de cerrar sesión. Solo se
// guardan recursos estáticos públicos (CSS, JS, imágenes de /assets/) y la página offline.
//
// Al cambiar CACHE, activate borra las versiones anteriores (incluida 'sigebi-v1', que
// sí guardaba el panel autenticado).
const CACHE = 'sigebi-v3'; // v3: nombre y logo de MIA
const PRECARGA = ['./offline.html', './assets/css/app.css'];

self.addEventListener('install', (event) => {
    event.waitUntil(
        caches.open(CACHE).then((cache) => cache.addAll(PRECARGA)).catch(() => {})
    );
    self.skipWaiting();
});

self.addEventListener('activate', (event) => {
    event.waitUntil(
        caches.keys()
            .then((claves) => Promise.all(claves.filter((c) => c !== CACHE).map((c) => caches.delete(c))))
            .then(() => self.clients.claim())
    );
});

self.addEventListener('fetch', (event) => {
    const peticion = event.request;
    if (peticion.method !== 'GET') {
        return;
    }

    // Páginas: siempre desde la red; sin conexión, la página offline (nunca una copia vieja).
    if (peticion.mode === 'navigate') {
        event.respondWith(fetch(peticion).catch(() => caches.match('./offline.html')));
        return;
    }

    const url = new URL(peticion.url);
    const esEstatico = url.origin === self.location.origin && url.pathname.includes('/assets/');
    if (!esEstatico) {
        return; // todo lo demás (archivos protegidos, JSON, CDN) va directo a la red
    }

    // Estáticos: red primero (siempre la versión nueva); si no hay red, la copia guardada.
    event.respondWith(
        fetch(peticion)
            .then((respuesta) => {
                if (respuesta.ok) {
                    const copia = respuesta.clone();
                    caches.open(CACHE).then((cache) => cache.put(peticion, copia));
                }
                return respuesta;
            })
            .catch(() => caches.match(peticion))
    );
});
