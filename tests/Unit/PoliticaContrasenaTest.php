<?php

declare(strict_types=1);

namespace Tests\Unit;

use App\Helpers\PoliticaContrasena;
use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\TestCase;

final class PoliticaContrasenaTest extends TestCase
{
    public static function rechazadas(): array
    {
        return [
            'muy corta' => ['Abc12345'],
            'solo letras' => ['SoloLetrasLargas'],
            'solo números' => ['12345678901'],
            'común' => ['Password123'],
            'más de 72 bytes' => [str_repeat('Ab1', 25)],
        ];
    }

    #[DataProvider('rechazadas')]
    public function testRechazaContrasenasDebiles(string $clave): void
    {
        self::assertNotNull(PoliticaContrasena::validar($clave));
    }

    public function testRechazaDatosPersonales(): void
    {
        $datos = ['María', 'Pérez', '1036654321', 'maria.perez@colegio.edu.co'];

        self::assertNotNull(PoliticaContrasena::validar('Perez-2026-seguro', $datos));
        self::assertNotNull(PoliticaContrasena::validar('Clave1036654321', $datos));
    }

    public function testAceptaUnaContrasenaRazonable(): void
    {
        self::assertNull(PoliticaContrasena::validar('Bodega-Norte-73', ['María', 'Pérez', '1036654321', 'maria@x.co']));
        self::assertSame(10, PoliticaContrasena::LONGITUD_MINIMA);
    }
}
