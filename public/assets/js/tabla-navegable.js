/**
 * Tabla navegable como una planilla (ui('tabla', ['navegable' => true]), o cualquier recuadro
 * de tabla con el atributo data-navegable): en pantallas
 * grandes (desde 992 px, donde la tabla no se ve como tarjetas) se recorre con el teclado
 * celda por celda, como en Excel. En el celular no hace nada: siguen las tarjetas.
 *
 *  - Flechas: celda vecina. Inicio/Fin: primera o última celda de la fila. Ctrl+Inicio /
 *    Ctrl+Fin: primera o última celda de la página.
 *  - Av Pág / Re Pág: 10 filas. En la última fila, Av Pág pasa a la página siguiente (y deja
 *    el cursor en su primera fila); en la primera, Re Pág pasa a la anterior.
 *  - Enter o doble clic: abre la fila (data-url del <tr> o, si no tiene, su único enlace,
 *    como «Editar» o «Ver»). En las tablas para marcar varios
 *    (fila con casilla y sin data-url), Enter marca o desmarca la casilla. En una celda con
 *    varios botones o campos, Enter entra a ellos (flechas ← → para pasar de uno a otro,
 *    Escape para volver); dentro de un campo de texto las flechas mueven el cursor del texto.
 *  - Espacio: en una celda con un solo botón o foto, lo pulsa (p. ej. amplía la foto); si no,
 *    marca o desmarca la casilla de la fila.
 *  - Ctrl+C sin texto seleccionado: copia el contenido de la celda.
 *  - «/»: va al buscador de la página; Escape en el buscador vuelve a la tabla.
 *
 * Una sola celda se puede enfocar a la vez (tabindex="0"): Tab entra a la tabla en ella y el
 * siguiente Tab sale. La celda donde quedó el cursor se recuerda en la pestaña
 * (sessionStorage): al recargar o al volver de editar un bien (?editado=ID), sigue ahí.
 *
 * También:
 *  - Columnas inmovilizadas (data-columnas-fijas="N"): las N primeras quedan fijas al
 *    desplazarse a la derecha. Aquí se mide su ancho para ubicarlas (CSS: .tabla-fijas).
 *  - Vista compacta: el botón [data-densidad] la alterna y se recuerda en este computador
 *    (localStorage «mia.tabla.densidad»).
 * Sin scripts en línea, por la política de contenido.
 */
(function () {
    var ESCRITORIO = window.matchMedia('(min-width: 992px)');
    var SALTO = 10;
    var CLAVE_DENSIDAD = 'mia.tabla.densidad';
    var CLAVE_SALTO = 'mia.tabla.salto';
    var CONTROLES = 'a[href], button, input:not([type="hidden"]), select, textarea, summary, [data-lightbox-src]';

    function leer(almacen, clave) { try { return window[almacen].getItem(clave); } catch (e) { return null; } }
    function guardar(almacen, clave, valor) {
        try { if (valor === null) { window[almacen].removeItem(clave); } else { window[almacen].setItem(clave, valor); } } catch (e) { /* sin almacenamiento: no pasa nada */ }
    }
    function escribiendo(elemento) {
        return elemento && (elemento.isContentEditable || /^(INPUT|TEXTAREA|SELECT)$/.test(elemento.tagName));
    }
    // Un campo donde se escribe o se elige (no una casilla ni un botón): sus flechas son suyas.
    function campoDeTexto(elemento) {
        if (elemento.tagName === 'TEXTAREA' || elemento.tagName === 'SELECT') { return true; }
        return elemento.tagName === 'INPUT' && !/^(checkbox|radio|button|submit|reset|image|file)$/i.test(elemento.type);
    }
    function casillaDe(fila) { return fila.querySelector('input[type="checkbox"]:not(:disabled)'); }

    function iniciar(contenedor, indice) {
        var tabla = contenedor.querySelector('table');
        if (!tabla || !tabla.tBodies[0]) { return; }
        var cuerpo = tabla.tBodies[0];
        // Una por tabla: una página puede tener varias (p. ej. una verificación física).
        var clavePosicion = 'mia.tabla.posicion.' + window.location.pathname + (indice > 0 ? '#' + indice : '');
        var activa = null;
        var activado = false;

        function filas() {
            // Las filas de datos: sin la del «no hay resultados» (una sola celda con colspan) ni
            // las que oculta un buscador de la misma página (p. ej. la Papelera).
            return Array.prototype.filter.call(cuerpo.rows, function (fila) {
                return fila.cells.length > 1 && !fila.hidden && fila.style.display !== 'none';
            });
        }
        function controlesDe(celda) { return celda ? celda.querySelectorAll(CONTROLES) : []; }

        function marcar(celda) {
            if (activa === celda) { return; }
            if (activa) {
                activa.removeAttribute('tabindex');
                activa.parentElement.classList.remove('fila-activa');
            }
            activa = celda;
            activa.setAttribute('tabindex', '0');
            activa.parentElement.classList.add('fila-activa');
            var fila = activa.parentElement;
            guardar('sessionStorage', clavePosicion, JSON.stringify({
                fila: fila.getAttribute('data-id') || String(filas().indexOf(fila)),
                columna: activa.cellIndex,
            }));
        }

        function enfocar(celda) {
            if (!celda) { return; }
            marcar(celda);
            celda.focus({ preventScroll: true });
            asegurarVisible(celda);
        }

        // Que la celda se vea entera dentro del recuadro, sin quedar tapada por el encabezado
        // fijo ni por las columnas inmovilizadas, y que el recuadro se vea en la página.
        function asegurarVisible(celda) {
            var caja = contenedor.getBoundingClientRect();
            var r = celda.getBoundingClientRect();
            var altoEncabezado = tabla.tHead ? tabla.tHead.getBoundingClientRect().height : 0;
            if (r.top < caja.top + altoEncabezado) {
                contenedor.scrollTop -= (caja.top + altoEncabezado) - r.top;
            } else if (r.bottom > caja.bottom) {
                contenedor.scrollTop += r.bottom - caja.bottom;
            }
            var fijas = contenedor.classList.contains('tabla-fijas-activas')
                ? parseInt(contenedor.getAttribute('data-columnas-fijas') || '0', 10) : 0;
            if (celda.cellIndex >= fijas) {
                var anchoFijo = fijas > 0 ? tabla.tHead.rows[0].cells[fijas - 1].getBoundingClientRect().right - caja.left : 0;
                if (r.left < caja.left + anchoFijo) {
                    contenedor.scrollLeft -= (caja.left + anchoFijo) - r.left;
                } else if (r.right > caja.right) {
                    contenedor.scrollLeft += r.right - caja.right;
                }
            }
            r = celda.getBoundingClientRect();
            if (r.top < 0 || r.bottom > window.innerHeight) {
                celda.scrollIntoView({ block: 'nearest' });
            }
        }

        function celdaEn(indiceFila, columna) {
            var lista = filas();
            if (lista.length === 0) { return null; }
            var fila = lista[Math.max(0, Math.min(indiceFila, lista.length - 1))];
            return fila.cells[Math.max(0, Math.min(columna, fila.cells.length - 1))];
        }

        function irAPagina(rel, foco) {
            var enlace = document.querySelector('a.page-link[aria-label="Página ' + rel + '"]:not([aria-disabled="true"])');
            if (!enlace) { return false; }
            guardar('sessionStorage', CLAVE_SALTO, JSON.stringify({ ruta: window.location.pathname, foco: foco, columna: activa ? activa.cellIndex : 0 }));
            window.location = enlace.href;
            return true;
        }

        // Lo que abre una fila: su data-url o, si no tiene, su único enlace (Editar, Ver…).
        function abrir(fila) {
            if (!fila) { return false; }
            var url = fila.getAttribute('data-url');
            if (url) { window.location = url; return true; }
            var enlaces = fila.querySelectorAll('a[href]');
            if (enlaces.length === 1) { enlaces[0].click(); return true; }
            return false;
        }

        // Prepara la tabla en pantallas grandes (o la deja como estaba en el celular).
        function preparar() {
            var escritorio = ESCRITORIO.matches;
            if (escritorio === activado) { return; }
            activado = escritorio;
            if (escritorio) {
                tabla.setAttribute('role', 'grid');
                // El recuadro ya no es una parada de Tab aparte: la tabla tiene la suya.
                if (contenedor.getAttribute('tabindex') === '0') { contenedor.setAttribute('data-tabindex-original', '0'); contenedor.removeAttribute('tabindex'); }
                cuerpo.querySelectorAll(CONTROLES).forEach(function (control) {
                    control.setAttribute('data-tabindex-original', control.getAttribute('tabindex') || '');
                    control.setAttribute('tabindex', '-1');
                });
                var lista = filas();
                var inicial = parseInt(contenedor.getAttribute('data-fila-inicial') || '0', 10);
                lista.forEach(function (fila, i) { fila.setAttribute('aria-rowindex', String(inicial + i + 2)); });
                if (tabla.tHead) { tabla.tHead.rows[0].setAttribute('aria-rowindex', '1'); }
                var total = contenedor.getAttribute('data-total-filas');
                if (total) { tabla.setAttribute('aria-rowcount', String(parseInt(total, 10) + 1)); }
                restaurar();
            } else {
                tabla.removeAttribute('role');
                tabla.removeAttribute('aria-rowcount');
                if (contenedor.getAttribute('data-tabindex-original') === '0') { contenedor.setAttribute('tabindex', '0'); }
                cuerpo.querySelectorAll('[data-tabindex-original]').forEach(function (control) {
                    var original = control.getAttribute('data-tabindex-original');
                    if (original === '') { control.removeAttribute('tabindex'); } else { control.setAttribute('tabindex', original); }
                    control.removeAttribute('data-tabindex-original');
                });
                if (activa) {
                    activa.removeAttribute('tabindex');
                    activa.parentElement.classList.remove('fila-activa');
                    activa = null;
                }
            }
        }

        // Dónde queda el cursor al cargar: tras Av/Re Pág, en la fila del bien recién editado
        // o donde estaba antes de recargar; si no, en la primera celda (sin robar el foco).
        function restaurar() {
            var lista = filas();
            if (lista.length === 0) { return; }
            var guardada = null;
            try { guardada = JSON.parse(leer('sessionStorage', clavePosicion) || 'null'); } catch (e) { guardada = null; }
            var columna = guardada && typeof guardada.columna === 'number' ? guardada.columna : 1;

            var salto = null;
            try { salto = JSON.parse(leer('sessionStorage', CLAVE_SALTO) || 'null'); } catch (e) { salto = null; }
            if (salto && salto.ruta === window.location.pathname) {
                guardar('sessionStorage', CLAVE_SALTO, null);
                enfocar(celdaEn(salto.foco === 'fin' ? lista.length - 1 : 0, salto.columna));
                return;
            }

            var editada = cuerpo.querySelector('tr#bienEditado, tr.fila-editada');
            if (editada && lista.indexOf(editada) !== -1) {
                enfocar(celdaEn(lista.indexOf(editada), columna));
                return;
            }

            var indice = 0;
            if (guardada) {
                var porId = guardada.fila ? cuerpo.querySelector('tr[data-id="' + String(guardada.fila).replace(/[^0-9]/g, '') + '"]') : null;
                if (porId && lista.indexOf(porId) !== -1) {
                    indice = lista.indexOf(porId);
                } else if (/^[0-9]+$/.test(String(guardada.fila))) {
                    indice = Math.min(parseInt(guardada.fila, 10), lista.length - 1);
                }
            }
            marcar(celdaEn(indice, columna));
        }

        cuerpo.addEventListener('keydown', function (e) {
            if (!activado) { return; }
            var celda = e.target.closest('td, th');
            if (!celda || !cuerpo.contains(celda)) { return; }
            var dentroDeControl = e.target !== celda;
            var fila = celda.parentElement;
            var lista = filas();
            var i = lista.indexOf(fila);
            if (i === -1) { return; }
            var col = celda.cellIndex;
            var destino = null;

            // Dentro de un botón de la celda: ← → entre los botones, Escape vuelve a la celda.
            // Dentro de un campo de texto, todas las teclas son del campo (menos Escape).
            if (dentroDeControl && campoDeTexto(e.target)) {
                if (e.key === 'Escape') { e.preventDefault(); enfocar(celda); }
                return;
            }
            if (dentroDeControl) {
                var controles = Array.prototype.slice.call(controlesDe(celda));
                var j = controles.indexOf(e.target);
                if (e.key === 'Escape') { e.preventDefault(); enfocar(celda); return; }
                if ((e.key === 'ArrowRight' || e.key === 'ArrowLeft') && controles.length > 1 && j !== -1) {
                    e.preventDefault();
                    controles[(j + (e.key === 'ArrowRight' ? 1 : controles.length - 1)) % controles.length].focus();
                    return;
                }
                if (e.key !== 'ArrowUp' && e.key !== 'ArrowDown') { return; } // Enter y Espacio: los del botón
            }

            switch (e.key) {
                case 'ArrowDown': destino = celdaEn(i + 1, col); break;
                case 'ArrowUp': destino = celdaEn(i - 1, col); break;
                case 'ArrowRight': destino = celdaEn(i, col + 1); break;
                case 'ArrowLeft': destino = celdaEn(i, col - 1); break;
                case 'Home': destino = e.ctrlKey ? celdaEn(0, 0) : celdaEn(i, 0); break;
                case 'End': destino = e.ctrlKey ? celdaEn(lista.length - 1, fila.cells.length - 1) : celdaEn(i, fila.cells.length - 1); break;
                case 'PageDown':
                    if (i === lista.length - 1 && irAPagina('siguiente', 'inicio')) { e.preventDefault(); return; }
                    destino = celdaEn(i + SALTO, col);
                    break;
                case 'PageUp':
                    if (i === 0 && irAPagina('anterior', 'fin')) { e.preventDefault(); return; }
                    destino = celdaEn(i - SALTO, col);
                    break;
                case 'Enter': {
                    e.preventDefault();
                    var varios = controlesDe(celda);
                    if (varios.length > 1) { varios[0].focus(); return; }
                    if (varios.length === 1 && campoDeTexto(varios[0])) { varios[0].focus(); return; }
                    if (fila.getAttribute('data-url')) { abrir(fila); return; }
                    var casilla = casillaDe(fila);
                    if (casilla) { casilla.click(); return; }
                    if (varios.length === 1) { varios[0].click(); return; }
                    abrir(fila);
                    return;
                }
                case ' ': {
                    var uno = controlesDe(celda);
                    if (uno.length === 1 && !campoDeTexto(uno[0])) { e.preventDefault(); uno[0].click(); return; }
                    var marcar2 = casillaDe(fila);
                    if (marcar2) { e.preventDefault(); marcar2.click(); }
                    return;
                }
                case 'c':
                case 'C':
                    if ((e.ctrlKey || e.metaKey) && String(window.getSelection()) === '' && navigator.clipboard) {
                        e.preventDefault();
                        navigator.clipboard.writeText(celda.innerText.trim()).catch(function () {});
                    }
                    return;
                default:
                    return;
            }
            e.preventDefault();
            enfocar(destino);
        });

        cuerpo.addEventListener('click', function (e) {
            if (!activado) { return; }
            // Solo los clics del ratón (detail ≥ 1): el que hace el propio script al marcar una
            // casilla con Enter no debe mover el cursor (la celda activa perdería el foco).
            if (e.detail === 0) { return; }
            var celda = e.target.closest('td, th');
            if (!celda || !cuerpo.contains(celda) || filas().indexOf(celda.parentElement) === -1) { return; }
            if (e.target.closest(CONTROLES)) { marcar(celda); return; } // el botón hace lo suyo
            if (String(window.getSelection()) !== '') { marcar(celda); return; } // seleccionando texto para copiar
            enfocar(celda);
        });

        cuerpo.addEventListener('dblclick', function (e) {
            if (!activado || e.target.closest(CONTROLES)) { return; }
            var fila = e.target.closest('tr');
            if (fila && filas().indexOf(fila) !== -1 && !casillaDe(fila)) { abrir(fila); }
        });

        // Si el foco llega a un botón de la celda por otra vía (p. ej. al cerrar la foto
        // ampliada), esa celda pasa a ser la activa.
        cuerpo.addEventListener('focusin', function (e) {
            if (!activado) { return; }
            var celda = e.target.closest('td, th');
            if (celda && filas().indexOf(celda.parentElement) !== -1) { marcar(celda); }
        });

        preparar();
        if (ESCRITORIO.addEventListener) { ESCRITORIO.addEventListener('change', preparar); }

        return {
            enfocarActiva: function () { if (activado && activa) { enfocar(activa); return true; } return false; },
        };
    }

    // Columnas inmovilizadas: las N primeras quedan a la izquierda al desplazarse. Se mide
    // cada una para saber dónde empieza la siguiente; si juntas ocupan más de la mitad del
    // recuadro (pantalla angosta o descripciones muy largas), no se inmovilizan: estorbarían.
    function ubicarFijas(contenedor) {
        var n = parseInt(contenedor.getAttribute('data-columnas-fijas') || '0', 10);
        var tabla = contenedor.querySelector('table');
        if (!n || !tabla || !tabla.tHead) { return; }
        var celdas = tabla.tHead.rows[0].cells;
        var izquierda = 0;
        for (var k = 0; k < n && k < celdas.length; k++) {
            tabla.style.setProperty('--fija-' + (k + 1), izquierda + 'px');
            izquierda += celdas[k].getBoundingClientRect().width;
        }
        var activas = ESCRITORIO.matches && izquierda <= contenedor.clientWidth * 0.55;
        contenedor.classList.toggle('tabla-fijas-activas', activas);
    }

    // Vista compacta: se aplica antes de medir las columnas (cambia los anchos).
    function aplicarDensidad(compacta) {
        document.querySelectorAll('[data-navegable]').forEach(function (contenedor) {
            contenedor.classList.toggle('tabla-compacta', compacta);
            ubicarFijas(contenedor);
        });
        document.querySelectorAll('[data-densidad]').forEach(function (boton) {
            boton.setAttribute('aria-pressed', compacta ? 'true' : 'false');
        });
    }

    var tablas = [];
    function arrancar() {
        var contenedores = document.querySelectorAll('[data-navegable]');
        if (contenedores.length === 0) { return; }
        aplicarDensidad(leer('localStorage', CLAVE_DENSIDAD) === 'compacta');
        contenedores.forEach(function (contenedor, indice) {
            var tabla = iniciar(contenedor, indice);
            if (tabla) { tablas.push(tabla); }
        });

        document.addEventListener('click', function (e) {
            var boton = e.target.closest('[data-densidad]');
            if (!boton) { return; }
            var compacta = boton.getAttribute('aria-pressed') !== 'true';
            guardar('localStorage', CLAVE_DENSIDAD, compacta ? 'compacta' : null);
            aplicarDensidad(compacta);
        });

        // «/» lleva al buscador; Escape en el buscador vuelve a la celda activa.
        var buscador = document.querySelector('input[data-buscar], input[data-buscar-local]');
        document.addEventListener('keydown', function (e) {
            if (!ESCRITORIO.matches) { return; }
            if (e.key === '/' && !e.ctrlKey && !e.metaKey && !e.altKey && buscador && !escribiendo(e.target)) {
                e.preventDefault();
                buscador.focus();
                buscador.select();
            } else if (e.key === 'Escape' && buscador && e.target === buscador && tablas.length > 0) {
                // Sin preventDefault el campo de búsqueda se borraría (y recargaría sin búsqueda).
                if (tablas[0].enfocarActiva()) { e.preventDefault(); }
            }
        });

        var espera = null;
        window.addEventListener('resize', function () {
            window.clearTimeout(espera);
            espera = window.setTimeout(function () { contenedores.forEach(ubicarFijas); }, 150);
        });
        window.addEventListener('load', function () { contenedores.forEach(ubicarFijas); });
    }

    if (document.readyState === 'loading') {
        document.addEventListener('DOMContentLoaded', arrancar);
    } else {
        arrancar();
    }
})();
