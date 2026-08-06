import { test, expect } from '@playwright/test';

/**
 * A propósito NO crea una institución nueva: a diferencia de usuarios/espacios/
 * categorías/cargos, una institución nunca se puede borrar ni enviar a la papelera
 * (solo activar/desactivar) — cualquiera que creáramos quedaría para siempre en la
 * base de datos real. En su lugar, se edita una institución EXISTENTE guardando
 * exactamente los mismos datos que ya tenía, para probar el formulario de punta a
 * punta sin modificar nada de verdad.
 */
test('el listado de instituciones carga', async ({ page }) => {
    await page.goto('instituciones');

    await expect(page.locator('h1')).toContainText('Instituciones');
    await expect(page.locator('table')).toBeVisible();
});

test('editar una institución existente conservando sus datos', async ({ page }) => {
    await page.goto('instituciones');

    await page.getByRole('link', { name: 'Editar' }).first().click();
    await expect(page).toHaveURL(/\/instituciones\/\d+\/editar/);

    const nombre = await page.locator('input[name="nombre"]').inputValue();
    expect(nombre.length).toBeGreaterThan(0);

    await page.getByRole('button', { name: 'Guardar cambios' }).click();

    await expect(page).toHaveURL(/\/instituciones$/);
    await expect(page.locator('.alert-success')).toContainText('actualizada');
    await expect(page.locator('tr', { hasText: nombre })).toBeVisible();
});
