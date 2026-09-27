import { test, expect } from '@playwright/test';
import fs from 'node:fs';
import path from 'node:path';
import { datos, bd, comoRol } from './helpers/datos.js';

/**
 * Auditoría, tanda 3:
 *  - las fotos se sirven con enlaces firmados (sin consultar la base por cada imagen) y la
 *    firma solo vale para la institución de quien vio la página;
 *  - la imagen pública del QR se genera una vez y queda guardada;
 *  - "Buscar por foto" termina aunque una foto no se pueda cargar (antes, ciclo sin fin);
 *  - ninguna pantalla incumple la política de seguridad de contenido.
 */
test.beforeEach(() => test.skip(datos().remoto === true, 'Necesita la base de pruebas local'));
test.use({ storageState: 'playwright/.auth/rector.json' });

const avisoCsp = (texto) => /Content Security Policy|Report Only/i.test(texto);

test('la foto del bien usa un enlace firmado que solo vale para su institución', async ({ page, browser }) => {
    const d = datos();
    await page.goto(`bienes/${d.bienes.A.silla}/editar`);
    const src = await page.locator(`img[src*="${path.basename(d.fotos.silla)}"]`).first().getAttribute('src');
    expect(src).toMatch(/[?&]e=\d+&f=[a-f0-9]{32}/);

    const propia = await page.request.get(src);
    expect(propia.status()).toBe(200);
    expect(propia.headers()['content-type']).toMatch(/^image\//);

    // El mismo enlace en manos de otra institución no sirve: se revisa contra la base y se niega.
    const otra = await comoRol(browser, 'rector_b');
    const ajena = await otra.request.get(src);
    expect(ajena.status()).toBe(404);
    await otra.close();

    // Una firma alterada tampoco abre un archivo que no es de la institución.
    const falsa = await page.request.get('archivos/fotos_bienes/NOEXISTE_1.jpg?e=9999999999&f=' + 'a'.repeat(32));
    expect(falsa.status()).toBe(404);
});

test('la imagen del QR se genera una vez y queda guardada', async ({ page }) => {
    const token = datos().qr.A;
    const guardada = path.resolve('storage/pruebas/cache/qr', `${token}.png`);
    fs.rmSync(guardada, { force: true });

    const primera = await page.request.get(`qr/${token}/imagen`);
    expect(primera.status()).toBe(200);
    expect(primera.headers()['content-type']).toBe('image/png');
    expect(fs.existsSync(guardada)).toBe(true);

    const segunda = await page.request.get(`qr/${token}/imagen`);
    expect(segunda.status()).toBe(200);
    expect((await segunda.body()).equals(await primera.body())).toBe(true);

    expect((await page.request.get('qr/no-es-un-token/imagen')).status()).toBe(404);
    expect((await page.request.get('qr/00000000-0000-0000-0000-000000000000/imagen')).status()).toBe(404);
});

test('"Buscar por foto" termina el indexado aunque una foto no cargue', async ({ page }) => {
    test.setTimeout(120_000);
    const d = datos();
    const codigo = `PW-FOTOPERDIDA-${Date.now()}`;
    bd(`INSERT INTO bienes (institucion_id, codigo_identificacion, descripcion, fecha_ingreso, valor, estado, qr_token, foto_path)
        VALUES (${d.instituciones.A}, '${codigo}', 'PW foto perdida', CURDATE(), 1000, 'activo', UUID(), 'fotos_bienes/${codigo}_1.jpg')`);

    const pedidas = [];
    const avisos = [];
    page.on('request', (r) => { if (r.url().includes(codigo)) pedidas.push(r.url()); });
    page.on('console', (m) => { if (avisoCsp(m.text())) avisos.push(m.text()); });

    try {
        await page.goto('bienes/buscar-por-foto');
        await page.waitForFunction(() => window.__indexadoListo === true, { timeout: 90_000 });
        // La foto perdida se intenta una sola vez; antes se pedía una y otra vez sin fin.
        expect(pedidas.length).toBe(1);
        expect(avisos, avisos.join('\n')).toEqual([]);
    } finally {
        bd(`UPDATE bienes SET foto_path = NULL WHERE codigo_identificacion = '${codigo}'`);
    }
});

test('ninguna pantalla incumple la política de seguridad de contenido', async ({ page }) => {
    test.setTimeout(180_000);
    const d = datos();
    const avisos = [];
    page.on('console', (m) => { if (avisoCsp(m.text())) avisos.push(`${page.url()}: ${m.text().slice(0, 200)}`); });

    for (const ruta of [
        'dashboard', 'buscar?q=a', 'mi-cuenta', 'manual', 'bienes', 'bienes/crear', `bienes/${d.bienes.A.silla}/editar`,
        'bienes/alta-masiva', 'bienes/qr-masivo', 'bienes/qr-masivo/bodega', 'bienes/carga-masiva-fotos',
        'espacios', 'asignaciones', 'reintegros', 'reintegros/lotes', 'reintegros/solicitudes', 'bajas',
        'verificaciones', 'escanear', 'reportes', 'cartera/enviar', 'usuarios', 'categorias', 'cargas-masivas',
        `qr/${d.qr.A}`,
    ]) {
        await page.goto(ruta, { waitUntil: 'networkidle' });
    }

    expect(avisos, avisos.join('\n')).toEqual([]);
});
