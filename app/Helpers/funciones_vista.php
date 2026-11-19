<?php

declare(strict_types=1);

/**
 * Funciones globales para las vistas. Se cargan en public/index.php (y en el arranque de
 * PHPUnit) justo después del autoload de Composer.
 */

if (!function_exists('icono')) {
    /** Ícono del diccionario de MIA: icono('editar') → <i class="bi bi-pencil" aria-hidden="true"></i>. */
    function icono(string $clave, string $clases = '', bool $activo = false): string
    {
        return \App\Helpers\Icono::html($clave, $clases, $activo);
    }
}

if (!function_exists('error_campo')) {
    /**
     * Mensaje que el aviso de error de la acción anterior dejó para el campo $campo
     * (App\Core\Aviso::errorDeCampo), listo para ponerlo debajo del control; '' si no hay.
     * Para los controles escritos a mano; ui('campo_formulario') ya lo hace solo.
     */
    function error_campo(string $campo): string
    {
        $mensaje = \App\Core\Aviso::errorDeCampo($campo);

        return $mensaje === null ? '' : '<div class="invalid-feedback d-block" id="' . htmlspecialchars($campo, ENT_QUOTES)
            . '-error">' . htmlspecialchars($mensaje, ENT_QUOTES) . '</div>';
    }

    /** ' is-invalid' si el aviso de error de la acción anterior marcó el campo $campo. */
    function clase_invalido(string $campo): string
    {
        return \App\Core\Aviso::errorDeCampo($campo) === null ? '' : ' is-invalid';
    }
}

if (!function_exists('ui')) {
    /**
     * HTML de un componente de app/Views/partials/ui (docs/guia-estilo-ui.md):
     * ui('boton', ['texto' => 'Guardar', 'tipo' => 'primario']).
     *
     * @param array<string, mixed> $datos
     */
    function ui(string $componente, array $datos = []): string
    {
        if (preg_match('/^[a-z_]+$/', $componente) !== 1) {
            throw new \InvalidArgumentException("Componente no válido: \"{$componente}\".");
        }
        ob_start();
        try {
            \App\Core\View::render('partials/ui/' . $componente, $datos);
        } catch (\Throwable $e) {
            ob_end_clean();
            throw $e;
        }

        return (string) ob_get_clean();
    }
}

if (!function_exists('resaltar')) {
    /**
     * El texto escapado para HTML, con cada aparición de lo buscado marcada con
     * <mark class="coincidencia">: así se ve por qué una fila salió en la búsqueda. Igual que
     * la búsqueda de MySQL (utf8mb4_unicode_ci), no distingue mayúsculas ni tildes: «silla»
     * marca «SILLA» y «Sílla». Sin búsqueda, solo escapa.
     */
    function resaltar(?string $texto, ?string $busqueda): string
    {
        $texto ??= '';
        $busqueda = trim((string) $busqueda);
        $escapar = static fn (string $s): string => htmlspecialchars($s, ENT_QUOTES);
        if ($texto === '' || $busqueda === '') {
            return $escapar($texto);
        }

        // Un carácter normalizado por cada carácter original, para ubicar las coincidencias
        // en el texto tal como está.
        $normalizar = static function (string $s): array {
            $sinTildes = ['á' => 'a', 'à' => 'a', 'ä' => 'a', 'â' => 'a', 'ã' => 'a', 'é' => 'e', 'è' => 'e', 'ë' => 'e', 'ê' => 'e',
                'í' => 'i', 'ì' => 'i', 'ï' => 'i', 'î' => 'i', 'ó' => 'o', 'ò' => 'o', 'ö' => 'o', 'ô' => 'o', 'õ' => 'o',
                'ú' => 'u', 'ù' => 'u', 'ü' => 'u', 'û' => 'u', 'ñ' => 'n', 'ç' => 'c'];

            return array_map(static function (string $c) use ($sinTildes): string {
                $n = strtr(mb_strtolower($c), $sinTildes);

                return mb_strlen($n) === 1 ? $n : $c;
            }, mb_str_split($s));
        };
        $caracteres = mb_str_split($texto);
        $pajar = implode('', $normalizar($texto));
        $aguja = implode('', $normalizar($busqueda));
        $largo = mb_strlen($aguja);

        $html = '';
        $desde = 0;
        while (($pos = mb_strpos($pajar, $aguja, $desde)) !== false) {
            $html .= $escapar(implode('', array_slice($caracteres, $desde, $pos - $desde)))
                . '<mark class="coincidencia">' . $escapar(implode('', array_slice($caracteres, $pos, $largo))) . '</mark>';
            $desde = $pos + $largo;
        }

        return $html . $escapar(implode('', array_slice($caracteres, $desde)));
    }
}
