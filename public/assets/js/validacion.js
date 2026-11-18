/**
 * Validación de los formularios en el navegador, con mensajes de MIA en español en lugar de
 * los globos del navegador (que salían a veces en otro idioma y desaparecían solos).
 *
 * Al enviar un formulario que no cumple (required, type="email", min, max, minlength,
 * pattern…), no se envía y:
 *  - cada campo queda en rojo con su mensaje debajo, enlazado con aria-describedby;
 *  - arriba del formulario sale un aviso: qué pasó, qué datos faltan y qué hacer;
 *  - el foco va al primer campo por corregir.
 * El mensaje de un campo se quita en cuanto se corrige.
 *
 * Personalizar: data-error-requerido="…" o data-error-formato="…" en el campo (o title para
 * el formato de un pattern). Excepciones: <form novalidate> o data-sin-validar, botón con
 * formnovalidate y form method="dialog". Sin JavaScript queda la validación del navegador.
 *
 * Se escucha en window y en fase de captura: corre antes que confirmar.js (no se pregunta
 * "¿Seguro?" por un formulario incompleto) y que cargando.js (el botón no queda "Procesando").
 */
(function () {
    'use strict';

    var CLASE_MENSAJE = 'mensaje-validacion';

    function seValida(form) {
        if (!(form instanceof HTMLFormElement)) { return false; }
        if (form.hasAttribute('data-sin-validar')) { return false; }
        if ((form.getAttribute('method') || '').toLowerCase() === 'dialog') { return false; }
        return form.dataset.validacionMia === '1';
    }

    // Los formularios de la página (y los que se agreguen después, al primer clic) quedan sin
    // los globos del navegador; los que ya traían novalidate se respetan.
    function preparar(form) {
        if (!(form instanceof HTMLFormElement) || form.dataset.validacionMia) { return; }
        if (form.noValidate || form.hasAttribute('data-sin-validar')) {
            form.dataset.validacionMia = '0';
            return;
        }
        form.dataset.validacionMia = '1';
        form.noValidate = true;
    }

    function textoEtiqueta(campo) {
        var etiqueta = null;
        if (campo.id) {
            etiqueta = document.querySelector('label[for="' + (window.CSS && CSS.escape ? CSS.escape(campo.id) : campo.id) + '"]');
        }
        if (!etiqueta) { etiqueta = campo.closest('label'); }
        var texto = etiqueta ? etiqueta.textContent : (campo.getAttribute('aria-label') || campo.getAttribute('placeholder') || campo.name || 'este dato');
        return texto.replace(/\s+/g, ' ').replace(/[*:]+\s*$/, '').trim();
    }

    function fecha(valor) {
        var partes = /^(\d{4})-(\d{2})-(\d{2})$/.exec(valor || '');
        return partes ? partes[3] + '/' + partes[2] + '/' + partes[1] : valor;
    }

    function mensajeDe(campo) {
        var v = campo.validity;
        var etiqueta = textoEtiqueta(campo);
        var tipo = (campo.type || '').toLowerCase();
        var esFecha = tipo === 'date' || tipo === 'datetime-local' || tipo === 'month';

        if (v.valueMissing) {
            if (campo.dataset.errorRequerido) { return campo.dataset.errorRequerido; }
            if (campo.tagName === 'SELECT') { return 'Elige una opción en «' + etiqueta + '».'; }
            if (tipo === 'file') { return 'Adjunta el archivo en «' + etiqueta + '».'; }
            if (tipo === 'checkbox') { return 'Marca la casilla «' + etiqueta + '» para continuar.'; }
            if (tipo === 'radio') { return 'Elige una de las opciones.'; }
            return 'Completa «' + etiqueta + '»: es obligatorio.';
        }
        if (v.badInput) {
            return esFecha ? 'Escribe una fecha válida en «' + etiqueta + '».' : 'Escribe solo números en «' + etiqueta + '».';
        }
        if (v.typeMismatch) {
            if (tipo === 'email') { return 'Escribe un correo válido, por ejemplo nombre@colegio.edu.co.'; }
            if (tipo === 'url') { return 'Escribe la dirección completa, por ejemplo https://…'; }
            return 'El formato de «' + etiqueta + '» no es válido.';
        }
        if (v.patternMismatch) {
            return campo.dataset.errorFormato || campo.getAttribute('title') || 'El formato de «' + etiqueta + '» no es válido: revisa lo que escribiste.';
        }
        if (v.tooShort) {
            return 'Escribe al menos ' + campo.minLength + ' caracteres (llevas ' + campo.value.length + ').';
        }
        if (v.tooLong) {
            return 'Escribe máximo ' + campo.maxLength + ' caracteres (llevas ' + campo.value.length + ').';
        }
        if (v.rangeUnderflow) {
            return esFecha ? 'La fecha no puede ser anterior al ' + fecha(campo.min) + '.' : 'El valor mínimo es ' + campo.min + '.';
        }
        if (v.rangeOverflow) {
            return esFecha ? 'La fecha no puede ser posterior al ' + fecha(campo.max) + '.' : 'El valor máximo es ' + campo.max + '.';
        }
        if (v.stepMismatch) {
            var paso = campo.getAttribute('step');
            return !paso || paso === '1' ? 'Escribe un número entero, sin decimales.' : 'Usa múltiplos de ' + paso + '.';
        }
        if (v.customError) { return campo.validationMessage; }
        return campo.dataset.errorFormato || 'Revisa «' + etiqueta + '».';
    }

    // Dónde va el mensaje: después del campo, de su grupo (input-group) o del selector con
    // buscador (Tom Select pone su .ts-wrapper al lado del <select>).
    function anclaDe(campo) {
        if (campo.type === 'checkbox' || campo.type === 'radio') {
            return campo.closest('.form-check') || campo;
        }
        var grupo = campo.closest('.input-group');
        if (grupo) { return grupo; }
        var siguiente = campo.nextElementSibling;
        if (siguiente && siguiente.classList.contains('ts-wrapper')) { return siguiente; }
        return campo;
    }

    function idMensaje(campo, form) {
        if (!campo.id) {
            campo.id = 'campo-' + Math.random().toString(36).slice(2, 9);
        }
        return campo.id + '-validacion';
    }

    function marcar(campo, form) {
        var id = idMensaje(campo, form);
        var mensaje = document.getElementById(id);
        if (!mensaje) {
            mensaje = document.createElement('div');
            mensaje.id = id;
            mensaje.className = 'invalid-feedback d-block ' + CLASE_MENSAJE;
            anclaDe(campo).insertAdjacentElement('afterend', mensaje);
        }
        mensaje.textContent = mensajeDe(campo);
        campo.classList.add('is-invalid');
        campo.setAttribute('aria-invalid', 'true');
        var descritoPor = (campo.getAttribute('aria-describedby') || '').split(/\s+/).filter(Boolean);
        if (descritoPor.indexOf(id) === -1) {
            descritoPor.push(id);
            campo.setAttribute('aria-describedby', descritoPor.join(' '));
        }
    }

    function desmarcar(campo) {
        if (!campo.id) { return; }
        var id = campo.id + '-validacion';
        var mensaje = document.getElementById(id);
        if (!mensaje) { return; }
        mensaje.remove();
        campo.classList.remove('is-invalid');
        campo.removeAttribute('aria-invalid');
        var descritoPor = (campo.getAttribute('aria-describedby') || '').split(/\s+/).filter(function (x) { return x && x !== id; });
        if (descritoPor.length) {
            campo.setAttribute('aria-describedby', descritoPor.join(' '));
        } else {
            campo.removeAttribute('aria-describedby');
        }
    }

    function textoBoton(form, enviador) {
        var boton = enviador || form.querySelector('button[type="submit"], input[type="submit"]');
        if (!boton) { return ''; }
        return (boton.value && boton.tagName === 'INPUT' ? boton.value : boton.textContent).replace(/\s+/g, ' ').trim();
    }

    function resumen(form, invalidos, enviador) {
        var aviso = form.querySelector(':scope > .resumen-validacion');
        if (!aviso) {
            aviso = document.createElement('div');
            aviso.className = 'alert alert-danger aviso resumen-validacion';
            aviso.setAttribute('role', 'alert');
            aviso.setAttribute('data-sin-cerrar', '');
            aviso.innerHTML = '<i class="bi bi-x-circle aviso-icono" aria-hidden="true"></i>'
                + '<div class="aviso-cuerpo"><p class="aviso-titulo"></p><p class="aviso-detalle"></p>'
                + '<p class="aviso-solucion"><strong>Qué hacer:</strong> <span></span></p></div>';
            form.insertBefore(aviso, form.firstChild);
        }
        var nombres = [];
        invalidos.forEach(function (campo) {
            var nombre = textoEtiqueta(campo);
            if (nombres.indexOf(nombre) === -1) { nombres.push(nombre); }
        });
        var boton = textoBoton(form, enviador);
        aviso.querySelector('.aviso-titulo').textContent = 'No se envió: hay ' + (invalidos.length === 1 ? '1 dato' : invalidos.length + ' datos') + ' por corregir';
        aviso.querySelector('.aviso-detalle').textContent = nombres.join(', ') + '.';
        aviso.querySelector('.aviso-solucion span').textContent = 'Corrige los campos marcados en rojo'
            + (boton ? ' y vuelve a pulsar «' + boton + '».' : ' y vuelve a enviar.');
    }

    function quitarResumenSiYaEstaBien(form) {
        var aviso = form.querySelector(':scope > .resumen-validacion');
        if (aviso && form.checkValidity()) { aviso.remove(); }
    }

    function enfocar(campo) {
        var destino = campo;
        if (campo.tagName === 'SELECT' && campo.tomselect) {
            campo.tomselect.focus();
            destino = null;
        }
        if (destino && typeof destino.focus === 'function') {
            destino.focus({ preventScroll: true });
        }
        anclaDe(campo).scrollIntoView({ block: 'center', behavior: 'smooth' });
    }

    function campos(form) {
        return Array.prototype.filter.call(form.elements, function (c) {
            return typeof c.checkValidity === 'function' && c.willValidate;
        });
    }

    window.addEventListener('submit', function (evento) {
        var form = evento.target;
        preparar(form);
        if (!seValida(form)) { return; }
        if (evento.submitter && evento.submitter.hasAttribute('formnovalidate')) { return; }

        var gruposVistos = {};
        var invalidos = campos(form).filter(function (c) {
            if (c.checkValidity()) { return false; }
            // Un grupo de opciones (radio) se marca una sola vez.
            if (c.type === 'radio') {
                if (gruposVistos[c.name]) { return false; }
                gruposVistos[c.name] = true;
            }
            return true;
        });
        // Los mensajes de un intento anterior que ya se corrigieron.
        campos(form).forEach(function (c) { if (invalidos.indexOf(c) === -1) { desmarcar(c); } });
        if (invalidos.length === 0) {
            var resumenAnterior = form.querySelector(':scope > .resumen-validacion');
            if (resumenAnterior) { resumenAnterior.remove(); }
            return;
        }

        evento.preventDefault();
        evento.stopImmediatePropagation();
        invalidos.forEach(function (c) { marcar(c, form); });
        resumen(form, invalidos, evento.submitter);
        enfocar(invalidos[0]);
    }, true);

    // Al corregir un campo, se quita su mensaje (y el aviso de arriba si ya no falta nada).
    function alCambiar(evento) {
        var campo = evento.target;
        if (!campo || !campo.form || !seValida(campo.form) || typeof campo.checkValidity !== 'function') { return; }
        if (!document.getElementById((campo.id || '') + '-validacion')) { return; }
        if (campo.checkValidity()) {
            desmarcar(campo);
            quitarResumenSiYaEstaBien(campo.form);
        } else {
            marcar(campo, campo.form);
        }
    }
    document.addEventListener('input', alCambiar);
    document.addEventListener('change', alCambiar);

    // Antes de que el navegador valide (el clic en un botón de envío llega primero).
    document.addEventListener('click', function (evento) {
        var boton = evento.target.closest && evento.target.closest('button, input[type="submit"]');
        if (boton && boton.form) { preparar(boton.form); }
    }, true);

    function prepararTodos() {
        Array.prototype.forEach.call(document.forms, preparar);
    }
    if (document.readyState === 'loading') {
        document.addEventListener('DOMContentLoaded', prepararTodos);
    } else {
        prepararTodos();
    }
})();
