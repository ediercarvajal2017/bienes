<?php

use App\Core\Auth;
use App\Core\Csrf;
use App\Core\Url;
use App\Core\View;
use App\Helpers\AyudaCampo;
use App\Helpers\Evidencia;

/**
 * Facturas en una sola ventana: el formulario (registrar, o editar con ?editar=ID) y
 * debajo los registros con Descargar, Editar y Eliminar.
 */
$editando = $registro !== null;
$ventana = '/facturas';
$v = static fn (string $campo): string => htmlspecialchars((string) ($valores[$campo] ?? ''), ENT_QUOTES);
?>
<?= ui('encabezado_pagina', ['titulo' => 'Facturas', 'icono' => 'factura', 'ayuda' => AyudaCampo::boton('facturas'), 'subtitulo' => 'Registra y conserva las facturas de procesos administrativos.']) ?>

<?php View::render('partials/evidencia_institucion', ['instituciones' => $instituciones, 'institucionId' => $institucionId, 'ventana' => $ventana]); ?>

<?php if ($institucionId === 0): ?>
    <p class="text-muted">Elige una institución para registrar.</p>
<?php else: ?>
    <section class="card mb-4 contenedor-md" id="formularioEvidencia" aria-labelledby="tituloFormularioEvidencia">
        <div class="card-body">
            <h2 class="h6 mb-2" id="tituloFormularioEvidencia"><?= $editando ? 'Editar registro' : 'Registrar factura' ?></h2>
            <form method="post" action="<?= $editando ? Url::to($ventana . '/' . $registro['id'] . '/actualizar') : Url::to($ventana) ?>"
                  enctype="multipart/form-data" class="row g-3">
                <?= Csrf::field() ?>
                <?php if (!$editando): ?>
                    <input type="hidden" name="institucion_id" value="<?= $institucionId ?>">
                <?php endif; ?>
                <div class="col-md-6">
                    <?= AyudaCampo::etiqueta('campo-fecha-factura', 'Fecha de la factura', 'factura_fecha', true) ?>
                    <input id="campo-fecha-factura" type="date" name="fecha_factura" class="form-control" required value="<?= $v('fecha_factura') ?>">
                </div>
                <div class="col-12">
                    <?= AyudaCampo::etiqueta('campo-descripcion', 'Descripción breve', 'factura_descripcion', true) ?>
                    <input id="campo-descripcion" type="text" name="descripcion" class="form-control" required maxlength="300" placeholder="Ej. Compra de sillas para aula 101" value="<?= $v('descripcion') ?>">
                </div>
                <?php if ($editando): ?>
                    <div class="col-md-6">
                        <label class="form-label small d-block">Archivo actual</label>
                        <a href="<?= \App\Helpers\EnlaceArchivo::url($registro['archivo_path']) ?>" class="btn btn-sm btn-outline-secondary" target="_blank">
                            <?= icono('descargar', 'me-1') ?>Descargar
                        </a>
                    </div>
                    <div class="col-12">
                        <?php View::render('partials/campo_foto', [
                            'nombreCampo' => 'archivo',
                            'etiqueta' => 'Reemplazar archivo (opcional, PDF o foto)',
                            'ayuda' => 'factura_archivo',
                            'fotoActualUrl' => null,
                            'accept' => '.pdf,image/jpeg,image/png',
                        ]); ?>
                    </div>
                <?php else: ?>
                    <div class="col-12">
                        <?php View::render('partials/campo_foto', [
                            'nombreCampo' => 'archivo',
                            'etiqueta' => 'Archivo adjunto (PDF o foto)',
                            'ayuda' => 'factura_archivo',
                            'fotoActualUrl' => null,
                            'accept' => '.pdf,image/jpeg,image/png',
                            'required' => true,
                        ]); ?>
                    </div>
                <?php endif; ?>

                <div class="col-12 d-flex flex-wrap gap-2">
                    <?php if ($editando): ?>
                        <button type="submit" class="btn btn-primary"><?= icono('guardar', 'me-1') ?>Guardar cambios</button>
                        <a href="<?= Url::to(Evidencia::ruta($ventana, $institucionId)) ?>" class="btn btn-outline-secondary">Cancelar</a>
                    <?php else: ?>
                        <button type="submit" class="btn btn-primary"><?= icono('guardar', 'me-1') ?>Guardar registro</button>
                    <?php endif; ?>
                </div>
            </form>
        </div>
    </section>
<?php endif; ?>

<section aria-labelledby="tituloRegistrosEvidencia">
    <h2 class="h6 mb-2" id="tituloRegistrosEvidencia">Facturas registradas (<?= (int) $total ?>)</h2>
    <?php if (empty($facturas)): ?>
        <p class="text-muted">Todavía no hay facturas registradas.</p>
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
                    <th>Fecha de la factura</th>
                    <th>Descripción</th>
                    <?php if (Auth::esSuperusuario()): ?><th>Institución</th><?php endif; ?>
                    <th>Registrado por</th>
                    <th>Registrado el</th>
                    <th></th>
                </tr>
                </thead>
                <tbody>
                <?php foreach ($facturas as $f): ?>
                    <tr<?= $editando && (int) $registro['id'] === (int) $f['id'] ? ' class="table-active" aria-current="true"' : '' ?>>
                        <td class="mono" data-label="Fecha de la factura"><?= htmlspecialchars($f['fecha_factura'], ENT_QUOTES) ?></td>
                        <td data-label="Descripción"><?= htmlspecialchars($f['descripcion'], ENT_QUOTES) ?></td>
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
