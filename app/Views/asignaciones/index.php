<?php

use App\Core\Auth;
use App\Core\Csrf;
use App\Core\Url;
use App\Core\View;

$viejo ??= [];
$bienesSeleccionados = $viejo['bienes'] ?? [];
?>
<div class="d-flex flex-wrap justify-content-between align-items-center gap-2 mb-3">
    <div>
        <h1 class="h4 mb-0">Asignar bienes</h1>
        <p class="text-muted small mb-0">Selecciona uno o varios bienes y asígnalos (o reasígnalos) a un espacio.</p>
    </div>
    <a href="<?= Url::to('/reintegros') ?>" class="btn btn-sm btn-outline-secondary">
        <i class="bi bi-box-arrow-in-left me-1"></i>Ir a Reintegrar
    </a>
</div>

<?php if (!empty($mensaje)): ?><div class="alert alert-success py-2 small"><?= htmlspecialchars($mensaje, ENT_QUOTES) ?></div><?php endif; ?>
<?php if (!empty($error)): ?><div class="alert alert-danger py-2 small"><?= htmlspecialchars($error, ENT_QUOTES) ?></div><?php endif; ?>

<?php if (Auth::esSuperusuario()): ?>
    <div class="mb-3" style="max-width: 320px;">
        <label class="form-label small">Institución</label>
        <select id="selectorInstitucion" class="form-select form-select-sm">
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
        window.location = <?= json_encode(Url::to('/asignaciones')) ?> + (this.value ? '?institucion=' + encodeURIComponent(this.value) : '');
    });
    </script>
<?php endif; ?>

<?php if ($institucionId === null): ?>
    <p class="text-muted">Selecciona una institución para continuar.</p>
<?php elseif ($total === 0 && $q === ''): ?>
    <p class="text-muted">No hay bienes disponibles para asignar en esta institución.</p>
<?php else: ?>

    <form method="post" action="<?= Url::to('/asignaciones') ?>" id="formAsignar">
        <?= Csrf::field() ?>
        <input type="hidden" name="institucion_id" value="<?= $institucionId ?>">

        <div class="card mb-3" style="max-width: 760px;">
            <div class="card-body py-3">
                <h2 class="h6 mb-3">Datos de la asignación</h2>

                <div class="row g-3">
                    <div class="col-md-6">
                        <label class="form-label small">Espacio / ubicación (define el responsable)</label>
                        <select name="espacio_id" class="form-select form-select-sm" required>
                            <option value="">-- Selecciona --</option>
                            <?php foreach ($espacios as $e): ?>
                                <option value="<?= $e['id'] ?>" <?= ($viejo['espacio_id'] ?? '') === (string) $e['id'] ? 'selected' : '' ?>><?= htmlspecialchars($e['codigo'] . ' - ' . $e['nombre'], ENT_QUOTES) ?></option>
                            <?php endforeach; ?>
                        </select>
                        <?php if (empty($espacios)): ?>
                            <div class="form-text text-danger">No hay espacios creados en esta institución. Crea uno en "Espacios" antes de asignar.</div>
                        <?php endif; ?>
                    </div>
                    <div class="col-md-6">
                        <label class="form-label small">Fecha de asignación</label>
                        <input type="date" name="fecha_asignacion" class="form-control form-control-sm" value="<?= htmlspecialchars($viejo['fecha_asignacion'] ?? date('Y-m-d'), ENT_QUOTES) ?>" required>
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
        $urlBasePaginacion = Url::to('/asignaciones') . '?' . http_build_query($queryBase);
        ?>

        <h2 class="h6 mb-2">Bienes (<?= $total ?>)</h2>
        <div class="mb-2" style="max-width: 420px;">
            <input type="search" id="buscador" class="form-control form-control-sm"
                   placeholder="Buscar por código, descripción, responsable, ubicación, estado o valor..."
                   value="<?= htmlspecialchars($q, ENT_QUOTES) ?>">
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
                    <th>Estado</th>
                    <th class="text-end">Valor</th>
                </tr>
                </thead>
                <tbody>
                <?php foreach ($bienes as $b): ?>
                    <tr>
                        <td data-label="Seleccionar"><input type="checkbox" name="bienes[]" value="<?= $b['id'] ?>" class="form-check-input casilla-bien" <?= in_array((int) $b['id'], $bienesSeleccionados, true) ? 'checked' : '' ?>></td>
                        <td class="mono" data-label="Código"><?= htmlspecialchars($b['codigo_identificacion'], ENT_QUOTES) ?></td>
                        <td data-label="Descripción"><?= htmlspecialchars($b['descripcion'], ENT_QUOTES) ?></td>
                        <?php if (!$b['asignado']): ?>
                            <td class="text-muted small" data-label="Responsable / ubicación">— Sin asignar —</td>
                            <td data-label="Estado"><span class="badge text-bg-light border">Sin asignar</span></td>
                        <?php else: ?>
                            <td class="small" data-label="Responsable / ubicación">
                                <?= htmlspecialchars($b['espacio_nombre'] ?? '—', ENT_QUOTES) ?>
                                <?php if (!empty($b['responsables_nombres'])): ?>
                                    <div class="text-muted"><?= htmlspecialchars($b['responsables_nombres'], ENT_QUOTES) ?></div>
                                <?php endif; ?>
                            </td>
                            <td data-label="Estado"><span class="badge badge-estado-activo">Asignado</span></td>
                        <?php endif; ?>
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

        <button type="submit" class="btn btn-primary mt-2" id="botonAsignar" disabled>
            <i class="bi bi-person-check me-1"></i>Asignar seleccionados (<span id="contadorSeleccionados">0</span>)
        </button>
    </form>

    <script>
    (function () {
        const todos = document.getElementById('seleccionarTodos');
        const boton = document.getElementById('botonAsignar');
        const contador = document.getElementById('contadorSeleccionados');
        const casillas = document.querySelectorAll('.casilla-bien');

        function actualizarContador() {
            const seleccionadas = Array.from(casillas).filter(function (c) { return c.checked; });
            contador.textContent = seleccionadas.length;
            boton.disabled = seleccionadas.length === 0;
            todos.checked = casillas.length > 0 && seleccionadas.length === casillas.length;
        }

        casillas.forEach(function (c) { c.addEventListener('change', actualizarContador); });

        todos.addEventListener('change', function () {
            casillas.forEach(function (c) { c.checked = todos.checked; });
            actualizarContador();
        });

        document.getElementById('formAsignar').addEventListener('submit', function (e) {
            const seleccionadas = Array.from(casillas).filter(function (c) { return c.checked; }).length;
            if (seleccionadas === 0) {
                e.preventDefault();
                return;
            }
            if (!confirm('¿Asignar ' + seleccionadas + ' bien(es)?')) {
                e.preventDefault();
            }
        });

        actualizarContador();

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
