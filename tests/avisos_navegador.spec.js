import { test, expect } from '@playwright/test';
import AxeBuilder from '@axe-core/playwright';
import path from 'node:path';
import { fileURLToPath } from 'node:url';

const __dirname = path.dirname(fileURLToPath(import.meta.url));
const foto = path.join(__dirname, 'fixtures', 'foto_busqueda.jpg');

/**
 * Avisos que arma el navegador (v1.2.38): búsqueda por foto, escáner y Reintegrar. Siguen la
 * misma regla que los del servidor: qué pasó, por qué y qué hacer, con el diseño de MIA.
 */
test.use({ storageState: 'playwright/.auth/rector.json' });

/**
 * Buscar por foto sin el modelo real (pesado, por CDN): un modelo de mentira que entrega una
 * huella fija. El indexado en segundo plano no recibe pendientes, para no guardar huellas
 * de mentira en la base de pruebas.
 */
async function prepararBusquedaPorFoto(page, { modeloFalla = false } = {}) {
    await page.route('https://cdn.jsdelivr.net/npm/@tensorflow/**', (ruta) => ruta.abort());
    await page.route('**/bienes/buscar-por-foto/pendientes', (ruta) => ruta.fulfill({ json: { pendientes: [], total: 0 } }));
    await page.addInitScript((falla) => {
        const huella = new Float32Array(1024).fill(0.5);
        Object.defineProperty(window, 'mobilenet', {
            value: { load: () => (falla ? Promise.reject(new Error('sin modelo')) : Promise.resolve({ infer: () => ({ dataSync: () => huella, dispose() {} }) })) },
            writable: false,
        });
    }, modeloFalla);
    await page.goto('bienes/buscar-por-foto');
}

test('buscar por foto sin conexión ofrece reintentar; sin parecidos, dice cómo seguir', async ({ page }) => {
    await prepararBusquedaPorFoto(page);
    await page.route('**/bienes/buscar-por-foto/buscar', (ruta) => ruta.abort('internetdisconnected'));
    await page.setInputFiles('#inputFotoBusqueda', foto);

    const aviso = page.locator('#estadoBusqueda + .alert-danger');
    await expect(aviso.locator('.aviso-titulo')).toHaveText('No se pudo buscar');
    await expect(aviso).toContainText('MIA no recibió respuesta del servidor.');
    await expect(aviso).toContainText('Qué hacer: Revisa tu conexión a internet y pulsa Reintentar.');

    // Vuelve la conexión: Reintentar repite la búsqueda con la misma foto.
    await page.unroute('**/bienes/buscar-por-foto/buscar');
    await page.route('**/bienes/buscar-por-foto/buscar', (ruta) => ruta.fulfill({ json: { resultados: [] } }));
    await aviso.getByRole('button', { name: 'Reintentar' }).click();
    await expect(page.locator('#estadoBusqueda + .alert')).toHaveCount(0);

    const vacio = page.locator('#resultadosBusqueda .estado-vacio-explicado');
    await expect(vacio.locator('.estado-vacio-titulo')).toHaveText('Ningún bien se parece a esta foto.');
    await expect(vacio.getByRole('link', { name: 'Buscar por código' })).toHaveAttribute('href', /escanear$/);
    await expect(vacio.getByRole('link', { name: 'Buscar en Bienes' })).toHaveAttribute('href', /bienes$/);
});

test('buscar por foto: si el servidor la rechaza, el aviso trae su porqué y qué hacer', async ({ page }) => {
    await prepararBusquedaPorFoto(page);
    await page.route('**/bienes/buscar-por-foto/buscar', (ruta) => ruta.fulfill({ status: 403,
        json: { error: 'La página estuvo abierta mucho tiempo y venció por seguridad.', queHacer: 'Recarga la página y vuelve a tomar la foto.' } }));
    await page.setInputFiles('#inputFotoBusqueda', foto);

    const aviso = page.locator('#estadoBusqueda + .alert-danger');
    await expect(aviso).toContainText('La página estuvo abierta mucho tiempo y venció por seguridad.');
    await expect(aviso).toContainText('Qué hacer: Recarga la página y vuelve a tomar la foto.');
});

test('buscar por foto: si el navegador no puede analizarla, lo dice y sugiere otra vía', async ({ page }) => {
    await prepararBusquedaPorFoto(page, { modeloFalla: true });
    await page.setInputFiles('#inputFotoBusqueda', foto);

    const aviso = page.locator('#estadoBusqueda + .alert-danger');
    await expect(aviso.locator('.aviso-titulo')).toHaveText('No se pudo analizar la foto');
    await expect(aviso).toContainText('Prueba con otra foto (JPG o PNG) o con Chrome o Edge actualizados.');
});

for (const tema of ['light', 'dark']) {
    test(`escanear con la cámara bloqueada lo explica en español (tema ${tema === 'light' ? 'claro' : 'oscuro'})`, async ({ page }) => {
        await page.addInitScript((t) => {
            try { localStorage.setItem('sigebi-theme', t); } catch (e) {}
            if (navigator.mediaDevices) {
                navigator.mediaDevices.getUserMedia = () => Promise.reject(new DOMException('Permission denied', 'NotAllowedError'));
            }
        }, tema);
        await page.goto('escanear');

        const aviso = page.locator('#mensajeEstadoCamara + .alert-info');
        await expect(aviso.locator('.aviso-titulo')).toHaveText('No se pudo abrir la cámara');
        await expect(aviso).toContainText('No diste permiso para usar la cámara.');
        await expect(aviso).toContainText('Qué hacer: Permite la cámara en el candado de la barra de direcciones');
        await expect(aviso).toContainText('«Buscar por código»');
        await expect(aviso).not.toContainText('Permission');

        const { violations } = await new AxeBuilder({ page }).include('#mensajeEstadoCamara + .alert').withTags(['wcag2a', 'wcag2aa']).analyze();
        expect(violations.map((v) => v.id)).toEqual([]);
    });
}

test('Reintegrar: «Ver solo seleccionados» sin selección dice cómo seleccionar', async ({ page }) => {
    await page.goto('reintegros');
    await page.evaluate(() => { try { sessionStorage.clear(); } catch (e) {} });
    await page.reload();
    await page.locator('#verSoloSeleccionados').click();

    const modal = page.locator('#modalConfirmar');
    await expect(modal).toContainText('No tienes ningún bien seleccionado todavía.');
    await expect(modal).toContainText('Qué hacer: marca la casilla de cada bien en la lista o escanea su QR');
    await modal.getByRole('button', { name: 'Entendido' }).click();
    await expect(page.locator('#verSoloSeleccionados')).not.toBeChecked();
});
