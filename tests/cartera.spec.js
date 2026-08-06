import { test, expect } from '@playwright/test';
import path from 'node:path';
import { fileURLToPath } from 'node:url';
import { seleccionarPrimeraOpcionTomSelect } from './helpers/tomSelect.js';

const __dirname = path.dirname(fileURLToPath(import.meta.url));
const excelPrueba = path.join(__dirname, 'fixtures', 'excel_prueba.xlsx');

test('registrar, editar y eliminar un envío de cartera', async ({ page }) => {
    const correo = `pw-test-${Date.now()}@example.com`;
    const correoEditado = `pw-test-${Date.now()}-ed@example.com`;

    await page.goto('cartera/enviar');

    await Promise.all([
        page.waitForURL(/institucion=\d+/),
        seleccionarPrimeraOpcionTomSelect(page, 'selectorInstitucion'),
    ]);

    // "Funcionario que realizó el envío" ya viene prellenado con el nombre del usuario
    // que inició sesión — no hace falta tocarlo.
    await page.locator('input[name="correo_remitente"]').fill(correo);
    await page.setInputFiles('input[name="archivo"]', excelPrueba);
    await page.getByRole('button', { name: 'Guardar registro' }).click();

    await expect(page.locator('.alert-success')).toBeVisible();

    await page.goto('cartera/enviados');
    let fila = page.locator('tr', { hasText: correo });
    await expect(fila).toBeVisible();

    await fila.getByRole('link', { name: 'Editar' }).click();
    await expect(page.locator('input[name="correo_remitente"]')).toHaveValue(correo);

    await page.locator('input[name="correo_remitente"]').fill(correoEditado);
    await page.getByRole('button', { name: 'Guardar cambios' }).click();

    await expect(page).toHaveURL(/\/cartera\/enviados$/);
    fila = page.locator('tr', { hasText: correoEditado });
    await expect(fila).toBeVisible();

    await fila.getByRole('link', { name: 'Editar' }).click();
    page.once('dialog', (dialog) => dialog.accept('ELIMINAR'));
    await page.getByRole('button', { name: 'Eliminar registro' }).click();

    await expect(page).toHaveURL(/\/cartera\/enviados$/);
    await expect(page.locator('tr', { hasText: correoEditado })).toHaveCount(0);
});
