/**
 * Lightbox simple: cualquier elemento con data-lightbox-src abre esa imagen en
 * grande sobre un fondo oscuro. Se activa por delegación de eventos en el
 * documento, así que funciona con cualquier miniatura de la página sin
 * inicialización adicional — basta con agregar el atributo.
 *
 * Accesible: las miniaturas se pueden abrir con Enter o la barra espaciadora, el
 * visor es un diálogo con botón de cerrar (y Escape), muestra el texto alternativo de
 * la miniatura y devuelve el foco al elemento que lo abrió.
 *
 * Celular: el aspecto va en app.css (.visor-foto). Antes iba en línea con «inset:0», que
 * los iPhone con iOS anterior a 14.5 no conocen: el visor quedaba sin tamaño y fuera de
 * la vista, y la foto «no se abría».
 */
(function () {
    let overlay = null;
    let imagen = null;
    let botonCerrar = null;
    let origen = null;

    function crearOverlay() {
        overlay = document.createElement('div');
        overlay.id = 'lightboxOverlay';
        overlay.className = 'visor-foto';
        overlay.hidden = true;
        overlay.setAttribute('role', 'dialog');
        overlay.setAttribute('aria-modal', 'true');
        overlay.setAttribute('aria-label', 'Foto ampliada');

        imagen = document.createElement('img');
        imagen.className = 'visor-foto-imagen';

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
        overlay.hidden = false;
        botonCerrar.focus();
    }

    function cerrar() {
        if (!overlay || overlay.hidden) { return; }
        overlay.hidden = true;
        if (origen) { origen.focus(); origen = null; }
    }

    // Las miniaturas se vuelven enfocables con el teclado. El escuchador propio (vacío: abre
    // el del documento) hace que Safari del iPhone las trate como tocables y envíe el clic.
    function prepararMiniaturas() {
        document.querySelectorAll('[data-lightbox-src]:not([tabindex])').forEach(function (el) {
            if (el.tagName !== 'A' && el.tagName !== 'BUTTON') {
                el.setAttribute('tabindex', '0');
                el.setAttribute('role', 'button');
            }
            el.addEventListener('click', function () {});
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
        if (overlay && !overlay.hidden && evento.key === 'Tab') {
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
