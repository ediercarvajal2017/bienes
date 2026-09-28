import { test, expect } from '@playwright/test';
import { comoRol } from './helpers/datos.js';

/**
 * "Asignar bienes" y "Reintegrar bienes" viven en Bienes > Acciones masivas; ya no están
 * en el menú lateral (Operación diaria), donde se repetían.
 */
test.use({ storageState: 'playwright/.auth/rector.json' });

test('asignar y reintegrar en lote se abren desde Acciones masivas, no desde el menú lateral', async ({ page }) => {
    await page.goto('bienes');

    const menuLateral = page.locator('#sidebar');
    await expect(menuLateral.getByRole('link', { name: 'Asignar bienes' })).toHaveCount(0);
    await expect(menuLateral.getByRole('link', { name: 'Reintegrar bienes' })).toHaveCount(0);
    await expect(menuLateral.getByRole('link', { name: 'Lotes de reintegro' })).toHaveCount(1);

    await page.locator('#botonAccionesMasivas').click();
    await expect(page.getByRole('link', { name: 'Asignar bienes' })).toBeVisible();
    await page.getByRole('link', { name: 'Reintegrar bienes' }).click();
    await expect(page).toHaveURL(/\/reintegros$/);

    // En esa pantalla el menú lateral sigue indicando que se está dentro de Bienes.
    await expect(menuLateral.locator('a.nav-link[href$="/bienes"]')).toHaveAttribute('aria-current', 'page');
});

test('el docente no ve asignar ni reintegrar en Acciones masivas', async ({ browser }) => {
    const docente = await (await comoRol(browser, 'docente')).newPage();
    await docente.goto('bienes');
    await docente.locator('#botonAccionesMasivas').click();
    await expect(docente.getByRole('link', { name: 'Asignar bienes' })).toHaveCount(0);
    await expect(docente.getByRole('link', { name: 'Reintegrar bienes' })).toHaveCount(0);
    await docente.context().close();
});
