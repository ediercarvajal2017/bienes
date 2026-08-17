import { test, expect } from '@playwright/test';

/**
 * ArchivoController sirve lo subido a storage/ (fuera del webroot) con una lista
 * blanca de carpetas + una regex sobre el nombre de archivo -- la defensa real contra
 * path traversal. Usa el bien fijo PW-TEST-FOTO-001 (ver
 * bienes_carga_masiva_fotos.spec.js), que siempre tiene una foto real.
 */
test.use({ storageState: 'playwright/.auth/user.json' });

test('archivos: sirve una foto real y rechaza carpeta/nombre inválidos', async ({ page }) => {
    const real = await page.request.get('archivos/fotos_bienes/PW-TEST-FOTO-001_1.jpg');
    expect(real.status()).toBe(200);
    expect(real.headers()['content-type']).toMatch(/^image\//);

    const carpetaInvalida = await page.request.get('archivos/carpeta-que-no-existe/algo.jpg');
    expect(carpetaInvalida.status()).toBe(404);

    const traversal = await page.request.get('archivos/fotos_bienes/..%2F..%2Fconfig%2Fdatabase.php');
    expect(traversal.status()).toBe(404);

    const archivoInexistente = await page.request.get('archivos/fotos_bienes/no-existe-de-verdad.jpg');
    expect(archivoInexistente.status()).toBe(404);
});
