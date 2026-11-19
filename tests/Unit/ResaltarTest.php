<?php

declare(strict_types=1);

namespace Tests\Unit;

use App\Models\Bien;
use PHPUnit\Framework\TestCase;

/** Lo buscado, marcado en la tabla (resaltar()), y el orden por columna del listado (Bien::sqlOrden). */
final class ResaltarTest extends TestCase
{
    protected function setUp(): void
    {
        require_once dirname(__DIR__, 2) . '/app/Helpers/funciones_vista.php';
    }

    public function testMarcaSinImportarMayusculasNiTildes(): void
    {
        self::assertSame('<mark class="coincidencia">SÍLLA</mark> PLÁSTICA', resaltar('SÍLLA PLÁSTICA', 'silla'));
        self::assertSame('Mesa <mark class="coincidencia">plástica</mark>', resaltar('Mesa plástica', 'PLASTICA'));
        self::assertSame('Año <mark class="coincidencia">niño</mark>', resaltar('Año niño', 'nino'));
    }

    public function testMarcaCadaAparicion(): void
    {
        self::assertSame('<mark class="coincidencia">ab</mark>c<mark class="coincidencia">AB</mark>', resaltar('abcAB', 'ab'));
    }

    public function testEscapaElHtmlDelTextoYDeLoBuscado(): void
    {
        self::assertSame('&lt;b&gt;<mark class="coincidencia">x</mark>&lt;/b&gt;', resaltar('<b>x</b>', 'x'));
        self::assertSame('<mark class="coincidencia">&lt;b&gt;</mark>', resaltar('<b>', '<b>'));
        self::assertSame('&quot;a&quot; &amp; b', resaltar('"a" & b', 'zzz'));
    }

    public function testSinBusquedaSoloEscapa(): void
    {
        self::assertSame('Silla &lt;azul&gt;', resaltar('Silla <azul>', ''));
        self::assertSame('Silla', resaltar('Silla', '   '));
        self::assertSame('', resaltar(null, 'silla'));
    }

    public function testElOrdenSoloAceptaLasColumnasPermitidas(): void
    {
        self::assertSame(' ORDER BY b.created_at DESC, b.id DESC', Bien::sqlOrden(null), 'sin orden: los más recientes primero, como siempre');
        self::assertSame(' ORDER BY b.created_at DESC, b.id DESC', Bien::sqlOrden('valor; DROP TABLE bienes', 'desc'), 'una columna inventada no llega al SQL');
        self::assertSame(' ORDER BY b.valor DESC, b.id DESC', Bien::sqlOrden('valor', 'desc'));
        self::assertSame(' ORDER BY b.valor ASC, b.id ASC', Bien::sqlOrden('valor', 'cualquier cosa'), 'un sentido inventado es de menor a mayor');
        self::assertStringContainsString('CHAR_LENGTH(b.codigo_identificacion) ASC', Bien::sqlOrden('codigo'), 'código en orden natural: 9 antes que 10');
        self::assertStringStartsWith(' ORDER BY c.nombre IS NULL,', Bien::sqlOrden('categoria', 'desc'), 'sin categoría, siempre al final');
        foreach (Bien::ORDENES as $columna) {
            self::assertStringEndsWith(', b.id ASC', Bien::sqlOrden($columna), "{$columna}: desempata por id");
        }
    }
}
