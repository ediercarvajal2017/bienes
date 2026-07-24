<?php

declare(strict_types=1);

namespace App\Middlewares;

use App\Core\Auth;
use App\Core\MiddlewareInterface;
use App\Core\Request;

final class InstitucionScopeMiddleware implements MiddlewareInterface
{
    public function handle(Request $request, callable $next, ?string $param = null): mixed
    {
        if (!Auth::esSuperusuario() && !Auth::institucionId()) {
            http_response_code(403);
            exit('Tu usuario no tiene una institución asignada.');
        }

        return $next();
    }
}
