import { test, expect } from '@playwright/test';
import { execFileSync } from 'node:child_process';
import fs from 'node:fs';
import os from 'node:os';
import path from 'node:path';
import { PHP_BIN } from '../playwright.config.js';
import { datos, bd } from './helpers/datos.js';
import { confirmar } from './helpers/confirmacion.js';

/**
 * Plantilla de la carga masiva de bienes (PlantillaCargaBienes): se descarga desde el ícono de
 * ayuda junto al título, trae las instrucciones dentro, las obligatorias en rojo claro y listas
 * desplegables de Ubicación y Categoría; y llenada tal como se descarga, se carga bien.
 */
test.beforeEach(() => test.skip(datos().remoto === true, 'Necesita la base de pruebas local'));
test.use({ storageState: 'playwright/.auth/rector.json' });

const php = (codigo, ...args) => execFileSync(PHP_BIN, ['-r', `require 'vendor/autoload.php'; ${codigo}`, ...args], { encoding: 'utf8' });

/** Lo que importa de la plantilla: hojas, títulos, colores, validaciones y listas. */
function leerPlantilla(ruta) {
    return JSON.parse(php(`
        $libro = PhpOffice\\PhpSpreadsheet\\IOFactory::load($argv[1]);
        $h = $libro->getSheetByName('Bienes'); $l = $libro->getSheetByName('Listas');
        $texto = fn ($v) => $v instanceof PhpOffice\\PhpSpreadsheet\\RichText\\RichText ? $v->getPlainText() : (string) $v;
        $lista = function (string $col) use ($l): array { $r = []; for ($i = 2; $l->getCell($col . $i)->getValue() !== null; $i++) { $r[] = (string) $l->getCell($col . $i)->getValue(); } return $r; };
        $validaciones = [];
        foreach ($h->getDataValidationCollection() as $rango => $v) { $validaciones[substr($rango, 0, 1)] = [$v->getType(), $v->getFormula1(), $v->getPromptTitle()]; }
        echo json_encode([
            'hojas' => array_map(fn ($s) => [$s->getTitle(), $s->getSheetState()], $libro->getAllSheets()),
            'activa' => $libro->getActiveSheet()->getTitle(),
            'titulos' => array_map(fn ($c) => explode("\\n", $texto($h->getCell($c . '8')->getValue()))[0], range('A', 'H')),
            'instrucciones' => implode(' ', array_map(fn ($f) => $texto($h->getCell('A' . $f)->getValue()), range(1, 7))),
            'relleno' => array_map(fn ($c) => $h->getStyle($c . '8')->getFill()->getStartColor()->getRGB(), ['A', 'B', 'C']),
            'validaciones' => $validaciones,
            'categorias' => $lista('A'),
            'ubicaciones' => $lista('B'),
        ], JSON_UNESCAPED_UNICODE);`, ruta));
}

/** Escribe filas en la plantilla desde la fila 9, como lo haría el usuario, y la guarda. */
function llenarPlantilla(ruta, filas) {
    php(`
        $libro = PhpOffice\\PhpSpreadsheet\\IOFactory::load($argv[1]); $h = $libro->getSheetByName('Bienes');
        foreach (json_decode($argv[2], true) as $i => $fila) { foreach ($fila as $j => $valor) {
            if ($valor !== '') { $h->getCell([$j + 1, $i + 9])->setValueExplicit((string) $valor, PhpOffice\\PhpSpreadsheet\\Cell\\DataType::TYPE_STRING); }
        } }
        (new PhpOffice\\PhpSpreadsheet\\Writer\\Xlsx($libro))->save($argv[1]);`, ruta, JSON.stringify(filas));
}

test('se descarga desde la ayuda del título, trae instrucciones y listas, y se carga tal como se llena', async ({ page }) => {
    const s = Date.now();
    const d = datos();
    const inst = d.instituciones.A;
    bd(`INSERT INTO espacios (institucion_id, nombre, codigo, activo) VALUES (${inst}, 'PW INACTIVO', 'PWP${s}I', 0)`);
    bd(`INSERT INTO espacios (institucion_id, nombre, codigo, activo, eliminado_en) VALUES (${inst}, 'PW PAPELERA', 'PWP${s}D', 1, NOW())`);
    const aula = bd(`SELECT CONCAT_WS('|', codigo, nombre) FROM espacios WHERE id = ${d.espacios.aulaA}`).split('|');
    const etiquetaAula = `${aula[0]} — ${aula[1]}`;

    await page.goto('cargas-masivas');
    // La guía y la plantilla, en el ícono (?) junto al título: se abre con el cursor.
    await page.getByRole('button', { name: 'Ayuda: Carga masiva de bienes' }).hover();
    const globo = page.locator('.ayuda-campo-globo:visible');
    await expect(globo).toContainText('Obligatorias (en rojo claro en la plantilla): Código (la placa) y Descripción.');
    await expect(globo).toContainText('No se borra nada:');
    const [descarga] = await Promise.all([
        page.waitForEvent('download'),
        globo.getByRole('link', { name: 'Descargar plantilla (.xlsx)' }).click(),
    ]);
    const ruta = path.join(os.tmpdir(), `plantilla-${s}.xlsx`);
    await descarga.saveAs(ruta);

    const p = leerPlantilla(ruta);
    expect(p.hojas).toEqual([['Bienes', 'visible'], ['Listas', 'veryHidden']]);
    expect(p.activa).toBe('Bienes');
    expect(p.titulos).toEqual(['Código *', 'Descripción *', 'Marca', 'Fecha de ingreso', 'Valor', 'Ubicación', 'Categoría', 'Responsable individual']);
    expect(p.instrucciones).toContain('No se borra nada: los bienes que no están en el archivo no se tocan');
    expect(p.instrucciones).toContain('Obligatorias (títulos en rojo claro): Código (la placa) y Descripción.');
    expect(p.relleno, 'obligatorias en rojo claro, opcionales en verde').toEqual(['E5A39B', 'E5A39B', 'E3F3E9']);
    expect(p.validaciones.F[0]).toBe('list');
    expect(p.validaciones.G[0]).toBe('list');
    expect(p.validaciones.A[2]).toBe('Código (obligatorio)');
    expect(p.categorias.join('|')).toBe(bd(`SELECT GROUP_CONCAT(nombre ORDER BY nombre SEPARATOR '|') FROM categorias_bienes
        WHERE institucion_id = ${inst} AND activo = 1 AND eliminado_en IS NULL`));
    expect(p.ubicaciones).toContain(etiquetaAula);
    expect(p.ubicaciones.some((u) => u.startsWith(`PWP${s}`)), 'sin espacios inactivos ni en la papelera').toBe(false);

    // Llenada como la deja la lista desplegable («código — nombre»), y con el código solo.
    llenarPlantilla(ruta, [
        [`PWT-${s}-1`, 'PW PLANTILLA LISTA', 'Rimax', '', '85000', etiquetaAula, p.categorias[0], ''],
        [`PWT-${s}-2`, 'PW PLANTILLA CODIGO', '', '', '', aula[0], '', ''],
        [`PWT-${s}-3`, 'PW INACTIVO', '', '', '', `PWP${s}I — PW INACTIVO`, '', ''],
        [`PWT-${s}-4`, 'PW PAPELERA', '', '', '', `PWP${s}D`, '', ''],
        [`PWT-${s}-5`, 'X'.repeat(201), '', '', '', '', '', ''],
        [`PWT-${s}-6`, 'PW FUTURA', '', '2099-01-01', '', '', '', ''],
    ]);
    await page.setInputFiles('input[name="archivo"]', ruta);
    await page.getByRole('button', { name: 'Analizar archivo' }).click();
    await expect(page).toHaveURL(/\/cargas-masivas\/\d+/);
    fs.unlinkSync(ruta);

    const fila = (codigo) => page.locator('tr', { has: page.locator('td[data-label="Código"]', { hasText: codigo }) });
    await expect(fila(`PWT-${s}-1`).locator('.badge')).toHaveText('Nuevo');
    await expect(fila(`PWT-${s}-2`).locator('.badge')).toHaveText('Nuevo');
    await expect(fila(`PWT-${s}-3`)).toContainText(`El espacio "PWP${s}I" está inactivo`);
    await expect(fila(`PWT-${s}-4`)).toContainText(`El espacio "PWP${s}D" está en la papelera`);
    await expect(fila(`PWT-${s}-5`)).toContainText('La descripción tiene más de 200 caracteres');
    await expect(fila(`PWT-${s}-6`)).toContainText('La fecha de ingreso 2099-01-01 es futura');
    // Las instrucciones de arriba no se leen como bienes.
    await expect(page.getByText('Falta código o descripción')).toHaveCount(0);

    await confirmar(page, () => page.getByRole('button', { name: 'Confirmar importación' }).click());
    await expect(page.locator('.avisos-pagina .alert-danger')).toContainText('aplicada parcialmente');
    const espacioDe = (codigo) => bd(`SELECT a.espacio_id FROM asignaciones a JOIN bienes b ON b.id = a.bien_id
        WHERE b.codigo_identificacion = '${codigo}' AND a.activa = 1`);
    expect(espacioDe(`PWT-${s}-1`), 'la etiqueta de la lista deja el bien en ese espacio').toBe(String(d.espacios.aulaA));
    expect(espacioDe(`PWT-${s}-2`), 'el código solo sigue sirviendo').toBe(String(d.espacios.aulaA));
    expect(bd(`SELECT COUNT(*) FROM bienes WHERE codigo_identificacion LIKE 'PWT-${s}-%' AND codigo_identificacion NOT IN ('PWT-${s}-1', 'PWT-${s}-2')`)).toBe('0');
});

test('la ayuda del título también se abre con el teclado y deja llegar al botón de descarga', async ({ page }) => {
    await page.goto('cargas-masivas');
    const ayuda = page.getByRole('button', { name: 'Ayuda: Carga masiva de bienes' });
    // Desde el elemento anterior, con Tab, como lo haría quien usa el teclado.
    await page.locator('h1.titulo-pagina').evaluate((h) => { h.tabIndex = -1; h.focus(); });
    await page.keyboard.press('Tab');
    await expect(ayuda).toBeFocused();
    await expect(ayuda).toHaveAttribute('aria-expanded', 'true');
    await page.keyboard.press('Tab');
    await expect(page.getByRole('link', { name: 'Descargar plantilla (.xlsx)' })).toBeFocused();
    await page.keyboard.press('Escape');
    await expect(ayuda).toBeFocused();
    await expect(ayuda).toHaveAttribute('aria-expanded', 'false');
});
