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
    <title>Olvidé mi correo · SIGEBI</title>
    <link rel="icon" type="image/png" sizes="32x32" href="<?= Url::asset('/assets/img/favicon-32.png') ?>">
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.3/dist/css/bootstrap.min.css" integrity="sha384-QWTKZyjpPEjISv5WaRU9OFeRpok6YctnYmDr5pNlyT2bRjXh0JMhjY6hW+ALEwIH" crossorigin="anonymous" rel="stylesheet">
    <link href="https://cdn.jsdelivr.net/npm/bootstrap-icons@1.11.3/font/bootstrap-icons.css" integrity="sha384-tViUnnbYAV00FLIhhi3v/dWt3Jxw4gZQcNoSCxCIFNJVCx7/D55/wXsrNIRANwdD" crossorigin="anonymous" rel="stylesheet">
    <link href="https://cdn.jsdelivr.net/npm/tom-select@2.3.1/dist/css/tom-select.bootstrap5.min.css" integrity="sha384-piG3EtH1fBnPi68q4spy+Qgpb0dHK1D1dwk0GaHwFkvmUxYi526bBlk3xJcjEBsD" crossorigin="anonymous" rel="stylesheet">
    <link href="<?= Url::asset('/assets/css/app.css') ?>" rel="stylesheet">
</head>
<body>

<div class="auth-shell">
    <div class="auth-card position-relative">
        <button type="button" id="btnTema" class="theme-toggle" aria-label="Cambiar tema" title="Cambiar tema">
            <i class="bi bi-moon-stars"></i>
        </button>
        <div class="text-center mb-2">
            <img src="<?= Url::asset('/assets/img/logo.webp') ?>" width="600" height="206" alt="SIGEBI" class="auth-logo">
        </div>
        <div class="brand-sub text-center">¿Cuál es mi correo?</div>

        <?php if (!empty($resultado)): ?>
            <div class="alert alert-success py-2 small">
                Tu correo registrado es: <strong><?= htmlspecialchars($resultado, ENT_QUOTES) ?></strong>
            </div>
        <?php endif; ?>
        <?php if (!empty($error)): ?>
            <div class="alert alert-danger py-2 small"><?= htmlspecialchars($error, ENT_QUOTES) ?></div>
        <?php endif; ?>

        <p class="small text-muted">Indica tu número de documento y tu institución; te mostramos una versión parcialmente oculta del correo con el que inicias sesión.</p>

        <form method="post" action="<?= Url::to('/olvide-correo') ?>">
            <?= \App\Core\Csrf::field() ?>
            <div class="mb-3">
                <label class="form-label small">Institución</label>
                <select name="institucion_id" class="form-select selector-buscable" required>
                    <option value="">-- Selecciona --</option>
                    <?php foreach ($instituciones as $i): ?>
                        <option value="<?= $i['id'] ?>"><?= htmlspecialchars($i['nombre'], ENT_QUOTES) ?></option>
                    <?php endforeach; ?>
                </select>
            </div>
            <div class="mb-3">
                <label class="form-label small">Número de documento</label>
                <input type="text" name="documento" class="form-control" required autofocus>
            </div>
            <button type="submit" class="btn btn-primary w-100">Buscar mi correo</button>
        </form>

        <div class="text-center mt-3">
            <a href="<?= Url::to('/login') ?>" class="small">Volver a iniciar sesión</a>
        </div>
    </div>
</div>

<script src="<?= Url::asset('/assets/js/tema.js') ?>"></script>
<script src="<?= Url::asset('/assets/js/alertas.js') ?>"></script>
<script src="https://cdn.jsdelivr.net/npm/tom-select@2.3.1/dist/js/tom-select.complete.min.js" integrity="sha384-cnROoUgVILyibe3J0zhzWoJ9p2WmdnK7j/BOTSWqVDbC1pVw2d+i6Q/1ESKJKCYf" crossorigin="anonymous"></script>
<script src="<?= Url::asset('/assets/js/selector-buscable.js') ?>"></script>
</body>
</html>
