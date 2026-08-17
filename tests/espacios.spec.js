import { test, expect } from '@playwright/test';
import { seleccionarTomSelect } from './helpers/tomSelect.js';

test('crear, editar y eliminar un espacio', async ({ page }) => {
    const codigo = `PWT-${Date.now()}`;
    const nombre = `PW-TEST-Espacio-${Date.now()}`;
    const nombreEditado = `${nombre}-editado`;

    await page.goto('espacios/crear');

    await page.locator('input[name="codigo"]').fill(codigo);
    await page.locator('input[name="nombre"]').fill(nombre);
    // El select de institución no tiene opción en blanco -queda la primera seleccionada
    // por defecto- así que solo hace falta elegir el responsable.
    await seleccionarTomSelect(page, 'responsables', 'Edier');
    await page.getByRole('button', { name: 'Registrar espacio' }).click();

    await expect(page).toHaveURL(/\/espacios$/);
    let fila = page.locator('tr', { hasText: nombre });
    await expect(fila).toBeVisible();

    await fila.getByRole('link', { name: 'Editar' }).click();
    await expect(page.locator('input[name="nombre"]')).toHaveValue(nombre);

    await page.locator('input[name="nombre"]').fill(nombreEditado);
    await page.getByRole('button', { name: 'Guardar cambios' }).click();

    fila = page.locator('tr', { hasText: nombreEditado });
    await expect(fila).toBeVisible();

    page.once('dialog', (dialog) => dialog.accept());
    await fila.getByRole('button', { name: 'Eliminar' }).click();

    await expect(page.locator('tr', { hasText: nombreEditado })).toHaveCount(0);
});
