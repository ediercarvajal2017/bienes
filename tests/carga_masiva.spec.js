import { test, expect } from '@playwright/test';
import path from 'node:path';
import { fileURLToPath } from 'node:url';

const __dirname = path.dirname(fileURLToPath(import.meta.url));
const fixture = path.join(__dirname, 'fixtures', 'carga_masiva_bienes.xlsx');

/**
 * Regresión directa del bug corregido en esta misma sesión: una carga con filas
 * inválidas ya no debe decir "aplicada correctamente". El fixture (ver
 * generar_fixture_carga_masiva.php, borrado tras generarlo) trae una fila válida y una
 * inválida a propósito (sin descripción).
 *
 * OJO: esta prueba escribe en la base de datos real de la institución de la cuenta de
 * prueba (crea/actualiza el bien PW-TEST-BIEN-001) — no hay una BD de pruebas aislada
 * en este starter. Pensada para correr contra el entorno de desarrollo, no producción.
 */
test('carga masiva con filas inválidas no dice "aplicada correctamente"', async ({ page }) => {
    await page.goto('cargas-masivas');

    await page.setInputFiles('input[name="archivo"]', fixture);
    await page.getByRole('button', { name: 'Analizar archivo' }).click();

    await expect(page).toHaveURL(/\/cargas-masivas\/\d+/);
    await expect(page.getByText('con error')).toBeVisible();

    page.once('dialog', (dialog) => dialog.accept());
    await page.getByRole('button', { name: 'Confirmar importación' }).click();

    const alerta = page.locator('.alert-danger');
    await expect(alerta).toBeVisible();
    await expect(alerta).toContainText('aplicada parcialmente');
});
