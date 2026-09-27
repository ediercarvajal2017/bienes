import { test, expect } from '@playwright/test';
import { datos, bd } from './helpers/datos.js';

/**
 * Bodega de impresión de QR en dos pestañas: "Por imprimir" → (imprimir) → "Impresos,
 * por pegar" → (confirmar pegados) → sale de la bodega.
 */
test.beforeEach(() => test.skip(datos().remoto === true, 'Necesita la base de pruebas local'));
test.use({ storageState: 'playwright/.auth/rector.json' });

/** Crea un bien pedido para imprimir; `impresoAntes` simula una impresión vieja. */
function bienSolicitado(sufijo, { impresoAntes = false, institucion = datos().instituciones.A } = {}) {
    const codigo = `PW-QRB-${sufijo}`;
    bd(`INSERT INTO bienes (institucion_id, codigo_identificacion, descripcion, categoria_id, fecha_ingreso, valor, estado, qr_token,
            qr_solicitado_en, qr_impreso_en)
        SELECT ${institucion}, '${codigo}', 'PW bien bodega ${sufijo}', id, CURDATE(), 1000, 'activo', UUID(),
            NOW(), ${impresoAntes ? 'NOW() - INTERVAL 30 DAY' : 'NULL'}
          FROM categorias_bienes WHERE institucion_id = ${institucion} LIMIT 1`);
    return { codigo, id: Number(bd(`SELECT id FROM bienes WHERE codigo_identificacion = '${codigo}' AND institucion_id = ${institucion}`)) };
}

test('imprimir pasa el bien a "Impresos, por pegar" y confirmar lo saca de la bodega', async ({ page }) => {
    const sufijo = Date.now();
    const { codigo, id } = bienSolicitado(sufijo);

    await page.goto('bienes/qr-masivo/bodega?pestana=por_imprimir');
    const fila = page.locator('tr', { hasText: codigo });
    await expect(fila).toBeVisible();

    // Solo este bien: se desmarcan los demás pendientes de la base de pruebas.
    await page.locator('.seleccionar-todos').uncheck();
    await fila.locator('.casilla-bien').check();
    const [hoja] = await Promise.all([page.waitForEvent('popup'), page.locator('#botonGenerar').click()]);
    await hoja.waitForLoadState();
    await hoja.close();

    // La bodega se recarga sola: ya no está por imprimir, sino por pegar.
    await expect(page.locator('tr', { hasText: codigo })).toHaveCount(0, { timeout: 10000 });
    await page.locator('#pestanaPorPegar').click();
    const filaPegar = page.locator('tr', { hasText: codigo });
    await expect(filaPegar).toBeVisible();

    await filaPegar.locator('.casilla-bien').check();
    page.once('dialog', (d) => d.accept());
    await page.locator('#botonConfirmarPegados').click();
    await expect(page.locator('.alert-success')).toContainText('1 sticker confirmado como pegado');
    await expect(page.locator('tr', { hasText: codigo })).toHaveCount(0);

    expect(bd(`SELECT qr_solicitado_en IS NULL, qr_confirmado_en IS NOT NULL FROM bienes WHERE id = ${id}`)).toBe('1\t1');
    expect(bd(`SELECT COUNT(*) FROM auditoria WHERE accion = 'confirmar_qr' AND entidad = 'bien' AND entidad_id = ${id}`)).toBe('1');
});

test('un bien impreso hace tiempo que se vuelve a pedir aparece por imprimir', async ({ page }) => {
    const { codigo } = bienSolicitado(Date.now(), { impresoAntes: true });

    await page.goto('bienes/qr-masivo/bodega?pestana=por_imprimir');
    await expect(page.locator('tr', { hasText: codigo })).toBeVisible();
    await page.goto('bienes/qr-masivo/bodega?pestana=por_pegar');
    await expect(page.locator('tr', { hasText: codigo })).toHaveCount(0);
});

test('no se confirma un bien sin imprimir ni uno de otra institución', async ({ page }) => {
    const sufijo = Date.now();
    const sinImprimir = bienSolicitado(sufijo);
    const otra = bienSolicitado(sufijo + 1, { institucion: datos().instituciones.B });
    bd(`UPDATE bienes SET qr_impreso_en = NOW() WHERE id = ${otra.id}`);

    // La pestaña "Por imprimir" siempre tiene formulario (con el token CSRF): se envía a mano.
    await page.goto('bienes/qr-masivo/bodega?pestana=por_imprimir');
    const respuesta = await page.evaluate(async ({ ids }) => {
        const cuerpo = new URLSearchParams({ _csrf: document.querySelector('input[name="_csrf"]').value });
        ids.forEach((i) => cuerpo.append('bienes[]', String(i)));
        const r = await fetch(location.pathname + '/confirmar', { method: 'POST', body: cuerpo });
        return r.status;
    }, { ids: [sinImprimir.id, otra.id] });
    expect(respuesta).toBe(200);

    expect(bd(`SELECT COUNT(*) FROM bienes WHERE id IN (${sinImprimir.id}, ${otra.id}) AND qr_solicitado_en IS NOT NULL AND qr_confirmado_en IS NULL`)).toBe('2');
});
