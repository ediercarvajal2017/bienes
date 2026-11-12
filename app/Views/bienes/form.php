<?php

use App\Core\Auth;
use App\Core\Csrf;
use App\Core\Url;
use App\Core\View;
use App\Helpers\AyudaCampo;
use App\Models\Categoria;

$esEdicion = $bien !== null;
$puedeEditar = Auth::esSuperusuario() || Auth::tienePermiso('bienes.editar') || (!$esEdicion && Auth::tienePermiso('bienes.crear'));
// Un bien dado de baja es de solo lectura (estado final; ver BienController::actualizar).
$bienDadoDeBaja = $esEdicion && $bien['estado'] === 'dado_de_baja';
$puedeEditar = $puedeEditar && !$bienDadoDeBaja;
// Un bien dado de baja o reintegrado ya no esta fisicamente en la institucion -- no tiene
// sentido ofrecer "Asignar" para el (ver MovimientoController::verificarAsignable()).
$bienFueraDeCirculacion = $esEdicion && in_array($bien['estado'], ['dado_de_baja', 'reintegrado'], true);
// "Sin cartera" no admite reintegro, solo baja (ver MovimientoController::reintegrar()) --
// Trasladar y Trasladar a otra sede siguen disponibles para esta categoria.
$bienEsSinCartera = $esEdicion && ($bien['categoria_nombre'] ?? null) === Categoria::NOMBRE_CATEGORIA_PROTEGIDA;
$viejo ??= [];
$verificacionId ??= null;
$hallazgo ??= null;
$errorCampo ??= null;
$acciones ??= [];
$espaciosInstitucion ??= [];
$urlVolver ??= Url::to('/bienes');
$v = static fn (string $campo, mixed $porDefecto = '') => $viejo[$campo] ?? $bien[$campo] ?? $porDefecto;
$invalido = static fn (string $campo) => $errorCampo === $campo ? ' is-invalid' : '';
$e = static fn (mixed $texto): string => htmlspecialchars((string) $texto, ENT_QUOTES);
$deshabilitado = $puedeEditar ? '' : ' disabled';
// El error de un campo se muestra debajo de ese campo; el aviso de arriba queda para los
// errores generales (así el mismo mensaje no aparece dos veces).
$errorDe = static fn (string $campo): string => $errorCampo === $campo && !empty($error)
    ? '<div class="invalid-feedback d-block">' . htmlspecialchars((string) $error, ENT_QUOTES) . '</div>' : '';
$etiquetasEstado = ['activo' => 'Activo', 'reintegrado' => 'Reintegrado', 'en_reparacion' => 'En reparación', 'dado_de_baja' => 'Dado de baja'];
?>
<div class="d-flex flex-wrap justify-content-between align-items-center gap-2 mb-3" style="max-width: 720px;">
    <h1 class="h4 mb-0"><?= $esEdicion ? 'Editar bien' : 'Registrar bien' ?></h1>
    <a href="<?= $e($urlVolver) ?>" class="btn btn-sm btn-outline-secondary">Volver</a>
</div>

<?php if ($esEdicion): ?>
    <div class="card mb-3" style="max-width: 720px;">
        <div class="card-body d-flex align-items-center gap-3 py-3">
            <a href="<?= Url::to('/qr/' . $bien['qr_token']) ?>" target="_blank" title="Ver la ficha pública del código QR" aria-label="Ver ficha pública" class="flex-shrink-0">
                <img src="<?= Url::to('/qr/' . $bien['qr_token'] . '/imagen') ?>" alt="Código QR del bien" style="width:64px;height:64px;">
            </a>
            <div class="flex-grow-1" style="min-width: 0;">
                <div class="d-flex flex-wrap align-items-center gap-2">
                    <span class="mono fw-semibold"><?= $e($bien['codigo_identificacion']) ?></span>
                    <span class="badge badge-estado-<?= $e($bien['estado']) ?>"><?= $etiquetasEstado[$bien['estado']] ?? $e($bien['estado']) ?></span>
                </div>
                <div class="text-truncate"><?= $e($bien['descripcion']) ?></div>
                <div class="small text-muted" id="ubicacionActual">
                    <?php if ($asignacionActiva && ($asignacionActiva['tipo_responsabilidad'] ?? '') === 'individual'): ?>
                        <span class="badge etiqueta-responsabilidad etiqueta-individual">Individual</span>
                        A cargo de <span class="fw-semibold text-body"><?= $e($asignacionActiva['persona_nombre']) ?></span>
                        <?php if (!empty($asignacionActiva['espacio_nombre'])): ?>
                            · guardado en <?= $e($asignacionActiva['espacio_nombre']) ?>
                        <?php endif; ?>
                        · desde <?= $e($asignacionActiva['fecha_asignacion']) ?>
                    <?php elseif ($asignacionActiva && !empty($asignacionActiva['espacio_nombre'])): ?>
                        <span class="badge etiqueta-responsabilidad etiqueta-grupal">Grupal</span>
                        <span class="fw-semibold text-body"><?= $e($asignacionActiva['espacio_nombre']) ?></span>
                        <?php if (!empty($asignacionActiva['responsables_nombres'])): ?>
                            · <?= $e($asignacionActiva['responsables_nombres']) ?>
                        <?php endif; ?>
                        · desde <?= $e($asignacionActiva['fecha_asignacion']) ?>
                    <?php elseif ($bienFueraDeCirculacion): ?>
                        Ya no está en la institución (<?= $etiquetasEstado[$bien['estado']] ?? $e($bien['estado']) ?>).
                    <?php else: ?>
                        Sin espacio asignado
                    <?php endif; ?>
                </div>
            </div>
            <a href="<?= Url::to('/qr/' . $bien['qr_token'] . '/imagen') ?>" download="qr-<?= $e($bien['codigo_identificacion']) ?>.png"
               class="btn btn-sm btn-outline-secondary flex-shrink-0" title="Descargar el código QR para imprimir" aria-label="Descargar el código QR">
                <i class="bi bi-download" aria-hidden="true"></i>
            </a>
        </div>
    </div>
<?php endif; ?>

<?php if (!empty($mensaje)): ?>
    <div class="alert alert-success py-2 small" style="max-width: 720px;"><?= $e($mensaje) ?></div>
<?php endif; ?>
<?php if (!empty($verificacionId)): ?>
    <div class="alert alert-info py-2 small" style="max-width: 720px;">
        Corrigiendo la ubicación a partir de una discrepancia de la verificación física: elija el nuevo espacio y guarde.
    </div>
<?php endif; ?>
<?php if (!empty($error) && $errorCampo === null): ?>
    <div class="alert alert-danger py-2 small" style="max-width: 720px;"><?= $e($error) ?></div>
<?php endif; ?>

<?php if (!$esEdicion && $hallazgo !== null): ?>
    <div class="alert alert-info py-2 small" style="max-width: 720px;">
        Formalizando un hallazgo reportado en <strong><?= $e($hallazgo['espacio_nombre']) ?></strong>
        por <?= $e($hallazgo['nombres'] . ' ' . $hallazgo['apellidos']) ?>.
        Al guardar, el bien quedará asignado automáticamente a ese espacio.
    </div>
<?php endif; ?>

<?php if ($bienDadoDeBaja): ?>
    <div class="alert alert-secondary py-2 small" style="max-width: 720px;">
        Este bien está <strong>dado de baja</strong>: sus datos se conservan tal como estaban y ya no se pueden modificar.
    </div>
<?php endif; ?>

<form id="datosBien" method="post"
      action="<?= $esEdicion ? Url::to('/bienes/' . $bien['id']) : Url::to('/bienes') ?>"
      enctype="multipart/form-data" class="formulario-secciones">
    <?= Csrf::field() ?>
    <?php if (!$esEdicion && $hallazgo !== null): ?>
        <input type="hidden" name="hallazgo_id" value="<?= (int) $hallazgo['id'] ?>">
    <?php endif; ?>

    <?php if ($esEdicion && $puedeEditar && $acciones !== []): ?>
        <?php
        // Al llegar desde una discrepancia de la verificación física, la acción de corregir
        // la ubicación viene ya elegida (y la discrepancia queda resuelta al guardar).
        $accionElegida = (string) ($viejo['accion'] ?? '');
        if ($accionElegida === '' && $verificacionId !== null) {
            $accionElegida = isset($acciones['trasladar']) ? 'trasladar' : (isset($acciones['asignar']) ? 'asignar' : '');
        }
        $va = static fn (string $campo, string $porDefecto = '') => (string) ($viejo[$campo] ?? $porDefecto);
        ?>
        <div class="card mb-3">
            <div class="card-body">
                <fieldset id="seccionAccion" aria-labelledby="tituloAccion">
                    <legend class="titulo-seccion float-none w-auto d-flex align-items-center gap-1">
                        <span id="tituloAccion">¿Qué desea hacer con este bien?</span><?= AyudaCampo::boton('accion') ?>
                    </legend>
                    <?php if ($verificacionId !== null): ?>
                        <input type="hidden" name="verificacion_id" value="<?= (int) $verificacionId ?>">
                    <?php endif; ?>
                    <label for="accionBien" class="visually-hidden">Acción</label>
                    <select id="accionBien" name="accion" class="form-select mb-2">
                        <option value="ninguna">Solo guardar los datos</option>
                        <?php foreach ($acciones as $clave => $texto): ?>
                            <option value="<?= $clave ?>" <?= $accionElegida === $clave ? 'selected' : '' ?>><?= $e($texto) ?></option>
                        <?php endforeach; ?>
                    </select>

                    <?php if (isset($acciones['asignar']) || isset($acciones['trasladar'])): ?>
                        <div data-campos-accion="asignar trasladar" class="mb-2" hidden>
                            <?php View::render('partials/campos_responsabilidad', [
                                'prefijo' => 'accion',
                                'nombres' => ['tipo' => 'accion_tipo', 'espacio' => 'accion_espacio_id', 'persona' => 'accion_persona_id'],
                                'espacios' => $espaciosInstitucion,
                                'personas' => $personasInstitucion ?? [],
                                'valores' => [
                                    'tipo' => $va('accion_tipo', (string) ($asignacionActiva['tipo_responsabilidad'] ?? 'grupal')),
                                    'espacio' => $va('accion_espacio_id'),
                                    'persona' => $va('accion_persona_id'),
                                ],
                                'espacioObligatorio' => true,
                                'deshabilitado' => true,
                            ]); ?>
                        </div>
                    <?php endif; ?>

                    <?php if (isset($acciones['trasladar_sede'])): ?>
                        <div data-campos-accion="trasladar_sede" class="mb-2" hidden>
                            <label class="form-label small requerido" for="accionSede">Sede destino</label>
                            <select id="accionSede" name="accion_sede_id" class="form-select form-select-sm mb-2" required disabled>
                                <option value="">-- Selecciona una sede --</option>
                                <?php foreach ($familiaSedesDestino as $sede): ?>
                                    <option value="<?= (int) $sede['id'] ?>" <?= $va('accion_sede_id') === (string) $sede['id'] ? 'selected' : '' ?>><?= $e($sede['nombre']) ?></option>
                                <?php endforeach; ?>
                            </select>
                            <label class="form-label small requerido" for="accionEspacioSede">Espacio en la sede destino</label>
                            <select id="accionEspacioSede" name="accion_espacio_sede_id" class="form-select form-select-sm" required disabled>
                                <option value="">-- Primero selecciona una sede --</option>
                            </select>
                        </div>
                    <?php endif; ?>

                    <?php if (isset($acciones['reintegrar'])): ?>
                        <div data-campos-accion="reintegrar" class="mb-2" hidden>
                            <label class="form-label small requerido" for="accionDestino">Destino del reintegro</label>
                            <input id="accionDestino" type="text" name="accion_destino" class="form-control form-control-sm" required disabled
                                   placeholder="Ej. Almacén institucional" value="<?= $e($va('accion_destino')) ?>">
                        </div>
                    <?php endif; ?>

                    <?php if (isset($acciones['reportar_baja'])): ?>
                        <div data-campos-accion="reportar_baja" class="mb-2" hidden>
                            <label class="form-label small requerido" for="accionEstadoReportado">Estado del bien</label>
                            <input id="accionEstadoReportado" type="text" name="accion_estado_reportado" class="form-control form-control-sm mb-2" required disabled
                                   list="sugerenciasEstadoBaja" value="<?= $e($va('accion_estado_reportado')) ?>">
                            <datalist id="sugerenciasEstadoBaja">
                                <option value="Dañado"><option value="Inservible"><option value="Deteriorado"><option value="Obsoleto"><option value="Perdido">
                            </datalist>
                            <label class="form-label small requerido" for="accionDescripcionBaja">Qué pasó y por qué se da de baja</label>
                            <textarea id="accionDescripcionBaja" name="accion_descripcion_baja" class="form-control form-control-sm mb-2" rows="2" required disabled><?= $e($va('accion_descripcion_baja')) ?></textarea>
                            <label class="form-label small" for="accionFotoBaja">Foto del estado (opcional)</label>
                            <input id="accionFotoBaja" type="file" name="accion_foto_baja" accept="image/*" capture="environment" class="form-control form-control-sm" disabled>
                        </div>
                    <?php endif; ?>

                    <?php if (isset($acciones['reactivar'])): ?>
                        <div data-campos-accion="reactivar" class="mb-2" hidden>
                            <label class="form-label small requerido" for="accionMotivo">Motivo</label>
                            <textarea id="accionMotivo" name="accion_motivo" class="form-control form-control-sm" rows="2" required disabled
                                      placeholder="Ej. La Alcaldía devolvió el bien"><?= $e($va('accion_motivo')) ?></textarea>
                        </div>
                    <?php endif; ?>

                    <div data-campos-accion="asignar trasladar trasladar_sede reintegrar reactivar reportar_baja" hidden>
                        <div class="row g-2">
                            <div class="col-sm-5">
                                <label class="form-label small requerido" for="accionFecha">Fecha</label>
                                <input id="accionFecha" type="date" min="<?= \App\Helpers\FechaMovimiento::MINIMA ?>" max="<?= \App\Helpers\FechaMovimiento::hoy() ?>" name="accion_fecha" class="form-control form-control-sm" required disabled
                                       value="<?= $e($va('accion_fecha', date('Y-m-d'))) ?>">
                            </div>
                            <div class="col-sm-7" data-campos-accion="asignar trasladar trasladar_sede reintegrar">
                                <label class="form-label small" for="accionObservaciones">Observación</label>
                                <input id="accionObservaciones" type="text" name="accion_observaciones" class="form-control form-control-sm" disabled
                                       value="<?= $e($va('accion_observaciones')) ?>">
                            </div>
                        </div>
                    </div>
                </fieldset>
            </div>
        </div>
    <?php endif; ?>

    <div class="card mb-3">
        <div class="card-body">
            <h2 class="titulo-seccion">Datos del bien</h2>
            <div class="row g-3">
                <?php if (!$esEdicion && Auth::esSuperusuario()): ?>
                    <div class="col-12">
                        <?= AyudaCampo::etiqueta('institucionBien', 'Institución', 'institucion', true) ?>
                        <select name="institucion_id" id="institucionBien" class="form-select selector-buscable" required>
                            <?php foreach ($instituciones as $i): ?>
                                <option value="<?= $i['id'] ?>" <?= (string) $v('institucion_id') === (string) $i['id'] ? 'selected' : '' ?>><?= $e($i['nombre']) ?></option>
                            <?php endforeach; ?>
                        </select>
                    </div>
                <?php endif; ?>

                <div class="col-md-6">
                    <?= AyudaCampo::etiqueta('categoriaBien', 'Categoría', 'categoria') ?>
                    <select name="categoria_id" id="categoriaBien" class="form-select selector-buscable<?= $invalido('categoria_id') ?>"<?= $deshabilitado ?>>
                        <option value="">-- Selecciona --</option>
                        <?php foreach ($categorias as $c): ?>
                            <option value="<?= $c['id'] ?>" <?= (int) $v('categoria_id', 0) === (int) $c['id'] ? 'selected' : '' ?>>
                                <?= $e($c['nombre']) ?>
                            </option>
                        <?php endforeach; ?>
                    </select>
                    <?= $errorDe('categoria_id') ?>
                </div>
                <div class="col-md-6">
                    <?= AyudaCampo::etiqueta('codigoIdentificacion', 'Código de identificación', 'codigo', true) ?>
                    <input type="text" name="codigo_identificacion" id="codigoIdentificacion" class="form-control<?= $invalido('codigo_identificacion') ?>" required autocomplete="off"<?= $deshabilitado ?>
                           value="<?= $e($v('codigo_identificacion')) ?>">
                    <?= $errorDe('codigo_identificacion') ?>
                </div>

                <div class="col-md-8">
                    <?= AyudaCampo::etiqueta('campo-descripcion', 'Descripción', 'descripcion', true) ?>
                    <input id="campo-descripcion" type="text" name="descripcion" data-descripcion-segun-categoria="categoriaBien" class="form-control<?= $invalido('descripcion') ?>" required<?= $deshabilitado ?>
                           placeholder="Ej. Silla universitaria en madera"
                           value="<?= $e($v('descripcion')) ?>">
                    <?= $errorDe('descripcion') ?>
                </div>
                <div class="col-md-4">
                    <?= AyudaCampo::etiqueta('campo-marca', 'Marca', 'marca') ?>
                    <input id="campo-marca" type="text" name="marca" class="form-control"<?= $deshabilitado ?>
                           value="<?= $e($v('marca')) ?>">
                </div>

                <div class="col-md-4">
                    <?= AyudaCampo::etiqueta('campo-fecha-ingreso', 'Fecha de ingreso', 'fecha_ingreso', true) ?>
                    <input id="campo-fecha-ingreso" type="date" name="fecha_ingreso" class="form-control<?= $invalido('fecha_ingreso') ?>" required<?= $deshabilitado ?>
                           value="<?= $e($v('fecha_ingreso', date('Y-m-d'))) ?>">
                    <?= $errorDe('fecha_ingreso') ?>
                </div>
                <div class="col-md-4">
                    <?= AyudaCampo::etiqueta('campo-valor', 'Valor', 'valor') ?>
                    <input id="campo-valor" type="number" inputmode="decimal" step="0.01" min="0" max="9999999999" name="valor" class="form-control<?= $invalido('valor') ?>"<?= $deshabilitado ?>
                           value="<?= $e($v('valor', '0')) ?>">
                    <?= $errorDe('valor') ?>
                </div>
                <div class="col-md-4">
                    <?= AyudaCampo::etiqueta('campoEstadoBien', 'Estado', 'estado') ?>
                    <?php if ($esEdicion && in_array($bien['estado'], ['reintegrado', 'dado_de_baja'], true)): ?>
                        <input type="text" id="campoEstadoBien" class="form-control" value="<?= $etiquetasEstado[$bien['estado']] ?>" disabled>
                    <?php else: ?>
                        <select name="estado" id="campoEstadoBien" class="form-select"<?= $deshabilitado ?>>
                            <?php foreach (['activo' => 'Activo', 'en_reparacion' => 'En reparación'] as $valorEstado => $etiqueta): ?>
                                <option value="<?= $valorEstado ?>" <?= $v('estado', 'activo') === $valorEstado ? 'selected' : '' ?>><?= $etiqueta ?></option>
                            <?php endforeach; ?>
                        </select>
                    <?php endif; ?>
                </div>
            </div>
        </div>
    </div>

    <?php if (!$esEdicion && !empty($espaciosInstitucion)): ?>
        <div class="card mb-3">
            <div class="card-body">
                <h2 class="titulo-seccion">Ubicación <span class="fw-normal text-muted small">(opcional)</span></h2>
                <?php View::render('partials/campos_responsabilidad', [
                    'prefijo' => 'nuevo',
                    'idEspacio' => 'campoEspacioNuevo',
                    'nombres' => ['tipo' => 'tipo_responsabilidad', 'espacio' => 'espacio_id', 'persona' => 'persona_id'],
                    'espacios' => $espaciosInstitucion,
                    'personas' => $personasInstitucion ?? [],
                    'valores' => [
                        'tipo' => (string) ($viejo['tipo_responsabilidad'] ?? 'grupal'),
                        'espacio' => (string) ($viejo['espacio_id'] ?? ''),
                        'persona' => (string) ($viejo['persona_id'] ?? ''),
                    ],
                    'espacioObligatorio' => false,
                    'textoSinEspacio' => '-- Sin asignar por ahora --',
                    'tamano' => '',
                ]); ?>
            </div>
        </div>
    <?php endif; ?>

    <?php
    // "Imprimir QR" siempre viene desmarcada: marcarla es una orden ("mándalo a imprimir"),
    // no un estado, y desmarcada no cambia nada (ver BienController::procesarSolicitudQr).
    // Al editar se informa si el bien ya está en la Bodega de QR.
    $faseQr = $esEdicion ? \App\Models\Bien::faseQr($bien) : null;
    $fotoActualUrl = !empty($bien['foto_path'])
        ? \App\Helpers\EnlaceArchivo::url($bien['foto_path'])
        : (!empty($hallazgo['foto_path']) ? \App\Helpers\EnlaceArchivo::url($hallazgo['foto_path']) : null);
    ?>
    <div class="card mb-3">
        <div class="card-body">
            <h2 class="titulo-seccion">Foto y documentos</h2>
            <div class="row g-3">
                <div class="col-md-6">
                    <?php if ($puedeEditar): ?>
                        <?php View::render('partials/campo_foto', [
                            'nombreCampo' => 'foto',
                            'etiqueta' => 'Fotografía',
                            'ayuda' => 'foto',
                            'fotoActualUrl' => $fotoActualUrl,
                        ]); ?>
                    <?php elseif (!empty($bien['foto_path'])): ?>
                        <span class="form-label small d-block">Fotografía</span>
                        <img src="<?= \App\Helpers\EnlaceArchivo::url($bien['foto_path']) ?>"
                             alt="Foto de <?= $e($bien['descripcion']) ?>"
                             class="mb-2 d-block" style="height:72px;border-radius:4px;">
                    <?php else: ?>
                        <span class="form-label small d-block">Fotografía</span>
                        <span class="nota-campo">Sin foto.</span>
                    <?php endif; ?>
                </div>

                <div class="col-md-6">
                    <div class="form-check">
                        <input type="checkbox" name="tiene_factura" value="1" id="tieneFactura" class="form-check-input"
                               <?= !empty($v('tiene_factura', 0)) ? 'checked' : '' ?><?= $deshabilitado ?>>
                        <label class="form-check-label small" for="tieneFactura">Tiene factura de compra</label><?= AyudaCampo::boton('factura') ?>
                    </div>
                    <div class="mt-2<?= !empty($v('tiene_factura', 0)) ? '' : ' d-none' ?>" id="contenedorFactura">
                        <?php if (!empty($bien['factura_pdf_path'])): ?>
                            <a href="<?= \App\Helpers\EnlaceArchivo::url($bien['factura_pdf_path']) ?>" target="_blank" class="d-inline-block mb-2 small">Ver factura actual</a>
                        <?php endif; ?>
                        <?php if ($puedeEditar): ?>
                            <label for="campo-factura-pdf" class="visually-hidden">Factura (PDF)</label>
                            <input id="campo-factura-pdf" type="file" name="factura_pdf" accept="application/pdf" class="form-control form-control-sm">
                        <?php endif; ?>
                    </div>

                    <div class="form-check mt-3">
                        <?php if ($faseQr === 'por_imprimir'): ?>
                            <input type="checkbox" name="quitar_qr" value="1" id="quitarQr" class="form-check-input"<?= $deshabilitado ?>>
                            <label class="form-check-label small" for="quitarQr">Quitar de la Bodega de QR</label><?= AyudaCampo::boton('imprimir_qr') ?>
                            <div class="nota-campo">Este bien ya está en la Bodega de QR, pendiente de imprimir.</div>
                        <?php else: ?>
                            <input type="checkbox" name="imprimir_qr" value="1" id="imprimirQr" class="form-check-input"
                                   <?= !empty($v('imprimir_qr', '0')) ? 'checked' : '' ?><?= $deshabilitado ?>>
                            <label class="form-check-label small" for="imprimirQr"><?= $faseQr === 'por_pegar' ? 'Volver a imprimir el QR' : 'Imprimir QR' ?></label><?= AyudaCampo::boton('imprimir_qr') ?>
                            <?php if ($faseQr === 'por_pegar'): ?>
                                <div class="nota-campo">El QR ya se imprimió y está pendiente de pegar (Bodega de QR).</div>
                            <?php endif; ?>
                        <?php endif; ?>
                    </div>
                </div>
            </div>
        </div>
    </div>

    <?php if ($puedeEditar): ?>
        <div class="accion-fija-movil d-flex flex-wrap gap-2">
            <button type="submit" id="botonGuardarBien" class="btn btn-primary"><?= $esEdicion ? 'Guardar cambios' : 'Registrar bien' ?></button>
            <?php if ($esEdicion): ?>
                <a href="<?= $e($urlVolver) ?>" class="btn btn-outline-secondary">Cancelar</a>
            <?php endif; ?>
        </div>
    <?php endif; ?>
</form>

<?php if ($esEdicion && $puedeEditar && $acciones !== []): ?>
<script>
// Menú "¿Qué desea hacer con este bien?": muestra solo los campos de la acción elegida
// (los ocultos quedan deshabilitados: ni se validan ni se envían), cambia el texto del
// botón y pide confirmación en las acciones que sacan el bien de su espacio.
(function () {
    var formulario = document.getElementById('datosBien');
    var selector = document.getElementById('accionBien');
    var boton = document.getElementById('botonGuardarBien');
    var textos = {
        ninguna: 'Guardar cambios', asignar: 'Guardar y asignar', trasladar: 'Guardar y trasladar',
        trasladar_sede: 'Guardar y trasladar de sede', reintegrar: 'Guardar y reintegrar',
        reportar_baja: 'Guardar y reportar baja', reactivar: 'Guardar y reactivar'
    };
    var confirmaciones = {
        reintegrar: '¿Guardar y reintegrar este bien? Saldrá de su espacio y quedará para el formato de reintegro.',
        reportar_baja: '¿Guardar y enviar el reporte de baja? Quedará pendiente de aprobación.',
        trasladar_sede: '¿Guardar y trasladar este bien a otra sede? Pasará a pertenecer a esa sede.'
    };

    function aplicar() {
        var accion = selector.value;
        formulario.querySelectorAll('[data-campos-accion]').forEach(function (bloque) {
            var visible = bloque.getAttribute('data-campos-accion').split(' ').indexOf(accion) !== -1;
            bloque.hidden = !visible;
        });
        formulario.querySelectorAll('#seccionAccion input:not([type=hidden]), #seccionAccion textarea, #seccionAccion select:not(#accionBien)').forEach(function (campo) {
            var oculto = campo.closest('[hidden]') !== null;
            campo.disabled = oculto;
            if (campo.tomselect) { oculto ? campo.tomselect.disable() : campo.tomselect.enable(); }
        });
        var espacioSede = document.getElementById('accionEspacioSede');
        if (espacioSede && !espacioSede.closest('[hidden]')) { espacioSede.disabled = espacioSede.options.length <= 1; }
        boton.textContent = textos[accion] || 'Guardar cambios';
        if (confirmaciones[accion]) { formulario.setAttribute('data-confirmar', confirmaciones[accion]); }
        else { formulario.removeAttribute('data-confirmar'); }
    }

    var sede = document.getElementById('accionSede');
    if (sede) {
        var espaciosPorSede = <?= json_encode($espaciosPorSedeDestino ?? []) ?>;
        var espacioSede = document.getElementById('accionEspacioSede');
        var elegido = <?= json_encode($va('accion_espacio_sede_id')) ?>;
        var llenar = function () {
            var espacios = espaciosPorSede[sede.value] || [];
            espacioSede.innerHTML = '';
            var primera = document.createElement('option');
            primera.value = '';
            primera.textContent = !sede.value ? '-- Primero selecciona una sede --' : (espacios.length ? '-- Selecciona --' : 'Esa sede no tiene espacios activos');
            espacioSede.appendChild(primera);
            espacios.forEach(function (e) {
                var opcion = document.createElement('option');
                opcion.value = e.id;
                opcion.textContent = e.codigo + ' - ' + e.nombre;
                opcion.selected = String(e.id) === String(elegido);
                espacioSede.appendChild(opcion);
            });
            espacioSede.disabled = espacios.length === 0;
        };
        sede.addEventListener('change', llenar);
        if (sede.value) { llenar(); }
    }

    selector.addEventListener('change', aplicar);
    // Tom Select se inicializa al cargar la página: se aplica después para poder habilitarlo.
    document.addEventListener('DOMContentLoaded', aplicar);
    aplicar();
})();
</script>
<?php endif; ?>

<?php if ($puedeEditar): ?>
<script>
(function () {
    const checkFactura = document.getElementById('tieneFactura');
    const contenedorFactura = document.getElementById('contenedorFactura');
    if (checkFactura && contenedorFactura) {
        checkFactura.addEventListener('change', function () {
            contenedorFactura.classList.toggle('d-none', !checkFactura.checked);
        });
    }
})();

// Las categorías ahora son propias de cada institución — cuando el superusuario elige
// (o cambia) la institución en este mismo formulario, el combo de categoría se llena
// por AJAX en vez de quedar fijo desde que cargó la página.
document.addEventListener('DOMContentLoaded', function () {
    const institucionSelect = document.getElementById('institucionBien');
    const categoriaSelect = document.getElementById('categoriaBien');
    if (!institucionSelect || !categoriaSelect) { return; }

    const categoriaIdPrevio = <?= json_encode((string) $v('categoria_id', '')) ?>;

    function actualizarCategorias(institucionId) {
        const tomCategoria = categoriaSelect.tomselect;
        if (!tomCategoria) { return; }

        tomCategoria.clear();
        tomCategoria.clearOptions();

        if (!institucionId) { return; }

        fetch(<?= json_encode(Url::to('/categorias/por-institucion')) ?> + '?institucion_id=' + encodeURIComponent(institucionId))
            .then(function (respuesta) { return respuesta.json(); })
            .then(function (datos) {
                (datos.categorias || []).forEach(function (c) {
                    tomCategoria.addOption({ value: String(c.id), text: c.nombre });
                });
                tomCategoria.refreshOptions(false);

                if (categoriaIdPrevio && (datos.categorias || []).some(function (c) { return String(c.id) === categoriaIdPrevio; })) {
                    tomCategoria.setValue(categoriaIdPrevio);
                }
            });
    }

    institucionSelect.addEventListener('change', function () {
        actualizarCategorias(institucionSelect.value);
    });

    if (institucionSelect.value) {
        actualizarCategorias(institucionSelect.value);
    }
});

<?php if (!$esEdicion): ?>
// Al registrar un bien nuevo se le pregunta al SERVIDOR, por el id de la categoría
// elegida, si corresponde sugerir un código (solo lo hace para "Sin cartera"). A
// propósito no se compara aquí el nombre de la categoría: Tom Select guarda el texto de
// un <option> renderizado por el servidor junto con los saltos de línea e indentación
// del HTML, así que esa comparación fallaba siempre y la consulta ni se disparaba.
//
// La sugerencia solo se aplica si el usuario todavía no escribió nada en el campo — y
// eso se sigue con una bandera propia, no mirando si el campo está vacío, porque el
// navegador puede restaurar ahí un valor viejo por su cuenta (de ahí también el
// autocomplete="off" del campo).
document.addEventListener('DOMContentLoaded', function () {
    const categoriaSelect = document.getElementById('categoriaBien');
    const codigoInput = document.getElementById('codigoIdentificacion');
    if (!categoriaSelect || !codigoInput) { return; }

    let editadoPorUsuario = codigoInput.value.trim() !== '';
    codigoInput.addEventListener('input', function () {
        editadoPorUsuario = true;
    });

    categoriaSelect.addEventListener('change', function () {
        const tomCategoria = categoriaSelect.tomselect;
        const categoriaId = tomCategoria ? tomCategoria.getValue() : categoriaSelect.value;
        if (!categoriaId || editadoPorUsuario) { return; }

        fetch(<?= json_encode(Url::to('/bienes/siguiente-codigo-sin-cartera')) ?> + '?categoria_id=' + encodeURIComponent(categoriaId))
            .then(function (respuesta) { return respuesta.json(); })
            .then(function (datos) {
                if (datos.codigo && !editadoPorUsuario) {
                    codigoInput.value = datos.codigo;
                }
            });
    });
});
<?php endif; ?>

<?php if ($esEdicion): ?>
// Al editar, cambiar la categoría a "Sin cartera" descarta el código actual (que pudo
// venir de otro esquema, ej. TEC-2024-045) y el servidor sugiere uno nuevo de 10 dígitos.
// Se avisa antes de aplicarlo porque, a diferencia de crear, aquí sí había un código
// previo con significado (posiblemente ya impreso en un QR físico).
document.addEventListener('DOMContentLoaded', function () {
    const categoriaSelect = document.getElementById('categoriaBien');
    const codigoInput = document.getElementById('codigoIdentificacion');
    if (!categoriaSelect || !codigoInput) { return; }

    const tomCategoria = categoriaSelect.tomselect;
    let categoriaAnterior = tomCategoria ? tomCategoria.getValue() : categoriaSelect.value;
    // Tom Select dispara "change" en el <select> oculto DOS VECES por cada elección real
    // (una vez por su propio sync interno, otra por el redisparo manual de
    // selector-buscable.js, que existe para que otras pantallas con su propio listener de
    // "change" sigan funcionando). Sin este candado, el fetch de abajo -y el confirm()-
    // se dispararían dos veces seguidas para la misma elección del usuario.
    let idEnProceso = null;

    categoriaSelect.addEventListener('change', function () {
        const tom = categoriaSelect.tomselect;
        const categoriaId = tom ? tom.getValue() : categoriaSelect.value;
        if (!categoriaId) { categoriaAnterior = categoriaId; idEnProceso = null; return; }
        if (categoriaId === idEnProceso) { return; }
        idEnProceso = categoriaId;

        fetch(<?= json_encode(Url::to('/bienes/siguiente-codigo-sin-cartera')) ?> + '?categoria_id=' + encodeURIComponent(categoriaId))
            .then(function (respuesta) { return respuesta.json(); })
            .then(function (datos) {
                idEnProceso = null;

                if (!datos.codigo) {
                    // No es "Sin cartera": no hay nada que confirmar.
                    categoriaAnterior = categoriaId;
                    return;
                }

                const codigoActual = codigoInput.value.trim();
                const aceptar = confirm(
                    'Vas a cambiar la categoría a "Sin cartera".\n\n' +
                    'El código actual (' + (codigoActual || 'sin código') + ') se perderá y se asignará ' +
                    'uno nuevo automáticamente: ' + datos.codigo + '.\n\n' +
                    '¿Deseas continuar?'
                );

                if (aceptar) {
                    codigoInput.value = datos.codigo;
                    categoriaAnterior = categoriaId;
                } else if (tom) {
                    tom.setValue(categoriaAnterior, true);
                } else {
                    categoriaSelect.value = categoriaAnterior;
                }

                // El confirm() nativo bloquea el hilo de JS mientras está abierto; al
                // cerrarse, Tom Select puede quedar con el buscador visible/enfocado
                // (clase "input-active") en vez de mostrar la opción elegida como
                // pastilla, aunque el menú de opciones ya esté cerrado.
                if (tom) {
                    tom.close();
                    tom.blur();
                }
            });
    });
});
<?php endif; ?>
</script>
<?php endif; ?>

<?php if ($esEdicion && !empty($historialMovimientos)): ?>
    <details class="mt-4" style="max-width: 720px;">
        <summary class="small fw-semibold text-muted">Ver historial de movimientos (<?= count($historialMovimientos) ?>)</summary>
        <div class="mt-2">
        <div class="table-responsive">
            <table class="table table-sm bg-white tabla-cards">
                <thead>
                <tr><th>Fecha</th><th>Tipo</th><th>Responsable</th><th>Destino</th><th>Observaciones</th></tr>
                </thead>
                <tbody>
                <?php foreach ($historialMovimientos as $m): ?>
                    <tr>
                        <td class="mono" data-label="Fecha"><?= htmlspecialchars($m['fecha'], ENT_QUOTES) ?></td>
                        <td data-label="Tipo"><span class="badge text-bg-light border text-capitalize"><?= htmlspecialchars($m['tipo'], ENT_QUOTES) ?></span></td>
                        <td data-label="Responsable"><?= htmlspecialchars($m['nombres'] . ' ' . $m['apellidos'], ENT_QUOTES) ?></td>
                        <td class="text-muted" data-label="Destino"><?= htmlspecialchars(!empty($m['persona_destino_nombre'])
                            ? 'A cargo de ' . $m['persona_destino_nombre'] . (!empty($m['espacio_destino_nombre']) ? ' (guardado en ' . $m['espacio_destino_nombre'] . ')' : '')
                            : ($m['espacio_destino_nombre'] ?? $m['destino_texto'] ?? '—'), ENT_QUOTES) ?></td>
                        <td class="text-muted small" data-label="Observaciones"><?= htmlspecialchars($m['observaciones'] ?? '', ENT_QUOTES) ?></td>
                    </tr>
                <?php endforeach; ?>
                </tbody>
            </table>
        </div>
        </div>
    </details>
<?php endif; ?>
<script src="<?= Url::asset('/assets/js/descripcion-categoria.js') ?>"></script>
