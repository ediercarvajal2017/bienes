<?php

use App\Core\Csrf;
use App\Core\Url;

$e = static fn (string $t): string => htmlspecialchars($t, ENT_QUOTES);
$primerNombre = explode(' ', trim($nombre))[0] ?? '';
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
        <div class="d-flex align-items-center gap-3 mb-2">
            <img src="<?= Url::asset('/assets/img/logo.webp') ?>" width="400" height="400" alt="MIA" class="auth-logo-mini">
            <h1 class="h5 mb-0">Antes de continuar</h1>
        </div>
        <p class="small mb-3">
            Hola<?= $primerNombre !== '' ? ', ' . $e($primerNombre) : '' ?>. Para usar MIA debes leer y aceptar la
            política de tratamiento de datos personales y los términos de uso. Solo se pide una vez.
        </p>

        <?php if (!empty($error)): ?>
            <div class="alert alert-danger py-2 small" role="alert"><?= $e($error) ?></div>
        <?php endif; ?>

        <div class="caja-texto-legal mb-3" tabindex="0" role="region" aria-label="Texto de la política de datos y términos de uso">
            <?php require __DIR__ . '/_texto.php'; ?>
        </div>

        <form method="post" action="<?= Url::to('/politica/aceptar') ?>">
            <?= Csrf::field() ?>
            <div class="form-check mb-3">
                <input type="checkbox" name="acepto" value="1" id="acepto" class="form-check-input" required>
                <label class="form-check-label small" for="acepto">
                    He leído y acepto la política de tratamiento de datos personales y los términos de uso de MIA, y
                    autorizo el tratamiento de mis datos para las finalidades descritas.
                </label>
            </div>
            <button type="submit" class="btn btn-primary w-100">Aceptar y continuar</button>
        </form>
        <form method="post" action="<?= Url::to('/logout') ?>" class="text-center mt-2">
            <?= Csrf::field() ?>
            <button type="submit" class="btn btn-link btn-sm text-muted">Salir sin aceptar</button>
        </form>
    </main>
</div>

<script src="<?= Url::asset('/assets/js/tema.js') ?>"></script>
<script src="<?= Url::asset('/assets/js/cargando.js') ?>"></script>
</body>
</html>
