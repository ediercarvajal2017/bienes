<?php

declare(strict_types=1);

namespace App\Middlewares;

use App\Core\Auth;
use App\Core\MiddlewareInterface;
use App\Core\Request;
use App\Core\Url;

final class AuthMiddleware implements MiddlewareInterface
{
    public function handle(Request $request, callable $next, ?string $param = null): mixed
    {
        if (!Auth::check()) {
            header('Location: ' . Url::to('/login'));
            exit;
        }

        // Rol con verificación en dos pasos obligatoria y plazo de gracia vencido: antes de
        // cualquier otra pantalla, debe configurarla (o cerrar sesión).
        if (Auth::debeConfigurarDosFactores() && !$this->permitidaSinDosFactores($request->uri)) {
            header('Location: ' . Url::to('/2fa/configurar'));
            exit;
        }

        return $next();
    }

    private function permitidaSinDosFactores(string $ruta): bool
    {
        return str_starts_with($ruta, '/2fa/') || $ruta === '/logout';
    }
}
