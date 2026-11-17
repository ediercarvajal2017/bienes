<?php

declare(strict_types=1);

namespace Tests\Unit;

use PHPUnit\Framework\TestCase;

/**
 * Los mensajes de las acciones dicen qué pasó, por qué y qué hacer (App\Core\Aviso). Los
 * antiguos de una sola línea solo pueden bajar, y los módulos migrados quedan en cero.
 */
final class MensajesGuiadosTest extends TestCase
{
    public function testNingunArchivoSumaMensajesAntiguos(): void
    {
        self::assertSame([], RevisionMensajes::comparar()['subio'],
            "Hay mensajes nuevos con Session::flash('ok'|'error') o new \\DomainException.\n"
            . 'Usa Aviso::enviar(Aviso::exito|error(…)) y ErrorGuiado, con qué pasó, por qué y qué hacer.');
    }

    public function testLaListaDePendientesEstaAlDia(): void
    {
        self::assertSame([], RevisionMensajes::comparar()['bajo'],
            'Un archivo ya mejoró: actualiza la lista con php database/herramientas/verificar_mensajes.php --actualizar');
    }

    public function testLosModulosMigradosNoTienenMensajesAntiguos(): void
    {
        $conteo = RevisionMensajes::conteo();
        foreach (RevisionMensajes::MIGRADOS as $archivo) {
            self::assertFileExists(RevisionMensajes::carpetaApp() . '/' . $archivo);
            self::assertArrayNotHasKey($archivo, $conteo, "{$archivo} ya se migró: no puede volver a tener mensajes de una sola línea.");
        }
    }
}
