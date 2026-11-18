<?php

use App\Core\Csrf;
use App\Core\Url;
use App\Core\View;

?>
<?= ui('encabezado_pagina', ['titulo' => 'Buscar por foto', 'icono' => 'foto',
    'subtitulo' => 'Toma o sube una foto del bien y el sistema busca los más parecidos por su apariencia, aunque no tengas el código a mano.',
    'acciones' => ui('boton', ['texto' => 'Volver', 'icono' => 'volver', 'tamano' => 'sm', 'url' => Url::to('/bienes')]) . ui('menu_acciones', ['opciones' => \App\Helpers\AccionesBienes::opciones(), 'actual' => 'buscar_foto']),
]) ?>

<?php if ($sinInstitucion): ?>
    <?= ui('alerta', ['tipo' => 'advertencia', 'mensaje' => 'Selecciona una institución en el filtro del encabezado para buscar por foto.', 'cerrable' => false, 'clases' => 'py-2 small contenedor-md']) ?>
<?php else: ?>

    <div class="contenedor-sm">
        <?php View::render('partials/campo_foto', [
            // Mismo componente de los formularios: elegir una foto o "Tomar foto" con la cámara
            // del equipo. Sin capture, para que en el celular se pueda elegir cámara o galería.
            'nombreCampo' => 'foto_busqueda',
            'etiqueta' => 'Foto a buscar',
            'idCampo' => 'inputFotoBusqueda',
            'accept' => 'image/*',
            'capturaNativa' => false,
        ]); ?>
        <p id="notaIndexado" class="small text-muted mt-2 mb-0"></p>
        <p id="estadoBusqueda" class="small mt-2 mb-0"></p>
    </div>

    <div id="resultadosBusqueda" class="row g-3 mt-1 contenedor-lg"></div>

    <p class="text-muted small mt-3 contenedor-md">
        Los resultados son sugerencias por parecido visual, no una identificación exacta -- confirma
        siempre el código antes de dar por hecho que es el mismo bien.
    </p>

    <script src="https://cdn.jsdelivr.net/npm/@tensorflow/tfjs@4.20.0/dist/tf.min.js" integrity="sha384-xc4sZTUOM2obsQR75Be0zGbt7Gb6mOVFJN4yBm30Xn0YQLDWIY+yrtFmLmIank6w" crossorigin="anonymous"></script>
    <script src="https://cdn.jsdelivr.net/npm/@tensorflow-models/mobilenet@2.1.1/dist/mobilenet.min.js" integrity="sha384-oBAqwJ0tv9zzKlbIZyBhhXlEvU/PMrSMqDyOHlEZVC8xWHx4yPySuS7vRikRcYFq" crossorigin="anonymous"></script>
    <script>
    (function () {
        const csrfToken = <?= json_encode(Csrf::token()) ?>;
        const archivosBase = <?= json_encode(Url::to('/archivos')) ?>;
        const bienesBase = <?= json_encode(Url::to('/bienes')) ?>;
        const urlPendientes = <?= json_encode(Url::to('/bienes/buscar-por-foto/pendientes')) ?>;
        const urlGuardarVector = <?= json_encode(Url::to('/bienes/buscar-por-foto/vector')) ?>;
        const urlBuscar = <?= json_encode(Url::to('/bienes/buscar-por-foto/buscar')) ?>;
        const urlEscanear = <?= json_encode(Url::to('/escanear')) ?>;

        const inputFoto = document.getElementById('inputFotoBusqueda');
        const estado = document.getElementById('estadoBusqueda');
        const notaIndexado = document.getElementById('notaIndexado');
        const resultados = document.getElementById('resultadosBusqueda');

        let modelo = null;

        function cargarModelo() {
            if (!modelo) {
                modelo = mobilenet.load();
            }
            return modelo;
        }

        function cargarImagen(src) {
            return new Promise(function (resolve, reject) {
                const img = new Image();
                img.crossOrigin = 'anonymous';
                img.onload = function () { resolve(img); };
                img.onerror = reject;
                img.src = src;
            });
        }

        function leerComoDataUrl(file) {
            return new Promise(function (resolve, reject) {
                const lector = new FileReader();
                lector.onload = function () { resolve(lector.result); };
                lector.onerror = reject;
                lector.readAsDataURL(file);
            });
        }

        function calcularVector(m, img) {
            const t = m.infer(img, true);
            const vector = Array.from(t.dataSync());
            t.dispose();
            return vector;
        }

        function escapeHtml(texto) {
            const div = document.createElement('div');
            div.textContent = texto || '';
            return div.innerHTML;
        }

        // Indexa en segundo plano las fotos de bienes que aún no tienen huella visual
        // guardada (bienes nuevos, o subidos antes de que existiera esta función). No
        // bloquea la búsqueda -- si falla o tarda, el usuario igual puede buscar con lo
        // que ya esté indexado.
        // window.__indexadoListo: solo para que las pruebas automatizadas sepan cuándo
        // terminó el indexado en segundo plano (con éxito o no) sin adivinar por texto
        // en pantalla -- no lo usa ninguna parte visible de la interfaz.
        window.__indexadoListo = false;

        async function indexarPendientes() {
            // Fotos ya intentadas en esta visita: si una no carga (archivo perdido o
            // ilegible) no guarda su huella y el servidor la sigue devolviendo como
            // pendiente. Sin este registro el ciclo la pedía una y otra vez sin fin.
            const intentados = new Set();
            try {
                let m;
                try {
                    m = await cargarModelo();
                } catch (e) {
                    return;
                }

                for (;;) {
                    let datos;
                    try {
                        const resp = await fetch(urlPendientes);
                        datos = await resp.json();
                    } catch (e) {
                        return;
                    }

                    const nuevos = (datos.pendientes || []).filter(function (b) { return !intentados.has(b.id); });
                    if (nuevos.length === 0) {
                        notaIndexado.textContent = '';
                        return;
                    }

                    notaIndexado.textContent = 'Preparando fotos existentes para la búsqueda… (' + datos.total + ' pendientes)';

                    for (const bien of nuevos) {
                        intentados.add(bien.id);
                        try {
                            const img = await cargarImagen(bien.foto_url || (archivosBase + '/' + bien.foto_path));
                            const vector = calcularVector(m, img);
                            const body = new URLSearchParams({ _csrf: csrfToken, id: String(bien.id), vector: JSON.stringify(vector) });
                            await fetch(urlGuardarVector, { method: 'POST', body });
                        } catch (e) {
                            // Una foto ilegible no debe frenar el resto del indexado.
                        }
                    }
                }
            } finally {
                window.__indexadoListo = true;
            }
        }

        function elemento(etiqueta, clase, texto) {
            const el = document.createElement(etiqueta);
            el.className = clase;
            if (texto !== undefined) { el.textContent = texto; }
            return el;
        }

        // Sin parecidos: qué pudo pasar y cómo seguir (mismo diseño que ui('estado_vacio')).
        function sinResultados() {
            const col = elemento('div', 'col-12');
            const caja = elemento('div', 'estado-vacio-explicado');
            caja.setAttribute('role', 'status');
            caja.appendChild(elemento('p', 'estado-vacio-titulo', 'Ningún bien se parece a esta foto.'));
            caja.appendChild(elemento('p', 'estado-vacio-detalle',
                'Puede que el bien no tenga foto en MIA, o que la foto se tomara con otro ángulo o poca luz. Prueba con otra foto, o búscalo por su placa.'));
            const acciones = elemento('div', 'estado-vacio-acciones');
            const porCodigo = elemento('a', 'btn btn-primary btn-sm', 'Buscar por código');
            porCodigo.href = urlEscanear;
            const enBienes = elemento('a', 'btn btn-outline-secondary btn-sm', 'Buscar en Bienes');
            enBienes.href = bienesBase;
            acciones.appendChild(porCodigo);
            acciones.appendChild(enBienes);
            caja.appendChild(acciones);
            col.appendChild(caja);
            return col;
        }

        function mostrarResultados(lista) {
            if (!lista || lista.length === 0) {
                estado.textContent = '';
                resultados.replaceChildren(sinResultados());
                return;
            }

            estado.textContent = lista.length + ' bien(es) parecido(s), de más a menos probable:';
            resultados.innerHTML = lista.map(function (b) {
                const pct = Math.round(Math.max(0, b.similitud) * 100);
                const foto = b.foto_url || (b.foto_path ? archivosBase + '/' + b.foto_path : null);

                return (
                    '<div class="col-6 col-md-4 col-lg-3">' +
                        '<div class="card h-100">' +
                            (foto ? '<img src="' + escapeHtml(foto + (foto.indexOf('?') === -1 ? '?' : '&') + 'w=480') + '" class="card-img-top foto-resultado" alt="' + escapeHtml('Foto de ' + (b.descripcion || b.codigo_identificacion)) + '" loading="lazy">' : '') +
                            '<div class="card-body p-2">' +
                                '<div class="small text-muted">' + pct + '% parecido</div>' +
                                '<div class="fw-semibold small text-truncate">' + escapeHtml(b.codigo_identificacion) + '</div>' +
                                '<div class="small text-truncate">' + escapeHtml(b.descripcion) + '</div>' +
                                '<a href="' + bienesBase + '/' + b.id + '/editar" class="btn btn-sm btn-outline-secondary mt-1 w-100">Ver bien</a>' +
                            '</div>' +
                        '</div>' +
                    '</div>'
                );
            }).join('');
        }

        // Tres fallos distintos, cada uno con su porqué y qué hacer (MIA.avisar, alertas.js):
        // el navegador no pudo analizar la foto, no hubo respuesta del servidor, o el
        // servidor rechazó la búsqueda (trae su error y su queHacer).
        async function buscar(file) {
            resultados.replaceChildren();
            MIA.quitarAviso(estado);
            estado.textContent = 'Analizando la foto…';

            let vector;
            try {
                const m = await cargarModelo();
                const img = await cargarImagen(await leerComoDataUrl(file));
                vector = calcularVector(m, img);
            } catch (e) {
                estado.textContent = '';
                MIA.avisar(estado, {
                    titulo: 'No se pudo analizar la foto',
                    detalle: 'Este navegador no pudo leer la foto o preparar la búsqueda por parecido.',
                    solucion: 'Prueba con otra foto (JPG o PNG) o con Chrome o Edge actualizados. También puedes buscar el bien por su placa en «Escanear QR».'
                });
                return;
            }

            estado.textContent = 'Buscando bienes parecidos…';
            let resp;
            let datos;
            try {
                const body = new URLSearchParams({ _csrf: csrfToken, vector: JSON.stringify(vector) });
                resp = await fetch(urlBuscar, { method: 'POST', body });
                if ((resp.headers.get('Content-Type') || '').indexOf('application/json') === -1) {
                    // El servidor respondió con otra página: la sesión se cerró.
                    estado.textContent = '';
                    MIA.avisar(estado, {
                        titulo: 'No se pudo buscar',
                        detalle: 'Tu sesión se cerró mientras tenías la página abierta.',
                        solucion: 'Recarga la página, ingresa de nuevo y vuelve a tomar la foto.'
                    });
                    return;
                }
                datos = await resp.json();
            } catch (e) {
                estado.textContent = '';
                MIA.avisar(estado, {
                    titulo: 'No se pudo buscar',
                    detalle: 'MIA no recibió respuesta del servidor.',
                    solucion: 'Revisa tu conexión a internet y pulsa Reintentar.',
                    boton: { texto: 'Reintentar', alPulsar: function () { buscar(file); } }
                });
                return;
            }

            if (!resp.ok) {
                estado.textContent = '';
                MIA.avisar(estado, {
                    titulo: 'No se pudo buscar',
                    detalle: datos.error || 'El servidor no aceptó la búsqueda.',
                    solucion: datos.queHacer || 'Recarga la página y vuelve a tomar la foto.'
                });
                return;
            }

            mostrarResultados(datos.resultados);
        }

        inputFoto.addEventListener('change', function () {
            if (inputFoto.files && inputFoto.files[0]) {
                buscar(inputFoto.files[0]);
            }
        });

        indexarPendientes();
    })();
    </script>
<?php endif; ?>
