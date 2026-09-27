<?php

declare(strict_types=1);

namespace Tests\Unit;

use App\Core\Database;
use PDOException;
use PHPUnit\Framework\TestCase;

/** Qué errores de conexión se reintentan (pasajeros del hosting) y cuáles no. */
final class ConexionBaseTest extends TestCase
{
    public function testSeReintentanLosRechazosPasajerosDelHosting(): void
    {
        foreach ([
            'SQLSTATE[HY000] [2002] Operation not permitted',
            'SQLSTATE[HY000] [2002] Connection refused',
            'SQLSTATE[08004] [1040] Too many connections',
            'SQLSTATE[42000] [1203] User u397951547 already has more than \'max_user_connections\' active connections',
        ] as $mensaje) {
            $this->assertTrue(Database::esFallaTransitoria(new PDOException($mensaje)), $mensaje);
        }
    }

    public function testNoSeReintentanCredencialesNiBaseInexistente(): void
    {
        foreach ([
            'SQLSTATE[HY000] [1045] Access denied for user \'x\'@\'localhost\' (using password: YES)',
            'SQLSTATE[HY000] [1049] Unknown database \'no_existe\'',
            'SQLSTATE[23000]: Integrity constraint violation: 1062 Duplicate entry \'2002\' for key \'codigo\'',
        ] as $mensaje) {
            $this->assertFalse(Database::esFallaTransitoria(new PDOException($mensaje)), $mensaje);
        }
    }
}
