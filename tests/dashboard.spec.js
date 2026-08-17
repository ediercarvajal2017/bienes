import { test, expect } from '@playwright/test';

/**
 * Es la pantalla de aterrizaje de todo usuario tras iniciar sesión y no tenía ningún
 * test propio -- solo se verificaba que el login TERMINARA ahí (login.spec.js,
 * auth.setup.js), nunca su contenido. casos_limite.spec.js ya cubre que "Primeros
 * pasos" no rompa; esta prueba cubre el saludo, la cuadrícula de accesos y que sus
 * enlaces de verdad lleven a la pantalla correcta.
 */
test.use({ storageState: 'playwright/.auth/user.json' });

test('el panel principal carga con el saludo y accesos funcionales', async ({ page }) => {
    await page.goto('dashboard');

    await expect(page.getByRole('heading', { name: /^Hola, / })).toBeVisible();
    await expect(page.getByText(/^Rol: /)).toBeVisible();

    // El usuario de pruebas es superusuario -- ve absolutamente todos los accesos,
    // incluido "Cargos" (marcado soloSuperusuario en la vista). Acotado a
    // #contenidoPrincipal porque el sidebar también tiene un enlace "Bienes", y
    // exact:true porque un indicador pendiente ("106 bienes sin asignar") también
    // contiene la palabra.
    const contenido = page.locator('#contenidoPrincipal');
    const accesoBienes = contenido.getByText('Bienes', { exact: true });
    await expect(accesoBienes).toBeVisible();
    await expect(contenido.getByText('Cargos', { exact: true })).toBeVisible();
    await expect(contenido.getByText('Usuarios', { exact: true })).toBeVisible();

    await accesoBienes.click();
    await expect(page).toHaveURL(/\/bienes$/);

    // Si hay algún indicador pendiente (baja, bien sin asignar, hallazgo), su tarjeta
    // debe llevar a una pantalla real, no a un enlace roto.
    await page.goto('dashboard');
    const indicador = page.locator('a.card.border-warning, a.card.border-secondary, a.card.border-info').first();
    if (await indicador.count() > 0) {
        const errores = [];
        page.on('pageerror', (e) => errores.push(e.message));
        await indicador.click();
        await expect(page.locator('body')).not.toContainText('HTTP ERROR 500');
        expect(errores).toEqual([]);
    }
});
