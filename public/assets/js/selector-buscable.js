/**
 * Convierte cada <select class="selector-buscable"> en un menu con buscador (Tom Select),
 * reservado para los que pueden llegar a tener muchas opciones (espacio/ubicacion,
 * responsables, categoria, institucion). Los select cortos (estado, rol, "por pagina",
 * etc.) no llevan esta clase y siguen siendo el <select> nativo de siempre.
 */
(function () {
    if (typeof TomSelect === 'undefined') { return; }

    document.querySelectorAll('select.selector-buscable').forEach(function (select) {
        new TomSelect(select, {
            plugins: select.multiple ? ['remove_button'] : [],
            placeholder: 'Buscar...',
            allowEmptyOption: true,
            maxOptions: null,
            onChange: function () {
                // El <select> original queda oculto pero sigue en el DOM; algunas pantallas
                // ya tenian su propio listener de 'change' sobre ese elemento (ej. el
                // selector de institucion que recarga la pagina) — se re-dispara aqui para
                // que sigan funcionando exactamente igual, sin tener que tocarlos.
                select.dispatchEvent(new Event('change', { bubbles: true }));
            },
        });
    });
})();
