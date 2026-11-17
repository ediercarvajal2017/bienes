<?php

declare(strict_types=1);

namespace Tests\Unit;

use App\Core\Aviso;
use App\Core\ErrorGuiado;
use App\Core\Session;
use PHPUnit\Framework\TestCase;

/** Avisos de las acciones: qué pasó, por qué y qué hacer (App\Core\Aviso). */
final class AvisoTest extends TestCase
{
    public static function setUpBeforeClass(): void
    {
        require_once dirname(__DIR__, 2) . '/app/Helpers/funciones_vista.php';
    }

    protected function setUp(): void
    {
        $_SESSION = [];
        Aviso::reiniciar();
    }

    public function testUnErrorSinQueHacerNoSeAcepta(): void
    {
        $this->expectException(\InvalidArgumentException::class);
        Aviso::error('No se guardó el bien', 'La placa ya existe.', '  ');
    }

    public function testElErrorLlevaSusTresPartesYElCampo(): void
    {
        $aviso = Aviso::error('No se guardó el bien', 'La placa 200 ya existe.', 'Revisa el número.',
            ['texto' => 'Ver el bien', 'url' => '/bienes/7/editar'], ['codigo_identificacion' => 'Esta placa ya existe.']);

        self::assertSame(['tipo' => 'peligro', 'titulo' => 'No se guardó el bien', 'detalle' => 'La placa 200 ya existe.',
            'solucion' => 'Revisa el número.', 'accion' => ['texto' => 'Ver el bien', 'url' => '/bienes/7/editar'],
            'campos' => ['codigo_identificacion' => 'Esta placa ya existe.']], $aviso);
    }

    public function testLosAvisosSeEntreganUnaSolaVez(): void
    {
        Aviso::enviar(Aviso::exito('Bien registrado.', 'Siguiente paso: asígnalo.'));

        self::assertCount(1, Aviso::paraMostrar(), 'el encabezado de la página los recibe');
        self::assertSame([], Aviso::paraMostrar(), 'el layout ya no los repite');
        self::assertArrayNotHasKey('_avisos', $_SESSION, 'y no quedan guardados para otra página');
    }

    public function testUnMensajeAntiguoQueNadieLeyoSeMuestraIgual(): void
    {
        Session::flash('ok', 'Cargo creado correctamente.');
        Session::flash('error', 'Ese cargo ya existe.');

        $avisos = Aviso::paraMostrar();

        self::assertSame(['exito', 'peligro'], array_column($avisos, 'tipo'));
        self::assertSame('Cargo creado correctamente.', $avisos[0]['titulo']);
        self::assertNull(Session::pullFlash('error'), 'ya no reaparece en la siguiente página');
    }

    public function testElCampoMarcadoSeConsultaPorSuNombre(): void
    {
        Aviso::enviar(Aviso::error('No se registró el bien', 'Falta la descripción.', 'Escríbela.', null, ['descripcion' => 'Escribe qué es el bien.']));

        self::assertSame('Escribe qué es el bien.', Aviso::errorDeCampo('descripcion'));
        self::assertNull(Aviso::errorDeCampo('valor'));
        self::assertStringContainsString('id="descripcion-error">Escribe qué es el bien.</div>', error_campo('descripcion'));
        self::assertSame(' is-invalid', clase_invalido('descripcion'));
        self::assertSame('', clase_invalido('valor'));
    }

    public function testUnaReglaGuiadaTraeSuPorQueYQueHacer(): void
    {
        $aviso = Aviso::desdeExcepcion(new ErrorGuiado('El bien ya está en ese espacio.', 'Elige otro destino.', null, ['espacio_id' => 'Otro espacio.']),
            'No se trasladó el bien', 'Revisa los datos.');

        self::assertSame('El bien ya está en ese espacio.', $aviso['detalle']);
        self::assertSame('Elige otro destino.', $aviso['solucion']);
        self::assertSame(['espacio_id' => 'Otro espacio.'], $aviso['campos']);

        $sinGuia = Aviso::desdeExcepcion(new \DomainException('Regla antigua.'), 'No se trasladó el bien', 'Revisa los datos.');
        self::assertSame('Revisa los datos.', $sinGuia['solucion'], 'una regla sin guía usa el qué hacer del llamador');
    }

    public function testUnAvisoGuardadoMalFormadoSeDescarta(): void
    {
        $_SESSION['_avisos'] = [['tipo' => 'raro', 'titulo' => 'x'], 'texto', ['tipo' => 'exito', 'titulo' => 'Listo.']];

        self::assertSame(['Listo.'], array_column(Aviso::pendientes(), 'titulo'));
    }

    public function testElAvisoDeTresPartesSePintaConSuBotonEscapado(): void
    {
        $html = ui('alerta', ['tipo' => 'peligro', 'titulo' => 'No se guardó el bien', 'detalle' => 'La placa <200> ya existe.',
            'solucion' => 'Revisa el número.', 'accion' => ['texto' => 'Ver el bien "200"', 'url' => '/bienes/7/editar?a=1&b=2'], 'enfocar' => true]);

        self::assertStringContainsString('role="alert"', $html);
        self::assertStringContainsString('tabindex="-1" data-aviso-enfocar', $html);
        self::assertStringContainsString('<p class="aviso-titulo">No se guardó el bien</p>', $html);
        self::assertStringContainsString('La placa &lt;200&gt; ya existe.', $html);
        self::assertStringContainsString('<strong>Qué hacer:</strong> Revisa el número.', $html);
        self::assertStringContainsString('href="/bienes/7/editar?a=1&amp;b=2" class="btn btn-primary btn-sm">Ver el bien &quot;200&quot;', $html);

        $simple = ui('alerta', ['tipo' => 'exito', 'titulo' => 'Cargo creado.']);
        self::assertStringContainsString('<p class="aviso-titulo">Cargo creado.</p>', $simple, 'una sola línea también lleva el título en verde MIA');
        self::assertStringNotContainsString('aviso-detalle', $simple);
        self::assertStringNotContainsString('data-aviso-enfocar', $simple);
        self::assertStringContainsString('<span>Lote pendiente.</span>', ui('alerta', ['tipo' => 'info', 'mensaje' => 'Lote pendiente.']),
            'un aviso de estado va como texto simple');
    }
}
