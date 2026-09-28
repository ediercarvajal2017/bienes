/**
 * Menú de navegación:
 *  - Celular y tableta: el botón ☰ (arriba) o "Menú" (barra inferior) abren el menú como
 *    panel deslizable; se cierra con el fondo oscuro, con Escape o al elegir una opción,
 *    y el foco vuelve al botón que lo abrió.
 *  - Escritorio: el botón ☰ alterna el modo compacto (solo íconos). Se recuerda.
 *  - Las secciones que el usuario pliega se recuerdan; la de la página actual siempre
 *    queda abierta.
 * Las preferencias viven en localStorage (con try/catch: en ventanas privadas o con el
 * almacenamiento bloqueado el menú funciona igual, solo que no recuerda).
 */
(function () {
    var sidebar = document.getElementById('sidebar');
    var overlay = document.getElementById('sidebarOverlay');
    var boton = document.getElementById('btnMenu');
    if (!sidebar || !overlay) { return; }

    var raiz = document.documentElement;
    var CLAVE_COMPACTO = 'mia-menu-compacto';
    var CLAVE_PLEGADAS = 'mia-menu-plegadas';
    var escritorio = window.matchMedia('(min-width: 992px)');
    var secciones = Array.prototype.slice.call(sidebar.querySelectorAll('details.menu-seccion'));
    var disparador = null;
    var restaurando = false;

    function leer(clave) { try { return window.localStorage.getItem(clave); } catch (e) { return null; } }
    function guardar(clave, valor) {
        try {
            if (valor === null) { window.localStorage.removeItem(clave); } else { window.localStorage.setItem(clave, valor); }
        } catch (e) { /* sin almacenamiento: no se recuerda */ }
    }
    function plegadas() {
        try { var lista = JSON.parse(leer(CLAVE_PLEGADAS) || '[]'); return Array.isArray(lista) ? lista : []; } catch (e) { return []; }
    }

    // Alto real de la barra superior (cambia con la muesca del iPhone): el menú se pega debajo.
    var barra = document.querySelector('.navbar-sigebi');
    function medirBarra() {
        if (barra) { raiz.style.setProperty('--alto-navbar', barra.offsetHeight + 'px'); }
    }
    medirBarra();
    window.addEventListener('resize', medirBarra);

    // ---------- Panel deslizable (celular y tableta) ----------
    function marcarExpandido(abierto) {
        document.querySelectorAll('[data-abrir-menu]').forEach(function (b) { b.setAttribute('aria-expanded', String(abierto)); });
        if (boton && !escritorio.matches) { boton.setAttribute('aria-expanded', String(abierto)); }
    }
    function abrir(origen) {
        disparador = origen || boton;
        sidebar.classList.add('abierto');
        overlay.classList.add('visible');
        marcarExpandido(true);
        var destino = sidebar.querySelector('.menu-opcion.active') || sidebar.querySelector('.menu-opcion');
        if (destino) { destino.focus({ preventScroll: true }); }
    }
    function cerrar() {
        if (!sidebar.classList.contains('abierto')) { return; }
        sidebar.classList.remove('abierto');
        overlay.classList.remove('visible');
        marcarExpandido(false);
        if (disparador) { disparador.focus({ preventScroll: true }); }
    }

    // ---------- Modo compacto (escritorio) ----------
    function aplicarCompacto(activo) {
        raiz.classList.toggle('menu-compacto', activo);
        sidebar.querySelectorAll('.menu-opcion[data-texto]').forEach(function (a) {
            if (activo) { a.setAttribute('title', a.getAttribute('data-texto')); } else { a.removeAttribute('title'); }
        });
        if (activo) {
            // Con solo íconos no hay títulos que desplegar: todo queda a la vista.
            restaurando = true;
            secciones.forEach(function (d) { d.open = true; });
            window.setTimeout(function () { restaurando = false; }, 0);
        } else {
            restaurarPlegadas();
        }
        if (boton && escritorio.matches) {
            boton.setAttribute('aria-label', activo ? 'Expandir el menú' : 'Contraer el menú');
            boton.setAttribute('aria-expanded', String(!activo));
        }
    }

    function restaurarPlegadas() {
        var lista = plegadas();
        restaurando = true;
        secciones.forEach(function (d) {
            var tieneActual = d.querySelector('.menu-opcion.active') !== null;
            d.open = tieneActual || lista.indexOf(d.getAttribute('data-seccion')) === -1;
        });
        window.setTimeout(function () { restaurando = false; }, 0);
    }

    secciones.forEach(function (d) {
        d.addEventListener('toggle', function () {
            if (restaurando || raiz.classList.contains('menu-compacto')) { return; }
            var clave = d.getAttribute('data-seccion');
            var lista = plegadas().filter(function (c) { return c !== clave; });
            if (!d.open) { lista.push(clave); }
            guardar(CLAVE_PLEGADAS, lista.length ? JSON.stringify(lista) : null);
        });
    });

    // ---------- Eventos ----------
    if (boton) {
        boton.setAttribute('aria-controls', 'sidebar');
        boton.addEventListener('click', function () {
            if (escritorio.matches) {
                var nuevo = !raiz.classList.contains('menu-compacto');
                aplicarCompacto(nuevo);
                guardar(CLAVE_COMPACTO, nuevo ? '1' : null);
            } else if (sidebar.classList.contains('abierto')) {
                cerrar();
            } else {
                abrir(boton);
            }
        });
    }
    document.querySelectorAll('[data-abrir-menu]').forEach(function (b) {
        b.addEventListener('click', function () {
            if (sidebar.classList.contains('abierto')) { cerrar(); } else { abrir(b); }
        });
    });
    overlay.addEventListener('click', cerrar);
    sidebar.querySelectorAll('a').forEach(function (enlace) { enlace.addEventListener('click', cerrar); });
    document.addEventListener('keydown', function (e) { if (e.key === 'Escape') { cerrar(); } });
    escritorio.addEventListener('change', function () {
        cerrar();
        aplicarCompacto(escritorio.matches && leer(CLAVE_COMPACTO) === '1');
    });

    aplicarCompacto(escritorio.matches && leer(CLAVE_COMPACTO) === '1');
})();
