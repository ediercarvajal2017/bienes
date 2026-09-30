/**
 * "Cartera recibida de la Alcaldía" (views/cartera/_campos.php): al elegir el funcionario
 * que solicitó la cartera, su correo (de MIA) se escribe en "Correo del funcionario que la
 * solicitó". Si el usuario ya escribió otro correo a mano, no se le borra.
 */
(function () {
    var selector = document.querySelector('select[data-correos]');
    if (!selector) { return; }
    var destino = document.getElementById(selector.getAttribute('data-correo-destino'));
    if (!destino) { return; }

    var correos = {};
    try { correos = JSON.parse(selector.getAttribute('data-correos') || '{}'); } catch (e) { correos = {}; }
    var ultimoAutomatico = correos[selector.value] || '';

    selector.addEventListener('change', function () {
        var correo = correos[selector.value] || '';
        var actual = destino.value.trim();
        if (actual === '' || actual === ultimoAutomatico) {
            destino.value = correo;
        }
        ultimoAutomatico = correo;
    });
})();
