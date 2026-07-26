/**
 * Lightbox simple: cualquier elemento con data-lightbox-src abre esa imagen en
 * grande sobre un fondo oscuro. Se activa por delegación de eventos en el
 * documento, así que funciona con cualquier miniatura de la página sin
 * inicialización adicional — basta con agregar el atributo.
 */
(function () {
    let overlay = null;
    let imagen = null;

    function crearOverlay() {
        overlay = document.createElement('div');
        overlay.id = 'lightboxOverlay';
        overlay.style.cssText = [
            'display:none', 'position:fixed', 'inset:0', 'z-index:1080',
            'background:rgba(0,0,0,.85)', 'align-items:center', 'justify-content:center',
            'padding:24px', 'cursor:zoom-out',
        ].join(';');

        imagen = document.createElement('img');
        imagen.style.cssText = 'max-width:100%;max-height:100%;border-radius:8px;box-shadow:0 10px 40px rgba(0,0,0,.5);';
        overlay.appendChild(imagen);

        overlay.addEventListener('click', cerrar);
        document.body.appendChild(overlay);
    }

    function abrir(src) {
        if (!overlay) { crearOverlay(); }
        imagen.src = src;
        overlay.style.display = 'flex';
    }

    function cerrar() {
        if (overlay) { overlay.style.display = 'none'; }
    }

    document.addEventListener('click', function (evento) {
        const elemento = evento.target.closest('[data-lightbox-src]');
        if (!elemento) { return; }
        abrir(elemento.getAttribute('data-lightbox-src'));
    });

    document.addEventListener('keydown', function (evento) {
        if (evento.key === 'Escape') { cerrar(); }
    });
})();
