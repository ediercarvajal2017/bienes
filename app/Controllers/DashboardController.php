<?php

declare(strict_types=1);

namespace App\Controllers;

use App\Core\Auth;
use App\Core\View;
use App\Models\Baja;
use App\Models\Bien;
use App\Models\Espacio;
use App\Models\Hallazgo;

final class DashboardController
{
    public function index(): void
    {
        View::layout('partials/layout', 'dashboard/index', [
            'title' => 'Panel principal',
            'primerosPasos' => $this->primerosPasos(),
            'indicadores' => $this->indicadores(),
        ]);
    }

    /**
     * Lo que necesita atención hoy, no todo lo que existe: cada indicador solo aparece
     * si el usuario tiene permiso para actuar sobre él y solo si hay algo pendiente
     * (en 0 no aporta nada, sería ruido en un panel que ya tiene muchos accesos).
     */
    private function indicadores(): array
    {
        $institucionId = Auth::esSuperusuario() ? Auth::filtroInstitucionId() : Auth::institucionId();
        $indicadores = [];

        if (Auth::esSuperusuario() || Auth::tienePermiso('bajas.aprobar')) {
            $pendientes = Baja::contarPendientes($institucionId);
            if ($pendientes > 0) {
                $indicadores[] = [
                    'icono' => 'exclamation-triangle',
                    'texto' => $pendientes === 1 ? '1 baja pendiente de aprobar' : "{$pendientes} bajas pendientes de aprobar",
                    'ruta' => '/bajas',
                    'color' => 'warning',
                ];
            }
        }

        if (Auth::esSuperusuario() || Auth::tienePermiso('bienes.ver')) {
            $sinAsignar = Bien::contarSinAsignar($institucionId);
            if ($sinAsignar > 0) {
                $indicadores[] = [
                    'icono' => 'box-seam',
                    'texto' => $sinAsignar === 1 ? '1 bien sin asignar' : "{$sinAsignar} bienes sin asignar",
                    'ruta' => '/asignaciones',
                    'color' => 'secondary',
                ];
            }
        }

        if (Auth::esSuperusuario() || Auth::tienePermiso('verificaciones.gestionar')) {
            $hallazgos = Hallazgo::contarPendientesInstitucion($institucionId);
            if ($hallazgos > 0) {
                $indicadores[] = [
                    'icono' => 'flag',
                    'texto' => $hallazgos === 1 ? '1 hallazgo pendiente por resolver' : "{$hallazgos} hallazgos pendientes por resolver",
                    'ruta' => '/verificaciones',
                    'color' => 'info',
                ];
            }
        }

        return $indicadores;
    }

    /**
     * Checklist de arranque, solo para quien puede configurar la institución
     * (rector/secretario/superusuario con institución). Se muestra expandida
     * mientras la institución todavía no tiene ningún bien registrado; en cuanto
     * ya tiene alguno, queda colapsada (no vuelve a interrumpir) pero sigue
     * disponible con un clic — antes desaparecía del todo y no había forma de
     * volver a consultarla.
     */
    private function primerosPasos(): ?array
    {
        $institucionId = Auth::institucionId();

        if (Auth::esSuperusuario() || $institucionId === null) {
            return null;
        }

        if (!Auth::tienePermiso('bienes.crear') && !Auth::tienePermiso('espacios.crear')) {
            return null;
        }

        $totalBienes = Bien::contarListado($institucionId);

        // Una vez que ya hay bienes, la tarjeta queda colapsada como simple referencia
        // (ver dashboard/index.php) y ya no necesita el conteo exacto de espacios para
        // decidir nada -- evita sumar una segunda consulta a esta pantalla, la más
        // visitada del sistema, en cada carga para siempre.
        $totalEspacios = $totalBienes === 0 ? Espacio::contarListado($institucionId) : null;

        return [
            'totalEspacios' => $totalEspacios,
            'totalBienes' => $totalBienes,
        ];
    }
}
