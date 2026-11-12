<?php

declare(strict_types=1);

namespace Tests\Unit;

use App\Models\Bien;
use App\Services\ReintegroUnidadesService;
use App\Services\ReporteService;
use PHPUnit\Framework\TestCase;

/** Reintegro por cantidad de los códigos con varias unidades (alta masiva). */
final class ReintegroUnidadesTest extends TestCase
{
    public function testUnaUnidadDeLoteSinAsignarSePuedeReintegrar(): void
    {
        $unidad = ['estado' => 'activo', 'lote' => '200033042', 'categoria_id' => 3, 'categoria_nombre' => 'Muebles'];
        self::assertNull(Bien::motivoNoReintegrable($unidad, false), 'unidad de lote sin asignar');
        self::assertNull(Bien::motivoNoReintegrable($unidad, true), 'unidad de lote asignada');

        $suelto = ['estado' => 'activo', 'lote' => null, 'categoria_id' => 3, 'categoria_nombre' => 'Muebles'];
        self::assertSame('el bien no tiene una asignación activa', Bien::motivoNoReintegrable($suelto, false), 'un bien suelto sigue exigiendo asignación');

        // Las demás reglas siguen igual para las unidades de un lote.
        self::assertNotNull(Bien::motivoNoReintegrable(['estado' => 'reintegrado'] + $unidad, false));
        self::assertNotNull(Bien::motivoNoReintegrable(['categoria_id' => null, 'categoria_nombre' => null] + $unidad, false));
        self::assertNotNull(Bien::motivoNoReintegrable(['categoria_nombre' => 'Sin cartera'] + $unidad, false));
    }

    public function testClavesDeUbicacion(): void
    {
        self::assertSame('e5-u0', ReintegroUnidadesService::claveGrupo(5, null));
        self::assertSame('e0-u7', ReintegroUnidadesService::claveGrupo(null, 7));
        self::assertSame(['espacio' => 5, 'usuario' => null, 'sin_asignar' => false], ReintegroUnidadesService::parsearClaveGrupo('e5-u0'));
        self::assertSame(['espacio' => null, 'usuario' => 7, 'sin_asignar' => false], ReintegroUnidadesService::parsearClaveGrupo('e0-u7'));
        self::assertSame(['espacio' => 5, 'usuario' => 7, 'sin_asignar' => false], ReintegroUnidadesService::parsearClaveGrupo('e5-u7'));
        self::assertSame(['espacio' => null, 'usuario' => null, 'sin_asignar' => true], ReintegroUnidadesService::parsearClaveGrupo('sin'));
        self::assertNull(ReintegroUnidadesService::parsearClaveGrupo('e0-u0'));
        self::assertNull(ReintegroUnidadesService::parsearClaveGrupo('e5'));
        self::assertNull(ReintegroUnidadesService::parsearClaveGrupo("e5-u0' OR 1=1"));
    }

    public function testCantidadesValidas(): void
    {
        self::assertSame(['e5-u0' => 3, 'sin' => 12], ReintegroUnidadesService::cantidadesValidas(['e5-u0' => '3', 'sin' => '12', 'e6-u0' => '0', 'e7-u0' => '']));

        $this->expectException(\DomainException::class);
        ReintegroUnidadesService::cantidadesValidas(['e5-u0' => '0']);
    }

    public function testCantidadNoNumericaSeRechaza(): void
    {
        $this->expectException(\DomainException::class);
        ReintegroUnidadesService::cantidadesValidas(['e5-u0' => '-2']);
    }

    public function testDescripcionDelBienResumenConLaCantidadQueQueda(): void
    {
        self::assertSame('175 SILLAS UNIVERSITARIAS', ReintegroUnidadesService::descripcionConCantidad('178 SILLAS UNIVERSITARIAS', 175));
        self::assertSame('0 MESA DE METAL Y MADERA', ReintegroUnidadesService::descripcionConCantidad(' 6 MESA DE METAL Y MADERA ', 0));
        self::assertSame('12 SILLAS UNIVERSITARIAS', ReintegroUnidadesService::descripcionConCantidad('SILLAS UNIVERSITARIAS', 12), 'sin número al comienzo: se antepone');
        self::assertSame('9 200033042 SILLAS', ReintegroUnidadesService::descripcionConCantidad('200033042 SILLAS', 9), 'un código largo al comienzo no es una cantidad');
    }

    public function testRenglonesDelComprobanteSumanPorPlaca(): void
    {
        $unidad = static fn (string $codigo, string $fecha, string $origen = ''): array => [
            'codigo_identificacion' => $codigo, 'lote' => '200033042', 'descripcion' => 'SILLAS UNIVERSITARIAS', 'marca' => null,
            'categoria_nombre' => 'Muebles', 'fecha_reintegro' => $fecha, 'destino_texto' => 'Bodega de reintegro',
            'espacio_origen_nombre' => $origen, 'valor' => 100000,
        ];
        $renglones = ReporteService::renglonesPorPlaca([
            $unidad('200033042-176', '2026-11-10', 'A-101 - Aula 101'),
            ['codigo_identificacion' => 'PC-01', 'lote' => null, 'descripcion' => 'Portátil', 'marca' => 'Lenovo',
                'categoria_nombre' => 'Tecnología', 'fecha_reintegro' => '2026-11-09', 'destino_texto' => 'Bodega', 'espacio_origen_nombre' => null, 'valor' => 2500000],
            $unidad('200033042-177', '2026-11-12'),
            $unidad('200033042-178', '2026-11-11', 'A-101 - Aula 101'),
        ]);

        self::assertCount(2, $renglones);
        self::assertSame('200033042', $renglones[0]['placa']);
        self::assertSame(3, $renglones[0]['cantidad'], 'las tres sillas en un solo renglón');
        self::assertSame(['200033042-176', '200033042-177', '200033042-178'], $renglones[0]['unidades']);
        self::assertSame(['2026-11-10', '2026-11-12'], [$renglones[0]['fecha_desde'], $renglones[0]['fecha_hasta']]);
        self::assertSame(['A-101 - Aula 101'], $renglones[0]['origenes'], 'sin repetir y sin vacíos');
        self::assertSame(300000.0, $renglones[0]['valor']);

        self::assertSame('PC-01', $renglones[1]['placa'], 'un bien suelto conserva su código');
        self::assertSame(1, $renglones[1]['cantidad']);
        self::assertSame([], ReporteService::renglonesPorPlaca([]));
    }
}
