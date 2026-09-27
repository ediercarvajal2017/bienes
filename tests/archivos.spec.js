import { test, expect } from '@playwright/test';
import { datos, comoRol } from './helpers/datos.js';

/**
 * ArchivoController sirve lo subido a storage/ (fuera del webroot) con una lista
 * blanca de carpetas + una regex sobre el nombre de archivo -- la defensa real contra
 * path traversal. Usa la foto del bien "silla" de la institución A que crea
 * database/seeders/pruebas.php.
 */
test.use({ storageState: 'playwright/.auth/superusuario.json' });

test('archivos: sirve una foto real y rechaza carpeta/nombre inválidos', async ({ page, browser }) => {
    const foto = datos().fotos.silla;
    const real = await page.request.get(`archivos/${foto}`);
    expect(real.status()).toBe(200);
    expect(real.headers()['content-type']).toMatch(/^image\//);

    // Miniatura (?w=96): WebP mucho más liviana que la original.
    const miniatura = await page.request.get(`archivos/${foto}?w=96`);
    expect(miniatura.status()).toBe(200);
    expect(miniatura.headers()['content-type']).toBe('image/webp');
    expect((await miniatura.body()).length).toBeLessThan((await real.body()).length);

    // Una foto de la institución A no se sirve a un usuario de la institución B (ni su miniatura).
    const rectorB = await comoRol(browser, 'rector_b');
    for (const url of [`archivos/${foto}`, `archivos/${foto}?w=96`]) {
        expect((await rectorB.request.get(url)).status()).toBe(404);
    }
    await rectorB.close();

    const carpetaInvalida = await page.request.get('archivos/carpeta-que-no-existe/algo.jpg');
    expect(carpetaInvalida.status()).toBe(404);

    const traversal = await page.request.get('archivos/fotos_bienes/..%2F..%2Fconfig%2Fdatabase.php');
    expect(traversal.status()).toBe(404);

    const archivoInexistente = await page.request.get('archivos/fotos_bienes/no-existe-de-verdad.jpg');
    expect(archivoInexistente.status()).toBe(404);
});
