/**
 * Comportamiento ÚNICO de todos los buscadores de SIGEBI: la búsqueda se hace cuando el
 * usuario TERMINA de escribir, es decir, al salir del cuadro (clic o toque fuera, tecla
 * Tab), al pulsar Enter o al borrar el texto con la ✕ del campo. No hay temporizador:
 * antes la página se recargaba 600 ms después de dejar de teclear, a veces a mitad de
 * una palabra.
 *
 * Cada buscador solo declara qué hace, con atributos:
 *   data-buscar="q"                  recarga el listado con ?q=<texto> (y vuelve a la página 1)
 *     data-buscar-pagina="pagina"    parámetro de página a reiniciar (por defecto "pagina")
 *     data-buscar-ancla="seccion"    ancla a la que vuelve (pantallas con pestañas)
 *   data-buscar-form                 envía el formulario que lo contiene (buscador global)
 *   data-buscar-local                filtra en la misma página: dispara el evento
 *                                    "sigebi:buscar" (detail = texto) sobre el campo
 * Solo actúa si el texto cambió desde la última búsqueda.
 */
(function () {
    var CLAVE_FOCO = 'sigebi-buscar-foco';

    function activar(input, accion) {
        var ultimo = input.value.trim();

        function ejecutar(porEnter) {
            var valor = input.value.trim();
            if (valor === ultimo) { return; }
            ultimo = valor;
            // Si buscó con Enter seguía en el cuadro: al recargar se le devuelve el foco.
            if (porEnter && input.id) {
                try { sessionStorage.setItem(CLAVE_FOCO, input.id); } catch (e) { /* sin almacenamiento */ }
            }
            accion(valor);
        }

        input.addEventListener('change', function () { ejecutar(false); });   // al salir del cuadro
        input.addEventListener('keydown', function (evento) {
            if (evento.key === 'Enter') {
                evento.preventDefault();
                ejecutar(true);
            }
        });
        input.addEventListener('search', function () {                        // la ✕ de borrar
            if (input.value === '') { ejecutar(false); }
        });
    }

    document.querySelectorAll('input[data-buscar]').forEach(function (input) {
        activar(input, function (valor) {
            var url = new URL(window.location.href);
            var parametro = input.dataset.buscar;
            if (valor !== '') { url.searchParams.set(parametro, valor); } else { url.searchParams.delete(parametro); }
            url.searchParams.set(input.dataset.buscarPagina || 'pagina', '1');
            if (input.dataset.buscarAncla) { url.hash = input.dataset.buscarAncla; }
            window.location = url.toString();
        });
    });

    document.querySelectorAll('input[data-buscar-form]').forEach(function (input) {
        activar(input, function (valor) {
            if (valor !== '' && input.form) { input.form.submit(); }
        });
    });

    document.querySelectorAll('input[data-buscar-local]').forEach(function (input) {
        activar(input, function (valor) {
            input.dispatchEvent(new CustomEvent('sigebi:buscar', { detail: valor }));
        });
    });

    // Tras una búsqueda con Enter, el cursor vuelve al final del texto para seguir escribiendo.
    try {
        var id = sessionStorage.getItem(CLAVE_FOCO);
        if (id) {
            sessionStorage.removeItem(CLAVE_FOCO);
            var campo = document.getElementById(id);
            if (campo && campo.offsetParent !== null) {
                campo.focus();
                campo.setSelectionRange(campo.value.length, campo.value.length);
            }
        }
    } catch (e) { /* sin almacenamiento: no pasa nada */ }
})();
