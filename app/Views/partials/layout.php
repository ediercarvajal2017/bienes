<?php

use App\Core\Auth;
use App\Core\Request;
use App\Core\Url;

$rutaActual = (new Request())->uri;
$esActiva = static fn (string $prefijo): string => str_starts_with($rutaActual, $prefijo) ? ' active' : '';

?><!DOCTYPE html>
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
    <title><?= htmlspecialchars($title ?? 'SIGEBI', ENT_QUOTES) ?> · SIGEBI</title>
    <link rel="icon" type="image/jpeg" href="<?= Url::asset('/assets/img/favicon.jpg') ?>">
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.3/dist/css/bootstrap.min.css" rel="stylesheet">
    <link href="https://cdn.jsdelivr.net/npm/bootstrap-icons@1.11.3/font/bootstrap-icons.css" rel="stylesheet">
    <link href="https://cdn.jsdelivr.net/npm/tom-select@2.3.1/dist/css/tom-select.bootstrap5.min.css" rel="stylesheet">
    <link href="<?= Url::asset('/assets/css/app.css') ?>" rel="stylesheet">
    <link rel="manifest" href="<?= Url::to('/manifest.json') ?>">
    <meta name="theme-color" content="#1F6F54">
</head>
<body>

<nav class="navbar navbar-sigebi navbar-expand px-3">
    <button type="button" id="btnMenu" class="navbar-toggle me-2" aria-label="Abrir menú">
        <i class="bi bi-list"></i>
    </button>
    <a class="navbar-brand d-flex align-items-center" href="<?= Url::to('/dashboard') ?>">
        <img src="<?= Url::asset('/assets/img/logo.png') ?>" alt="SIGEBI" class="navbar-logo">
    </a>
    <div class="ms-auto d-flex align-items-center gap-2 gap-sm-3">
        <span class="text-white small d-none d-md-inline"><?= htmlspecialchars(Auth::nombreCompleto() ?? '', ENT_QUOTES) ?> · <?= htmlspecialchars(Auth::rol() ?? '', ENT_QUOTES) ?></span>
        <button type="button" id="btnTema" class="theme-toggle" aria-label="Cambiar tema" title="Cambiar tema">
            <i class="bi bi-moon-stars"></i>
        </button>
        <form method="post" action="<?= Url::to('/logout') ?>">
            <?= \App\Core\Csrf::field() ?>
            <button type="submit" class="btn btn-sm btn-light">Salir</button>
        </form>
    </div>
</nav>

<div class="d-flex">
    <div id="sidebarOverlay" class="sidebar-overlay"></div>
    <aside id="sidebar" class="sidebar">
        <nav class="nav flex-column">
            <a class="nav-link<?= $esActiva('/dashboard') ?>" href="<?= Url::to('/dashboard') ?>"><i class="bi bi-grid-1x2 me-2"></i>Panel principal</a>

            <?php if (Auth::esSuperusuario() || Auth::tienePermiso('bienes.ver')): ?>
                <a class="nav-link<?= $esActiva('/bienes') ?>" href="<?= Url::to('/bienes') ?>"><i class="bi bi-box-seam me-2"></i>Bienes</a>
            <?php endif; ?>

            <?php if (Auth::esSuperusuario() || Auth::tienePermiso('espacios.ver')): ?>
                <a class="nav-link<?= $esActiva('/espacios') ?>" href="<?= Url::to('/espacios') ?>"><i class="bi bi-door-open me-2"></i>Espacios</a>
            <?php endif; ?>

            <?php if (Auth::esSuperusuario() || Auth::tienePermiso('asignaciones.crear')): ?>
                <a class="nav-link<?= $esActiva('/asignaciones') ?>" href="<?= Url::to('/asignaciones') ?>"><i class="bi bi-person-check me-2"></i>Asignar bienes</a>
                <a class="nav-link<?= $rutaActual === '/reintegros' ? ' active' : '' ?>" href="<?= Url::to('/reintegros') ?>"><i class="bi bi-box-arrow-in-left me-2"></i>Reintegrar bienes</a>
                <a class="nav-link<?= $esActiva('/reintegros/lotes') ?>" href="<?= Url::to('/reintegros/lotes') ?>"><i class="bi bi-file-earmark-spreadsheet me-2"></i>Lotes de reintegro</a>
            <?php endif; ?>

            <a class="nav-link<?= $esActiva('/escanear') ?>" href="<?= Url::to('/escanear') ?>"><i class="bi bi-qr-code-scan me-2"></i>Escanear QR</a>

            <?php if (Auth::esSuperusuario() || Auth::tienePermiso('bajas.crear') || Auth::tienePermiso('bajas.aprobar')): ?>
                <a class="nav-link<?= $esActiva('/bajas') ?>" href="<?= Url::to('/bajas') ?>"><i class="bi bi-exclamation-triangle me-2"></i>Bajas</a>
            <?php endif; ?>

            <?php if (Auth::esSuperusuario() || Auth::tienePermiso('verificaciones.gestionar')): ?>
                <a class="nav-link<?= $esActiva('/verificaciones') ?>" href="<?= Url::to('/verificaciones') ?>"><i class="bi bi-clipboard2-check me-2"></i>Verificación física</a>
            <?php endif; ?>

            <?php if (Auth::esSuperusuario() || Auth::tienePermiso('reportes.generar')): ?>
                <a class="nav-link<?= $esActiva('/reportes') ?>" href="<?= Url::to('/reportes') ?>"><i class="bi bi-file-earmark-spreadsheet me-2"></i>Reportes</a>
            <?php endif; ?>

            <?php if (Auth::esSuperusuario() || Auth::tienePermiso('cartera.gestionar')): ?>
                <a class="nav-link<?= $esActiva('/cartera') ?>" href="<?= Url::to('/cartera/enviar') ?>"><i class="bi bi-archive me-2"></i>Cartera (histórico)</a>
            <?php endif; ?>

            <?php if (Auth::esSuperusuario() || Auth::tienePermiso('formatos_reintegro.gestionar')): ?>
                <a class="nav-link<?= $esActiva('/formatos-reintegro') ?>" href="<?= Url::to('/formatos-reintegro') ?>"><i class="bi bi-file-earmark-check me-2"></i>Formatos de reintegro</a>
            <?php endif; ?>

            <?php if (Auth::esSuperusuario() || Auth::tienePermiso('formatos_plaqueteo.gestionar')): ?>
                <a class="nav-link<?= $esActiva('/formatos-plaqueteo') ?>" href="<?= Url::to('/formatos-plaqueteo') ?>"><i class="bi bi-tag me-2"></i>Formatos de plaqueteo</a>
            <?php endif; ?>

            <?php if (Auth::esSuperusuario() || Auth::tienePermiso('facturas_admin.gestionar')): ?>
                <a class="nav-link<?= $esActiva('/facturas') ?>" href="<?= Url::to('/facturas') ?>"><i class="bi bi-receipt me-2"></i>Facturas</a>
            <?php endif; ?>

            <?php if (Auth::esSuperusuario() || Auth::tienePermiso('cargas.masivas')): ?>
                <a class="nav-link<?= $esActiva('/cargas-masivas') ?>" href="<?= Url::to('/cargas-masivas') ?>"><i class="bi bi-upload me-2"></i>Carga masiva</a>
            <?php endif; ?>

            <?php if (Auth::esSuperusuario() || Auth::tienePermiso('usuarios.ver')): ?>
                <a class="nav-link<?= $esActiva('/usuarios') ?>" href="<?= Url::to('/usuarios') ?>"><i class="bi bi-people me-2"></i>Usuarios</a>
            <?php endif; ?>

            <?php if (Auth::esSuperusuario() || Auth::tienePermiso('instituciones.ver')): ?>
                <a class="nav-link<?= $esActiva('/instituciones') ?>" href="<?= Url::to('/instituciones') ?>"><i class="bi bi-building me-2"></i>Instituciones</a>
            <?php endif; ?>

            <?php if (Auth::esSuperusuario()): ?>
                <a class="nav-link<?= $esActiva('/cargos') ?>" href="<?= Url::to('/cargos') ?>"><i class="bi bi-person-badge me-2"></i>Cargos</a>
            <?php endif; ?>

            <?php if (Auth::esSuperusuario() || Auth::tienePermiso('categorias.gestionar')): ?>
                <a class="nav-link<?= $esActiva('/categorias') ?>" href="<?= Url::to('/categorias') ?>"><i class="bi bi-tags me-2"></i>Categorías</a>
            <?php endif; ?>
        </nav>
    </aside>

    <main class="flex-fill p-4">
        <?php $content(); ?>
    </main>
</div>

<script src="<?= Url::asset('/assets/js/tema.js') ?>"></script>
<script src="<?= Url::asset('/assets/js/camara.js') ?>"></script>
<script src="<?= Url::asset('/assets/js/lightbox.js') ?>"></script>
<script src="<?= Url::asset('/assets/js/cargando.js') ?>"></script>
<script src="https://cdn.jsdelivr.net/npm/tom-select@2.3.1/dist/js/tom-select.complete.min.js"></script>
<script src="<?= Url::asset('/assets/js/selector-buscable.js') ?>"></script>
<script>
(function () {
    var boton = document.getElementById('btnMenu');
    var sidebar = document.getElementById('sidebar');
    var overlay = document.getElementById('sidebarOverlay');
    if (!boton || !sidebar || !overlay) { return; }

    function abrir() {
        sidebar.classList.add('abierto');
        overlay.classList.add('visible');
    }

    function cerrar() {
        sidebar.classList.remove('abierto');
        overlay.classList.remove('visible');
    }

    boton.addEventListener('click', function () {
        sidebar.classList.contains('abierto') ? cerrar() : abrir();
    });
    overlay.addEventListener('click', cerrar);
    sidebar.querySelectorAll('a').forEach(function (enlace) {
        enlace.addEventListener('click', cerrar);
    });
    document.addEventListener('keydown', function (e) {
        if (e.key === 'Escape') { cerrar(); }
    });
})();

if ('serviceWorker' in navigator) {
    navigator.serviceWorker.register(<?= json_encode(Url::to('/sw.js')) ?>).catch(function () {});
}
</script>

</body>
</html>
