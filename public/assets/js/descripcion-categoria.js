/**
 * Descripción del bien en mayúscula o minúscula según la categoría. Es la misma regla de
 * Bien::descripcionSegunCategoria (servidor), que es la que vale al guardar: "Sin cartera"
 * en minúscula; cualquier otra categoría en mayúscula; sin categoría, como se escriba.
 *
 * Mientras se escribe solo cambia cómo se VE (CSS text-transform): reescribir el texto en
 * cada tecla hace saltar el cursor y, en los teclados del celular con autocorrector, duplica
 * o pierde letras. El texto se convierte de verdad al salir del campo, al cambiar la
 * categoría y al enviar el formulario.
 *
 * Uso: <input data-descripcion-segun-categoria="<id del select de categoría>">
 */
(function () {
    function iniciar(selectCategoria, campo) {
        function modo() {
            const opcion = selectCategoria.options[selectCategoria.selectedIndex];
            if (!opcion || !opcion.value) {
                return '';
            }
            // Tom Select deja espacios y saltos de línea alrededor del texto de la opción.
            return opcion.text.trim().toLowerCase() === 'sin cartera' ? 'minuscula' : 'mayuscula';
        }

        function aplicar() {
            const m = modo();
            campo.style.textTransform = m === 'minuscula' ? 'lowercase' : (m === 'mayuscula' ? 'uppercase' : '');
            if (m === '') {
                return;
            }
            const nuevo = m === 'minuscula' ? campo.value.toLocaleLowerCase('es') : campo.value.toLocaleUpperCase('es');
            if (nuevo !== campo.value) {
                campo.value = nuevo;
            }
        }

        selectCategoria.addEventListener('change', aplicar);
        campo.addEventListener('blur', aplicar);
        if (campo.form) {
            campo.form.addEventListener('submit', aplicar);
        }
        aplicar();
    }

    document.addEventListener('DOMContentLoaded', function () {
        document.querySelectorAll('[data-descripcion-segun-categoria]').forEach(function (campo) {
            const select = document.getElementById(campo.dataset.descripcionSegunCategoria);
            if (select) {
                iniciar(select, campo);
            }
        });
    });
})();
