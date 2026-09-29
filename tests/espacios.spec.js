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

test('al intentar eliminar un espacio en uso, el aviso dice qué bienes lo usan', async ({ browser }) => {
    const { datos, bd, comoRol } = await import('./helpers/datos.js');
    const aula = datos().espacios.aulaA;
    const asignados = Number(bd(`SELECT COUNT(DISTINCT bien_id) FROM asignaciones WHERE espacio_id = ${aula} AND activa = 1`));
    expect(asignados).toBeGreaterThan(0);

    const contexto = await comoRol(browser, 'rector');
    const pagina = await contexto.newPage();
    await pagina.goto('espacios');
    pagina.once('dialog', (dialogo) => dialogo.accept());
    await pagina.locator(`form[action$="/espacios/${aula}/eliminar"] button`).click();

    const aviso = pagina.getByRole('alert');
    await expect(aviso).toContainText('No se puede eliminar el espacio');
    await expect(aviso).toContainText(asignados === 1 ? '1 bien asignado' : `${asignados} bienes asignados`);
    // Cada bien, con enlace a su ficha.
    const primerCodigo = bd(`SELECT b.codigo_identificacion FROM asignaciones a JOIN bienes b ON b.id = a.bien_id
                             WHERE a.espacio_id = ${aula} AND a.activa = 1 ORDER BY b.codigo_identificacion LIMIT 1`);
    await expect(aviso.getByRole('link', { name: primerCodigo })).toHaveAttribute('href', /\/bienes\/\d+\/editar$/);
    await expect(aviso).toContainText('Traslada o reintegra esos bienes');
    // El espacio sigue ahí.
    expect(bd(`SELECT eliminado_en IS NULL FROM espacios WHERE id = ${aula}`)).toBe('1');

    // El aviso no se queda en la sesión: al recargar ya no aparece.
    await pagina.reload();
    await expect(pagina.getByRole('alert')).toHaveCount(0);
    await contexto.close();
});
