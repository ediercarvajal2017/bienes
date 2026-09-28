<?php

declare(strict_types=1);

namespace App\Middlewares;

use App\Core\Auth;
use App\Core\MiddlewareInterface;
use App\Core\Request;
use App\Core\Session;
use App\Core\Url;
use App\Helpers\PoliticaDatos;

final class AuthMiddleware implements MiddlewareInterface
{
    public function handle(Request $request, callable $next, ?string $param = null): mixed
    {
        if (!Auth::check()) {
            header('Location: ' . Url::to('/login'));
            exit;
        }

        // Política de datos sin aceptar: solo la pantalla de aceptación y cerrar sesión. Se
        // recuerda la página pedida (p. ej. la ficha de un bien desde un QR) para ir allí después.
        if (Session::get('politica_pendiente') && !in_array($request->uri, PoliticaDatos::RUTAS_PERMITIDAS, true)) {
            if ($request->method === 'GET') {
                $consulta = (string) ($_SERVER['QUERY_STRING'] ?? '');
                Session::put('politica_destino', $request->uri . ($consulta !== '' ? '?' . $consulta : ''));
            }
            header('Location: ' . Url::to('/politica/aceptar'));
            exit;
        }

        return $next();
    }
}
