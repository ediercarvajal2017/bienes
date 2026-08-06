import { test, expect } from '@playwright/test';

test('la pantalla de reportes carga y descarga la cartera de bienes', async ({ page }) => {
    await page.goto('reportes');

    await expect(page.locator('h1')).toContainText('Reportes');

    const [descarga] = await Promise.all([
        page.waitForEvent('download'),
        page.getByRole('link', { name: '.xlsx' }).first().click(),
    ]);

    expect(descarga.suggestedFilename()).toMatch(/\.xlsx$/);
});
