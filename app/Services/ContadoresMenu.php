<?php

declare(strict_types=1);

namespace App\Services;

use App\Core\Auth;
use App\Core\ErrorHandler;
use App\Core\Session;
use App\Models\Baja;
use App\Models\Hallazgo;
use App\Models\SolicitudReintegro;

/**
 * Pendientes que el menú muestra junto a cada opción (bajas por aprobar, solicitudes de
 * reintegro por revisar, hallazgos de verificación por resolver). Mismas reglas que los
 * indicadores del panel principal (DashboardController::indicadores): cada cifra solo se
 * calcula para quien puede actuar sobre ella.
 *
 * Se guardan en la sesión SEGUNDOS segundos para no sumar tres consultas a cada página
 * (Hostinger limita las conexiones simultáneas), y se descartan en cuanto el usuario
 * envía un formulario (ver public/index.php), para que al aprobar una baja el número
 * baje enseguida.
 */
final class ContadoresMenu
{
    private const CLAVE = 'contadores_menu';
    private const SEGUNDOS = 60;

    /** @return array<string, int> */
    public static function obtener(): array
    {
        $institucionId = Auth::esSuperusuario() ? Auth::filtroInstitucionId() : Auth::institucionId();
        $guardado = Session::get(self::CLAVE);
        if (is_array($guardado) && $guardado['institucion'] === $institucionId && time() - (int) $guardado['en'] < self::SEGUNDOS) {
            return $guardado['valores'];
        }

        $puede = static fn (string $permiso): bool => Auth::esSuperusuario() || Auth::tienePermiso($permiso);
        $valores = [];
        try {
            if ($puede('bajas.aprobar')) {
                $valores['bajas'] = Baja::contarPendientes($institucionId);
            }
            if ($puede('asignaciones.crear')) {
                $valores['solicitudes'] = SolicitudReintegro::contar($institucionId, null, 'pendiente');
            }
            if ($puede('verificaciones.gestionar')) {
                $valores['hallazgos'] = Hallazgo::contarPendientesInstitucion($institucionId);
            }
        } catch (\Throwable $e) {
            // Un contador que falla no debe tumbar la página: el menú se muestra sin números.
            ErrorHandler::reportar($e, __METHOD__);

            return [];
        }

        Session::put(self::CLAVE, ['institucion' => $institucionId, 'en' => time(), 'valores' => $valores]);

        return $valores;
    }

    public static function invalidar(): void
    {
        Session::put(self::CLAVE, null);
    }
}
