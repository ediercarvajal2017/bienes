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
        <p class="text-muted small mb-0">Selecciona uno o varios bienes y asígnalos (o reasígnalos) a un espacio o a una persona.</p>
    </div>
    <a href="<?= Url::to('/bienes') ?>" class="btn btn-sm btn-outline-secondary">Volver</a>
</div>

<?php if (!empty($mensaje)): ?><div class="alert alert-success py-2 small"><?= htmlspecialchars($mensaje, ENT_QUOTES) ?></div><?php endif; ?>
<?php if (!empty($error)): ?><div class="alert alert-danger py-2 small"><?= htmlspecialchars($error, ENT_QUOTES) ?></div><?php endif; ?>

<?php if (Auth::esSuperusuario()): ?>
    <div class="mb-3" style="max-width: 320px;">
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
        window.location = <?= json_encode(Url::to('/asignaciones')) ?> + (this.value ? '?institucion=' + encodeURIComponent(this.value) : '');
    });
    </script>
<?php endif; ?>

<?php if ($institucionId === null): ?>
    <p class="text-muted">Selecciona una institución para continuar.</p>
<?php elseif ($total === 0 && $q === '' && $espacioFiltro === null && $responsableFiltro === null && $tipoFiltro === null): ?>
    <p class="text-muted">
        No hay bienes disponibles para asignar en esta institución (ya están todos asignados, o aún no se ha
        registrado ninguno — puedes hacerlo en "<a href="<?= Url::to('/bienes/crear') ?>">Registrar bien</a>").
    </p>
<?php else: ?>

    <form method="post" action="<?= Url::to('/asignaciones') ?>" id="formAsignar">
        <?= Csrf::field() ?>
        <input type="hidden" name="institucion_id" value="<?= $institucionId ?>">

        <div class="card mb-3" style="max-width: 760px;">
            <div class="card-body py-3">
                <h2 class="h6 mb-3">Datos de la asignación</h2>

                <div class="row g-3">
                    <div class="col-md-7">
                        <?php View::render('partials/campos_responsabilidad', [
                            'prefijo' => 'masivo',
                            'idEspacio' => 'campo-espacio-id',
                            'nombres' => ['tipo' => 'tipo_responsabilidad', 'espacio' => 'espacio_id', 'persona' => 'persona_id'],
                            'espacios' => $espacios,
                            'personas' => $personas,
                            'valores' => [
                                'tipo' => (string) ($viejo['tipo_responsabilidad'] ?? 'grupal'),
                                'espacio' => (string) ($viejo['espacio_id'] ?? ''),
                                'persona' => (string) ($viejo['persona_id'] ?? ''),
                            ],
                            'espacioObligatorio' => true,
                        ]); ?>
                        <?php if (empty($espacios)): ?>
                            <div class="form-text text-danger">No hay espacios creados en esta institución. Para la responsabilidad grupal, crea uno en "Espacios".</div>
                        <?php endif; ?>
                    </div>
                    <div class="col-md-5">
                        <label for="campo-fecha-asignacion" class="form-label small">Fecha de asignación</label>
                        <input id="campo-fecha-asignacion" type="date" min="<?= \App\Helpers\FechaMovimiento::MINIMA ?>" max="<?= \App\Helpers\FechaMovimiento::hoy() ?>" name="fecha_asignacion" class="form-control form-control-sm" value="<?= htmlspecialchars($viejo['fecha_asignacion'] ?? date('Y-m-d'), ENT_QUOTES) ?>" required>
                    </div>
                </div>

                <div class="mt-3">
                    <label for="campo-observaciones" class="form-label small">Observaciones (opcional, aplica a todos)</label>
                    <input id="campo-observaciones" type="text" name="observaciones" class="form-control form-control-sm" value="<?= htmlspecialchars($viejo['observaciones'] ?? '', ENT_QUOTES) ?>">
                </div>
            </div>
        </div>

        <?php
        $queryBase = array_filter(['institucion' => $institucionId, 'q' => $q, 'espacio' => $espacioFiltro,
            'responsable' => $responsableFiltro, 'tipo' => $tipoFiltro], static fn ($v) => $v !== null && $v !== '');
        $urlBasePaginacion = Url::to('/asignaciones') . '?' . http_build_query($queryBase);
        $hayFiltros = $espacioFiltro !== null || $responsableFiltro !== null || $tipoFiltro !== null || $q !== '';
        ?>

        <h2 class="h6 mb-2">Bienes (<?= $total ?>)</h2>
        <div class="d-flex flex-wrap gap-3 align-items-end mb-2" id="filtrosAsignacion">
            <div class="filtro-item">
                <label for="filtroAsigEspacio" class="form-label small mb-1">Espacio</label>
                <select id="filtroAsigEspacio" data-filtro="espacio" class="form-select form-select-sm selector-buscable">
                    <option value="">Todos los espacios</option>
                    <?php foreach ($espacios as $e): ?>
                        <option value="<?= (int) $e['id'] ?>" <?= $espacioFiltro === (int) $e['id'] ? 'selected' : '' ?>><?= htmlspecialchars($e['codigo'] . ' - ' . $e['nombre'], ENT_QUOTES) ?></option>
                    <?php endforeach; ?>
                </select>
            </div>
            <div class="filtro-item">
                <label for="filtroAsigResponsable" class="form-label small mb-1">Responsable</label>
                <select id="filtroAsigResponsable" data-filtro="responsable" class="form-select form-select-sm selector-buscable">
                    <option value="">Todos los responsables</option>
                    <?php foreach ($responsablesFiltro as $r): ?>
                        <option value="<?= (int) $r['id'] ?>" <?= $responsableFiltro === (int) $r['id'] ? 'selected' : '' ?>>
                            <?= htmlspecialchars(trim($r['nombres'] . ' ' . $r['apellidos']) . ((int) $r['activo'] === 1 ? '' : ' (inactivo)'), ENT_QUOTES) ?>
                        </option>
                    <?php endforeach; ?>
                </select>
            </div>
            <div class="filtro-item">
                <label for="filtroAsigTipo" class="form-label small mb-1">Responsabilidad</label>
                <select id="filtroAsigTipo" data-filtro="tipo" class="form-select form-select-sm">
                    <option value="">Todas</option>
                    <?php foreach ($tipos as $valorTipo => $etiquetaTipo): ?>
                        <option value="<?= $valorTipo ?>" <?= $tipoFiltro === $valorTipo ? 'selected' : '' ?>><?= $etiquetaTipo ?></option>
                    <?php endforeach; ?>
                </select>
            </div>
            <?php if ($hayFiltros && $total > count($bienes)): ?>
                <a class="btn btn-sm btn-outline-primary" id="seleccionarFiltrados"
                   href="<?= htmlspecialchars($urlBasePaginacion . '&porPagina=0&seleccionar=todos', ENT_QUOTES) ?>">
                    Seleccionar los <?= (int) $total ?> filtrados
                </a>
            <?php endif; ?>
        </div>
        <div class="d-flex flex-wrap justify-content-between align-items-end gap-2 mb-2">
            <div style="max-width: 420px; flex: 1 1 260px;">
                <input type="search" id="buscador" data-buscar="q" class="form-control form-control-sm"
                       placeholder="Buscar por código, descripción, responsable, ubicación, estado o valor..."
                       value="<?= htmlspecialchars($q, ENT_QUOTES) ?>">
            </div>
            <button type="submit" class="btn btn-primary btn-sm boton-asignar" disabled>
                <i class="bi bi-person-check me-1"></i>Asignar <span class="badge bg-white text-primary contador-seleccionados">0</span>
            </button>
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
                    <th style="width: 32px;"><input type="checkbox" id="seleccionarTodos" class="form-check-input" aria-label="Seleccionar todos"></th>
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
                        <td data-label="Seleccionar"><input type="checkbox" name="bienes[]" value="<?= $b['id'] ?>" class="form-check-input casilla-bien" aria-label="Seleccionar <?= htmlspecialchars((string) $b['codigo_identificacion'], ENT_QUOTES) ?>" <?= in_array((int) $b['id'], $bienesSeleccionados, true) ? 'checked' : '' ?>></td>
                        <td class="mono" data-label="Código"><?= htmlspecialchars($b['codigo_identificacion'], ENT_QUOTES) ?></td>
                        <td data-label="Descripción"><?= htmlspecialchars($b['descripcion'], ENT_QUOTES) ?></td>
                        <?php if (!$b['asignado']): ?>
                            <td class="text-muted small" data-label="Responsable / ubicación">— Sin asignar —</td>
                            <td data-label="Estado"><span class="badge text-bg-light border">Sin asignar</span></td>
                        <?php else: ?>
                            <td class="small" data-label="Responsable / ubicación">
                                <?php \App\Core\View::render('partials/responsabilidad', ['fila' => $b]); ?>
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

        <div class="accion-fija-movil">
            <button type="submit" class="btn btn-primary mt-2 boton-asignar" disabled>
                <i class="bi bi-person-check me-1"></i>Asignar <span class="badge bg-white text-primary contador-seleccionados">0</span>
            </button>
        </div>
    </form>

    <script>
    (function () {
        const todos = document.getElementById('seleccionarTodos');
        const botones = document.querySelectorAll('.boton-asignar');
        const casillas = document.querySelectorAll('.casilla-bien');

        function actualizarContador() {
            const seleccionadas = Array.from(casillas).filter(function (c) { return c.checked; });
            botones.forEach(function (boton) {
                boton.disabled = seleccionadas.length === 0;
                boton.querySelector('.contador-seleccionados').textContent = seleccionadas.length;
            });
            todos.checked = casillas.length > 0 && seleccionadas.length === casillas.length;
        }

        casillas.forEach(function (c) { c.addEventListener('change', actualizarContador); });

        todos.addEventListener('change', function () {
            casillas.forEach(function (c) { c.checked = todos.checked; });
            actualizarContador();
        });

        // Filtros: al elegir uno se recarga la lista con él (en la página 1).
        document.querySelectorAll('#filtrosAsignacion [data-filtro]').forEach(function (select) {
            select.addEventListener('change', function () {
                const url = new URL(window.location.href);
                if (select.value !== '') { url.searchParams.set(select.dataset.filtro, select.value); }
                else { url.searchParams.delete(select.dataset.filtro); }
                url.searchParams.delete('pagina');
                url.searchParams.delete('seleccionar');
                window.location = url.toString();
            });
        });

        // "Seleccionar los N filtrados": la lista viene completa y todas marcadas.
        if (new URL(window.location.href).searchParams.get('seleccionar') === 'todos') {
            casillas.forEach(function (c) { c.checked = true; });
        }

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

    })();
    </script>
<?php endif; ?>
