import { test, expect } from '@playwright/test';
import { datos, comoRol, bd } from './helpers/datos.js';
import { seleccionarTomSelect } from './helpers/tomSelect.js';

/**
 * Formulario único de la ficha del bien: los datos y la acción elegida en "¿Qué desea
 * hacer con este bien?" se guardan con un solo botón, en una sola transacción, y al
 * terminar se vuelve al listado con la misma búsqueda y página.
 */
test.beforeEach(() => test.skip(datos().remoto === true, 'Necesita la base de pruebas local'));
test.use({ storageState: 'playwright/.auth/rector.json' });

/** Crea directamente en la base un bien y dos espacios propios de esta prueba. */
function prepararBien(sufijo, { sinCartera = false } = {}) {
    const d = datos();
    const inst = d.instituciones.A;
    const categoria = sinCartera ? 'Sin cartera' : 'Muebles';
    const codigo = sinCartera ? String(9000000000 + (sufijo % 999999999)).padStart(10, '0') : `PW-ACC-${sufijo}`;
    bd(`INSERT INTO espacios (institucion_id, codigo, nombre, activo) VALUES
        (${inst}, 'PWA-${sufijo}', 'PW Espacio A ${sufijo}', 1), (${inst}, 'PWB-${sufijo}', 'PW Espacio B ${sufijo}', 1)`);
    const [espA, espB] = bd(`SELECT id FROM espacios WHERE codigo IN ('PWA-${sufijo}','PWB-${sufijo}') ORDER BY codigo`).split('\n').map(Number);
    bd(`INSERT INTO bienes (institucion_id, codigo_identificacion, descripcion, categoria_id, fecha_ingreso, valor, estado, qr_token)
        SELECT ${inst}, '${codigo}', 'PW bien acciones ${sufijo}', id, CURDATE(), 1000, 'activo', UUID()
          FROM categorias_bienes WHERE institucion_id = ${inst} AND nombre = '${categoria}' LIMIT 1`);
    const bienId = Number(bd(`SELECT id FROM bienes WHERE codigo_identificacion = '${codigo}' AND institucion_id = ${inst}`));
    bd(`INSERT INTO asignaciones (bien_id, espacio_id, fecha_asignacion, activa) VALUES (${bienId}, ${espA}, CURDATE(), 1)`);
    return { bienId, codigo, espA, espB };
}

test('editar datos y trasladar con un solo guardado, y volver al listado como estaba', async ({ page }) => {
    const sufijo = Date.now();
    const { bienId, codigo, espB } = prepararBien(sufijo);

    await page.goto(`bienes?q=${encodeURIComponent(codigo)}&pagina=1`);
    await page.locator('tr', { hasText: codigo }).getByRole('link', { name: 'Editar' }).click();
    await expect(page).toHaveURL(new RegExp(`/bienes/${bienId}/editar`));

    await page.locator('input[name="descripcion"]').fill(`PW bien editado ${sufijo}`);
    await page.selectOption('#accionBien', 'trasladar');
    await expect(page.getByRole('button', { name: 'Guardar y trasladar' })).toBeVisible();
    await seleccionarTomSelect(page, 'accionEspacio', `PW Espacio B ${sufijo}`);
    await page.getByRole('button', { name: 'Guardar y trasladar' }).click();

    // Se queda en la ficha, que ya muestra la nueva ubicación.
    await expect(page).toHaveURL(new RegExp(`/bienes/${bienId}/editar$`));
    await expect(page.locator('.alert-success')).toContainText('Bien actualizado. Trasladado a PW Espacio B');
    await expect(page.locator('#ubicacionActual')).toContainText(`PW Espacio B ${sufijo}`);

    // "Volver" regresa al listado con la misma búsqueda y página, resaltando el bien.
    await page.getByRole('link', { name: 'Volver' }).click();
    await expect(page).toHaveURL(new RegExp(`/bienes\\?q=${codigo}&pagina=1&editado=${bienId}`));
    await expect(page.locator('#bienEditado')).toContainText(`PW bien editado ${sufijo}`);

    // En la base: el dato, un traslado, una sola asignación activa (la nueva) y la auditoría.
    expect(bd(`SELECT descripcion FROM bienes WHERE id = ${bienId}`)).toBe(`PW bien editado ${sufijo}`);
    expect(bd(`SELECT COUNT(*) FROM movimientos WHERE bien_id = ${bienId} AND tipo = 'traslado' AND espacio_destino_id = ${espB}`)).toBe('1');
    expect(bd(`SELECT GROUP_CONCAT(espacio_id) FROM asignaciones WHERE bien_id = ${bienId} AND activa = 1`)).toBe(String(espB));
    expect(bd(`SELECT GROUP_CONCAT(accion ORDER BY id) FROM auditoria WHERE entidad = 'bien' AND entidad_id = ${bienId}`)).toBe('trasladar,editar');
});

test('solo guardar los datos no crea movimientos y se queda en la ficha', async ({ page }) => {
    const sufijo = Date.now();
    const { bienId, codigo } = prepararBien(sufijo);

    await page.goto(`bienes?q=${encodeURIComponent(codigo)}`);
    await page.goto(`bienes/${bienId}/editar`);
    await expect(page.getByRole('link', { name: 'Volver' })).toHaveAttribute('href', new RegExp(`/bienes\\?q=${codigo}&editado=${bienId}$`));

    await page.locator('input[name="descripcion"]').fill(`PW solo datos ${sufijo}`);
    await page.getByRole('button', { name: 'Guardar cambios' }).click();
    await expect(page).toHaveURL(new RegExp(`/bienes/${bienId}/editar$`));
    expect(bd(`SELECT COUNT(*) FROM movimientos WHERE bien_id = ${bienId}`)).toBe('0');
});

test('si la acción no se puede hacer, tampoco se guardan los datos', async ({ page }) => {
    const sufijo = Date.now();
    const { bienId } = prepararBien(sufijo);

    await page.goto(`bienes/${bienId}/editar`);
    await page.locator('input[name="descripcion"]').fill(`PW no debe guardarse ${sufijo}`);
    await page.selectOption('#accionBien', 'reintegrar');
    // Se salta la validación del navegador para que el servidor reciba el destino vacío.
    await page.evaluate(() => { document.getElementById('datosBien').noValidate = true; });
    page.once('dialog', (dialogo) => dialogo.accept());
    await page.getByRole('button', { name: 'Guardar y reintegrar' }).click();

    await expect(page).toHaveURL(new RegExp(`/bienes/${bienId}/editar`));
    await expect(page.locator('.alert-danger')).toContainText('destino');
    // El formulario conserva lo escrito y la acción elegida; la base no cambió.
    await expect(page.locator('input[name="descripcion"]')).toHaveValue(`PW no debe guardarse ${sufijo}`);
    await expect(page.locator('#accionBien')).toHaveValue('reintegrar');
    expect(bd(`SELECT descripcion FROM bienes WHERE id = ${bienId}`)).toBe(`PW bien acciones ${sufijo}`);
    expect(bd(`SELECT estado FROM bienes WHERE id = ${bienId}`)).toBe('activo');
});

test('el menú ofrece solo lo que corresponde al bien', async ({ page }) => {
    const sufijo = Date.now();
    const normal = prepararBien(sufijo);
    const sinCartera = prepararBien(sufijo + 1, { sinCartera: true });

    await page.goto(`bienes/${normal.bienId}/editar`);
    const opcionesNormal = await page.locator('#accionBien option').evaluateAll((o) => o.map((x) => x.value));
    expect(opcionesNormal).toEqual(expect.arrayContaining(['ninguna', 'trasladar', 'reintegrar']));
    expect(opcionesNormal).not.toContain('reportar_baja');
    expect(opcionesNormal).not.toContain('asignar');

    await page.goto(`bienes/${sinCartera.bienId}/editar`);
    const opcionesSinCartera = await page.locator('#accionBien option').evaluateAll((o) => o.map((x) => x.value));
    expect(opcionesSinCartera).toContain('reportar_baja');
    expect(opcionesSinCartera).not.toContain('reintegrar');
});

test('reportar baja desde la ficha queda pendiente de aprobación', async ({ page }) => {
    const sufijo = Date.now();
    const { bienId } = prepararBien(sufijo, { sinCartera: true });

    await page.goto(`bienes/${bienId}/editar`);
    await page.selectOption('#accionBien', 'reportar_baja');
    await page.locator('#accionEstadoReportado').fill('Dañado');
    await page.locator('#accionDescripcionBaja').fill('Pata partida');
    page.once('dialog', (dialogo) => dialogo.accept());
    await page.getByRole('button', { name: 'Guardar y reportar baja' }).click();

    await expect(page.locator('.alert-success')).toContainText('pendiente de aprobación');
    expect(bd(`SELECT estado FROM bajas_bienes WHERE bien_id = ${bienId}`)).toBe('pendiente');
    expect(bd(`SELECT estado FROM bienes WHERE id = ${bienId}`)).toBe('activo');
});

test('el docente no ve el menú de acciones', async ({ browser }) => {
    const docente = await (await comoRol(browser, 'docente')).newPage();
    await docente.goto(`bienes/${datos().bienes.A.silla}/editar`);
    await expect(docente.locator('#accionBien')).toHaveCount(0);
    await docente.context().close();
});

test('registrar un bien con su ubicación en un solo paso abre su ficha', async ({ page }) => {
    const sufijo = Date.now();
    const d = datos();
    bd(`INSERT INTO espacios (institucion_id, codigo, nombre, activo) VALUES (${d.instituciones.A}, 'PWN-${sufijo}', 'PW Espacio nuevo ${sufijo}', 1)`);
    const espacio = Number(bd(`SELECT id FROM espacios WHERE codigo = 'PWN-${sufijo}'`));

    await page.goto('bienes/crear');
    await page.locator('input[name="codigo_identificacion"]').fill(`PW-NUEVO-${sufijo}`);
    await page.locator('input[name="descripcion"]').fill(`PW bien nuevo ${sufijo}`);
    await seleccionarTomSelect(page, 'campoEspacioNuevo', `PW Espacio nuevo ${sufijo}`);
    await page.getByRole('button', { name: 'Registrar bien' }).click();

    await expect(page).toHaveURL(/\/bienes\/\d+\/editar$/);
    await expect(page.locator('.alert-success')).toContainText(`Bien registrado y asignado a PW Espacio nuevo ${sufijo}`);
    await expect(page.locator('#ubicacionActual')).toContainText(`PW Espacio nuevo ${sufijo}`);
    const bienId = Number(bd(`SELECT id FROM bienes WHERE codigo_identificacion = 'PW-NUEVO-${sufijo}'`));
    expect(bd(`SELECT espacio_id FROM asignaciones WHERE bien_id = ${bienId} AND activa = 1`)).toBe(String(espacio));
    expect(bd(`SELECT GROUP_CONCAT(accion ORDER BY id) FROM auditoria WHERE entidad = 'bien' AND entidad_id = ${bienId}`)).toBe('crear,asignar');
});
