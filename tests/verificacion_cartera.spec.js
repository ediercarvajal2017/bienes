import { test, expect } from '@playwright/test';
import AxeBuilder from '@axe-core/playwright';
import { execFileSync } from 'node:child_process';
import fs from 'node:fs';
import os from 'node:os';
import path from 'node:path';
import { PHP_BIN } from '../playwright.config.js';
import { datos, bd, comoRol } from './helpers/datos.js';

/**
 * Verificar un lote en la cartera de la Alcaldía (CarteraController::verificacion,
 * App\Services\VerificacionCartera): se elige un lote entregado y MIA muestra solo sus bienes
 * que siguen en la cartera registrada en /cartera/enviar. Solo lee.
 *
 * Los bienes y los lotes se crean por SQL; la cartera se registra como lo haría el usuario.
 */
test.beforeEach(() => test.skip(datos().remoto === true, 'Necesita la base de pruebas local'));
test.use({ storageState: 'playwright/.auth/rector.json' });

let contador = 0;

/** .xlsx de la cartera con las filas dadas (la primera son los títulos); la placa va como texto. */
function crearCartera(filas) {
    const ruta = path.join(os.tmpdir(), `cartera-${Date.now()}-${++contador}.xlsx`);
    const codigo = `require 'vendor/autoload.php';
        $filas = json_decode($argv[2], true); $libro = new PhpOffice\\PhpSpreadsheet\\Spreadsheet(); $hoja = $libro->getActiveSheet();
        foreach ($filas as $i => $fila) { foreach ($fila as $j => $valor) {
            if ($valor !== '') { $hoja->getCell([$j + 1, $i + 1])->setValue($valor); }
        } }
        (new PhpOffice\\PhpSpreadsheet\\Writer\\Xlsx($libro))->save($argv[1]);`;
    execFileSync(PHP_BIN, ['-r', codigo, ruta, JSON.stringify(filas)]);
    return ruta;
}

/**
 * Lote de reintegro con estado y fecha de entrega, y sus bienes: cada uno {codigo, lote?}.
 * Devuelve el id del lote.
 */
function crearLote({ estado = 'reintegrado', entregado = '2024-03-01', bienes }) {
    const d = datos();
    const inst = d.instituciones.A;
    const filas = bienes.map((b) => `
        INSERT INTO bienes (institucion_id, codigo_identificacion, lote, descripcion, fecha_ingreso, valor, estado, qr_token, created_by)
        VALUES (${inst}, '${b.codigo}', ${b.lote ? `'${b.lote}'` : 'NULL'}, '${b.descripcion ?? 'PW-TEST bien verificado'}', '2024-01-15', 1000, 'reintegrado', UUID(), ${d.usuarios.superusuario.id});
        INSERT INTO movimientos (bien_id, tipo, fecha, responsable_id, destino_texto, lote_reintegro_id)
        VALUES (LAST_INSERT_ID(), 'reintegro', '2024-02-20', ${d.usuarios.rector.id}, 'Bodega de reintegro', @lote);`).join('');

    return Number(bd(`
        INSERT INTO lotes_reintegro (institucion_id, fecha, destino_texto, registrado_por, estado, reintegrado_fecha, reintegrado_por)
        VALUES (${inst}, '2024-02-20', 'PW-TEST verificar cartera', ${d.usuarios.rector.id}, '${estado}',
                ${estado === 'reintegrado' ? `'${entregado}'` : 'NULL'}, ${estado === 'reintegrado' ? d.usuarios.rector.id : 'NULL'});
        SET @lote = LAST_INSERT_ID();
        ${filas}
        SELECT @lote;`));
}

/** Unidades de una placa que siguen activas (en la cartera según MIA). */
function crearUnidadesActivas(placa, desde, cantidad) {
    const d = datos();
    for (let i = 0; i < cantidad; i++) {
        bd(`INSERT INTO bienes (institucion_id, codigo_identificacion, lote, descripcion, fecha_ingreso, valor, estado, qr_token, created_by)
            VALUES (${d.instituciones.A}, '${placa}-${String(desde + i).padStart(3, '0')}', '${placa}', 'PW-TEST SILLA UNIVERSITARIA', '2024-01-15', 1000, 'activo', UUID(), ${d.usuarios.superusuario.id})`);
    }
}

/** Registra la cartera en /cartera/enviar como el rector (formulario real) y devuelve su id. */
async function registrarCartera(page, archivo, fecha = '2024-04-10') {
    const d = datos();
    const correo = `alcaldia-${Date.now()}-${++contador}@example.com`;
    await page.goto('cartera/enviar');
    const token = await page.locator('input[name="_csrf"]').first().inputValue();
    const respuesta = await page.request.post('cartera/enviar', {
        multipart: {
            _csrf: token,
            funcionario_id: String(d.usuarios.rector.id),
            correo_solicitante: d.usuarios.rector.email,
            nombre_remitente: 'Funcionaria de Bienes de la Alcaldía',
            correo_remitente: correo,
            fecha_envio: fecha,
            archivo: { name: 'cartera.xlsx', mimeType: 'application/vnd.openxmlformats-officedocument.spreadsheetml.sheet', buffer: fs.readFileSync(archivo) },
        },
        maxRedirects: 0,
    });
    expect(respuesta.status()).toBe(302);
    return { id: Number(bd(`SELECT id FROM cartera_envios WHERE correo_remitente = '${correo}'`)), correo };
}

test('muestra solo los bienes del lote elegido que siguen en la cartera y los descarga en Excel', async ({ page }) => {
    const s = `PWVC${Date.now()}`;
    // Placa con 10 sillas: 4 se reintegraron en el lote (deben quedar 6) y la cartera dice 8 → siguen 2.
    crearUnidadesActivas(`${s}P`, 1, 6);
    const lote = crearLote({ bienes: [
        ...[7, 8, 9, 10].map((n) => ({ codigo: `${s}P-${String(n).padStart(3, '0')}`, lote: `${s}P`, descripcion: 'PW-TEST SILLA UNIVERSITARIA' })),
        { codigo: `${s}S`, descripcion: 'PW-TEST COMPUTADOR QUE SIGUE' },
        { codigo: `${s}D`, descripcion: 'PW-TEST TABLERO DESCARGADO' },
    ] });
    // Otro lote con un bien de placa propia que también sigue: no debe aparecer.
    crearLote({ bienes: [{ codigo: `${s}O`, descripcion: 'PW-TEST DE OTRO LOTE' }] });

    const archivo = crearCartera([
        ['ALCALDÍA — CARTERA DE BIENES DEL RECTOR, POR PLACA Y CÓDIGO DE INVENTARIO'],
        [],
        ['Descripción', 'Placa', 'Cantidad', 'Valor'],
        ['10 SILLAS UNIVERSITARIAS', `${s}P`, 8, 10000],
        ['COMPUTADOR', `${s}S`, 1, 2000000],
        ['DE OTRO LOTE', `${s}O`, 1, 5000],
    ]);
    const { correo } = await registrarCartera(page, archivo);

    // Desde la lista de carteras: «Verificar un lote».
    await page.goto('cartera/enviar');
    await page.locator('tbody tr', { hasText: correo }).getByRole('link', { name: 'Verificar un lote' }).click();
    await expect(page.getByRole('heading', { name: 'Verificar un lote en la cartera' })).toBeVisible();
    await expect(page.getByText('Cartera recibida de la Alcaldía el 2024-04-10')).toBeVisible();

    // El selector trae solo lotes entregados.
    const selector = page.locator('select[name="lote"]');
    await expect(selector.locator(`option[value="${lote}"]`)).toContainText(`Lote #${lote}`);
    await expect(selector.locator(`option[value="${lote}"]`)).toContainText('entregado el 2024-03-01');
    const pendiente = crearLote({ estado: 'pendiente', bienes: [{ codigo: `${s}N` }] });
    await page.reload();
    await expect(selector.locator(`option[value="${pendiente}"]`)).toHaveCount(0);

    await selector.selectOption(String(lote));
    await page.getByRole('button', { name: 'Verificar', exact: true }).click();
    await expect(page).toHaveURL(new RegExp(`verificacion\\?lote=${lote}`));

    const aviso = page.locator('.alert-warning');
    await expect(aviso).toContainText(`3 bienes del lote #${lote} siguen en la cartera de la Alcaldía`);
    await expect(aviso).toContainText('3 de 6 bienes');
    await expect(aviso).toContainText('Descarga el Excel y envíalo a la Alcaldía');
    await expect(page.getByText(/Se leyeron 3 placas de la cartera \(placa de la columna B «Placa»; la cantidad, de la columna C «Cantidad»\)/)).toBeVisible();

    const filas = page.locator('[data-navegable] tbody tr');
    await expect(filas).toHaveCount(2);
    const sillas = filas.filter({ hasText: `${s}P` });
    await expect(sillas.locator('td').nth(2)).toContainText('2');
    await expect(sillas).toContainText('La cartera dice 8; deberían quedar 6.');
    await expect(sillas).toContainText('4 unidades de esta placa en el lote');
    await expect(filas.filter({ hasText: `${s}S` })).toContainText('La placa sigue en la cartera.');
    await expect(page.locator('[data-navegable]')).not.toContainText(`${s}D`);
    await expect(page.locator('[data-navegable]')).not.toContainText(`${s}O`);

    // El Excel trae la misma lista.
    const [descarga] = await Promise.all([
        page.waitForEvent('download'),
        aviso.getByRole('link', { name: 'Descargar en Excel' }).click(),
    ]);
    expect(descarga.suggestedFilename()).toBe(`siguen-en-cartera-lote-${lote}.xlsx`);
    const ruta = path.join(os.tmpdir(), `descarga-${Date.now()}.xlsx`);
    await descarga.saveAs(ruta);
    const contenido = execFileSync(PHP_BIN, ['-r', `require 'vendor/autoload.php';
        $h = PhpOffice\\PhpSpreadsheet\\IOFactory::load($argv[1])->getActiveSheet();
        echo json_encode([$h->getCell('A4')->getValue(), $h->getCell('A7')->getValue(), $h->getCell('C7')->getValue(), $h->getCell('A8')->getValue(), $h->getCell('A9')->getValue()]);`, ruta], { encoding: 'utf8' });
    const [resumen, placa1, siguen1, placa2, vacia] = JSON.parse(contenido);
    expect(resumen).toContain('3 de 6 bienes del lote siguen en ella');
    expect([placa1, placa2].sort()).toEqual([`${s}P`, `${s}S`].sort());
    expect([2, 1]).toContain(siguen1);
    expect(vacia).toBeNull();

    // Solo lee: nada cambió en los bienes ni en el lote.
    expect(bd(`SELECT COUNT(*) FROM bienes WHERE codigo_identificacion LIKE '${s}%' AND estado = 'reintegrado'`)).toBe('8');
    expect(bd(`SELECT estado FROM lotes_reintegro WHERE id = ${lote}`)).toBe('reintegrado');
});

test('todos descargados, lote entregado después de la cartera y columna elegida a mano', async ({ page }) => {
    const s = `PWVD${Date.now()}`;
    const descargado = crearLote({ bienes: [{ codigo: `${s}A` }, { codigo: `${s}B` }] });
    const tardio = crearLote({ entregado: '2024-05-01', bienes: [{ codigo: `${s}T` }] });

    // Títulos que MIA no reconoce: se elige la columna de la placa.
    const archivo = crearCartera([
        ['Elemento', 'Numero', 'Unidades'],
        ['COMPUTADOR', `${s}T`, 1],
        ['OTRO', '999999', 1],
    ]);
    const { id } = await registrarCartera(page, archivo, '2024-04-10');

    await page.goto(`cartera/${id}/verificacion?lote=${descargado}`);
    await expect(page.locator('.alert-warning')).toContainText('No se encontró la columna de la placa');
    await expect(page.locator('.alert-warning')).toContainText('Elige abajo en qué columna está la placa');
    await page.locator('select[name="columna"]').selectOption('B');
    await page.getByRole('button', { name: 'Verificar con estas columnas' }).click();
    await expect(page).toHaveURL(/columna=B/);
    await expect(page.locator('.alert-success')).toContainText('La Alcaldía descargó todos los bienes de este lote.');
    await expect(page.locator('.alert-success')).toContainText(`Ninguno de los 2 bienes del lote #${descargado}`);
    await expect(page.locator('[data-navegable]')).toHaveCount(0);
    await expect(page.getByRole('link', { name: 'Descargar en Excel' })).toHaveCount(0);

    // Al cambiar de lote se conserva la columna elegida. El lote entregado después de la
    // fecha de la cartera no se calcula: aviso guiado.
    await page.locator('select[name="lote"]').selectOption(String(tardio));
    await page.getByRole('button', { name: 'Verificar', exact: true }).click();
    await expect(page).toHaveURL(/columna=B/);
    const aviso = page.locator('.alert-warning');
    await expect(aviso).toContainText('Esta cartera es anterior a la entrega del lote');
    await expect(aviso).toContainText(`se entregó el 2024-05-01 y esta cartera se recibió el 2024-04-10`);
    await expect(aviso.getByRole('link', { name: 'Registrar otra cartera' })).toBeVisible();
    await expect(page.locator('select[name="lote"] option', { hasText: `Lote #${tardio}` })).toContainText('(después de esta cartera)');

    // El Excel sin nada que descargar vuelve a la pantalla con el aviso.
    await page.goto(`cartera/${id}/verificacion.xlsx?lote=${tardio}&columna=B`);
    await expect(page).toHaveURL(new RegExp(`cartera/${id}/verificacion\\?lote=${tardio}`));
    await expect(page.locator('.alert-warning')).toContainText('Esta cartera es anterior a la entrega del lote');
});

test('un lote de otra institución o inexistente no se verifica', async ({ page }) => {
    const archivo = crearCartera([['Placa'], ['123']]);
    const { id } = await registrarCartera(page, archivo);

    await page.goto(`cartera/${id}/verificacion?lote=999999`);
    await expect(page.locator('.alert-danger')).toContainText('No se verificó el lote');
    await expect(page.locator('.alert-danger')).toContainText('Elige uno de la lista');
});

test('el docente no puede verificar ni descargar', async ({ browser, page }) => {
    const archivo = crearCartera([['Placa'], ['123']]);
    const { id } = await registrarCartera(page, archivo);

    const contexto = await comoRol(browser, 'docente');
    const docente = await contexto.newPage();
    for (const url of [`cartera/${id}/verificacion`, `cartera/${id}/verificacion.xlsx`]) {
        const respuesta = await docente.goto(url);
        expect(respuesta.status(), url).toBe(403);
    }
    await contexto.close();
});

test('en el celular la lista se ve como tarjetas', async ({ page }) => {
    const s = `PWVM${Date.now()}`;
    const lote = crearLote({ bienes: [{ codigo: `${s}X`, descripcion: 'PW-TEST VIDEOBEAM' }] });
    const archivo = crearCartera([['PLACA', 'DESCRIPCION'], [`${s}X`, 'VIDEOBEAM']]);
    const { id } = await registrarCartera(page, archivo);

    await page.setViewportSize({ width: 390, height: 844 });
    await page.goto(`cartera/${id}/verificacion?lote=${lote}`);
    await expect(page.locator('.alert-warning')).toContainText(`1 bien del lote #${lote} sigue en la cartera de la Alcaldía`);
    const celda = page.locator('[data-navegable] td[data-label="Placa"]');
    await expect(celda).toContainText(`${s}X`);
    const ancho = await page.evaluate(() => document.documentElement.scrollWidth - document.documentElement.clientWidth);
    expect(ancho).toBeLessThanOrEqual(0);
});

test('accesible en modo claro y oscuro, con el resultado y con el selector de columnas', async ({ page }) => {
    const s = `PWVA${Date.now()}`;
    const lote = crearLote({ bienes: [{ codigo: `${s}X` }] });
    const reconocida = await registrarCartera(page, crearCartera([['PLACA', 'CANTIDAD'], [`${s}X`, 1]]));
    const sinTitulos = await registrarCartera(page, crearCartera([['Elemento', 'Numero'], ['X', `${s}X`]]));

    for (const tema of ['light', 'dark']) {
        await page.addInitScript((t) => { try { localStorage.setItem('sigebi-theme', t); } catch (e) { /* sin almacenamiento */ } }, tema);
        for (const url of [`cartera/${reconocida.id}/verificacion?lote=${lote}`, `cartera/${sinTitulos.id}/verificacion?lote=${lote}`, `cartera/${reconocida.id}/verificacion`]) {
            await page.goto(url);
            const { violations } = await new AxeBuilder({ page }).withTags(['wcag2a', 'wcag2aa']).analyze();
            const graves = violations
                .filter((v) => v.impact === 'serious' || v.impact === 'critical')
                .map((v) => `${v.id} (${v.nodes.length}): ${v.nodes.slice(0, 2).map((n) => n.target.join(' ')).join(' | ')}`);
            expect(graves, `${tema} ${url}: ${graves.join(' / ')}`).toEqual([]);
        }
    }
});
