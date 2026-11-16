<?php

declare(strict_types=1);

namespace App\Helpers;

use App\Core\Auth;
use App\Core\Url;
use App\Core\View;

/**
 * Utilidades comunes a los módulos de biblioteca de evidencia (cartera, formatos de
 * reintegro, formatos de plaqueteo, facturas). Cada uno es UNA sola ventana: el formulario
 * (registrar, o editar con ?editar=ID) y debajo la lista de registros con Descargar,
 * Editar y Eliminar. Aquí están la verificación de acceso por institución, el borrado del
 * archivo físico, la paginación y la institución de la ventana.
 */
final class Evidencia
{
    public const POR_PAGINA_DEFECTO = 50;
    public const OPCIONES_POR_PAGINA = [10, 25, 50, 100, 0];

    /**
     * @phpstan-assert array $registro
     */
    public static function verificarAcceso(?array $registro): void
    {
        if (!$registro) {
            http_response_code(404);
            View::render('errors/404');
            exit;
        }

        if (!Auth::esSuperusuario() && (int) $registro['institucion_id'] !== Auth::institucionId()) {
            http_response_code(403);
            View::render('errors/403');
            exit;
        }
    }

    public static function eliminarArchivoFisico(string $rutaRelativa): void
    {
        $config = require dirname(__DIR__, 2) . '/config/app.php';
        $path = $config['storage_path'] . '/uploads/' . $rutaRelativa;

        if (is_file($path)) {
            @unlink($path);
        }
    }

    /** @return array{0: int, 1: int} página y cantidad por página (0 = todas) */
    public static function paginacion(): array
    {
        $porPagina = (int) ($_GET['porPagina'] ?? self::POR_PAGINA_DEFECTO);

        return [
            max(1, (int) ($_GET['pagina'] ?? 1)),
            in_array($porPagina, self::OPCIONES_POR_PAGINA, true) ? $porPagina : self::POR_PAGINA_DEFECTO,
        ];
    }

    /**
     * Institución de la ventana: la del usuario; el superusuario, la que eligió en la
     * ventana (o en el formulario), o si no, la del filtro de la barra superior. 0 = ninguna.
     */
    public static function institucionDeVentana(): int
    {
        if (!Auth::esSuperusuario()) {
            return (int) Auth::institucionId();
        }
        $elegida = (int) ($_GET['institucion'] ?? $_POST['institucion_id'] ?? 0);

        return $elegida > 0 ? $elegida : (int) (Auth::filtroInstitucionId() ?? 0);
    }

    /**
     * Dirección de la ventana (ruta de la app, sin resolver), con la institución si es el
     * superusuario y los parámetros extra (p. ej. ['editar' => 5]).
     *
     * @param array<string, int|string> $extra
     */
    public static function ruta(string $ventana, int $institucionId, array $extra = []): string
    {
        $consulta = (Auth::esSuperusuario() && $institucionId > 0 ? ['institucion' => $institucionId] : []) + $extra;

        return $ventana . ($consulta !== [] ? '?' . http_build_query($consulta) : '');
    }

    /** Redirige a la ventana (o a una de sus direcciones) y termina. */
    public static function redirigir(string $ruta): never
    {
        header('Location: ' . Url::to($ruta));
        exit;
    }
}
