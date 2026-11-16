/**
 * Menús desplegables sin el JS de Bootstrap (partials/ui/menu_acciones.php):
 * <button data-desplegable aria-expanded="false"> seguido de <ul class="dropdown-menu">.
 *  - Se abre y se cierra con clic; se cierra al hacer clic fuera o con Escape (el foco
 *    vuelve al botón). Nunca hay dos abiertos a la vez.
 *  - Se abre hacia la derecha; si así se sale de la pantalla, se alinea con el borde derecho
 *    del botón, y si tampoco cabe (celular), se corre lo justo para no salirse.
 */
(function () {
    var MARGEN = 8;

    function menuDe(boton) {
        return boton.nextElementSibling;
    }

    function cerrar(boton) {
        var menu = menuDe(boton);
        if (menu) { menu.classList.remove('show'); }
        boton.setAttribute('aria-expanded', 'false');
    }

    function ubicar(boton, menu) {
        menu.style.left = '';
        var caja = menu.getBoundingClientRect();
        if (caja.right <= document.documentElement.clientWidth - MARGEN) { return; }
        var contenedor = menu.parentElement.getBoundingClientRect();
        var izquierda = contenedor.width - caja.width;
        if (contenedor.left + izquierda < MARGEN) { izquierda = MARGEN - contenedor.left; }
        menu.style.left = izquierda + 'px';
    }

    function abiertos() {
        return document.querySelectorAll('[data-desplegable][aria-expanded="true"]');
    }

    document.addEventListener('click', function (evento) {
        var boton = evento.target.closest('[data-desplegable]');
        abiertos().forEach(function (otro) {
            if (otro !== boton) { cerrar(otro); }
        });
        if (!boton) { return; }
        var menu = menuDe(boton);
        if (!menu) { return; }
        var abierto = menu.classList.toggle('show');
        boton.setAttribute('aria-expanded', abierto ? 'true' : 'false');
        if (abierto) { ubicar(boton, menu); }
    });

    document.addEventListener('keydown', function (evento) {
        if (evento.key !== 'Escape') { return; }
        abiertos().forEach(function (boton) {
            cerrar(boton);
            boton.focus();
        });
    });
})();
