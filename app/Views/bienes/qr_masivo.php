<?php

use App\Core\Auth;
use App\Core\Csrf;
use App\Core\Url;
use App\Core\View;

?>
<?= ui('encabezado_pagina', ['titulo' => 'Generar QR masivo', 'icono' => 'qr',
    'subtitulo' => 'Selecciona los bienes y genera una hoja lista para imprimir, con el código QR en negro y el código del bien debajo.',
    'acciones' => ui('boton', ['texto' => 'Volver', 'icono' => 'volver', 'tamano' => 'sm', 'url' => Url::to('/bienes')])
        . ui('menu_acciones', ['opciones' => \App\Helpers\AccionesBienes::opciones(), 'actual' => 'qr_masivo'])
        . (!empty($totalSolicitados) ? ui('boton', ['texto' => "Bodega de impresión ({$totalSolicitados})", 'icono' => 'imprimir', 'tamano' => 'sm',
            'url' => Url::to('/bienes/qr-masivo/bodega') . ($institucionId !== null ? '?' . http_build_query(['institucion' => $institucionId]) : '')]) : ''),
]) ?>

<?php if (Auth::esSuperusuario()): ?>
    <div class="mb-3 contenedor-sm">
        <label for="selectorInstitucion" class="form-label small">Institución</label>
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
        window.location = <?= json_encode(Url::to('/bienes/qr-masivo')) ?> + (this.value ? '?institucion=' + encodeURIComponent(this.value) : '');
    });
    </script>
<?php endif; ?>

<?php if ($institucionId === null): ?>
    <p class="text-muted">Selecciona una institución para continuar.</p>
<?php else: ?>
    <div class="mb-3 contenedor-sm">
        <input type="search" id="buscador" data-buscar="q" class="form-control form-control-sm"
               placeholder="Buscar por código, descripción, ubicación..."
               value="<?= htmlspecialchars($busqueda, ENT_QUOTES) ?>">
    </div>

    <?php $urlBasePaginacion = Url::to('/bienes/qr-masivo') . '?' . http_build_query(['institucion' => $institucionId, 'q' => $busqueda]); ?>

    <form method="post" action="<?= Url::to('/bienes/qr-masivo') ?>" id="formQrMasivo" target="_blank">
        <?= Csrf::field() ?>
        <input type="hidden" name="institucion_id" value="<?= $institucionId ?>">
        <input type="hidden" name="busqueda" value="<?= htmlspecialchars($busqueda, ENT_QUOTES) ?>">
        <input type="hidden" name="todos_filtrados" id="inputTodosFiltrados" value="0">

        <?php if (empty($bienes)): ?>
            <p class="text-muted">No hay bienes que coincidan con la búsqueda.</p>
        <?php else: ?>
            <?php View::render('partials/paginacion', ['densidad' => true,
                'pagina' => $pagina, 'porPagina' => $porPagina, 'total' => $total, 'totalPaginas' => $totalPaginas,
                'opcionesPorPagina' => $opcionesPorPagina,
                'urlBase' => $urlBasePaginacion,
            ]); ?>

            <?php // Un clic en el título ordena todo el listado (App\Helpers\OrdenListado). ?>
            <?php $columnasOrden = [
                'codigo' => ['Código', ['de menor a mayor', 'de mayor a menor']],
                'descripcion' => ['Descripción', ['de la A a la Z', 'de la Z a la A']],
            ]; ?>
            <?= ui('filtros_activos', ['filtros' => filtro_orden($columnasOrden)]) ?>
            <div data-navegable class="table-responsive">
                <table class="table table-sm table-hover align-middle bg-white tabla-cards">
                    <thead>
                    <tr>
                        <th class="columna-casilla"><input type="checkbox" id="seleccionarTodos" class="form-check-input" aria-label="Seleccionar todos"></th>
                        <?= th_orden('codigo', $columnasOrden) ?>
                        <?= th_orden('descripcion', $columnasOrden) ?>
                        <th>Ubicación</th>
                    </tr>
                    </thead>
                    <tbody>
                    <?php foreach ($bienes as $b): ?>
                        <tr>
                            <td data-label="Seleccionar"><input type="checkbox" name="bienes[]" value="<?= $b['id'] ?>" class="form-check-input casilla-bien" aria-label="Seleccionar <?= htmlspecialchars((string) $b['codigo_identificacion'], ENT_QUOTES) ?>"></td>
                            <td class="mono" data-label="Código"><?= resaltar($b['codigo_identificacion'], $busqueda) ?></td>
                            <td data-label="Descripción"><?= resaltar($b['descripcion'], $busqueda) ?></td>
                            <td class="small text-muted" data-label="Ubicación"><?= !empty($b['espacio_nombre']) ? resaltar($b['espacio_nombre'], $busqueda) : 'Sin asignar' ?></td>
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

            <div class="mb-2 contenedor-sm">
                <label for="campo-formato" class="form-label small mb-1">Formato de impresión</label>
                <select id="campo-formato" name="formato" class="form-select form-select-sm">
                    <option value="hoja">Hoja para recortar (varios QR por página, papel normal)</option>
                    <option value="etiqueta">Etiqueta térmica 50x25mm (una por etiqueta, rollo continuo)</option>
                </select>
            </div>

            <div class="d-flex flex-wrap gap-2 align-items-center mt-3 accion-fija-movil">
                <button type="submit" class="btn btn-primary" id="botonGenerar" disabled>
                    <?= icono('qr', 'me-1') ?>Generar QR (<span id="contadorSeleccionados">0</span>)
                </button>
                <button type="submit" class="btn btn-outline-secondary" id="botonGenerarTodos">
                    <?= icono('qr', 'me-1') ?>Generar QR de los <?= number_format((int) $total, 0, ',', '.') ?> bienes que coinciden con la búsqueda
                </button>
            </div>
        <?php endif; ?>
    </form>

    <script>
    (function () {
        const casillas = document.querySelectorAll('.casilla-bien');
        const todos = document.getElementById('seleccionarTodos');
        const boton = document.getElementById('botonGenerar');
        const contador = document.getElementById('contadorSeleccionados');
        const botonTodos = document.getElementById('botonGenerarTodos');
        const inputTodosFiltrados = document.getElementById('inputTodosFiltrados');
        if (!boton) { return; }

        function actualizar() {
            const seleccionados = document.querySelectorAll('.casilla-bien:checked').length;
            contador.textContent = seleccionados;
            boton.disabled = seleccionados === 0;
            todos.checked = seleccionados === casillas.length && casillas.length > 0;
        }

        todos.addEventListener('change', function () {
            casillas.forEach(function (c) { c.checked = todos.checked; });
            actualizar();
        });
        casillas.forEach(function (c) { c.addEventListener('change', actualizar); });

        botonTodos.addEventListener('click', function () {
            inputTodosFiltrados.value = '1';
        });
        boton.addEventListener('click', function () {
            inputTodosFiltrados.value = '0';
        });
    })();
    </script>
<?php endif; ?>
