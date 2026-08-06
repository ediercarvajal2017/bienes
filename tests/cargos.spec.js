import { test, expect } from '@playwright/test';

function filaConValor(page, valor) {
    return page.locator('tr').filter({ has: page.locator(`input[value="${valor}"]`) });
}

test('crear, editar, desactivar/activar y eliminar un cargo', async ({ page }) => {
    const nombre = `PW-TEST-Cargo-${Date.now()}`;
    const nombreEditado = `${nombre}-editado`;

    await page.goto('cargos');

    await page.locator('form[action$="/cargos"] input[name="nombre"]').fill(nombre);
    await page.locator('form[action$="/cargos"] button[type="submit"]').click();

    let fila = filaConValor(page, nombre);
    await expect(fila).toBeVisible();
    await expect(fila.locator('.badge')).toHaveText('Activo');

    await fila.locator('input[name="nombre"]').fill(nombreEditado);
    await fila.getByRole('button', { name: 'Guardar' }).click();

    fila = filaConValor(page, nombreEditado);
    await expect(fila).toBeVisible();

    await fila.getByRole('button', { name: 'Desactivar' }).click();
    fila = filaConValor(page, nombreEditado);
    await expect(fila.locator('.badge')).toHaveText('Inactivo');

    await fila.getByRole('button', { name: 'Activar' }).click();
    fila = filaConValor(page, nombreEditado);
    await expect(fila.locator('.badge')).toHaveText('Activo');

    page.once('dialog', (dialog) => dialog.accept());
    await fila.getByRole('button', { name: 'Eliminar' }).click();

    await expect(page.locator(`input[value="${nombreEditado}"]`)).toHaveCount(0);
});
