<?php

declare(strict_types=1);

namespace Tests\Unit;

use App\Helpers\BinderSinFormulas;
use App\Models\Bien;
use App\Models\Categoria;
use App\Models\CodigoRecuperacion;
use App\Models\DispositivoConfiable;
use PhpOffice\PhpSpreadsheet\Cell\DataType;
use PHPUnit\Framework\TestCase;

/** Reglas de negocio puras: estados del bien, reintegro, fórmulas y formatos. */
final class ReglasDeNegocioTest extends TestCase
{
    public function testMaquinaDeEstadosDelBien(): void
    {
        self::assertSame([], Bien::TRANSICIONES['dado_de_baja'], 'dado de baja es un estado final');
        self::assertContains('activo', Bien::TRANSICIONES['reintegrado'], 'un reintegrado solo vuelve a activo (reactivar)');
        self::assertNotContains('dado_de_baja', Bien::TRANSICIONES['reintegrado']);
        self::assertContains('dado_de_baja', Bien::TRANSICIONES['activo']);
        self::assertSame(['activo', 'en_reparacion'], Bien::ESTADOS_REINTEGRABLES);
    }

    public function testReglaUnicaDeReintegro(): void
    {
        $bien = ['estado' => 'activo', 'categoria_id' => 3, 'categoria_nombre' => 'Muebles'];

        self::assertNull(Bien::motivoNoReintegrable($bien, true));
        self::assertNotNull(Bien::motivoNoReintegrable($bien, false), 'sin asignación activa no se reintegra');
        self::assertNotNull(Bien::motivoNoReintegrable(['estado' => 'dado_de_baja'] + $bien, true));
        self::assertNotNull(Bien::motivoNoReintegrable(['estado' => 'reintegrado'] + $bien, true));
        self::assertNotNull(Bien::motivoNoReintegrable(['categoria_id' => null] + $bien, true), 'sin categoría no se reintegra');
        self::assertNotNull(
            Bien::motivoNoReintegrable(['categoria_nombre' => Categoria::NOMBRE_CATEGORIA_PROTEGIDA] + $bien, true),
            '"Sin cartera" solo admite baja'
        );
        self::assertNull(Bien::motivoNoReintegrable(['estado' => 'en_reparacion'] + $bien, true));
    }

    public function testLosReportesNuncaGuardanFormulas(): void
    {
        foreach (['=HYPERLINK("http://malo";"Ver")', '=1+1', '=cmd|\' /C calc\'!A0'] as $texto) {
            self::assertSame(DataType::TYPE_STRING, BinderSinFormulas::dataTypeForValue($texto), $texto);
        }
        self::assertSame(DataType::TYPE_NUMERIC, BinderSinFormulas::dataTypeForValue(150000));
    }

    public function testFormatoDeLosCodigosDeRecuperacion(): void
    {
        self::assertTrue(CodigoRecuperacion::pareceCodigo('ABCDE-FGHJK'));
        self::assertTrue(CodigoRecuperacion::pareceCodigo('abcde fghjk'), 'sin importar mayúsculas ni separador');
        self::assertFalse(CodigoRecuperacion::pareceCodigo('123456'), 'un código de la aplicación no es de recuperación');
        self::assertFalse(CodigoRecuperacion::pareceCodigo('1234567890'), 'solo dígitos: no es de recuperación');
        self::assertFalse(CodigoRecuperacion::pareceCodigo('ABC'));
    }

    public function testDescripcionDelDispositivoDeConfianza(): void
    {
        $android = 'Mozilla/5.0 (Linux; Android 14; Pixel 7) AppleWebKit/537.36 Chrome/126.0 Mobile Safari/537.36';
        $windowsEdge = 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 Chrome/126.0 Safari/537.36 Edg/126.0';

        self::assertSame('Chrome en Android', DispositivoConfiable::describirAgente($android));
        self::assertSame('Edge en Windows', DispositivoConfiable::describirAgente($windowsEdge));
        self::assertSame('Navegador en sistema desconocido', DispositivoConfiable::describirAgente(null));
    }
}
