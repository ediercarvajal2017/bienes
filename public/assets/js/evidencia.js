/**
 * Bibliotecas de evidencia (partials/evidencia_institucion.php): al elegir la institución,
 * la ventana se recarga con ?institucion=ID (o sin ella, para ver todas).
 */
(function () {
    var selector = document.querySelector('[data-selector-institucion]');
    if (!selector) { return; }
    selector.addEventListener('change', function () {
        var destino = selector.getAttribute('data-selector-institucion');
        window.location = destino + (selector.value ? '?institucion=' + encodeURIComponent(selector.value) : '');
    });
})();
