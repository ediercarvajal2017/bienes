import { test, expect } from '@playwright/test';
import { seleccionarPrimeraOpcionTomSelect } from './helpers/tomSelect.js';

/**
 * Casos límite y regresiones puntuales encontradas en una auditoría de bugs
 * (2026-08-17): cada prueba de este archivo existe porque ya se rompió una vez.
 */

test.use({ storageState: 'playwright/.auth/user.json' });

test('bienes: un valor de más de 10 dígitos se rechaza en el campo correcto', async ({ page }) => {
    await page.goto('bienes/crear');
    await page.locator('input[name="codigo_identificacion"]').fill(`PW-TEST-VAL-${Date.now()}`);
    await page.locator('input[name="descripcion"]').fill('PW-TEST valor excesivo');
    const campoValor = page.locator('input[name="valor"]');
    // El atributo max="9999999999" del HTML ya bloquea esto en un navegador real; se
    // quita para probar la validación del servidor, que es la que de verdad protege
    // contra un envío directo (sin JS, o saltándose el formulario).
    await campoValor.evaluate((el) => el.removeAttribute('max'));
    await campoValor.fill('99999999999'); // 11 dígitos
    await page.getByRole('button', { name: 'Registrar bien' }).click();

    await expect(campoValor).toHaveClass(/is-invalid/);
    await expect(page.locator('.invalid-feedback', { hasText: 'no puede ser negativo' })).toBeVisible();
});

test('bienes: alta masiva idéntica también limita el valor a 10 dígitos', async ({ page }) => {
    await page.goto('bienes/alta-masiva');
    await page.locator('input[name="lote"]').fill(`PWTESTLOTEVAL${Date.now()}`);
    await page.locator('input[name="cantidad"]').fill('2');
    await page.locator('input[name="descripcion"]').fill('PW-TEST lote valor excesivo');
    const campoValor = page.locator('input[name="valor"]');
    await campoValor.evaluate((el) => el.removeAttribute('max'));
    await campoValor.fill('99999999999');
    await page.getByRole('button', { name: 'Crear bienes del lote' }).click();

    await expect(page.getByText('no puede ser negativo ni tener más de 10 dígitos', { exact: false })).toBeVisible();
});

test('bienes: registrar el mismo código dos veces casi al mismo tiempo no da un error 500', async ({ browser }) => {
    // Regresión de un bug real: entre la comprobación previa de "código ya existe" y el
    // INSERT, dos peticiones casi simultáneas podían chocar contra la restricción UNIQUE
    // de la base de datos sin que nadie la atrapara, mostrando un 500 crudo en vez del
    // mensaje de siempre.
    const codigo = `PW-TEST-RACE-${Date.now()}`;

    const ctx1 = await browser.newContext({ storageState: 'playwright/.auth/user.json' });
    const ctx2 = await browser.newContext({ storageState: 'playwright/.auth/user.json' });
    const page1 = await ctx1.newPage();
    const page2 = await ctx2.newPage();

    await page1.goto('bienes/crear');
    await page1.locator('input[name="codigo_identificacion"]').fill(codigo);
    await page1.locator('input[name="descripcion"]').fill('PW-TEST bien carrera 1');

    await page2.goto('bienes/crear');
    await page2.locator('input[name="codigo_identificacion"]').fill(codigo);
    await page2.locator('input[name="descripcion"]').fill('PW-TEST bien carrera 2');

    await Promise.all([
        page1.getByRole('button', { name: 'Registrar bien' }).click().then(() => page1.waitForLoadState()),
        page2.getByRole('button', { name: 'Registrar bien' }).click().then(() => page2.waitForLoadState()),
    ]);

    await expect(page1.locator('body')).not.toContainText('HTTP ERROR 500');
    await expect(page2.locator('body')).not.toContainText('HTTP ERROR 500');

    await ctx1.close();
    await ctx2.close();
});

test('bienes: el checkbox "Imprimir QR" de alta masiva agrega el lote a la Bodega de impresión', async ({ page }) => {
    await page.goto('bienes/alta-masiva');
    const lote = `PWTESTLOTEQR${Date.now()}`;
    await page.locator('input[name="lote"]').fill(lote);
    await page.locator('input[name="cantidad"]').fill('2');
    await page.locator('input[name="descripcion"]').fill('PW-TEST lote para bodega QR');
    await expect(page.locator('#imprimirQrLote')).toBeChecked();
    await page.getByRole('button', { name: 'Crear bienes del lote' }).click();
    await expect(page).toHaveURL(/\/bienes$/);

    await page.goto('bienes/qr-masivo');
    if (await page.locator('#selectorInstitucion').count() > 0) {
        await Promise.all([
            page.waitForURL(/institucion=\d+/),
            seleccionarPrimeraOpcionTomSelect(page, 'selectorInstitucion'),
        ]);
    }
    await page.getByRole('link', { name: /Bodega de impresión/ }).click();
    await expect(page.locator('tr', { hasText: lote + '-001' })).toBeVisible();
    await expect(page.locator('tr', { hasText: lote + '-002' })).toBeVisible();
});

test('bienes: una categoría marcada inválida resalta en rojo el widget de Tom Select', async ({ page }) => {
    // Regresión de un bug real: Tom Select oculta el <select> original y pinta su
    // propio widget al lado (.ts-wrapper) — poner "is-invalid" solo en el <select>
    // oculto no se veía. Simula el estado porque provocar el error real end-to-end
    // exige una categoría de otra institución, difícil de montar en esta suite.
    await page.goto('bienes/crear');
    await page.evaluate(() => document.getElementById('categoriaBien').classList.add('is-invalid'));
    await page.waitForTimeout(250); // transición CSS de border-color (150ms)

    const control = page.locator('#categoriaBien + .ts-wrapper .ts-control');
    const color = await control.evaluate((el) => getComputedStyle(el).borderColor);
    expect(color).toBe('rgb(214, 69, 69)'); // --sigebi-danger
});

test('cargas masivas: buscar por "pendiente" o "aplicada" no rompe (regresión de colación de MySQL)', async ({ page }) => {
    for (const termino of ['pendiente', 'aplicada', 'zzznoexiste123']) {
        const resp = await page.goto(`cargas-masivas?q=${termino}`);
        expect(resp.status()).toBe(200);
    }
});

test('buscador global: encuentra un bien por código y agrupa los resultados por módulo', async ({ page }) => {
    await page.goto('bienes/crear');
    await seleccionarPrimeraOpcionTomSelect(page, 'institucionBien');
    const codigo = `PWTESTGLOBAL${Date.now()}`;
    await page.locator('input[name="codigo_identificacion"]').fill(codigo);
    await page.locator('input[name="descripcion"]').fill('PW-TEST bien para buscador global');
    await page.getByRole('button', { name: 'Registrar bien' }).click();
    await expect(page).toHaveURL(/\/bienes$/);

    await Promise.all([
        page.waitForLoadState('networkidle'),
        seleccionarPrimeraOpcionTomSelect(page, 'filtroInstitucionSelect'),
    ]);

    await page.goto('buscar?q=' + codigo);
    await expect(page.getByText(/Bienes \(\d+\)/)).toBeVisible();
    await expect(page.getByText(codigo)).toBeVisible();
});

test('dashboard: "Primeros pasos" no rompe para una institución que ya tiene bienes', async ({ page }) => {
    const erroresPagina = [];
    page.on('pageerror', (e) => erroresPagina.push(e.message));
    await page.goto('dashboard');
    expect(erroresPagina).toEqual([]);

    const detalles = page.locator('details.card', { hasText: 'Primeros pasos' });
    if (await detalles.count() > 0) {
        // Ya hay bienes de sobra en esta base de pruebas: debe verse colapsada, no abierta.
        expect(await detalles.evaluate((el) => el.open)).toBe(false);
    }
});
