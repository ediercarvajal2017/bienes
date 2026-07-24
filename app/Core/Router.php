<?php

declare(strict_types=1);

namespace App\Core;

final class Router
{
    private array $routes = [];

    public function get(string $path, array $action, array $middlewares = []): void
    {
        $this->routes[] = ['method' => 'GET', 'path' => $path, 'action' => $action, 'middlewares' => $middlewares];
    }

    public function post(string $path, array $action, array $middlewares = []): void
    {
        $this->routes[] = ['method' => 'POST', 'path' => $path, 'action' => $action, 'middlewares' => $middlewares];
    }

    public function dispatch(Request $request): void
    {
        foreach ($this->routes as $route) {
            if ($route['method'] !== $request->method) {
                continue;
            }

            $params = $this->match($route['path'], $request->uri);
            if ($params === null) {
                continue;
            }

            $final = function () use ($route, $params) {
                [$controllerClass, $method] = $route['action'];
                (new $controllerClass())->$method(...array_values($params));
            };

            $this->runMiddlewares($route['middlewares'], $request, $final);

            return;
        }

        http_response_code(404);
        View::render('errors/404');
    }

    private function match(string $routePath, string $uri): ?array
    {
        $pattern = preg_replace('#\{([a-zA-Z_]+)\}#', '(?P<$1>[^/]+)', $routePath);
        $pattern = '#^' . $pattern . '$#';

        if (!preg_match($pattern, $uri, $matches)) {
            return null;
        }

        $params = [];
        foreach ($matches as $key => $value) {
            if (is_string($key)) {
                $params[$key] = $value;
            }
        }

        return $params;
    }

    private function runMiddlewares(array $middlewares, Request $request, callable $final): void
    {
        $next = $final;

        foreach (array_reverse($middlewares) as $entry) {
            [$middlewareClass, $param] = array_pad(explode(':', $entry, 2), 2, null);
            $middleware = new $middlewareClass();
            $currentNext = $next;
            $next = fn () => $middleware->handle($request, $currentNext, $param);
        }

        $next();
    }
}
