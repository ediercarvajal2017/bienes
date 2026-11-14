<?php

declare(strict_types=1);

namespace Tests\Unit;

use PHPUnit\Framework\TestCase;

/** Componentes de app/Views/partials/ui (docs/guia-estilo-ui.md). */
final class ComponentesUiTest extends TestCase
{
    public static function setUpBeforeClass(): void
    {
        require_once dirname(__DIR__, 2) . '/app/Helpers/funciones_vista.php';
    }

    public function testElBotonSigueLaJerarquia(): void
    {
        self::assertStringContainsString('class="btn btn-primary"', ui('boton', ['texto' => 'Guardar', 'tipo' => 'primario']));
        self::assertStringContainsString('class="btn btn-outline-secondary btn-sm"', ui('boton', ['texto' => 'Volver', 'tamano' => 'sm']));
        self::assertStringContainsString('class="btn btn-outline-danger"', ui('boton', ['texto' => 'Eliminar', 'tipo' => 'peligro']));
        self::assertMatchesRegularExpression('#^<a href="/bienes" class="btn btn-link">#', trim(ui('boton', ['texto' => 'Ver', 'tipo' => 'enlace', 'url' => '/bienes'])));
        self::assertMatchesRegularExpression('#^<button type="submit" class="btn btn-primary">#', trim(ui('boton', ['texto' => 'Guardar', 'tipo' => 'primario', 'tipoBoton' => 'submit'])));

        $this->expectException(\InvalidArgumentException::class);
        ui('boton', ['texto' => 'Aprobar', 'tipo' => 'exito']);
    }

    public function testUnBotonDeSoloIconoTieneNombreAccesible(): void
    {
        $html = ui('boton', ['texto' => 'Editar el bien PA-0001', 'icono' => 'editar', 'soloIcono' => true, 'tamano' => 'sm']);

        self::assertStringContainsString('aria-label="Editar el bien PA-0001"', $html);
        self::assertStringContainsString('title="Editar el bien PA-0001"', $html);
        self::assertStringContainsString('<i class="bi bi-pencil" aria-hidden="true"></i></button>', $html, 'el texto no se ve: solo el ícono');
    }

    public function testElBotonDePeligroPideConfirmacionConSuTexto(): void
    {
        $html = ui('boton', ['texto' => 'Eliminar', 'tipo' => 'peligro', 'tipoBoton' => 'submit', 'confirmar' => 'El espacio "A-101" pasará a la papelera.']);

        self::assertStringContainsString('data-confirmar="El espacio &quot;A-101&quot; pasará a la papelera."', $html);
        self::assertStringContainsString('data-confirmar-boton="Eliminar"', $html);
        self::assertStringContainsString('data-confirmar-tipo="peligro"', $html);
    }

    public function testLosAtributosSeEscapanYLosBooleanosSeRespetan(): void
    {
        $html = ui('boton', ['texto' => '<b>x</b>', 'atributos' => ['name' => 'accion', 'value' => 'a"b', 'disabled' => true, 'form' => null]]);

        self::assertStringContainsString('&lt;b&gt;x&lt;/b&gt;', $html);
        self::assertStringContainsString('value="a&quot;b"', $html);
        self::assertStringContainsString(' disabled>', $html);
        self::assertStringNotContainsString('form=', $html);
    }

    public function testInsigniasDeEstado(): void
    {
        self::assertStringContainsString('badge-estado-en_reparacion">En reparación<', ui('insignia_estado', ['tipo' => 'bien', 'estado' => 'en_reparacion']));
        self::assertStringContainsString('badge-solicitud-aprobada">Aprobada<', ui('insignia_estado', ['tipo' => 'baja', 'estado' => 'aprobada']));
        self::assertStringContainsString('badge-lote-unificado">Unificado<', ui('insignia_estado', ['tipo' => 'lote', 'estado' => 'unificado']));
        self::assertStringContainsString('etiqueta-individual">Individual<', ui('insignia_estado', ['tipo' => 'responsabilidad', 'estado' => 'individual']));
        self::assertStringContainsString('>estado&lt;raro&gt;<', ui('insignia_estado', ['tipo' => 'bien', 'estado' => 'estado<raro>']), 'un estado desconocido no rompe la página');
    }

    public function testElErrorDelCampoQuedaEnlazado(): void
    {
        $html = ui('campo_formulario', ['id' => 'codigo', 'nombre' => 'codigo_identificacion', 'etiqueta' => 'Código', 'ayuda' => 'codigo',
            'requerido' => true, 'valor' => 'PA-1', 'error' => 'La placa ya existe. Verifica el número o busca el bien.']);

        self::assertStringContainsString('class="form-control is-invalid"', $html);
        self::assertStringContainsString('aria-describedby="codigo-error"', $html);
        self::assertStringContainsString('id="codigo-error">La placa ya existe.', $html);
        self::assertStringContainsString('aria-label="Ayuda: Código de identificación"', $html);
        self::assertStringContainsString('value="PA-1"', $html);
    }

    public function testElSelectMarcaLaOpcionElegida(): void
    {
        $html = ui('campo_formulario', ['id' => 'estado', 'nombre' => 'estado', 'etiqueta' => 'Estado', 'tipo' => 'select',
            'opciones' => ['activo' => 'Activo', 'en_reparacion' => 'En reparación'], 'valor' => 'en_reparacion']);

        self::assertStringContainsString('<option value="en_reparacion" selected>', $html);
        self::assertStringContainsString('<option value="">-- Selecciona --</option>', $html);
    }

    public function testElAvisoLlevaElIconoYElRolDeSuTipo(): void
    {
        $error = ui('alerta', ['tipo' => 'peligro', 'mensaje' => 'No se pudo guardar.']);
        self::assertStringContainsString('role="alert"', $error);
        self::assertStringContainsString('bi-x-circle', $error);

        $estado = ui('alerta', ['tipo' => 'info', 'mensaje' => 'Lote pendiente.', 'cerrable' => false]);
        self::assertStringContainsString('role="status" data-sin-cerrar', $estado);
    }

    public function testUnComponenteConNombreRaroSeRechaza(): void
    {
        $this->expectException(\InvalidArgumentException::class);
        ui('../layout');
    }
}
