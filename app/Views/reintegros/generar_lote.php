<?php

use App\Core\Csrf;
use App\Core\Url;
use App\Core\View;

?>
<div class="d-flex flex-wrap justify-content-between align-items-center gap-2 mb-3">
    <div>
        <h1 class="h4 mb-0">Generar lote de reintegro</h1>
        <p class="text-muted small mb-0">Reintegros ya registrados que todavía no están agrupados en ningún lote. Selecciona los que quieras consolidar y genera el formato.</p>
    </div>
    <a href="<?= Url::to('/reintegros/lotes') ?>" class="btn btn-sm btn-outline-secondary">Ver lotes</a>
</div>

<?php if (!empty($error)): ?><div class="alert alert-danger py-2 small"><?= htmlspecialchars($error, ENT_QUOTES) ?></div><?php endif; ?>

<?php if (empty($pendientes)): ?>
    <p class="text-muted">No hay reintegros sueltos pendientes de agrupar. Aparecerán aquí cada vez que reintegres un bien.</p>
<?php else: ?>
    <form method="post" action="<?= Url::to('/reintegros/lotes/generar') ?>" id="formGenerarLote">
        <?= Csrf::field() ?>

        <div class="card mb-3" style="max-width: 680px;">
            <div class="card-body py-3">
                <h2 class="h6 mb-3">Datos del lote (opcional)</h2>
                <div class="row g-3">
                    <div class="col-md-6">
                        <label class="form-label small">Descripción</label>
                        <input type="text" name="descripcion" class="form-control form-control-sm" placeholder="Ej. Entregas de la semana del 20 de julio">
                    </div>
                    <div class="col-md-6">
                        <label class="form-label small">Observaciones</label>
                        <input type="text" name="observaciones" class="form-control form-control-sm">
                    </div>
                </div>
            </div>
        </div>

        <div class="table-responsive">
            <table class="table table-sm table-hover align-middle bg-white tabla-cards">
                <thead>
                <tr>
                    <th style="width: 32px;"><input type="checkbox" id="seleccionarTodos" class="form-check-input"></th>
                    <th>Código</th>
                    <th>Descripción</th>
                    <th>Fecha de reintegro</th>
                    <th>Destino</th>
                    <th>Espacio de origen</th>
                    <th class="text-end">Valor</th>
                </tr>
                </thead>
                <tbody>
                <?php foreach ($pendientes as $p): ?>
                    <tr>
                        <td data-label="Seleccionar"><input type="checkbox" name="movimientos[]" value="<?= $p['id'] ?>" class="form-check-input casilla-bien"></td>
                        <td class="mono" data-label="Código"><?= htmlspecialchars($p['codigo_identificacion'], ENT_QUOTES) ?></td>
                        <td data-label="Descripción"><?= htmlspecialchars($p['descripcion'], ENT_QUOTES) ?></td>
                        <td class="text-muted mono" data-label="Fecha de reintegro"><?= htmlspecialchars($p['fecha_reintegro'], ENT_QUOTES) ?></td>
                        <td class="text-muted" data-label="Destino"><?= htmlspecialchars($p['destino_texto'], ENT_QUOTES) ?></td>
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
            'urlBase' => Url::to('/reintegros/lotes/generar'),
        ]); ?>
        <?php if ($totalPaginas > 1): ?>
            <p class="text-muted small mt-2">La selección solo aplica a los reintegros visibles en esta página; genera el lote antes de pasar de página si quieres incluir más.</p>
        <?php endif; ?>

        <button type="submit" class="btn btn-primary mt-2" id="botonGenerar" disabled>
            <i class="bi bi-file-earmark-plus me-1"></i>Generar lote (<span id="contadorSeleccionados">0</span>)
        </button>
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
            if (!confirm('¿Generar un lote con ' + seleccionados + ' reintegro(s) seleccionado(s)?')) {
                e.preventDefault();
            }
        });
    })();
    </script>
<?php endif; ?>
