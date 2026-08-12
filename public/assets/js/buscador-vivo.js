/**
 * Los buscadores de las pantallas de listado (bienes, asignaciones, reintegros,
 * espacios, usuarios, cargas, QR masivo, verificación) recargan la página completa
 * 450ms después de que el usuario deja de escribir — eso reemplaza el <input> por uno
 * nuevo, así que el navegador le quita el foco y el cursor, y el usuario tiene que
 * volver a hacer clic para seguir escribiendo.
 *
 * Este script corre en cada carga de página y, si un <input type="search"> ya trae un
 * valor (viene de una búsqueda que el propio usuario disparó), le devuelve el foco y
 * pone el cursor al final del texto — así puede seguir escribiendo sin interrupción.
 * Se aplica solo por atributo (type="search"), sin inicialización aparte: cualquier
 * buscador nuevo que use ese tipo de input queda cubierto automáticamente.
 *
 * En pantallas con más de un buscador a la vez (ver verificaciones/mostrar.php, que
 * tiene tres, uno por pestaña), esto no elige "el correcto" a propósito — simplemente
 * intenta enfocar todos los que tengan valor, y el navegador ignora los .focus() sobre
 * inputs ocultos (pestañas no activas), así que en la práctica solo el buscador de la
 * pestaña visible termina recibiendo el foco.
 */
(function () {
    document.querySelectorAll('input[type="search"]').forEach(function (input) {
        if (input.value === '') { return; }

        input.focus();
        input.setSelectionRange(input.value.length, input.value.length);
    });
})();
