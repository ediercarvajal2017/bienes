import { test, expect } from '@playwright/test';
import AxeBuilder from '@axe-core/playwright';

/**
 * Guía visual interna (/manual/ui, solo superusuario) y modal de confirmación propio
 * (partials/ui/modal_confirmar.php, assets/js/confirmar.js), que reemplaza al confirm()
 * del navegador.
 */
test.use({ storageState: 'playwright/.auth/superusuario.json' });

test('la guía muestra los tokens, los íconos y los componentes', async ({ page }) => {
    await page.goto('manual/ui');
    await expect(page.getByRole('heading', { level: 1, name: 'Guía visual' })).toBeVisible();
    for (const seccion of ['Colores', 'Tipografía', 'Botones', 'Estados', 'Avisos', 'Formulario', 'Tabla de datos', 'Íconos']) {
        await expect(page.getByRole('heading', { name: seccion, exact: true })).toBeVisible();
    }
    // Diccionario de íconos: una fila por clave, con la del ícono de editar.
    await expect(page.getByRole('row', { name: /pencil/ })).toContainText('editar');
    // Cifras con separador de miles colombiano.
    await expect(page.getByText('$11.356.400').first()).toBeVisible();
    await expect(page.getByText('Mostrando 26–50 de 1.340')).toBeVisible();
    // Botón de solo ícono con nombre accesible.
    await expect(page.getByRole('button', { name: 'Descargar el código QR' })).toHaveAttribute('title', 'Descargar el código QR');
});

test('el modal de confirmación: cancelar, Escape y confirmar', async ({ page }) => {
    await page.goto('manual/ui');
    const eliminar = page.getByRole('link', { name: 'Eliminar' });
    const modal = page.locator('#modalConfirmar');

    await eliminar.click();
    await expect(modal).toBeVisible();
    await expect(modal).toContainText('El espacio pasará a la papelera.');
    const aceptar = modal.locator('[data-confirmar-aceptar]');
    await expect(aceptar).toHaveText('Eliminar');
    await expect(aceptar).toHaveClass(/btn-danger/);
    // Acción de peligro: el foco empieza en Cancelar.
    await expect(modal.locator('[data-confirmar-cancelar]')).toBeFocused();

    await modal.getByRole('button', { name: 'Cancelar' }).click();
    await expect(modal).toBeHidden();
    await expect(page).not.toHaveURL(/#botones/);
    await expect(eliminar).toBeFocused();

    await eliminar.click();
    await page.keyboard.press('Escape');
    await expect(modal).toBeHidden();
    await expect(page).not.toHaveURL(/#botones/);

    await eliminar.click();
    await aceptar.click();
    await expect(page).toHaveURL(/manual\/ui#botones/);
});

test('miaConfirmar() devuelve la respuesta del usuario', async ({ page }) => {
    await page.goto('manual/ui');
    const respuesta = page.evaluate(() => window.miaConfirmar({ mensaje: 'Línea uno\nLínea dos', boton: 'Continuar' }));
    const modal = page.locator('#modalConfirmar');
    await expect(modal).toContainText('Línea uno');
    await expect(modal.locator('[data-confirmar-aceptar]')).toHaveClass(/btn-primary/);
    await modal.getByRole('button', { name: 'Continuar' }).click();
    expect(await respuesta).toBe(true);
});

for (const tema of ['light', 'dark']) {
    test(`la guía no tiene problemas de accesibilidad (${tema === 'light' ? 'modo claro' : 'modo oscuro'})`, async ({ page }) => {
        await page.addInitScript((t) => localStorage.setItem('sigebi-theme', t), tema);
        await page.goto('manual/ui');
        const { violations } = await new AxeBuilder({ page }).withTags(['wcag2a', 'wcag2aa']).analyze();
        const graves = violations
            .filter((v) => v.impact === 'serious' || v.impact === 'critical')
            .map((v) => `${v.id} (${v.nodes.length}): ${v.nodes.slice(0, 2).map((n) => n.target.join(' ')).join(' | ')}`);
        expect(graves, graves.join('\n')).toEqual([]);

        // Y con el modal abierto.
        await page.getByRole('link', { name: 'Eliminar' }).click();
        const conModal = await new AxeBuilder({ page }).include('#modalConfirmar').withTags(['wcag2a', 'wcag2aa']).analyze();
        expect(conModal.violations.filter((v) => v.impact === 'serious' || v.impact === 'critical')).toEqual([]);
    });
}
