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
<?php ob_start(); ?>
<?php if ($mostrarAccionesMasivas): ?>
    <div class="dropdown">
        <?= ui('boton', ['texto' => 'Acciones masivas', 'tamano' => 'sm', 'clases' => 'dropdown-toggle',
            'atributos' => ['id' => 'botonAccionesMasivas', 'aria-haspopup' => 'true', 'aria-expanded' => 'false']]) ?>
        <ul class="dropdown-menu dropdown-menu-end" aria-labelledby="botonAccionesMasivas">
            <li><a class="dropdown-item" href="<?= Url::to('/bienes/buscar-por-foto') ?>"><?= icono('foto', 'me-2') ?>Buscar por foto</a></li>
            <?php if ($puedeAsignar): ?>
                <li><a class="dropdown-item" href="<?= Url::to('/asignaciones') ?>"><?= icono('asignacion', 'me-2') ?>Asignar bienes</a></li>
                <li><a class="dropdown-item" href="<?= Url::to('/reintegros') ?>"><?= icono('reintegro', 'me-2') ?>Reintegrar bienes</a></li>
                <li><a class="dropdown-item" href="<?= Url::to('/reintegros/unidades') ?>"><?= icono('reintegro', 'me-2') ?>Reintegrar por cantidad</a></li>
            <?php endif; ?>
            <?php if ($puedeQr): ?>
                <li><a class="dropdown-item" href="<?= Url::to('/bienes/qr-masivo') ?>"><?= icono('qr', 'me-2') ?>Generar QR masivo</a></li>
            <?php endif; ?>
            <?php if ($puedeCrear): ?>
                <li><a class="dropdown-item" href="<?= Url::to('/bienes/alta-masiva') ?>"><?= icono('crear', 'me-2') ?>Alta masiva idéntica</a></li>
            <?php endif; ?>
            <?php if ($puedeCargaMasiva): ?>
                <li><a class="dropdown-item" href="<?= Url::to('/cargas-masivas') ?>"><?= icono('subir', 'me-2') ?>Carga masiva de bienes</a></li>
            <?php endif; ?>
        </ul>
    </div>
<?php endif; ?>
<?php if ($puedeCrear): ?>
    <?= ui('boton', ['texto' => 'Registrar bien', 'tipo' => 'primario', 'icono' => 'crear', 'tamano' => 'sm', 'url' => Url::to('/bienes/crear')]) ?>
<?php endif; ?>
<?= ui('encabezado_pagina', ['titulo' => 'Bienes', 'icono' => 'bien', 'acciones' => (string) ob_get_clean()]) ?>

<?php if ($bodegaReintegroTotal > 0 || $bodegaBajaTotal > 0): ?>
    <div class="mb-3 d-flex flex-wrap gap-2">
        <?php if ($bodegaReintegroTotal > 0): ?>
            <?= ui('boton', ['texto' => "Bodega Reintegro ({$bodegaReintegroTotal})", 'icono' => 'reintegro', 'tamano' => 'sm', 'url' => Url::to('/bienes') . '?estado=reintegrado']) ?>
        <?php endif; ?>
        <?php if ($bodegaBajaTotal > 0): ?>
            <?= ui('boton', ['texto' => "Bodega de Baja ({$bodegaBajaTotal})", 'icono' => 'baja', 'tamano' => 'sm', 'url' => Url::to('/bienes') . '?estado=dado_de_baja']) ?>
        <?php endif; ?>
    </div>
<?php endif; ?>

<?php if (!empty($mensaje)): ?><?= ui('alerta', ['tipo' => 'exito', 'mensaje' => $mensaje, 'clases' => 'py-2 small']) ?><?php endif; ?>
<?php if (!empty($error)): ?><?= ui('alerta', ['tipo' => 'peligro', 'mensaje' => $error, 'clases' => 'py-2 small']) ?><?php endif; ?>

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
                <?= icono('filtrar', 'me-1') ?>Filtros<?= $filtrosActivos > 0 ? " ({$filtrosActivos} activo" . ($filtrosActivos === 1 ? '' : 's') . ')' : '' ?>
            </button>
            <?php if ($algunFiltroActivo): ?>
                <a href="<?= Url::to('/bienes') ?>" class="btn btn-sm btn-outline-secondary" id="quitarFiltrosBienes">
                    <?= icono('cancelar', 'me-1') ?>Quitar filtros
                </a>
            <?php endif; ?>
            <?php if ($urlDescarga !== null): ?>
                <a href="<?= htmlspecialchars($urlDescarga, ENT_QUOTES) ?>" class="btn btn-sm btn-outline-secondary" id="descargarCarteraFiltrada" data-sin-cargando
                   title="Descarga la cartera en Excel con los filtros y la búsqueda que tengas puestos">
                    <?= icono('exportar_excel', 'me-1') ?>Descargar en Excel
                </a>
            <?php endif; ?>
            <?php if ($urlActa !== null): ?>
                <a href="<?= htmlspecialchars($urlActa, ENT_QUOTES) ?>" class="btn btn-sm btn-outline-secondary" id="descargarActaACargo"
                   title="Acta con los bienes individuales y grupales de este responsable, para imprimir y firmar">
                    <?= icono('descargar', 'me-1') ?>Acta de bienes a cargo
                </a>
            <?php endif; ?>
        </div>
    </div>
    <?php $numFiltros = 2 + (int) !empty($espacios) + (int) !empty($responsables) + (int) !empty($tipos); ?>
    <div id="filtrosBienes" class="filtros-plegables filtros-rejilla filtros-n<?= $numFiltros ?><?= $filtrosActivos > 0 ? ' abierto' : '' ?>">
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

<?= ui('tabla', ['leyenda' => 'Bienes', 'columnas' => [
    ['texto' => 'Foto', 'oculta' => true], ['texto' => 'Descripción'], ['texto' => 'Código'], ['texto' => 'Categoría'],
    ['texto' => 'Responsable / ubicación'], ['texto' => 'Valor', 'alineacion' => 'derecha'], ['texto' => 'Estado'], ['texto' => 'QR'],
    ['texto' => 'Acciones', 'alineacion' => 'derecha', 'oculta' => true],
]]) ?>
        <?php if (empty($lotes) && empty($bienes)): ?>
            <?php View::render('partials/tabla_vacia', [
                'colspan' => 9,
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
                    <span class="miniatura-vacia" title="Código con varias unidades"><?= icono('bien') ?></span>
                </td>
                <td data-label="Descripción">
                    <?= htmlspecialchars($l['descripcion'], ENT_QUOTES) ?>
                    <?php // Lo que queda del código en la institución: baja al reintegrar o dar de baja unidades. ?>
                    <div class="small text-muted"><strong><?= (int) $l['en_cartera'] ?></strong> en cartera de <?= (int) $l['total'] ?> unidades</div>
                </td>
                <td class="text-muted mono" data-label="Código"><?= htmlspecialchars($l['lote'], ENT_QUOTES) ?></td>
                <td class="text-muted" data-label="Categoría"><?= htmlspecialchars($l['categoria_nombre'] ?? '—', ENT_QUOTES) ?></td>
                <td class="small text-muted" data-label="Responsable / ubicación">—</td>
                <td class="celda-numero" data-label="Valor" title="Valor de las unidades en cartera">$<?= number_format((float) $l['valor_en_cartera'], 0, ',', '.') ?></td>
                <td class="small" data-label="Estado">
                    <?= (int) $l['activos'] ?> activas
                    <?php if ((int) $l['reintegrados'] > 0): ?> · <?= (int) $l['reintegrados'] ?> reintegradas<?php endif; ?>
                    <?php if ((int) $l['en_reparacion'] > 0): ?> · <?= (int) $l['en_reparacion'] ?> en reparación<?php endif; ?>
                    <?php if ((int) $l['dados_de_baja'] > 0): ?> · <?= (int) $l['dados_de_baja'] ?> dadas de baja<?php endif; ?>
                </td>
                <td class="text-muted small" data-label="QR">—</td>
                <td class="celda-acciones">
                    <?= ui('boton', ['texto' => 'Ver detalles', 'icono' => 'ver', 'tamano' => 'sm', 'url' => Url::to('/bienes') . '?q=' . urlencode((string) $l['lote'])]) ?>
                    <?php if ($puedeAsignar && (int) $l['en_cartera'] > 0): ?>
                        <?= ui('boton', ['texto' => 'Reintegrar por cantidad', 'icono' => 'reintegro', 'tamano' => 'sm',
                            'url' => Url::to('/reintegros/unidades/' . rawurlencode((string) $l['lote'])) . $sufijoInstitucion,
                            'atributos' => ['title' => 'Reintegrar solo algunas unidades de este código']]) ?>
                    <?php endif; ?>
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
                        <span class="miniatura-vacia"><?= icono('bien') ?></span>
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
                <td class="celda-numero" data-label="Valor">$<?= number_format((float) $b['valor'], 0, ',', '.') ?></td>
                <td data-label="Estado"><?= ui('insignia_estado', ['tipo' => 'bien', 'estado' => (string) $b['estado']]) ?></td>
                <td data-label="QR">
                    <?= ui('insignia_estado', ['tipo' => 'qr', 'estado' => !empty($b['qr_confirmado_en']) ? 'confirmada' : (!empty($b['qr_impreso_en']) ? 'sin_confirmar' : 'sin_imprimir')]) ?>
                </td>
                <td class="celda-acciones">
                    <?php $puedeEditarBien = Auth::esSuperusuario() || Auth::tienePermiso('bienes.editar'); ?>
                    <?= ui('boton', ['texto' => $puedeEditarBien ? 'Editar' : 'Ver', 'icono' => $puedeEditarBien ? 'editar' : 'ver', 'tamano' => 'sm', 'url' => Url::to('/bienes/' . $b['id'] . '/editar')]) ?>
                </td>
            </tr>
        <?php endforeach; ?>
<?= ui('tabla_fin') ?>

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
