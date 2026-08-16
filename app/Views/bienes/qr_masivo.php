<?php

use App\Core\Auth;
use App\Core\Csrf;
use App\Core\Url;
use App\Core\View;

?>
<div class="d-flex flex-wrap justify-content-between align-items-center gap-2 mb-3">
    <div>
        <h1 class="h4 mb-0">Generar QR masivo</h1>
        <p class="text-muted small mb-0">Selecciona los bienes y genera una hoja lista para imprimir, con el código QR en negro y el código del bien debajo.</p>
    </div>
    <div class="d-flex gap-2">
        <?php if (!empty($totalSolicitados)): ?>
            <a href="<?= Url::to('/bienes/qr-masivo/bodega') . ($institucionId !== null ? '?' . http_build_query(['institucion' => $institucionId]) : '') ?>" class="btn btn-sm btn-primary">
                <i class="bi bi-box-seam me-1"></i>Bodega de impresión (<?= $totalSolicitados ?>)
            </a>
        <?php endif; ?>
        <a href="<?= Url::to('/bienes') ?>" class="btn btn-sm btn-outline-secondary">Volver</a>
    </div>
</div>

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
        window.location = <?= json_encode(Url::to('/bienes/qr-masivo')) ?> + (this.value ? '?institucion=' + encodeURIComponent(this.value) : '');
    });
    </script>
<?php endif; ?>

<?php if ($institucionId === null): ?>
    <p class="text-muted">Selecciona una institución para continuar.</p>
<?php else: ?>
    <div class="mb-3" style="max-width: 420px;">
        <input type="search" id="buscador" class="form-control form-control-sm"
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
                        <th>Ubicación</th>
                    </tr>
                    </thead>
                    <tbody>
                    <?php foreach ($bienes as $b): ?>
                        <tr>
                            <td data-label="Seleccionar"><input type="checkbox" name="bienes[]" value="<?= $b['id'] ?>" class="form-check-input casilla-bien"></td>
                            <td class="mono" data-label="Código"><?= htmlspecialchars($b['codigo_identificacion'], ENT_QUOTES) ?></td>
                            <td data-label="Descripción"><?= htmlspecialchars($b['descripcion'], ENT_QUOTES) ?></td>
                            <td class="small text-muted" data-label="Ubicación"><?= !empty($b['espacio_nombre']) ? htmlspecialchars($b['espacio_nombre'], ENT_QUOTES) : 'Sin asignar' ?></td>
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

            <div class="mb-2" style="max-width: 360px;">
                <label class="form-label small mb-1">Formato de impresión</label>
                <select name="formato" class="form-select form-select-sm">
                    <option value="hoja">Hoja para recortar (varios QR por página, papel normal)</option>
                    <option value="etiqueta">Etiqueta térmica 50x25mm (una por etiqueta, rollo continuo)</option>
                </select>
            </div>

            <div class="d-flex flex-wrap gap-2 align-items-center mt-3">
                <button type="submit" class="btn btn-primary" id="botonGenerar" disabled>
                    <i class="bi bi-qr-code me-1"></i>Generar QR (<span id="contadorSeleccionados">0</span>)
                </button>
                <button type="submit" class="btn btn-outline-secondary" id="botonGenerarTodos">
                    <i class="bi bi-qr-code-scan me-1"></i>Generar QR de los <?= $total ?> bienes que coinciden con la búsqueda
                </button>
            </div>
        <?php endif; ?>
    </form>

    <script>
    (function () {
        const input = document.getElementById('buscador');
        let temporizador = null;
        input.addEventListener('input', function () {
            clearTimeout(temporizador);
            temporizador = setTimeout(function () {
                const url = new URL(window.location.href);
                const valor = input.value.trim();
                if (valor !== '') { url.searchParams.set('q', valor); } else { url.searchParams.delete('q'); }
                url.searchParams.set('pagina', '1');
                window.location = url.toString();
            }, 600);
        });
    })();

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
