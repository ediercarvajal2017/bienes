import { test, expect } from '@playwright/test';
import { seleccionarPrimeraOpcionTomSelect } from './helpers/tomSelect.js';

/**
 * La pantalla de asignación masiva (/asignaciones) es distinta del panel "Asignar" de
 * un bien individual (ya cubierto por bienes_ciclo_vida.spec.js): aquí se eligen
 * varios bienes por checkbox y se asignan todos al mismo espacio/fecha de una vez,
 * mismo patrón de selección masiva que reintegros_lote.spec.js.
 */
test('asignar un bien desde la selección masiva de /asignaciones', async ({ page }) => {
    const sufijo = Date.now();
    const codigoBien = `PW-TEST-ASIG-${sufijo}`;

    await page.goto('bienes/crear');
    await page.locator('input[name="codigo_identificacion"]').fill(codigoBien);
    await page.locator('input[name="descripcion"]').fill(`PW-TEST bien para asignación masiva ${sufijo}`);
    await page.getByRole('button', { name: 'Registrar bien' }).click();
    await expect(page).toHaveURL(/\/bienes$/);

    await page.goto('asignaciones');
    await Promise.all([
        page.waitForURL(/institucion=\d+/),
        seleccionarPrimeraOpcionTomSelect(page, 'selectorInstitucion'),
    ]);

    await page.locator('#buscador').fill(codigoBien);
    await page.waitForURL(/q=/);

    const fila = page.locator('tr', { hasText: codigoBien });
    await expect(fila).toBeVisible();
    await expect(fila.locator('.badge')).toHaveText('Sin asignar');
    await fila.locator('.casilla-bien').check();

    const idEspacio = await page.locator('select[name="espacio_id"]').evaluate((el) => el.id);
    await seleccionarPrimeraOpcionTomSelect(page, idEspacio);

    page.once('dialog', (dialog) => dialog.accept());
    await page.locator('.boton-asignar').first().click();

    await expect(page.locator('.alert-success')).toBeVisible();

    // El bien ya no aparece "Sin asignar" en /bienes.
    await page.goto('bienes?q=' + codigoBien);
    await expect(page.locator('tr', { hasText: codigoBien })).not.toContainText('Sin asignar');
});
