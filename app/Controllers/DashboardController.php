<?php

declare(strict_types=1);

namespace App\Controllers;

use App\Core\Auth;
use App\Core\View;

final class DashboardController
{
    public function index(): void
    {
        View::layout('partials/layout', 'dashboard/index', [
            'title' => 'Panel principal',
        ]);
    }
}
