<?php

declare(strict_types=1);

namespace App\Core;

final class View
{
    public static function render(string $view, array $data = []): void
    {
        $viewFile = dirname(__DIR__) . "/Views/{$view}.php";

        if (!is_file($viewFile)) {
            throw new \RuntimeException("Vista no encontrada: {$view}");
        }

        extract($data, EXTR_SKIP);
        require $viewFile;
    }

    public static function layout(string $layout, string $view, array $data = []): void
    {
        $viewFile = dirname(__DIR__) . "/Views/{$view}.php";
        $layoutFile = dirname(__DIR__) . "/Views/{$layout}.php";

        if (!is_file($viewFile)) {
            throw new \RuntimeException("Vista no encontrada: {$view}");
        }
        if (!is_file($layoutFile)) {
            throw new \RuntimeException("Layout no encontrado: {$layout}");
        }

        $content = function () use ($viewFile, $data) {
            extract($data, EXTR_SKIP);
            require $viewFile;
        };

        extract($data, EXTR_SKIP);
        require $layoutFile;
    }
}
