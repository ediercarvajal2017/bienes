import { test, expect } from '@playwright/test';
import { datos } from './helpers/datos.js';

/**
 * Visor de fotos (assets/js/lightbox.js): tocar la miniatura de un bien abre la foto en
 * grande, también en el celular (donde la tabla de bienes se ve como tarjetas).
 * El bien PA-0001 de la base de pruebas tiene foto (database/seeders/pruebas.php).
 */
test.use({ storageState: 'playwright/.auth/rector.json' });
test.beforeEach(() => test.skip(datos().remoto === true, 'Necesita la base de pruebas local'));

async function revisarVisor(page, ruta, tocar) {
    await page.goto(ruta);
    const miniatura = page.locator('img[data-lightbox-src]').first();
    await expect(miniatura, ruta).toBeVisible();
    await miniatura.scrollIntoViewIfNeeded();
    await tocar(miniatura);

    const visor = page.getByRole('dialog', { name: 'Foto ampliada' });
    await expect(visor, ruta).toBeVisible();
    const foto = visor.locator('img');
    await expect(foto, ruta).toBeVisible();
    await expect.poll(() => foto.evaluate((img) => img.complete && img.naturalWidth > 0), { message: ruta }).toBe(true);
    // El visor ocupa toda la pantalla y la foto queda dentro de ella.
    const pantalla = page.viewportSize();
    const caja = await visor.boundingBox();
    expect(caja.width, ruta).toBe(pantalla.width);
    expect(caja.height, ruta).toBe(pantalla.height);
    const cajaFoto = await foto.boundingBox();
    expect(cajaFoto.x + cajaFoto.width, ruta).toBeLessThanOrEqual(pantalla.width);
    expect(cajaFoto.y + cajaFoto.height, ruta).toBeLessThanOrEqual(pantalla.height);
    // Tocar la foto no cambia de página (en la lista no abre la edición del bien).
    await expect(page, ruta).toHaveURL(new RegExp(ruta.replace(/[?]/g, '\\?') + '$'));

    await visor.getByRole('button', { name: 'Cerrar foto' }).click();
    await expect(visor, ruta).toBeHidden();
}

// Donde aparece la foto de un bien: la lista, la ficha del QR (la que se abre en el celular
// al escanear) y editar bien.
const rutas = () => {
    const d = datos();
    return ['bienes?q=PA-0001', `qr/${d.qr.A}`, `bienes/${d.bienes.A.silla}/editar`];
};

test('en el computador, un clic en la foto la abre en grande', async ({ page }) => {
    await page.setViewportSize({ width: 1280, height: 800 });
    for (const ruta of rutas()) {
        await revisarVisor(page, ruta, (miniatura) => miniatura.click());
    }
});

test.describe('en el celular', () => {
    test.use({ viewport: { width: 390, height: 844 }, hasTouch: true, isMobile: true });

    test('un toque en la foto la abre en grande', async ({ page }) => {
        for (const ruta of rutas()) {
            await revisarVisor(page, ruta, (miniatura) => miniatura.tap());
        }
    });

    test('en pantallas táctiles la foto lleva cursor de mano (Safari del iPhone solo envía el toque así)', async ({ page }) => {
        await page.goto('bienes?q=PA-0001');
        const cursor = await page.locator('img[data-lightbox-src]').first().evaluate((img) => getComputedStyle(img).cursor);
        expect(cursor).toBe('pointer');
    });
});
