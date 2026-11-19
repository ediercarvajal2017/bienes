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

if (!function_exists('url_orden')) {
    /**
     * La dirección actual con otro orden por columna (o sin orden, con $clave null), de vuelta
     * a la página 1. Conserva la búsqueda y los filtros.
     */
    function url_orden(?string $clave, string $dir = 'asc'): string
    {
        $ruta = parse_url((string) ($_SERVER['REQUEST_URI'] ?? '/'), PHP_URL_PATH) ?: '/';
        $consulta = array_diff_key($_GET, ['pagina' => true, 'orden' => true, 'dir' => true]);
        if ($clave !== null) {
            $consulta += ['orden' => $clave, 'dir' => $dir === 'desc' ? 'desc' : 'asc'];
        }

        return $ruta . ($consulta !== [] ? '?' . http_build_query($consulta) : '');
    }
}

if (!function_exists('th_orden')) {
    /**
     * Título de columna que ordena el listado (App\Helpers\OrdenListado): un enlace con la
     * flecha del sentido y aria-sort en la columna ordenada. $columnas: clave => [título,
     * [texto ascendente, texto descendente]], p. ej. 'valor' => ['Valor', ['de menor a mayor', 'de mayor a menor']].
     *
     * @param array<string, array{0: string, 1: array{0: string, 1: string}}> $columnas
     */
    function th_orden(string $clave, array $columnas, string $clase = ''): string
    {
        [$texto, [$textoAsc, $textoDesc]] = $columnas[$clave];
        $actual = ($_GET['orden'] ?? null) === $clave;
        $dir = $actual && ($_GET['dir'] ?? '') === 'desc' ? 'desc' : 'asc';
        $siguiente = $actual && $dir === 'asc' ? 'desc' : 'asc';
        $accion = 'ordenar ' . ($siguiente === 'asc' ? $textoAsc : $textoDesc);
        $icono = $actual ? ($dir === 'asc' ? 'orden_ascendente' : 'orden_descendente') : 'ordenable';
        $e = static fn (string $s): string => htmlspecialchars($s, ENT_QUOTES);

        return '<th scope="col"' . ($clase !== '' ? ' class="' . $e($clase) . '"' : '')
            . ($actual ? ' aria-sort="' . ($dir === 'asc' ? 'ascending' : 'descending') . '"' : '') . '>'
            . '<a class="encabezado-orden' . ($actual ? ' activo' : '') . '" href="' . $e(url_orden($clave, $siguiente)) . '"'
            . ' aria-label="' . $e($texto . ', ' . $accion) . '" title="' . $e(mb_strtoupper(mb_substr($accion, 0, 1)) . mb_substr($accion, 1)) . '">'
            . $e($texto) . icono($icono, 'encabezado-orden-icono') . '</a></th>';
    }
}

if (!function_exists('filtro_orden')) {
    /**
     * El orden elegido como etiqueta para ui('filtros_activos') («Orden: Fecha, de la más
     * reciente…» con ✕ para volver al orden de siempre), o nada si no hay orden.
     *
     * @param array<string, array{0: string, 1: array{0: string, 1: string}}> $columnas
     * @return list<array{etiqueta: string, valor: string, urlQuitar: string}>
     */
    function filtro_orden(array $columnas): array
    {
        $orden = $_GET['orden'] ?? null;
        if (!is_string($orden) || !isset($columnas[$orden])) {
            return [];
        }
        $desc = ($_GET['dir'] ?? '') === 'desc';

        return [['etiqueta' => 'Orden', 'valor' => $columnas[$orden][0] . ', ' . $columnas[$orden][1][$desc ? 1 : 0], 'urlQuitar' => url_orden(null)]];
    }
}
