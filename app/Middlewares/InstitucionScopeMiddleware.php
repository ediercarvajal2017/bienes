<?php

declare(strict_types=1);

namespace App\Middlewares;

use App\Core\Auth;
use App\Core\MiddlewareInterface;
use App\Core\Request;
use App\Core\View;

final class InstitucionScopeMiddleware implements MiddlewareInterface
{
    public function handle(Request $request, callable $next, ?string $param = null): mixed
    {
        if (!Auth::esSuperusuario() && !Auth::institucionId()) {
            http_response_code(403);
            View::render('errors/_pagina', [
                'codigo' => 403,
                'titulo' => 'Usuario sin institución',
                'mensaje' => 'Su usuario no tiene una institución asignada. Pida al rector o al administrador que la configure.',
                'enlace' => '/login',
                'textoEnlace' => 'Volver al inicio de sesión',
            ]);
            exit;
        }

        return $next();
    }
}
