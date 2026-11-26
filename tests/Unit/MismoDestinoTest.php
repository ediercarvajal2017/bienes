<?php

declare(strict_types=1);

namespace Tests\Unit;

use App\Services\CicloVidaBien;
use PHPUnit\Framework\TestCase;

/**
 * Traslado al mismo lugar: en la ficha del bien no bloquea el guardado de los datos
 * (p. ej. al pasar un bien de «Sin cartera» a la cartera con su placa); en las demás
 * pantallas se rechaza. CicloVidaBien::esMismoDestino decide cuándo es el mismo lugar.
 */
final class MismoDestinoTest extends TestCase
{
    public function testGrupalEnElMismoEspacioEsElMismoDestino(): void
    {
        $grupal = ['espacio_id' => 7, 'usuario_responsable_id' => null];

        self::assertTrue(CicloVidaBien::esMismoDestino($grupal, 7, null));
        self::assertFalse(CicloVidaBien::esMismoDestino($grupal, 8, null), 'otro espacio es un traslado');
        self::assertFalse(CicloVidaBien::esMismoDestino($grupal, 7, 3), 'pasar a Individual es un traslado');
    }

    public function testIndividualConLaMismaPersonaYEspacioEsElMismoDestino(): void
    {
        $individual = ['espacio_id' => null, 'usuario_responsable_id' => 3];

        self::assertTrue(CicloVidaBien::esMismoDestino($individual, null, 3));
        self::assertFalse(CicloVidaBien::esMismoDestino($individual, null, 4), 'otra persona es un traslado');
        self::assertFalse(CicloVidaBien::esMismoDestino($individual, 7, 3), 'guardarlo en un espacio es un cambio');
    }

    public function testSinAsignacionNuncaEsElMismoDestino(): void
    {
        self::assertFalse(CicloVidaBien::esMismoDestino(null, 7, null));
        self::assertFalse(CicloVidaBien::esMismoDestino(null, null, null));
    }
}
