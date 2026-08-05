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
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <title>Olvidé mi contraseña · SIGEBI</title>
    <link rel="icon" type="image/jpeg" href="<?= Url::asset('/assets/img/favicon.jpg') ?>">
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.3/dist/css/bootstrap.min.css" rel="stylesheet">
    <link href="https://cdn.jsdelivr.net/npm/bootstrap-icons@1.11.3/font/bootstrap-icons.css" rel="stylesheet">
    <link href="<?= Url::asset('/assets/css/app.css') ?>" rel="stylesheet">
</head>
<body>

<div class="auth-shell">
    <div class="auth-card position-relative">
        <button type="button" id="btnTema" class="theme-toggle" aria-label="Cambiar tema" title="Cambiar tema">
            <i class="bi bi-moon-stars"></i>
        </button>
        <div class="text-center mb-2">
            <img src="<?= Url::asset('/assets/img/logo.png') ?>" alt="SIGEBI" class="auth-logo">
        </div>
        <div class="brand-sub text-center">Recuperar contraseña</div>

        <?php if (!empty($mensaje)): ?>
            <div class="alert alert-success py-2 small"><?= htmlspecialchars($mensaje, ENT_QUOTES) ?></div>
        <?php endif; ?>
        <?php if (!empty($error)): ?>
            <div class="alert alert-danger py-2 small"><?= htmlspecialchars($error, ENT_QUOTES) ?></div>
        <?php endif; ?>

        <p class="small text-muted">Escribe el correo con el que inicias sesión y te enviaremos un enlace para crear una nueva contraseña.</p>

        <form method="post" action="<?= Url::to('/olvide-contrasena') ?>">
            <?= \App\Core\Csrf::field() ?>
            <div class="mb-3">
                <label class="form-label small">Correo institucional</label>
                <input type="email" name="email" class="form-control" required autofocus>
            </div>
            <button type="submit" class="btn btn-primary w-100">Enviar enlace</button>
        </form>

        <div class="text-center mt-3">
            <a href="<?= Url::to('/login') ?>" class="small">Volver a iniciar sesión</a>
        </div>
    </div>
</div>

<script src="<?= Url::asset('/assets/js/tema.js') ?>"></script>
<script src="<?= Url::asset('/assets/js/alertas.js') ?>"></script>
</body>
</html>
