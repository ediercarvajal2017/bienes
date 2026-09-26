<?php

declare(strict_types=1);

namespace App\Helpers;

/**
 * Reglas para una contraseña NUEVA (al crear un usuario, cambiarla o restablecerla por
 * correo). Las contraseñas que ya existen no se ven afectadas hasta que se cambien.
 */
final class PoliticaContrasena
{
    public const LONGITUD_MINIMA = 10;

    /** bcrypt solo usa los primeros 72 bytes: más allá, el resto se ignoraría en silencio. */
    private const BYTES_MAXIMOS = 72;

    /** Contraseñas muy usadas (se comparan sin mayúsculas ni espacios). */
    private const COMUNES = [
        '1234567890', '0123456789', '12345678910', '1234512345', '1111111111', '0000000000',
        'qwertyuiop', 'asdfghjklñ', 'password123', 'password1234', 'contraseña1', 'contraseña123',
        'contrasena1', 'contrasena123', 'colegio123', 'colegio2026', 'institucion1', 'sigebi2026',
        'sigebi12345', 'rector12345', 'docente1234', 'bienes12345', 'colombia123', 'administrador',
        'admin12345', 'bogota12345', 'teamo12345', 'abcd123456', 'abc1234567',
    ];

    /**
     * @param string[] $datosPersonales correo, documento, nombres... del usuario: la
     *                                  contraseña no puede contenerlos.
     * @return string|null mensaje de error, o null si la contraseña es aceptable
     */
    public static function validar(string $password, array $datosPersonales = []): ?string
    {
        if (mb_strlen($password) < self::LONGITUD_MINIMA) {
            return 'La contraseña debe tener al menos ' . self::LONGITUD_MINIMA . ' caracteres.';
        }

        if (strlen($password) > self::BYTES_MAXIMOS) {
            return 'La contraseña es demasiado larga (máximo ' . self::BYTES_MAXIMOS . ' caracteres).';
        }

        if (!preg_match('/\p{L}/u', $password) || !preg_match('/\d/', $password)) {
            return 'La contraseña debe combinar letras y números.';
        }

        $normalizada = mb_strtolower(str_replace(' ', '', $password));
        if (in_array($normalizada, self::COMUNES, true)) {
            return 'Esa contraseña es demasiado común. Elija otra más difícil de adivinar.';
        }

        // Se compara sin separadores: "juan.perez@..." no debe permitir "JuanPerez2026", ni
        // "Juan" o "Perez" por separado.
        $soloLetrasNumeros = static fn (string $t): string => (string) preg_replace('/[^\p{L}\p{N}]+/u', '', $t);
        $claveCompacta = $soloLetrasNumeros($normalizada);

        foreach ($datosPersonales as $dato) {
            $dato = mb_strtolower(trim((string) $dato));
            if (str_contains($dato, '@')) {
                $dato = explode('@', $dato)[0]; // del correo, solo la parte antes de la @
            }

            $partes = preg_split('/[^\p{L}\p{N}]+/u', $dato, -1, PREG_SPLIT_NO_EMPTY) ?: [];
            $partes[] = $soloLetrasNumeros($dato);

            foreach ($partes as $parte) {
                if (mb_strlen($parte) >= 4 && str_contains($claveCompacta, $parte)) {
                    return 'La contraseña no puede contener su correo, documento ni nombre.';
                }
            }
        }

        return null;
    }
}
