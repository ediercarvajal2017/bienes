<?php

use App\Core\Auth;
use App\Core\Url;

$qrConfirmado = !empty($bien['qr_confirmado_en']);
$qrImpreso = !empty($bien['qr_impreso_en']);
?>
<!DOCTYPE html>
<html lang="es">
<head>
    <script>
    (function () {
        try {
            var guardado = localStorage.getItem('sigebi-theme');
            var tema = guardado || 'dark';
            document.documentElement.setAttribute('data-bs-theme', tema);
        } catch (e) {}
    })();
    </script>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1, viewport-fit=cover">
    <title><?= htmlspecialchars($bien['descripcion'], ENT_QUOTES) ?> · MIA</title>
    <link rel="icon" type="image/png" sizes="32x32" href="<?= Url::asset('/assets/img/favicon-32.png') ?>">
    <?php \App\Core\View::render('partials/ui/hojas_estilo') ?>
</head>
<body>

<div class="auth-shell auth-shell-ficha">
    <div class="auth-card position-relative contenedor-sm">
        <button type="button" id="btnTema" class="theme-toggle" aria-label="Cambiar tema" title="Cambiar tema">
            <?= icono('tema') ?>
        </button>
        <div class="text-center mb-3"><img src="<?= Url::asset('/assets/img/logo.webp') ?>" width="400" height="400" alt="MIA" class="auth-logo-72"></div>

        <?php if (!empty($mensaje)): ?>
            <?= ui('alerta', ['tipo' => 'exito', 'mensaje' => (string) $mensaje, 'clases' => 'py-2 small']) ?>
        <?php endif; ?>
        <?php if (!empty($error)): ?>
            <?= ui('alerta', ['tipo' => 'peligro', 'mensaje' => (string) $error, 'clases' => 'py-2 small']) ?>
        <?php endif; ?>

        <?php if (!empty($bien['foto_path'])): ?>
            <img src="<?= \App\Helpers\EnlaceArchivo::url($bien['foto_path']) ?>"
                 alt="Foto de <?= htmlspecialchars($bien['descripcion'], ENT_QUOTES) ?>"
                 class="d-block w-100 mb-3 foto-ficha">
        <?php else: ?>
            <div class="foto-ficha-vacia mb-3"><?= icono('foto') ?><span class="visually-hidden">Sin foto</span></div>
        <?php endif; ?>

        <h1 class="h5 mb-1"><?= htmlspecialchars($bien['descripcion'], ENT_QUOTES) ?></h1>
        <div class="mono text-muted mb-2"><?= htmlspecialchars($bien['codigo_identificacion'], ENT_QUOTES) ?></div>

        <?= ui('insignia_estado', ['tipo' => 'bien', 'estado' => (string) $bien['estado'], 'clases' => 'mb-3']) ?>

        <?php if ($puedeGestionar): ?>
            <?php if ($qrConfirmado): ?>
                <span class="badge badge-exito mb-3">
                    <?= icono('exito', 'me-1') ?>Etiqueta confirmada
                </span>
            <?php elseif ($qrImpreso): ?>
                <span class="badge badge-advertencia mb-3">
                    <?= icono('advertencia', 'me-1') ?>Etiqueta impresa, sin confirmar
                </span>
            <?php else: ?>
                <span class="badge badge-neutro mb-3">
                    <?= icono('imprimir', 'me-1') ?>QR sin imprimir todavía
                </span>
            <?php endif; ?>
        <?php endif; ?>

        <dl class="row small mb-0">
            <?php if (!empty($bien['marca'])): ?>
                <dt class="col-5 text-muted fw-normal">Marca</dt>
                <dd class="col-7"><?= htmlspecialchars($bien['marca'], ENT_QUOTES) ?></dd>
            <?php endif; ?>
            <?php if (!empty($bien['categoria_nombre'])): ?>
                <dt class="col-5 text-muted fw-normal">Categoría</dt>
                <dd class="col-7"><?= htmlspecialchars($bien['categoria_nombre'], ENT_QUOTES) ?></dd>
            <?php endif; ?>

            <?php $esIndividual = ($asignacion['tipo_responsabilidad'] ?? null) === 'individual'; ?>
            <dt class="col-5 text-muted fw-normal">Responsabilidad</dt>
            <dd class="col-7"><?= $asignacion ? ($esIndividual ? 'Individual' : 'Grupal') : 'Sin asignar' ?></dd>

            <dt class="col-5 text-muted fw-normal"><?= $esIndividual ? 'Guardado en' : 'Ubicación' ?></dt>
            <dd class="col-7"><?= !empty($asignacion['espacio_nombre']) ? htmlspecialchars($asignacion['espacio_nombre'], ENT_QUOTES) : ($esIndividual ? '—' : 'Sin asignar') ?></dd>

            <dt class="col-5 text-muted fw-normal"><?= $esIndividual ? 'A cargo de' : 'Responsable' ?></dt>
            <dd class="col-7"><?= !empty($asignacion['responsables_nombres']) ? htmlspecialchars($asignacion['responsables_nombres'], ENT_QUOTES) : '—' ?></dd>

            <dt class="col-5 text-muted fw-normal">Institución</dt>
            <dd class="col-7"><?= htmlspecialchars($bien['institucion_nombre'], ENT_QUOTES) ?></dd>
        </dl>

        <hr>

        <?php if ($puedeGestionar): ?>
            <div class="d-grid gap-2">
                <a href="<?= Url::to('/bienes/' . $bien['id'] . '/editar') ?>" class="btn btn-primary btn-sm">
                    <?= icono('editar', 'me-1') ?>Gestionar este bien
                </a>
                <?php if ($puedeReportarBaja): ?>
                    <a href="<?= Url::to('/qr/' . $token . '/baja') ?>" class="btn btn-outline-danger btn-sm">
                        <?= icono('baja', 'me-1') ?>Reportar baja
                    </a>
                <?php endif; ?>
                <?php if (!empty($puedeSolicitarReintegro)): ?>
                    <a href="<?= Url::to('/qr/' . $token . '/solicitar-reintegro') ?>" class="btn btn-outline-secondary btn-sm">
                        <?= icono('reintegro', 'me-1') ?>Solicitar reintegro
                    </a>
                <?php elseif (!empty($solicitudReintegroPendiente)): ?>
                    <div class="small text-muted text-center">
                        <?= icono('pendiente', 'me-1') ?>Tiene una solicitud de reintegro pendiente.
                    </div>
                <?php endif; ?>
            </div>

            <?php if (!$qrImpreso): ?>
                <p class="text-muted small mt-2 mb-0">
                    <?= icono('imprimir', 'me-1') ?>
                    Todavía no se ha generado la etiqueta de este bien. Ve a
                    <a href="<?= Url::to('/bienes/qr-masivo') ?>">Generar QR masivo</a> para imprimirla —
                    una vez impresa, podrás confirmarla aquí.
                </p>
            <?php elseif (!$qrConfirmado): ?>
                <form method="post" action="<?= Url::to('/qr/' . $token . '/confirmar-etiqueta') ?>" class="mt-2">
                    <?= \App\Core\Csrf::field() ?>
                    <button type="submit" class="btn btn-outline-secondary btn-sm w-100">
                        <?= icono('guardar', 'me-1') ?>Confirmar: esta etiqueta corresponde a este bien
                    </button>
                </form>
                <p class="text-muted small mt-1 mb-0">
                    ¿Acabas de pegar esta etiqueta? Si lo que ves aquí coincide con el bien que tienes
                    enfrente, confírmalo — así queda registrado que quedó en el lugar correcto.
                </p>
            <?php else: ?>
                <p class="text-muted small mt-2 mb-0">
                    <?= icono('exito', 'text-success me-1') ?>
                    Confirmada por <?= htmlspecialchars(trim((string) ($bien['qr_confirmado_por_nombre'] ?? '')) ?: '—', ENT_QUOTES) ?>
                    el <?= htmlspecialchars((string) $bien['qr_confirmado_en'], ENT_QUOTES) ?>
                </p>
            <?php endif; ?>

            <?php if (!empty($puedeVerificar)): ?>
                <hr>
                <div class="small fw-semibold mb-2"><?= icono('verificacion', 'me-1') ?>Verificación física en curso</div>

                <?php if (!empty($verificacionActual)): ?>
                    <?php if ($verificacionActual['resultado'] === 'ok'): ?>
                        <div class="alert alert-success py-2 small mb-2">
                            <?= icono('exito', 'me-1') ?>Ya verificado por <?= htmlspecialchars($verificacionActual['nombres'] . ' ' . $verificacionActual['apellidos'], ENT_QUOTES) ?>.
                        </div>
                    <?php else: ?>
                        <div class="alert alert-warning py-2 small mb-2">
                            <?= icono('advertencia', 'me-1') ?>Discrepancia reportada por <?= htmlspecialchars($verificacionActual['nombres'] . ' ' . $verificacionActual['apellidos'], ENT_QUOTES) ?>: <?= htmlspecialchars($verificacionActual['observaciones'] ?? '', ENT_QUOTES) ?>
                        </div>
                    <?php endif; ?>
                    <p class="text-muted small mb-2">Si vuelves a verificar, se reemplaza el registro anterior.</p>
                <?php endif; ?>

                <form method="post" action="<?= Url::to('/qr/' . $token . '/verificar') ?>" enctype="multipart/form-data">
                    <?= \App\Core\Csrf::field() ?>
                    <input type="hidden" name="resultado" value="ok">
                    <?php if (empty($bien['foto_path'])): ?>
                        <label for="fotoVerificar" class="form-label small text-muted mb-1">Este bien no tiene foto — puedes tomarle una (opcional)</label>
                        <input type="file" name="foto" id="fotoVerificar" accept="image/*" capture="environment" class="form-control form-control-sm mb-2">
                    <?php endif; ?>
                    <button type="submit" class="btn btn-outline-secondary btn-sm w-100 mb-2">
                        <?= icono('guardar', 'me-1') ?>Confirmar: el bien está aquí
                    </button>
                </form>

                <button type="button" id="btnMostrarDiscrepancia" class="btn btn-outline-secondary btn-sm w-100">
                    <?= icono('advertencia', 'me-1') ?>Reportar discrepancia
                </button>
                <form method="post" action="<?= Url::to('/qr/' . $token . '/verificar') ?>" id="formDiscrepancia" class="mt-2" hidden>
                    <?= \App\Core\Csrf::field() ?>
                    <input type="hidden" name="resultado" value="discrepancia">
                    <label for="motivoDiscrepancia" class="form-label small text-muted mb-1">¿Qué pasa con este bien?</label>
                    <select name="motivo" id="motivoDiscrepancia" class="form-select form-select-sm mb-2" required>
                        <option value="">-- Selecciona --</option>
                        <option value="no_se_encuentra">No se encuentra</option>
                        <option value="otra_ubicacion">Está en otro salón</option>
                        <option value="danado">Dañado</option>
                        <option value="responsable_incorrecto">El responsable no es el correcto</option>
                        <option value="otro">Otro</option>
                    </select>
                    <textarea name="observaciones" id="observacionesDiscrepancia" class="form-control form-control-sm mb-2" rows="2"
                              placeholder="Detalle adicional (opcional)"></textarea>
                    <button type="submit" class="btn btn-primary btn-sm w-100">Enviar discrepancia</button>
                </form>
                <script>
                document.getElementById('btnMostrarDiscrepancia').addEventListener('click', function () {
                    this.hidden = true;
                    document.getElementById('formDiscrepancia').hidden = false;
                });

                // Si el motivo es "Otro", el detalle deja de ser opcional — sin un motivo
                // predefinido, el texto es la unica pista de que se trata.
                document.getElementById('motivoDiscrepancia').addEventListener('change', function () {
                    const observaciones = document.getElementById('observacionesDiscrepancia');
                    const esOtro = this.value === 'otro';
                    observaciones.required = esOtro;
                    observaciones.placeholder = esOtro ? 'Describe brevemente la discrepancia' : 'Detalle adicional (opcional)';
                });
                </script>
            <?php endif; ?>
        <?php elseif (Auth::check()): ?>
            <p class="small text-muted mb-0">Este bien pertenece a otra institución; no puedes gestionarlo desde tu cuenta.</p>
        <?php else: ?>
            <a href="<?= Url::to('/login') ?>" class="btn btn-primary btn-sm w-100">
                <?= icono('entrar', 'me-1') ?>Iniciar sesión para gestionar
            </a>
        <?php endif; ?>

        <?php if (Auth::check()): ?>
            <a href="<?= Url::to('/escanear') ?>" class="btn btn-link btn-sm w-100 mt-2">
                <?= icono('escanear', 'me-1') ?>Volver a escanear
            </a>
        <?php endif; ?>
    </div>
</div>

<script src="<?= Url::asset('/assets/js/tema.js') ?>"></script>
<script src="<?= Url::asset('/assets/js/camara.js') ?>"></script>
<script>
(function () {
    var input = document.getElementById('fotoVerificar');
    if (!input || !window.SigebiFoto) { return; }
    input.addEventListener('change', async function () {
        var archivo = input.files && input.files[0];
        if (!archivo) { return; }
        var comprimido = await window.SigebiFoto.comprimir(archivo);
        if (comprimido !== archivo) {
            var lista = new DataTransfer();
            lista.items.add(comprimido);
            input.files = lista.files;
        }
    });
})();
</script>
</body>
</html>
