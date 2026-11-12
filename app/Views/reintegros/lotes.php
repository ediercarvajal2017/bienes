<?php

use App\Core\Auth;
use App\Core\Csrf;
use App\Core\Url;
use App\Core\View;
use App\Helpers\FechaMovimiento;
use App\Models\LoteReintegro;

$insignias = [
    'pendiente' => 'badge-lote badge-lote-pendiente',
    'reintegrado' => 'badge-lote badge-lote-reintegrado',
    'unificado' => 'badge-lote badge-lote-unificado',
];
$pestanas = [
    'pendiente' => 'Pendientes',
    'reintegrado' => 'Reintegrados',
    'unificado' => 'Unificados',
    'todos' => 'Todos',
];
$conteos['todos'] = array_sum($conteos);

// Parámetros actuales (sin vacíos) para armar enlaces que conserven los filtros.
$parametros = array_filter(['estado' => $pestana] + $filtros, static fn ($v): bool => $v !== null && $v !== '');
$url = static fn (array $cambios = []): string => Url::to('/reintegros/lotes') . '?'
    . http_build_query(array_filter($cambios + $parametros, static fn ($v): bool => $v !== null && $v !== ''));
$urlRegreso = '/reintegros/lotes?' . http_build_query($parametros + ['pagina' => $pagina, 'porPagina' => $porPagina]);

$hayPendientesEnPagina = (bool) array_filter($lotes, static fn (array $l): bool => $l['estado'] === 'pendiente');
$hayFiltros = $filtros['categoria'] !== null || $filtros['desde'] !== null || $filtros['hasta'] !== null;
$e = static fn (?string $texto): string => htmlspecialchars((string) $texto, ENT_QUOTES);
?>
<div class="d-flex flex-wrap justify-content-between align-items-center gap-2 mb-3">
    <div>
        <h1 class="h4 mb-0">Lotes de reintegro</h1>
        <p class="text-muted small mb-0">Cada lote agrupa reintegros de una categoría para entregarlos a la Alcaldía con el comprobante FO-ADMI-009 (Excel). Puedes unificar varios lotes pendientes; cuando los entregues, márcalos como reintegrados.</p>
    </div>
    <div class="d-flex gap-2">
        <a href="<?= Url::to('/reintegros') ?>" class="btn btn-sm btn-outline-secondary">Ir a reintegrar</a>
        <a href="<?= Url::to('/reintegros/lotes/generar') ?>" class="btn btn-sm btn-primary position-relative">
            <i class="bi bi-file-earmark-plus me-1"></i>Generar lote
            <?php if ($pendientesDeLote > 0): ?>
                <span class="badge rounded-pill text-bg-warning ms-1"><?= $pendientesDeLote ?></span>
            <?php endif; ?>
        </a>
    </div>
</div>

<?php if (!empty($mensaje)): ?><div class="alert alert-success py-2 small"><?= $e($mensaje) ?></div><?php endif; ?>
<?php if (!empty($error)): ?><div class="alert alert-danger py-2 small"><?= $e($error) ?></div><?php endif; ?>

<?php if ($pendientesDeLote > 0): ?>
    <div class="alert alert-warning py-2 small">
        Tienes <?= $pendientesDeLote ?> reintegro(s) sin agrupar todavía.
        <a href="<?= Url::to('/reintegros/lotes/generar') ?>">Generar lote ahora</a>.
    </div>
<?php endif; ?>

<ul class="nav nav-tabs mb-3">
    <?php foreach ($pestanas as $valor => $texto): ?>
        <?php if ($valor === 'unificado' && $conteos['unificado'] === 0 && $pestana !== 'unificado') { continue; } ?>
        <li class="nav-item">
            <a class="nav-link<?= $pestana === $valor ? ' active' : '' ?>" <?= $pestana === $valor ? 'aria-current="page"' : '' ?>
               href="<?= $e($url(['estado' => $valor])) ?>">
                <?= $texto ?>
                <span class="badge rounded-pill <?= $valor === 'pendiente' && $conteos[$valor] > 0 ? 'text-bg-warning' : 'text-bg-light border' ?> ms-1"><?= (int) $conteos[$valor] ?></span>
            </a>
        </li>
    <?php endforeach; ?>
</ul>

<form method="get" action="<?= Url::to('/reintegros/lotes') ?>" class="row g-2 align-items-end mb-3">
    <input type="hidden" name="estado" value="<?= $e($pestana) ?>">
    <div class="col-12 col-sm-4 col-md-3">
        <label for="filtroCategoria" class="form-label small mb-1">Categoría</label>
        <select id="filtroCategoria" name="categoria" class="form-select form-select-sm">
            <option value="">Todas</option>
            <?php foreach ($categorias as $nombre): ?>
                <?php $valor = $nombre === LoteReintegro::NOMBRE_SIN_CATEGORIA ? LoteReintegro::FILTRO_SIN_CATEGORIA : $nombre; ?>
                <option value="<?= $e($valor) ?>" <?= $filtros['categoria'] === $valor ? 'selected' : '' ?>><?= $e($nombre) ?></option>
            <?php endforeach; ?>
        </select>
    </div>
    <div class="col-6 col-sm-3 col-md-2">
        <label for="filtroDesde" class="form-label small mb-1">Lote desde</label>
        <input id="filtroDesde" type="date" name="desde" class="form-control form-control-sm" value="<?= $e($filtros['desde']) ?>">
    </div>
    <div class="col-6 col-sm-3 col-md-2">
        <label for="filtroHasta" class="form-label small mb-1">Lote hasta</label>
        <input id="filtroHasta" type="date" name="hasta" class="form-control form-control-sm" value="<?= $e($filtros['hasta']) ?>">
    </div>
    <div class="col-12 col-sm-auto d-flex gap-2">
        <button type="submit" class="btn btn-sm btn-outline-primary"><i class="bi bi-funnel me-1"></i>Filtrar</button>
        <?php if ($hayFiltros): ?>
            <a href="<?= $e(Url::to('/reintegros/lotes') . '?estado=' . $pestana) ?>" class="btn btn-sm btn-link">Quitar filtros</a>
        <?php endif; ?>
    </div>
</form>

<?php if (empty($lotes)): ?>
    <p class="text-muted">
        <?= match (true) {
            $hayFiltros => 'Ningún lote coincide con los filtros.',
            $pestana === 'pendiente' => 'No hay lotes pendientes de entregar.',
            $pestana === 'reintegrado' => 'Todavía no has marcado ningún lote como reintegrado.',
            default => 'Todavía no se ha generado ningún lote.',
        } ?>
    </p>
<?php else: ?>
    <?php View::render('partials/paginacion', [
        'pagina' => $pagina, 'porPagina' => $porPagina, 'total' => $total, 'totalPaginas' => $totalPaginas,
        'opcionesPorPagina' => $opcionesPorPagina,
        'urlBase' => $url(),
    ]); ?>

    <form method="post" action="<?= Url::to('/reintegros/lotes/marcar-reintegrados') ?>" id="formLotes">
        <?= Csrf::field() ?>
        <input type="hidden" name="volver" value="<?= $e($urlRegreso) ?>">

        <div class="table-responsive">
            <table class="table table-sm table-hover align-middle bg-white tabla-cards">
                <thead>
                <tr>
                    <th style="width: 32px;">
                        <?php if ($hayPendientesEnPagina): ?>
                            <input type="checkbox" id="seleccionarTodos" class="form-check-input" aria-label="Seleccionar todos los lotes pendientes">
                        <?php endif; ?>
                    </th>
                    <th>Lote</th>
                    <th>Fecha del lote</th>
                    <?php if (Auth::esSuperusuario()): ?><th>Institución</th><?php endif; ?>
                    <th>Categoría</th>
                    <th>Fechas de reintegro</th>
                    <th class="text-end">Bienes</th>
                    <th>Estado</th>
                    <th></th>
                </tr>
                </thead>
                <tbody>
                <?php foreach ($lotes as $l): ?>
                    <?php
                    $categoriasLote = $l['categorias'] !== null ? explode('|', (string) $l['categorias']) : [];
                    $desde = $l['reintegro_desde'];
                    $hasta = $l['reintegro_hasta'];
                    ?>
                    <tr>
                        <td data-label="Seleccionar">
                            <?php if ($l['estado'] === 'pendiente'): ?>
                                <input type="checkbox" name="lotes[]" value="<?= (int) $l['id'] ?>" class="form-check-input casilla-lote"
                                       aria-label="Seleccionar el lote <?= (int) $l['id'] ?>">
                            <?php endif; ?>
                        </td>
                        <td data-label="Lote">
                            <span class="mono fw-semibold">#<?= (int) $l['id'] ?></span>
                            <?php if (!empty($l['destino_texto'])): ?>
                                <div class="text-muted small"><?= $e($l['destino_texto']) ?></div>
                            <?php endif; ?>
                        </td>
                        <td class="mono" data-label="Fecha del lote"><?= $e($l['fecha']) ?></td>
                        <?php if (Auth::esSuperusuario()): ?><td class="text-muted small" data-label="Institución"><?= $e($l['institucion_nombre']) ?></td><?php endif; ?>
                        <td data-label="Categoría">
                            <?php if ($categoriasLote === []): ?>
                                <span class="text-muted">—</span>
                            <?php else: ?>
                                <?php foreach ($categoriasLote as $categoria): ?>
                                    <span class="badge text-bg-light border"><?= $e($categoria) ?></span>
                                <?php endforeach; ?>
                                <?php if (count($categoriasLote) > 1): ?><span class="text-muted small">(mixto)</span><?php endif; ?>
                            <?php endif; ?>
                        </td>
                        <td class="text-muted mono small" data-label="Fechas de reintegro">
                            <?= $desde === null ? '—' : $e($desde) . ($hasta !== $desde ? ' → ' . $e($hasta) : '') ?>
                        </td>
                        <td class="text-end" data-label="Bienes"><?= (int) $l['total_bienes'] ?></td>
                        <td data-label="Estado">
                            <span class="badge <?= $insignias[$l['estado']] ?? 'text-bg-secondary' ?>"><?= $e(LoteReintegro::ESTADOS[$l['estado']] ?? $l['estado']) ?></span>
                            <div class="small text-muted">
                                <?php if ($l['estado'] === 'pendiente'): ?>
                                    <?= $e(FechaMovimiento::antiguedad((string) $l['fecha'])) ?>
                                <?php elseif ($l['estado'] === 'reintegrado'): ?>
                                    Entregado el <span class="mono"><?= $e($l['reintegrado_fecha']) ?></span>
                                <?php elseif (!empty($l['lotes_destino'])): ?>
                                    En
                                    <?php foreach (explode(',', (string) $l['lotes_destino']) as $i => $destinoId): ?><?= $i > 0 ? ', ' : '' ?><a href="<?= Url::to('/reintegros/lotes/' . (int) $destinoId) ?>">#<?= (int) $destinoId ?></a><?php endforeach; ?>
                                <?php endif; ?>
                            </div>
                        </td>
                        <td class="text-end text-nowrap">
                            <a href="<?= Url::to('/reintegros/lotes/' . $l['id']) ?>" class="btn btn-sm btn-outline-secondary">Ver</a>
                            <?php if ($l['estado'] !== 'unificado'): ?>
                                <a href="<?= Url::to('/reintegros/lotes/' . $l['id'] . '/formato.xlsx') ?>" class="btn btn-sm btn-primary" title="Descargar comprobante FO-ADMI-009 (Excel)">
                                    <i class="bi bi-file-earmark-excel me-1"></i>Excel
                                </a>
                            <?php endif; ?>
                        </td>
                    </tr>
                <?php endforeach; ?>
                </tbody>
            </table>
        </div>

        <?php if ($totalPaginas > 1 && $hayPendientesEnPagina): ?>
            <p class="text-muted small mt-2">La selección solo incluye los lotes de esta página. Para marcar muchos lotes viejos a la vez, filtra con "Lote hasta" y elige "Todos" en "Mostrar".</p>
        <?php endif; ?>

        <?php if ($hayPendientesEnPagina): ?>
            <div class="accion-fija-movil">
                <div class="d-flex flex-wrap align-items-center gap-2 mt-2">
                    <button type="button" class="btn btn-success" id="botonMostrarMarcar" disabled aria-expanded="false" aria-controls="panelMarcar">
                        <i class="bi bi-check2-circle me-1"></i>Marcar como reintegrados (<span class="contador-lotes">0</span>)
                    </button>
                    <button type="button" class="btn btn-outline-primary" id="botonUnificar" disabled
                            data-url="<?= Url::to('/reintegros/lotes/unificar') ?>"
                            title="Juntar los lotes elegidos y dejarlos separados en un lote por categoría">
                        <i class="bi bi-diagram-3 me-1"></i>Unificar por categoría (<span class="contador-lotes">0</span>)
                    </button>
                </div>
            </div>

            <div class="card mt-2" id="panelMarcar" hidden style="max-width: 680px;">
                <div class="card-body py-3">
                    <h2 class="h6 mb-1">Confirmar la entrega a la Alcaldía</h2>
                    <p class="small text-muted mb-3">Los lotes marcados salen de "Pendientes" y quedan bloqueados: no se unifican ni se mezclan con reintegros nuevos. El Excel se sigue pudiendo descargar.</p>
                    <div class="row g-3">
                        <div class="col-sm-5">
                            <label for="campoFechaEntrega" class="form-label small">Fecha de entrega</label>
                            <input id="campoFechaEntrega" type="date" name="fecha_entrega" class="form-control form-control-sm" required
                                   value="<?= FechaMovimiento::hoy() ?>" max="<?= FechaMovimiento::hoy() ?>">
                        </div>
                        <div class="col-sm-7">
                            <label for="campoNota" class="form-label small">Nota (opcional)</label>
                            <input id="campoNota" type="text" name="nota" maxlength="500" class="form-control form-control-sm" placeholder="Ej. Recibido por la Unidad de Bienes Muebles">
                        </div>
                    </div>
                    <div class="d-flex gap-2 mt-3">
                        <button type="submit" class="btn btn-sm btn-success" id="botonConfirmarMarcar">
                            Confirmar (<span class="contador-lotes">0</span>)
                        </button>
                        <button type="button" class="btn btn-sm btn-outline-secondary" id="botonCancelarMarcar">Cancelar</button>
                    </div>
                </div>
            </div>
        <?php endif; ?>
    </form>

    <?php View::render('partials/paginacion', [
        'pagina' => $pagina, 'porPagina' => $porPagina, 'total' => $total, 'totalPaginas' => $totalPaginas,
        'opcionesPorPagina' => $opcionesPorPagina,
        'urlBase' => $url(),
    ]); ?>

    <?php if ($hayPendientesEnPagina): ?>
        <script>
        (function () {
            const casillas = document.querySelectorAll('.casilla-lote');
            const todos = document.getElementById('seleccionarTodos');
            const botonMostrar = document.getElementById('botonMostrarMarcar');
            const botonUnificar = document.getElementById('botonUnificar');
            const panel = document.getElementById('panelMarcar');
            const contadores = document.querySelectorAll('.contador-lotes');

            function seleccionados() {
                return document.querySelectorAll('.casilla-lote:checked').length;
            }

            function actualizar() {
                const n = seleccionados();
                contadores.forEach(function (c) { c.textContent = n; });
                botonMostrar.disabled = n === 0;
                botonUnificar.disabled = n === 0;
                todos.checked = n > 0 && n === casillas.length;
                todos.indeterminate = n > 0 && n < casillas.length;
                if (n === 0) { mostrarPanel(false); }
            }

            function mostrarPanel(visible) {
                panel.hidden = !visible;
                botonMostrar.setAttribute('aria-expanded', visible ? 'true' : 'false');
                if (visible) {
                    panel.scrollIntoView({ block: 'nearest', behavior: 'smooth' });
                    document.getElementById('campoFechaEntrega').focus({ preventScroll: true });
                }
            }

            todos.addEventListener('change', function () {
                casillas.forEach(function (c) { c.checked = todos.checked; });
                actualizar();
            });
            casillas.forEach(function (c) { c.addEventListener('change', actualizar); });
            botonMostrar.addEventListener('click', function () { mostrarPanel(panel.hidden); });
            document.getElementById('botonCancelarMarcar').addEventListener('click', function () { mostrarPanel(false); });

            // Unificar: abre la vista previa con los lotes elegidos (no cambia nada todavía).
            botonUnificar.addEventListener('click', function () {
                const parametros = new URLSearchParams();
                document.querySelectorAll('.casilla-lote:checked').forEach(function (c) { parametros.append('lotes[]', c.value); });
                window.location.href = botonUnificar.dataset.url + '?' + parametros.toString();
            });

            document.getElementById('formLotes').addEventListener('submit', function (evento) {
                const n = seleccionados();
                if (n === 0) {
                    evento.preventDefault();
                    return;
                }
                if (!confirm('¿Marcar ' + n + ' lote(s) como reintegrados (ya entregados a la Alcaldía)?')) {
                    evento.preventDefault();
                }
            });

            actualizar();
        })();
        </script>
    <?php endif; ?>
<?php endif; ?>
