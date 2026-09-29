import { test, expect } from '@playwright/test';
import { datos } from './helpers/datos.js';

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
        await expect(alerta).toContainText('Correo o contraseña incorrectos');

        await alerta.getByRole('button', { name: 'Cerrar mensaje' }).click();
        await expect(alerta).toBeHidden();
    });

    test('con credenciales válidas entra al panel principal', async ({ page }) => {
        const cuenta = datos().usuarios.superusuario;
        test.skip(!cuenta.email, 'Sin credenciales (modo remoto sin TEST_USER_EMAIL)');

        await page.goto('login');
        await page.locator('input[name="email"]').fill(cuenta.email);
        await page.locator('input[name="password"]').fill(cuenta.clave);
        await page.getByRole('button', { name: 'Ingresar' }).click();

        await expect(page).toHaveURL(/\/dashboard/);
    });
});

test('la base guarda la hora de Colombia (último ingreso), aunque su servidor esté en otra zona', async ({ browser }) => {
    const { datos, bd, sinSesion } = await import('./helpers/datos.js');
    const cuenta = datos().usuarios.secretario;
    test.skip(!cuenta?.email, 'Necesita la base de pruebas local');
    const contexto = await sinSesion(browser);
    const pagina = await contexto.newPage();
    await pagina.goto('login');
    await pagina.locator('input[name="email"]').fill(cuenta.email);
    await pagina.locator('input[name="password"]').fill(cuenta.clave);
    await pagina.getByRole('button', { name: 'Ingresar' }).click();
    await expect(pagina).toHaveURL(/\/dashboard/);

    // ultimo_login lo escribe la base con NOW(): debe ser la hora actual de Colombia.
    const guardado = bd(`SELECT ultimo_login FROM usuarios WHERE id = ${cuenta.id}`);
    const ahoraColombia = new Date().toLocaleString('sv-SE', { timeZone: 'America/Bogota' });
    const diferencia = Math.abs(new Date(guardado.replace(' ', 'T')) - new Date(ahoraColombia.replace(' ', 'T')));
    expect(diferencia, `guardado ${guardado}, hora de Colombia ${ahoraColombia}`).toBeLessThan(5 * 60 * 1000);
    await contexto.close();
});
