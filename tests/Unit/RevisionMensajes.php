<?php

declare(strict_types=1);

namespace Tests\Unit;

/**
 * Mensajes que todavía no siguen el formato de tres partes (qué pasó, por qué, qué hacer;
 * App\Core\Aviso y docs/guia-estilo-ui.md, "Mensajes"):
 *  - flash: Session::flash('ok'|'error', texto), un aviso de una sola línea;
 *  - sinGuia: new \DomainException(…), una regla que dice el porqué pero no qué hacer
 *    (va App\Core\ErrorGuiado).
 *
 * Lo usan MensajesGuiadosTest (falla en CI) y database/herramientas/verificar_mensajes.php,
 * con el mismo método que el estándar visual: una lista de pendientes que solo puede bajar.
 */
final class RevisionMensajes
{
    public const REGLAS = [
        'flash' => "/Session::flash\\(\\s*'(?:ok|error)'/",
        'sinGuia' => '/new \\\\DomainException\\(/',
    ];

    /** Carpetas de app/ donde se arman los mensajes de las acciones. */
    private const CARPETAS = ['Controllers', 'Core', 'Helpers', 'Models', 'Services'];

    /**
     * Archivos ya migrados (fase 1, v1.2.36): no pueden volver a tener mensajes antiguos.
     * Al migrar otro módulo, se agrega aquí.
     */
    public const MIGRADOS = [
        'Controllers/AsignacionController.php',
        'Controllers/BajaController.php',
        'Controllers/BienController.php',
        'Controllers/FiltroInstitucionController.php',
        'Controllers/LoteReintegroController.php',
        'Controllers/MovimientoController.php',
        'Controllers/ReintegroController.php',
        'Controllers/ReintegroUnidadesController.php',
        'Controllers/SedeActivaController.php',
        'Controllers/SolicitudReintegroController.php',
        'Core/Csrf.php',
        'Services/CicloVidaBien.php',
        'Services/LoteReintegroService.php',
        'Services/ReintegroService.php',
        'Services/ReintegroUnidadesService.php',
    ];

    public static function carpetaApp(): string
    {
        return dirname(__DIR__, 2) . '/app';
    }

    public static function archivoPendientes(): string
    {
        return __DIR__ . '/datos/mensajes_pendientes.php';
    }

    /**
     * Cuántos mensajes antiguos tiene cada archivo, por regla (solo los que tienen alguno).
     *
     * @return array<string, array<string, int>> archivo (relativo a app/) => regla => cantidad
     */
    public static function conteo(): array
    {
        $raiz = self::carpetaApp();
        $conteo = [];
        foreach (self::CARPETAS as $carpeta) {
            $archivos = new \RecursiveIteratorIterator(new \RecursiveDirectoryIterator($raiz . '/' . $carpeta, \FilesystemIterator::SKIP_DOTS));
            foreach ($archivos as $archivo) {
                if (!$archivo instanceof \SplFileInfo || $archivo->getExtension() !== 'php') {
                    continue;
                }
                $ruta = str_replace('\\', '/', substr($archivo->getPathname(), strlen($raiz) + 1));
                $codigo = (string) file_get_contents($archivo->getPathname());
                foreach (self::REGLAS as $regla => $patron) {
                    $cantidad = preg_match_all($patron, $codigo);
                    if ($cantidad > 0) {
                        $conteo[$ruta][$regla] = $cantidad;
                    }
                }
            }
        }
        ksort($conteo);

        return $conteo;
    }

    /** @return array<string, array<string, int>> */
    public static function pendientes(): array
    {
        return is_file(self::archivoPendientes()) ? require self::archivoPendientes() : [];
    }

    /**
     * Diferencias con la lista de pendientes: lo que subió (error) y lo que bajó (hay que
     * actualizar la lista para que no vuelva a subir).
     *
     * @return array{subio: list<string>, bajo: list<string>}
     */
    public static function comparar(): array
    {
        $actual = self::conteo();
        $pendientes = self::pendientes();
        $subio = [];
        $bajo = [];
        foreach (array_unique([...array_keys($actual), ...array_keys($pendientes)]) as $archivo) {
            foreach (array_keys(self::REGLAS) as $regla) {
                $hoy = $actual[$archivo][$regla] ?? 0;
                $permitido = $pendientes[$archivo][$regla] ?? 0;
                if ($hoy > $permitido) {
                    $subio[] = "{$archivo}: {$hoy} {$regla} (permitido: {$permitido})";
                } elseif ($hoy < $permitido) {
                    $bajo[] = "{$archivo}: {$hoy} {$regla} (la lista dice {$permitido})";
                }
            }
        }

        return ['subio' => $subio, 'bajo' => $bajo];
    }

    /** Código PHP de la lista de pendientes con el conteo actual. */
    public static function exportar(): string
    {
        $lineas = [];
        foreach (self::conteo() as $archivo => $reglas) {
            $partes = [];
            foreach ($reglas as $regla => $cantidad) {
                $partes[] = "'{$regla}' => {$cantidad}";
            }
            $lineas[] = "    '{$archivo}' => [" . implode(', ', $partes) . '],';
        }

        return "<?php\n\n"
            . "// Archivos con mensajes que aún no dicen qué pasó, por qué y qué hacer (ver RevisionMensajes).\n"
            . "// Solo puede bajar: al migrar un módulo, se actualiza con\n"
            . "//   php database/herramientas/verificar_mensajes.php --actualizar\n"
            . "// Generado automáticamente: no editar a mano.\n\n"
            . "return [\n" . implode("\n", $lineas) . ($lineas !== [] ? "\n" : '') . "];\n";
    }
}
