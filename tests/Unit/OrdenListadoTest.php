<?php

declare(strict_types=1);

namespace Tests\Unit;

use App\Helpers\OrdenListado;
use App\Models\Auditoria;
use App\Models\Baja;
use App\Models\Bien;
use App\Models\CargaMasiva;
use App\Models\Espacio;
use App\Models\JornadaVerificacion;
use App\Models\LoteReintegro;
use App\Models\SolicitudReintegro;
use App\Models\Usuario;
use PHPUnit\Framework\TestCase;

/** Orden por columna de los listados (clic en el título): solo columnas permitidas llegan al SQL. */
final class OrdenListadoTest extends TestCase
{
    private const COLUMNAS = ['nombre' => ['u.nombres', 'u.apellidos'], 'categoria' => ['=c.nombre IS NULL', 'c.nombre']];

    public function testSoloAceptaLasColumnasDeclaradas(): void
    {
        self::assertSame(['nombre', 'desc'], OrdenListado::desdeConsulta(self::COLUMNAS, ['orden' => 'nombre', 'dir' => 'desc']));
        self::assertSame([null, 'asc'], OrdenListado::desdeConsulta(self::COLUMNAS, ['orden' => 'u.nombres; DROP TABLE usuarios', 'dir' => 'desc']));
        self::assertSame([null, 'asc'], OrdenListado::desdeConsulta(self::COLUMNAS, ['orden' => ['nombre']]), 'un arreglo en la dirección no rompe');
        self::assertSame(['nombre', 'asc'], OrdenListado::desdeConsulta(self::COLUMNAS, ['orden' => 'nombre', 'dir' => 'cualquiera']));
    }

    public function testArmaElOrderByConSentidoYDesempate(): void
    {
        self::assertSame(' ORDER BY u.created_at DESC', OrdenListado::sql(self::COLUMNAS, null, 'asc', 'u.created_at DESC', 'u.id'));
        self::assertSame(' ORDER BY u.nombres DESC, u.apellidos DESC, u.id DESC', OrdenListado::sql(self::COLUMNAS, 'nombre', 'desc', 'x', 'u.id'));
        self::assertSame(' ORDER BY c.nombre IS NULL, c.nombre ASC, u.id ASC', OrdenListado::sql(self::COLUMNAS, 'categoria', 'asc', 'x', 'u.id'),
            '«=» va tal cual: los vacíos siempre al final');
        self::assertSame(' ORDER BY x', OrdenListado::sql(self::COLUMNAS, 'inventada', 'asc', 'x', 'u.id'));
    }

    /** Las columnas declaradas por cada listado son expresiones fijas, nunca algo de la dirección. */
    public function testLasColumnasDeLosListadosSonExpresionesFijas(): void
    {
        $todas = [Usuario::ORDENES, Espacio::ORDENES, Bien::ORDENES_OPERABLES, Bien::ORDENES_REINTEGRABLES, Baja::ORDENES,
            SolicitudReintegro::ORDENES, LoteReintegro::ORDENES, Auditoria::ORDENES, CargaMasiva::ORDENES, JornadaVerificacion::ORDENES];
        foreach ($todas as $columnas) {
            foreach ($columnas as $clave => $expresiones) {
                self::assertMatchesRegularExpression('/^[a-z]+$/', $clave);
                self::assertNotSame([], $expresiones);
                foreach ($expresiones as $expresion) {
                    self::assertDoesNotMatchRegularExpression('/(;|--|\bASC\b|\bDESC\b)/i', $expresion, "{$clave}: el sentido lo pone OrdenListado");
                }
            }
        }
    }
}
