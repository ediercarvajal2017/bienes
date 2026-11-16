import { test, expect } from '@playwright/test';
import { datos, comoRol } from './helpers/datos.js';

/**
 * El menú Acciones de Bienes está también en cada pantalla a la que lleva, con la pantalla
 * actual marcada: se pasa de una acción a otra sin volver al listado.
 */
test.beforeEach(() => test.skip(datos().remoto === true, 'Necesita la base de pruebas local'));

const PANTALLAS = [
    ['bienes/alta-masiva', 'Alta masiva idéntica'],
    ['cargas-masivas', 'Carga masiva de bienes'],
    ['asignaciones', 'Asignar bienes'],
    ['reintegros', 'Reintegrar bienes'],
    ['reintegros/unidades', 'Reintegrar por cantidad'],
    ['bienes/qr-masivo', 'Generar QR masivo'],
    ['bienes/buscar-por-foto', 'Buscar por foto'],
];

test('cada pantalla de Acciones trae el mismo menú, con la actual marcada', async ({ browser }) => {
    const pagina = await (await comoRol(browser, 'rector')).newPage();
    for (const [ruta, actual] of PANTALLAS) {
        await pagina.goto(ruta);
        const menu = pagina.locator('.menu-acciones .dropdown-menu');
        await pagina.locator('#botonAccionesMasivas').click();
        await expect(menu, ruta).toBeVisible();
        await expect(menu.locator('.dropdown-item'), ruta).toHaveCount(PANTALLAS.length);
        await expect(menu.locator('[aria-current="page"]'), ruta).toHaveText(actual);
        await pagina.keyboard.press('Escape');
        await expect(menu, ruta).toBeHidden();
    }
    await pagina.context().close();
});

test('se pasa de una acción a otra sin volver a Bienes', async ({ browser }) => {
    const pagina = await (await comoRol(browser, 'rector')).newPage();
    await pagina.goto('bienes/alta-masiva');

    await pagina.locator('#botonAccionesMasivas').click();
    await pagina.locator('.menu-acciones').getByRole('link', { name: 'Generar QR masivo' }).click();
    await expect(pagina).toHaveURL(/\/bienes\/qr-masivo$/);

    await pagina.locator('#botonAccionesMasivas').click();
    await pagina.locator('.menu-acciones').getByRole('link', { name: 'Asignar bienes' }).click();
    await expect(pagina).toHaveURL(/\/asignaciones$/);

    // El listado no marca ninguna opción: no es una de ellas.
    await pagina.goto('bienes');
    await pagina.locator('#botonAccionesMasivas').click();
    await expect(pagina.locator('.menu-acciones [aria-current="page"]')).toHaveCount(0);
    await pagina.context().close();
});

test('en el celular el menú de una pantalla de destino cabe', async ({ browser }) => {
    const contexto = await comoRol(browser, 'rector');
    const pagina = await contexto.newPage();
    await pagina.setViewportSize({ width: 375, height: 800 });
    await pagina.goto('cargas-masivas');
    await pagina.locator('#botonAccionesMasivas').click();
    const caja = await pagina.locator('.menu-acciones .dropdown-menu').boundingBox();
    expect(caja.x).toBeGreaterThanOrEqual(0);
    expect(caja.x + caja.width).toBeLessThanOrEqual(375);
    await contexto.close();
});

test('el docente en Buscar por foto no ve un menú sin otras acciones', async ({ browser }) => {
    const pagina = await (await comoRol(browser, 'docente')).newPage();
    await pagina.goto('bienes/buscar-por-foto');
    await expect(pagina.getByRole('heading', { name: 'Buscar por foto' })).toBeVisible();
    await expect(pagina.locator('#botonAccionesMasivas')).toHaveCount(0);
    await pagina.context().close();
});
