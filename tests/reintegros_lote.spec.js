import { test, expect } from '@playwright/test';
import path from 'node:path';
import { fileURLToPath } from 'node:url';
import { seleccionarTomSelect, seleccionarPrimeraOpcionTomSelect } from './helpers/tomSelect.js';

const __dirname = path.dirname(fileURLToPath(import.meta.url));
const fotoFixture = path.join(__dirname, 'fixtures', 'foto_busqueda.jpg');

/**
 * Reintegrar un bien (con categoría e institución elegidas por defecto igual que en
 * bienes_ciclo_vida.spec.js) y agruparlo en un lote nuevo, hasta ver el comprobante
 * FO-ADMI-009 listo para descargar. No se prueba la descarga en sí: loteFormato()
 * exige que la institución tenga un rector activo registrado, algo que este arranque
 * no puede garantizar en cualquier entorno de desarrollo.
 */
test('reintegrar un bien y agruparlo en un lote', async ({ page }) => {
    const sufijo = Date.now();
    const codigoBien = `PW-TEST-LOTE-${sufijo}`;
    const descripcionBien = `PW-TEST bien para lote de reintegro ${sufijo}`;
    const nombreEspacio = `PW-TEST-EspacioLote-${sufijo}`;

    // --- Espacio + bien asignado, con categoría (igual que exige reintegrarLote()) ---
    await page.goto('espacios/crear');
    await page.locator('input[name="codigo"]').fill(`PWTL-${sufijo}`);
    await page.locator('input[name="nombre"]').fill(nombreEspacio);
    await seleccionarTomSelect(page, 'responsables', 'Edier');
    await page.getByRole('button', { name: 'Registrar espacio' }).click();
    await expect(page).toHaveURL(/\/espacios$/);

    await page.goto('bienes/crear');
    await page.locator('input[name="codigo_identificacion"]').fill(codigoBien);
    await page.locator('input[name="descripcion"]').fill(descripcionBien);
    await seleccionarPrimeraOpcionTomSelect(page, 'categoriaBien');
    await page.setInputFiles('input[name="foto"]', fotoFixture);
    await page.getByRole('button', { name: 'Registrar bien' }).click();
    await expect(page).toHaveURL(/\/bienes$/);

    const filaBien = page.locator('tr', { hasText: codigoBien });
    await filaBien.getByRole('link', { name: 'Editar' }).click();
    await page.locator('#panelAsignar summary').click();
    await seleccionarTomSelect(page, 'espacioAsignar', nombreEspacio);
    await page.locator('#panelAsignar').getByRole('button', { name: 'Asignar' }).click();
    await expect(page.locator('.alert-success')).toBeVisible();

    // --- Reintegrar desde /reintegros (selección masiva, no el panel individual) ---
    await page.goto('reintegros');
    await Promise.all([
        page.waitForURL(/institucion=\d+/),
        seleccionarPrimeraOpcionTomSelect(page, 'selectorInstitucion'),
    ]);

    await page.locator('#buscador').fill(codigoBien);
    await page.waitForURL(/q=/);

    // El filtro de categoría existe (la institución de prueba tiene al menos una) y la
    // foto del bien se ve en la tabla -- las dos cosas que agrega esta pantalla.
    await expect(page.locator('#filtroCategoria')).toBeVisible();
    const filaReintegro = page.locator('tr.fila-bien', { hasText: codigoBien });
    await expect(filaReintegro.locator('img[data-lightbox-src]')).toBeVisible();

    await page.locator('input[name="destino_texto"]').fill('PW-TEST Almacén institucional');
    await page.locator('.casilla-bien').first().check();

    page.once('dialog', (dialog) => dialog.accept());
    await page.locator('.boton-reintegrar').first().click();

    await expect(page.locator('.alert-success')).toBeVisible();

    // --- Agrupar en un lote ---
    await page.goto('reintegros/lotes/generar');
    const filaPendiente = page.locator('tr', { hasText: codigoBien });
    await expect(filaPendiente).toBeVisible();
    await filaPendiente.locator('.casilla-bien').check();

    page.once('dialog', (dialog) => dialog.accept());
    await page.locator('#botonGenerar').click();

    await expect(page).toHaveURL(/\/reintegros\/lotes\/\d+$/);
    await expect(page.locator('tr', { hasText: codigoBien })).toBeVisible();
    await expect(page.getByRole('link', { name: 'Descargar comprobante (FO-ADMI-009)' })).toBeVisible();
});
