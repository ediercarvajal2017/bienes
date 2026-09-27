<?php

declare(strict_types=1);

namespace Tests\Unit;

use App\Helpers\Totp;
use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\TestCase;

final class TotpTest extends TestCase
{
    /** Vectores oficiales del RFC 6238 (SHA1), recortados a 6 dígitos. */
    public static function vectoresRfc(): array
    {
        return [
            [59, '287082'], [1111111109, '081804'], [1111111111, '050471'],
            [1234567890, '005924'], [2000000000, '279037'], [20000000000, '353130'],
        ];
    }

    #[DataProvider('vectoresRfc')]
    public function testCoincideConLosVectoresDelRfc(int $momento, string $esperado): void
    {
        $secreto = Totp::base32Codificar('12345678901234567890');

        self::assertSame($esperado, Totp::codigo($secreto, intdiv($momento, 30)));
    }

    public function testBase32IdaYVuelta(): void
    {
        $bytes = random_bytes(20);

        self::assertSame($bytes, Totp::base32Decodificar(Totp::base32Codificar($bytes)));
        self::assertSame(32, strlen(Totp::generarSecreto()));
    }

    public function testToleraTreintaSegundosDeDesfaseYNoMas(): void
    {
        $secreto = Totp::generarSecreto();
        $momento = 1_700_000_000;
        $paso = Totp::pasoActual($momento);

        self::assertSame($paso - 1, Totp::pasoValido($secreto, Totp::codigo($secreto, $paso - 1), $momento));
        self::assertSame($paso + 1, Totp::pasoValido($secreto, Totp::codigo($secreto, $paso + 1), $momento));
        self::assertNull(Totp::pasoValido($secreto, Totp::codigo($secreto, $paso - 2), $momento));
    }

    public function testRechazaFormatosInvalidos(): void
    {
        $secreto = Totp::generarSecreto();

        foreach (['', '12345', '1234567', 'abcdef', '12 34 5'] as $codigo) {
            self::assertNull(Totp::pasoValido($secreto, $codigo), "debía rechazar «{$codigo}»");
        }
    }

    public function testUriOtpauthParaLosAutenticadores(): void
    {
        $uri = Totp::uriOtpauth('JBSWY3DPEHPK3PXP', 'rector@colegio.edu.co', 'SIGEBI');

        self::assertStringStartsWith('otpauth://totp/SIGEBI%3Arector%40colegio.edu.co?secret=JBSWY3DPEHPK3PXP', $uri);
        self::assertStringContainsString('issuer=SIGEBI', $uri);
        self::assertSame('JBSW Y3DP EHPK 3PXP', Totp::formatearParaMostrar('JBSWY3DPEHPK3PXP'));
    }
}
