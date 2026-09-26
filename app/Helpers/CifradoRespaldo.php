<?php

declare(strict_types=1);

namespace App\Helpers;

/**
 * Cifra y descifra los respaldos con AES-256-CBC + PBKDF2 (SHA-256) en el formato
 * estándar de OpenSSL ("Salted__" + sal + datos cifrados). Así un respaldo se puede abrir
 * aun sin SIGEBI (p. ej. si se pierde el servidor), en cualquier equipo con OpenSSL:
 *
 *   openssl enc -d -aes-256-cbc -pbkdf2 -iter 200000 -md sha256 \
 *       -in respaldo.sql.gz.enc -out respaldo.sql.gz
 *
 * Trabaja por bloques: no carga el archivo completo en memoria.
 */
final class CifradoRespaldo
{
    public const ITERACIONES = 200000;
    private const CIFRADO = 'aes-256-cbc';
    private const BLOQUE = 1048576; // 1 MB, múltiplo de 16 (tamaño de bloque de AES)

    public static function comandoDescifrado(string $archivo): string
    {
        $salida = preg_replace('/\.enc$/', '', $archivo);

        return 'openssl enc -d -aes-256-cbc -pbkdf2 -iter ' . self::ITERACIONES
            . " -md sha256 -in {$archivo} -out {$salida}";
    }

    public static function cifrar(string $origen, string $destino, string $clave): void
    {
        $sal = random_bytes(8);
        [$llave, $iv] = self::derivar($clave, $sal);

        $entrada = self::abrir($origen, 'rb');
        $salida = self::abrir($destino, 'wb');
        fwrite($salida, 'Salted__' . $sal);

        $bloque = (string) fread($entrada, self::BLOQUE);
        while (true) {
            $siguiente = feof($entrada) ? '' : (string) fread($entrada, self::BLOQUE);
            $esUltimo = $siguiente === '' && feof($entrada);

            // Bloques intermedios: sin relleno, encadenando el IV con el último bloque
            // cifrado (resultado idéntico a cifrar todo de una vez). El último lleva el
            // relleno PKCS#7 estándar.
            $opciones = OPENSSL_RAW_DATA | ($esUltimo ? 0 : OPENSSL_ZERO_PADDING);
            $cifrado = openssl_encrypt($bloque, self::CIFRADO, $llave, $opciones, $iv);
            if ($cifrado === false) {
                throw new \RuntimeException('No se pudo cifrar el respaldo.');
            }
            fwrite($salida, $cifrado);

            if ($esUltimo) {
                break;
            }
            $iv = substr($cifrado, -16);
            $bloque = $siguiente;
        }

        fclose($entrada);
        fclose($salida);
    }

    public static function descifrar(string $origen, string $destino, string $clave): void
    {
        $entrada = self::abrir($origen, 'rb');
        $cabecera = (string) fread($entrada, 16);
        if (strlen($cabecera) !== 16 || !str_starts_with($cabecera, 'Salted__')) {
            fclose($entrada);
            throw new \RuntimeException('El archivo no es un respaldo cifrado de SIGEBI (falta la cabecera de OpenSSL).');
        }
        [$llave, $iv] = self::derivar($clave, substr($cabecera, 8, 8));

        $salida = self::abrir($destino, 'wb');
        $bloque = (string) fread($entrada, self::BLOQUE);
        while (true) {
            $siguiente = feof($entrada) ? '' : (string) fread($entrada, self::BLOQUE);
            $esUltimo = $siguiente === '' && feof($entrada);

            $opciones = OPENSSL_RAW_DATA | ($esUltimo ? 0 : OPENSSL_ZERO_PADDING);
            $claro = openssl_decrypt($bloque, self::CIFRADO, $llave, $opciones, $iv);
            if ($claro === false) {
                fclose($entrada);
                fclose($salida);
                @unlink($destino);
                throw new \RuntimeException('No se pudo descifrar el respaldo: la contraseña es incorrecta o el archivo está dañado.');
            }
            fwrite($salida, $claro);

            if ($esUltimo) {
                break;
            }
            $iv = substr($bloque, -16);
            $bloque = $siguiente;
        }

        fclose($entrada);
        fclose($salida);
    }

    /** @return array{0: string, 1: string} llave (32 bytes) e IV (16 bytes) */
    private static function derivar(string $clave, string $sal): array
    {
        $material = openssl_pbkdf2($clave, $sal, 48, self::ITERACIONES, 'sha256');
        if ($material === false) {
            throw new \RuntimeException('No se pudo derivar la llave de cifrado.');
        }

        return [substr($material, 0, 32), substr($material, 32, 16)];
    }

    /** @return resource */
    private static function abrir(string $ruta, string $modo)
    {
        $recurso = fopen($ruta, $modo);
        if ($recurso === false) {
            throw new \RuntimeException("No se pudo abrir {$ruta}.");
        }

        return $recurso;
    }
}
