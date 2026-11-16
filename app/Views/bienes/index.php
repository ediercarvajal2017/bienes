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

$algunFiltroActivo = $busqueda !== '' || $categoriaId !== null || $estado !== null || $espacioId !== null || $responsableId !== null || $tipo !== null;

// Filtros aplicados, como etiquetas: cada una lleva a la misma dirección sin ese filtro
// (y de vuelta a la página 1). El nombre sale de las mismas listas de los selectores.
$consultaActual = $parametrosPaginacion + (isset($_GET['porPagina']) ? ['porPagina' => $porPagina] : []);
$sinFiltro = static function (string $clave) use ($consultaActual): string {
    unset($consultaActual[$clave]);

    return Url::to('/bienes') . ($consultaActual !== [] ? '?' . http_build_query($consultaActual) : '');
};
$nombreEn = static function (array $lista, ?int $id, callable $nombre): ?string {
    foreach ($lista as $fila) {
        if ((int) $fila['id'] === $id) {
            return (string) $nombre($fila);
        }
    }

    return null;
};
$nombreResponsable = $nombreEn($responsables, $responsableId, static fn (array $r): string => trim($r['nombres'] . ' ' . $r['apellidos']));
$filtrosAplicados = [];
if ($categoriaId !== null) {
    $filtrosAplicados[] = ['etiqueta' => 'Categoría', 'clave' => 'categoria',
        'valor' => $nombreEn($categorias, $categoriaId, static fn (array $c): string => $c['nombre']) ?? "#{$categoriaId}"];
}
if ($estado !== null) {
    $filtrosAplicados[] = ['etiqueta' => 'Estado', 'clave' => 'estado', 'valor' => $etiquetasEstado[$estado] ?? $estado];
}
if ($espacioId !== null) {
    $filtrosAplicados[] = ['etiqueta' => 'Espacio', 'clave' => 'espacio',
        'valor' => $nombreEn($espacios, $espacioId, static fn (array $esp): string => $esp['codigo'] . ' - ' . $esp['nombre']) ?? "#{$espacioId}"];
}
if ($responsableId !== null) {
    $filtrosAplicados[] = ['etiqueta' => 'Responsable', 'clave' => 'responsable', 'valor' => $nombreResponsable ?? "#{$responsableId}"];
}
if ($tipo !== null) {
    $filtrosAplicados[] = ['etiqueta' => 'Responsabilidad', 'clave' => 'tipo', 'valor' => $tipos[$tipo] ?? $tipo];
}
$filtrosAplicados = array_map(static fn (array $f): array => [
    'etiqueta' => $f['etiqueta'], 'valor' => $f['valor'], 'urlQuitar' => $sinFiltro($f['clave']),
], $filtrosAplicados);
$filtrosActivos = count($filtrosAplicados);

// La descarga en Excel dice qué toma de la lista, si toma algo.
$alcanceDescarga = array_filter([
    $busqueda !== '' ? 'la búsqueda' : null,
    $filtrosActivos === 1 ? 'el filtro' : ($filtrosActivos > 1 ? "los {$filtrosActivos} filtros" : null),
]);
?>
<?php ob_start(); ?>
<div class="dropdown menu-acciones">
    <?= ui('boton', ['texto' => 'Acciones', 'tamano' => 'sm', 'clases' => 'dropdown-toggle',
        'atributos' => ['id' => 'botonAccionesMasivas', 'aria-haspopup' => 'true', 'aria-expanded' => 'false']]) ?>
    <ul class="dropdown-menu" aria-labelledby="botonAccionesMasivas">
        <?php if ($puedeCrear): ?>
            <li><a class="dropdown-item" href="<?= Url::to('/bienes/alta-masiva') ?>">Alta masiva idéntica</a></li>
        <?php endif; ?>
        <?php if ($puedeCargaMasiva): ?>
            <li><a class="dropdown-item" href="<?= Url::to('/cargas-masivas') ?>">Carga masiva de bienes</a></li>
        <?php endif; ?>
        <?php if ($puedeAsignar): ?>
            <li><a class="dropdown-item" href="<?= Url::to('/asignaciones') ?>">Asignar bienes</a></li>
            <li><a class="dropdown-item" href="<?= Url::to('/reintegros') ?>">Reintegrar bienes</a></li>
            <li><a class="dropdown-item" href="<?= Url::to('/reintegros/unidades') ?>">Reintegrar por cantidad</a></li>
        <?php endif; ?>
        <?php if ($puedeQr): ?>
            <li><a class="dropdown-item" href="<?= Url::to('/bienes/qr-masivo') ?>">Generar QR masivo</a></li>
        <?php endif; ?>
        <?php // Esta vista ya exige bienes.ver (ver rutas): "Buscar por foto" se muestra a todos. ?>
        <li><a class="dropdown-item" href="<?= Url::to('/bienes/buscar-por-foto') ?>">Buscar por foto</a></li>
        <?php if ($urlDescarga !== null): ?>
            <li>
                <a class="dropdown-item" href="<?= htmlspecialchars($urlDescarga, ENT_QUOTES) ?>" id="descargarCarteraFiltrada" data-sin-cargando>
                    Descargar cartera en Excel
                    <?php if ($alcanceDescarga !== []): ?>
                        <span class="dropdown-item-detalle">Con <?= implode(' y ', $alcanceDescarga) ?> de la lista</span>
                    <?php endif; ?>
                </a>
            </li>
        <?php endif; ?>
        <?php if ($urlActa !== null): ?>
            <li>
                <a class="dropdown-item" href="<?= htmlspecialchars($urlActa, ENT_QUOTES) ?>" id="descargarActaACargo">
                    Acta de bienes a cargo
                    <span class="dropdown-item-detalle"><?= htmlspecialchars($nombreResponsable ?? 'Del responsable filtrado', ENT_QUOTES) ?>, para imprimir y firmar</span>
                </a>
            </li>
        <?php endif; ?>
    </ul>
</div>
<?php if ($puedeCrear): ?>
    <?= ui('boton', ['texto' => 'Registrar bien', 'tipo' => 'primario', 'icono' => 'crear', 'tamano' => 'sm', 'url' => Url::to('/bienes/crear')]) ?>
<?php endif; ?>
<?= ui('encabezado_pagina', ['titulo' => 'Bienes', 'icono' => 'bien', 'acciones' => (string) ob_get_clean()]) ?>

<?php if (!empty($mensaje)): ?><?= ui('alerta', ['tipo' => 'exito', 'mensaje' => $mensaje, 'clases' => 'py-2 small']) ?><?php endif; ?>
<?php if (!empty($error)): ?><?= ui('alerta', ['tipo' => 'peligro', 'mensaje' => $error, 'clases' => 'py-2 small']) ?><?php endif; ?>

<?php if (!empty($soloPropios)): ?>
    <p class="text-muted small">Mostrando solo los bienes de los espacios donde eres responsable.</p>
<?php endif; ?>

<!-- Una fila: Buscar y el botón Filtros. Los filtros quedan plegados; lo aplicado se ve
     debajo como etiquetas que se quitan con un clic. -->
<div class="zona-filtros mb-3">
<div class="barra-busqueda">
    <label for="buscador" class="visually-hidden">Buscar</label>
    <input type="search" id="buscador" data-buscar="q" class="form-control form-control-sm"
           placeholder="Buscar por código, descripción, responsable, ubicación o valor…"
           value="<?= htmlspecialchars($busqueda, ENT_QUOTES) ?>">
    <?= ui('boton', ['texto' => $filtrosActivos > 0 ? "Filtros ({$filtrosActivos})" : 'Filtros', 'icono' => 'filtrar', 'tamano' => 'sm',
        'atributos' => ['id' => 'botonFiltrosBienes', 'data-alternar' => 'filtrosBienes', 'data-alternar-recordar' => 'bienes.filtros',
            'aria-controls' => 'filtrosBienes', 'aria-expanded' => 'false']]) ?>
</div>

<?php $numFiltros = 2 + (int) !empty($espacios) + (int) !empty($responsables) + (int) !empty($tipos); ?>
<div id="filtrosBienes" class="panel-filtros filtros-plegables plegable-siempre">
<div class="filtros-rejilla filtros-n<?= $numFiltros ?>">
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
                <?php // Cuántos hay en cada estado con los demás filtros (no aplica al docente). ?>
                <option value="<?= $valorEstado ?>" <?= $estado === $valorEstado ? 'selected' : '' ?>><?= $etiqueta ?><?= $conteoEstados !== [] ? ' (' . number_format($conteoEstados[$valorEstado] ?? 0, 0, ',', '.') . ')' : '' ?></option>
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

<?= ui('filtros_activos', ['filtros' => $filtrosAplicados,
    'urlQuitarTodos' => $algunFiltroActivo ? Url::to('/bienes') : null, 'idQuitarTodos' => 'quitarFiltrosBienes']) ?>
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
        // Se abre hacia la derecha; si así se sale de la pantalla, se alinea con el borde
        // derecho del botón, y si tampoco cabe (celular), se corre lo justo para no salirse.
        menu.style.left = '';
        if (abierto) {
            const margen = 8;
            const caja = menu.getBoundingClientRect();
            if (caja.right > document.documentElement.clientWidth - margen) {
                const contenedor = menu.parentElement.getBoundingClientRect();
                let izquierda = contenedor.width - caja.width;
                if (contenedor.left + izquierda < margen) { izquierda = margen - contenedor.left; }
                menu.style.left = izquierda + 'px';
            }
        }
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
