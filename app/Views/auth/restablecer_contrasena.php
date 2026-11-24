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
    <?php \App\Core\View::render('partials/ui/hojas_estilo') ?>
</head>
<body>

<div class="auth-shell">
    <div class="auth-card position-relative">
        <button type="button" id="btnTema" class="theme-toggle" aria-label="Cambiar tema" title="Cambiar tema">
            <?= icono('tema') ?>
        </button>
        <div class="text-center"><img src="<?= Url::asset('/assets/img/logo.webp') ?>" width="400" height="400" alt="MIA" class="auth-logo-72"></div>
        <div class="brand-sub d-flex justify-content-center align-items-center gap-1">Restablecer contraseña<?= \App\Helpers\AyudaCampo::boton('restablecer_contrasena') ?></div>

        <?= ui('avisos', ['avisos' => \App\Core\Aviso::paraMostrar(), 'enfocar' => false]) ?>

        <?php if (!$valido): ?>
            <p class="small text-muted">Este enlace ya no es válido — puede que haya expirado (dura 60 minutos) o que ya se haya usado.</p>
            <a href="<?= Url::to('/olvide-contrasena') ?>" class="btn btn-primary w-100">Solicitar un enlace nuevo</a>
        <?php else: ?>
            <form method="post" action="<?= Url::to('/restablecer-contrasena/' . $token) ?>">
                <?= \App\Core\Csrf::field() ?>
                <div class="mb-3">
                    <?= \App\Helpers\AyudaCampo::etiqueta('password', 'Nueva contraseña', 'restablecer_nueva') ?>
                    <input type="password" name="password" id="password" class="form-control" required minlength="10" autofocus autocomplete="new-password">
                </div>
                <div class="mb-3">
                    <?= \App\Helpers\AyudaCampo::etiqueta('password_confirmacion', 'Confirmar contraseña', 'contrasena_confirmacion') ?>
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
<script src="<?= Url::asset('/assets/js/ayuda-campo.js') ?>"></script>
</body>
</html>
