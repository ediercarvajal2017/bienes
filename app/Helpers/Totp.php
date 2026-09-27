<?php

declare(strict_types=1);

namespace App\Helpers;

/**
 * Códigos de un solo uso basados en tiempo (TOTP, RFC 6238), los de Google/Microsoft
 * Authenticator, Authy o el gestor de contraseñas del teléfono: 6 dígitos que cambian
 * cada 30 segundos, calculados con HMAC-SHA1 a partir de una clave compartida.
 *
 * Implementación propia (sin dependencias): es un algoritmo corto y estándar, y así no
 * se agrega un paquete más al servidor de producción.
 */
final class Totp
{
    public const DIGITOS = 6;
    public const PERIODO = 30;
    private const ALFABETO_BASE32 = 'ABCDEFGHIJKLMNOPQRSTUVWXYZ234567';

    /** Clave nueva de 160 bits (lo que recomienda el RFC 4226), en base32 para el autenticador. */
    public static function generarSecreto(): string
    {
        return self::base32Codificar(random_bytes(20));
    }

    public static function pasoActual(?int $momento = null): int
    {
        return intdiv($momento ?? time(), self::PERIODO);
    }

    /** Código de 6 dígitos para un paso de tiempo dado. */
    public static function codigo(string $secretoBase32, int $paso): string
    {
        $clave = self::base32Decodificar($secretoBase32);
        $hmac = hash_hmac('sha1', pack('J', $paso), $clave, true);
        $desplazamiento = ord($hmac[19]) & 0x0F;
        $numero = ((ord($hmac[$desplazamiento]) & 0x7F) << 24)
            | (ord($hmac[$desplazamiento + 1]) << 16)
            | (ord($hmac[$desplazamiento + 2]) << 8)
            | ord($hmac[$desplazamiento + 3]);

        return str_pad((string) ($numero % 10 ** self::DIGITOS), self::DIGITOS, '0', STR_PAD_LEFT);
    }

    /**
     * Paso de tiempo en el que el código es válido, o null. Se tolera ±1 paso (±30 s)
     * de desfase entre el reloj del teléfono y el del servidor. Quien llama debe además
     * rechazar pasos ya usados (anti-repetición, ver DosFactoresService).
     */
    public static function pasoValido(string $secretoBase32, string $codigo, ?int $momento = null): ?int
    {
        $codigo = preg_replace('/\s+/', '', $codigo) ?? '';
        if (!preg_match('/^\d{' . self::DIGITOS . '}$/', $codigo)) {
            return null;
        }

        $actual = self::pasoActual($momento);
        foreach ([$actual - 1, $actual, $actual + 1] as $paso) {
            if (hash_equals(self::codigo($secretoBase32, $paso), $codigo)) {
                return $paso;
            }
        }

        return null;
    }

    /** URI que se codifica en el QR (formato "Key Uri" que entienden todos los autenticadores). */
    public static function uriOtpauth(string $secretoBase32, string $cuenta, string $emisor): string
    {
        return 'otpauth://totp/' . rawurlencode($emisor . ':' . $cuenta)
            . '?secret=' . $secretoBase32
            . '&issuer=' . rawurlencode($emisor)
            . '&algorithm=SHA1&digits=' . self::DIGITOS . '&period=' . self::PERIODO;
    }

    /** "JBSW Y3DP EHPK 3PXP": la clave en grupos de 4, para copiarla a mano sin errores. */
    public static function formatearParaMostrar(string $secretoBase32): string
    {
        return trim(chunk_split($secretoBase32, 4, ' '));
    }

    public static function base32Codificar(string $bytes): string
    {
        $bits = '';
        foreach (str_split($bytes) as $byte) {
            $bits .= str_pad(decbin(ord($byte)), 8, '0', STR_PAD_LEFT);
        }

        $resultado = '';
        foreach (str_split($bits, 5) as $grupo) {
            $resultado .= self::ALFABETO_BASE32[intval(str_pad($grupo, 5, '0'), 2)];
        }

        return $resultado;
    }

    public static function base32Decodificar(string $texto): string
    {
        $texto = strtoupper(preg_replace('/[\s=]+/', '', $texto) ?? '');
        $bits = '';
        foreach (str_split($texto) as $caracter) {
            $valor = strpos(self::ALFABETO_BASE32, $caracter);
            if ($valor === false) {
                throw new \InvalidArgumentException('Clave base32 inválida.');
            }
            $bits .= str_pad(decbin($valor), 5, '0', STR_PAD_LEFT);
        }

        $bytes = '';
        foreach (str_split($bits, 8) as $grupo) {
            if (strlen($grupo) === 8) {
                $bytes .= chr(intval($grupo, 2));
            }
        }

        return $bytes;
    }
}
