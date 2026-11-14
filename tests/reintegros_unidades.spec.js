import { test, expect } from '@playwright/test';
import { execFileSync } from 'node:child_process';
import fs from 'node:fs';
import os from 'node:os';
import path from 'node:path';
import { PHP_BIN } from '../playwright.config.js';
import { datos, comoRol, bd } from './helpers/datos.js';
import { confirmar } from './helpers/confirmacion.js';

// Solo en modo local: crea los bienes directamente en la base de pruebas.
test.beforeEach(() => test.skip(datos().remoto === true, 'Necesita la base de pruebas local'));

/**
 * Reintegro POR CANTIDAD de un código con varias unidades (alta masiva): el usuario dice
 * cuántas unidades reintegra desde cada ubicación, y el comprobante las suma en un solo
 * renglón (placa + CANT.).
 */
let contador = 0;

/**
 * Código con 10 unidades de Muebles: -004, -005 y -006 asignadas al aula A; las demás sin
 * asignar. Como en los datos reales, también existe el bien "resumen" de la cartera de la
 * Alcaldía con el mismo código: "10 SILLAS DE PRUEBA" por $1.000.000.
 */
function crearCodigoConUnidades() {
    const d = datos();
    const placa = `PWU${Date.now()}${++contador}`;
    const categoria = `(SELECT id FROM categorias_bienes WHERE institucion_id = ${d.instituciones.A} AND nombre = 'Muebles' LIMIT 1)`;
    const valores = Array.from({ length: 10 }, (_, i) => `(${d.instituciones.A}, '${placa}-${String(i + 1).padStart(3, '0')}', 'SILLAS DE PRUEBA', '${placa}',
        ${categoria}, '2024-01-15', 100000, 'activo', UUID(), ${d.usuarios.superusuario.id})`).join(',');
    bd(`INSERT INTO bienes (institucion_id, codigo_identificacion, descripcion, lote, categoria_id, fecha_ingreso, valor, estado, qr_token, created_by)
        VALUES (${d.instituciones.A}, '${placa}', '10 SILLAS DE PRUEBA', NULL, ${categoria}, '2024-01-10', 1000000, 'activo', UUID(), ${d.usuarios.superusuario.id}),
        ${valores};
        INSERT INTO asignaciones (bien_id, espacio_id, fecha_asignacion, activa, asignado_por)
        SELECT id, ${d.espacios.aulaA}, '2024-02-01', 1, ${d.usuarios.superusuario.id} FROM bienes
        WHERE lote = '${placa}' AND codigo_identificacion IN ('${placa}-004', '${placa}-005', '${placa}-006');`);
    return placa;
}

const reintegradasDe = (placa) => bd(`SELECT IFNULL(GROUP_CONCAT(SUBSTRING_INDEX(codigo_identificacion, '-', -1) ORDER BY codigo_identificacion), '')
    FROM bienes WHERE lote = '${placa}' AND estado = 'reintegrado'`);

/** "descripción|valor|estado" del bien resumen del código. */
const resumenDe = (placa) => bd(`SELECT CONCAT_WS('|', descripcion, valor, estado) FROM bienes WHERE codigo_identificacion = '${placa}' AND lote IS NULL`);

async function sesionDe(browser, rol) {
    const contexto = await comoRol(browser, rol);
    const pagina = await contexto.newPage();
    await pagina.goto('reintegros/unidades');
    const token = await pagina.locator('input[name="_csrf"]').first().inputValue().catch(() => '');
    return { contexto, pagina, token };
}

/** Celdas de la primera hoja de un .xlsx descargado. */
function celdasDelExcel(cuerpo, celdas) {
    const ruta = path.join(os.tmpdir(), `comprobante-${Date.now()}-${Math.random().toString(16).slice(2)}.xlsx`);
    fs.writeFileSync(ruta, cuerpo);
    try {
        return JSON.parse(execFileSync(PHP_BIN, ['-r',
            `require 'vendor/autoload.php'; $h = PhpOffice\\PhpSpreadsheet\\IOFactory::load($argv[1])->getSheet(0); $o = [];
             foreach (array_slice($argv, 2) as $c) { $o[$c] = $h->getCell($c)->getValue(); } echo json_encode($o);`,
            ruta, ...celdas,
        ], { encoding: 'utf8' }));
    } finally {
        fs.unlinkSync(ruta);
    }
}

test('reintegrar 3 unidades por ubicación: un solo renglón con CANT. 3 en el comprobante', async ({ browser }) => {
    const d = datos();
    const placa = crearCodigoConUnidades();
    const rector = await sesionDe(browser, 'rector');
    const { pagina } = rector;

    // Aparece en la lista de códigos con varias unidades, con su cartera.
    await pagina.goto(`reintegros/unidades?q=${placa}`);
    await expect(pagina.locator('tr', { hasText: placa }).locator('td[data-label="En cartera"]')).toHaveText('10 de 10');
    await pagina.locator('tr', { hasText: placa }).getByRole('link', { name: 'Reintegrar' }).click();

    await expect(pagina.getByRole('heading', { name: `Reintegrar por cantidad · Placa ${placa}` })).toBeVisible();
    await expect(pagina.locator('.alert-info'), 'avisa que el bien resumen se ajustará').toContainText('"10 SILLAS DE PRUEBA"');
    const claveAula = `e${d.espacios.aulaA}-u0`;
    await expect(pagina.locator('tr', { has: pagina.locator(`input[name="cantidad[${claveAula}]"]`) }).locator('td[data-label="Disponibles"]')).toHaveText('3');
    await expect(pagina.locator('tr', { has: pagina.locator('input[name="cantidad[sin]"]') }).locator('td[data-label="Disponibles"]')).toHaveText('7');

    await expect(pagina.locator('#botonReintegrarUnidades')).toBeDisabled();
    await pagina.locator(`input[name="cantidad[${claveAula}]"]`).fill('2');
    await pagina.locator('input[name="cantidad[sin]"]').fill('1');
    await expect(pagina.locator('#botonReintegrarUnidades')).toHaveText(/Reintegrar 3 unidad/);
    await expect(pagina.locator('#campoGenerarLote')).toBeChecked();

    await confirmar(pagina, () => pagina.locator('#botonReintegrarUnidades').click());

    await expect(pagina).toHaveURL(/\/reintegros\/lotes\/\d+$/);
    await expect(pagina.locator('.alert-success')).toContainText(`Se reintegraron 3 unidades de la placa ${placa}.`);
    await expect(pagina.locator('.alert-success')).toContainText(/Lote de reintegro #\d+ Muebles \(3 bienes\) generado/);
    await expect(pagina.locator('.alert-success')).toContainText(`El bien ${placa} de la cartera quedó como "7 SILLAS DE PRUEBA".`);

    // El bien resumen de la cartera resta lo reintegrado (cantidad y valor), con auditoría.
    expect(resumenDe(placa)).toBe('7 SILLAS DE PRUEBA|700000.00|activo');
    expect(bd(`SELECT COUNT(*) FROM auditoria WHERE accion = 'editar' AND entidad = 'bien'
               AND entidad_id = (SELECT id FROM bienes WHERE codigo_identificacion = '${placa}' AND lote IS NULL)`)).toBe('1');

    // Los últimos consecutivos de cada ubicación: -005 y -006 del aula, -010 sin asignar.
    expect(reintegradasDe(placa)).toBe('005,006,010');
    expect(bd(`SELECT COUNT(*) FROM asignaciones a JOIN bienes b ON b.id = a.bien_id WHERE b.lote = '${placa}' AND a.activa = 1`), 'solo -004 sigue en el aula').toBe('1');
    const loteId = Number(pagina.url().match(/lotes\/(\d+)$/)[1]);
    expect(bd(`SELECT COUNT(*) FROM movimientos m JOIN bienes b ON b.id = m.bien_id WHERE b.lote = '${placa}' AND m.lote_reintegro_id = ${loteId}`)).toBe('3');
    expect(bd(`SELECT COUNT(*) FROM auditoria WHERE accion = 'reintegrar' AND entidad = 'bien'
               AND entidad_id IN (SELECT id FROM bienes WHERE lote = '${placa}') AND datos_despues LIKE '%por_cantidad%'`)).toBe('3');

    // Detalle del lote: un renglón con la placa y la cantidad.
    const renglon = pagina.locator('tbody tr', { has: pagina.locator('td[data-label="Placa"]', { hasText: placa }) });
    await expect(renglon).toHaveCount(1);
    await expect(renglon.locator('td[data-label="Cant."]')).toHaveText('3');
    await expect(renglon.locator('td[data-label="Placa"]')).toContainText('-005, -006, -010');

    // Comprobante FO-ADMI-009: PLACA = código, CANT. = 3, en un solo renglón.
    const excel = await rector.contexto.request.get(`reintegros/lotes/${loteId}/formato.xlsx`);
    expect(excel.status()).toBe(200);
    const celdas = celdasDelExcel(await excel.body(), ['A15', 'C15', 'D15', 'A16']);
    expect(celdas.A15).toBe(placa);
    expect(celdas.C15).toBe(3);
    expect(celdas.D15).toBe('SILLAS DE PRUEBA');
    expect(celdas.A16 ?? null, 'no hay un renglón por unidad').toBeNull();

    // En la lista ya quedan 7 en cartera.
    await pagina.goto(`reintegros/unidades?q=${placa}`);
    await expect(pagina.locator('tr', { hasText: placa }).locator('td[data-label="En cartera"]')).toHaveText('7 de 10');

    // En Bienes, la fila del código resta lo reintegrado (unidades y valor).
    await pagina.goto('bienes');
    const filaCodigo = pagina.locator('tbody tr', { has: pagina.locator('td[data-label="Código"]', { hasText: new RegExp(`^${placa}$`) }) })
        .filter({ hasText: 'en cartera de' });
    await expect(filaCodigo.locator('td[data-label="Descripción"]')).toContainText('7 en cartera de 10 unidades');
    await expect(filaCodigo.locator('td[data-label="Valor"]')).toHaveText('$700.000');
    await expect(filaCodigo.locator('td[data-label="Estado"]')).toContainText('3 reintegradas');

    // Excel de cartera completo: la hoja "Cartera por placa" trae la cantidad que queda, sin
    // sumar también el bien resumen (es el mismo código).
    const cartera = await rector.contexto.request.get('reportes/cartera.xlsx');
    expect(cartera.status()).toBe(200);
    expect(filaDePlaca(await cartera.body(), placa)).toEqual([placa, 'SILLAS DE PRUEBA', null, 'Muebles', 7, 3, 0, 100000, 700000]);
    await rector.contexto.close();
});

/** La fila de una placa en la hoja "Cartera por placa" de un .xlsx descargado. */
function filaDePlaca(cuerpo, placa) {
    const ruta = path.join(os.tmpdir(), `cartera-${Date.now()}-${Math.random().toString(16).slice(2)}.xlsx`);
    fs.writeFileSync(ruta, cuerpo);
    try {
        return JSON.parse(execFileSync(PHP_BIN, ['-r',
            `require 'vendor/autoload.php'; $h = PhpOffice\\PhpSpreadsheet\\IOFactory::load($argv[1])->getSheetByName('Cartera por placa');
             foreach ($h->toArray(null, true, false) as $f) { if ($f[0] === $argv[2]) { echo json_encode($f); exit; } } echo 'null';`,
            ruta, placa,
        ], { encoding: 'utf8' }));
    } finally {
        fs.unlinkSync(ruta);
    }
}

test('reintegro por cantidad: todo o nada y sin generar lote', async ({ browser }) => {
    const d = datos();
    const placa = crearCodigoConUnidades();
    const rector = await sesionDe(browser, 'rector');
    await rector.pagina.goto(`reintegros/unidades/${placa}`);
    const token = await rector.pagina.locator('input[name="_csrf"]').first().inputValue();

    // Más unidades de las que hay en el aula: no se reintegra ninguna.
    await rector.contexto.request.post(`reintegros/unidades/${placa}`, {
        form: { _csrf: token, [`cantidad[e${d.espacios.aulaA}-u0]`]: '5', 'cantidad[sin]': '1', fecha: '2026-11-01', destino_texto: 'Bodega de reintegro', generar_lote: '1' },
        maxRedirects: 0,
    });
    await rector.pagina.goto(`reintegros/unidades/${placa}`);
    await expect(rector.pagina.locator('.alert-danger')).toContainText('solo quedan 3 unidad(es)');
    expect(reintegradasDe(placa)).toBe('');
    expect(resumenDe(placa), 'el bien resumen no cambia').toBe('10 SILLAS DE PRUEBA|1000000.00|activo');

    // Sin cantidades: error.
    await rector.contexto.request.post(`reintegros/unidades/${placa}`, {
        form: { _csrf: token, 'cantidad[sin]': '0', fecha: '2026-11-01', destino_texto: 'Bodega de reintegro' }, maxRedirects: 0,
    });
    await rector.pagina.goto(`reintegros/unidades/${placa}`);
    await expect(rector.pagina.locator('.alert-danger')).toContainText('Indica cuántas unidades');

    // Sin "generar lote": quedan como reintegros sueltos, listos para agrupar.
    await rector.pagina.locator('input[name="cantidad[sin]"]').fill('2');
    await rector.pagina.locator('#campoGenerarLote').uncheck();
    await confirmar(rector.pagina, () => rector.pagina.locator('#botonReintegrarUnidades').click());
    await expect(rector.pagina).toHaveURL(/\/reintegros\/lotes\/generar$/);
    await expect(rector.pagina.locator('.alert-success')).toContainText(`Se reintegraron 2 unidades de la placa ${placa}.`);
    expect(reintegradasDe(placa)).toBe('009,010');
    expect(resumenDe(placa)).toBe('8 SILLAS DE PRUEBA|800000.00|activo');
    expect(bd(`SELECT COUNT(*) FROM movimientos m JOIN bienes b ON b.id = m.bien_id WHERE b.lote = '${placa}' AND m.lote_reintegro_id IS NULL`)).toBe('2');
    await rector.contexto.close();
});

test('la ficha de una unidad sin asignar ofrece "Reintegrar"; un bien suelto sin asignar no', async ({ browser }) => {
    const d = datos();
    const placa = crearCodigoConUnidades();
    const unidad = bd(`SELECT id FROM bienes WHERE codigo_identificacion = '${placa}-001'`);
    const rector = await sesionDe(browser, 'rector');

    await rector.pagina.goto(`bienes/${unidad}/editar`);
    await expect(rector.pagina.locator('#accionBien option[value="reintegrar"]')).toHaveCount(1);
    await rector.pagina.goto(`bienes/${d.bienes.A.libre}/editar`);
    await expect(rector.pagina.locator('#accionBien option[value="reintegrar"]')).toHaveCount(0);
    await rector.contexto.close();
});
