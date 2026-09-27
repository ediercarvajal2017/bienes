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

        return $next();
    }
}
