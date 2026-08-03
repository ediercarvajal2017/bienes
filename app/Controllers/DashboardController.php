<?php

declare(strict_types=1);

namespace App\Controllers;

use App\Core\Auth;
use App\Core\View;
use App\Models\Bien;
use App\Models\Espacio;

final class DashboardController
{
    public function index(): void
    {
        View::layout('partials/layout', 'dashboard/index', [
            'title' => 'Panel principal',
            'primerosPasos' => $this->primerosPasos(),
        ]);
    }

    /**
     * Checklist de arranque, solo para quien puede configurar la institución
     * (rector/secretario/superusuario con institución) y solo mientras esa
     * institución todavía no tiene ningún bien registrado — una vez que ya se
     * usa el sistema con normalidad, esta guía deja de ser relevante y no
     * vuelve a mostrarse.
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
        if ($totalBienes > 0) {
            return null;
        }

        $totalEspacios = Espacio::contarListado($institucionId);

        return [
            'totalEspacios' => $totalEspacios,
            'totalBienes' => $totalBienes,
        ];
    }
}
