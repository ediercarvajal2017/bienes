import { test, expect } from '@playwright/test';
import { execFileSync } from 'node:child_process';
import fs from 'node:fs';
import os from 'node:os';
import path from 'node:path';
import { PHP_BIN } from '../playwright.config.js';
import { datos, bd } from './helpers/datos.js';
import { confirmar } from './helpers/confirmacion.js';

/**
 * Plantillas de las cargas masivas de bienes, espacios y usuarios (PlantillaExcel): se
 * descargan desde el ícono de ayuda junto al título, traen las instrucciones dentro, las
 * obligatorias en rojo claro (y en amarillo las que solo pide un registro nuevo) y listas
 * desplegables con lo que hay en MIA; y llenadas tal como se descargan, se cargan bien.
 */
test.beforeEach(() => test.skip(datos().remoto === true, 'Necesita la base de pruebas local'));
test.use({ storageState: 'playwright/.auth/rector.json' });

const php = (codigo, ...args) => execFileSync(PHP_BIN, ['-r', `require 'vendor/autoload.php'; ${codigo}`, ...args], { encoding: 'utf8' });

/** Lo que importa de la plantilla: hojas, títulos, colores, validaciones y listas (por nombre). */
function leerPlantilla(ruta, hoja, filaTitulos) {
    return JSON.parse(php(`
        $libro = PhpOffice\\PhpSpreadsheet\\IOFactory::load($argv[1]);
        $h = $libro->getSheetByName($argv[2]); $l = $libro->getSheetByName('Listas'); $f = (int) $argv[3];
        $texto = fn ($v) => $v instanceof PhpOffice\\PhpSpreadsheet\\RichText\\RichText ? $v->getPlainText() : (string) $v;
        $columnas = range('A', $h->getHighestDataColumn());
        $listas = [];
        for ($c = 1; $l->getCell([$c, 1])->getValue() !== null; $c++) {
            $valores = []; for ($i = 2; $l->getCell([$c, $i])->getValue() !== null; $i++) { $valores[] = (string) $l->getCell([$c, $i])->getValue(); }
            $listas[(string) $l->getCell([$c, 1])->getValue()] = $valores;
        }
        $validaciones = [];
        foreach ($h->getDataValidationCollection() as $rango => $v) { $validaciones[substr($rango, 0, 1)] = [$v->getType(), $v->getPromptTitle()]; }
        echo json_encode([
            'hojas' => array_map(fn ($s) => [$s->getTitle(), $s->getSheetState()], $libro->getAllSheets()),
            'activa' => $libro->getActiveSheet()->getTitle(),
            'titulos' => array_map(fn ($c) => explode("\\n", $texto($h->getCell($c . $f)->getValue()))[0], $columnas),
            'instrucciones' => implode(' ', array_map(fn ($i) => $texto($h->getCell('A' . $i)->getValue()), range(1, $f - 1))),
            'relleno' => array_map(fn ($c) => $h->getStyle($c . $f)->getFill()->getStartColor()->getRGB(), $columnas),
            'validaciones' => $validaciones,
            'listas' => $listas,
        ], JSON_UNESCAPED_UNICODE);`, ruta, hoja, String(filaTitulos)));
}

/** Escribe filas debajo de los títulos, como lo haría el usuario, y la guarda. */
function llenarPlantilla(ruta, hoja, filaTitulos, filas) {
    php(`
        $libro = PhpOffice\\PhpSpreadsheet\\IOFactory::load($argv[1]); $h = $libro->getSheetByName($argv[2]); $f = (int) $argv[4];
        foreach (json_decode($argv[3], true) as $i => $fila) { foreach ($fila as $j => $valor) {
            if ($valor !== '') { $h->getCell([$j + 1, $i + $f + 1])->setValueExplicit((string) $valor, PhpOffice\\PhpSpreadsheet\\Cell\\DataType::TYPE_STRING); }
        } }
        (new PhpOffice\\PhpSpreadsheet\\Writer\\Xlsx($libro))->save($argv[1]);`, ruta, hoja, JSON.stringify(filas), String(filaTitulos));
}

/** Abre la ayuda del título con el cursor, revisa su texto y descarga la plantilla. */
async function descargarDesdeLaAyuda(page, ruta, titulo, textoAyuda) {
    await page.goto(ruta);
    await page.getByRole('button', { name: `Ayuda: ${titulo}` }).hover();
    const globo = page.locator('.ayuda-campo-globo:visible');
    await expect(globo).toContainText(textoAyuda);
    await expect(globo).toContainText('No se borra nada:');
    const [descarga] = await Promise.all([
        page.waitForEvent('download'),
        globo.getByRole('link', { name: 'Descargar plantilla (.xlsx)' }).click(),
    ]);
    const archivo = path.join(os.tmpdir(), `plantilla-${Date.now()}-${Math.random().toString(16).slice(2)}.xlsx`);
    await descarga.saveAs(archivo);
    return archivo;
}

async function subir(page, archivo, ruta) {
    await page.setInputFiles('input[name="archivo"]', archivo);
    await page.getByRole('button', { name: 'Analizar archivo' }).click();
    await expect(page).toHaveURL(new RegExp(`${ruta}/\\d+`));
    fs.unlinkSync(archivo);
}

const ROJO = 'E5A39B';
const AMARILLO = 'E0B54D';
const VERDE = 'E3F3E9';
const documentoDe = (id) => bd(`SELECT documento FROM usuarios WHERE id = ${id}`);
const etiquetaPersona = (lista, id) => lista.find((p) => p.startsWith(`${documentoDe(id)} — `));

test('bienes: se descarga desde la ayuda del título, trae instrucciones y listas, y se carga tal como se llena', async ({ page }) => {
    const s = Date.now();
    const d = datos();
    const inst = d.instituciones.A;
    bd(`INSERT INTO espacios (institucion_id, nombre, codigo, activo) VALUES (${inst}, 'PW INACTIVO', 'PWP${s}I', 0)`);
    bd(`INSERT INTO espacios (institucion_id, nombre, codigo, activo, eliminado_en) VALUES (${inst}, 'PW PAPELERA', 'PWP${s}D', 1, NOW())`);
    const aula = bd(`SELECT CONCAT_WS('|', codigo, nombre) FROM espacios WHERE id = ${d.espacios.aulaA}`).split('|');
    const etiquetaAula = `${aula[0]} — ${aula[1]}`;

    const ruta = await descargarDesdeLaAyuda(page, 'cargas-masivas', 'Carga masiva de bienes',
        'Obligatorias (en rojo claro en la plantilla): Código (la placa) y Descripción.');
    const p = leerPlantilla(ruta, 'Bienes', 8);
    expect(p.hojas).toEqual([['Bienes', 'visible'], ['Listas', 'veryHidden']]);
    expect(p.activa).toBe('Bienes');
    expect(p.titulos).toEqual(['Código *', 'Descripción *', 'Marca', 'Fecha de ingreso', 'Valor', 'Ubicación', 'Categoría', 'Responsable individual']);
    expect(p.instrucciones).toContain('No se borra nada: los bienes que no están en el archivo no se tocan');
    expect(p.instrucciones).toContain('Obligatorias (títulos en rojo claro): Código (la placa) y Descripción.');
    expect(p.relleno.slice(0, 3), 'obligatorias en rojo claro, opcionales en verde').toEqual([ROJO, ROJO, VERDE]);
    expect(['F', 'G', 'H'].map((c) => p.validaciones[c][0])).toEqual(['list', 'list', 'list']);
    expect(p.validaciones.A[1]).toBe('Código (obligatorio)');
    expect(p.listas['Categorías'].join('|')).toBe(bd(`SELECT GROUP_CONCAT(nombre ORDER BY nombre SEPARATOR '|') FROM categorias_bienes
        WHERE institucion_id = ${inst} AND activo = 1 AND eliminado_en IS NULL`));
    expect(p.listas.Ubicaciones).toContain(etiquetaAula);
    expect(p.listas.Ubicaciones.some((u) => u.startsWith(`PWP${s}`)), 'sin espacios inactivos ni en la papelera').toBe(false);
    const docente = etiquetaPersona(p.listas.Personas, d.usuarios.docente.id);
    expect(docente, 'la lista de personas trae al docente').toBeTruthy();
    expect(etiquetaPersona(p.listas.Personas, d.usuarios.superusuario.id), 'un superusuario no tiene bienes a cargo').toBeUndefined();

    // Llenada como la dejan las listas desplegables («código — nombre»), y con el código solo.
    llenarPlantilla(ruta, 'Bienes', 8, [
        [`PWT-${s}-1`, 'PW PLANTILLA LISTA', 'Rimax', '', '85000', etiquetaAula, p.listas['Categorías'][0], docente],
        [`PWT-${s}-2`, 'PW PLANTILLA CODIGO', '', '', '', aula[0], '', ''],
        [`PWT-${s}-3`, 'PW INACTIVO', '', '', '', `PWP${s}I — PW INACTIVO`, '', ''],
        [`PWT-${s}-4`, 'PW PAPELERA', '', '', '', `PWP${s}D`, '', ''],
        [`PWT-${s}-5`, 'X'.repeat(201), '', '', '', '', '', ''],
        [`PWT-${s}-6`, 'PW FUTURA', '', '2099-01-01', '', '', '', ''],
    ]);
    await subir(page, ruta, 'cargas-masivas');

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
    const asignacion = (codigo) => bd(`SELECT CONCAT(IFNULL(a.usuario_responsable_id, '-'), '/', a.espacio_id) FROM asignaciones a
        JOIN bienes b ON b.id = a.bien_id WHERE b.codigo_identificacion = '${codigo}' AND a.activa = 1`);
    expect(asignacion(`PWT-${s}-1`), 'la persona y el espacio elegidos de las listas').toBe(`${d.usuarios.docente.id}/${d.espacios.aulaA}`);
    expect(asignacion(`PWT-${s}-2`), 'el código solo sigue sirviendo').toBe(`-/${d.espacios.aulaA}`);
    expect(bd(`SELECT COUNT(*) FROM bienes WHERE codigo_identificacion LIKE 'PWT-${s}-%' AND codigo_identificacion NOT IN ('PWT-${s}-1', 'PWT-${s}-2')`)).toBe('0');
});

test('espacios: la plantilla trae la lista de responsables y se carga tal como se llena', async ({ page }) => {
    const s = Date.now();
    const d = datos();
    const aula = bd(`SELECT CONCAT_WS('|', codigo, nombre) FROM espacios WHERE id = ${d.espacios.aulaA}`).split('|');
    const responsablesAntes = bd(`SELECT GROUP_CONCAT(usuario_id ORDER BY usuario_id) FROM espacio_responsables WHERE espacio_id = ${d.espacios.aulaA}`);
    bd(`INSERT INTO usuarios (documento, nombres, apellidos, cargo_id, email, password_hash, institucion_id, rol_id, activo)
        SELECT 'IE${s}', 'PW', 'INACTIVO', cargo_id, 'ie${s}@prueba.test', password_hash, institucion_id, rol_id, 0
          FROM usuarios WHERE id = ${d.usuarios.docente.id}`);

    const ruta = await descargarDesdeLaAyuda(page, 'espacios/carga-masiva', 'Carga masiva de espacios',
        'Obligatorias (en rojo claro en la plantilla): Número y Nombre.');
    const p = leerPlantilla(ruta, 'Espacios', 8);
    expect(p.hojas).toEqual([['Espacios', 'visible'], ['Listas', 'veryHidden']]);
    expect(p.titulos).toEqual(['Número *', 'Nombre *', 'Responsable 1', 'Responsable 2', 'Responsable 3']);
    expect(p.relleno, 'obligatorias en rojo, la de un espacio nuevo en amarillo').toEqual([ROJO, ROJO, AMARILLO, VERDE, VERDE]);
    expect(p.instrucciones).toContain('Obligatoria solo para un espacio nuevo (título en amarillo): Responsable 1.');
    expect(['C', 'D', 'E'].map((c) => p.validaciones[c][0])).toEqual(['list', 'list', 'list']);
    const secretario = etiquetaPersona(p.listas.Personas, d.usuarios.secretario.id);
    const docente = etiquetaPersona(p.listas.Personas, d.usuarios.docente.id);
    expect(secretario && docente, 'la lista trae a los usuarios activos').toBeTruthy();
    expect(p.listas.Personas.some((u) => u.startsWith(`IE${s} `)), 'sin usuarios inactivos').toBe(false);

    llenarPlantilla(ruta, 'Espacios', 8, [
        [`PWE${s}N`, 'PW ESPACIO DE LA PLANTILLA', secretario, docente, ''],
        [aula[0], aula[1], '', '', ''],
        [`PWE${s}L`, 'X'.repeat(101), secretario, '', ''],
    ]);
    await subir(page, ruta, 'espacios/carga-masiva');

    const fila = (texto) => page.locator('tbody tr', { hasText: texto });
    await expect(fila(`PWE${s}N`).locator('.badge')).toHaveText('Nuevo');
    await expect(fila(aula[1]).locator('.badge')).toHaveText('Sin cambios');
    await expect(fila(`PWE${s}L`)).toContainText('El nombre tiene más de 100 caracteres');
    await expect(page.getByText('Falta el número o el nombre del espacio')).toHaveCount(0);

    await confirmar(page, () => page.getByRole('button', { name: 'Confirmar importación' }).click());
    await expect(page.locator('.avisos-pagina .alert-danger')).toContainText('aplicada parcialmente');
    expect(bd(`SELECT GROUP_CONCAT(er.usuario_id ORDER BY er.usuario_id) FROM espacio_responsables er JOIN espacios e ON e.id = er.espacio_id
        WHERE e.codigo = 'PWE${s}N'`), 'el espacio nuevo queda con los dos responsables elegidos')
        .toBe([d.usuarios.secretario.id, d.usuarios.docente.id].sort((a, b) => a - b).join(','));
    expect(bd(`SELECT GROUP_CONCAT(usuario_id ORDER BY usuario_id) FROM espacio_responsables WHERE espacio_id = ${d.espacios.aulaA}`),
        'el espacio existente sin responsables conserva los suyos').toBe(responsablesAntes);
});

test('usuarios: la plantilla trae listas de cargo y rol y se carga tal como se llena', async ({ page }) => {
    const s = Date.now();
    const ruta = await descargarDesdeLaAyuda(page, 'usuarios/carga-masiva', 'Carga masiva de usuarios',
        'Obligatoria (en rojo claro en la plantilla): Documento.');
    const p = leerPlantilla(ruta, 'Usuarios', 10);
    expect(p.hojas).toEqual([['Usuarios', 'visible'], ['Listas', 'veryHidden']]);
    expect(p.titulos).toEqual(['Documento *', 'Nombres', 'Apellidos', 'Cargo', 'Correo', 'Rol']);
    expect(p.relleno, 'Documento en rojo; lo de un usuario nuevo en amarillo; Rol en verde').toEqual([ROJO, AMARILLO, AMARILLO, AMARILLO, AMARILLO, VERDE]);
    expect(p.instrucciones).toContain('Contraseñas: el archivo nunca las trae.');
    expect(p.validaciones.D[0]).toBe('list');
    expect(p.validaciones.F[0]).toBe('list');
    expect(p.listas.Cargos.join('|')).toBe(bd("SELECT GROUP_CONCAT(nombre ORDER BY nombre SEPARATOR '|') FROM cargos WHERE activo = 1 AND eliminado_en IS NULL"));
    expect(p.listas.Roles, 'un rector no ve el rol superusuario').toEqual(['docente', 'rector', 'secretario']);

    llenarPlantilla(ruta, 'Usuarios', 10, [
        [`PL${s}`, 'PW', 'DE LA PLANTILLA', p.listas.Cargos[0], `pl${s}@prueba.test`, 'secretario'],
        [`${s}123456789`, 'PW', 'DOCUMENTO LARGO', p.listas.Cargos[0], `dl${s}@prueba.test`, ''],
    ]);
    await subir(page, ruta, 'usuarios/carga-masiva');

    const fila = (texto) => page.locator('tbody tr', { hasText: texto });
    await expect(fila(`PL${s}`).locator('.badge')).toHaveText('Nuevo');
    await expect(fila(`${s}123456789`)).toContainText('El documento tiene más de 20 caracteres');
    await expect(page.getByText('Falta el documento')).toHaveCount(0);

    await confirmar(page, () => page.getByRole('button', { name: 'Confirmar importación' }).click());
    await expect(page.locator('.avisos-pagina .alert-danger')).toContainText('aplicada parcialmente');
    expect(bd(`SELECT CONCAT_WS('|', c.nombre, r.nombre) FROM usuarios u JOIN cargos c ON c.id = u.cargo_id JOIN roles r ON r.id = u.rol_id
        WHERE u.documento = 'PL${s}'`)).toBe(`${p.listas.Cargos[0]}|secretario`);
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
