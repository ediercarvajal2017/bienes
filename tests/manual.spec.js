import { test, expect } from '@playwright/test';

/**
 * /manual (la "Guía rápida" en pantalla) nunca tuvo un test propio. El usuario de
 * pruebas es superusuario, que no tiene guía propia (solo docente/rector/secretario
 * la tienen) -- cubre justamente esa rama de repliegue a la guía de Docente.
 */
test.use({ storageState: 'playwright/.auth/superusuario.json' });

test('la guía rápida del superusuario es propia (ya no la del docente)', async ({ page }) => {
    await page.goto('manual');

    await expect(page.getByRole('heading', { name: /Guía rápida: rol Superusuario/ })).toBeVisible();
    await expect(page.getByText('Estás viendo la guía del rol Docente')).toHaveCount(0);
    await expect(page.getByText('Política de verificación en dos pasos')).toHaveCount(0);
    await expect(page.getByText('Tu cuenta: contraseña y verificación en dos pasos')).toBeVisible();
    // Contenido real de _superusuario.php, no una página en blanco.
    await expect(page.locator('main, #contenidoPrincipal').first()).not.toBeEmpty();
});
