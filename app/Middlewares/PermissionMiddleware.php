<?php

declare(strict_types=1);

namespace App\Middlewares;

use App\Core\Auth;
use App\Core\MiddlewareInterface;
use App\Core\Request;
use App\Core\View;

final class PermissionMiddleware implements MiddlewareInterface
{
    public function handle(Request $request, callable $next, ?string $param = null): mixed
    {
        if ($param !== null && !Auth::esSuperusuario() && !Auth::tienePermiso($param)) {
            http_response_code(403);
            View::render('errors/403');
            exit;
        }

        return $next();
    }
}
