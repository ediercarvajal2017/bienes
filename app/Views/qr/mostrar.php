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
            var tema = guardado || (window.matchMedia('(prefers-color-scheme: dark)').matches ? 'dark' : 'light');
            document.documentElement.setAttribute('data-bs-theme', tema);
        } catch (e) {}
    })();
    </script>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <title><?= htmlspecialchars($bien['descripcion'], ENT_QUOTES) ?> · SIGEBI</title>
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
        <?php elseif (Auth::check()): ?>
            <p class="small text-muted mb-0">Este bien pertenece a otra institución; no puedes gestionarlo desde tu cuenta.</p>
        <?php else: ?>
            <a href="<?= Url::to('/login') ?>" class="btn btn-outline-secondary btn-sm w-100">
                <i class="bi bi-box-arrow-in-right me-1"></i>Iniciar sesión para gestionar
            </a>
        <?php endif; ?>
    </div>
</div>

<script src="<?= Url::asset('/assets/js/tema.js') ?>"></script>
</body>
</html>
