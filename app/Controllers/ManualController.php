<?php

declare(strict_types=1);

namespace App\Controllers;

use App\Core\Auth;
use App\Core\View;

final class ManualController
{
    public function index(): void
    {
        View::layout('partials/layout', 'manual/index', [
            'title' => 'Guía rápida',
            'rol' => Auth::rol(),
        ]);
    }
}
