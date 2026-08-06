import { test, expect } from '@playwright/test';
import path from 'node:path';
import { fileURLToPath } from 'node:url';

const __dirname = path.dirname(fileURLToPath(import.meta.url));
const fixture = path.join(__dirname, 'fixtures', 'espacios_carga_masiva.xlsx');

/**
 * Mismo criterio que carga_masiva.spec.js (bienes): el fixture trae una fila válida
 * (responsable = documento real del usuario de pruebas) y una inválida a propósito
 * (documento que no existe), para confirmar que el mensaje final avisa del error en
 * vez de decir "aplicada correctamente".
 */
test('carga masiva de espacios con filas inválidas no dice "aplicada correctamente"', async ({ page }) => {
    await page.goto('espacios/carga-masiva');

    await page.setInputFiles('input[name="archivo"]', fixture);
    await page.getByRole('button', { name: 'Analizar archivo' }).click();

    await expect(page).toHaveURL(/\/espacios\/carga-masiva\/\d+/);

    page.once('dialog', (dialog) => dialog.accept());
    await page.getByRole('button', { name: 'Confirmar importación' }).click();

    const alerta = page.locator('.alert-danger');
    await expect(alerta).toBeVisible();
    await expect(alerta).toContainText('aplicada parcialmente');
});
