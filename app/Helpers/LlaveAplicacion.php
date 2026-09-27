<?php

declare(strict_types=1);

namespace App\Helpers;

use App\Core\Env;

/**
 * Cifra datos sensibles guardados en la base (hoy: la clave del autenticador de cada
 * usuario) con la llave APP_KEY del .env, usando libsodium (XSalsa20-Poly1305). Así, una
 * copia de la base de datos —un respaldo que se filtre, por ejemplo— no basta para
 * generar códigos de verificación de nadie.
 *
 * APP_KEY: 32 bytes en base64 (se acepta también con el prefijo "base64:"). Se genera
 * una sola vez con:
 *     php -r "echo base64_encode(random_bytes(32)), PHP_EOL;"
 * Debe respaldarse FUERA del servidor. Si se pierde, nadie pierde datos del sistema, pero
 * quienes tenían la verificación en dos pasos deben entrar con un código de recuperación
 * (o pedir que se la restablezcan) y volver a configurarla.
 */
final class LlaveAplicacion
{
    public static function disponible(): bool
    {
        return self::llave() !== null;
    }

    public static function cifrar(string $texto): string
    {
        $llave = self::llave() ?? throw new \RuntimeException('APP_KEY no está configurada.');
        $nonce = random_bytes(SODIUM_CRYPTO_SECRETBOX_NONCEBYTES);

        return $nonce . sodium_crypto_secretbox($texto, $nonce, $llave);
    }

    /** null si no hay llave o si el dato no se cifró con la llave actual (p. ej. APP_KEY cambió). */
    public static function descifrar(string $cifrado): ?string
    {
        $llave = self::llave();
        if ($llave === null || strlen($cifrado) <= SODIUM_CRYPTO_SECRETBOX_NONCEBYTES) {
            return null;
        }

        $nonce = substr($cifrado, 0, SODIUM_CRYPTO_SECRETBOX_NONCEBYTES);
        $texto = sodium_crypto_secretbox_open(substr($cifrado, SODIUM_CRYPTO_SECRETBOX_NONCEBYTES), $nonce, $llave);

        return $texto === false ? null : $texto;
    }

    private static function llave(): ?string
    {
        if (!function_exists('sodium_crypto_secretbox')) {
            return null;
        }

        $valor = trim((string) Env::get('APP_KEY', ''));
        if (str_starts_with($valor, 'base64:')) {
            $valor = substr($valor, 7);
        }

        $llave = base64_decode($valor, true);

        return $llave !== false && strlen($llave) === SODIUM_CRYPTO_SECRETBOX_KEYBYTES ? $llave : null;
    }
}
