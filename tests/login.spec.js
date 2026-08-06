import { test, expect } from '@playwright/test';

test.describe('Login', () => {
    test('la página carga con los campos esperados', async ({ page }) => {
        await page.goto('login');

        await expect(page).toHaveTitle(/Iniciar sesión/);
        await expect(page.locator('input[name="email"]')).toBeVisible();
        await expect(page.locator('input[name="password"]')).toBeVisible();
        await expect(page.getByRole('checkbox', { name: 'Recordarme en este dispositivo' })).toBeVisible();
    });

    test('mostrar/ocultar contraseña cambia el tipo del campo', async ({ page }) => {
        await page.goto('login');
        const password = page.locator('input[name="password"]');

        await expect(password).toHaveAttribute('type', 'password');

        await page.getByRole('button', { name: 'Mostrar contraseña' }).click();
        await expect(password).toHaveAttribute('type', 'text');

        await page.getByRole('button', { name: 'Ocultar contraseña' }).click();
        await expect(password).toHaveAttribute('type', 'password');
    });

    test('credenciales inválidas muestran una alerta que se puede cerrar', async ({ page }) => {
        await page.goto('login');

        await page.locator('input[name="email"]').fill('no-existe@ejemplo.com');
        await page.locator('input[name="password"]').fill('contraseña-incorrecta');
        await page.getByRole('button', { name: 'Ingresar' }).click();

        const alerta = page.locator('.alert-danger');
        await expect(alerta).toBeVisible();
        await expect(alerta).toContainText('Credenciales inválidas');

        await alerta.getByRole('button', { name: 'Cerrar mensaje' }).click();
        await expect(alerta).toBeHidden();
    });

    test('con credenciales válidas entra al panel principal', async ({ page }) => {
        test.skip(!process.env.TEST_USER_EMAIL, 'Define TEST_USER_EMAIL/TEST_USER_PASSWORD en .env.test para esta prueba');

        await page.goto('login');
        await page.locator('input[name="email"]').fill(process.env.TEST_USER_EMAIL);
        await page.locator('input[name="password"]').fill(process.env.TEST_USER_PASSWORD);
        await page.getByRole('button', { name: 'Ingresar' }).click();

        await expect(page).toHaveURL(/\/dashboard/);
    });
});
