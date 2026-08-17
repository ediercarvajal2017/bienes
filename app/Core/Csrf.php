<?php

declare(strict_types=1);

namespace App\Core;

final class Csrf
{
    public static function token(): string
    {
        if (!Session::has('_csrf_token')) {
            Session::put('_csrf_token', bin2hex(random_bytes(32)));
        }

        return Session::get('_csrf_token');
    }

    public static function field(): string
    {
        return '<input type="hidden" name="_csrf" value="' . htmlspecialchars(self::token(), ENT_QUOTES) . '">';
    }

    public static function verify(?string $token): bool
    {
        return is_string($token) && hash_equals(self::token(), $token);
    }

    /**
     * Verifica el token CSRF del request; si falla, deja el mensaje de siempre (y
     * opcionalmente los datos del formulario, para no perderlos) y redirige sin volver.
     * Antes este mismo bloque estaba copiado en 14 controladores.
     */
    public static function verificarORedirigir(Request $request, string $volverA, array $datosAConservar = []): void
    {
        if (self::verify((string) $request->input('_csrf'))) {
            return;
        }

        Session::flash('error', 'Tu sesión expiró, intenta de nuevo.');
        if ($datosAConservar !== []) {
            Session::flashOld($datosAConservar);
        }
        header('Location: ' . Url::to($volverA));
        exit;
    }
}
