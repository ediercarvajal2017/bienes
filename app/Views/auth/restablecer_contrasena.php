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
    <title>Restablecer contraseña · MIA</title>
    <link rel="icon" type="image/png" sizes="32x32" href="<?= Url::asset('/assets/img/favicon-32.png') ?>">
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.3/dist/css/bootstrap.min.css" integrity="sha384-QWTKZyjpPEjISv5WaRU9OFeRpok6YctnYmDr5pNlyT2bRjXh0JMhjY6hW+ALEwIH" crossorigin="anonymous" rel="stylesheet">
    <link href="https://cdn.jsdelivr.net/npm/bootstrap-icons@1.11.3/font/bootstrap-icons.css" integrity="sha384-tViUnnbYAV00FLIhhi3v/dWt3Jxw4gZQcNoSCxCIFNJVCx7/D55/wXsrNIRANwdD" crossorigin="anonymous" rel="stylesheet">
    <link href="<?= Url::asset('/assets/css/app.css') ?>" rel="stylesheet">
</head>
<body>

<div class="auth-shell">
    <div class="auth-card position-relative">
        <button type="button" id="btnTema" class="theme-toggle" aria-label="Cambiar tema" title="Cambiar tema">
            <i class="bi bi-moon-stars"></i>
        </button>
        <div class="text-center"><img src="<?= Url::asset('/assets/img/logo.webp') ?>" width="400" height="400" alt="MIA" style="height: 72px; width: auto;"></div>
        <div class="brand-sub">Restablecer contraseña</div>

        <?php if (!empty($error)): ?>
            <div class="alert alert-danger py-2 small"><?= htmlspecialchars($error, ENT_QUOTES) ?></div>
        <?php endif; ?>

        <?php if (!$valido): ?>
            <p class="small text-muted">Este enlace ya no es válido — puede que haya expirado (dura 60 minutos) o que ya se haya usado.</p>
            <a href="<?= Url::to('/olvide-contrasena') ?>" class="btn btn-primary w-100">Solicitar un enlace nuevo</a>
        <?php else: ?>
            <form method="post" action="<?= Url::to('/restablecer-contrasena/' . $token) ?>">
                <?= \App\Core\Csrf::field() ?>
                <div class="mb-3">
                    <label class="form-label small" for="password">Nueva contraseña</label>
                    <input type="password" name="password" id="password" class="form-control" required minlength="10" autofocus autocomplete="new-password">
                    <div class="form-text">Mínimo 10 caracteres, con letras y números. No use su nombre, documento ni correo.</div>
                </div>
                <div class="mb-3">
                    <label class="form-label small" for="password_confirmacion">Confirmar contraseña</label>
                    <input type="password" name="password_confirmacion" id="password_confirmacion" class="form-control" required minlength="10" autocomplete="new-password">
                </div>
                <button type="submit" class="btn btn-primary w-100">Guardar contraseña</button>
            </form>
        <?php endif; ?>

        <div class="text-center mt-3">
            <a href="<?= Url::to('/login') ?>" class="small">Volver a iniciar sesión</a>
        </div>
    </div>
</div>

<script src="<?= Url::asset('/assets/js/tema.js') ?>"></script>
<script src="<?= Url::asset('/assets/js/cargando.js') ?>"></script>
<script src="<?= Url::asset('/assets/js/alertas.js') ?>"></script>
<script src="<?= Url::asset('/assets/js/mostrar-contrasena.js') ?>"></script>
</body>
</html>
