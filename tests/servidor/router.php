<?php

// Enrutador del servidor embebido de PHP para la suite de pruebas (lo levanta Playwright:
// ver webServer en playwright.config.js). Sirve los archivos estáticos de public/ tal cual
// y todo lo demás lo pasa por public/index.php, como hace el .htaccess en Apache.
$publico = dirname(__DIR__, 2) . '/public';
$ruta = parse_url($_SERVER['REQUEST_URI'], PHP_URL_PATH) ?: '/';

if ($ruta !== '/' && is_file($publico . $ruta) && !str_ends_with($ruta, '.php')) {
    return false;
}

$_SERVER['SCRIPT_NAME'] = '/index.php';
chdir($publico);
require $publico . '/index.php';
