<?php

use App\Core\Auth;
use App\Core\Request;
use App\Core\Url;
use App\Models\Institucion;

$rutaActual = (new Request())->uri;
/**
 * Menú de navegación: definición única en partials/menu_definicion.php. Aquí se busca la
 * opción de la página actual (la primera cuyo prefijo coincide; "=" delante = ruta exacta)
 * y su sección, que también nombra el paso intermedio de la ruta de navegación.
 */
$menu = require __DIR__ . '/menu_definicion.php';
$coincideRuta = static function (array $prefijos) use ($rutaActual): bool {
    foreach ($prefijos as $prefijo) {
        if (str_starts_with($prefijo, '=') ? $rutaActual === substr($prefijo, 1) : str_starts_with($rutaActual, $prefijo)) {
            return true;
        }
    }

    return false;
};
$opcionActiva = null;
$seccionActiva = null;
$tituloSeccionActiva = null;
foreach ($menu as $seccionMenu) {
    foreach ($seccionMenu['opciones'] as $opcionMenu) {
        if ($opcionMenu['visible'] && $coincideRuta($opcionMenu['activo'])) {
            $opcionActiva = $opcionMenu;
            $seccionActiva = $seccionMenu['clave'];
            $tituloSeccionActiva = $seccionMenu['titulo'];
            break 2;
        }
    }
}
// Opciones del pie del menú (no pertenecen a ninguna sección).
foreach (['/manual', '/mi-cuenta'] as $rutaPie) {
    if ($opcionActiva === null && str_starts_with($rutaActual, $rutaPie)) {
        $opcionActiva = ['ruta' => $rutaPie];
    }
}

?><!DOCTYPE html>
<html lang="es">
<head>
    <script>
    (function () {
        try {
            var guardado = localStorage.getItem('sigebi-theme');
            var tema = guardado || 'dark';
            document.documentElement.setAttribute('data-bs-theme', tema);
            if (localStorage.getItem('mia-menu-compacto') === '1') {
                document.documentElement.classList.add('menu-compacto');
            }
        } catch (e) {}
    })();
    </script>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1, viewport-fit=cover">
    <title><?= htmlspecialchars($title ?? 'MIA', ENT_QUOTES) ?> · MIA</title>
    <link rel="icon" type="image/png" sizes="32x32" href="<?= Url::asset('/assets/img/favicon-32.png') ?>">
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.3/dist/css/bootstrap.min.css" integrity="sha384-QWTKZyjpPEjISv5WaRU9OFeRpok6YctnYmDr5pNlyT2bRjXh0JMhjY6hW+ALEwIH" crossorigin="anonymous" rel="stylesheet">
    <link href="https://cdn.jsdelivr.net/npm/bootstrap-icons@1.11.3/font/bootstrap-icons.css" integrity="sha384-tViUnnbYAV00FLIhhi3v/dWt3Jxw4gZQcNoSCxCIFNJVCx7/D55/wXsrNIRANwdD" crossorigin="anonymous" rel="stylesheet">
    <link href="https://cdn.jsdelivr.net/npm/tom-select@2.3.1/dist/css/tom-select.bootstrap5.min.css" integrity="sha384-piG3EtH1fBnPi68q4spy+Qgpb0dHK1D1dwk0GaHwFkvmUxYi526bBlk3xJcjEBsD" crossorigin="anonymous" rel="stylesheet">
    <link href="<?= Url::asset('/assets/css/app.css') ?>" rel="stylesheet">
    <link rel="manifest" href="<?= Url::to('/manifest.json') ?>">
    <meta name="theme-color" content="#1F6F54" media="(prefers-color-scheme: light)">
    <meta name="theme-color" content="#0f141a" media="(prefers-color-scheme: dark)">
    <!-- iPhone/iPad: ícono y pantalla completa al agregar MIA a la pantalla de inicio. -->
    <link rel="apple-touch-icon" href="<?= Url::asset('/assets/img/icon-192.png') ?>">
    <meta name="apple-mobile-web-app-capable" content="yes">
    <meta name="mobile-web-app-capable" content="yes">
    <meta name="apple-mobile-web-app-status-bar-style" content="black-translucent">
    <meta name="apple-mobile-web-app-title" content="MIA">
</head>
<body>

<a class="visually-hidden-focusable" href="#contenidoPrincipal">Saltar al contenido</a>

<nav class="navbar navbar-sigebi navbar-expand px-3">
    <button type="button" id="btnMenu" class="navbar-toggle me-2" aria-label="Abrir menú" aria-expanded="false">
        <i class="bi bi-list"></i>
    </button>
    <a class="navbar-brand d-flex align-items-center" href="<?= Url::to('/dashboard') ?>">
        <img src="<?= Url::asset('/assets/img/logo.webp') ?>" width="400" height="400" alt="" class="navbar-logo">
        <span class="navbar-marca-texto">MIA</span>
    </a>
    <?php
    $nombreUsuarioNavbar = Auth::nombreCompleto() ?? '';
    $palabrasNombre = preg_split('/\s+/', trim($nombreUsuarioNavbar), -1, PREG_SPLIT_NO_EMPTY);
    $inicialesUsuario = '';
    if (!empty($palabrasNombre)) {
        $inicialesUsuario = mb_strtoupper(mb_substr($palabrasNombre[0], 0, 1));
        if (count($palabrasNombre) > 1) {
            $inicialesUsuario .= mb_strtoupper(mb_substr(end($palabrasNombre), 0, 1));
        }
    }
    $familiaSedes = [];
    if (Auth::rol() === 'rector' && Auth::institucionId()) {
        $familiaSedes = Institucion::familiaDe((int) Auth::institucionId());
    }
    $institucionesFiltro = Auth::esSuperusuario() ? Institucion::listadoParaSelect() : [];
    ?>
    <form method="get" action="<?= Url::to('/buscar') ?>" class="d-none d-lg-block ms-3" style="width: 220px;">
        <label for="buscadorGlobal" class="visually-hidden">Buscar bienes, espacios o usuarios</label>
        <input type="search" data-buscar-form id="buscadorGlobal" name="q" class="form-control form-control-sm"
               placeholder="Buscar en todo MIA...">
    </form>
    <?php
    // Notas rápidas del usuario (partials/notas.php). Si la tabla aún no existe (despliegue
    // a medias), el ícono sale sin número en lugar de tumbar todas las páginas.
    try {
        $totalNotas = \App\Models\Nota::contar((int) Auth::id());
    } catch (\Throwable $e) {
        \App\Core\ErrorHandler::reportar($e, 'layout: contar notas');
        $totalNotas = 0;
    }
    ?>
    <div class="ms-auto d-flex align-items-center gap-2 gap-sm-3">
        <button type="button" class="theme-toggle boton-notas" data-abrir-notas aria-controls="panelNotas" aria-expanded="false"
                title="Mis notas" aria-label="Mis notas<?= $totalNotas > 0 ? " ({$totalNotas})" : '' ?>">
            <i class="bi bi-sticky" aria-hidden="true"></i>
            <span class="boton-notas-cuenta" data-cuenta-notas aria-hidden="true"<?= $totalNotas > 0 ? '' : ' hidden' ?>><?= $totalNotas ?></span>
        </button>
        <a href="<?= Url::to('/buscar') ?>" class="theme-toggle d-none d-sm-inline-flex d-lg-none" aria-label="Buscar" title="Buscar">
            <i class="bi bi-search"></i>
        </a>
        <a href="<?= Url::to('/mi-cuenta') ?>" class="theme-toggle d-md-none" aria-label="Mi cuenta" title="Mi cuenta">
            <i class="bi bi-person-circle"></i>
        </a>
        <a href="<?= Url::to('/mi-cuenta') ?>" class="usuario-navbar usuario-navbar-enlace d-none d-md-flex align-items-center gap-2" title="Mi cuenta: contraseña y verificación en dos pasos">
            <span class="usuario-navbar-avatar" aria-hidden="true"><?= htmlspecialchars($inicialesUsuario, ENT_QUOTES) ?></span>
            <div class="usuario-navbar-info">
                <div class="usuario-navbar-nombre"><?= htmlspecialchars($nombreUsuarioNavbar, ENT_QUOTES) ?></div>
                <div class="usuario-navbar-detalle">
                    <span class="usuario-navbar-rol"><?= htmlspecialchars(Auth::rol() ?? '', ENT_QUOTES) ?></span>
                    <?php if (!Auth::esSuperusuario() && Auth::institucionNombre() !== null && count($familiaSedes) <= 1): ?>
                        <span class="usuario-navbar-institucion" title="<?= htmlspecialchars(Auth::institucionNombre(), ENT_QUOTES) ?>">
                            <i class="bi bi-building"></i><?= htmlspecialchars(Auth::institucionNombre(), ENT_QUOTES) ?>
                        </span>
                    <?php endif; ?>
                </div>
            </div>
        </a>
        <?php if (count($familiaSedes) > 1): ?>
            <form method="post" action="<?= Url::to('/sede-activa') ?>" class="d-none d-md-flex align-items-center">
                <?= \App\Core\Csrf::field() ?>
                <input type="hidden" name="volver" value="<?= htmlspecialchars($rutaActual, ENT_QUOTES) ?>">
                <label for="sedeActivaSelect" class="visually-hidden">Sede activa</label>
                <select id="sedeActivaSelect" name="institucion_id" class="form-select form-select-sm sede-activa-select" onchange="this.form.submit()" title="Cambiar de sede">
                    <?php foreach ($familiaSedes as $sede): ?>
                        <option value="<?= (int) $sede['id'] ?>" <?= (int) $sede['id'] === Auth::sedeActivaId() ? 'selected' : '' ?>><?= htmlspecialchars($sede['nombre'], ENT_QUOTES) ?></option>
                    <?php endforeach; ?>
                </select>
            </form>
        <?php endif; ?>
        <?php if (Auth::esSuperusuario()): ?>
            <form method="post" action="<?= Url::to('/filtro-institucion') ?>"
                  class="d-none d-md-flex align-items-center filtro-institucion-form<?= Auth::filtroInstitucionId() !== null ? ' filtro-institucion-form--activo' : '' ?>">
                <?= \App\Core\Csrf::field() ?>
                <input type="hidden" name="volver" value="<?= htmlspecialchars($rutaActual, ENT_QUOTES) ?>">
                <label for="filtroInstitucionSelect" class="visually-hidden">Filtrar por institución</label>
                <select id="filtroInstitucionSelect" name="institucion_id"
                        class="form-select form-select-sm selector-buscable filtro-institucion-select"
                        onchange="this.form.submit()" title="Filtrar listados por institución">
                    <option value="">Ver todas las instituciones</option>
                    <?php foreach ($institucionesFiltro as $i): ?>
                        <option value="<?= (int) $i['id'] ?>" <?= Auth::filtroInstitucionId() === (int) $i['id'] ? 'selected' : '' ?>>
                            <?= htmlspecialchars($i['nombre'], ENT_QUOTES) ?>
                        </option>
                    <?php endforeach; ?>
                </select>
            </form>
        <?php endif; ?>
        <button type="button" id="btnTema" class="theme-toggle d-none d-sm-inline-flex" data-tema-toggle aria-label="Cambiar tema" title="Cambiar tema">
            <i class="bi bi-moon-stars"></i>
        </button>
        <form method="post" action="<?= Url::to('/logout') ?>">
            <?= \App\Core\Csrf::field() ?>
            <button type="submit" class="btn btn-sm btn-light boton-salir" aria-label="Salir" title="Salir">
                <i class="bi bi-box-arrow-right d-sm-none" aria-hidden="true"></i><span class="d-none d-sm-inline">Salir</span>
            </button>
        </form>
    </div>
</nav>

<div class="d-flex">
    <div id="sidebarOverlay" class="sidebar-overlay"></div>
    <?php require __DIR__ . '/menu_lateral.php'; ?>

    <main id="contenidoPrincipal" class="flex-fill p-4">
        <?php if ($rutaActual !== '/dashboard'): ?>
            <nav aria-label="Ruta de navegación" class="mb-3">
                <ol class="breadcrumb small mb-0">
                    <li class="breadcrumb-item"><a href="<?= Url::to('/dashboard') ?>">Panel principal</a></li>
                    <?php if ($tituloSeccionActiva !== null): ?>
                        <li class="breadcrumb-item text-muted"><?= htmlspecialchars($tituloSeccionActiva, ENT_QUOTES) ?></li>
                    <?php endif; ?>
                    <li class="breadcrumb-item active" aria-current="page"><?= htmlspecialchars($title ?? '', ENT_QUOTES) ?></li>
                </ol>
            </nav>
        <?php endif; ?>
        <?php $content(); ?>

        <?php $configApp = require dirname(__DIR__, 3) . '/config/app.php'; ?>
        <footer class="pie-sigebi small text-muted d-flex flex-wrap justify-content-between gap-2 mt-5 pt-3 border-top">
            <span>MIA · versión <?= htmlspecialchars((string) $configApp['version'], ENT_QUOTES) ?><?php if (Auth::institucionNombre() !== null && !Auth::esSuperusuario()): ?> · <?= htmlspecialchars(Auth::institucionNombre(), ENT_QUOTES) ?><?php endif; ?></span>
            <span class="d-flex flex-wrap gap-3">
                <a href="<?= Url::to('/politica-de-datos') ?>" class="text-muted">Política de datos</a>
                <a href="<?= Url::to('/manual') ?>" class="text-muted">¿Necesitas ayuda? Guía rápida</a>
            </span>
        </footer>
    </main>
</div>

<script src="<?= Url::asset('/assets/js/tema.js') ?>"></script>
<script src="<?= Url::asset('/assets/js/alertas.js') ?>"></script>
<script src="<?= Url::asset('/assets/js/mostrar-contrasena.js') ?>"></script>
<script src="<?= Url::asset('/assets/js/camara.js') ?>"></script>
<?php require __DIR__ . '/menu_inferior.php'; ?>
<?php require __DIR__ . '/notas.php'; ?>
<script src="<?= Url::asset('/assets/js/menu.js') ?>"></script>
<script src="<?= Url::asset('/assets/js/notas.js') ?>"></script>
<script src="<?= Url::asset('/assets/js/lightbox.js') ?>"></script>
<script src="<?= Url::asset('/assets/js/cargando.js') ?>"></script>
<script src="<?= Url::asset('/assets/js/buscador-vivo.js') ?>"></script>
<script src="https://cdn.jsdelivr.net/npm/tom-select@2.3.1/dist/js/tom-select.complete.min.js" integrity="sha384-cnROoUgVILyibe3J0zhzWoJ9p2WmdnK7j/BOTSWqVDbC1pVw2d+i6Q/1ESKJKCYf" crossorigin="anonymous"></script>
<script src="<?= Url::asset('/assets/js/selector-buscable.js') ?>"></script>
<script>
if ('serviceWorker' in navigator) {
    navigator.serviceWorker.register(<?= json_encode(Url::to('/sw.js')) ?>).catch(function () {});
}
</script>

</body>
</html>
