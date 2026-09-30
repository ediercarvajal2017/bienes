/**
 * Notas rápidas (ícono de la barra superior y partials/notas.php), como las notas
 * adhesivas de Windows: panel lateral con las notas privadas del usuario.
 *  - Cada nota se guarda sola: un momento después de dejar de escribir y al salir del
 *    campo. Una nota nueva se crea en el servidor con el primer texto; si se deja vacía,
 *    se descarta al cerrar el panel.
 *  - El color se guarda al elegirlo. Borrar pide confirmación dentro de la misma nota.
 *  - El panel se cierra con la X, con el fondo oscuro o con Escape, y el foco vuelve al
 *    botón que lo abrió.
 * Rutas en NotaController (JSON). Sin scripts en línea, por la política de contenido.
 */
(function () {
    var panel = document.getElementById('panelNotas');
    var fondo = document.getElementById('notasFondo');
    if (!panel || !fondo) { return; }

    var URL_NOTAS = panel.getAttribute('data-url');
    var CSRF = panel.getAttribute('data-csrf');
    var MAX_CARACTERES = parseInt(panel.getAttribute('data-max-caracteres'), 10) || 2000;
    var MAX_NOTAS = parseInt(panel.getAttribute('data-max-notas'), 10) || 100;
    var ESPERA_GUARDADO = 800;
    var COLORES = [['amarillo', 'Amarillo'], ['verde', 'Verde'], ['rosado', 'Rosado'], ['azul', 'Azul'], ['morado', 'Morado']];

    var lista = panel.querySelector('[data-lista-notas]');
    var vacio = panel.querySelector('[data-notas-vacio]');
    var cargando = panel.querySelector('[data-notas-cargando]');
    var avisoError = panel.querySelector('[data-notas-error]');
    var botonNueva = panel.querySelector('[data-nueva-nota]');
    var disparador = null;
    var cargadas = false;

    function botonesAbrir() { return document.querySelectorAll('[data-abrir-notas]'); }

    function mostrarError(texto) {
        avisoError.textContent = texto || '';
        avisoError.hidden = !texto;
    }

    function formatoNumero(n) { return String(n).replace(/\B(?=(\d{3})+(?!\d))/g, '.'); }

    // Siempre resuelve con {ok, estado, datos}. Si la sesión se cerró, el servidor redirige
    // al inicio de sesión (HTML, no JSON): se avisa en lugar de fallar en silencio.
    function pedir(metodo, ruta, campos) {
        var opciones = { method: metodo, credentials: 'same-origin', headers: { 'Accept': 'application/json' } };
        if (metodo === 'POST') {
            var cuerpo = new URLSearchParams(campos || {});
            cuerpo.set('_csrf', CSRF);
            opciones.body = cuerpo;
            opciones.keepalive = true; // termina de guardar aunque el usuario cambie de página
        }
        return fetch(URL_NOTAS + ruta, opciones).then(function (r) {
            var tipo = r.headers.get('Content-Type') || '';
            if (tipo.indexOf('application/json') === -1) {
                return { ok: false, estado: r.status, datos: { error: 'Tu sesión se cerró. Recarga la página para seguir.' } };
            }
            return r.json().then(function (datos) { return { ok: r.ok, estado: r.status, datos: datos }; });
        }).catch(function () {
            return { ok: false, estado: 0, datos: { error: 'Sin conexión: la nota se guardará cuando vuelva.' } };
        });
    }

    function notasGuardadas() { return lista.querySelectorAll('.nota[data-id]').length; }

    function actualizarCuenta() {
        var total = notasGuardadas();
        botonesAbrir().forEach(function (b) {
            var cuenta = b.querySelector('[data-cuenta-notas]');
            if (cuenta) {
                cuenta.textContent = String(total);
                cuenta.hidden = total === 0;
            }
            b.setAttribute('aria-label', total > 0 ? 'Mis notas (' + total + ')' : 'Mis notas');
        });
        vacio.hidden = !cargadas || lista.children.length > 0;
    }

    function ajustarAlto(campo) {
        campo.style.height = 'auto';
        campo.style.height = campo.scrollHeight + 'px';
    }

    function boton(clase, texto, etiqueta) {
        var b = document.createElement('button');
        b.type = 'button';
        b.className = clase;
        if (texto) { b.textContent = texto; }
        if (etiqueta) {
            b.setAttribute('aria-label', etiqueta);
            b.title = etiqueta;
        }
        return b;
    }

    function crearTarjeta(nota) {
        var tarjeta = document.createElement('article');
        tarjeta.className = 'nota nota-' + nota.color;
        tarjeta.setAttribute('data-color', nota.color);
        if (nota.id) { tarjeta.setAttribute('data-id', String(nota.id)); }

        var campo = document.createElement('textarea');
        campo.className = 'nota-texto';
        campo.value = nota.texto;
        campo.maxLength = MAX_CARACTERES;
        campo.rows = 3;
        campo.placeholder = 'Escribe tu nota…';
        campo.setAttribute('aria-label', 'Texto de la nota');

        var pie = document.createElement('div');
        pie.className = 'nota-pie';
        var colores = document.createElement('div');
        colores.className = 'nota-colores';
        colores.setAttribute('role', 'group');
        colores.setAttribute('aria-label', 'Color de la nota');
        COLORES.forEach(function (c) {
            var b = boton('nota-color nota-color-' + c[0], '', 'Color ' + c[1].toLowerCase());
            b.setAttribute('data-color', c[0]);
            b.setAttribute('aria-pressed', String(c[0] === nota.color));
            colores.appendChild(b);
        });
        var estado = document.createElement('span');
        estado.className = 'nota-estado';
        estado.setAttribute('role', 'status');
        estado.textContent = nota.actualizada || '';
        if (nota.actualizada) { estado.title = 'Última edición: ' + nota.actualizada; }
        var borrar = boton('nota-borrar', '', 'Borrar nota');
        var icono = document.createElement('i');
        icono.className = 'bi bi-trash3';
        icono.setAttribute('aria-hidden', 'true');
        borrar.appendChild(icono);
        pie.append(colores, estado, borrar);

        var confirmar = document.createElement('div');
        confirmar.className = 'nota-confirmar';
        confirmar.hidden = true;
        var pregunta = document.createElement('span');
        pregunta.textContent = '¿Borrar esta nota?';
        var si = boton('btn btn-sm btn-danger', 'Borrar');
        var no = boton('btn btn-sm btn-light', 'Cancelar');
        confirmar.append(pregunta, si, no);

        tarjeta.append(campo, pie, confirmar);

        var s = {
            id: nota.id || null,
            textoGuardado: nota.texto,
            colorGuardado: nota.id ? nota.color : null,
            guardando: false,
            pendiente: false,
            temporizador: null
        };
        tarjeta._nota = s;

        function guardar() {
            window.clearTimeout(s.temporizador);
            s.temporizador = null;
            var texto = campo.value;
            var color = tarjeta.getAttribute('data-color');
            if (s.id === null && texto.trim() === '') { return; } // nota nueva vacía: aún no se crea
            if (texto === s.textoGuardado && color === s.colorGuardado) { return; }
            if (s.guardando) {
                s.pendiente = true;
                return;
            }
            s.guardando = true;
            estado.textContent = 'Guardando…';
            pedir('POST', s.id === null ? '' : '/' + s.id, { texto: texto, color: color }).then(function (r) {
                s.guardando = false;
                if (r.ok) {
                    if (s.id === null) {
                        s.id = r.datos.nota.id;
                        tarjeta.setAttribute('data-id', String(s.id));
                        actualizarCuenta();
                    }
                    s.textoGuardado = texto;
                    s.colorGuardado = color;
                    estado.textContent = 'Guardado';
                    mostrarError('');
                } else if (r.estado === 404) {
                    // Se borró en otra pestaña: se quita de aquí también.
                    tarjeta.remove();
                    actualizarCuenta();
                    mostrarError(r.datos.error);
                    return;
                } else {
                    estado.textContent = 'Sin guardar';
                    mostrarError(r.datos.error || 'No se pudo guardar la nota.');
                    if (r.estado === 0) { s.temporizador = window.setTimeout(guardar, 5000); }
                }
                if (s.pendiente) {
                    s.pendiente = false;
                    guardar();
                }
            });
        }
        s.guardar = guardar;

        campo.addEventListener('input', function () {
            ajustarAlto(campo);
            var largo = campo.value.length;
            estado.textContent = largo > MAX_CARACTERES - 200
                ? formatoNumero(largo) + ' de ' + formatoNumero(MAX_CARACTERES) + ' caracteres'
                : 'Escribiendo…';
            window.clearTimeout(s.temporizador);
            s.temporizador = window.setTimeout(guardar, ESPERA_GUARDADO);
        });
        campo.addEventListener('blur', guardar);

        colores.addEventListener('click', function (e) {
            var elegido = e.target.closest('[data-color]');
            if (!elegido) { return; }
            var color = elegido.getAttribute('data-color');
            COLORES.forEach(function (c) { tarjeta.classList.remove('nota-' + c[0]); });
            tarjeta.classList.add('nota-' + color);
            tarjeta.setAttribute('data-color', color);
            colores.querySelectorAll('[data-color]').forEach(function (b) {
                b.setAttribute('aria-pressed', String(b === elegido));
            });
            guardar();
        });

        borrar.addEventListener('click', function () {
            pie.hidden = true;
            confirmar.hidden = false;
            no.focus();
        });
        no.addEventListener('click', function () {
            confirmar.hidden = true;
            pie.hidden = false;
            borrar.focus();
        });
        si.addEventListener('click', function () {
            window.clearTimeout(s.temporizador);
            if (s.id === null) {
                quitar();
                return;
            }
            si.disabled = true;
            pedir('POST', '/' + s.id + '/eliminar').then(function (r) {
                if (r.ok || r.estado === 404) {
                    quitar();
                } else {
                    si.disabled = false;
                    mostrarError(r.datos.error || 'No se pudo borrar la nota.');
                }
            });
        });
        function quitar() {
            tarjeta.remove();
            actualizarCuenta();
            mostrarError('');
            botonNueva.focus();
        }

        return tarjeta;
    }

    function cargar() {
        cargando.hidden = false;
        mostrarError('');
        pedir('GET', '').then(function (r) {
            cargando.hidden = true;
            if (!r.ok) {
                mostrarError(r.datos.error || 'No se pudieron cargar tus notas.');
                return;
            }
            cargadas = true;
            // Se agregan después de una nota nueva que el usuario haya empezado mientras cargaba.
            r.datos.notas.forEach(function (n) { lista.appendChild(crearTarjeta(n)); });
            lista.querySelectorAll('.nota-texto').forEach(ajustarAlto);
            actualizarCuenta();
        });
    }

    function abierto() { return panel.classList.contains('abierto'); }

    function abrir(origen) {
        disparador = origen || null;
        panel.classList.add('abierto');
        fondo.classList.add('visible');
        botonesAbrir().forEach(function (b) { b.setAttribute('aria-expanded', 'true'); });
        botonNueva.focus({ preventScroll: true });
        if (!cargadas) { cargar(); }
    }

    function cerrar() {
        if (!abierto()) { return; }
        // Las notas nuevas que quedaron vacías no se crean; las demás terminan de guardarse.
        lista.querySelectorAll('.nota').forEach(function (t) {
            var s = t._nota;
            if (!s) { return; }
            if (s.id === null && t.querySelector('.nota-texto').value.trim() === '') {
                t.remove();
            } else if (s.temporizador) {
                s.guardar();
            }
        });
        actualizarCuenta();
        panel.classList.remove('abierto');
        fondo.classList.remove('visible');
        botonesAbrir().forEach(function (b) { b.setAttribute('aria-expanded', 'false'); });
        if (disparador) { disparador.focus({ preventScroll: true }); }
    }

    botonesAbrir().forEach(function (b) {
        b.addEventListener('click', function () {
            if (abierto()) { cerrar(); } else { abrir(b); }
        });
    });
    panel.querySelector('[data-cerrar-notas]').addEventListener('click', cerrar);
    fondo.addEventListener('click', cerrar);
    document.addEventListener('keydown', function (e) {
        if (e.key === 'Escape' && abierto()) { cerrar(); }
    });

    botonNueva.addEventListener('click', function () {
        mostrarError('');
        // Si ya hay una nota nueva sin escribir, se usa esa en lugar de abrir otra.
        var sinEscribir = Array.prototype.find.call(lista.querySelectorAll('.nota'), function (t) {
            return t._nota && t._nota.id === null && t.querySelector('.nota-texto').value.trim() === '';
        });
        if (sinEscribir) {
            sinEscribir.querySelector('.nota-texto').focus();
            return;
        }
        if (notasGuardadas() >= MAX_NOTAS) {
            mostrarError('Ya tienes ' + MAX_NOTAS + ' notas, el máximo. Borra alguna para crear otra.');
            return;
        }
        var tarjeta = crearTarjeta({ id: null, texto: '', color: 'amarillo', actualizada: '' });
        lista.insertBefore(tarjeta, lista.firstChild);
        actualizarCuenta();
        tarjeta.querySelector('.nota-texto').focus();
    });
})();
