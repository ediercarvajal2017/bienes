<?php

use App\Core\Csrf;
use App\Core\Url;
use App\Core\View;

$viejo ??= [];
$movimientosSeleccionados = $viejo['movimientos'] ?? [];
?>
<?php ob_start(); ?>
    <a href="<?= Url::to('/reintegros/lotes') ?>" class="btn btn-sm btn-outline-secondary">Ver lotes</a>
<?= ui('encabezado_pagina', ['titulo' => 'Generar lote de reintegro', 'icono' => 'reintegro', 'subtitulo' => 'Reintegros ya registrados que todavía no están agrupados en ningún lote. Selecciona los que quieras consolidar: se crea un lote por cada categoría (Muebles, Tecnología…), cada uno con su comprobante en Excel.', 'acciones' => (string) ob_get_clean()]) ?>


<?php if (empty($pendientes)): ?>
    <p class="text-muted">No hay reintegros sueltos pendientes de agrupar. Aparecerán aquí cada vez que reintegres un bien.</p>
<?php else: ?>
    <form method="post" action="<?= Url::to('/reintegros/lotes/generar') ?>" id="formGenerarLote">
        <?= Csrf::field() ?>

        <div class="card mb-3 contenedor-md">
            <div class="card-body py-3">
                <h2 class="h6 mb-3">Datos del lote (opcional)</h2>
                <div class="row g-3">
                    <div class="col-md-6">
                        <label for="campo-descripcion" class="form-label small">Descripción</label>
                        <input id="campo-descripcion" type="text" name="descripcion" class="form-control form-control-sm" placeholder="Ej. Entregas de la semana del 20 de julio"
                               value="<?= htmlspecialchars($viejo['descripcion'] ?? '', ENT_QUOTES) ?>">
                    </div>
                    <div class="col-md-6">
                        <label for="campo-observaciones" class="form-label small">Observaciones</label>
                        <input id="campo-observaciones" type="text" name="observaciones" class="form-control form-control-sm" value="<?= htmlspecialchars($viejo['observaciones'] ?? '', ENT_QUOTES) ?>">
                    </div>
                </div>
            </div>
        </div>

        <?php View::render('partials/paginacion', [
            'pagina' => $pagina, 'porPagina' => $porPagina, 'total' => $total, 'totalPaginas' => $totalPaginas,
            'opcionesPorPagina' => $opcionesPorPagina,
            'urlBase' => Url::to('/reintegros/lotes/generar'),
        ]); ?>

        <div class="table-responsive">
            <table class="table table-sm table-hover align-middle bg-white tabla-cards">
                <thead>
                <tr>
                    <th class="columna-casilla"><input type="checkbox" id="seleccionarTodos" class="form-check-input" aria-label="Seleccionar todos"></th>
                    <th></th>
                    <th>Código</th>
                    <th>Descripción</th>
                    <th>Categoría</th>
                    <th>Fecha de reintegro</th>
                    <th>Reintegrado por</th>
                    <th>Destino</th>
                    <th>Espacio de origen</th>
                    <th class="text-end">Valor</th>
                </tr>
                </thead>
                <tbody>
                <?php foreach ($pendientes as $p): ?>
                    <tr>
                        <td data-label="Seleccionar"><input type="checkbox" name="movimientos[]" value="<?= $p['id'] ?>" class="form-check-input casilla-bien" aria-label="Seleccionar <?= htmlspecialchars((string) $p['codigo_identificacion'], ENT_QUOTES) ?>" <?= in_array((int) $p['id'], $movimientosSeleccionados, true) ? 'checked' : '' ?>></td>
                        <td data-label="Foto">
                            <?php if (!empty($p['foto_path'])): ?>
                                <img src="<?= \App\Helpers\EnlaceArchivo::url($p['foto_path'], 96) ?>" loading="lazy"
                                     data-lightbox-src="<?= \App\Helpers\EnlaceArchivo::url($p['foto_path']) ?>"
                                     alt="Foto de <?= htmlspecialchars($p['descripcion'], ENT_QUOTES) ?>"
                                     class="miniatura-36 miniatura-ampliable"
                                     title="Ver foto en grande" loading="lazy">
                            <?php else: ?>
                                <span class="miniatura-vacia">
                                    <?= icono('bien') ?>
                                </span>
                            <?php endif; ?>
                        </td>
                        <td class="mono" data-label="Código"><?= htmlspecialchars($p['codigo_identificacion'], ENT_QUOTES) ?></td>
                        <td data-label="Descripción"><?= htmlspecialchars($p['descripcion'], ENT_QUOTES) ?></td>
                        <td data-label="Categoría">
                            <?php if (!empty($p['categoria_nombre'])): ?>
                                <span class="badge badge-neutro"><?= htmlspecialchars($p['categoria_nombre'], ENT_QUOTES) ?></span>
                            <?php else: ?>
                                <span class="text-muted">Sin categoría</span>
                            <?php endif; ?>
                        </td>
                        <td class="text-muted mono" data-label="Fecha de reintegro"><?= htmlspecialchars($p['fecha_reintegro'], ENT_QUOTES) ?></td>
                        <td class="text-muted small" data-label="Reintegrado por">
                            <?= !empty($p['responsable_nombre']) ? htmlspecialchars($p['responsable_nombre'], ENT_QUOTES) : '—' ?>
                        </td>
                        <td class="text-muted" data-label="Destino"><?= !empty($p['destino_texto']) ? htmlspecialchars($p['destino_texto'], ENT_QUOTES) : '—' ?></td>
                        <td class="text-muted small" data-label="Espacio de origen">
                            <?= !empty($p['espacio_origen_nombre']) ? htmlspecialchars($p['espacio_origen_nombre'], ENT_QUOTES) : '—' ?>
                        </td>
                        <td class="text-end" data-label="Valor"><?= number_format((float) $p['valor'], 2) ?></td>
                    </tr>
                <?php endforeach; ?>
                </tbody>
            </table>
        </div>

        <?php View::render('partials/paginacion', [
            'pagina' => $pagina, 'porPagina' => $porPagina, 'total' => $total, 'totalPaginas' => $totalPaginas,
            'opcionesPorPagina' => $opcionesPorPagina,
            'urlBase' => Url::to('/reintegros/lotes/generar'),
        ]); ?>
        <?php if ($totalPaginas > 1): ?>
            <p class="text-muted small mt-2">La selección solo aplica a los reintegros visibles en esta página; genera el lote antes de pasar de página si quieres incluir más.</p>
        <?php endif; ?>

        <div class="accion-fija-movil">
            <button type="submit" class="btn btn-primary mt-2" id="botonGenerar" disabled>
                <?= icono('crear', 'me-1') ?>Generar lote (<span id="contadorSeleccionados">0</span>)
            </button>
        </div>
    </form>

    <script>
    (function () {
        const casillas = document.querySelectorAll('.casilla-bien');
        const todos = document.getElementById('seleccionarTodos');
        const boton = document.getElementById('botonGenerar');
        const contador = document.getElementById('contadorSeleccionados');

        function actualizar() {
            const seleccionados = document.querySelectorAll('.casilla-bien:checked').length;
            contador.textContent = seleccionados;
            boton.disabled = seleccionados === 0;
            todos.checked = seleccionados === casillas.length;
        }

        todos.addEventListener('change', function () {
            casillas.forEach(function (c) { c.checked = todos.checked; });
            actualizar();
        });
        casillas.forEach(function (c) { c.addEventListener('change', actualizar); });

        document.getElementById('formGenerarLote').addEventListener('submit', function (e) {
            const seleccionados = document.querySelectorAll('.casilla-bien:checked').length;
            if (seleccionados === 0) {
                e.preventDefault();
                return;
            }
            if (!window.miaConfirmarEnvio(e, { mensaje: '¿Agrupar ' + seleccionados + ' reintegro(s)? Se creará un lote por cada categoría.', boton: 'Generar lote' })) {
                return;
            }
        });
    })();
    </script>
<?php endif; ?>
