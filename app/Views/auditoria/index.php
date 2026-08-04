<?php

use App\Core\View;

$etiquetasAccion = [
    'crear' => 'Crear',
    'editar' => 'Editar',
    'activar' => 'Activar',
    'desactivar' => 'Desactivar',
    'eliminar' => 'Eliminar',
    'restaurar' => 'Restaurar',
    'purgar' => 'Purgar',
];
$coloresAccion = [
    'crear' => 'text-bg-primary',
    'editar' => 'text-bg-info',
    'activar' => 'text-bg-success',
    'desactivar' => 'text-bg-warning',
    'eliminar' => 'text-bg-danger',
    'restaurar' => 'text-bg-success',
    'purgar' => 'text-bg-dark',
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
];
?>
<div class="mb-3">
    <h1 class="h4 mb-0"><i class="bi bi-journal-text me-1"></i>Auditoría</h1>
    <p class="text-muted small mb-0">
        Cada vez que algo se envía a la papelera o se restaura, en cualquier institución, queda registrado aquí
        -incluye lo que ya se purgó de la papelera para siempre.
    </p>
</div>

<?php View::render('partials/paginacion', [
    'pagina' => $pagina, 'porPagina' => $porPagina, 'total' => $total, 'totalPaginas' => $totalPaginas,
    'urlBase' => $urlBase,
]); ?>

<?php if (empty($registros)): ?>
    <p class="text-muted">Todavía no hay nada registrado.</p>
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
                        <?php if (!empty($r['datos_antes'])): ?>
                            <details>
                                <summary class="small text-muted" style="cursor:pointer;">Ver datos</summary>
                                <pre class="small text-muted mb-0 mt-1" style="max-width: 320px; white-space: pre-wrap;"><?= htmlspecialchars(
                                    json_encode(json_decode($r['datos_antes'], true), JSON_PRETTY_PRINT | JSON_UNESCAPED_UNICODE),
                                    ENT_QUOTES
                                ) ?></pre>
                            </details>
                        <?php endif; ?>
                    </td>
                </tr>
            <?php endforeach; ?>
            </tbody>
        </table>
    </div>
<?php endif; ?>
