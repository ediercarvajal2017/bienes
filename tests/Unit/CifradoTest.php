<?php

declare(strict_types=1);

namespace Tests\Unit;

use App\Helpers\CifradoRespaldo;
use App\Helpers\LlaveAplicacion;
use PHPUnit\Framework\TestCase;

/** Cifrado de los respaldos (AES-256, compatible con openssl) y de las claves del autenticador (APP_KEY). */
final class CifradoTest extends TestCase
{
    private ?string $appKeyAnterior = null;

    protected function setUp(): void
    {
        $valor = getenv('APP_KEY');
        $this->appKeyAnterior = $valor === false ? null : $valor;
    }

    protected function tearDown(): void
    {
        putenv($this->appKeyAnterior === null ? 'APP_KEY' : 'APP_KEY=' . $this->appKeyAnterior);
    }

    public function testRespaldoCifradoYDescifradoConLaMismaContrasena(): void
    {
        $origen = tempnam(sys_get_temp_dir(), 'sgb');
        $cifrado = $origen . '.enc';
        $descifrado = $origen . '.dec';
        file_put_contents($origen, "INSERT INTO bienes VALUES (1, 'Silla');\n" . random_bytes(2048));

        CifradoRespaldo::cifrar($origen, $cifrado, 'frase-larga-de-respaldo');
        self::assertStringStartsWith('Salted__', (string) file_get_contents($cifrado), 'formato de openssl enc');
        self::assertStringNotContainsString('Silla', (string) file_get_contents($cifrado));

        CifradoRespaldo::descifrar($cifrado, $descifrado, 'frase-larga-de-respaldo');
        self::assertSame(file_get_contents($origen), file_get_contents($descifrado));

        try {
            CifradoRespaldo::descifrar($cifrado, $descifrado . '2', 'otra-frase');
            self::fail('con otra contraseña no debía descifrar');
        } catch (\RuntimeException) {
            self::assertTrue(true);
        } finally {
            array_map('unlink', array_filter([$origen, $cifrado, $descifrado, $descifrado . '2'], 'is_file'));
        }

        self::assertStringContainsString('-iter ' . CifradoRespaldo::ITERACIONES, CifradoRespaldo::comandoDescifrado('x.enc'));
    }

    public function testLlaveDeLaAplicacion(): void
    {
        putenv('APP_KEY=' . base64_encode(random_bytes(32)));
        self::assertTrue(LlaveAplicacion::disponible());
        $cifrado = LlaveAplicacion::cifrar('JBSWY3DPEHPK3PXP');
        self::assertStringNotContainsString('JBSWY3DPEHPK3PXP', $cifrado);
        self::assertSame('JBSWY3DPEHPK3PXP', LlaveAplicacion::descifrar($cifrado));

        putenv('APP_KEY=base64:' . base64_encode(random_bytes(32)));
        self::assertNull(LlaveAplicacion::descifrar($cifrado), 'con otra llave no se puede leer (y no rompe)');

        putenv('APP_KEY=corta');
        self::assertFalse(LlaveAplicacion::disponible(), 'una llave inválida equivale a no tener llave');
    }
}
