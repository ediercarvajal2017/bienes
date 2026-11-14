<?php

declare(strict_types=1);

namespace Tests\Unit;

use App\Helpers\Icono;
use PHPUnit\Framework\TestCase;

/**
 * Estándar visual (docs/guia-estilo-ui.md): ninguna vista suma estilos en línea, íconos
 * escritos a mano ni botones fuera de la jerarquía, y el diccionario de íconos es coherente.
 */
final class EstandarVisualTest extends TestCase
{
    public function testNingunaVistaEmpeoraElEstandar(): void
    {
        $diferencias = RevisionVisual::comparar();

        self::assertSame([], $diferencias['subio'],
            "Hay estilos en línea, íconos \"bi bi-…\" o botones fuera de la jerarquía nuevos.\n"
            . "Usa clases y tokens, icono('clave') y las piezas de partials/ui.");
    }

    public function testLaListaDePendientesEstaAlDia(): void
    {
        self::assertSame([], RevisionVisual::comparar()['bajo'],
            "Una vista ya mejoró: actualiza la lista con php database/herramientas/verificar_ui.php --actualizar");
    }

    public function testElDiccionarioDeIconosEsCoherente(): void
    {
        $nombres = [];
        foreach (Icono::mapa() as $clave => $entrada) {
            self::assertMatchesRegularExpression('/^[a-z_]+$/', $clave);
            self::assertMatchesRegularExpression('/^[a-z0-9-]+$/', $entrada['icono']);
            self::assertStringEndsNotWith('-fill', $entrada['icono'], "{$clave}: la versión de línea va en 'icono'; la rellena, en 'activo'");
            if (isset($entrada['activo'])) {
                self::assertStringEndsWith('-fill', $entrada['activo'], "{$clave}: 'activo' es la versión rellena");
            }
            self::assertNotSame('', trim($entrada['uso']));
            $nombres[$entrada['icono']][] = $clave;
        }

        // Un ícono = un significado. La única pareja permitida: eliminar y papelera son lo mismo.
        $repetidos = array_filter($nombres, static fn (array $claves): bool => count($claves) > 1);
        self::assertSame(['trash3' => ['eliminar', 'papelera']], $repetidos);
    }

    public function testElHelperDevuelveElMarcadoDeclarado(): void
    {
        self::assertSame('<i class="bi bi-pencil" aria-hidden="true"></i>', Icono::html('editar'));
        self::assertSame('<i class="bi bi-box-seam-fill me-1" aria-hidden="true"></i>', Icono::html('bien', 'me-1', true));
        self::assertSame('plus-lg', Icono::nombre('crear', true), 'sin versión rellena, la de línea');

        $this->expectException(\InvalidArgumentException::class);
        Icono::html('no_existe');
    }
}
