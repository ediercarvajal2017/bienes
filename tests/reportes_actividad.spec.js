import { test, expect } from '@playwright/test';
import { execFileSync } from 'node:child_process';
import fs from 'node:fs';
import os from 'node:os';
import path from 'node:path';
import { PHP_BIN } from '../playwright.config.js';
import { datos, bd, comoRol } from './helpers/datos.js';

/**
 * Reportes > "Actividad de los funcionarios" (ReporteController::actividad y
 * App\Services\ReportesControl): Excel con el resumen por funcionario, los registros nuevos,
 * los actualizados y los movimientos de un período. Solo rector (su institución) y
 * superusuario.
 */
test.beforeEach(() => test.skip(datos().remoto === true, 'Necesita la base de pruebas local'));

/** Hojas del libro como listas de filas (lo lee PHP con PhpSpreadsheet). */
function leerXlsx(cuerpo) {
    const ruta = path.join(os.tmpdir(), `actividad-${Date.now()}-${Math.random().toString(16).slice(2)}.xlsx`);
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

test('el rector descarga la actividad de hoy: quién creó, qué cambió y el resumen', async ({ browser }) => {
    const d = datos();
    const contexto = await comoRol(browser, 'rector');
    const pagina = await contexto.newPage();
    const marca = Date.now();
    const codigo = `PW-ACT-${marca}`;

    // Un bien nuevo y un cambio hechos por el rector.
    await pagina.goto('bienes/crear');
    const token = await pagina.locator('input[name="_csrf"]').first().inputValue();
    await pagina.request.post('bienes', {
        form: { _csrf: token, codigo_identificacion: codigo, descripcion: 'silla de prueba', categoria_id: '',
            fecha_ingreso: '2026-01-15', valor: '1000', estado: 'activo' },
        maxRedirects: 0,
    });
    const id = Number(bd(`SELECT id FROM bienes WHERE codigo_identificacion = '${codigo}'`));
    await pagina.goto(`bienes/${id}/editar`);
    await pagina.locator('#campo-marca').fill('Rimax');
    await pagina.locator('#botonGuardarBien').click();
    await expect(pagina.locator('.alert-success')).toContainText('Bien actualizado');

    // La tarjeta está en Reportes.
    await pagina.goto('reportes');
    await expect(pagina.getByRole('heading', { name: 'Actividad de los funcionarios' })).toBeVisible();
    const [descarga] = await Promise.all([
        pagina.waitForEvent('download'),
        pagina.getByRole('button', { name: 'Todo en un solo Excel' }).click(),
    ]);
    expect(descarga.suggestedFilename()).toMatch(/^MIA_actividad_todo_\d{4}-\d{2}-\d{2}\.xlsx$/);
    const libro = leerXlsx(fs.readFileSync(await descarga.path()));

    expect(Object.keys(libro)).toEqual(['Información', 'Resumen por funcionario', 'Registros nuevos', 'Registros actualizados', 'Movimientos']);
    const nombreRector = bd(`SELECT TRIM(CONCAT_WS(' ', nombres, apellidos)) FROM usuarios WHERE id = ${d.usuarios.rector.id}`);

    const [encabezado, ...resumen] = libro['Resumen por funcionario'];
    const fila = resumen.find((f) => f[0] === nombreRector);
    expect(fila, 'el rector aparece en el resumen').toBeTruthy();
    expect(Number(fila[encabezado.indexOf('Registros nuevos')])).toBeGreaterThanOrEqual(1);
    expect(Number(fila[encabezado.indexOf('Actualizaciones')])).toBeGreaterThanOrEqual(1);

    const nuevos = libro['Registros nuevos'].map((f) => f.join(' | '));
    expect(nuevos.some((f) => f.includes(codigo) && f.includes(nombreRector))).toBe(true);

    const cambio = libro['Registros actualizados'].find((f) => String(f[4]).startsWith(codigo) && f[5] === 'Marca');
    expect(cambio, 'el cambio de marca aparece').toBeTruthy();
    expect(cambio[7]).toBe('Rimax');
    await contexto.close();
});

test('filtrar por funcionario, y el rector no puede pedir el de otra institución', async ({ browser }) => {
    const d = datos();
    const contexto = await comoRol(browser, 'rector');
    const nombreRector = bd(`SELECT TRIM(CONCAT_WS(' ', nombres, apellidos)) FROM usuarios WHERE id = ${d.usuarios.rector.id}`);

    const soloDocente = await contexto.request.get(`reportes/actividad.xlsx?tipo=resumen&periodo=7dias&usuario=${d.usuarios.docente.id}`);
    expect(soloDocente.status()).toBe(200);
    const libro = leerXlsx(await soloDocente.body());
    expect(libro['Resumen por funcionario'].some((f) => f[0] === nombreRector)).toBe(false);

    const otraInstitucion = await contexto.request.get(`reportes/actividad.xlsx?tipo=resumen&usuario=${d.usuarios.rector_b.id}`, { maxRedirects: 0 });
    expect(otraInstitucion.status()).toBe(302);

    // Pedir otra institución por la URL no cambia nada: el rector ve la suya.
    const conParametro = await contexto.request.get(`reportes/actividad.xlsx?tipo=resumen&institucion=${d.instituciones.B}`);
    const info = leerXlsx(await conParametro.body())['Información'];
    const nombreA = bd(`SELECT nombre FROM instituciones WHERE id = ${d.instituciones.A}`);
    expect(info.find((f) => f[0] === 'Institución')[1]).toContain(nombreA);
    await contexto.close();
});

test('el secretario y el docente no pueden descargar la actividad', async ({ browser }) => {
    for (const rol of ['secretario', 'docente']) {
        const contexto = await comoRol(browser, rol);
        const respuesta = await contexto.request.get('reportes/actividad.xlsx?tipo=resumen', { maxRedirects: 0 });
        expect(respuesta.status(), rol).toBe(403);
        await contexto.close();
    }
});
