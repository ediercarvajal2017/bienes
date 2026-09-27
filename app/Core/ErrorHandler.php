<?php

declare(strict_types=1);

namespace App\Core;

/**
 * Manejo global de errores: toda excepción no capturada o error fatal queda registrado en
 * storage/logs/app-AAAA-MM-DD.log con un código de incidente, y el usuario ve la página
 * errors/500 con ese código (para reportarlo a soporte) en lugar de una pantalla en blanco
 * o una traza de PHP.
 *
 * Las advertencias (warning/notice/deprecated) solo se registran: NO se convierten en
 * excepciones, para no romper pantallas que hoy funcionan en producción aunque generen
 * algún aviso menor.
 */
final class ErrorHandler
{
    private const FATALES = [E_ERROR, E_PARSE, E_CORE_ERROR, E_COMPILE_ERROR, E_USER_ERROR];

    private static bool $debug = false;
    private static string $dirLogs = '';
    private static bool $manejando = false;

    public static function registrar(bool $debug, string $dirLogs): void
    {
        self::$debug = $debug;
        self::$dirLogs = $dirLogs;

        set_error_handler([self::class, 'alError']);
        set_exception_handler([self::class, 'alExcepcion']);
        register_shutdown_function([self::class, 'alTerminar']);
    }

    /**
     * Para los catch que manejan el error con un mensaje amable (p. ej. "no se aplicó
     * ningún cambio") pero no deben perder el detalle: lo deja en el log y devuelve el
     * código de incidente por si se quiere mostrar.
     */
    public static function reportar(\Throwable $e, string $contexto = ''): string
    {
        $incidente = strtoupper(bin2hex(random_bytes(4)));
        self::escribirLog('ERROR', sprintf(
            "#%s %s%s: %s en %s:%d\n%s",
            $incidente,
            $contexto !== '' ? "[{$contexto}] " : '',
            $e::class,
            $e->getMessage(),
            $e->getFile(),
            $e->getLine(),
            $e->getTraceAsString()
        ));

        return $incidente;
    }

    public static function alError(int $nivel, string $mensaje, string $archivo = '', int $linea = 0): bool
    {
        if (!(error_reporting() & $nivel)) {
            return false; // silenciado con @ o por error_reporting
        }

        self::escribirLog('AVISO', sprintf('%s en %s:%d', $mensaje, $archivo, $linea));

        // true = PHP no muestra su propio mensaje (en producción display_errors ya está
        // apagado; en local se deja que PHP lo muestre para no esconder avisos al desarrollar).
        return !self::$debug;
    }

    public static function alExcepcion(\Throwable $e): void
    {
        self::responder500(
            $e::class . ': ' . $e->getMessage() . sprintf(' en %s:%d', $e->getFile(), $e->getLine()),
            $e->getTraceAsString(),
            (string) $e
        );
    }

    public static function alTerminar(): void
    {
        $error = error_get_last();
        if ($error === null || !in_array($error['type'], self::FATALES, true)) {
            return;
        }

        $detalle = sprintf('%s en %s:%d', $error['message'], $error['file'], $error['line']);
        self::responder500('FATAL: ' . $detalle, '', $detalle);
    }

    private static function responder500(string $resumen, string $traza, string $detalleDebug): void
    {
        if (self::$manejando) {
            return; // un error dentro del propio manejo: no entrar en bucle
        }
        self::$manejando = true;

        $incidente = strtoupper(bin2hex(random_bytes(4)));
        $contexto = sprintf(
            '#%s %s %s usuario=%s',
            $incidente,
            $_SERVER['REQUEST_METHOD'] ?? 'CLI',
            $_SERVER['REQUEST_URI'] ?? '',
            $_SESSION['usuario_id'] ?? '-'
        );
        self::escribirLog('ERROR', $contexto . "\n" . $resumen . ($traza !== '' ? "\n" . $traza : ''));

        if (PHP_SAPI === 'cli') {
            fwrite(STDERR, $resumen . "\n" . $traza . "\n");
            exit(1);
        }

        // Si ya se envió parte de la página no se puede cambiar el código HTTP; igual se
        // intenta mostrar el aviso al final de lo que alcanzó a salir.
        if (!headers_sent()) {
            http_response_code(500);
            header('Content-Type: text/html; charset=UTF-8');
            header('Cache-Control: no-store');
        }

        if (self::$debug) {
            echo '<pre style="white-space:pre-wrap;padding:1rem">' . htmlspecialchars($detalleDebug, ENT_QUOTES) . '</pre>';
            return;
        }

        try {
            View::render('errors/500', ['incidente' => $incidente]);
        } catch (\Throwable) {
            echo 'Ocurrió un error inesperado. Código de incidente: ' . $incidente;
        }
    }

    private static function escribirLog(string $tipo, string $mensaje): void
    {
        $linea = sprintf("[%s] %s %s\n", date('Y-m-d H:i:s'), $tipo, $mensaje);

        if (self::$dirLogs !== '' && (is_dir(self::$dirLogs) || @mkdir(self::$dirLogs, 0775, true))) {
            if (@file_put_contents(self::$dirLogs . '/app-' . date('Y-m-d') . '.log', $linea, FILE_APPEND | LOCK_EX) !== false) {
                return;
            }
        }

        error_log(rtrim($linea)); // respaldo: log de errores de PHP del servidor
    }
}
