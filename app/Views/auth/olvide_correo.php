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
    <title>Olvidé mi correo · MIA</title>
    <link rel="icon" type="image/png" sizes="32x32" href="<?= Url::asset('/assets/img/favicon-32.png') ?>">
    <?php \App\Core\View::render('partials/ui/hojas_estilo', ['conTomSelect' => true]) ?>
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
        <div class="brand-sub d-flex justify-content-center align-items-center gap-1">¿Cuál es mi correo?<?= \App\Helpers\AyudaCampo::boton('recuperar_correo') ?></div>

        <?php if (!empty($resultado)): ?>
            <div class="alert alert-success py-2 small">
                Tu correo registrado es: <strong><?= htmlspecialchars($resultado, ENT_QUOTES) ?></strong>
            </div>
        <?php endif; ?>
        <?= ui('avisos', ['avisos' => \App\Core\Aviso::paraMostrar(), 'enfocar' => false]) ?>

        <form method="post" action="<?= Url::to('/olvide-correo') ?>">
            <?= \App\Core\Csrf::field() ?>
            <div class="mb-3">
                <?= \App\Helpers\AyudaCampo::etiqueta('campo-institucion-id', 'Institución', 'recuperar_institucion') ?>
                <select id="campo-institucion-id" name="institucion_id" class="form-select selector-buscable" required>
                    <option value="">-- Selecciona --</option>
                    <?php foreach ($instituciones as $i): ?>
                        <option value="<?= $i['id'] ?>"><?= htmlspecialchars($i['nombre'], ENT_QUOTES) ?></option>
                    <?php endforeach; ?>
                </select>
            </div>
            <div class="mb-3">
                <?= \App\Helpers\AyudaCampo::etiqueta('campo-documento', 'Número de documento', 'recuperar_documento') ?>
                <input id="campo-documento" type="text" name="documento" class="form-control" required maxlength="20" autofocus>
            </div>
            <button type="submit" class="btn btn-primary w-100">Buscar mi correo</button>
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
<script src="https://cdn.jsdelivr.net/npm/tom-select@2.3.1/dist/js/tom-select.complete.min.js" integrity="sha384-cnROoUgVILyibe3J0zhzWoJ9p2WmdnK7j/BOTSWqVDbC1pVw2d+i6Q/1ESKJKCYf" crossorigin="anonymous"></script>
<script src="<?= Url::asset('/assets/js/selector-buscable.js') ?>"></script>
</body>
</html>
