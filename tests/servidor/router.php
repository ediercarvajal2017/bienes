<?php

// Enrutador del servidor embebido de PHP para la suite de pruebas (lo levanta Playwright:
// ver webServer en playwright.config.js). Sirve los archivos estáticos de public/ tal cual
// y todo lo demás lo pasa por public/index.php, como hace el .htaccess en Apache.
$publico = dirname(__DIR__, 2) . '/public';
$ruta = parse_url($_SERVER['REQUEST_URI'], PHP_URL_PATH) ?: '/';

if ($ruta !== '/' && is_file($publico . $ruta) && !str_ends_with($ruta, '.php')) {
    return false;
}

// Medición de velocidad (opcional): con MEDIR_TIEMPOS=<archivo>, cada petición deja una
// línea JSON con el método, la ruta, el estado, los milisegundos en el servidor, la memoria
// y las consultas a la base. Sin la variable no hace nada.
$registroTiempos = getenv('MEDIR_TIEMPOS');
if (is_string($registroTiempos) && $registroTiempos !== '') {
    $inicioPeticion = hrtime(true);
    register_shutdown_function(static function () use ($inicioPeticion, $registroTiempos, $ruta): void {
        $ms = (hrtime(true) - $inicioPeticion) / 1e6;
        $consultas = null;
        try {
            // Sentencias de esta conexión (menos la propia consulta del contador).
            $fila = \App\Core\Database::connection()->query("SHOW SESSION STATUS LIKE 'Questions'")->fetch(\PDO::FETCH_NUM);
            $consultas = $fila ? (int) $fila[1] - 1 : null;
        } catch (\Throwable) {
        }
        $tipo = '';
        foreach (headers_list() as $cabecera) {
            if (stripos($cabecera, 'Content-Type:') === 0) {
                $tipo = trim(explode(';', substr($cabecera, 13))[0]);
            }
        }
        file_put_contents($registroTiempos, json_encode([
            'metodo' => $_SERVER['REQUEST_METHOD'] ?? 'GET',
            'ruta' => $ruta,
            'consulta' => (string) parse_url($_SERVER['REQUEST_URI'], PHP_URL_QUERY),
            'estado' => http_response_code(),
            'ms' => round($ms, 1),
            'memoria_mb' => round(memory_get_peak_usage() / 1048576, 1),
            'consultas' => $consultas,
            'tipo' => $tipo,
            'origen' => (string) parse_url($_SERVER['HTTP_REFERER'] ?? '', PHP_URL_PATH),
        ], JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES) . "\n", FILE_APPEND | LOCK_EX);
    });
}

$_SERVER['SCRIPT_NAME'] = '/index.php';
chdir($publico);
require $publico . '/index.php';
