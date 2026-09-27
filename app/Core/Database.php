<?php

declare(strict_types=1);

namespace App\Core;

use PDO;

final class Database
{
    private static ?PDO $instance = null;

    public static function connection(): PDO
    {
        if (self::$instance === null) {
            $config = require dirname(__DIR__, 2) . '/config/database.php';

            // En el hosting compartido la conexión puede rechazarse por un instante cuando
            // llegan muchas peticiones juntas (p. ej. las fotos de un listado): se reintenta
            // unas pocas veces con una espera creciente antes de dar el error.
            for ($intento = 1; ; $intento++) {
                try {
                    self::$instance = new PDO(
                        "mysql:host={$config['host']};port={$config['port']};dbname={$config['database']};charset={$config['charset']}",
                        $config['username'],
                        $config['password'],
                        [
                            PDO::ATTR_ERRMODE => PDO::ERRMODE_EXCEPTION,
                            PDO::ATTR_DEFAULT_FETCH_MODE => PDO::FETCH_ASSOC,
                            PDO::ATTR_EMULATE_PREPARES => false,
                        ]
                    );
                    break;
                } catch (\PDOException $e) {
                    if ($intento >= 4 || !self::esFallaTransitoria($e)) {
                        throw $e;
                    }
                    usleep(150_000 * $intento);
                }
            }
        }

        return self::$instance;
    }

    /**
     * ¿El error de conexión es pasajero? 2002: el servidor rechazó o no atendió la conexión
     * (en el hosting compartido aparece como "Operation not permitted" bajo carga);
     * 1040 y 1203: demasiadas conexiones. Credenciales o base inexistente no se reintentan.
     */
    public static function esFallaTransitoria(\PDOException $e): bool
    {
        return preg_match('/\[(2002|1040|1203)\]/', $e->getMessage()) === 1;
    }

    /**
     * Cierra la conexión (se reabre sola si se vuelve a pedir). Útil antes de trabajos
     * largos que ya no usan la base, como enviar un archivo: libera la conexión antes.
     */
    public static function desconectar(): void
    {
        self::$instance = null;
    }

    /**
     * Ejecuta $fn dentro de una transacción: si lanza cualquier excepción se deshace todo
     * y la excepción sigue su curso. Si ya hay una transacción abierta, se reutiliza (no
     * se anidan). Devuelve lo que devuelva $fn.
     *
     * @template T
     * @param callable(PDO): T $fn
     * @return T
     */
    public static function transaccion(callable $fn): mixed
    {
        $pdo = self::connection();
        if ($pdo->inTransaction()) {
            return $fn($pdo);
        }

        $pdo->beginTransaction();
        try {
            $resultado = $fn($pdo);
            $pdo->commit();

            return $resultado;
        } catch (\Throwable $e) {
            $pdo->rollBack();
            throw $e;
        }
    }
}
