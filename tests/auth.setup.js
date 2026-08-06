import { test as setup, expect } from '@playwright/test';

const archivoSesion = 'playwright/.auth/user.json';

/**
 * Inicia sesión una sola vez y guarda las cookies para que carga_masiva.spec.js y
 * papelera.spec.js las reutilicen (ver dependencies: ['setup'] en playwright.config.js) —
 * evita loguearse de nuevo en cada prueba.
 *
 * papelera.spec.js necesita que esta cuenta sea SUPERUSUARIO (esas rutas están
 * protegidas por SuperusuarioMiddleware); carga_masiva.spec.js solo necesita el permiso
 * de cargas masivas. Ver .env.test.example.
 */
setup('iniciar sesión', async ({ page }) => {
    const email = process.env.TEST_USER_EMAIL;
    const password = process.env.TEST_USER_PASSWORD;

    if (!email || !password) {
        throw new Error(
            'Faltan TEST_USER_EMAIL / TEST_USER_PASSWORD. Copia .env.test.example a .env.test ' +
            'y completa las credenciales de una cuenta real antes de correr las pruebas autenticadas.'
        );
    }

    await page.goto('login');
    await page.locator('input[name="email"]').fill(email);
    await page.locator('input[name="password"]').fill(password);
    await page.getByRole('button', { name: 'Ingresar' }).click();

    await expect(page).toHaveURL(/\/dashboard/);

    await page.context().storageState({ path: archivoSesion });
});
