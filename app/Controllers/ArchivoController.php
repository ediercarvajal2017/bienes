<?php

declare(strict_types=1);

namespace App\Controllers;

use App\Core\Auth;
use App\Models\Archivo;

final class ArchivoController
{
    private const CARPETAS_PERMITIDAS = ['logos', 'fotos_usuarios', 'fotos_bienes', 'facturas', 'bajas', 'cartera', 'reintegros', 'plaqueteo'];

    public function mostrar(string $tipo, string $archivo): void
    {
        if (!in_array($tipo, self::CARPETAS_PERMITIDAS, true) || !preg_match('/^[A-Za-z0-9_-]+\.[a-z0-9]+$/', $archivo)) {
            http_response_code(404);
            exit;
        }

        if (!Auth::esSuperusuario()) {
            $institucionPropietaria = Archivo::institucionPropietaria($tipo, "{$tipo}/{$archivo}");
            if ($institucionPropietaria === null || $institucionPropietaria !== Auth::institucionId()) {
                http_response_code(404);
                exit;
            }
        }

        $config = require dirname(__DIR__, 2) . '/config/app.php';
        $path = $config['storage_path'] . "/uploads/{$tipo}/{$archivo}";

        if (!is_file($path)) {
            http_response_code(404);
            exit;
        }

        header('Content-Type: ' . mime_content_type($path));
        header('Cache-Control: private, max-age=3600');
        readfile($path);
        exit;
    }
}
