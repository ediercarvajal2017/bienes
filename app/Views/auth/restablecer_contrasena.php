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
    <title>Restablecer contraseña · SIGEBI</title>
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
        <div class="brand"><i class="bi bi-tag-fill me-1"></i>SIGEBI</div>
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
                    <label class="form-label small">Nueva contraseña</label>
                    <input type="password" name="password" class="form-control" required minlength="8" autofocus>
                </div>
                <div class="mb-3">
                    <label class="form-label small">Confirmar contraseña</label>
                    <input type="password" name="password_confirmacion" class="form-control" required minlength="8">
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
</body>
</html>
