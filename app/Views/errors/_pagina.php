<?php
/**
 * Esqueleto común de las páginas de error (403, 404, 500, mantenimiento). Es una página
 * independiente del layout principal a propósito: no toca la sesión ni la base de datos,
 * para poder mostrarse aunque el error venga justamente de ahí.
 *
 * Variables: $codigo, $titulo, $mensaje, $enlace (ruta), $textoEnlace, $incidente (opcional).
 */

use App\Core\Url;

$incidente ??= null;
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
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <meta name="robots" content="noindex">
    <title><?= htmlspecialchars($codigo . ' · SIGEBI', ENT_QUOTES) ?></title>
    <link rel="icon" type="image/jpeg" href="<?= Url::asset('/assets/img/favicon.jpg') ?>">
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.3/dist/css/bootstrap.min.css" integrity="sha384-QWTKZyjpPEjISv5WaRU9OFeRpok6YctnYmDr5pNlyT2bRjXh0JMhjY6hW+ALEwIH" crossorigin="anonymous" rel="stylesheet">
    <link href="https://cdn.jsdelivr.net/npm/bootstrap-icons@1.11.3/font/bootstrap-icons.min.css" integrity="sha384-XGjxtQfXaH2tnPFa9x+ruJTuLE3Aa6LhHSWRr1XeTyhezb4abCG4ccI5AkVDxqC+" crossorigin="anonymous" rel="stylesheet">
    <link href="<?= Url::asset('/assets/css/app.css') ?>" rel="stylesheet">
</head>
<body class="d-flex align-items-center justify-content-center min-vh-100 p-3">
    <main class="card shadow-sm text-center p-4" style="max-width: 440px; width: 100%;">
        <img src="<?= Url::asset('/assets/img/logo.png') ?>" alt="SIGEBI" class="mx-auto mb-3" style="height: 56px; width: auto;">
        <p class="display-6 fw-semibold mb-1"><?= htmlspecialchars((string) $codigo, ENT_QUOTES) ?></p>
        <h1 class="h5 mb-2"><?= htmlspecialchars($titulo, ENT_QUOTES) ?></h1>
        <p class="text-body-secondary mb-3"><?= htmlspecialchars($mensaje, ENT_QUOTES) ?></p>
        <?php if ($incidente): ?>
            <p class="small mb-3">
                Código de incidente: <code class="user-select-all"><?= htmlspecialchars($incidente, ENT_QUOTES) ?></code><br>
                <span class="text-body-secondary">Compártalo con soporte para ubicar el problema.</span>
            </p>
        <?php endif; ?>
        <?php if ($enlace !== ''): ?>
            <a href="<?= Url::to($enlace) ?>" class="btn btn-primary w-100">
                <i class="bi bi-arrow-left" aria-hidden="true"></i> <?= htmlspecialchars($textoEnlace, ENT_QUOTES) ?>
            </a>
        <?php endif; ?>
    </main>
</body>
</html>
