import { test, expect } from '@playwright/test';
import { datos } from './helpers/datos.js';

/**
 * Ayuda emergente de los campos (App\Helpers\AyudaCampo y assets/js/ayuda-campo.js): el
 * ícono (?) junto al título de cada campo del formulario de bienes muestra una explicación
 * detallada con el cursor, con el teclado (Tab / Escape) y con un toque en el celular. El
 * formulario ya no muestra textos sueltos debajo de los campos.
 */
test.use({ storageState: 'playwright/.auth/rector.json' });

const ayudaDe = (page, titulo) => page.getByRole('button', { name: `Ayuda: ${titulo}`, exact: true });
const globoDe = async (page, boton) => page.locator(`#${await boton.getAttribute('aria-controls')}`);

test('con el cursor se abre y al salir se cierra; con un clic queda fija', async ({ page }) => {
    await page.goto('bienes/crear');
    const boton = ayudaDe(page, 'Categoría');
    const globo = await globoDe(page, boton);
    await expect(globo).toBeHidden();

    await boton.hover();
    await expect(globo).toBeVisible();
    await expect(globo).toContainText('Sin cartera');
    await expect(boton).toHaveAttribute('aria-expanded', 'true');

    // Se puede pasar del ícono al globo (para leerlo) sin que se cierre.
    await globo.hover();
    await page.waitForTimeout(400);
    await expect(globo).toBeVisible();

    await page.locator('h1').hover();
    await expect(globo).toBeHidden();
    await expect(boton).toHaveAttribute('aria-expanded', 'false');

    // Un clic la deja fija aunque el cursor se vaya; otro clic fuera la cierra.
    await boton.click();
    await page.locator('h1').hover();
    await page.waitForTimeout(400);
    await expect(globo).toBeVisible();
    await page.locator('h1').click();
    await expect(globo).toBeHidden();

    // Solo una abierta a la vez.
    await ayudaDe(page, 'Código de identificación').click();
    await ayudaDe(page, 'Descripción').click();
    await expect(page.locator('.ayuda-campo-globo:visible')).toHaveCount(1);
    await expect(page.locator('.ayuda-campo-globo:visible')).toContainText('SILLA UNIVERSITARIA EN MADERA');
});

test('con el teclado: Tab la abre y Escape la cierra', async ({ page }) => {
    await page.goto('bienes/crear');
    const boton = ayudaDe(page, 'Código de identificación');
    const globo = await globoDe(page, boton);

    await page.locator('#codigoIdentificacion').focus();
    await page.keyboard.press('Shift+Tab');
    await expect(boton).toBeFocused();
    await expect(globo).toBeVisible();
    await expect(globo).toContainText('No se puede repetir');

    await page.keyboard.press('Escape');
    await expect(globo).toBeHidden();
});

test('el nombre de cada campo no cambia y ya no hay textos sueltos', async ({ page }) => {
    await page.goto('bienes/crear');
    await expect(page.getByLabel('Código de identificación', { exact: true })).toHaveAttribute('id', 'codigoIdentificacion');
    await expect(page.getByLabel('Descripción', { exact: true })).toHaveAttribute('id', 'campo-descripcion');
    await expect(page.getByLabel('Fecha de ingreso', { exact: true })).toHaveAttribute('id', 'campo-fecha-ingreso');
    await expect(page.getByRole('group', { name: 'Responsabilidad', exact: true })).toBeVisible();
    await expect(page.getByLabel('Grupal', { exact: true })).toBeChecked();

    await expect(page.getByText('Los campos marcados con')).toHaveCount(0);
    await expect(page.locator('#datosBien .form-text')).toHaveCount(0);
    await expect(page.getByRole('heading', { name: 'Datos del bien' })).toBeVisible();
    await expect(page.getByRole('heading', { name: /Foto y documentos/ })).toBeVisible();

    // La Categoría va antes que el Código (elegir "Sin cartera" sugiere el código).
    // (Tom Select cambia el "for" de la etiqueta de Categoría: se busca por el texto.)
    const categoria = await page.locator('.etiqueta-ayuda label', { hasText: /^Categoría$/ }).boundingBox();
    const codigo = await page.locator('.etiqueta-ayuda label', { hasText: /^Código de identificación$/ }).boundingBox();
    expect(categoria.y < codigo.y || (categoria.y === codigo.y && categoria.x < codigo.x)).toBe(true);
});

test('la ficha del bien y el alta masiva también tienen su ayuda', async ({ page }) => {
    test.skip(datos().remoto === true, 'Necesita la base de pruebas local');
    await page.goto(`bienes/${datos().bienes.A.silla}/editar`);
    await ayudaDe(page, '¿Qué desea hacer con este bien?').click();
    await expect(page.locator('.ayuda-campo-globo:visible')).toContainText('pendiente de aprobación');
    await expect(ayudaDe(page, 'Imprimir QR')).toBeVisible();

    await page.goto('bienes/alta-masiva');
    await ayudaDe(page, 'Código de lote').click();
    await expect(page.locator('.ayuda-campo-globo:visible')).toContainText('CÓDIGO-001');
    await expect(page.getByText('Los campos marcados con')).toHaveCount(0);
});

test.describe('en el celular', () => {
    test.use({ viewport: { width: 390, height: 844 }, hasTouch: true, isMobile: true });

    test('un toque la abre, tocar fuera la cierra y el globo cabe en la pantalla', async ({ page }) => {
        await page.goto('bienes/crear');
        const boton = ayudaDe(page, 'Valor');
        const globo = await globoDe(page, boton);

        await boton.scrollIntoViewIfNeeded();
        await boton.tap();
        await expect(globo).toBeVisible();
        await expect(globo).toContainText('Escribe 0 si no lo conoces');
        const caja = await globo.boundingBox();
        expect(caja.x).toBeGreaterThanOrEqual(0);
        expect(caja.x + caja.width).toBeLessThanOrEqual(390);

        await page.locator('h1').tap();
        await expect(globo).toBeHidden();

        // Otro toque en el mismo ícono también la cierra.
        await boton.tap();
        await expect(globo).toBeVisible();
        await boton.tap();
        await expect(globo).toBeHidden();
    });
});
