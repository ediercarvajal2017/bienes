<?php

use App\Core\Auth;
use App\Core\Csrf;
use App\Core\Url;
use App\Core\View;
use App\Helpers\AyudaCampo;

$viejo ??= [];
?>
<?php ob_start(); ?>
    <?= ui('boton', ['texto' => 'Volver', 'icono' => 'volver', 'tamano' => 'sm', 'url' => Url::to('/bienes')]) ?>
    <?= ui('menu_acciones', ['opciones' => \App\Helpers\AccionesBienes::opciones(), 'actual' => 'reintegrar']) ?>
<?= ui('encabezado_pagina', ['titulo' => 'Reintegrar bienes', 'icono' => 'reintegro', 'ayuda' => AyudaCampo::boton('reintegros'), 'acciones' => (string) ob_get_clean()]) ?>
<p class="text-muted small">Selecciona uno o varios bienes asignados y regístralos como reintegrados. ¿Un código con varias unidades (alta masiva)? <a href="<?= Url::to('/reintegros/unidades') ?>">Reintegrar por cantidad</a>.</p>

<?php if (Auth::esSuperusuario()): ?>
    <div class="mb-3 contenedor-sm">
        <?= AyudaCampo::etiqueta('selectorInstitucion', 'Institución', 'reintegro_institucion') ?>
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

        <div class="card mb-3 contenedor-md">
            <div class="card-body py-3">
                <h2 class="h6 mb-3">Datos del reintegro</h2>

                <div class="row g-3">
                    <div class="col-md-6">
                        <?= AyudaCampo::etiqueta('campo-destino-texto', 'Destino', 'reintegro_destino', true) ?>
                        <input id="campo-destino-texto" type="text" name="destino_texto" class="form-control form-control-sm<?= clase_invalido('destino_texto') ?>" placeholder="Ej. Almacén institucional" required maxlength="200"
                               value="<?= htmlspecialchars($viejo['destino_texto'] ?? '', ENT_QUOTES) ?>">
                        <?= error_campo('destino_texto') ?>
                    </div>
                    <div class="col-md-6">
                        <?= AyudaCampo::etiqueta('campo-fecha', 'Fecha del reintegro', 'reintegro_fecha', true) ?>
                        <input id="campo-fecha" type="date" min="<?= \App\Helpers\FechaMovimiento::MINIMA ?>" max="<?= \App\Helpers\FechaMovimiento::hoy() ?>" name="fecha" class="form-control form-control-sm<?= clase_invalido('fecha') ?>" value="<?= htmlspecialchars($viejo['fecha'] ?? date('Y-m-d'), ENT_QUOTES) ?>" required>
                        <?= error_campo('fecha') ?>
                    </div>
                </div>

                <div class="mt-3">
                    <?= AyudaCampo::etiqueta('campo-observaciones', 'Observaciones (opcional, aplica a todos)', 'reintegro_observaciones') ?>
                    <input id="campo-observaciones" type="text" name="observaciones" class="form-control form-control-sm<?= clase_invalido('observaciones') ?>" maxlength="500" value="<?= htmlspecialchars($viejo['observaciones'] ?? '', ENT_QUOTES) ?>">
                    <?= error_campo('observaciones') ?>
                </div>
            </div>
        </div>

        <?php
        $queryBase = ['institucion' => $institucionId, 'q' => $q];
        if ($categoriaId !== null) {
            $queryBase['categoria'] = $categoriaId;
        }
        if ($soloSeleccionados) {
            $queryBase['seleccionados'] = implode(',', $idsSeleccionados);
        }
        $urlBasePaginacion = Url::to('/reintegros') . '?' . http_build_query($queryBase);
        ?>

        <h2 class="h6 mb-2 d-flex align-items-center gap-1 flex-wrap">
            <?= $soloSeleccionados ? 'Bienes seleccionados' : 'Bienes asignados' ?> (<?= $total ?>)
            <?= \App\Helpers\AyudaCampo::boton('seleccion_paginas') ?>
            <button type="button" id="botonVaciarSeleccion" class="btn btn-link btn-sm p-0 align-baseline ms-2">Vaciar selección</button>
        </h2>
        <div class="mb-2 d-flex flex-wrap gap-3 align-items-center">
            <div class="buscador-flexible">
                <input type="search" id="buscador" data-buscar="q" class="form-control form-control-sm"
                       placeholder="Buscar por código, descripción, responsable, ubicación o valor..."
                       value="<?= htmlspecialchars($q, ENT_QUOTES) ?>">
            </div>
            <?php if (!empty($categorias)): ?>
                <div class="contenedor-sm">
                    <select id="filtroCategoria" class="form-select form-select-sm selector-buscable" aria-label="Filtrar por categoría">
                        <option value="">Todas las categorías</option>
                        <?php foreach ($categorias as $c): ?>
                            <option value="<?= $c['id'] ?>" <?= $categoriaId === (int) $c['id'] ? 'selected' : '' ?>>
                                <?= htmlspecialchars($c['nombre'], ENT_QUOTES) ?>
                            </option>
                        <?php endforeach; ?>
                    </select>
                </div>
            <?php endif; ?>
            <div class="form-check">
                <input type="checkbox" id="verSoloSeleccionados" class="form-check-input" <?= $soloSeleccionados ? 'checked' : '' ?>>
                <label class="form-check-label small" for="verSoloSeleccionados">Ver solo seleccionados</label>
            </div>
        </div>

        <?php if ($soloSeleccionados && $total === 0): ?>
            <p class="text-muted small">No tienes ningún bien seleccionado todavía.</p>
        <?php endif; ?>

        <div class="mb-3 d-flex flex-wrap gap-2 align-items-start">
            <div>
                <button type="button" id="botonEscanear" class="btn btn-sm btn-outline-secondary">
                    <?= icono('escanear', 'me-1') ?>Escanear QR para agregar bienes
                </button>
                <div id="panelEscaner" class="d-none mt-2 contenedor-sm">
                    <div id="lectorQrReintegro" class="rounded border overflow-hidden bg-dark"></div>
                    <div class="d-flex gap-2 mt-2 flex-wrap">
                        <button type="button" id="botonDetenerEscaneo" class="btn btn-sm btn-outline-secondary">Detener escaneo</button>
                    </div>
                    <p id="mensajeEscaneo" class="small mt-2 mb-0"></p>
                </div>
            </div>
            <button type="submit" class="btn btn-primary btn-sm boton-reintegrar" disabled>
                <?= icono('reintegro', 'me-1') ?>Reintegro (<span class="contador-seleccionados">0</span>)
            </button>
        </div>

        <?php View::render('partials/paginacion', ['densidad' => true,
            'pagina' => $pagina, 'porPagina' => $porPagina, 'total' => $total, 'totalPaginas' => $totalPaginas,
            'opcionesPorPagina' => $opcionesPorPagina,
            'urlBase' => $urlBasePaginacion,
        ]); ?>

        <?php if ($bienes === [] && !empty($sinResultados)): ?>
            <?php // Por qué la búsqueda no encontró nada para reintegrar (ReintegroController::explicarSinResultados). ?>
            <?= ui('estado_vacio', $sinResultados + ['clases' => 'mb-3']) ?>
        <?php endif; ?>
        <?php // Un clic en el título ordena todo el listado (App\Helpers\OrdenListado). ?>
        <?php $columnasOrden = [
            'codigo' => ['Código', ['de menor a mayor', 'de mayor a menor']],
            'descripcion' => ['Descripción', ['de la A a la Z', 'de la Z a la A']],
            'valor' => ['Valor', ['de menor a mayor', 'de mayor a menor']],
        ]; ?>
        <?= ui('filtros_activos', ['filtros' => filtro_orden($columnasOrden)]) ?>
        <div data-navegable data-columnas-fijas="3" class="table-responsive">
            <table class="table table-sm table-hover align-middle bg-white tabla-cards">
                <thead>
                <tr>
                    <th class="columna-casilla"><input type="checkbox" id="seleccionarTodos" class="form-check-input" aria-label="Seleccionar todos"></th>
                    <th></th>
                    <?= th_orden('codigo', $columnasOrden) ?>
                    <?= th_orden('descripcion', $columnasOrden) ?>
                    <th>Responsable / ubicación</th>
                    <?= th_orden('valor', $columnasOrden, 'text-end') ?>
                </tr>
                </thead>
                <tbody>
                <?php foreach ($bienes as $b): ?>
                    <tr class="fila-bien fila-seleccionable">
                        <td data-label="Seleccionar"><input type="checkbox" name="bienes[]" value="<?= $b['id'] ?>" class="form-check-input casilla-bien" aria-label="Seleccionar <?= htmlspecialchars((string) $b['codigo_identificacion'], ENT_QUOTES) ?>"></td>
                        <td data-label="Foto">
                            <?php if (!empty($b['foto_path'])): ?>
                                <img src="<?= \App\Helpers\EnlaceArchivo::url($b['foto_path'], 96) ?>" loading="lazy"
                                     data-lightbox-src="<?= \App\Helpers\EnlaceArchivo::url($b['foto_path']) ?>"
                                     alt="Foto de <?= htmlspecialchars($b['descripcion'], ENT_QUOTES) ?>"
                                     class="miniatura-36 miniatura-ampliable"
                                     title="Ver foto en grande" loading="lazy">
                            <?php else: ?>
                                <span class="miniatura-vacia">
                                    <?= icono('bien') ?>
                                </span>
                            <?php endif; ?>
                        </td>
                        <td class="mono" data-label="Código"><?= resaltar($b['codigo_identificacion'], $q) ?></td>
                        <td data-label="Descripción">
                            <?= resaltar($b['descripcion'], $q) ?>
                            <?php if (!empty($b['categoria_nombre'])): ?>
                                <div class="text-muted small"><?= htmlspecialchars($b['categoria_nombre'], ENT_QUOTES) ?></div>
                            <?php endif; ?>
                        </td>
                        <td class="small" data-label="Responsable / ubicación">
                            <?php \App\Core\View::render('partials/responsabilidad', ['fila' => $b, 'resaltar' => $q]); ?>
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

        <button type="submit" class="btn btn-primary mt-2 boton-reintegrar" disabled>
            <?= icono('reintegro', 'me-1') ?>Reintegro (<span class="contador-seleccionados">0</span>)
        </button>
    </form>

    <script src="https://cdn.jsdelivr.net/npm/html5-qrcode@2.3.8/html5-qrcode.min.js" integrity="sha384-c9d8RFSL+u3exBOJ4Yp3HUJXS4znl9f+z66d1y54ig+ea249SpqR+w1wyvXz/lk+" crossorigin="anonymous"></script>
    <script>
    (function () {
        const todos = document.getElementById('seleccionarTodos');
        const botones = document.querySelectorAll('.boton-reintegrar');
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
        <?php if (!empty($limpiarSeleccion)): ?>
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
            botones.forEach(function (boton) {
                boton.querySelector('.contador-seleccionados').textContent = totalSeleccionado();
                boton.disabled = totalSeleccionado() === 0;
            });
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
                if (e.target.closest('input, a, button, [data-lightbox-src]')) { return; }
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
                        window.miaConfirmar({ titulo: 'Sin selección', mensaje: 'No tienes ningún bien seleccionado todavía.\nQué hacer: marca la casilla de cada bien en la lista o escanea su QR, y vuelve a marcar «Ver solo seleccionados».', boton: 'Entendido', soloAceptar: true });
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
            if (!window.miaConfirmarEnvio(e, { mensaje: '¿Reintegrar ' + total + ' bien(es)? Saldrán de su espacio y quedarán listos para el lote de reintegro.', boton: 'Reintegrar' })) {
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
                    mostrarMensajeEscaneo('No se pudo consultar el bien: MIA no recibió respuesta. Revisa tu conexión y vuelve a escanearlo.', 'error');
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


        const filtroCategoria = document.getElementById('filtroCategoria');
        if (filtroCategoria) {
            filtroCategoria.addEventListener('change', function () {
                const url = new URL(window.location.href);
                if (filtroCategoria.value !== '') {
                    url.searchParams.set('categoria', filtroCategoria.value);
                } else {
                    url.searchParams.delete('categoria');
                }
                url.searchParams.set('pagina', '1');
                window.location = url.toString();
            });
        }
    })();
    </script>
<?php endif; ?>
