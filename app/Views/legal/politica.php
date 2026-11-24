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
    <?php \App\Core\View::render('partials/ui/hojas_estilo') ?>
</head>
<body>

<div class="auth-shell auth-shell-legal">
    <main class="auth-card auth-card-ancha position-relative">
        <button type="button" id="btnTema" class="theme-toggle" aria-label="Cambiar tema" title="Cambiar tema">
            <?= icono('tema') ?>
        </button>
        <div class="d-flex align-items-center gap-3 mb-3 titulo-legal">
            <img src="<?= Url::asset('/assets/img/logo.webp') ?>" width="400" height="400" alt="MIA" class="auth-logo-mini">
            <div class="d-flex align-items-center gap-1"><h1 class="h5 mb-0">Política de tratamiento de datos personales y términos de uso</h1><?= \App\Helpers\AyudaCampo::boton('politica_datos') ?></div>
        </div>

        <?php require __DIR__ . '/_texto.php'; ?>

        <div class="border-top pt-3 mt-3">
            <?php if ($conSesion): ?>
                <a href="<?= Url::to('/dashboard') ?>" class="btn btn-outline-secondary btn-sm"><?= icono('volver', 'me-1') ?>Volver a MIA</a>
            <?php else: ?>
                <a href="<?= Url::to('/login') ?>" class="btn btn-outline-secondary btn-sm"><?= icono('volver', 'me-1') ?>Ir a iniciar sesión</a>
            <?php endif; ?>
        </div>
    </main>
</div>

<script src="<?= Url::asset('/assets/js/tema.js') ?>"></script>
<script src="<?= Url::asset('/assets/js/ayuda-campo.js') ?>"></script>
</body>
</html>
