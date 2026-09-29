import { test, expect } from '@playwright/test';
import { execFileSync } from 'node:child_process';
import fs from 'node:fs';
import os from 'node:os';
import path from 'node:path';
import { PHP_BIN } from '../playwright.config.js';
import { datos, bd, comoRol } from './helpers/datos.js';

/**
 * Reportes > "Control del inventario" (ReporteController::control y
 * ReportesControl::controlXlsx): calidad del inventario, valor por espacio y categoría, y
 * funcionarios inactivos, en Excel. Rector (su institución y sedes) y superusuario.
 */
test.beforeEach(() => test.skip(datos().remoto === true, 'Necesita la base de pruebas local'));

function leerXlsx(cuerpo) {
    const ruta = path.join(os.tmpdir(), `control-${Date.now()}-${Math.random().toString(16).slice(2)}.xlsx`);
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

const familiaA = () => bd(`SELECT GROUP_CONCAT(id) FROM instituciones WHERE id = ${datos().instituciones.A} OR institucion_padre_id = ${datos().instituciones.A}`);

test('calidad del inventario: resumen por espacio y la lista de cada caso, solo de su institución', async ({ browser }) => {
    const d = datos();
    const contexto = await comoRol(browser, 'rector');
    const pagina = await contexto.newPage();
    await pagina.goto('reportes');
    await expect(pagina.getByRole('heading', { name: 'Control del inventario' })).toBeVisible();
    const [descarga] = await Promise.all([
        pagina.waitForEvent('download'),
        pagina.locator('#formControl button[value="calidad"]').click(),
    ]);
    expect(descarga.suggestedFilename()).toMatch(/^MIA_calidad_\d{4}-\d{2}-\d{2}\.xlsx$/);
    const libro = leerXlsx(fs.readFileSync(await descarga.path()));

    expect(Object.keys(libro)).toEqual(['Información', 'Resumen por espacio', 'Sin foto', 'Sin categoría', 'Sin ubicación', 'Sin QR pegado']);
    const codigoConFoto = bd(`SELECT codigo_identificacion FROM bienes WHERE id = ${d.bienes.A.silla}`);
    const sinFoto = libro['Sin foto'].map((f) => String(f[0]));
    expect(sinFoto).not.toContain(codigoConFoto);
    expect(sinFoto.length).toBeGreaterThan(1);
    // Nada de la institución B.
    const todo = JSON.stringify(libro);
    expect(todo).not.toContain('PB-0001');

    // El total de bienes del resumen coincide con la base.
    const [enc, ...filas] = libro['Resumen por espacio'];
    const totalResumen = filas.reduce((s, f) => s + Number(f[enc.indexOf('Bienes')]), 0);
    const enBase = Number(bd(`SELECT COUNT(*) FROM bienes WHERE estado IN ('activo','en_reparacion') AND institucion_id IN (${familiaA()})`));
    expect(totalResumen).toBe(enBase);
    await contexto.close();
});

test('valor por espacio y categoría: los totales coinciden con la base', async ({ browser }) => {
    const contexto = await comoRol(browser, 'rector');
    const respuesta = await contexto.request.get('reportes/control.xlsx?tipo=valor');
    expect(respuesta.status()).toBe(200);
    const libro = leerXlsx(await respuesta.body());
    expect(Object.keys(libro)).toEqual(['Información', 'Por espacio', 'Por categoría', 'Espacio × categoría']);

    const filaTotal = libro['Por espacio'].find((f) => f[0] === 'TOTAL');
    const [bienes, valor] = bd(`SELECT COUNT(*), ROUND(SUM(valor)) FROM bienes WHERE estado IN ('activo','en_reparacion') AND institucion_id IN (${familiaA()})`).split('\t').map(Number);
    expect(Number(filaTotal[2])).toBe(bienes);
    expect(Math.round(Number(filaTotal[3]))).toBe(valor);
    expect(Number(libro['Por categoría'].find((f) => f[0] === 'TOTAL')[1])).toBe(bienes);
    await contexto.close();
});

test('funcionarios inactivos: aparece quien no ingresa hace más de 30 días, no quien entró hoy', async ({ browser }) => {
    const d = datos();
    const marca = Date.now();
    const correo = `inactivo-${marca}@prueba.test`;
    bd(`INSERT INTO usuarios (documento, nombres, apellidos, cargo_id, email, password_hash, institucion_id, rol_id, activo, ultimo_login)
        SELECT 'INA-${marca}', 'Ina', 'Activo', cargo_id, '${correo}', password_hash, institucion_id, rol_id, 1, NOW() - INTERVAL 40 DAY
        FROM usuarios WHERE id = ${d.usuarios.docente.id}`);

    const contexto = await comoRol(browser, 'rector');
    const libro = leerXlsx(await (await contexto.request.get('reportes/control.xlsx?tipo=inactivos')).body());
    const filas = libro['Funcionarios inactivos'];
    const encabezado = filas[0];
    const fila = filas.find((f) => f[encabezado.indexOf('Correo')] === correo);
    expect(fila, 'el funcionario inactivo aparece').toBeTruthy();
    expect(Number(fila[encabezado.indexOf('Días sin ingresar')])).toBeGreaterThanOrEqual(40);
    expect(filas.some((f) => f[encabezado.indexOf('Correo')] === d.usuarios.rector.email)).toBe(false);
    await contexto.close();

    bd(`DELETE FROM usuarios WHERE email = '${correo}'`);
});
