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
    <title>Verificación en dos pasos · MIA</title>
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
        <div class="text-center mb-2">
            <img src="<?= Url::asset('/assets/img/logo.webp') ?>" width="400" height="400" alt="MIA" class="auth-logo">
        </div>
        <h1 class="h5 text-center mb-1"><i class="bi bi-shield-lock me-1" aria-hidden="true"></i>Verificación en dos pasos</h1>
        <p class="text-muted small text-center mb-3">
            <?= $appDisponible
                ? 'Abre tu aplicación autenticadora y escribe el código de 6 dígitos de MIA.'
                : 'Escribe uno de tus códigos de recuperación.' ?>
        </p>

        <?php if (!$appDisponible): ?>
            <div class="alert alert-warning py-2 small">
                Los códigos de la aplicación no están disponibles en este momento. Usa un código de
                recuperación o pide a un administrador que restablezca tu verificación en dos pasos.
            </div>
        <?php endif; ?>

        <?php if (!empty($error)): ?>
            <div class="alert alert-danger py-2 small" role="alert">
                <?= htmlspecialchars($error, ENT_QUOTES) ?>
                <?php if ($intentosRestantes < 5): ?>
                    <div class="mt-1">Intentos restantes: <?= (int) $intentosRestantes ?>.</div>
                <?php endif; ?>
            </div>
        <?php endif; ?>

        <form method="post" action="<?= Url::to('/2fa/verificar') ?>" id="formCodigo">
            <?= \App\Core\Csrf::field() ?>
            <div class="mb-3">
                <label class="form-label small" for="codigo">Código de verificación</label>
                <input type="text" name="codigo" id="codigo" class="form-control form-control-lg text-center campo-codigo-2fa"
                       required autofocus autocomplete="one-time-code" inputmode="<?= $appDisponible ? 'numeric' : 'text' ?>"
                       maxlength="11" placeholder="<?= $appDisponible ? '123456' : 'ABCDE-FGHJK' ?>"
                       aria-describedby="ayudaCodigo">
                <div id="ayudaCodigo" class="form-text">¿Perdiste el teléfono? Escribe un código de recuperación (formato ABCDE-FGHJK).</div>
            </div>
            <div class="form-check mb-3">
                <input type="checkbox" name="confiar" value="1" id="confiar" class="form-check-input">
                <label class="form-check-label small" for="confiar">No volver a pedirlo en este dispositivo durante 30 días</label>
            </div>
            <button type="submit" class="btn btn-primary w-100">Verificar</button>
        </form>

        <div class="text-center mt-3">
            <a href="<?= Url::to('/login') ?>" class="small">Cancelar y volver al inicio de sesión</a>
        </div>
    </div>
</div>

<script src="<?= Url::asset('/assets/js/tema.js') ?>"></script>
<script src="<?= Url::asset('/assets/js/cargando.js') ?>"></script>
<script>
// Con 6 dígitos escritos (o pegados desde la aplicación), se envía solo.
(function () {
    var campo = document.getElementById('codigo');
    var form = document.getElementById('formCodigo');
    var enviado = false;
    campo.addEventListener('input', function () {
        if (!enviado && /^\d{6}$/.test(campo.value.replace(/\s/g, ''))) {
            enviado = true;
            form.submit();
        }
    });
})();
</script>
</body>
</html>
