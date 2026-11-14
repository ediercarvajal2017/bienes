/**
 * Estado "procesando" de los formularios, por delegación (cualquier formulario nuevo lo tiene
 * sin inicializarlo):
 *  - el botón que envió queda deshabilitado con un indicador y el resto de botones de envío,
 *    deshabilitados (evita el doble envío);
 *  - los formularios que suben archivos (enctype multipart) muestran además un aviso a
 *    pantalla completa, porque la subida puede tardar.
 * La confirmación previa (data-confirmar) la hace confirmar.js.
 *
 * Se excluye un formulario con target (la respuesta abre en otra pestaña y la página no
 * navega) y uno marcado con data-sin-cargando.
 */
(function () {
    var overlay = null;

    // Indicador + texto plano (nunca HTML: el texto puede venir de datos).
    function conIndicador(elemento, texto) {
        var indicador = document.createElement('span');
        indicador.className = 'spinner-border spinner-border-sm me-1';
        indicador.setAttribute('aria-hidden', 'true');
        elemento.replaceChildren(indicador, document.createTextNode(texto));
    }

    function mostrarOverlay() {
        if (!overlay) {
            overlay = document.createElement('div');
            overlay.id = 'cargandoOverlay';
            overlay.className = 'cargando-overlay';
            overlay.setAttribute('role', 'status');
            overlay.innerHTML = '<div class="cargando-caja">'
                + '<div class="spinner-border text-primary mb-3" aria-hidden="true"></div>'
                + '<div class="fw-semibold">Procesando…</div>'
                + '<div class="text-muted small mt-1">No cierres ni recargues esta página.</div>'
                + '</div>';
            document.body.appendChild(overlay);
        }
        overlay.classList.add('visible');
    }

    function marcarProcesando(form, enviador, texto) {
        form.querySelectorAll('button[type="submit"], input[type="submit"]').forEach(function (boton) {
            boton.disabled = true;
        });
        if (enviador && enviador.tagName === 'BUTTON') {
            enviador.dataset.textoOriginal = enviador.innerHTML;
            conIndicador(enviador, texto || enviador.textContent.trim());
        }
    }

    function restaurar(form) {
        form.querySelectorAll('button[type="submit"], input[type="submit"]').forEach(function (boton) {
            boton.disabled = false;
            if (boton.dataset.textoOriginal) {
                boton.innerHTML = boton.dataset.textoOriginal;
                delete boton.dataset.textoOriginal;
            }
        });
    }

    document.addEventListener('submit', function (evento) {
        var form = evento.target;
        if (!(form instanceof HTMLFormElement) || form.hasAttribute('data-sin-cargando')) { return; }
        // Un formulario method="dialog" solo cierra un <dialog> (p. ej. el modal de confirmación).
        if ((form.getAttribute('method') || '').toLowerCase() === 'dialog') { return; }
        var enviador = evento.submitter || form.querySelector('button[type="submit"]');

        // Al siguiente ciclo: si otro manejador (validación de la vista) canceló el envío,
        // no se marca nada.
        window.setTimeout(function () {
            if (evento.defaultPrevented) { return; }

            if (form.hasAttribute('target')) {
                // Abre en otra pestaña (PDF, QR masivo): se reactiva al volver a esta pestaña.
                marcarProcesando(form, enviador, 'Generando…');
                var reactivar = function () {
                    restaurar(form);
                    window.removeEventListener('focus', reactivar);
                };
                window.addEventListener('focus', reactivar);
                window.setTimeout(reactivar, 20000);
                return;
            }

            marcarProcesando(form, enviador, null);
            if ((form.getAttribute('enctype') || '').toLowerCase() === 'multipart/form-data') {
                mostrarOverlay();
            }
        }, 0);
    });

    // Volver con el botón "atrás" muestra la página desde la caché del navegador: sin esto
    // los botones quedarían deshabilitados.
    window.addEventListener('pageshow', function (evento) {
        if (!evento.persisted) { return; }
        document.querySelectorAll('form').forEach(restaurar);
        if (overlay) { overlay.classList.remove('visible'); }
    });

    /**
     * Enlaces de descarga (reportes .xlsx/.csv): <a data-descarga="texto mientras genera">
     * descargan sin navegar; se deshabilitan un momento para evitar el doble clic.
     */
    document.addEventListener('click', function (evento) {
        var enlace = evento.target.closest('a[data-descarga]');
        if (!enlace) { return; }

        if (enlace.classList.contains('disabled')) {
            evento.preventDefault();
            return;
        }

        var textoOriginal = enlace.innerHTML;
        enlace.classList.add('disabled');
        enlace.setAttribute('aria-disabled', 'true');
        conIndicador(enlace, enlace.dataset.descarga);

        window.setTimeout(function () {
            enlace.classList.remove('disabled');
            enlace.removeAttribute('aria-disabled');
            enlace.innerHTML = textoOriginal;
        }, 3000);
    });

    /**
     * Selección masiva (asignar, QR masivo, generar lote): tocar cualquier parte de la celda
     * marca la casilla (también la de "seleccionar todos"). En el celular la celda ocupa todo
     * el ancho de la tarjeta, así el área táctil es la fila completa.
     */
    document.addEventListener('click', function (evento) {
        var celda = evento.target.closest('td, th');
        if (!celda || evento.target.closest('input, a, button, label, select, textarea')) { return; }
        var casilla = celda.querySelector(':scope > input[type="checkbox"]');
        if (!casilla || casilla.disabled) { return; }
        casilla.checked = !casilla.checked;
        casilla.dispatchEvent(new Event('change', { bubbles: true }));
    });

    /**
     * Botón que muestra/oculta un bloque (p. ej. los filtros en el celular):
     * <button data-alternar="idDelBloque" aria-expanded="false">. El bloque usa la clase
     * "abierto"; el CSS decide en qué tamaños se oculta.
     */
    document.addEventListener('click', function (evento) {
        var boton = evento.target.closest('[data-alternar]');
        if (!boton) { return; }
        var bloque = document.getElementById(boton.getAttribute('data-alternar'));
        if (!bloque) { return; }
        var abierto = bloque.classList.toggle('abierto');
        boton.setAttribute('aria-expanded', abierto ? 'true' : 'false');
    });
})();
