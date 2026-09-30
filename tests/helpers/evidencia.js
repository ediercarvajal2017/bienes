import { expect } from '@playwright/test';
import { seleccionarPrimeraOpcionTomSelect } from './tomSelect.js';

/**
 * Recorrido común de las bibliotecas de evidencia en UNA sola ventana (Formatos de
 * reintegro, Formatos de plaqueteo y Facturas): registrar, ver el registro en la lista de
 * abajo, editarlo en la misma ventana, eliminarlo, y que las direcciones viejas del
 * histórico y de editar lleven a la ventana.
 *
 * @param {import('@playwright/test').Page} page  como superusuario (elige la institución)
 * @param {{ ruta: string, archivo: string, llenar: (page: any, texto: string) => Promise<void> }} opciones
 *        llenar escribe los campos obligatorios y deja `texto` en el campo "descripcion".
 */
export async function recorrerVentanaUnica(page, { ruta, archivo, llenar }) {
    const texto = `PW-TEST-${ruta}-${Date.now()}`;
    const textoEditado = `${texto}-editado`;

    await page.goto(ruta);
    await Promise.all([
        page.waitForURL(/institucion=\d+/),
        seleccionarPrimeraOpcionTomSelect(page, 'selectorInstitucion'),
    ]);
    const ventana = page.url();
    await expect(page.getByRole('link', { name: 'Ver histórico' })).toHaveCount(0);

    // Registrar: el registro aparece en la lista, en la misma ventana.
    await llenar(page, texto);
    await page.setInputFiles('input[name="archivo"]', archivo);
    await page.getByRole('button', { name: 'Guardar registro' }).click();
    await expect(page.locator('.alert-success')).toBeVisible();
    await expect(page).toHaveURL(ventana);
    let fila = page.locator('tbody tr', { hasText: texto });
    await expect(fila).toBeVisible();
    await expect(fila.getByRole('link', { name: 'Descargar' })).toBeVisible();

    // Editar en la misma ventana: el formulario sale lleno y la fila queda resaltada.
    await fila.getByRole('link', { name: 'Editar' }).click();
    await expect(page).toHaveURL(/editar=\d+/);
    await expect(page.getByRole('heading', { name: 'Editar registro' })).toBeVisible();
    await expect(page.locator('input[name="descripcion"]')).toHaveValue(texto);
    await expect(page.locator('tbody tr.table-active')).toContainText(texto);
    await page.locator('input[name="descripcion"]').fill(textoEditado);
    await page.getByRole('button', { name: 'Guardar cambios' }).click();
    await expect(page.locator('.alert-success')).toContainText('Registro actualizado');
    await expect(page).not.toHaveURL(/editar=/);
    fila = page.locator('tbody tr', { hasText: textoEditado });
    await expect(fila).toBeVisible();

    // Las direcciones viejas llevan a la ventana única.
    const id = new URL(await fila.getByRole('link', { name: 'Editar' }).getAttribute('href'), ventana).searchParams.get('editar');
    const historial = await page.request.get(`${ruta}/historial`, { maxRedirects: 0 });
    expect(historial.status()).toBe(302);
    expect(historial.headers().location).toContain(`/${ruta}`);
    const editar = await page.request.get(`${ruta}/${id}/editar`, { maxRedirects: 0 });
    expect(editar.headers().location).toContain(`/${ruta}?editar=${id}`);

    // Eliminar desde la lista, con confirmación.
    page.once('dialog', (dialogo) => dialogo.accept());
    await fila.getByRole('button', { name: 'Eliminar' }).click();
    await expect(page.locator('.alert-success')).toContainText('papelera');
    await expect(page.locator('tbody tr', { hasText: textoEditado })).toHaveCount(0);
}
