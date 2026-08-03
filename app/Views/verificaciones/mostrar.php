<?php

use App\Core\Csrf;
use App\Core\Url;
use App\Core\View;
use App\Models\Verificacion;

/**
 * Las 4 secciones (hallazgos, discrepancias, pendientes, verificados) paginan y buscan
 * de forma independiente, pero ahora se muestran de a una (pestañas) en vez de todas
 * apiladas — cada una necesita su URL base con los filtros de las OTRAS ya incluidos,
 * para que cambiar de página en una no resetee lo que el usuario tenía filtrado en
 * las demás.
 */
$parametrosComunes = array_filter([
    'q' => $busquedaPendientes !== '' ? $busquedaPendientes : null,
    'pagina' => $pagina,
    'porPagina' => $porPagina,
    'qOk' => $busquedaOk !== '' ? $busquedaOk : null,
    'paginaOk' => $paginaOk,
    'porPaginaOk' => $porPaginaOk,
    'qDiscrepancia' => $busquedaDiscrepancia !== '' ? $busquedaDiscrepancia : null,
    'paginaDiscrepancia' => $paginaDiscrepancia,
    'porPaginaDiscrepancia' => $porPaginaDiscrepancia,
    'estadoDiscrepancia' => $estadoDiscrepancia !== 'pendiente' ? $estadoDiscrepancia : null,
], static fn ($valor) => $valor !== null);

$urlBaseSeccion = static function (array $excluir) use ($jornada, $parametrosComunes): string {
    $filtrados = array_diff_key($parametrosComunes, array_flip($excluir));

    return Url::to('/verificaciones/' . $jornada['id']) . (!empty($filtrados) ? '?' . http_build_query($filtrados) : '');
};

$urlBasePendientes = $urlBaseSeccion(['pagina', 'porPagina']);
$urlBaseOk = $urlBaseSeccion(['paginaOk', 'porPaginaOk']);
$urlBaseDiscrepancia = $urlBaseSeccion(['paginaDiscrepancia', 'porPaginaDiscrepancia']);

// Pestaña visible por defecto (sin JS ni hash en la URL): la primera en orden de
// prioridad que exista. Hallazgos solo aparece si hay alguno; las otras 3 siempre.
$tabPorDefecto = !empty($hallazgos) ? 'hallazgos' : 'discrepancia';
?>
<div class="d-flex flex-wrap justify-content-between align-items-center gap-2 mb-1">
    <h1 class="h4 mb-0"><?= htmlspecialchars($jornada['nombre'], ENT_QUOTES) ?></h1>
    <div class="d-flex gap-2">
        <a href="<?= Url::to('/verificaciones/' . $jornada['id'] . '/exportar.xlsx') ?>" class="btn btn-sm btn-outline-primary">
            <i class="bi bi-file-earmark-excel me-1"></i>Exportar a Excel
        </a>
        <a href="<?= Url::to('/verificaciones') ?>" class="btn btn-sm btn-outline-secondary">Volver</a>
    </div>
</div>
<p class="text-muted small mb-3">
    <?= (int) $universo ?> bienes en total · Iniciada el <?= htmlspecialchars($jornada['fecha_inicio'], ENT_QUOTES) ?>
    <?php if ($jornada['estado'] === 'cerrada'): ?>
        · Cerrada el <?= htmlspecialchars(substr($jornada['fecha_cierre'], 0, 16), ENT_QUOTES) ?>
    <?php endif; ?>
</p>

<?php if (!empty($mensaje)): ?><div class="alert alert-success py-2 small"><?= htmlspecialchars($mensaje, ENT_QUOTES) ?></div><?php endif; ?>
<?php if (!empty($error)): ?><div class="alert alert-danger py-2 small"><?= htmlspecialchars($error, ENT_QUOTES) ?></div><?php endif; ?>

<?php if ($jornada['estado'] === 'en_progreso'): ?>
    <form method="post" action="<?= Url::to('/verificaciones/' . $jornada['id'] . '/cerrar') ?>" class="card mb-4" style="max-width: 480px;"
          onsubmit="return confirm('¿Cerrar la jornada de verificación? Quedan <?= (int) $totalPendientes ?> bien(es) pendiente(s) por verificar.');">
        <div class="card-body">
            <?= Csrf::field() ?>
            <label class="form-label small">Observaciones de cierre (opcional)</label>
            <textarea name="observaciones" class="form-control form-control-sm mb-2" rows="2"></textarea>
            <button type="submit" class="btn btn-outline-danger btn-sm">
                <i class="bi bi-lock me-1"></i>Cerrar jornada
            </button>
        </div>
    </form>
<?php elseif (!empty($jornada['observaciones_cierre'])): ?>
    <div class="alert alert-secondary py-2 small" style="max-width:560px;">
        <strong>Observaciones de cierre:</strong> <?= htmlspecialchars($jornada['observaciones_cierre'], ENT_QUOTES) ?>
    </div>
<?php endif; ?>

<ul class="nav nav-tabs mb-3" id="tabsVerificacion">
    <?php if (!empty($hallazgos)): ?>
        <li class="nav-item">
            <a class="nav-link<?= $tabPorDefecto === 'hallazgos' ? ' active' : '' ?>" href="#seccion-hallazgos" data-tab-link="hallazgos">
                <i class="bi bi-flag me-1"></i>Hallazgos <span class="badge rounded-pill text-bg-primary ms-1"><?= count($hallazgos) ?></span>
            </a>
        </li>
    <?php endif; ?>
    <li class="nav-item">
        <a class="nav-link<?= $tabPorDefecto === 'discrepancia' ? ' active' : '' ?>" href="#seccion-discrepancia" data-tab-link="discrepancia">
            <i class="bi bi-exclamation-triangle me-1"></i>Discrepancias <span class="badge rounded-pill text-bg-warning ms-1"><?= (int) $verificadosDiscrepancia ?></span>
        </a>
    </li>
    <li class="nav-item">
        <a class="nav-link" href="#seccion-pendientes" data-tab-link="pendientes">
            <i class="bi bi-hourglass-split me-1"></i>Pendientes <span class="badge rounded-pill text-bg-secondary ms-1"><?= (int) $totalPendientes ?></span>
        </a>
    </li>
    <li class="nav-item">
        <a class="nav-link" href="#seccion-ok" data-tab-link="ok">
            <i class="bi bi-check2-circle me-1"></i>Verificados <span class="badge rounded-pill text-bg-success ms-1"><?= (int) $verificadosOk ?></span>
        </a>
    </li>
</ul>

<?php if (!empty($hallazgos)): ?>
<div data-tab-panel="hallazgos" id="seccion-hallazgos" class="<?= $tabPorDefecto === 'hallazgos' ? '' : 'd-none' ?>">
    <p class="text-muted small mb-2">Bienes físicos reportados por el personal durante la verificación, que no están en el sistema.</p>

    <div class="table-responsive mb-2">
        <table class="table table-sm bg-white tabla-cards">
            <thead>
            <tr><th>Descripción</th><th>Espacio</th><th>Foto</th><th>Reportado por</th><th>Fecha</th><th>Acciones</th></tr>
            </thead>
            <tbody>
            <?php foreach ($hallazgos as $h): ?>
                <tr>
                    <td data-label="Descripción"><?= htmlspecialchars($h['descripcion'], ENT_QUOTES) ?></td>
                    <td class="text-muted small" data-label="Espacio"><?= htmlspecialchars($h['espacio_nombre'], ENT_QUOTES) ?></td>
                    <td data-label="Foto">
                        <?php if (!empty($h['foto_path'])): ?>
                            <img src="<?= Url::to('/archivos/' . $h['foto_path']) ?>"
                                 data-lightbox-src="<?= Url::to('/archivos/' . $h['foto_path']) ?>"
                                 style="width:36px;height:36px;object-fit:cover;border-radius:4px;cursor:zoom-in;">
                        <?php else: ?>
                            <span class="text-muted small">—</span>
                        <?php endif; ?>
                    </td>
                    <td class="small" data-label="Reportado por"><?= htmlspecialchars($h['nombres'] . ' ' . $h['apellidos'], ENT_QUOTES) ?></td>
                    <td class="mono small text-muted" data-label="Fecha"><?= htmlspecialchars(substr($h['created_at'], 0, 16), ENT_QUOTES) ?></td>
                    <td data-label="Acciones">
                        <div class="d-flex flex-column gap-1">
                            <a href="<?= Url::to('/bienes/crear') ?>?hallazgo_id=<?= (int) $h['id'] ?>" class="btn btn-sm btn-outline-primary">Registrar como bien</a>
                            <form method="post" action="<?= Url::to('/hallazgos/' . $h['id'] . '/descartar') ?>"
                                  onsubmit="return confirm('¿Descartar este hallazgo? No se creará ningún bien.');">
                                <?= Csrf::field() ?>
                                <button type="submit" class="btn btn-sm btn-outline-secondary w-100">Descartar</button>
                            </form>
                        </div>
                    </td>
                </tr>
            <?php endforeach; ?>
            </tbody>
        </table>
    </div>
</div>
<?php endif; ?>

<div data-tab-panel="discrepancia" id="seccion-discrepancia" class="<?= $tabPorDefecto === 'discrepancia' ? '' : 'd-none' ?>">
    <?php
    $motivosConDatos = array_filter($discrepanciasPorMotivo, static fn (int $cantidad) => $cantidad > 0);
    ?>
    <?php if (!empty($motivosConDatos)): ?>
        <p class="text-muted small mb-2">
            Sin atender por motivo:
            <?php $partes = []; ?>
            <?php foreach ($motivosConDatos as $motivo => $cantidad): ?>
                <?php $partes[] = $cantidad . ' ' . mb_strtolower(Verificacion::etiquetaMotivo($motivo)); ?>
            <?php endforeach; ?>
            <?= htmlspecialchars(implode(', ', $partes), ENT_QUOTES) ?>.
        </p>
    <?php endif; ?>

    <?php if ($verificadosDiscrepancia > 0): ?>
        <div class="mb-2 d-flex flex-wrap gap-3 align-items-end">
            <div style="max-width: 420px; flex: 1 1 260px;">
                <label class="form-label small mb-1">Buscar</label>
                <input type="search" id="buscadorDiscrepancia" class="form-control form-control-sm"
                       placeholder="Buscar por código, descripción o ubicación..."
                       value="<?= htmlspecialchars($busquedaDiscrepancia, ENT_QUOTES) ?>">
            </div>
            <div>
                <label class="form-label small mb-1">Estado</label>
                <select id="selectorEstadoDiscrepancia" class="form-select form-select-sm">
                    <option value="pendiente" <?= $estadoDiscrepancia === 'pendiente' ? 'selected' : '' ?>>Sin atender</option>
                    <option value="revisada" <?= $estadoDiscrepancia === 'revisada' ? 'selected' : '' ?>>Revisadas</option>
                    <option value="todas" <?= $estadoDiscrepancia === 'todas' ? 'selected' : '' ?>>Todas</option>
                </select>
            </div>
        </div>
    <?php endif; ?>

    <?php if (empty($discrepancias)): ?>
        <p class="text-muted small">
            <?php if ($verificadosDiscrepancia === 0): ?>
                Ninguna hasta ahora.
            <?php elseif ($estadoDiscrepancia === 'pendiente'): ?>
                No hay discrepancias sin atender — todas están revisadas, o cámbialo arriba para verlas todas.
            <?php else: ?>
                Ningún resultado para ese filtro.
            <?php endif; ?>
        </p>
    <?php else: ?>
        <div class="table-responsive mb-2">
            <table class="table table-sm bg-white tabla-cards">
                <thead>
                <tr><th>Código</th><th>Descripción</th><th>Ubicación</th><th>Responsable(s)</th><th>Motivo</th><th>Observación</th><th>Reportado por</th><th>Fecha</th><th>Estado</th><th>Acciones</th></tr>
                </thead>
                <tbody>
                <?php foreach ($discrepancias as $d): ?>
                    <tr>
                        <td class="mono small" data-label="Código"><?= htmlspecialchars($d['codigo_identificacion'], ENT_QUOTES) ?></td>
                        <td data-label="Descripción"><?= htmlspecialchars($d['descripcion'], ENT_QUOTES) ?></td>
                        <td class="text-muted small" data-label="Ubicación"><?= !empty($d['espacio_nombre']) ? htmlspecialchars($d['espacio_nombre'], ENT_QUOTES) : 'Sin asignar' ?></td>
                        <td class="text-muted small" data-label="Responsable(s)"><?= !empty($d['responsables_nombres']) ? htmlspecialchars($d['responsables_nombres'], ENT_QUOTES) : '—' ?></td>
                        <td class="small" data-label="Motivo"><?= htmlspecialchars(Verificacion::etiquetaMotivo($d['motivo'] ?? null), ENT_QUOTES) ?></td>
                        <td class="small" data-label="Observación"><?= htmlspecialchars($d['observaciones'] ?? '', ENT_QUOTES) ?></td>
                        <td class="text-muted small" data-label="Reportado por"><?= htmlspecialchars($d['nombres'] . ' ' . $d['apellidos'], ENT_QUOTES) ?></td>
                        <td class="mono small text-muted" data-label="Fecha"><?= htmlspecialchars(substr($d['updated_at'], 0, 16), ENT_QUOTES) ?></td>
                        <td data-label="Estado">
                            <?php if (!empty($d['revisada'])): ?>
                                <span class="badge text-bg-secondary d-block mb-1">Revisada</span>
                                <?php if (!empty($d['revisor_nombres'])): ?>
                                    <div class="text-muted" style="font-size: 0.75rem;">
                                        por <?= htmlspecialchars($d['revisor_nombres'] . ' ' . $d['revisor_apellidos'], ENT_QUOTES) ?>
                                        <?php if (!empty($d['revisada_en'])): ?>
                                            · <?= htmlspecialchars(substr($d['revisada_en'], 0, 16), ENT_QUOTES) ?>
                                        <?php endif; ?>
                                    </div>
                                <?php endif; ?>
                            <?php else: ?>
                                <span class="badge text-bg-warning">Pendiente</span>
                            <?php endif; ?>
                        </td>
                        <td data-label="Acciones">
                            <?php $panelUbicacion = !empty($d['espacio_nombre']) ? 'panelTrasladar' : 'panelAsignar'; ?>
                            <div class="d-flex flex-column gap-1">
                                <a href="<?= Url::to('/bienes/' . $d['bien_id'] . '/editar') ?>?verificacion_id=<?= (int) $d['id'] ?>#<?= $panelUbicacion ?>"
                                   class="btn btn-sm btn-outline-primary">Corregir ubicación</a>
                                <a href="<?= Url::to('/qr/' . $d['qr_token'] . '/baja') ?>?verificacion_id=<?= (int) $d['id'] ?>"
                                   class="btn btn-sm btn-outline-danger">Dar de baja</a>
                                <?php if (empty($d['revisada'])): ?>
                                    <form method="post" action="<?= Url::to('/verificaciones/' . $jornada['id'] . '/discrepancias/' . $d['id'] . '/revisada') ?>">
                                        <?= Csrf::field() ?>
                                        <button type="submit" class="btn btn-sm btn-outline-secondary w-100">Marcar revisada</button>
                                    </form>
                                <?php endif; ?>
                            </div>
                        </td>
                    </tr>
                <?php endforeach; ?>
                </tbody>
            </table>
        </div>
    <?php endif; ?>

    <?php if ($verificadosDiscrepancia > 0): ?>
        <?php View::render('partials/paginacion', [
            'pagina' => $paginaDiscrepancia, 'porPagina' => $porPaginaDiscrepancia, 'total' => $totalDiscrepanciaFiltrado, 'totalPaginas' => $totalPaginasDiscrepancia,
            'opcionesPorPagina' => $opcionesPorPagina,
            'urlBase' => $urlBaseDiscrepancia,
            'paramPagina' => 'paginaDiscrepancia', 'paramPorPagina' => 'porPaginaDiscrepancia',
        ]); ?>
    <?php endif; ?>
</div>

<div data-tab-panel="pendientes" id="seccion-pendientes" class="d-none">
    <div class="mb-2" style="max-width: 420px;">
        <input type="search" id="buscadorPendientes" class="form-control form-control-sm"
               placeholder="Buscar por código, descripción o ubicación..."
               value="<?= htmlspecialchars($busquedaPendientes, ENT_QUOTES) ?>">
    </div>

    <div class="table-responsive">
        <table class="table table-sm bg-white tabla-cards">
            <thead>
            <tr><th>Código</th><th>Descripción</th><th>Ubicación</th></tr>
            </thead>
            <tbody>
            <?php foreach ($pendientes as $p): ?>
                <tr>
                    <td class="mono small" data-label="Código"><?= htmlspecialchars($p['codigo_identificacion'], ENT_QUOTES) ?></td>
                    <td data-label="Descripción"><?= htmlspecialchars($p['descripcion'], ENT_QUOTES) ?></td>
                    <td class="text-muted small" data-label="Ubicación"><?= !empty($p['espacio_nombre']) ? htmlspecialchars($p['espacio_nombre'], ENT_QUOTES) : 'Sin asignar' ?></td>
                </tr>
            <?php endforeach; ?>
            </tbody>
        </table>
    </div>

    <?php View::render('partials/paginacion', [
        'pagina' => $pagina, 'porPagina' => $porPagina, 'total' => $totalPendientes, 'totalPaginas' => $totalPaginasPendientes,
        'opcionesPorPagina' => $opcionesPorPagina,
        'urlBase' => $urlBasePendientes,
    ]); ?>
</div>

<div data-tab-panel="ok" id="seccion-ok" class="d-none">
    <?php if ($verificadosOk > 0): ?>
        <div class="mb-2" style="max-width: 420px;">
            <input type="search" id="buscadorOk" class="form-control form-control-sm"
                   placeholder="Buscar por código, descripción o ubicación..."
                   value="<?= htmlspecialchars($busquedaOk, ENT_QUOTES) ?>">
        </div>
    <?php endif; ?>

    <?php if (empty($verificadosOkDetalle)): ?>
        <p class="text-muted small">
            <?= $busquedaOk !== '' ? 'Ningún resultado para esa búsqueda.' : 'Ninguno hasta ahora.' ?>
        </p>
    <?php else: ?>
        <div class="table-responsive mb-2">
            <table class="table table-sm bg-white tabla-cards">
                <thead>
                <tr><th>Código</th><th>Descripción</th><th>Ubicación</th><th>Responsable(s)</th><th>Verificado por</th><th>Fecha</th></tr>
                </thead>
                <tbody>
                <?php foreach ($verificadosOkDetalle as $v): ?>
                    <tr>
                        <td class="mono small" data-label="Código"><?= htmlspecialchars($v['codigo_identificacion'], ENT_QUOTES) ?></td>
                        <td data-label="Descripción"><?= htmlspecialchars($v['descripcion'], ENT_QUOTES) ?></td>
                        <td class="text-muted small" data-label="Ubicación"><?= !empty($v['espacio_nombre']) ? htmlspecialchars($v['espacio_nombre'], ENT_QUOTES) : 'Sin asignar' ?></td>
                        <td class="text-muted small" data-label="Responsable(s)"><?= !empty($v['responsables_nombres']) ? htmlspecialchars($v['responsables_nombres'], ENT_QUOTES) : '—' ?></td>
                        <td class="small" data-label="Verificado por"><?= htmlspecialchars($v['nombres'] . ' ' . $v['apellidos'], ENT_QUOTES) ?></td>
                        <td class="mono small text-muted" data-label="Fecha"><?= htmlspecialchars(substr($v['updated_at'], 0, 16), ENT_QUOTES) ?></td>
                    </tr>
                <?php endforeach; ?>
                </tbody>
            </table>
        </div>
    <?php endif; ?>

    <?php if ($verificadosOk > 0): ?>
        <?php View::render('partials/paginacion', [
            'pagina' => $paginaOk, 'porPagina' => $porPaginaOk, 'total' => $totalOkFiltrado, 'totalPaginas' => $totalPaginasOk,
            'opcionesPorPagina' => $opcionesPorPagina,
            'urlBase' => $urlBaseOk,
            'paramPagina' => 'paginaOk', 'paramPorPagina' => 'porPaginaOk',
        ]); ?>
    <?php endif; ?>
</div>

<script>
(function () {
    // Buscador con reload debounceado, reutilizable por las tablas de la pantalla —
    // cada una con su propio parametro de busqueda/pagina para no interferir entre si.
    function activarBuscador(idInput, paramBusqueda, paramPagina, ancla) {
        const input = document.getElementById(idInput);
        if (!input) { return; }
        let temporizador = null;

        input.addEventListener('input', function () {
            clearTimeout(temporizador);
            temporizador = setTimeout(function () {
                const url = new URL(window.location.href);
                const valor = input.value.trim();
                if (valor !== '') {
                    url.searchParams.set(paramBusqueda, valor);
                } else {
                    url.searchParams.delete(paramBusqueda);
                }
                url.searchParams.set(paramPagina, '1');
                url.hash = ancla;
                window.location = url.toString();
            }, 450);
        });
    }

    activarBuscador('buscadorOk', 'qOk', 'paginaOk', 'seccion-ok');
    activarBuscador('buscadorDiscrepancia', 'qDiscrepancia', 'paginaDiscrepancia', 'seccion-discrepancia');
    activarBuscador('buscadorPendientes', 'q', 'pagina', 'seccion-pendientes');

    const selectorEstado = document.getElementById('selectorEstadoDiscrepancia');
    if (selectorEstado) {
        selectorEstado.addEventListener('change', function () {
            const url = new URL(window.location.href);
            url.searchParams.set('estadoDiscrepancia', selectorEstado.value);
            url.searchParams.set('paginaDiscrepancia', '1');
            url.hash = 'seccion-discrepancia';
            window.location = url.toString();
        });
    }

    // Pestañas: sin recarga, solo muestra/oculta paneles ya renderizados y recuerda
    // la elección en el hash de la URL (así un reintento tras buscar/paginar vuelve a
    // la pestaña correcta, y el enlace se puede compartir apuntando a una sección).
    const enlaces = Array.prototype.slice.call(document.querySelectorAll('[data-tab-link]'));
    const paneles = Array.prototype.slice.call(document.querySelectorAll('[data-tab-panel]'));

    function activarTab(nombre) {
        paneles.forEach(function (panel) {
            panel.classList.toggle('d-none', panel.dataset.tabPanel !== nombre);
        });
        enlaces.forEach(function (enlace) {
            enlace.classList.toggle('active', enlace.dataset.tabLink === nombre);
        });
    }

    enlaces.forEach(function (enlace) {
        enlace.addEventListener('click', function (e) {
            e.preventDefault();
            const nombre = enlace.dataset.tabLink;
            activarTab(nombre);
            history.replaceState(null, '', '#seccion-' + nombre);
        });
    });

    const disponibles = enlaces.map(function (enlace) { return enlace.dataset.tabLink; });
    const hashInicial = window.location.hash.replace('#seccion-', '');
    if (disponibles.indexOf(hashInicial) !== -1) {
        activarTab(hashInicial);
    }
})();
</script>
