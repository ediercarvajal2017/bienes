/**
 * Avisos de la página (partials/ui/alerta.php).
 *
 *  - Les agrega el botón de cerrar (antes se quedaban fijos en pantalla hasta que el usuario
 *    navegaba a otro lado). Bootstrap trae "alert-dismissible" listo por CSS, pero su cierre
 *    es JS del bundle completo que este proyecto no carga (ver layout.php): el clic se conecta
 *    a mano, igual que el resto de widgets del proyecto.
 *  - El error que dejó la acción anterior (data-aviso-enfocar) recibe el foco al cargar: el
 *    lector de pantalla lo anuncia y queda a la vista.
 *  - MIA.avisar(ancla, aviso): aviso de error para lo que falla sin recargar la página (una
 *    petición de fondo sin conexión), con el mismo diseño:
 *      MIA.avisar(campoCategoria, { titulo: 'No se pudieron cargar las categorías',
 *          detalle: 'No hubo respuesta del servidor.', solucion: 'Revisa tu conexión y pulsa Reintentar.',
 *          boton: { texto: 'Reintentar', alPulsar: cargarCategorias } });
 *    Se pone justo después de "ancla" y reemplaza el aviso anterior de esa misma ancla.
 *    Con tipo: 'info' es un aviso que no es error (franja verde MIA e ícono de información).
 *    MIA.quitarAviso(ancla) lo quita (p. ej. cuando el reintento sale bien).
 *  - MIA.motivoCamara(error): { porQue, queHacer } en español para un fallo de la cámara
 *    (getUserMedia), en lugar del texto del navegador, que a veces sale en inglés.
 */
(function () {
    function agregarCerrar(alerta) {
        // data-sin-cerrar: un aviso de estado, no un mensaje (ui('alerta', ['cerrable' => false])).
        if (alerta.querySelector('.btn-close') || alerta.hasAttribute('data-sin-cerrar')) { return; }

        alerta.classList.add('alert-dismissible', 'fade', 'show');

        var boton = document.createElement('button');
        boton.type = 'button';
        boton.className = 'btn-close';
        boton.setAttribute('aria-label', 'Cerrar mensaje');
        boton.addEventListener('click', function () {
            alerta.classList.remove('show');
            window.setTimeout(function () { alerta.remove(); }, 150);
        });

        alerta.appendChild(boton);
    }

    function parrafo(clase, texto) {
        var p = document.createElement('p');
        p.className = clase;
        p.textContent = texto;
        return p;
    }

    var avisosPorAncla = new WeakMap();

    function quitarAviso(ancla) {
        var anterior = avisosPorAncla.get(ancla);
        if (anterior) {
            anterior.remove();
            avisosPorAncla.delete(ancla);
        }
    }

    function avisar(ancla, aviso) {
        quitarAviso(ancla);

        var esInfo = aviso.tipo === 'info';
        var alerta = document.createElement('div');
        alerta.className = 'alert ' + (esInfo ? 'alert-info' : 'alert-danger') + ' aviso mt-2';
        alerta.setAttribute('role', esInfo ? 'status' : 'alert');

        var icono = document.createElement('i');
        icono.className = 'bi ' + (esInfo ? 'bi-info-circle' : 'bi-x-circle') + ' aviso-icono';
        icono.setAttribute('aria-hidden', 'true');
        alerta.appendChild(icono);

        var cuerpo = document.createElement('div');
        cuerpo.className = 'aviso-cuerpo';
        cuerpo.appendChild(parrafo('aviso-titulo', aviso.titulo));
        if (aviso.detalle) { cuerpo.appendChild(parrafo('aviso-detalle', aviso.detalle)); }
        if (aviso.solucion) {
            var solucion = parrafo('aviso-solucion', '');
            var etiqueta = document.createElement('strong');
            etiqueta.textContent = 'Qué hacer:';
            solucion.appendChild(etiqueta);
            solucion.appendChild(document.createTextNode(' ' + aviso.solucion));
            cuerpo.appendChild(solucion);
        }
        if (aviso.boton) {
            var acciones = document.createElement('div');
            acciones.className = 'aviso-acciones';
            var boton = document.createElement('button');
            boton.type = 'button';
            boton.className = 'btn btn-primary btn-sm';
            boton.textContent = aviso.boton.texto;
            boton.addEventListener('click', function () {
                quitarAviso(ancla);
                aviso.boton.alPulsar();
            });
            acciones.appendChild(boton);
            cuerpo.appendChild(acciones);
        }
        alerta.appendChild(cuerpo);
        agregarCerrar(alerta);

        ancla.insertAdjacentElement('afterend', alerta);
        avisosPorAncla.set(ancla, alerta);
        return alerta;
    }

    function motivoCamara(error) {
        // getUserMedia entrega un DOMException con su nombre; html5-qrcode a veces lo
        // convierte en texto ("NotAllowedError: Permission denied").
        var texto = String((error && (error.name || error.message)) || error || '');
        var hallado = /(NotAllowed|Security|NotFound|Overconstrained|NotReadable|Abort)Error/.exec(texto + ' ' + String(error || ''));
        var nombre = hallado ? hallado[0] : (/permission|denied/i.test(texto) ? 'NotAllowedError' : '');
        if (!window.isSecureContext) {
            return { porQue: 'La cámara solo funciona con conexión segura (https).', queHacer: 'Entra a MIA con https://.' };
        }
        if (nombre === 'NotAllowedError' || nombre === 'SecurityError') {
            return { porQue: 'No diste permiso para usar la cámara.',
                queHacer: 'Permite la cámara en el candado de la barra de direcciones y vuelve a intentarlo, o elige una foto guardada.' };
        }
        if (nombre === 'NotFoundError' || nombre === 'OverconstrainedError') {
            return { porQue: 'Este dispositivo no tiene una cámara disponible.', queHacer: 'Elige una foto guardada, o usa un teléfono con cámara.' };
        }
        if (nombre === 'NotReadableError' || nombre === 'AbortError') {
            return { porQue: 'Otra aplicación está usando la cámara.', queHacer: 'Ciérrala y vuelve a intentarlo.' };
        }
        return { porQue: 'El navegador no dejó usar la cámara.', queHacer: 'Recarga la página y vuelve a intentarlo, o elige una foto guardada.' };
    }

    window.MIA = window.MIA || {};
    window.MIA.avisar = avisar;
    window.MIA.quitarAviso = quitarAviso;
    window.MIA.motivoCamara = motivoCamara;

    document.addEventListener('DOMContentLoaded', function () {
        document.querySelectorAll('.alert').forEach(agregarCerrar);

        var error = document.querySelector('[data-aviso-enfocar]');
        if (error) {
            error.focus({ preventScroll: true });
            error.scrollIntoView({ block: 'nearest' });
        }
    });
})();
