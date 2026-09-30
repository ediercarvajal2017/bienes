/**
 * Campos "Responsabilidad" de un bien (partials/campos_responsabilidad.php):
 *  - Grupal: pide el espacio (obligatorio salvo al registrar un bien).
 *  - Individual: pide la persona responsable y deja el espacio como "Guardado en
 *    (espacio, opcional)".
 * Los campos ocultos quedan deshabilitados (ni se validan ni se envían). En la ficha del
 * bien, el menú de acciones también habilita o deshabilita estos campos: aquí solo se
 * habilita lo que además está visible.
 */
(function () {
    function textoOpcionVacia(contenedor, tipo) {
        return tipo === 'individual' ? '-- Sin espacio --' : (contenedor.getAttribute('data-texto-sin-espacio') || '-- Selecciona --');
    }

    function aplicar(contenedor) {
        var marcado = contenedor.querySelector('[data-tipo-responsabilidad]:checked');
        var tipo = marcado ? marcado.value : 'grupal';

        contenedor.querySelectorAll('[data-solo-tipo]').forEach(function (bloque) {
            bloque.hidden = bloque.getAttribute('data-solo-tipo') !== tipo;
        });
        contenedor.querySelectorAll('[data-solo-tipo] select').forEach(function (campo) {
            var apagado = campo.closest('[hidden]') !== null;
            campo.disabled = apagado;
            if (campo.tomselect) {
                if (apagado) { campo.tomselect.disable(); } else { campo.tomselect.enable(); }
            }
        });

        var espacio = contenedor.querySelector('[data-campo-espacio]');
        var etiqueta = contenedor.querySelector('[data-etiqueta-espacio]');
        var obligatorio = tipo === 'grupal' && contenedor.hasAttribute('data-espacio-obligatorio');
        var textoEtiqueta = tipo === 'individual' ? 'Guardado en (espacio, opcional)' : 'Espacio';
        if (etiqueta) {
            etiqueta.textContent = textoEtiqueta;
            etiqueta.classList.toggle('requerido', obligatorio);
        }
        if (espacio) {
            espacio.required = obligatorio;
            espacio.setAttribute('aria-label', textoEtiqueta);
            var vacia = espacio.querySelector('option[value=""]');
            var texto = textoOpcionVacia(contenedor, tipo);
            if (vacia) { vacia.textContent = texto; }
            if (espacio.tomselect) { espacio.tomselect.updateOption('', { value: '', text: texto }); }
        }
    }

    document.querySelectorAll('[data-responsabilidad]').forEach(function (contenedor) {
        contenedor.addEventListener('change', function (evento) {
            if (evento.target.matches('[data-tipo-responsabilidad]')) { aplicar(contenedor); }
        });
        aplicar(contenedor);
    });
})();
