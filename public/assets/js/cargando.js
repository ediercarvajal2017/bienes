/**
 * Overlay de "procesando..." para cualquier formulario de la aplicación — antes solo
 * cubría los que suben archivos, dejando sin ninguna señal de progreso a los demás
 * (crear/editar sin foto, activar/desactivar, eliminar...). Se activa por delegación
 * de eventos en el documento — cualquier formulario nuevo lo obtiene automáticamente,
 * sin inicialización aparte.
 *
 * Se excluye automáticamente un formulario con target="_blank" (o cualquier target):
 * como la respuesta abre en otra pestaña, la página actual nunca navega y el overlay
 * se quedaría pegado en pantalla sin que nada lo cierre. Un formulario puntual que por
 * algún otro motivo no deba mostrarlo puede marcarse con data-sin-cargando.
 *
 * También deshabilita el botón de envío para evitar doble clic/doble envío mientras
 * la petición está en curso.
 */
(function () {
    let overlay = null;

    function crearOverlay() {
        overlay = document.createElement('div');
        overlay.id = 'cargandoOverlay';
        overlay.style.cssText = [
            'display:none', 'position:fixed', 'inset:0', 'z-index:1090',
            'background:rgba(0,0,0,.6)', 'align-items:center', 'justify-content:center',
            'padding:24px',
        ].join(';');

        overlay.innerHTML = [
            '<div class="bg-white rounded-3 shadow p-4 text-center" style="max-width:320px;">',
            '  <div class="spinner-border text-primary mb-3" role="status" style="width:2.5rem;height:2.5rem;"></div>',
            '  <div class="fw-semibold">Procesando…</div>',
            '  <div class="text-muted small mt-1">No cierres ni recargues esta página.</div>',
            '</div>',
        ].join('');

        document.body.appendChild(overlay);
    }

    function mostrar() {
        if (!overlay) { crearOverlay(); }
        overlay.style.display = 'flex';
    }

    function requiereOverlay(form) {
        if (form.hasAttribute('data-sin-cargando')) { return false; }
        if (form.hasAttribute('target')) { return false; }

        return true;
    }

    document.addEventListener('submit', function (evento) {
        const form = evento.target;
        if (!(form instanceof HTMLFormElement)) { return; }

        if (!requiereOverlay(form)) {
            if (form.hasAttribute('target')) { manejarEnvioEnPestañaNueva(evento, form); }
            return;
        }

        // Si el formulario tiene su propia validación/confirm() que puede cancelar
        // el envío, esperamos al siguiente tick para no mostrar el overlay si el
        // usuario canceló el confirm().
        window.setTimeout(function () {
            if (evento.defaultPrevented) { return; }

            mostrar();
            form.querySelectorAll('button[type="submit"], input[type="submit"]').forEach(function (boton) {
                boton.disabled = true;
            });
        }, 0);
    });

    /**
     * Los formularios target="_blank" (ej. generar QR/PDF masivo) no reciben el overlay
     * de arriba porque la página actual nunca navega — pero sin ningún feedback, el botón
     * sigue habilitado mientras el PDF se genera en la pestaña nueva y se puede hacer clic
     * dos veces. Aquí solo se deshabilita el botón y se cambia su texto, reactivándolo
     * cuando el usuario vuelve a esta pestaña (evento "focus"), con un tope de tiempo por
     * si el navegador no dispara ese evento.
     */
    function manejarEnvioEnPestañaNueva(evento, form) {
        if (form.hasAttribute('data-sin-cargando')) { return; }

        window.setTimeout(function () {
            if (evento.defaultPrevented) { return; }

            const botones = form.querySelectorAll('button[type="submit"], input[type="submit"]');
            botones.forEach(function (boton) {
                boton.disabled = true;
                boton.dataset.textoOriginal = boton.innerHTML;
                boton.innerHTML = '<span class="spinner-border spinner-border-sm me-1" role="status"></span>Generando…';
            });

            const reactivar = function () {
                botones.forEach(function (boton) {
                    boton.disabled = false;
                    if (boton.dataset.textoOriginal) { boton.innerHTML = boton.dataset.textoOriginal; }
                });
                window.removeEventListener('focus', reactivar);
            };
            window.addEventListener('focus', reactivar);
            window.setTimeout(reactivar, 20000);
        }, 0);
    }

    /**
     * Enlaces de descarga (reportes .xlsx/.csv): no son formularios ni target="_blank",
     * son <a> planos que descargan un archivo sin navegar fuera de la página. Se marcan
     * con data-descarga="texto mientras genera" para dar el mismo tipo de feedback breve
     * y evitar doble clic mientras el archivo se genera en el servidor.
     */
    document.addEventListener('click', function (evento) {
        const enlace = evento.target.closest('a[data-descarga]');
        if (!enlace) { return; }

        if (enlace.classList.contains('disabled')) {
            evento.preventDefault();
            return;
        }

        const textoOriginal = enlace.innerHTML;
        enlace.classList.add('disabled');
        enlace.setAttribute('aria-disabled', 'true');
        enlace.innerHTML = '<span class="spinner-border spinner-border-sm me-1" role="status"></span>' + enlace.dataset.descarga;

        window.setTimeout(function () {
            enlace.classList.remove('disabled');
            enlace.removeAttribute('aria-disabled');
            enlace.innerHTML = textoOriginal;
        }, 3000);
    });
})();
