import { test, expect } from '@playwright/test';

function filaConValor(page, valor) {
    // CSS por atributo: matchea el "value" del HTML tal como lo renderizó el servidor
    // en la última carga de la página, no la propiedad viva del input tras un fill().
    return page.locator('tr').filter({ has: page.locator(`input[value="${valor}"]`) });
}

test('crear, editar, desactivar/activar y eliminar una categoría', async ({ page }) => {
    const nombre = `PW-TEST-Categoria-${Date.now()}`;
    const nombreEditado = `${nombre}-editada`;

    await page.goto('categorias');

    await page.locator('form[action$="/categorias"] input[name="nombre"]').fill(nombre);
    await page.locator('form[action$="/categorias"] button[type="submit"]').click();

    let fila = filaConValor(page, nombre);
    await expect(fila).toBeVisible();
    await expect(fila.locator('.badge')).toHaveText('Activa');

    // Editar el nombre inline.
    await fila.locator('input[name="nombre"]').fill(nombreEditado);
    await fila.getByRole('button', { name: 'Guardar' }).click();

    fila = filaConValor(page, nombreEditado);
    await expect(fila).toBeVisible();

    // Desactivar y volver a activar.
    await fila.getByRole('button', { name: 'Desactivar' }).click();
    fila = filaConValor(page, nombreEditado);
    await expect(fila.locator('.badge')).toHaveText('Inactiva');

    await fila.getByRole('button', { name: 'Activar' }).click();
    fila = filaConValor(page, nombreEditado);
    await expect(fila.locator('.badge')).toHaveText('Activa');

    // Eliminar: va a la papelera (borrado suave), desaparece del listado activo.
    page.once('dialog', (dialog) => dialog.accept());
    await fila.getByRole('button', { name: 'Eliminar' }).click();

    await expect(page.locator(`input[value="${nombreEditado}"]`)).toHaveCount(0);
});
