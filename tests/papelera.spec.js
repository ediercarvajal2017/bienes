import { test, expect } from '@playwright/test';

/**
 * /papelera y /auditoria están protegidas por SuperusuarioMiddleware — la cuenta en
 * TEST_USER_EMAIL/TEST_USER_PASSWORD (ver .env.test) tiene que ser superusuario o estas
 * dos pruebas reciben un 403 en vez del contenido esperado.
 */
test('la papelera de reciclaje carga para un superusuario', async ({ page }) => {
    await page.goto('papelera');

    await expect(page.locator('h1')).toContainText('Papelera de reciclaje');
});

test('la auditoría carga con sus filtros', async ({ page }) => {
    await page.goto('auditoria');

    await expect(page.locator('h1')).toContainText('Auditoría');
    await expect(page.locator('select[name="entidad"]')).toBeVisible();
    await expect(page.locator('select[name="accion"]')).toBeVisible();
});
