<?php

use App\Core\Csrf;
use App\Core\Url;

?>
<div class="d-flex flex-wrap justify-content-between align-items-center gap-2 mb-3">
    <div>
        <h1 class="h4 mb-0">Buscar por foto</h1>
        <p class="text-muted small mb-0">Toma o sube una foto del bien y el sistema busca los más parecidos por su apariencia, aunque no tengas el código a mano.</p>
    </div>
    <a href="<?= Url::to('/bienes') ?>" class="btn btn-sm btn-outline-secondary">Volver</a>
</div>

<?php if ($sinInstitucion): ?>
    <div class="alert alert-warning py-2 small" style="max-width: 640px;">
        Selecciona una institución en el filtro del encabezado para buscar por foto.
    </div>
<?php else: ?>

    <div style="max-width: 420px;">
        <label for="inputFotoBusqueda" class="form-label small">Foto a buscar</label>
        <input type="file" id="inputFotoBusqueda" accept="image/*" class="form-control form-control-sm">
        <p id="notaIndexado" class="small text-muted mt-2 mb-0"></p>
        <p id="estadoBusqueda" class="small mt-2 mb-0"></p>
    </div>

    <div id="resultadosBusqueda" class="row g-3 mt-1" style="max-width: 900px;"></div>

    <p class="text-muted small mt-3" style="max-width: 640px;">
        Los resultados son sugerencias por parecido visual, no una identificación exacta -- confirma
        siempre el código antes de dar por hecho que es el mismo bien.
    </p>

    <script src="https://cdn.jsdelivr.net/npm/@tensorflow/tfjs@4.20.0/dist/tf.min.js"></script>
    <script src="https://cdn.jsdelivr.net/npm/@tensorflow-models/mobilenet@2.1.1/dist/mobilenet.min.js"></script>
    <script>
    (function () {
        const csrfToken = <?= json_encode(Csrf::token()) ?>;
        const archivosBase = <?= json_encode(Url::to('/archivos')) ?>;
        const bienesBase = <?= json_encode(Url::to('/bienes')) ?>;
        const urlPendientes = <?= json_encode(Url::to('/bienes/buscar-por-foto/pendientes')) ?>;
        const urlGuardarVector = <?= json_encode(Url::to('/bienes/buscar-por-foto/vector')) ?>;
        const urlBuscar = <?= json_encode(Url::to('/bienes/buscar-por-foto/buscar')) ?>;

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

                    if (!datos.pendientes || datos.pendientes.length === 0) {
                        notaIndexado.textContent = '';
                        return;
                    }

                    notaIndexado.textContent = 'Preparando fotos existentes para la búsqueda… (' + datos.total + ' pendientes)';

                    for (const bien of datos.pendientes) {
                        try {
                            const img = await cargarImagen(archivosBase + '/' + bien.foto_path);
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

        function mostrarResultados(lista) {
            if (!lista || lista.length === 0) {
                estado.textContent = 'Sin resultados todavía -- puede que ninguna foto guardada se parezca, o que falten fotos por indexar.';
                resultados.innerHTML = '';
                return;
            }

            estado.textContent = lista.length + ' bien(es) parecido(s), de más a menos probable:';
            resultados.innerHTML = lista.map(function (b) {
                const pct = Math.round(Math.max(0, b.similitud) * 100);
                const foto = b.foto_path ? archivosBase + '/' + b.foto_path : null;

                return (
                    '<div class="col-6 col-md-4 col-lg-3">' +
                        '<div class="card h-100">' +
                            (foto ? '<img src="' + foto + '" class="card-img-top" style="height:140px;object-fit:cover;" loading="lazy">' : '') +
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

        async function buscar(file) {
            resultados.innerHTML = '';
            estado.classList.remove('text-danger');
            estado.textContent = 'Analizando la foto…';

            try {
                const m = await cargarModelo();
                const dataUrl = await leerComoDataUrl(file);
                const img = await cargarImagen(dataUrl);
                const vector = calcularVector(m, img);

                estado.textContent = 'Buscando bienes parecidos…';
                const body = new URLSearchParams({ _csrf: csrfToken, vector: JSON.stringify(vector) });
                const resp = await fetch(urlBuscar, { method: 'POST', body });
                const datos = await resp.json();

                if (!resp.ok) {
                    estado.classList.add('text-danger');
                    estado.textContent = datos.error || 'No se pudo completar la búsqueda.';
                    return;
                }

                mostrarResultados(datos.resultados);
            } catch (e) {
                estado.classList.add('text-danger');
                estado.textContent = 'No se pudo procesar la foto en este navegador.';
            }
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
