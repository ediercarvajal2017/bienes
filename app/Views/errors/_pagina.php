<?php
/**
 * Esqueleto común de las páginas de error (403, 404, 500, mantenimiento). Es una página
 * independiente del layout principal a propósito: no toca la sesión ni la base de datos,
 * para poder mostrarse aunque el error venga justamente de ahí.
 *
 * Variables: $codigo, $titulo (qué pasó), $mensaje (por qué), $causas (list<string>, por qué
 * puede pasar), $queHacer, $enlace (ruta), $textoEnlace, $incidente (opcional). Misma
 * redacción que los avisos (docs/guia-estilo-ui.md, "Mensajes").
 */

use App\Core\Url;

$incidente ??= null;
$causas ??= [];
$queHacer ??= '';
$enlace ??= '/dashboard';
$textoEnlace ??= 'Volver al panel';
?>
<!DOCTYPE html>
<html lang="es">
<head>
    <script>
    (function () {
        try {
            var guardado = localStorage.getItem('sigebi-theme');
            document.documentElement.setAttribute('data-bs-theme', guardado || 'dark');
        } catch (e) {}
    })();
    </script>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1, viewport-fit=cover">
    <meta name="robots" content="noindex">
    <title><?= htmlspecialchars($codigo . ' · MIA', ENT_QUOTES) ?></title>
    <link rel="icon" type="image/png" sizes="32x32" href="<?= Url::asset('/assets/img/favicon-32.png') ?>">
    <?php \App\Core\View::render('partials/ui/hojas_estilo') ?>
</head>
<body class="d-flex align-items-center justify-content-center min-vh-100 p-3">
    <main class="card text-center p-4 contenedor-sm w-100">
        <img src="<?= Url::asset('/assets/img/logo.webp') ?>" width="400" height="400" alt="MIA" class="mx-auto mb-3 logo-56">
        <p class="display-6 fw-semibold mb-1"><?= htmlspecialchars((string) $codigo, ENT_QUOTES) ?></p>
        <h1 class="h5 mb-2"><?= htmlspecialchars($titulo, ENT_QUOTES) ?></h1>
        <p class="text-body-secondary mb-3"><?= htmlspecialchars($mensaje, ENT_QUOTES) ?></p>
        <?php if ($causas !== []): ?>
            <div class="text-start small mb-3">
                <p class="mb-1 fw-semibold">Puede pasar porque:</p>
                <ul class="mb-0 ps-3">
                    <?php foreach ($causas as $causa): ?><li><?= htmlspecialchars($causa, ENT_QUOTES) ?></li><?php endforeach; ?>
                </ul>
            </div>
        <?php endif; ?>
        <?php if ($queHacer !== ''): ?>
            <p class="small text-start mb-3"><strong>Qué hacer:</strong> <?= htmlspecialchars($queHacer, ENT_QUOTES) ?></p>
        <?php endif; ?>
        <?php if ($incidente): ?>
            <p class="small mb-3">
                Código de incidente: <code class="user-select-all"><?= htmlspecialchars($incidente, ENT_QUOTES) ?></code><br>
                <span class="text-body-secondary">Compártelo con soporte para ubicar el problema.</span>
            </p>
        <?php endif; ?>
        <?php if ($enlace !== ''): ?>
            <a href="<?= Url::to($enlace) ?>" class="btn btn-primary w-100">
                <?= icono('volver') ?> <?= htmlspecialchars($textoEnlace, ENT_QUOTES) ?>
            </a>
        <?php endif; ?>
    </main>
</body>
</html>
