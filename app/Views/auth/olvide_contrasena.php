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
    <title>Olvidé mi contraseña · MIA</title>
    <link rel="icon" type="image/png" sizes="32x32" href="<?= Url::asset('/assets/img/favicon-32.png') ?>">
    <?php \App\Core\View::render('partials/ui/hojas_estilo') ?>
</head>
<body>

<div class="auth-shell">
    <div class="auth-card position-relative">
        <button type="button" id="btnTema" class="theme-toggle" aria-label="Cambiar tema" title="Cambiar tema">
            <?= icono('tema') ?>
        </button>
        <div class="text-center mb-2">
            <img src="<?= Url::asset('/assets/img/logo.webp') ?>" width="400" height="400" alt="MIA" class="auth-logo">
        </div>
        <div class="brand-sub d-flex justify-content-center align-items-center gap-1">Recuperar contraseña<?= \App\Helpers\AyudaCampo::boton('recuperar_contrasena') ?></div>

        <?= ui('avisos', ['avisos' => \App\Core\Aviso::paraMostrar(), 'enfocar' => false]) ?>

        <form method="post" action="<?= Url::to('/olvide-contrasena') ?>">
            <?= \App\Core\Csrf::field() ?>
            <div class="mb-3">
                <?= \App\Helpers\AyudaCampo::etiqueta('campo-email', 'Correo institucional', 'ingreso_correo') ?>
                <input id="campo-email" type="email" name="email" class="form-control" required maxlength="150" autofocus autocomplete="username">
            </div>
            <button type="submit" class="btn btn-primary w-100">Enviar enlace</button>
        </form>

        <div class="text-center mt-3">
            <a href="<?= Url::to('/login') ?>" class="small">Volver a iniciar sesión</a>
        </div>
    </div>
</div>

<script src="<?= Url::asset('/assets/js/tema.js') ?>"></script>
<script src="<?= Url::asset('/assets/js/cargando.js') ?>"></script>
<script src="<?= Url::asset('/assets/js/alertas.js') ?>"></script>
<script src="<?= Url::asset('/assets/js/ayuda-campo.js') ?>"></script>
</body>
</html>
