<?php use App\Core\Url; ?>
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
    <title>Política de datos · MIA</title>
    <link rel="icon" type="image/png" sizes="32x32" href="<?= Url::asset('/assets/img/favicon-32.png') ?>">
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.3/dist/css/bootstrap.min.css" integrity="sha384-QWTKZyjpPEjISv5WaRU9OFeRpok6YctnYmDr5pNlyT2bRjXh0JMhjY6hW+ALEwIH" crossorigin="anonymous" rel="stylesheet">
    <link href="https://cdn.jsdelivr.net/npm/bootstrap-icons@1.11.3/font/bootstrap-icons.css" integrity="sha384-tViUnnbYAV00FLIhhi3v/dWt3Jxw4gZQcNoSCxCIFNJVCx7/D55/wXsrNIRANwdD" crossorigin="anonymous" rel="stylesheet">
    <link href="<?= Url::asset('/assets/css/app.css') ?>" rel="stylesheet">
</head>
<body>

<div class="auth-shell auth-shell-legal">
    <main class="auth-card auth-card-ancha position-relative">
        <button type="button" id="btnTema" class="theme-toggle" aria-label="Cambiar tema" title="Cambiar tema">
            <i class="bi bi-moon-stars"></i>
        </button>
        <div class="d-flex align-items-center gap-3 mb-3">
            <img src="<?= Url::asset('/assets/img/logo.webp') ?>" width="400" height="400" alt="MIA" class="auth-logo-mini">
            <h1 class="h5 mb-0">Política de tratamiento de datos personales y términos de uso</h1>
        </div>

        <?php require __DIR__ . '/_texto.php'; ?>

        <div class="border-top pt-3 mt-3">
            <?php if ($conSesion): ?>
                <a href="<?= Url::to('/dashboard') ?>" class="btn btn-outline-primary btn-sm"><i class="bi bi-arrow-left me-1" aria-hidden="true"></i>Volver a MIA</a>
            <?php else: ?>
                <a href="<?= Url::to('/login') ?>" class="btn btn-outline-primary btn-sm"><i class="bi bi-arrow-left me-1" aria-hidden="true"></i>Ir a iniciar sesión</a>
            <?php endif; ?>
        </div>
    </main>
</div>

<script src="<?= Url::asset('/assets/js/tema.js') ?>"></script>
</body>
</html>
