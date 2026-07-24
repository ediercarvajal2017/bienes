<?php

declare(strict_types=1);

namespace App\Helpers;

use App\Core\Auth;
use App\Core\View;

/**
 * Utilidades comunes a los módulos de biblioteca de evidencia (cartera, formatos de
 * reintegro, formatos de plaqueteo, facturas): verificación de acceso por institución
 * y borrado del archivo físico asociado a un registro.
 */
final class Evidencia
{
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
}
