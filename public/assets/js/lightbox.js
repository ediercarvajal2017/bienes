/**
 * Lightbox simple: cualquier elemento con data-lightbox-src abre esa imagen en
 * grande sobre un fondo oscuro. Se activa por delegación de eventos en el
 * documento, así que funciona con cualquier miniatura de la página sin
 * inicialización adicional — basta con agregar el atributo.
 *
 * Accesible: las miniaturas se pueden abrir con Enter o la barra espaciadora, el
 * visor es un diálogo con botón de cerrar (y Escape), muestra el texto alternativo de
 * la miniatura y devuelve el foco al elemento que lo abrió.
 */
(function () {
    let overlay = null;
    let imagen = null;
    let botonCerrar = null;
    let origen = null;

    function crearOverlay() {
        overlay = document.createElement('div');
        overlay.id = 'lightboxOverlay';
        overlay.setAttribute('role', 'dialog');
        overlay.setAttribute('aria-modal', 'true');
        overlay.setAttribute('aria-label', 'Foto ampliada');
        overlay.style.cssText = [
            'display:none', 'position:fixed', 'inset:0', 'z-index:1080',
            'background:rgba(0,0,0,.85)', 'align-items:center', 'justify-content:center',
            'padding:24px', 'cursor:zoom-out',
        ].join(';');

        imagen = document.createElement('img');
        imagen.style.cssText = 'max-width:100%;max-height:100%;border-radius:8px;box-shadow:0 10px 40px rgba(0,0,0,.5);';

        botonCerrar = document.createElement('button');
        botonCerrar.type = 'button';
        botonCerrar.className = 'btn btn-light position-absolute top-0 end-0 m-3';
        botonCerrar.setAttribute('aria-label', 'Cerrar foto');
        botonCerrar.innerHTML = '<i class="bi bi-x-lg" aria-hidden="true"></i>';

        overlay.appendChild(imagen);
        overlay.appendChild(botonCerrar);
        overlay.addEventListener('click', cerrar);
        document.body.appendChild(overlay);
    }

    function abrir(elemento) {
        if (!overlay) { crearOverlay(); }
        origen = elemento;
        imagen.src = elemento.getAttribute('data-lightbox-src');
        imagen.alt = elemento.getAttribute('alt') || elemento.getAttribute('aria-label') || 'Foto ampliada';
        overlay.style.display = 'flex';
        botonCerrar.focus();
    }

    function cerrar() {
        if (!overlay || overlay.style.display === 'none') { return; }
        overlay.style.display = 'none';
        if (origen) { origen.focus(); origen = null; }
    }

    // Las miniaturas se vuelven enfocables con el teclado.
    function prepararMiniaturas() {
        document.querySelectorAll('[data-lightbox-src]:not([tabindex])').forEach(function (el) {
            if (el.tagName !== 'A' && el.tagName !== 'BUTTON') {
                el.setAttribute('tabindex', '0');
                el.setAttribute('role', 'button');
            }
        });
    }
    if (document.readyState === 'loading') {
        document.addEventListener('DOMContentLoaded', prepararMiniaturas);
    } else {
        prepararMiniaturas();
    }

    document.addEventListener('click', function (evento) {
        const elemento = evento.target.closest('[data-lightbox-src]');
        if (!elemento) { return; }
        abrir(elemento);
    });

    document.addEventListener('keydown', function (evento) {
        if (evento.key === 'Escape') { cerrar(); return; }
        if (overlay && overlay.style.display !== 'none' && evento.key === 'Tab') {
            evento.preventDefault(); // el foco se queda en el diálogo (solo tiene el botón de cerrar)
            botonCerrar.focus();
            return;
        }
        const elemento = evento.target.closest && evento.target.closest('[data-lightbox-src]');
        if (elemento && (evento.key === 'Enter' || evento.key === ' ')) {
            evento.preventDefault();
            abrir(elemento);
        }
    });
})();
