import { test, expect } from '@playwright/test';
import path from 'node:path';
import { fileURLToPath } from 'node:url';
import { seleccionarPrimeraOpcionTomSelect } from './helpers/tomSelect.js';

const __dirname = path.dirname(fileURLToPath(import.meta.url));
const pdfPrueba = path.join(__dirname, 'fixtures', 'documento_prueba.pdf');

test('registrar, editar y eliminar un formato de reintegro', async ({ page }) => {
    const descripcion = `PW-TEST-FormatoReintegro-${Date.now()}`;
    const descripcionEditada = `${descripcion}-editado`;

    await page.goto('formatos-reintegro');

    await Promise.all([
        page.waitForURL(/institucion=\d+/),
        seleccionarPrimeraOpcionTomSelect(page, 'selectorInstitucion'),
    ]);

    await page.setInputFiles('input[name="archivo"]', pdfPrueba);
    await page.locator('input[name="descripcion"]').fill(descripcion);
    await page.getByRole('button', { name: 'Guardar registro' }).click();

    await expect(page.locator('.alert-success')).toBeVisible();

    await page.goto('formatos-reintegro/historial');
    let fila = page.locator('tr', { hasText: descripcion });
    await expect(fila).toBeVisible();

    await fila.getByRole('link', { name: 'Editar' }).click();
    await expect(page.locator('input[name="descripcion"]')).toHaveValue(descripcion);

    await page.locator('input[name="descripcion"]').fill(descripcionEditada);
    await page.getByRole('button', { name: 'Guardar cambios' }).click();

    await expect(page).toHaveURL(/\/formatos-reintegro\/historial$/);
    fila = page.locator('tr', { hasText: descripcionEditada });
    await expect(fila).toBeVisible();

    await fila.getByRole('link', { name: 'Editar' }).click();
    page.once('dialog', (dialog) => dialog.accept('ELIMINAR'));
    await page.getByRole('button', { name: 'Eliminar registro' }).click();

    await expect(page).toHaveURL(/\/formatos-reintegro\/historial$/);
    await expect(page.locator('tr', { hasText: descripcionEditada })).toHaveCount(0);
});
