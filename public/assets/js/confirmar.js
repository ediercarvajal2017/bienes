/**
 * Confirmación de acciones con el modal de MIA (partials/ui/modal_confirmar.php), en lugar
 * del confirm() del navegador, que no se puede adaptar al diseño ni al tema oscuro.
 *
 *  - <form data-confirmar="¿…?">, o un botón de envío con data-confirmar: pide confirmación
 *    antes de enviar y luego envía con el mismo botón (conserva su name/value).
 *  - <a data-confirmar="¿…?">: pide confirmación antes de seguir el enlace.
 *  - window.miaConfirmar({ mensaje, titulo, boton, peligro }) → Promise<boolean>, para los
 *    scripts de las vistas. Con soloAceptar: true es un aviso de un solo botón (en lugar de
 *    alert()).
 *  - window.miaPedirTexto({ mensaje, titulo, etiqueta, boton, peligro }) → Promise<string|null>:
 *    pide un texto obligatorio, como el motivo de un rechazo (en lugar de prompt()).
 * Atributos opcionales: data-confirmar-boton (texto del botón), data-confirmar-tipo="peligro"
 * (botón rojo; el foco empieza en Cancelar) y data-confirmar-titulo.
 * Sin <dialog> (navegador muy antiguo) se usan los diálogos del navegador.
 */
(function () {
    var modal = document.getElementById('modalConfirmar');

    // Abre el modal y resuelve con { aceptado, texto }.
    function abrir(opciones) {
        var aceptar = modal.querySelector('[data-confirmar-aceptar]');
        var cancelar = modal.querySelector('[data-confirmar-cancelar]');
        var bloqueCampo = modal.querySelector('.modal-confirmar-campo');
        var campo = modal.querySelector('#modalConfirmarTexto');
        var conCampo = Boolean(opciones.etiqueta);
        var origen = document.activeElement;

        modal.querySelector('#modalConfirmarTitulo').textContent = opciones.titulo || 'Confirmar acción';
        modal.querySelector('#modalConfirmarMensaje').textContent = opciones.mensaje || '';
        aceptar.textContent = opciones.boton || 'Confirmar';
        aceptar.className = 'btn ' + (opciones.peligro ? 'btn-danger' : 'btn-primary');
        cancelar.hidden = Boolean(opciones.soloAceptar);
        bloqueCampo.hidden = !conCampo;
        campo.value = '';
        campo.classList.remove('is-invalid');
        // aria-required y no required: la validación nativa bloquearía también "Cancelar".
        campo.setAttribute('aria-required', conCampo ? 'true' : 'false');
        if (conCampo) { modal.querySelector('#modalConfirmarEtiqueta').textContent = opciones.etiqueta; }
        modal.returnValue = '';

        // Con campo, "aceptar" no cierra mientras esté vacío.
        function validar(evento) {
            if (conCampo && campo.value.trim() === '') {
                evento.preventDefault();
                campo.classList.add('is-invalid');
                campo.focus();
            }
        }
        aceptar.addEventListener('click', validar);

        return new Promise(function (resolver) {
            modal.addEventListener('close', function alCerrar() {
                modal.removeEventListener('close', alCerrar);
                aceptar.removeEventListener('click', validar);
                if (origen && typeof origen.focus === 'function') { origen.focus({ preventScroll: true }); }
                resolver({ aceptado: modal.returnValue === 'aceptar', texto: campo.value.trim() });
            });
            modal.showModal();
            (conCampo ? campo : (opciones.peligro && !opciones.soloAceptar ? cancelar : aceptar)).focus();
        });
    }

    function confirmar(opciones) {
        if (!modal || typeof modal.showModal !== 'function') {
            if (opciones.soloAceptar) {
                window.alert(opciones.mensaje);
                return Promise.resolve(true);
            }
            return Promise.resolve(window.confirm(opciones.mensaje || '¿Deseas continuar?'));
        }
        return abrir(opciones).then(function (r) { return r.aceptado; });
    }

    function pedirTexto(opciones) {
        if (!modal || typeof modal.showModal !== 'function') {
            var respuesta = window.prompt(opciones.etiqueta || opciones.mensaje);
            return Promise.resolve(respuesta && respuesta.trim() ? respuesta.trim() : null);
        }
        return abrir(Object.assign({ etiqueta: 'Motivo' }, opciones)).then(function (r) {
            return r.aceptado && r.texto !== '' ? r.texto : null;
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
    window.miaPedirTexto = pedirTexto;

    /**
     * Para el manejador "submit" de una vista que calcula el mensaje al enviar:
     *   if (!miaConfirmarEnvio(e, { mensaje: '¿Reintegrar 3 bienes?', boton: 'Reintegrar' })) { return; }
     * La primera vez detiene el envío y pregunta; si se acepta, vuelve a enviar con el mismo
     * botón y en ese segundo envío devuelve true para que la vista siga.
     */
    window.miaConfirmarEnvio = function (evento, opciones) {
        var form = evento.target;
        if (form.dataset.envioConfirmado === '1') {
            delete form.dataset.envioConfirmado;
            return true;
        }
        evento.preventDefault();
        var enviador = evento.submitter;
        confirmar(opciones).then(function (si) {
            if (!si) { return; }
            form.dataset.envioConfirmado = '1';
            if (typeof form.requestSubmit === 'function') {
                form.requestSubmit(enviador || undefined);
            } else {
                form.submit();
            }
        });
        return false;
    };

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
