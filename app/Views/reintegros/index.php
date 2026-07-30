<?php

use App\Core\Auth;
use App\Core\Csrf;
use App\Core\Url;
use App\Core\View;

$viejo ??= [];
?>
<div class="d-flex flex-wrap justify-content-between align-items-center gap-2 mb-3">
    <div>
        <h1 class="h4 mb-0">Reintegrar bienes</h1>
        <p class="text-muted small mb-0">Selecciona uno o varios bienes asignados y regístralos como reintegrados.</p>
    </div>
    <a href="<?= Url::to('/asignaciones') ?>" class="btn btn-sm btn-outline-secondary">
        <i class="bi bi-person-check me-1"></i>Ir a Asignar
    </a>
</div>

<?php if (!empty($mensaje)): ?><div class="alert alert-success py-2 small"><?= htmlspecialchars($mensaje, ENT_QUOTES) ?></div><?php endif; ?>
<?php if (!empty($error)): ?><div class="alert alert-danger py-2 small"><?= htmlspecialchars($error, ENT_QUOTES) ?></div><?php endif; ?>

<?php if (Auth::esSuperusuario()): ?>
    <div class="mb-3" style="max-width: 320px;">
        <label class="form-label small">Institución</label>
        <select id="selectorInstitucion" class="form-select form-select-sm selector-buscable">
            <option value="">-- Selecciona una institución --</option>
            <?php foreach ($instituciones as $i): ?>
                <option value="<?= $i['id'] ?>" <?= $institucionId === (int) $i['id'] ? 'selected' : '' ?>>
                    <?= htmlspecialchars($i['nombre'], ENT_QUOTES) ?>
                </option>
            <?php endforeach; ?>
        </select>
    </div>
    <script>
    document.getElementById('selectorInstitucion').addEventListener('change', function () {
        window.location = <?= json_encode(Url::to('/reintegros')) ?> + (this.value ? '?institucion=' + encodeURIComponent(this.value) : '');
    });
    </script>
<?php endif; ?>

<?php if ($institucionId === null): ?>
    <p class="text-muted">Selecciona una institución para continuar.</p>
<?php elseif ($total === 0 && $q === '' && !$soloSeleccionados): ?>
    <p class="text-muted">No hay bienes pendientes de reintegro en esta institución.</p>
<?php else: ?>

    <form method="post" action="<?= Url::to('/reintegros') ?>" id="formReintegro">
        <?= Csrf::field() ?>
        <input type="hidden" name="institucion_id" value="<?= $institucionId ?>">

        <div class="card mb-3" style="max-width: 760px;">
            <div class="card-body py-3">
                <h2 class="h6 mb-3">Datos del reintegro</h2>

                <div class="row g-3">
                    <div class="col-md-6">
                        <label class="form-label small">Destino</label>
                        <input type="text" name="destino_texto" class="form-control form-control-sm" placeholder="Ej. Almacén institucional" required
                               value="<?= htmlspecialchars($viejo['destino_texto'] ?? '', ENT_QUOTES) ?>">
                    </div>
                    <div class="col-md-6">
                        <label class="form-label small">Fecha del reintegro</label>
                        <input type="date" name="fecha" class="form-control form-control-sm" value="<?= htmlspecialchars($viejo['fecha'] ?? date('Y-m-d'), ENT_QUOTES) ?>" required>
                    </div>
                </div>

                <div class="mt-3">
                    <label class="form-label small">Observaciones (opcional, aplica a todos)</label>
                    <input type="text" name="observaciones" class="form-control form-control-sm" value="<?= htmlspecialchars($viejo['observaciones'] ?? '', ENT_QUOTES) ?>">
                </div>
            </div>
        </div>

        <?php
        $queryBase = ['institucion' => $institucionId, 'q' => $q];
        if ($soloSeleccionados) {
            $queryBase['seleccionados'] = implode(',', $idsSeleccionados);
        }
        $urlBasePaginacion = Url::to('/reintegros') . '?' . http_build_query($queryBase);
        ?>

        <h2 class="h6 mb-2"><?= $soloSeleccionados ? 'Bienes seleccionados' : 'Bienes asignados' ?> (<?= $total ?>)</h2>
        <p class="text-muted small mb-2">
            La selección se mantiene al buscar o cambiar de página — puedes ir marcando bienes en varias páginas antes de reintegrar.
            <button type="button" id="botonVaciarSeleccion" class="btn btn-link btn-sm p-0 align-baseline">Vaciar selección</button>
        </p>
        <div class="mb-2 d-flex flex-wrap gap-3 align-items-center">
            <div style="max-width: 420px; flex: 1 1 260px;">
                <input type="search" id="buscador" class="form-control form-control-sm"
                       placeholder="Buscar por código, descripción, responsable, ubicación o valor..."
                       value="<?= htmlspecialchars($q, ENT_QUOTES) ?>">
            </div>
            <div class="form-check">
                <input type="checkbox" id="verSoloSeleccionados" class="form-check-input" <?= $soloSeleccionados ? 'checked' : '' ?>>
                <label class="form-check-label small" for="verSoloSeleccionados">Ver solo seleccionados</label>
            </div>
        </div>

        <?php if ($soloSeleccionados && $total === 0): ?>
            <p class="text-muted small">No tienes ningún bien seleccionado todavía.</p>
        <?php endif; ?>

        <div class="mb-3">
            <button type="button" id="botonEscanear" class="btn btn-sm btn-outline-primary">
                <i class="bi bi-qr-code-scan me-1"></i>Escanear QR para agregar bienes
            </button>
            <div id="panelEscaner" class="d-none mt-2" style="max-width: 360px;">
                <div id="lectorQrReintegro" class="rounded border overflow-hidden bg-dark"></div>
                <div class="d-flex gap-2 mt-2 flex-wrap">
                    <button type="button" id="botonDetenerEscaneo" class="btn btn-sm btn-outline-secondary">Detener escaneo</button>
                </div>
                <p id="mensajeEscaneo" class="small mt-2 mb-0"></p>
            </div>
        </div>

        <?php View::render('partials/paginacion', [
            'pagina' => $pagina, 'porPagina' => $porPagina, 'total' => $total, 'totalPaginas' => $totalPaginas,
            'opcionesPorPagina' => $opcionesPorPagina,
            'urlBase' => $urlBasePaginacion,
        ]); ?>

        <div class="table-responsive">
            <table class="table table-sm table-hover align-middle bg-white tabla-cards">
                <thead>
                <tr>
                    <th style="width: 32px;"><input type="checkbox" id="seleccionarTodos" class="form-check-input"></th>
                    <th>Código</th>
                    <th>Descripción</th>
                    <th>Responsable / ubicación</th>
                    <th class="text-end">Valor</th>
                </tr>
                </thead>
                <tbody>
                <?php foreach ($bienes as $b): ?>
                    <tr class="fila-bien" style="cursor:pointer;">
                        <td data-label="Seleccionar"><input type="checkbox" name="bienes[]" value="<?= $b['id'] ?>" class="form-check-input casilla-bien"></td>
                        <td class="mono" data-label="Código"><?= htmlspecialchars($b['codigo_identificacion'], ENT_QUOTES) ?></td>
                        <td data-label="Descripción"><?= htmlspecialchars($b['descripcion'], ENT_QUOTES) ?></td>
                        <td class="small" data-label="Responsable / ubicación">
                            <?= htmlspecialchars($b['espacio_nombre'] ?? '—', ENT_QUOTES) ?>
                            <?php if (!empty($b['responsables_nombres'])): ?>
                                <div class="text-muted"><?= htmlspecialchars($b['responsables_nombres'], ENT_QUOTES) ?></div>
                            <?php endif; ?>
                        </td>
                        <td class="text-end" data-label="Valor"><?= number_format((float) $b['valor'], 2) ?></td>
                    </tr>
                <?php endforeach; ?>
                </tbody>
            </table>
        </div>
        <?php View::render('partials/paginacion', [
            'pagina' => $pagina, 'porPagina' => $porPagina, 'total' => $total, 'totalPaginas' => $totalPaginas,
            'opcionesPorPagina' => $opcionesPorPagina,
            'urlBase' => $urlBasePaginacion,
        ]); ?>

        <button type="submit" class="btn btn-primary mt-2" id="botonReintegrar" disabled>
            <i class="bi bi-box-arrow-in-left me-1"></i>Reintegrar seleccionados (<span id="contadorSeleccionados">0</span>)
        </button>
    </form>

    <script src="https://cdn.jsdelivr.net/npm/html5-qrcode@2.3.8/html5-qrcode.min.js"></script>
    <script>
    (function () {
        const todos = document.getElementById('seleccionarTodos');
        const boton = document.getElementById('botonReintegrar');
        const contador = document.getElementById('contadorSeleccionados');
        const casillas = document.querySelectorAll('.casilla-bien');
        const form = document.getElementById('formReintegro');

        // La seleccion se guarda en sessionStorage (por institucion) para que sobreviva
        // al cambiar de pagina o buscar — solo se pierde si el usuario la desmarca a mano
        // o vacia la seleccion, nunca por la sola navegacion entre paginas.
        const claveAlmacenamiento = 'sigebi_reintegro_seleccion_' + <?= json_encode((string) $institucionId) ?>;

        function leerSeleccion() {
            try {
                const guardado = sessionStorage.getItem(claveAlmacenamiento);
                return guardado ? JSON.parse(guardado) : {};
            } catch (e) {
                return {};
            }
        }

        function guardarSeleccion() {
            try {
                sessionStorage.setItem(claveAlmacenamiento, JSON.stringify(seleccion));
            } catch (e) {}
        }

        let seleccion = leerSeleccion(); // { [id]: true }

        // Si la operacion se aplico con exito, la seleccion ya cumplio su proposito.
        <?php if (!empty($mensaje)): ?>
        seleccion = {};
        guardarSeleccion();
        <?php endif; ?>

        casillas.forEach(function (c) {
            if (seleccion[c.value]) {
                c.checked = true;
            }
        });

        function totalSeleccionado() {
            return Object.keys(seleccion).length;
        }

        function actualizarContador() {
            contador.textContent = totalSeleccionado();
            boton.disabled = totalSeleccionado() === 0;
            const marcadasEnPagina = Array.from(casillas).filter(function (c) { return c.checked; });
            todos.checked = casillas.length > 0 && marcadasEnPagina.length === casillas.length;
        }

        casillas.forEach(function (c) {
            c.addEventListener('change', function () {
                if (c.checked) {
                    seleccion[c.value] = true;
                } else {
                    delete seleccion[c.value];
                }
                guardarSeleccion();
                actualizarContador();
            });
        });

        // Clic en cualquier parte de la fila selecciona/deselecciona el bien, no solo la
        // casilla — el clic sobre la casilla misma se ignora aqui para no revertir su
        // propio toggle nativo (ya dispara el listener 'change' de arriba).
        document.querySelectorAll('.fila-bien').forEach(function (fila) {
            fila.addEventListener('click', function (e) {
                if (e.target.closest('input, a, button')) { return; }
                const casilla = fila.querySelector('.casilla-bien');
                if (!casilla) { return; }
                casilla.checked = !casilla.checked;
                casilla.dispatchEvent(new Event('change'));
            });
        });

        todos.addEventListener('change', function () {
            casillas.forEach(function (c) {
                c.checked = todos.checked;
                if (todos.checked) {
                    seleccion[c.value] = true;
                } else {
                    delete seleccion[c.value];
                }
            });
            guardarSeleccion();
            actualizarContador();
        });

        document.getElementById('botonVaciarSeleccion').addEventListener('click', function () {
            seleccion = {};
            guardarSeleccion();
            casillas.forEach(function (c) { c.checked = false; });
            actualizarContador();

            // Si estaba activo el filtro "ver solo seleccionados", ya no tiene nada que
            // mostrar — se vuelve al listado completo en vez de dejar una pantalla vacía.
            const urlActual = new URL(window.location.href);
            if (urlActual.searchParams.has('seleccionados')) {
                urlActual.searchParams.delete('seleccionados');
                urlActual.searchParams.set('pagina', '1');
                window.location = urlActual.toString();
            }
        });

        const verSoloSeleccionados = document.getElementById('verSoloSeleccionados');
        if (verSoloSeleccionados) {
            verSoloSeleccionados.addEventListener('change', function () {
                const url = new URL(window.location.href);

                if (verSoloSeleccionados.checked) {
                    const ids = Object.keys(seleccion);
                    if (ids.length === 0) {
                        verSoloSeleccionados.checked = false;
                        alert('No tienes ningún bien seleccionado todavía.');
                        return;
                    }
                    url.searchParams.set('seleccionados', ids.join(','));
                } else {
                    url.searchParams.delete('seleccionados');
                }

                url.searchParams.set('pagina', '1');
                window.location = url.toString();
            });
        }

        form.addEventListener('submit', function (e) {
            const total = totalSeleccionado();
            if (total === 0) {
                e.preventDefault();
                return;
            }
            if (!confirm('¿Reintegrar ' + total + ' bien(es)?')) {
                e.preventDefault();
                return;
            }

            // Las casillas visibles ya se envian solas por su name="bienes[]"; se agregan
            // campos ocultos solo para lo seleccionado en otras paginas que no esta en pantalla.
            // No se vacia aqui: si el servidor rechaza el envio (ej. falta la fecha) el
            // usuario vuelve a esta pantalla y la seleccion debe seguir intacta. Solo se
            // vacia cuando el flash de exito confirma que el reintegro sí se aplicó.
            const idsEnPagina = new Set(Array.from(casillas).map(function (c) { return c.value; }));
            Object.keys(seleccion).forEach(function (id) {
                if (!idsEnPagina.has(id)) {
                    const input = document.createElement('input');
                    input.type = 'hidden';
                    input.name = 'bienes[]';
                    input.value = id;
                    form.appendChild(input);
                }
            });
        });

        actualizarContador();

        // --- Escaneo de QR para agregar bienes a la selección sin buscarlos a mano ---
        const botonEscanear = document.getElementById('botonEscanear');
        const panelEscaner = document.getElementById('panelEscaner');
        const botonDetenerEscaneo = document.getElementById('botonDetenerEscaneo');
        const mensajeEscaneo = document.getElementById('mensajeEscaneo');

        if (botonEscanear && window.Html5Qrcode) {
            let lector = null;
            let escaneando = false;
            let procesando = false;
            let ultimoToken = null;
            let ultimoEn = 0;

            function mostrarMensajeEscaneo(texto, tipo) {
                mensajeEscaneo.textContent = texto;
                mensajeEscaneo.className = 'small mt-2 mb-0 ' + (tipo === 'error' ? 'text-danger' : 'text-success');
            }

            async function alDetectarQr(textoDecodificado) {
                if (procesando) { return; }

                const ahora = Date.now();
                if (textoDecodificado === ultimoToken && (ahora - ultimoEn) < 3000) {
                    return; // evita reprocesar el mismo QR mientras la camara sigue apuntando a el
                }
                ultimoToken = textoDecodificado;
                ultimoEn = ahora;
                procesando = true;

                try {
                    const url = <?= json_encode(Url::to('/reintegros/buscar-qr')) ?>
                        + '?token=' + encodeURIComponent(textoDecodificado)
                        + '&institucion=' + encodeURIComponent(<?= json_encode((string) $institucionId) ?>);
                    const respuesta = await fetch(url);
                    const datos = await respuesta.json();

                    if (!datos.ok) {
                        mostrarMensajeEscaneo(datos.mensaje || 'No se pudo agregar ese bien.', 'error');
                        return;
                    }

                    if (seleccion[String(datos.id)]) {
                        mostrarMensajeEscaneo(datos.codigo + ' ya estaba en la selección.', 'ok');
                        return;
                    }

                    seleccion[String(datos.id)] = true;
                    guardarSeleccion();
                    actualizarContador();
                    const casilla = document.querySelector('.casilla-bien[value="' + datos.id + '"]');
                    if (casilla) { casilla.checked = true; }
                    mostrarMensajeEscaneo('Agregado: ' + datos.codigo + ' — ' + datos.descripcion, 'ok');
                } catch (e) {
                    mostrarMensajeEscaneo('Error de conexión al buscar el bien.', 'error');
                } finally {
                    procesando = false;
                }
            }

            async function iniciarEscaneo() {
                panelEscaner.classList.remove('d-none');
                if (escaneando) { return; }
                mensajeEscaneo.textContent = '';
                lector = new Html5Qrcode('lectorQrReintegro');
                try {
                    await lector.start({ facingMode: 'environment' }, { fps: 10, qrbox: 240 }, alDetectarQr, function () {});
                    escaneando = true;
                } catch (e) {
                    mostrarMensajeEscaneo('No se pudo acceder a la cámara: ' + (e.message || e), 'error');
                }
            }

            async function detenerEscaneo() {
                if (escaneando && lector) {
                    escaneando = false;
                    await lector.stop().catch(function () {});
                    await lector.clear().catch(function () {});
                }
                panelEscaner.classList.add('d-none');
            }

            botonEscanear.addEventListener('click', iniciarEscaneo);
            botonDetenerEscaneo.addEventListener('click', detenerEscaneo);
        }

        // --- Búsqueda con reload debounceado ---
        const buscador = document.getElementById('buscador');
        let temporizador = null;
        buscador.addEventListener('input', function () {
            clearTimeout(temporizador);
            temporizador = setTimeout(function () {
                const url = new URL(window.location.href);
                const valor = buscador.value.trim();
                if (valor !== '') {
                    url.searchParams.set('q', valor);
                } else {
                    url.searchParams.delete('q');
                }
                url.searchParams.set('pagina', '1');
                window.location = url.toString();
            }, 450);
        });
    })();
    </script>
<?php endif; ?>
