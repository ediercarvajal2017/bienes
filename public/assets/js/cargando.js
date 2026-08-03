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
        if (!(form instanceof HTMLFormElement) || !requiereOverlay(form)) { return; }

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
})();
