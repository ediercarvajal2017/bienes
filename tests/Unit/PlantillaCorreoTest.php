<?php

declare(strict_types=1);

namespace Tests\Unit;

use App\Services\PlantillaCorreo;
use PHPUnit\Framework\TestCase;

final class PlantillaCorreoTest extends TestCase
{
    public function testArmaElCorreoConLogoBotonYNota(): void
    {
        $correo = PlantillaCorreo::armar(
            'Restablece tu contraseña',
            'Ana Pérez',
            ['Recibimos una solicitud.'],
            ['texto' => 'Restablecer mi contraseña', 'url' => 'https://bienes.example.com/restablecer-contrasena/abc123'],
            '¿No fuiste tú? Ignora este correo.'
        );

        self::assertStringContainsString('src="cid:' . PlantillaCorreo::CID_LOGO . '"', $correo['html']);
        self::assertStringContainsString('href="https://bienes.example.com/restablecer-contrasena/abc123"', $correo['html']);
        self::assertStringContainsString('>Restablecer mi contraseña</a>', $correo['html']);
        self::assertStringContainsString('Ana Pérez', $correo['html']);
        self::assertStringContainsString('¿No fuiste tú?', $correo['html']);
        // La versión en texto plano trae el enlace completo, sin etiquetas.
        self::assertStringContainsString('Restablecer mi contraseña: https://bienes.example.com/restablecer-contrasena/abc123', $correo['texto']);
        self::assertStringNotContainsString('<', $correo['texto']);
        self::assertFileExists(dirname(__DIR__, 2) . '/' . PlantillaCorreo::RUTA_LOGO);
    }

    public function testEscapaLoQueEscribeElUsuario(): void
    {
        $correo = PlantillaCorreo::armar('Aviso', '<script>alert(1)</script>', ['"comillas" & <b>negrita</b>']);

        self::assertStringNotContainsString('<script>', $correo['html']);
        self::assertStringNotContainsString('<b>negrita</b>', $correo['html']);
        self::assertStringContainsString('&lt;script&gt;', $correo['html']);
        // Sin botón no hay enlace de respaldo.
        self::assertStringNotContainsString('Si el botón no funciona', $correo['html']);
    }
}
