/**
 * Confirmación de acciones con el modal de MIA (partials/ui/modal_confirmar.php), en lugar
 * del confirm() del navegador, que no se puede adaptar al diseño ni al tema oscuro.
 *
 *  - <form data-confirmar="¿…?">, o un botón de envío con data-confirmar: pide confirmación
 *    antes de enviar y luego envía con el mismo botón (conserva su name/value).
 *  - <a data-confirmar="¿…?">: pide confirmación antes de seguir el enlace.
 *  - window.miaConfirmar({ mensaje, titulo, boton, peligro }) → Promise<boolean>, para los
 *    scripts de las vistas.
 * Atributos opcionales: data-confirmar-boton (texto del botón), data-confirmar-tipo="peligro"
 * (botón rojo; el foco empieza en Cancelar) y data-confirmar-titulo.
 * Sin <dialog> (navegador muy antiguo) se usa el confirm() del navegador.
 */
(function () {
    var modal = document.getElementById('modalConfirmar');

    function confirmar(opciones) {
        var mensaje = opciones.mensaje || '¿Deseas continuar?';
        if (!modal || typeof modal.showModal !== 'function') {
            return Promise.resolve(window.confirm(mensaje));
        }
        var aceptar = modal.querySelector('[data-confirmar-aceptar]');
        var cancelar = modal.querySelector('[data-confirmar-cancelar]');
        var origen = document.activeElement;

        modal.querySelector('#modalConfirmarTitulo').textContent = opciones.titulo || 'Confirmar acción';
        modal.querySelector('#modalConfirmarMensaje').textContent = mensaje;
        aceptar.textContent = opciones.boton || 'Confirmar';
        aceptar.className = 'btn ' + (opciones.peligro ? 'btn-danger' : 'btn-primary');
        modal.returnValue = '';

        return new Promise(function (resolver) {
            modal.addEventListener('close', function alCerrar() {
                modal.removeEventListener('close', alCerrar);
                if (origen && typeof origen.focus === 'function') { origen.focus({ preventScroll: true }); }
                resolver(modal.returnValue === 'aceptar');
            });
            modal.showModal();
            (opciones.peligro ? cancelar : aceptar).focus();
        });
    }

    function opcionesDe(elemento) {
        return {
            mensaje: elemento.getAttribute('data-confirmar'),
            titulo: elemento.getAttribute('data-confirmar-titulo'),
            boton: elemento.getAttribute('data-confirmar-boton'),
            peligro: elemento.getAttribute('data-confirmar-tipo') === 'peligro'
        };
    }

    window.miaConfirmar = confirmar;

    // Formularios: se detiene el envío, se pregunta y, si se confirma, se vuelve a enviar
    // con el mismo botón. La marca data-confirmado evita volver a preguntar en ese envío.
    document.addEventListener('submit', function (evento) {
        var form = evento.target;
        if (!(form instanceof HTMLFormElement)) { return; }
        var boton = evento.submitter && evento.submitter.hasAttribute('data-confirmar') ? evento.submitter : null;
        var origen = boton || (form.hasAttribute('data-confirmar') ? form : null);
        if (!origen) { return; }
        if (form.dataset.confirmado === '1') {
            delete form.dataset.confirmado;
            return;
        }
        evento.preventDefault();
        evento.stopImmediatePropagation();
        confirmar(opcionesDe(origen)).then(function (si) {
            if (!si) { return; }
            form.dataset.confirmado = '1';
            if (typeof form.requestSubmit === 'function') {
                form.requestSubmit(evento.submitter || undefined);
            } else {
                form.submit();
            }
        });
    }, true);

    // Enlaces.
    document.addEventListener('click', function (evento) {
        var enlace = evento.target.closest('a[data-confirmar]');
        if (!enlace) { return; }
        evento.preventDefault();
        confirmar(opcionesDe(enlace)).then(function (si) {
            if (si) { window.location.href = enlace.href; }
        });
    });
})();
