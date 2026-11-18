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
