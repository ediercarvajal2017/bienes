import { test, expect } from '@playwright/test';
import { datos, bd } from './helpers/datos.js';

/**
 * Mejoras de la prueba de velocidad del 2026-11-24:
 *  - el resultado de una carga masiva va por páginas y se filtra por estado (antes, una carga
 *    de 2 475 filas pesaba 1,2 MB);
 *  - el CSV de la cartera se escribe directamente (antes armaba un libro de Excel completo);
 *  - cada respuesta de PHP lleva el encabezado Server-Timing (arranque, base, sesión, total).
 */
test.use({ storageState: 'playwright/.auth/rector.json' });
test.beforeEach(() => test.skip(datos().remoto === true, 'Necesita la base de pruebas local'));

test('el resultado de una carga grande va por páginas y se filtra por estado', async ({ page }) => {
    const d = datos();
    const filas = [
        ...Array.from({ length: 120 }, (_, i) => ({ fila: i + 2, tipo: 'sin_cambios', datos: { codigo_identificacion: `VEL-${i}`, descripcion: 'Silla sin cambios' } })),
        ...Array.from({ length: 7 }, (_, i) => ({ fila: i + 122, tipo: 'invalido', codigo: `VEL-MAL-${i}`, descripcion: 'Fila con error', motivo: 'Falta la categoria' })),
        ...Array.from({ length: 3 }, (_, i) => ({ fila: i + 129, tipo: 'nuevo', datos: { codigo_identificacion: `VEL-NUEVO-${i}`, descripcion: 'Mesa nueva' } })),
    ];
    bd(`INSERT INTO cargas_masivas (institucion_id, tipo, usuario_id, archivo_path, total_filas, nuevos, modificados, sin_cambios, resultado_diff_json, aplicada)
        VALUES (${d.instituciones.A}, 'bienes', ${d.usuarios.rector.id}, 'cargas/velocidad.xlsx', 130, 3, 0, 120, '${JSON.stringify(filas)}', 1)`);
    const id = Number(bd("SELECT MAX(id) FROM cargas_masivas WHERE archivo_path = 'cargas/velocidad.xlsx'"));
    try {
        const respuesta = await page.goto(`cargas-masivas/${id}`);
        // 50 filas por página: la página pesa una fracción de lo que pesaría con las 130.
        await expect(page.locator('tbody tr')).toHaveCount(50);
        expect((await respuesta.body()).length).toBeLessThan(120_000);

        const filtro = page.getByRole('navigation', { name: 'Mostrar filas de la carga' });
        await expect(filtro.getByRole('link')).toHaveText(['Todas (130)', 'Inválidas (7)', 'Nuevas (3)', 'Sin cambios (120)']);
        await expect(filtro.getByRole('link', { name: 'Todas (130)' })).toHaveAttribute('aria-current', 'true');

        await filtro.getByRole('link', { name: 'Inválidas (7)' }).click();
        await expect(page).toHaveURL(/estado=invalido/);
        await expect(page.locator('tbody tr')).toHaveCount(7);
        await expect(page.locator('tbody .badge')).toHaveText(Array(7).fill('Inválido'));
        await expect(page.locator('tbody')).toContainText('Falta la categoria');

        await page.goto(`cargas-masivas/${id}?pagina=3`);
        await expect(page.locator('tbody tr')).toHaveCount(30);
        await expect(page.locator('tbody')).toContainText('VEL-NUEVO-2');
    } finally {
        bd(`DELETE FROM cargas_masivas WHERE id = ${id}`);
    }
});

test('el CSV de la cartera trae el encabezado y una fila por bien', async ({ page }) => {
    const respuesta = await page.request.get('reportes/cartera.csv');
    expect(respuesta.status()).toBe(200);
    expect(respuesta.headers()['content-type']).toContain('text/csv');
    expect(respuesta.headers()['content-disposition']).toContain('cartera_bienes.csv');

    const lineas = (await respuesta.text()).trim().split(/\r?\n/);
    expect(lineas[0]).toBe('"Código","Descripción","Marca","Categoría","Ubicación","Responsable","Tipo de responsabilidad","Estado","Valor","Fecha de ingreso"');
    expect(lineas.some((l) => l.startsWith('"PA-0001","Silla de prueba"'))).toBe(true);
    expect(lineas.length).toBeGreaterThan(1);
});

test('cada respuesta dice cuánto tardó el servidor (encabezado Server-Timing)', async ({ page }) => {
    const panel = await page.request.get('dashboard');
    const tiempos = panel.headers()['server-timing'] || '';
    for (const medida of ['arranque', 'bd', 'sesion', 'app']) {
        expect(tiempos, medida).toMatch(new RegExp(`(^|, )${medida};dur=\\d+`));
    }

    // También en una descarga.
    const csv = await page.request.get('reportes/cartera.csv');
    expect(csv.headers()['server-timing']).toMatch(/app;dur=\d+/);
});
