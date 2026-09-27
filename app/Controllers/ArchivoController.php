<?php

declare(strict_types=1);

namespace App\Controllers;

use App\Core\Auth;
use App\Core\Database;
use App\Models\Archivo;

final class ArchivoController
{
    /** Anchos de miniatura permitidos (una lista fija: nadie puede llenar el disco pidiendo tamaños arbitrarios). */
    private const ANCHOS_MINIATURA = [96, 480];
    private const CARPETAS_PERMITIDAS = ['logos', 'fotos_usuarios', 'fotos_bienes', 'facturas', 'bajas', 'cartera', 'reintegros', 'plaqueteo', 'hallazgos'];

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

        // Permiso ya verificado: se liberan la sesión y la conexión a la base antes de
        // generar la miniatura y enviar el archivo. Si no, la sesión queda bloqueada durante
        // todo el envío y las demás fotos de la página esperan en fila ocupando procesos del
        // hosting (que termina rechazando conexiones nuevas a la base).
        session_write_close();
        Database::desconectar();

        $config = require dirname(__DIR__, 2) . '/config/app.php';
        $path = $config['storage_path'] . "/uploads/{$tipo}/{$archivo}";

        if (!is_file($path)) {
            http_response_code(404);
            exit;
        }

        // ?w=96 o ?w=480: versión reducida para listados (antes cada miniatura de 36 px
        // descargaba la foto completa, varios MB por página en el celular).
        $ancho = (int) ($_GET['w'] ?? 0);
        if (in_array($ancho, self::ANCHOS_MINIATURA, true) && ($miniatura = $this->miniatura($config['storage_path'], $tipo, $archivo, $path, $ancho))) {
            $path = $miniatura;
        }

        header('Content-Type: ' . mime_content_type($path));
        header('Cache-Control: private, max-age=3600');
        readfile($path);
        exit;
    }

    /**
     * Miniatura WebP en caché de disco (storage/uploads/_miniaturas/{ancho}/{tipo}/),
     * regenerada si la foto original cambió. Se escala para que el lado MENOR mida el
     * ancho pedido (las vistas la recortan con object-fit: cover) y nunca se agranda.
     * Devuelve null si no es una imagen o no se pudo generar: se sirve el original.
     */
    private function miniatura(string $storage, string $tipo, string $archivo, string $original, int $ancho): ?string
    {
        if (!function_exists('imagewebp') || !preg_match('/\.(jpe?g|png|webp|gif)$/i', $archivo)) {
            return null;
        }

        $destino = "{$storage}/uploads/_miniaturas/{$ancho}/{$tipo}/" . pathinfo($archivo, PATHINFO_FILENAME) . '.webp';
        if (is_file($destino) && filemtime($destino) >= filemtime($original)) {
            return $destino;
        }

        $info = @getimagesize($original);
        if ($info === false || $info[0] < 1 || $info[1] < 1) {
            return null;
        }

        $imagen = match ($info[2]) {
            IMAGETYPE_JPEG => @imagecreatefromjpeg($original),
            IMAGETYPE_PNG => @imagecreatefrompng($original),
            IMAGETYPE_WEBP => @imagecreatefromwebp($original),
            IMAGETYPE_GIF => @imagecreatefromgif($original),
            default => false,
        };
        if ($imagen === false) {
            return null;
        }

        $escala = min(1, $ancho / min($info[0], $info[1]));
        $nuevoAncho = max(1, (int) round($info[0] * $escala));
        $nuevoAlto = max(1, (int) round($info[1] * $escala));
        $reducida = imagescale($imagen, $nuevoAncho, $nuevoAlto, IMG_BICUBIC);
        imagedestroy($imagen);
        if ($reducida === false) {
            return null;
        }

        if (!is_dir(dirname($destino)) && !@mkdir(dirname($destino), 0755, true) && !is_dir(dirname($destino))) {
            imagedestroy($reducida);

            return null;
        }

        // Se escribe en un temporal y se renombra: dos peticiones simultáneas nunca
        // sirven una miniatura a medio escribir.
        $temporal = $destino . '.' . bin2hex(random_bytes(4)) . '.tmp';
        $ok = imagewebp($reducida, $temporal, 80);
        imagedestroy($reducida);

        if (!$ok || !@rename($temporal, $destino)) {
            @unlink($temporal);

            return null;
        }

        return $destino;
    }
}
