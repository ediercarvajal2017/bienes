<?php

use App\Core\Auth;
use App\Core\Url;

$etiquetasEstado = [
    'activo' => 'Activo',
    'reintegrado' => 'Reintegrado',
    'en_reparacion' => 'En reparación',
    'dado_de_baja' => 'Dado de baja',
];
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
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <title><?= htmlspecialchars($bien['descripcion'], ENT_QUOTES) ?> · SIGEBI</title>
    <link rel="icon" type="image/jpeg" href="<?= Url::asset('/assets/img/favicon.jpg') ?>">
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.3/dist/css/bootstrap.min.css" rel="stylesheet">
    <link href="https://cdn.jsdelivr.net/npm/bootstrap-icons@1.11.3/font/bootstrap-icons.css" rel="stylesheet">
    <link href="<?= Url::asset('/assets/css/app.css') ?>" rel="stylesheet">
</head>
<body>

<div class="auth-shell" style="align-items: flex-start; padding: 5vh 1rem;">
    <div class="auth-card position-relative" style="max-width: 420px;">
        <button type="button" id="btnTema" class="theme-toggle" aria-label="Cambiar tema" title="Cambiar tema">
            <i class="bi bi-moon-stars"></i>
        </button>
        <div class="brand mb-3"><i class="bi bi-tag-fill me-1"></i>SIGEBI</div>

        <?php if (!empty($bien['foto_path'])): ?>
            <img src="<?= Url::to('/archivos/' . $bien['foto_path']) ?>" class="d-block w-100 mb-3" style="border-radius:8px; max-height:220px; object-fit:cover;">
        <?php else: ?>
            <div class="d-flex align-items-center justify-content-center bg-light text-muted mb-3" style="height:140px;border-radius:8px;">
                <i class="bi bi-image" style="font-size:2rem;"></i>
            </div>
        <?php endif; ?>

        <h1 class="h5 mb-1"><?= htmlspecialchars($bien['descripcion'], ENT_QUOTES) ?></h1>
        <div class="mono text-muted mb-2"><?= htmlspecialchars($bien['codigo_identificacion'], ENT_QUOTES) ?></div>

        <span class="badge badge-estado-<?= htmlspecialchars($bien['estado'], ENT_QUOTES) ?> mb-3">
            <?= $etiquetasEstado[$bien['estado']] ?? $bien['estado'] ?>
        </span>

        <dl class="row small mb-0">
            <?php if (!empty($bien['marca'])): ?>
                <dt class="col-5 text-muted fw-normal">Marca</dt>
                <dd class="col-7"><?= htmlspecialchars($bien['marca'], ENT_QUOTES) ?></dd>
            <?php endif; ?>
            <?php if (!empty($bien['categoria_nombre'])): ?>
                <dt class="col-5 text-muted fw-normal">Categoría</dt>
                <dd class="col-7"><?= htmlspecialchars($bien['categoria_nombre'], ENT_QUOTES) ?></dd>
            <?php endif; ?>

            <dt class="col-5 text-muted fw-normal">Ubicación</dt>
            <dd class="col-7"><?= !empty($asignacion['espacio_nombre']) ? htmlspecialchars($asignacion['espacio_nombre'], ENT_QUOTES) : 'Sin asignar' ?></dd>

            <dt class="col-5 text-muted fw-normal">Responsable</dt>
            <dd class="col-7"><?= !empty($asignacion['responsables_nombres']) ? htmlspecialchars($asignacion['responsables_nombres'], ENT_QUOTES) : '—' ?></dd>

            <dt class="col-5 text-muted fw-normal">Institución</dt>
            <dd class="col-7"><?= htmlspecialchars($bien['institucion_nombre'], ENT_QUOTES) ?></dd>
        </dl>

        <hr>

        <?php if ($puedeGestionar): ?>
            <div class="d-grid gap-2">
                <a href="<?= Url::to('/bienes/' . $bien['id'] . '/editar') ?>" class="btn btn-primary btn-sm">
                    <i class="bi bi-gear me-1"></i>Gestionar este bien
                </a>
                <?php if ($puedeReportarBaja): ?>
                    <a href="<?= Url::to('/qr/' . $token . '/baja') ?>" class="btn btn-outline-danger btn-sm">
                        <i class="bi bi-exclamation-triangle me-1"></i>Reportar baja
                    </a>
                <?php endif; ?>
            </div>

            <?php if (!empty($puedeVerificar)): ?>
                <hr>
                <div class="small fw-semibold mb-2"><i class="bi bi-clipboard2-check me-1"></i>Verificación física en curso</div>

                <?php if (!empty($verificacionActual)): ?>
                    <?php if ($verificacionActual['resultado'] === 'ok'): ?>
                        <div class="alert alert-success py-2 small mb-2">
                            <i class="bi bi-check2-circle me-1"></i>Ya verificado por <?= htmlspecialchars($verificacionActual['nombres'] . ' ' . $verificacionActual['apellidos'], ENT_QUOTES) ?>.
                        </div>
                    <?php else: ?>
                        <div class="alert alert-warning py-2 small mb-2">
                            <i class="bi bi-exclamation-triangle me-1"></i>Discrepancia reportada por <?= htmlspecialchars($verificacionActual['nombres'] . ' ' . $verificacionActual['apellidos'], ENT_QUOTES) ?>: <?= htmlspecialchars($verificacionActual['observaciones'] ?? '', ENT_QUOTES) ?>
                        </div>
                    <?php endif; ?>
                    <p class="text-muted small mb-2">Si vuelves a verificar, se reemplaza el registro anterior.</p>
                <?php endif; ?>

                <form method="post" action="<?= Url::to('/qr/' . $token . '/verificar') ?>" enctype="multipart/form-data">
                    <?= \App\Core\Csrf::field() ?>
                    <input type="hidden" name="resultado" value="ok">
                    <?php if (empty($bien['foto_path'])): ?>
                        <label class="form-label small text-muted mb-1">Este bien no tiene foto — puedes tomarle una (opcional)</label>
                        <input type="file" name="foto" id="fotoVerificar" accept="image/*" capture="environment" class="form-control form-control-sm mb-2">
                    <?php endif; ?>
                    <button type="submit" class="btn btn-outline-success btn-sm w-100 mb-2">
                        <i class="bi bi-check2-circle me-1"></i>Confirmar: el bien está aquí
                    </button>
                </form>

                <button type="button" id="btnMostrarDiscrepancia" class="btn btn-outline-warning btn-sm w-100">
                    <i class="bi bi-exclamation-triangle me-1"></i>Reportar discrepancia
                </button>
                <form method="post" action="<?= Url::to('/qr/' . $token . '/verificar') ?>" id="formDiscrepancia" style="display:none;" class="mt-2">
                    <?= \App\Core\Csrf::field() ?>
                    <input type="hidden" name="resultado" value="discrepancia">
                    <label class="form-label small text-muted mb-1">¿Qué pasa con este bien?</label>
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
                    <button type="submit" class="btn btn-warning btn-sm w-100">Enviar discrepancia</button>
                </form>
                <script>
                document.getElementById('btnMostrarDiscrepancia').addEventListener('click', function () {
                    this.style.display = 'none';
                    document.getElementById('formDiscrepancia').style.display = 'block';
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
            <a href="<?= Url::to('/login') ?>" class="btn btn-outline-secondary btn-sm w-100">
                <i class="bi bi-box-arrow-in-right me-1"></i>Iniciar sesión para gestionar
            </a>
        <?php endif; ?>

        <?php if (Auth::check()): ?>
            <a href="<?= Url::to('/escanear') ?>" class="btn btn-link btn-sm w-100 mt-2">
                <i class="bi bi-qr-code-scan me-1"></i>Volver a escanear
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
