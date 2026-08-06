<?php

use App\Core\Url;
use App\Core\View;
use App\Helpers\AuditoriaFormato;

$etiquetasAccion = [
    'crear' => 'Crear',
    'editar' => 'Editar',
    'activar' => 'Activar',
    'desactivar' => 'Desactivar',
    'eliminar' => 'Eliminar',
    'restaurar' => 'Restaurar',
    'purgar' => 'Purgar',
    'archivar' => 'Archivar',
];
$coloresAccion = [
    'crear' => 'text-bg-primary',
    'editar' => 'text-bg-info',
    'activar' => 'text-bg-success',
    'desactivar' => 'text-bg-warning',
    'eliminar' => 'text-bg-danger',
    'restaurar' => 'text-bg-success',
    'purgar' => 'text-bg-dark',
    'archivar' => 'text-bg-secondary',
];
$etiquetasEntidad = [
    'usuario' => 'Usuario',
    'espacio' => 'Espacio',
    'categoria' => 'Categoría',
    'cargo' => 'Cargo',
    'bien' => 'Bien',
    'institucion' => 'Institución',
    'factura_administrativa' => 'Factura',
    'formato_reintegro' => 'Formato de reintegro',
    'formato_plaqueteo' => 'Formato de plaqueteo',
    'cartera_envio' => 'Cartera',
    'auditoria' => 'Auditoría (archivado)',
];
?>
<div class="mb-3">
    <h1 class="h4 mb-0"><i class="bi bi-journal-text me-1"></i>Auditoría</h1>
    <p class="text-muted small mb-0">
        Quién creó, editó, eliminó, restauró o purgó algo, en cualquier institución. Incluye lo que ya se
        purgó de la papelera para siempre.
    </p>
</div>

<form method="get" action="<?= Url::to('/auditoria') ?>" class="mb-3 d-flex flex-wrap gap-3 align-items-end">
    <div>
        <label class="form-label small mb-1">Institución</label>
        <select name="institucion_id" class="form-select form-select-sm selector-buscable" style="min-width: 200px;">
            <option value="">Todas</option>
            <?php foreach ($instituciones as $i): ?>
                <option value="<?= $i['id'] ?>" <?= (string) ($filtros['institucion_id'] ?? '') === (string) $i['id'] ? 'selected' : '' ?>>
                    <?= htmlspecialchars($i['nombre'], ENT_QUOTES) ?>
                </option>
            <?php endforeach; ?>
        </select>
    </div>
    <div>
        <label class="form-label small mb-1">Tipo</label>
        <select name="entidad" class="form-select form-select-sm">
            <option value="">Todos</option>
            <?php foreach ($etiquetasEntidad as $valor => $etiqueta): ?>
                <option value="<?= $valor ?>" <?= ($filtros['entidad'] ?? '') === $valor ? 'selected' : '' ?>><?= $etiqueta ?></option>
            <?php endforeach; ?>
        </select>
    </div>
    <div>
        <label class="form-label small mb-1">Acción</label>
        <select name="accion" class="form-select form-select-sm">
            <option value="">Todas</option>
            <?php foreach ($etiquetasAccion as $valor => $etiqueta): ?>
                <option value="<?= $valor ?>" <?= ($filtros['accion'] ?? '') === $valor ? 'selected' : '' ?>><?= $etiqueta ?></option>
            <?php endforeach; ?>
        </select>
    </div>
    <div>
        <label class="form-label small mb-1">Desde</label>
        <input type="date" name="desde" class="form-control form-control-sm" value="<?= htmlspecialchars($filtros['desde'] ?? '', ENT_QUOTES) ?>">
    </div>
    <div>
        <label class="form-label small mb-1">Hasta</label>
        <input type="date" name="hasta" class="form-control form-control-sm" value="<?= htmlspecialchars($filtros['hasta'] ?? '', ENT_QUOTES) ?>">
    </div>
    <div class="d-flex gap-2">
        <button type="submit" class="btn btn-sm btn-primary">Filtrar</button>
        <?php if (!empty($filtros)): ?>
            <a href="<?= Url::to('/auditoria') ?>" class="btn btn-sm btn-outline-secondary">Limpiar</a>
        <?php endif; ?>
    </div>
</form>

<?php View::render('partials/paginacion', [
    'pagina' => $pagina, 'porPagina' => $porPagina, 'total' => $total, 'totalPaginas' => $totalPaginas,
    'urlBase' => $urlBase,
]); ?>

<?php if (empty($registros)): ?>
    <p class="text-muted">No hay nada registrado con estos filtros.</p>
<?php else: ?>
    <div class="table-responsive">
        <table class="table table-sm bg-white align-middle tabla-cards">
            <thead>
            <tr>
                <th>Fecha</th>
                <th>Quién</th>
                <th>Institución</th>
                <th>Acción</th>
                <th>Tipo</th>
                <th></th>
            </tr>
            </thead>
            <tbody>
            <?php foreach ($registros as $r): ?>
                <?php
                $antes = $r['datos_antes'] !== null ? json_decode($r['datos_antes'], true) : null;
                $despues = $r['datos_despues'] !== null ? json_decode($r['datos_despues'], true) : null;
                $resumen = AuditoriaFormato::resumen($antes, $despues, $mapas);
                ?>
                <tr>
                    <td class="text-muted mono" data-label="Fecha"><?= htmlspecialchars($r['created_at'], ENT_QUOTES) ?></td>
                    <td data-label="Quién">
                        <?= $r['usuario_nombres'] !== null
                            ? htmlspecialchars(trim($r['usuario_nombres'] . ' ' . $r['usuario_apellidos']), ENT_QUOTES)
                            : '<span class="text-muted">—</span>' ?>
                    </td>
                    <td class="text-muted" data-label="Institución"><?= htmlspecialchars($r['institucion_nombre'] ?? '—', ENT_QUOTES) ?></td>
                    <td data-label="Acción">
                        <span class="badge <?= $coloresAccion[$r['accion']] ?? 'text-bg-secondary' ?>">
                            <?= htmlspecialchars($etiquetasAccion[$r['accion']] ?? $r['accion'], ENT_QUOTES) ?>
                        </span>
                    </td>
                    <td data-label="Tipo"><?= htmlspecialchars($etiquetasEntidad[$r['entidad']] ?? $r['entidad'], ENT_QUOTES) ?> #<?= (int) $r['entidad_id'] ?></td>
                    <td class="text-end">
                        <?php if (!empty($resumen)): ?>
                            <details>
                                <summary class="small text-muted" style="cursor:pointer;">Ver detalle</summary>
                                <table class="table table-sm mb-0 mt-1" style="min-width: 260px;">
                                    <?php foreach ($resumen as $fila): ?>
                                        <tr>
                                            <td class="small text-muted text-nowrap"><?= htmlspecialchars($fila['etiqueta'], ENT_QUOTES) ?></td>
                                            <?php if ($antes !== null && $despues !== null): ?>
                                                <td class="small"><?= htmlspecialchars((string) $fila['antes'], ENT_QUOTES) ?> → <?= htmlspecialchars((string) $fila['despues'], ENT_QUOTES) ?></td>
                                            <?php else: ?>
                                                <td class="small"><?= htmlspecialchars((string) ($fila['antes'] ?? $fila['despues']), ENT_QUOTES) ?></td>
                                            <?php endif; ?>
                                        </tr>
                                    <?php endforeach; ?>
                                </table>
                            </details>
                        <?php endif; ?>
                    </td>
                </tr>
            <?php endforeach; ?>
            </tbody>
        </table>
    </div>
<?php endif; ?>
