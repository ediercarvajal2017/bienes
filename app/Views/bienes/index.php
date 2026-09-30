<?php

use App\Core\Auth;
use App\Core\Url;
use App\Core\View;

$parametrosPaginacion = array_filter([
    'q' => $busqueda !== '' ? $busqueda : null,
    'categoria' => $categoriaId,
    'estado' => $estado,
    'espacio' => $espacioId,
    'responsable' => $responsableId,
    'tipo' => $tipo,
], static fn ($valor) => $valor !== null);
$urlBasePaginacion = Url::to('/bienes') . (!empty($parametrosPaginacion) ? '?' . http_build_query($parametrosPaginacion) : '');

$etiquetasEstado = [
    'activo' => 'Activo',
    'reintegrado' => 'Reintegrado',
    'en_reparacion' => 'En reparación',
    'dado_de_baja' => 'Dado de baja',
];

$puedeQr = Auth::rol() !== 'docente' && (Auth::esSuperusuario() || Auth::tienePermiso('bienes.ver'));
$puedeCrear = Auth::esSuperusuario() || Auth::tienePermiso('bienes.crear');
$puedeCargaMasiva = Auth::esSuperusuario() || Auth::tienePermiso('cargas.masivas');
$puedeAsignar = Auth::esSuperusuario() || Auth::tienePermiso('asignaciones.crear');
// Esta vista ya exige el permiso bienes.ver para llegar aquí (ver rutas), así que
// "Buscar por foto" siempre puede mostrarse -- por eso el dropdown ya no depende
// solo de los otros permisos, que sí varían según el rol.
$mostrarAccionesMasivas = true;

$algunFiltroActivo = $busqueda !== '' || $categoriaId !== null || $estado !== null || $espacioId !== null || $responsableId !== null || $tipo !== null;
?>
<div class="d-flex flex-wrap justify-content-between align-items-center gap-2 mb-3">
    <h1 class="h4 mb-0">Bienes</h1>
    <div class="d-flex gap-2">
        <?php if ($mostrarAccionesMasivas): ?>
            <div class="dropdown">
                <button type="button" class="btn btn-outline-secondary btn-sm dropdown-toggle"
                        id="botonAccionesMasivas" aria-haspopup="true" aria-expanded="false">
                    <i class="bi bi-stack me-1"></i>Acciones masivas
                </button>
                <ul class="dropdown-menu dropdown-menu-end" aria-labelledby="botonAccionesMasivas">
                    <li><a class="dropdown-item" href="<?= Url::to('/bienes/buscar-por-foto') ?>">
                        <i class="bi bi-camera me-1"></i>Buscar por foto
                    </a></li>
                    <?php if ($puedeAsignar): ?>
                        <li><a class="dropdown-item" href="<?= Url::to('/asignaciones') ?>">
                            <i class="bi bi-person-check me-1"></i>Asignar bienes
                        </a></li>
                        <li><a class="dropdown-item" href="<?= Url::to('/reintegros') ?>">
                            <i class="bi bi-box-arrow-in-left me-1"></i>Reintegrar bienes
                        </a></li>
                    <?php endif; ?>
                    <?php if ($puedeQr): ?>
                        <li><a class="dropdown-item" href="<?= Url::to('/bienes/qr-masivo') ?>">
                            <i class="bi bi-qr-code me-1"></i>Generar QR masivo
                        </a></li>
                    <?php endif; ?>
                    <?php if ($puedeCrear): ?>
                        <li><a class="dropdown-item" href="<?= Url::to('/bienes/alta-masiva') ?>">
                            <i class="bi bi-boxes me-1"></i>Alta masiva idéntica
                        </a></li>
                    <?php endif; ?>
                    <?php if ($puedeCargaMasiva): ?>
                        <li><a class="dropdown-item" href="<?= Url::to('/cargas-masivas') ?>">
                            <i class="bi bi-upload me-1"></i>Carga masiva de bienes
                        </a></li>
                    <?php endif; ?>
                </ul>
            </div>
        <?php endif; ?>
        <?php if ($puedeCrear): ?>
            <a href="<?= Url::to('/bienes/crear') ?>" class="btn btn-primary btn-sm">
                <i class="bi bi-plus-lg me-1"></i>Registrar bien
            </a>
        <?php endif; ?>
    </div>
</div>

<?php if ($bodegaReintegroTotal > 0 || $bodegaBajaTotal > 0): ?>
    <div class="mb-3 d-flex flex-wrap gap-2">
        <?php if ($bodegaReintegroTotal > 0): ?>
            <a href="<?= Url::to('/bienes') ?>?estado=reintegrado" class="btn btn-sm btn-outline-secondary">
                <i class="bi bi-box-seam me-1"></i>Bodega Reintegro (<?= $bodegaReintegroTotal ?>)
            </a>
        <?php endif; ?>
        <?php if ($bodegaBajaTotal > 0): ?>
            <a href="<?= Url::to('/bienes') ?>?estado=dado_de_baja" class="btn btn-sm btn-outline-secondary">
                <i class="bi bi-trash3 me-1"></i>Bodega de Baja (<?= $bodegaBajaTotal ?>)
            </a>
        <?php endif; ?>
    </div>
<?php endif; ?>

<?php if (!empty($mensaje)): ?>
    <div class="alert alert-success py-2 small"><?= htmlspecialchars($mensaje, ENT_QUOTES) ?></div>
<?php endif; ?>
<?php if (!empty($error)): ?>
    <div class="alert alert-danger py-2 small" role="alert"><?= htmlspecialchars($error, ENT_QUOTES) ?></div>
<?php endif; ?>

<?php if (!empty($soloPropios)): ?>
    <p class="text-muted small">Mostrando solo los bienes de los espacios donde eres responsable.</p>
<?php endif; ?>

<?php $filtrosActivos = count(array_filter([$categoriaId, $estado, $espacioId, $responsableId, $tipo], static fn ($f) => $f !== null)); ?>
<!-- Arriba: Buscar a lo ancho y los botones; abajo: los filtros en columnas iguales. -->
<div class="panel-filtros mb-3">
    <div class="panel-filtros-fila">
        <div class="panel-filtros-busqueda">
            <label for="buscador" class="form-label small mb-1">Buscar</label>
            <input type="search" id="buscador" data-buscar="q" class="form-control form-control-sm"
                   placeholder="Buscar por código, descripción, responsable, ubicación, estado o valor..."
                   value="<?= htmlspecialchars($busqueda, ENT_QUOTES) ?>">
        </div>
        <div class="panel-filtros-acciones">
            <button type="button" class="btn btn-sm btn-outline-secondary d-md-none" data-alternar="filtrosBienes"
                    aria-controls="filtrosBienes" aria-expanded="<?= $filtrosActivos > 0 ? 'true' : 'false' ?>">
                <i class="bi bi-funnel me-1" aria-hidden="true"></i>Filtros<?= $filtrosActivos > 0 ? " ({$filtrosActivos} activo" . ($filtrosActivos === 1 ? '' : 's') . ')' : '' ?>
            </button>
            <?php if ($algunFiltroActivo): ?>
                <a href="<?= Url::to('/bienes') ?>" class="btn btn-sm btn-outline-secondary" id="quitarFiltrosBienes">
                    <i class="bi bi-x-circle me-1" aria-hidden="true"></i>Quitar filtros
                </a>
            <?php endif; ?>
            <?php if ($urlDescarga !== null): ?>
                <a href="<?= htmlspecialchars($urlDescarga, ENT_QUOTES) ?>" class="btn btn-sm btn-outline-secondary" id="descargarCarteraFiltrada" data-sin-cargando
                   title="Descarga la cartera en Excel con los filtros y la búsqueda que tengas puestos">
                    <i class="bi bi-file-earmark-excel me-1" aria-hidden="true"></i>Descargar en Excel
                </a>
            <?php endif; ?>
            <?php if ($urlActa !== null): ?>
                <a href="<?= htmlspecialchars($urlActa, ENT_QUOTES) ?>" class="btn btn-sm btn-outline-secondary" id="descargarActaACargo"
                   title="Acta con los bienes individuales y grupales de este responsable, para imprimir y firmar">
                    <i class="bi bi-file-earmark-text me-1" aria-hidden="true"></i>Acta de bienes a cargo
                </a>
            <?php endif; ?>
        </div>
    </div>
    <?php $numFiltros = 2 + (int) !empty($espacios) + (int) !empty($responsables) + (int) !empty($tipos); ?>
    <div id="filtrosBienes" class="filtros-plegables filtros-rejilla<?= $filtrosActivos > 0 ? ' abierto' : '' ?>"
         style="--filtros: <?= $numFiltros ?>; --filtros-medio: <?= min($numFiltros, 3) ?>;">
    <div class="filtro-item">
        <label for="filtroCategoria" class="form-label small mb-1">Categoría</label>
        <select id="filtroCategoria" class="form-select form-select-sm selector-buscable">
            <option value="">Todas las categorías</option>
            <?php foreach ($categorias as $c): ?>
                <option value="<?= $c['id'] ?>" <?= $categoriaId === (int) $c['id'] ? 'selected' : '' ?>>
                    <?= htmlspecialchars($c['nombre'], ENT_QUOTES) ?>
                </option>
            <?php endforeach; ?>
        </select>
    </div>
    <div class="filtro-item">
        <label for="filtroEstado" class="form-label small mb-1">Estado</label>
        <select id="filtroEstado" class="form-select form-select-sm">
            <option value="">Todos los estados</option>
            <?php foreach ($etiquetasEstado as $valorEstado => $etiqueta): ?>
                <option value="<?= $valorEstado ?>" <?= $estado === $valorEstado ? 'selected' : '' ?>><?= $etiqueta ?></option>
            <?php endforeach; ?>
        </select>
    </div>
    <?php if (!empty($espacios)): ?>
        <div class="filtro-item">
            <label for="filtroEspacio" class="form-label small mb-1">Espacio</label>
            <select id="filtroEspacio" class="form-select form-select-sm selector-buscable">
                <option value="">Todos los espacios</option>
                <?php foreach ($espacios as $e): ?>
                    <option value="<?= $e['id'] ?>" <?= $espacioId === (int) $e['id'] ? 'selected' : '' ?>>
                        <?= htmlspecialchars($e['codigo'] . ' - ' . $e['nombre'], ENT_QUOTES) ?>
                    </option>
                <?php endforeach; ?>
            </select>
        </div>
    <?php endif; ?>
    <?php if (!empty($responsables)): ?>
        <div class="filtro-item">
            <label for="filtroResponsable" class="form-label small mb-1">Responsable</label>
            <select id="filtroResponsable" class="form-select form-select-sm selector-buscable">
                <option value="">Todos los responsables</option>
                <?php foreach ($responsables as $r): ?>
                    <option value="<?= (int) $r['id'] ?>" <?= $responsableId === (int) $r['id'] ? 'selected' : '' ?>>
                        <?= htmlspecialchars(trim($r['nombres'] . ' ' . $r['apellidos']) . ((int) $r['activo'] === 1 ? '' : ' (inactivo)'), ENT_QUOTES) ?>
                    </option>
                <?php endforeach; ?>
            </select>
        </div>
    <?php endif; ?>
    <?php if (!empty($tipos)): ?>
        <div class="filtro-item">
            <label for="filtroTipo" class="form-label small mb-1">Responsabilidad</label>
            <select id="filtroTipo" class="form-select form-select-sm">
                <option value="">Todas</option>
                <?php foreach ($tipos as $valorTipo => $etiquetaTipo): ?>
                    <option value="<?= $valorTipo ?>" <?= $tipo === $valorTipo ? 'selected' : '' ?>><?= $etiquetaTipo ?></option>
                <?php endforeach; ?>
            </select>
        </div>
    <?php endif; ?>
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
            <th></th>
            <th>Descripción</th>
            <th>Código</th>
            <th>Categoría</th>
            <th>Responsable / ubicación</th>
            <th>Valor</th>
            <th>Estado</th>
            <th>QR</th>
            <th></th>
        </tr>
        </thead>
        <tbody>
        <?php if (empty($lotes) && empty($bienes)): ?>
            <?php View::render('partials/tabla_vacia', [
                'colspan' => 9,
                'icono' => 'box-seam',
                'mensaje' => $algunFiltroActivo
                    ? 'Ningún bien coincide con los filtros aplicados.'
                    : 'Todavía no hay bienes registrados.',
                'ctaTexto' => $algunFiltroActivo ? 'Quitar filtros' : ($puedeCrear ? 'Registrar bien' : null),
                'ctaUrl' => $algunFiltroActivo ? Url::to('/bienes') : ($puedeCrear ? Url::to('/bienes/crear') : null),
            ]); ?>
        <?php endif; ?>
        <?php foreach ($lotes as $l): ?>
            <tr>
                <td>
                    <span class="d-inline-flex align-items-center justify-content-center bg-light text-muted" style="width:36px;height:36px;border-radius:4px;" title="Lote de bienes idénticos">
                        <i class="bi bi-boxes"></i>
                    </span>
                </td>
                <td data-label="Descripción">
                    <?= htmlspecialchars($l['descripcion'], ENT_QUOTES) ?>
                    <div class="small text-muted"><?= (int) $l['total'] ?> unidades</div>
                </td>
                <td class="text-muted mono" data-label="Código"><?= htmlspecialchars($l['lote'], ENT_QUOTES) ?></td>
                <td class="text-muted" data-label="Categoría"><?= htmlspecialchars($l['categoria_nombre'] ?? '—', ENT_QUOTES) ?></td>
                <td class="small text-muted" data-label="Responsable / ubicación">—</td>
                <td class="mono" data-label="Valor">$<?= number_format((float) $l['valor_total'], 0, ',', '.') ?></td>
                <td class="small" data-label="Estado">
                    <?= (int) $l['activos'] ?> activas
                    <?php if ((int) $l['reintegrados'] > 0): ?> · <?= (int) $l['reintegrados'] ?> reintegradas<?php endif; ?>
                    <?php if ((int) $l['en_reparacion'] > 0): ?> · <?= (int) $l['en_reparacion'] ?> en reparación<?php endif; ?>
                    <?php if ((int) $l['dados_de_baja'] > 0): ?> · <?= (int) $l['dados_de_baja'] ?> dadas de baja<?php endif; ?>
                </td>
                <td class="text-muted small" data-label="QR">—</td>
                <td class="text-end">
                    <a href="<?= Url::to('/bienes') ?>?q=<?= urlencode($l['lote']) ?>" class="btn btn-sm btn-outline-secondary">
                        <i class="bi bi-eye me-1"></i>Ver detalles
                    </a>
                </td>
            </tr>
        <?php endforeach; ?>
        <?php $bienEditado = (int) ($_GET['editado'] ?? 0); ?>
        <?php foreach ($bienes as $b): ?>
            <tr<?= (int) $b['id'] === $bienEditado ? ' id="bienEditado" class="fila-editada"' : '' ?>>
                <td>
                    <?php if (!empty($b['foto_path'])): ?>
                        <img src="<?= \App\Helpers\EnlaceArchivo::url($b['foto_path'], 96) ?>" loading="lazy"
                             data-lightbox-src="<?= \App\Helpers\EnlaceArchivo::url($b['foto_path']) ?>"
                             alt="Foto de <?= htmlspecialchars($b['descripcion'], ENT_QUOTES) ?>"
                             class="miniatura-36 miniatura-ampliable"
                             title="Ver foto en grande" loading="lazy">
                    <?php else: ?>
                        <span class="d-inline-flex align-items-center justify-content-center bg-light text-muted" style="width:36px;height:36px;border-radius:4px;">
                            <i class="bi bi-box-seam"></i>
                        </span>
                    <?php endif; ?>
                </td>
                <td data-label="Descripción">
                    <?= htmlspecialchars($b['descripcion'], ENT_QUOTES) ?>
                    <?php if (!empty($b['marca'])): ?><div class="small text-muted"><?= htmlspecialchars($b['marca'], ENT_QUOTES) ?></div><?php endif; ?>
                </td>
                <td class="text-muted mono" data-label="Código"><?= htmlspecialchars($b['codigo_identificacion'], ENT_QUOTES) ?></td>
                <td class="text-muted" data-label="Categoría"><?= htmlspecialchars($b['categoria_nombre'] ?? '—', ENT_QUOTES) ?></td>
                <td class="small" data-label="Responsable / ubicación">
                    <?php View::render('partials/responsabilidad', ['fila' => $b]); ?>
                </td>
                <td class="mono" data-label="Valor">$<?= number_format((float) $b['valor'], 0, ',', '.') ?></td>
                <td data-label="Estado"><span class="badge badge-estado-<?= htmlspecialchars($b['estado'], ENT_QUOTES) ?>"><?= $etiquetasEstado[$b['estado']] ?? $b['estado'] ?></span></td>
                <td data-label="QR">
                    <?php if (!empty($b['qr_confirmado_en'])): ?>
                        <span class="badge text-bg-success" title="Confirmada">Confirmada</span>
                    <?php elseif (!empty($b['qr_impreso_en'])): ?>
                        <span class="badge text-bg-warning" title="Impresa, sin confirmar">Sin confirmar</span>
                    <?php else: ?>
                        <span class="badge text-bg-secondary" title="QR sin imprimir todavía">Sin imprimir</span>
                    <?php endif; ?>
                </td>
                <td class="text-end">
                    <a href="<?= Url::to('/bienes/' . $b['id'] . '/editar') ?>" class="btn btn-sm btn-outline-secondary">
                        <?= Auth::esSuperusuario() || Auth::tienePermiso('bienes.editar') ? 'Editar' : 'Ver' ?>
                    </a>
                </td>
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

<script>
(function () {
    const boton = document.getElementById('botonAccionesMasivas');
    if (!boton) { return; }
    const menu = boton.nextElementSibling;

    function cerrar() {
        menu.classList.remove('show');
        boton.setAttribute('aria-expanded', 'false');
    }

    boton.addEventListener('click', function (e) {
        e.stopPropagation();
        const abierto = menu.classList.toggle('show');
        boton.setAttribute('aria-expanded', abierto ? 'true' : 'false');
    });
    document.addEventListener('click', function (e) {
        if (!menu.contains(e.target) && e.target !== boton) { cerrar(); }
    });
    document.addEventListener('keydown', function (e) {
        if (e.key === 'Escape') { cerrar(); }
    });
})();

(function () {

    const filtrosSelect = [
        ['filtroCategoria', 'categoria'],
        ['filtroEstado', 'estado'],
        ['filtroEspacio', 'espacio'],
        ['filtroResponsable', 'responsable'],
        ['filtroTipo', 'tipo'],
    ];
    filtrosSelect.forEach(function (par) {
        const select = document.getElementById(par[0]);
        if (!select) { return; }

        select.addEventListener('change', function () {
            const url = new URL(window.location.href);
            if (select.value !== '') {
                url.searchParams.set(par[1], select.value);
            } else {
                url.searchParams.delete(par[1]);
            }
            url.searchParams.set('pagina', '1');
            window.location = url.toString();
        });
    });
})();
</script>

<script>
// Tras guardar un bien se vuelve a este listado (con su búsqueda y página): se muestra
// la fila del bien editado, resaltada unos segundos.
(function () {
    var fila = document.getElementById('bienEditado');
    if (!fila) { return; }
    fila.scrollIntoView({ block: 'center' });
    window.setTimeout(function () { fila.classList.remove('fila-editada'); }, 4000);
})();
</script>
