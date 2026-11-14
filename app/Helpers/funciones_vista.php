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
