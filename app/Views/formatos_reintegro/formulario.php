<?php

use App\Core\Auth;
use App\Core\Csrf;
use App\Core\Url;
use App\Core\View;
use App\Helpers\Evidencia;

/**
 * Formatos de reintegro en una sola ventana: el formulario (registrar, o editar con ?editar=ID) y
 * debajo los registros con Descargar, Editar y Eliminar.
 */
$editando = $registro !== null;
$ventana = '/formatos-reintegro';
$v = static fn (string $campo): string => htmlspecialchars((string) ($valores[$campo] ?? ''), ENT_QUOTES);
?>
<div class="mb-3">
    <h1 class="h4 mb-0">Formatos de reintegro</h1>
    <p class="text-muted small mb-0">Registra la evidencia de cada formato de reintegro firmado por la Alcaldía.</p>
</div>

<?php if (!empty($mensaje)): ?><div class="alert alert-success py-2 small"><?= htmlspecialchars($mensaje, ENT_QUOTES) ?></div><?php endif; ?>
<?php if (!empty($error)): ?><div class="alert alert-danger py-2 small"><?= htmlspecialchars($error, ENT_QUOTES) ?></div><?php endif; ?>

<?php View::render('partials/evidencia_institucion', ['instituciones' => $instituciones, 'institucionId' => $institucionId, 'ventana' => $ventana]); ?>

<?php if ($institucionId === 0): ?>
    <p class="text-muted">Elige una institución para registrar.</p>
<?php else: ?>
    <section class="card mb-4" id="formularioEvidencia" style="max-width: 680px;" aria-labelledby="tituloFormularioEvidencia">
        <div class="card-body">
            <h2 class="h6 mb-2" id="tituloFormularioEvidencia"><?= $editando ? 'Editar registro' : 'Registrar formato de reintegro' ?></h2>
            <p class="text-muted small mb-2">Los campos marcados con <span class="text-danger">*</span> son obligatorios.</p>
            <form method="post" action="<?= $editando ? Url::to($ventana . '/' . $registro['id'] . '/actualizar') : Url::to($ventana) ?>"
                  enctype="multipart/form-data" class="row g-3">
                <?= Csrf::field() ?>
                <?php if (!$editando): ?>
                    <input type="hidden" name="institucion_id" value="<?= $institucionId ?>">
                <?php endif; ?>
                <div class="col-md-6">
                    <label for="campo-fecha-reintegro" class="form-label small requerido">Fecha del reintegro</label>
                    <input id="campo-fecha-reintegro" type="date" name="fecha_reintegro" class="form-control" required value="<?= $v('fecha_reintegro') ?>">
                </div>
                <div class="col-12">
                    <label for="campo-descripcion" class="form-label small">Descripción (opcional)</label>
                    <input id="campo-descripcion" type="text" name="descripcion" class="form-control" placeholder="Ej. Reintegro de equipos de la sede principal" value="<?= $v('descripcion') ?>">
                </div>
                <?php if ($editando): ?>
                    <div class="col-md-6">
                        <label class="form-label small d-block">Archivo actual</label>
                        <a href="<?= \App\Helpers\EnlaceArchivo::url($registro['archivo_path']) ?>" class="btn btn-sm btn-outline-secondary" target="_blank">
                            <i class="bi bi-download me-1" aria-hidden="true"></i>Descargar
                        </a>
                    </div>
                    <div class="col-12">
                        <label for="campo-archivo" class="form-label small">Reemplazar archivo (opcional, PDF)</label>
                        <input id="campo-archivo" type="file" name="archivo" accept="application/pdf" class="form-control">
                    </div>
                <?php else: ?>
                    <div class="col-12">
                        <label for="campo-archivo" class="form-label small requerido">Archivo adjunto (PDF)</label>
                        <input id="campo-archivo" type="file" name="archivo" accept="application/pdf" class="form-control" required>
                    </div>
                <?php endif; ?>

                <div class="col-12 d-flex flex-wrap gap-2">
                    <?php if ($editando): ?>
                        <button type="submit" class="btn btn-primary"><i class="bi bi-check-lg me-1" aria-hidden="true"></i>Guardar cambios</button>
                        <a href="<?= Url::to(Evidencia::ruta($ventana, $institucionId)) ?>" class="btn btn-outline-secondary">Cancelar</a>
                    <?php else: ?>
                        <button type="submit" class="btn btn-primary"><i class="bi bi-archive me-1" aria-hidden="true"></i>Guardar registro</button>
                    <?php endif; ?>
                </div>
            </form>
        </div>
    </section>
<?php endif; ?>

<section aria-labelledby="tituloRegistrosEvidencia">
    <h2 class="h6 mb-2" id="tituloRegistrosEvidencia">Formatos registrados (<?= (int) $total ?>)</h2>
    <?php if (empty($formatos)): ?>
        <p class="text-muted">Todavía no hay formatos de reintegro registrados.</p>
    <?php else: ?>
        <?php $urlBase = Url::to(Evidencia::ruta($ventana, $institucionId)); ?>
        <?php View::render('partials/paginacion', [
            'pagina' => $pagina, 'porPagina' => $porPagina, 'total' => $total, 'totalPaginas' => $totalPaginas,
            'opcionesPorPagina' => $opcionesPorPagina, 'urlBase' => $urlBase,
        ]); ?>
        <div class="table-responsive">
            <table class="table table-sm table-hover align-middle bg-white tabla-cards">
                <thead>
                <tr>
                    <th>Fecha del reintegro</th>
                    <th>Descripción</th>
                    <?php if (Auth::esSuperusuario()): ?><th>Institución</th><?php endif; ?>
                    <th>Registrado por</th>
                    <th>Registrado el</th>
                    <th></th>
                </tr>
                </thead>
                <tbody>
                <?php foreach ($formatos as $f): ?>
                    <tr<?= $editando && (int) $registro['id'] === (int) $f['id'] ? ' class="table-active" aria-current="true"' : '' ?>>
                        <td class="mono" data-label="Fecha del reintegro"><?= htmlspecialchars($f['fecha_reintegro'], ENT_QUOTES) ?></td>
                        <td class="text-muted small" data-label="Descripción"><?= !empty($f['descripcion']) ? htmlspecialchars($f['descripcion'], ENT_QUOTES) : '—' ?></td>
                        <?php if (Auth::esSuperusuario()): ?><td class="text-muted small" data-label="Institución"><?= htmlspecialchars($f['institucion_nombre'], ENT_QUOTES) ?></td><?php endif; ?>
                        <td class="text-muted small" data-label="Registrado por">
                            <?= !empty($f['registrado_por_nombres']) ? htmlspecialchars($f['registrado_por_nombres'] . ' ' . $f['registrado_por_apellidos'], ENT_QUOTES) : '—' ?>
                        </td>
                        <td class="text-muted small mono" data-label="Registrado el"><?= htmlspecialchars($f['fecha_registro'], ENT_QUOTES) ?></td>
                        <td class="text-end">
                            <?php View::render('partials/evidencia_acciones', [
                                'archivoPath' => $f['archivo_path'],
                                'urlEditar' => Url::to(Evidencia::ruta($ventana, (int) $f['institucion_id'], ['editar' => (int) $f['id']])) . '#formularioEvidencia',
                                'urlEliminar' => Url::to($ventana . '/' . $f['id'] . '/eliminar'),
                            ]); ?>
                        </td>
                    </tr>
                <?php endforeach; ?>
                </tbody>
            </table>
        </div>
        <?php View::render('partials/paginacion', [
            'pagina' => $pagina, 'porPagina' => $porPagina, 'total' => $total, 'totalPaginas' => $totalPaginas,
            'opcionesPorPagina' => $opcionesPorPagina, 'urlBase' => $urlBase,
        ]); ?>
    <?php endif; ?>
</section>
