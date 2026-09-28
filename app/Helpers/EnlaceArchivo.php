<?php

declare(strict_types=1);

namespace App\Helpers;

use App\Core\Auth;
use App\Core\Env;
use App\Core\Url;

/**
 * Dirección de un archivo subido (/archivos/{tipo}/{archivo}) firmada con la llave de la
 * aplicación para la institución de quien ve la página.
 *
 * Por qué: comprobar el permiso de cada foto exigía una consulta a la base por imagen, y un
 * listado con muchas miniaturas abría decenas de conexiones a la vez. Hostinger limita las
 * conexiones simultáneas y rechazaba algunas ("Operation not permitted"), con fotos que no
 * cargaban. La página que muestra el enlace ya comprobó el acceso; la firma lo certifica y
 * ArchivoController la verifica sin tocar la base. Sin firma válida (enlace viejo, otra
 * institución) se hace la comprobación de siempre contra la base.
 */
final class EnlaceArchivo
{
    public static function url(string $ruta, int $ancho = 0): string
    {
        $parametros = $ancho > 0 ? ['w' => $ancho] : [];
        // Caduca al terminar el día siguiente: la dirección no cambia en todo el día (el
        // navegador sigue guardando la foto en caché) y un enlace viejo deja de servir.
        $expira = (intdiv(time(), 86400) + 2) * 86400;
        $firma = self::firma($ruta, Auth::institucionId(), $expira);
        if ($firma !== null) {
            $parametros['e'] = $expira;
            $parametros['f'] = $firma;
        }

        return Url::to('/archivos/' . $ruta) . ($parametros !== [] ? '?' . http_build_query($parametros) : '');
    }

    public static function firmaValida(string $ruta, string $firma, int $expira, ?int $institucionId): bool
    {
        if ($firma === '' || $expira < time()) {
            return false;
        }
        $esperada = self::firma($ruta, $institucionId, $expira);

        return $esperada !== null && hash_equals($esperada, $firma);
    }

    private static function firma(string $ruta, ?int $institucionId, int $expira): ?string
    {
        $llave = trim((string) Env::get('APP_KEY', ''));
        if ($llave === '' || $institucionId === null) {
            return null;
        }

        return substr(hash_hmac('sha256', 'archivo|' . $institucionId . '|' . $expira . '|' . $ruta, $llave), 0, 32);
    }
}
