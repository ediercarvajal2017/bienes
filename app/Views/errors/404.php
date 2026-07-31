<!DOCTYPE html>
<html lang="es">
<head>
    <script>
    (function () {
        try {
            var guardado = localStorage.getItem('sigebi-theme');
            var tema = guardado || (window.matchMedia('(prefers-color-scheme: dark)').matches ? 'dark' : 'light');
            document.documentElement.setAttribute('data-bs-theme', tema);
        } catch (e) {}
    })();
    </script>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <title>404 · SIGEBI</title>
    <link rel="icon" type="image/jpeg" href="<?= \App\Core\Url::asset('/assets/img/favicon.jpg') ?>">
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.3/dist/css/bootstrap.min.css" rel="stylesheet">
</head>
<body class="d-flex align-items-center justify-content-center vh-100">
    <div class="text-center">
        <h1 class="display-5">404</h1>
        <p class="text-muted">La página que buscas no existe.</p>
        <a href="<?= \App\Core\Url::to('/') ?>" class="btn btn-primary btn-sm">Volver al inicio</a>
    </div>
</body>
</html>
