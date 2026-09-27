import { test as setup, expect } from '@playwright/test';
import { datos } from './helpers/datos.js';

/**
 * Inicia sesión UNA vez por rol y guarda las cookies en playwright/.auth/<rol>.json
 * (superusuario, rector, secretario, docente y rector_b, de database/seeders/pruebas.php).
 * El proyecto "authenticated" usa la del superusuario; las pruebas por rol abren un
 * contexto con la sesión que necesitan (ver tests/helpers/datos.js → comoRol).
 */
// Los archivos de prueba se cargan ANTES de global-setup: los datos se leen dentro de cada prueba.
const ROLES = ['superusuario', 'rector', 'secretario', 'docente', 'rector_b'];

for (const rol of ROLES) {
    setup(`iniciar sesión como ${rol}`, async ({ page }) => {
        const cuenta = datos().usuarios[rol] || {};
        setup.skip(!cuenta.email || !cuenta.clave, 'Sin credenciales para este rol (modo remoto)');

        await page.goto('login');
        await page.locator('input[name="email"]').fill(cuenta.email);
        await page.locator('input[name="password"]').fill(cuenta.clave);
        await page.getByRole('button', { name: 'Ingresar' }).click();
        await expect(page).toHaveURL(/\/dashboard/);

        await page.context().storageState({ path: `playwright/.auth/${rol}.json` });
    });
}
