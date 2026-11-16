import { test, expect } from '@playwright/test';
import { datos } from './helpers/datos.js';

/**
 * "Buscar por foto" usa el mismo componente de foto que los formularios
 * (partials/campo_foto.php + assets/js/camara.js): además de elegir un archivo, se puede
 * tomar la foto con la cámara del equipo, y la búsqueda arranca sola al capturarla.
 * El navegador usa una cámara simulada (sin pedir permiso).
 */
test.beforeEach(() => test.skip(datos().remoto === true, 'Necesita la base de pruebas local'));
test.use({
    storageState: 'playwright/.auth/rector.json',
    permissions: ['camera'],
    launchOptions: { args: ['--use-fake-device-for-media-stream', '--use-fake-ui-for-media-stream'] },
});

test('en Buscar por foto se puede tomar la foto con la cámara y la búsqueda arranca', async ({ page }) => {
    await page.goto('bienes/buscar-por-foto');

    // En el celular el campo debe ofrecer cámara Y galería: sin "capture", que solo abre la cámara.
    const campo = page.locator('#inputFotoBusqueda');
    await expect(campo).toBeVisible();
    await expect(campo).not.toHaveAttribute('capture', /.*/);

    await page.getByRole('button', { name: 'Tomar foto' }).click();
    await page.waitForFunction(() => (document.querySelector('.campo-foto-video')?.videoWidth ?? 0) > 0);
    await page.getByRole('button', { name: 'Capturar' }).click();

    await expect(page.locator('.campo-foto-preview')).toBeVisible();
    expect(await campo.evaluate((input) => input.files.length)).toBe(1);
    // La foto capturada dispara la búsqueda igual que un archivo elegido.
    await expect(page.locator('#estadoBusqueda')).not.toBeEmpty();
});
