<?php

declare(strict_types=1);

namespace App\Controllers;

use App\Core\Auth;
use App\Core\View;
use App\Helpers\Icono;

final class ManualController
{
    public function index(): void
    {
        View::layout('partials/layout', 'manual/index', [
            'title' => 'Guía rápida',
            'rol' => Auth::rol(),
        ]);
    }

    /** Guía visual interna (solo superusuario): tokens, íconos y componentes (docs/guia-estilo-ui.md). */
    public function ui(): void
    {
        View::layout('partials/layout', 'manual/ui', [
            'title' => 'Guía visual',
            'iconos' => Icono::mapa(),
        ]);
    }
}
