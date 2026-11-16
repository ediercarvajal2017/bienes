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
    'confirmar_qr' => 'Confirmar etiqueta QR',
    // Ciclo de vida de los bienes
    'asignar' => 'Asignar',
    'trasladar' => 'Trasladar',
    'trasladar_sede' => 'Trasladar de sede',
    'reintegrar' => 'Reintegrar',
    'reactivar' => 'Reactivar',
    'marcar_reintegrado' => 'Marcar lote como reintegrado',
    'reabrir' => 'Reabrir lote',
    'unificar' => 'Unificar lotes',
    'reportar' => 'Reportar baja',
    'solicitar' => 'Solicitar reintegro',
    'aprobar' => 'Aprobar',
    'rechazar' => 'Rechazar',
    'cancelar' => 'Cancelar',
    'aplicar' => 'Aplicar carga masiva',
    'iniciar' => 'Iniciar verificación',
    'cerrar' => 'Cerrar verificación',
    'descartar' => 'Descartar hallazgo',
    // Cuentas y seguridad
    'login_ok' => 'Inicio de sesión',
    'cambiar_contrasena' => 'Cambiar contraseña',
    'restablecer_contrasena' => 'Restablecer contraseña',
    '2fa_activar' => 'Activar verificación en dos pasos',
    '2fa_desactivar' => 'Desactivar verificación en dos pasos',
    '2fa_regenerar_codigos' => 'Regenerar códigos de recuperación',
    '2fa_restablecer' => 'Restablecer verificación en dos pasos',
    'correccion_datos' => 'Corrección de datos',
    'aceptar_politica' => 'Aceptar política de datos',
    'exportar_todo' => 'Descargar toda la información',
];
$coloresAccion = [
    'crear' => 'badge-info',
    'editar' => 'badge-info',
    'activar' => 'badge-exito',
    'desactivar' => 'badge-advertencia',
    'eliminar' => 'badge-peligro',
    'restaurar' => 'badge-exito',
    'purgar' => 'badge-neutro',
    'archivar' => 'badge-neutro',
    'confirmar_qr' => 'badge-exito',
    'asignar' => 'badge-info',
    'trasladar' => 'badge-info',
    'trasladar_sede' => 'badge-info',
    'reintegrar' => 'badge-advertencia',
    'reactivar' => 'badge-exito',
    'marcar_reintegrado' => 'badge-exito',
    'reabrir' => 'badge-advertencia',
    'unificar' => 'badge-info',
    'reportar' => 'badge-advertencia',
    'solicitar' => 'badge-advertencia',
    'aprobar' => 'badge-exito',
    'rechazar' => 'badge-peligro',
    'aplicar' => 'badge-info',
    'iniciar' => 'badge-info',
    'cerrar' => 'badge-neutro',
    '2fa_restablecer' => 'badge-advertencia',
    'correccion_datos' => 'badge-neutro',
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
    'baja' => 'Baja',
    'solicitud_reintegro' => 'Solicitud de reintegro',
    'lote_reintegro' => 'Lote de reintegro',
    'carga_masiva' => 'Carga masiva',
    'jornada_verificacion' => 'Verificación física',
    'hallazgo' => 'Hallazgo',
];
?>
<?= ui('encabezado_pagina', ['titulo' => 'Auditoría', 'icono' => 'auditoria',
    'subtitulo' => 'Quién hizo qué y cuándo, en cualquier institución: altas y cambios, asignaciones, traslados, reintegros, bajas, verificaciones e inicios de sesión. Incluye lo que ya se purgó de la papelera.']) ?>

<form method="get" action="<?= Url::to('/auditoria') ?>" class="mb-3 d-flex flex-wrap gap-3 align-items-end">
    <div>
        <label for="campo-institucion-id" class="form-label small mb-1">Institución</label>
        <select id="campo-institucion-id" name="institucion_id" class="form-select form-select-sm selector-buscable selector-filtro">
            <option value="">Todas</option>
            <?php foreach ($instituciones as $i): ?>
                <option value="<?= $i['id'] ?>" <?= (string) ($filtros['institucion_id'] ?? '') === (string) $i['id'] ? 'selected' : '' ?>>
                    <?= htmlspecialchars($i['nombre'], ENT_QUOTES) ?>
                </option>
            <?php endforeach; ?>
        </select>
    </div>
    <div>
        <label for="campo-entidad" class="form-label small mb-1">Tipo</label>
        <select id="campo-entidad" name="entidad" class="form-select form-select-sm">
            <option value="">Todos</option>
            <?php foreach ($etiquetasEntidad as $valor => $etiqueta): ?>
                <option value="<?= $valor ?>" <?= ($filtros['entidad'] ?? '') === $valor ? 'selected' : '' ?>><?= $etiqueta ?></option>
            <?php endforeach; ?>
        </select>
    </div>
    <div>
        <label for="campo-accion" class="form-label small mb-1">Acción</label>
        <select id="campo-accion" name="accion" class="form-select form-select-sm">
            <option value="">Todas</option>
            <?php foreach ($etiquetasAccion as $valor => $etiqueta): ?>
                <option value="<?= $valor ?>" <?= ($filtros['accion'] ?? '') === $valor ? 'selected' : '' ?>><?= $etiqueta ?></option>
            <?php endforeach; ?>
        </select>
    </div>
    <div>
        <label for="campo-desde" class="form-label small mb-1">Desde</label>
        <input id="campo-desde" type="date" name="desde" class="form-control form-control-sm" value="<?= htmlspecialchars($filtros['desde'] ?? '', ENT_QUOTES) ?>">
    </div>
    <div>
        <label for="campo-hasta" class="form-label small mb-1">Hasta</label>
        <input id="campo-hasta" type="date" name="hasta" class="form-control form-control-sm" value="<?= htmlspecialchars($filtros['hasta'] ?? '', ENT_QUOTES) ?>">
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
                        <span class="badge <?= $coloresAccion[$r['accion']] ?? 'badge-neutro' ?>">
                            <?= htmlspecialchars($etiquetasAccion[$r['accion']] ?? $r['accion'], ENT_QUOTES) ?>
                        </span>
                    </td>
                    <td data-label="Tipo"><?= htmlspecialchars($etiquetasEntidad[$r['entidad']] ?? $r['entidad'], ENT_QUOTES) ?> #<?= (int) $r['entidad_id'] ?></td>
                    <td class="text-end">
                        <?php if (!empty($resumen)): ?>
                            <details>
                                <summary class="small text-muted">Ver detalle</summary>
                                <div class="table-responsive">
                                    <table class="table table-sm mb-0 mt-1 tabla-detalle">
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
                                </div>
                            </details>
                        <?php endif; ?>
                    </td>
                </tr>
            <?php endforeach; ?>
            </tbody>
        </table>
    </div>
<?php endif; ?>
