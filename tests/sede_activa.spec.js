import { test, expect } from '@playwright/test';

/**
 * El selector de sede activa (para un rector con más de una sede en su institución)
 * exige explícitamente rol "rector" -- el usuario de pruebas es superusuario, así que
 * no puede probarse el cambio de sede en sí (necesitaría una segunda cuenta rector con
 * varias sedes, igual que la limitación ya documentada en bajas.spec.js/
 * verificaciones.spec.js para separación de roles). Esta prueba sí cubre que el candado
 * de rol se mantenga: un rol sin permiso debe recibir 403, no un error de servidor.
 */
test.use({ storageState: 'playwright/.auth/user.json' });

test('cambiar de sede activa exige rol rector, no solo sesión iniciada', async ({ page }) => {
    await page.goto('dashboard');
    const token = await page.locator('input[name="_csrf"]').first().getAttribute('value');

    const respuesta = await page.request.post('sede-activa', {
        form: { _csrf: token, institucion_id: '1', volver: '/dashboard' },
        maxRedirects: 0,
    });

    expect(respuesta.status()).toBe(403);
});
