import { test, expect } from '@playwright/test';
import { execFileSync } from 'node:child_process';
import fs from 'node:fs';
import os from 'node:os';
import path from 'node:path';
import { PHP_BIN } from '../playwright.config.js';
import { datos, bd, comoRol } from './helpers/datos.js';

/**
 * Filtros de la lista de bienes por Espacio, Responsable y Tipo de responsabilidad
 * (Grupal / Individual / Sin asignar), la cartera en Excel con esos filtros y los mismos
 * filtros en la asignación masiva.
 */
test.beforeEach(() => test.skip(datos().remoto === true, 'Necesita la base de pruebas local'));

function leerXlsx(cuerpo) {
    const ruta = path.join(os.tmpdir(), `filtros-${Date.now()}-${Math.random().toString(16).slice(2)}.xlsx`);
    fs.writeFileSync(ruta, cuerpo);
    const codigo = `require 'vendor/autoload.php';
        $libro = PhpOffice\\PhpSpreadsheet\\IOFactory::load($argv[1]); $h = [];
        foreach ($libro->getWorksheetIterator() as $hoja) { $h[$hoja->getTitle()] = $hoja->toArray(null, false, false); }
        echo json_encode($h);`;
    try {
        return JSON.parse(execFileSync(PHP_BIN, ['-r', codigo, ruta], { encoding: 'utf8' }));
    } finally {
        fs.unlinkSync(ruta);
    }
}

/**
 * Espacio 1 (responsable: docente) y Espacio 2 (responsable: rector), y cinco bienes:
 * G1 grupal en E1, G2 grupal en E2, I1 individual del secretario sin espacio,
 * I2 individual del docente guardado en E2, S1 sin asignar.
 */
function prepararEscenario() {
    const s = Date.now();
    const d = datos();
    const inst = d.instituciones.A;
    bd(`INSERT INTO espacios (institucion_id, codigo, nombre, activo) VALUES (${inst}, 'PWF1-${s}', 'PW Filtro uno ${s}', 1), (${inst}, 'PWF2-${s}', 'PW Filtro dos ${s}', 1)`);
    const [e1, e2] = bd(`SELECT id FROM espacios WHERE codigo IN ('PWF1-${s}', 'PWF2-${s}') ORDER BY codigo`).split('\n').map(Number);
    bd(`INSERT INTO espacio_responsables (espacio_id, usuario_id) VALUES (${e1}, ${d.usuarios.docente.id}), (${e2}, ${d.usuarios.rector.id})`);
    const bien = (etiqueta, persona, espacio) => {
        bd(`INSERT INTO bienes (institucion_id, codigo_identificacion, descripcion, categoria_id, fecha_ingreso, valor, estado, qr_token)
            SELECT ${inst}, 'PWF-${s}-${etiqueta}', 'PW filtro ${etiqueta}', id, CURDATE(), 1000, 'activo', UUID()
              FROM categorias_bienes WHERE institucion_id = ${inst} AND nombre = 'Muebles' LIMIT 1`);
        const id = Number(bd(`SELECT id FROM bienes WHERE codigo_identificacion = 'PWF-${s}-${etiqueta}'`));
        if (persona !== null || espacio !== null) {
            bd(`INSERT INTO asignaciones (bien_id, usuario_responsable_id, espacio_id, fecha_asignacion, activa)
                VALUES (${id}, ${persona ?? 'NULL'}, ${espacio ?? 'NULL'}, CURDATE(), 1)`);
        }
        return id;
    };
    const ids = {
        G1: bien('G1', null, e1),
        G2: bien('G2', null, e2),
        I1: bien('I1', d.usuarios.secretario.id, null),
        I2: bien('I2', d.usuarios.docente.id, e2),
        S1: bien('S1', null, null),
    };
    return { s, e1, e2, ids };
}

async function codigosEn(pagina, url, s) {
    await pagina.goto(url);
    const codigos = await pagina.locator('tbody td[data-label="Código"]').allInnerTexts();
    return codigos.map((c) => c.trim().replace(`PWF-${s}-`, '')).sort();
}

test('filtrar por responsable, tipo y espacio en la lista de bienes', async ({ browser }) => {
    const { s, e2 } = prepararEscenario();
    const d = datos();
    const pagina = await (await comoRol(browser, 'rector')).newPage();
    const base = `bienes?q=PWF-${s}`;

    expect(await codigosEn(pagina, `${base}&responsable=${d.usuarios.docente.id}`, s)).toEqual(['G1', 'I2']);
    expect(await codigosEn(pagina, `${base}&responsable=${d.usuarios.rector.id}`, s)).toEqual(['G2']);
    expect(await codigosEn(pagina, `${base}&responsable=${d.usuarios.docente.id}&tipo=individual`, s)).toEqual(['I2']);
    expect(await codigosEn(pagina, `${base}&espacio=${e2}`, s)).toEqual(['G2', 'I2']);
    expect(await codigosEn(pagina, `${base}&tipo=grupal`, s)).toEqual(['G1', 'G2']);
    expect(await codigosEn(pagina, `${base}&tipo=sin_asignar`, s)).toEqual(['S1']);

    // Con el selector de la pantalla.
    await pagina.goto(base);
    await pagina.locator('#filtroTipo').selectOption('individual');
    await expect(pagina).toHaveURL(/tipo=individual/);
    expect(await pagina.locator('tbody td[data-label="Código"]').count()).toBe(2);
    await pagina.context().close();
});

test('"Descargar en Excel" trae la cartera con los mismos filtros y una hoja que los dice', async ({ browser }) => {
    const { s } = prepararEscenario();
    const d = datos();
    const contexto = await comoRol(browser, 'rector');
    const pagina = await contexto.newPage();
    await pagina.goto(`bienes?q=PWF-${s}&responsable=${d.usuarios.docente.id}&tipo=individual`);

    const enlace = pagina.locator('#descargarCarteraFiltrada');
    const href = await enlace.getAttribute('href');
    expect(href).toContain(`responsable=${d.usuarios.docente.id}`);
    expect(href).toContain('tipo=individual');

    const respuesta = await contexto.request.get(href);
    expect(respuesta.status()).toBe(200);
    const libro = leerXlsx(await respuesta.body());
    expect(Object.keys(libro)).toEqual(['Cartera de bienes', 'Filtros']);
    const [encabezado, ...filas] = libro['Cartera de bienes'];
    expect(encabezado).toContain('Tipo de responsabilidad');
    expect(filas.map((f) => f[0])).toEqual([`PWF-${s}-I2`]);
    expect(filas[0][encabezado.indexOf('Tipo de responsabilidad')]).toBe('Individual');
    const filtros = Object.fromEntries(libro.Filtros.slice(1));
    expect(filtros['Tipo de responsabilidad']).toBe('Individual');
    expect(filtros.Responsable).toBe(bd(`SELECT CONCAT(nombres, ' ', apellidos) FROM usuarios WHERE id = ${d.usuarios.docente.id}`));
    expect(Number(filtros.Bienes)).toBe(1);
    await contexto.close();
});

test('el docente no ve el filtro de responsable ni el de tipo', async ({ browser }) => {
    const pagina = await (await comoRol(browser, 'docente')).newPage();
    await pagina.goto('bienes');
    await expect(pagina.locator('#filtroResponsable')).toHaveCount(0);
    await expect(pagina.locator('#filtroTipo')).toHaveCount(0);
    await pagina.context().close();
});

test('asignación masiva: filtrar los individuales del docente y pasarlos al secretario', async ({ browser }) => {
    const { s, ids } = prepararEscenario();
    const d = datos();
    const pagina = await (await comoRol(browser, 'rector')).newPage();

    await pagina.goto(`asignaciones?q=PWF-${s}&responsable=${d.usuarios.docente.id}&tipo=individual`);
    const casillas = pagina.locator('input.casilla-bien');
    await expect(casillas).toHaveCount(1);
    await pagina.locator('#seleccionarTodos').check();

    await pagina.locator('#masivoTipoIndividual').check();
    await pagina.locator('#masivoPersona').selectOption(String(d.usuarios.secretario.id));
    pagina.once('dialog', (dialogo) => dialogo.accept());
    await pagina.locator('.boton-asignar').first().click();
    await expect(pagina.locator('.alert-success')).toContainText('1 bien(es) asignado(s)');

    expect(bd(`SELECT CONCAT(usuario_responsable_id, '/', IFNULL(espacio_id, '-')) FROM asignaciones WHERE bien_id = ${ids.I2} AND activa = 1`))
        .toBe(`${d.usuarios.secretario.id}/-`);
    // Los demás no cambiaron.
    expect(bd(`SELECT usuario_responsable_id FROM asignaciones WHERE bien_id = ${ids.I1} AND activa = 1`)).toBe(String(d.usuarios.secretario.id));
    await pagina.context().close();
});
