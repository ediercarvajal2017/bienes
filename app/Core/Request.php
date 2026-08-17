<?php

declare(strict_types=1);

namespace App\Core;

final class Request
{
    public readonly string $method;
    public readonly string $uri;

    public function __construct()
    {
        $this->method = $_SERVER['REQUEST_METHOD'] ?? 'GET';
        $this->uri = $this->resolvePath();
    }

    /**
     * El front controller puede vivir en una subcarpeta (p. ej. /gestionbienes/public
     * bajo el alias por defecto de WAMP). Se calcula esa base a partir de SCRIPT_NAME
     * y se descuenta de REQUEST_URI para obtener siempre una ruta de app tipo "/login".
     */
    private function resolvePath(): string
    {
        // parse_url() devuelve false (no null) ante una URL malformada -- "??" no lo
        // atrapa, así que una REQUEST_URI rara podía llegar a str_starts_with() como
        // false y tumbar la petición entera con un TypeError.
        $requestUri = parse_url($_SERVER['REQUEST_URI'] ?? '/', PHP_URL_PATH) ?: '/';
        $scriptDir = Url::base();

        if ($scriptDir !== '' && str_starts_with($requestUri, $scriptDir)) {
            $requestUri = substr($requestUri, strlen($scriptDir));
        }

        $requestUri = '/' . ltrim($requestUri, '/');

        return rtrim($requestUri, '/') === '' ? '/' : rtrim($requestUri, '/');
    }

    public function input(string $key, mixed $default = null): mixed
    {
        return $_POST[$key] ?? $_GET[$key] ?? $default;
    }

    public function all(): array
    {
        return array_merge($_GET, $_POST);
    }

    public function file(string $key): ?array
    {
        return $_FILES[$key] ?? null;
    }

    public function isPost(): bool
    {
        return $this->method === 'POST';
    }
}
