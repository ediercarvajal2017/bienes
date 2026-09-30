<?php

use App\Core\Auth;
use App\Core\Csrf;
use App\Core\Url;
use App\Core\View;
use App\Helpers\Evidencia;

/**
 * "Cartera recibida de la Alcaldía" en una sola ventana: el formulario (registrar, o editar
 * con ?editar=ID) y debajo los registros con Descargar, Editar y Eliminar.
 */
$editando = $registro !== null;
$ventana = '/cartera/enviar';
?>
<div class="mb-3">
    <h1 class="h4 mb-0">Cartera recibida de la Alcaldía</h1>
    <p class="text-muted small mb-0">Registra la evidencia de cada cartera que la institución solicitó por correo (fuera del sistema).</p>
</div>

<?php if (!empty($mensaje)): ?><div class="alert alert-success py-2 small"><?= htmlspecialchars($mensaje, ENT_QUOTES) ?></div><?php endif; ?>
<?php if (!empty($error)): ?><div class="alert alert-danger py-2 small"><?= htmlspecialchars($error, ENT_QUOTES) ?></div><?php endif; ?>

<?php View::render('partials/evidencia_institucion', ['instituciones' => $instituciones, 'institucionId' => $institucionId, 'ventana' => $ventana]); ?>

<?php if ($institucionId === 0): ?>
    <p class="text-muted">Elige una institución para registrar una cartera recibida.</p>
<?php else: ?>
    <section class="card mb-4" id="formularioEvidencia" style="max-width: 680px;" aria-labelledby="tituloFormularioEvidencia">
        <div class="card-body">
            <h2 class="h6 mb-2" id="tituloFormularioEvidencia"><?= $editando ? 'Editar registro' : 'Registrar cartera recibida' ?></h2>
            <p class="text-muted small mb-2">Los campos marcados con <span class="text-danger">*</span> son obligatorios.</p>
            <form method="post" action="<?= $editando ? Url::to('/cartera/' . $registro['id'] . '/actualizar') : Url::to($ventana) ?>"
                  enctype="multipart/form-data" class="row g-3">
                <?= Csrf::field() ?>
                <?php if (!$editando): ?>
                    <input type="hidden" name="institucion_id" value="<?= $institucionId ?>">
                <?php elseif (empty($registro['funcionario_id'])): ?>
                    <div class="col-12">
                        <div class="alert alert-info py-2 small mb-0">
                            Este registro es de antes y guardó el funcionario como texto: <strong><?= htmlspecialchars((string) $registro['nombre_funcionario'], ENT_QUOTES) ?></strong>.
                            Elígelo de la lista y completa el correo con el que solicitó la cartera.
                        </div>
                    </div>
                <?php endif; ?>

                <?php View::render('cartera/_campos', ['funcionarios' => $funcionarios, 'valores' => $valores]); ?>

                <?php if ($editando): ?>
                    <div class="col-md-6">
                        <label class="form-label small d-block">Archivo actual</label>
                        <a href="<?= \App\Helpers\EnlaceArchivo::url($registro['archivo_path']) ?>" class="btn btn-sm btn-outline-secondary" target="_blank">
                            <i class="bi bi-download me-1" aria-hidden="true"></i>Descargar
                        </a>
                    </div>
                    <div class="col-12">
                        <label for="campo-archivo" class="form-label small">Reemplazar archivo (opcional, Excel)</label>
                        <input id="campo-archivo" type="file" name="archivo" accept=".xlsx,.xls" class="form-control">
                    </div>
                <?php else: ?>
                    <div class="col-12">
                        <label for="campo-archivo" class="form-label small requerido">Archivo de la cartera recibida (Excel)</label>
                        <input id="campo-archivo" type="file" name="archivo" accept=".xlsx,.xls" class="form-control" required>
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
    <h2 class="h6 mb-2" id="tituloRegistrosEvidencia">Carteras recibidas (<?= (int) $total ?>)</h2>
    <?php if (empty($envios)): ?>
        <p class="text-muted">Todavía no hay carteras recibidas registradas.</p>
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
                    <th>Fecha en que se recibió</th>
                    <?php if (Auth::esSuperusuario()): ?><th>Institución</th><?php endif; ?>
                    <th>Solicitada por</th>
                    <th>Correo del solicitante</th>
                    <th>Llegó desde</th>
                    <th>Registrado por</th>
                    <th>Registrado el</th>
                    <th></th>
                </tr>
                </thead>
                <tbody>
                <?php foreach ($envios as $e): ?>
                    <tr<?= $editando && (int) $registro['id'] === (int) $e['id'] ? ' class="table-active" aria-current="true"' : '' ?>>
                        <td class="mono" data-label="Fecha en que se recibió"><?= htmlspecialchars($e['fecha_envio'], ENT_QUOTES) ?></td>
                        <?php if (Auth::esSuperusuario()): ?><td class="text-muted small" data-label="Institución"><?= htmlspecialchars($e['institucion_nombre'], ENT_QUOTES) ?></td><?php endif; ?>
                        <td data-label="Solicitada por"><?= htmlspecialchars($e['nombre_funcionario'], ENT_QUOTES) ?></td>
                        <td class="small text-muted" data-label="Correo del solicitante"><?= !empty($e['correo_solicitante']) ? htmlspecialchars($e['correo_solicitante'], ENT_QUOTES) : '—' ?></td>
                        <td class="small text-muted" data-label="Llegó desde"><?= htmlspecialchars($e['correo_remitente'], ENT_QUOTES) ?></td>
                        <td class="text-muted small" data-label="Registrado por">
                            <?= !empty($e['registrado_por_nombres']) ? htmlspecialchars($e['registrado_por_nombres'] . ' ' . $e['registrado_por_apellidos'], ENT_QUOTES) : '—' ?>
                        </td>
                        <td class="text-muted small mono" data-label="Registrado el"><?= htmlspecialchars($e['fecha_registro'], ENT_QUOTES) ?></td>
                        <td class="text-end">
                            <?php View::render('partials/evidencia_acciones', [
                                'archivoPath' => $e['archivo_path'],
                                'urlEditar' => Url::to(Evidencia::ruta($ventana, (int) $e['institucion_id'], ['editar' => (int) $e['id']])) . '#formularioEvidencia',
                                'urlEliminar' => Url::to('/cartera/' . $e['id'] . '/eliminar'),
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
