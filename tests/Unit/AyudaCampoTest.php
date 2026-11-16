<?php

declare(strict_types=1);

namespace Tests\Unit;

use App\Helpers\AyudaCampo;
use PHPUnit\Framework\TestCase;

/** Ayuda emergente (?) de los campos de los formularios. */
final class AyudaCampoTest extends TestCase
{
    public function testCadaAyudaTieneTituloYTexto(): void
    {
        foreach (AyudaCampo::TEXTOS as $clave => $ayuda) {
            self::assertMatchesRegularExpression('/^[a-z_]+$/', $clave);
            self::assertNotSame('', trim($ayuda['titulo']), "título de {$clave}");
            self::assertGreaterThan(30, mb_strlen($ayuda['texto']), "texto de {$clave}: debe explicar en detalle");
        }
    }

    public function testElBotonVaFueraDeLaEtiquetaYApuntaASuGlobo(): void
    {
        $html = AyudaCampo::etiqueta('categoriaBien', 'Categoría', 'categoria', true);

        self::assertMatchesRegularExpression('#<label class="form-label small requerido" for="categoriaBien">Categoría</label><button#', $html,
            'el botón va después del label, no dentro: así el nombre del campo no cambia');
        self::assertSame(1, preg_match('/aria-controls="(ayuda-categoria-\d+)"/', $html, $m));
        self::assertStringContainsString('id="' . $m[1] . '" role="tooltip" hidden', $html);
        self::assertStringContainsString('aria-label="Ayuda: Categoría"', $html);
        self::assertStringContainsString('aria-expanded="false"', $html);
    }

    public function testCadaGloboTieneUnIdDistinto(): void
    {
        preg_match('/aria-controls="([^"]+)"/', AyudaCampo::boton('espacio'), $uno);
        preg_match('/aria-controls="([^"]+)"/', AyudaCampo::boton('espacio'), $dos);

        self::assertNotSame($uno[1], $dos[1], 'la misma ayuda dos veces en la página (p. ej. dos bloques de Responsabilidad)');
    }

    public function testParrafosNegritaYEscape(): void
    {
        self::assertSame('<p><strong>Grupal:</strong> en un espacio.</p><p>Otro &lt;b&gt;párrafo&lt;/b&gt; &quot;citado&quot;.</p>',
            AyudaCampo::parrafos("**Grupal:** en un espacio.\n\nOtro <b>párrafo</b> \"citado\"."));
    }

    public function testUnaClaveSinTextoEsUnError(): void
    {
        $this->expectException(\InvalidArgumentException::class);
        AyudaCampo::boton('no_existe');
    }

    /** Cada ayuda que piden las vistas existe, y cada texto de TEXTOS se usa en alguna vista. */
    public function testLasVistasUsanAyudasQueExisten(): void
    {
        $usadas = [];
        $vistas = new \RecursiveIteratorIterator(new \RecursiveDirectoryIterator(dirname(__DIR__, 2) . '/app/Views', \FilesystemIterator::SKIP_DOTS));
        foreach ($vistas as $archivo) {
            if ($archivo->getExtension() !== 'php') {
                continue;
            }
            $codigo = (string) file_get_contents($archivo->getPathname());
            preg_match_all('/AyudaCampo::(?:boton|etiqueta)\((.*?)\)\s*\?>/', $codigo, $llamadas);
            foreach ($llamadas[1] as $argumentos) {
                preg_match_all("/'([a-z_]+)'/", $argumentos, $literales);
                if ($literales[1] === []) {
                    continue; // la clave llega en una variable (p. ej. partials/campo_foto.php con $ayuda)
                }
                $claves = array_values(array_intersect($literales[1], array_keys(AyudaCampo::TEXTOS)));
                self::assertNotSame([], $claves, 'Ayuda inexistente en ' . basename($archivo->getPathname()) . ": {$argumentos}");
                $usadas = array_merge($usadas, $claves);
            }
            if (preg_match_all("/'ayuda' => '([a-z_]+)'/", $codigo, $deFoto) > 0) {
                foreach ($deFoto[1] as $clave) {
                    self::assertArrayHasKey($clave, AyudaCampo::TEXTOS);
                    $usadas[] = $clave;
                }
            }
        }

        self::assertSame([], array_values(array_diff(array_keys(AyudaCampo::TEXTOS), $usadas)), 'textos de ayuda que ninguna vista usa');
    }
}
