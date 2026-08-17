import { test, expect } from '@playwright/test';

/**
 * /manual (la "Guía rápida" en pantalla) nunca tuvo un test propio. El usuario de
 * pruebas es superusuario, que no tiene guía propia (solo docente/rector/secretario
 * la tienen) -- cubre justamente esa rama de repliegue a la guía de Docente.
 */
test.use({ storageState: 'playwright/.auth/user.json' });

test('la guía rápida carga con el aviso de repliegue para un rol sin guía propia', async ({ page }) => {
    await page.goto('manual');

    await expect(page.getByRole('heading', { name: /Guía rápida/ })).toBeVisible();
    await expect(page.getByText('Estás viendo la guía del rol Docente')).toBeVisible();
    // Contenido real de _docente.php, no una página en blanco.
    await expect(page.locator('main, #contenidoPrincipal').first()).not.toBeEmpty();
});
