/**
 * Ayuda emergente de los campos (App\Helpers\AyudaCampo): el ícono (?) junto al título de
 * un campo muestra su globo de ayuda.
 *  - Con el cursor: se abre al pasar por encima y sigue abierto mientras el cursor esté
 *    sobre el ícono o sobre el globo (se puede leer y seleccionar el texto).
 *  - Con el teclado: se abre al llegar con Tab y se cierra al salir o con Escape.
 *  - En el celular: se abre y se cierra tocando el ícono; tocar fuera lo cierra.
 * Solo hay un globo abierto a la vez. El globo usa position: fixed y se ubica debajo del
 * ícono (encima si no cabe), sin salirse de la pantalla ni quedar tras la barra inferior.
 * Sin scripts en línea, por la política de contenido.
 */
(function () {
    var ESPERA_ABRIR = 150;
    var ESPERA_CERRAR = 200;
    var MARGEN = 12;
    var SEPARACION = 8;

    var abierto = null; // { boton, globo, por: 'cursor' | 'clic' | 'teclado' }
    var temporizador = null;

    function globoDe(boton) {
        return document.getElementById(boton.getAttribute('aria-controls'));
    }

    function altoBarraInferior() {
        var valor = getComputedStyle(document.documentElement).getPropertyValue('--alto-barra-inferior');
        return parseFloat(valor) || 0;
    }

    function posicionar(boton, globo) {
        var r = boton.getBoundingClientRect();
        var anchoVista = document.documentElement.clientWidth;
        var altoVista = window.innerHeight - altoBarraInferior();
        globo.style.left = '0px';
        globo.style.top = '0px';
        var ancho = globo.offsetWidth;
        var alto = globo.offsetHeight;

        var centro = r.left + r.width / 2;
        var izquierda = Math.max(MARGEN, Math.min(centro - 22, anchoVista - ancho - MARGEN));
        var abajo = r.bottom + SEPARACION;
        var arriba = r.top - SEPARACION - alto;
        var ponerArriba = abajo + alto > altoVista - MARGEN && arriba >= MARGEN;

        globo.style.left = izquierda + 'px';
        globo.style.top = (ponerArriba ? arriba : abajo) + 'px';
        globo.classList.toggle('arriba', ponerArriba);
        globo.style.setProperty('--flecha-x', Math.max(10, Math.min(centro - izquierda, ancho - 10)) + 'px');
    }

    function cancelarEspera() {
        window.clearTimeout(temporizador);
        temporizador = null;
    }

    function abrir(boton, por) {
        cancelarEspera();
        if (abierto && abierto.boton === boton) { return; }
        cerrar();
        var globo = globoDe(boton);
        if (!globo) { return; }
        globo.hidden = false;
        boton.setAttribute('aria-expanded', 'true');
        posicionar(boton, globo);
        abierto = { boton: boton, globo: globo, por: por };
    }

    function cerrar() {
        cancelarEspera();
        if (!abierto) { return; }
        abierto.globo.hidden = true;
        abierto.boton.setAttribute('aria-expanded', 'false');
        abierto = null;
    }

    function despues(ms, accion) {
        cancelarEspera();
        temporizador = window.setTimeout(accion, ms);
    }

    function botonDe(objetivo) {
        return objetivo && objetivo.closest ? objetivo.closest('[data-ayuda-campo]') : null;
    }

    function dentroDelGlobo(objetivo) {
        return abierto !== null && objetivo instanceof Node && abierto.globo.contains(objetivo);
    }

    // Cursor (solo ratón: en las pantallas táctiles el toque se maneja con el clic).
    document.addEventListener('pointerover', function (e) {
        if (e.pointerType !== 'mouse') { return; }
        var boton = botonDe(e.target);
        if (boton) {
            if (abierto && abierto.boton === boton) { cancelarEspera(); } else { despues(ESPERA_ABRIR, function () { abrir(boton, 'cursor'); }); }
        } else if (dentroDelGlobo(e.target)) {
            cancelarEspera();
        }
    });
    document.addEventListener('pointerout', function (e) {
        if (e.pointerType !== 'mouse') { return; }
        var desde = botonDe(e.target) || (dentroDelGlobo(e.target) ? abierto.globo : null);
        if (!desde) { return; }
        var hacia = e.relatedTarget;
        if (hacia instanceof Node && (desde.contains(hacia) || dentroDelGlobo(hacia) || (abierto && abierto.boton.contains(hacia)))) { return; }
        // Abierto con un clic o con el teclado: queda fijo hasta cerrarlo.
        if (abierto && abierto.por !== 'cursor') { return; }
        despues(ESPERA_CERRAR, cerrar);
    });

    // Clic o toque: abre y deja fijo; otro clic lo cierra. Si ya estaba abierto por el
    // cursor, el clic lo deja fijo en lugar de cerrarlo.
    document.addEventListener('click', function (e) {
        var boton = botonDe(e.target);
        if (boton) {
            e.preventDefault();
            if (abierto && abierto.boton === boton) {
                if (abierto.por === 'cursor') { cancelarEspera(); abierto.por = 'clic'; } else { cerrar(); }
            } else {
                abrir(boton, 'clic');
            }
            return;
        }
        if (abierto && !dentroDelGlobo(e.target)) { cerrar(); }
    });

    // Teclado.
    document.addEventListener('focusin', function (e) {
        var boton = botonDe(e.target);
        if (boton && e.target.matches(':focus-visible')) { abrir(boton, 'teclado'); }
    });
    document.addEventListener('focusout', function (e) {
        // Solo el abierto con el teclado: el de un clic se cierra con otro clic o tocando fuera.
        if (abierto && abierto.por === 'teclado' && botonDe(e.target) === abierto.boton) { cerrar(); }
    });
    document.addEventListener('keydown', function (e) {
        if (e.key === 'Escape' && abierto) {
            cerrar();
        }
    });

    function reubicar() {
        if (abierto) { posicionar(abierto.boton, abierto.globo); }
    }
    window.addEventListener('resize', reubicar);
    window.addEventListener('scroll', reubicar, true);
})();
