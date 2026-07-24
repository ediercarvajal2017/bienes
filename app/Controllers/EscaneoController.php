<?php

declare(strict_types=1);

namespace App\Controllers;

use App\Core\View;

final class EscaneoController
{
    public function index(): void
    {
        View::layout('partials/layout', 'escanear/index', [
            'title' => 'Escanear código QR',
        ]);
    }
}
