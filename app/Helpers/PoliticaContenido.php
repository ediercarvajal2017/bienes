<?php

declare(strict_types=1);

namespace App\Helpers;

/**
 * Política de seguridad de contenido (CSP), por ahora en modo "solo reportar": el navegador
 * avisa en la consola lo que bloquearía, sin bloquearlo. La prueba
 * tests/archivos_qr_csp.spec.js recorre las pantallas y falla si aparece un aviso; cuando
 * esté limpia en producción se pasa a obligatoria cambiando la cabecera.
 */
final class PoliticaContenido
{
    /**
     * @param bool $permitirEvaluacion solo para la pantalla que usa TensorFlow ("Buscar por
     *        foto"), que compila código y WebAssembly en el navegador.
     */
    public static function enviar(bool $permitirEvaluacion = false): void
    {
        $scripts = "'self' 'unsafe-inline' https://cdn.jsdelivr.net" . ($permitirEvaluacion ? " 'unsafe-eval' 'wasm-unsafe-eval'" : '');

        header('Content-Security-Policy-Report-Only: ' . implode('; ', [
            "default-src 'self'",
            "script-src {$scripts}",
            "style-src 'self' 'unsafe-inline' https://cdn.jsdelivr.net",
            "font-src 'self' data: https://cdn.jsdelivr.net",
            "img-src 'self' data: blob:",
            "media-src 'self' blob:",
            "worker-src 'self' blob:",
            "connect-src 'self' https://cdn.jsdelivr.net https://tfhub.dev https://www.kaggle.com https://storage.googleapis.com",
            "object-src 'none'",
            "base-uri 'self'",
            "form-action 'self'",
            "frame-ancestors 'self'",
        ]));
    }
}
