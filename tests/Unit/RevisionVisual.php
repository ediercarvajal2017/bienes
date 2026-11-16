<?php

declare(strict_types=1);

namespace Tests\Unit;

/**
 * Reglas del estándar visual que se pueden comprobar en el código de las vistas
 * (docs/guia-estilo-ui.md):
 *  - estilos: atributos style="…" (van en clases y tokens);
 *  - iconos: "bi bi-…" escrito a mano (va icono('clave'));
 *  - botones: variantes fuera de la jerarquía (btn-success, btn-warning, btn-outline-primary…).
 * Las piezas de partials/ui y el diccionario partials/iconos.php son la excepción: ahí vive
 * el marcado que las demás vistas reutilizan.
 *
 * Lo usan EstandarVisualTest (falla en CI) y database/herramientas/verificar_ui.php.
 */
final class RevisionVisual
{
    public const REGLAS = [
        'estilos' => '/\bstyle="/',
        'iconos' => '/\bbi bi-/',
        'botones' => '/\bbtn-(?:success|warning|info|light|dark|outline-(?:success|warning|info|primary|light|dark))\b/',
    ];

    /** Vistas permitidas (rutas relativas a app/Views, con /). */
    private const EXCEPCIONES = ['#^partials/ui/#', '#^partials/iconos\.php$#'];

    public static function carpetaVistas(): string
    {
        return dirname(__DIR__, 2) . '/app/Views';
    }

    public static function archivoPendientes(): string
    {
        return __DIR__ . '/datos/ui_pendientes.php';
    }

    /**
     * Cuántas veces rompe cada vista cada regla (solo las que rompen alguna).
     *
     * @return array<string, array<string, int>> vista => regla => cantidad
     */
    public static function conteo(): array
    {
        $raiz = self::carpetaVistas();
        $conteo = [];
        $archivos = new \RecursiveIteratorIterator(new \RecursiveDirectoryIterator($raiz, \FilesystemIterator::SKIP_DOTS));
        foreach ($archivos as $archivo) {
            if (!$archivo instanceof \SplFileInfo || $archivo->getExtension() !== 'php') {
                continue;
            }
            $vista = str_replace('\\', '/', substr($archivo->getPathname(), strlen($raiz) + 1));
            foreach (self::EXCEPCIONES as $excepcion) {
                if (preg_match($excepcion, $vista) === 1) {
                    continue 2;
                }
            }
            $codigo = (string) file_get_contents($archivo->getPathname());
            foreach (self::REGLAS as $regla => $patron) {
                $cantidad = preg_match_all($patron, $codigo);
                if ($cantidad > 0) {
                    $conteo[$vista][$regla] = $cantidad;
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
        foreach (array_unique([...array_keys($actual), ...array_keys($pendientes)]) as $vista) {
            foreach (array_keys(self::REGLAS) as $regla) {
                $hoy = $actual[$vista][$regla] ?? 0;
                $permitido = $pendientes[$vista][$regla] ?? 0;
                if ($hoy > $permitido) {
                    $subio[] = "{$vista}: {$hoy} {$regla} (permitido: {$permitido})";
                } elseif ($hoy < $permitido) {
                    $bajo[] = "{$vista}: {$hoy} {$regla} (la lista dice {$permitido})";
                }
            }
        }

        return ['subio' => $subio, 'bajo' => $bajo];
    }

    /** Código PHP de la lista de pendientes con el conteo actual. */
    public static function exportar(): string
    {
        $lineas = [];
        foreach (self::conteo() as $vista => $reglas) {
            $partes = [];
            foreach ($reglas as $regla => $cantidad) {
                $partes[] = "'{$regla}' => {$cantidad}";
            }
            $lineas[] = "    '{$vista}' => [" . implode(', ', $partes) . '],';
        }

        return "<?php\n\n"
            . "// Vistas que aún no cumplen el estándar visual y cuánto les falta (ver RevisionVisual).\n"
            . "// Solo puede bajar: al migrar una vista, se actualiza con\n"
            . "//   php database/herramientas/verificar_ui.php --actualizar\n"
            . "// Generado automáticamente: no editar a mano.\n\n"
            . "return [\n" . implode("\n", $lineas) . ($lineas !== [] ? "\n" : '') . "];\n";
    }
}
