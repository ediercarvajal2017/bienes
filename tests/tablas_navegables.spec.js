import { test, expect } from '@playwright/test';
import { datos, bd, comoRol } from './helpers/datos.js';

/**
 * Todas las tablas de listado de MIA se recorren con el teclado como la de Bienes
 * (data-navegable, tabla-navegable.js), con «Vista compacta» y la ayuda de las teclas.
 */
test.beforeEach(() => test.skip(datos().remoto === true, 'Necesita la base de pruebas local'));
test.use({ viewport: { width: 1280, height: 800 } });

const PANTALLAS = {
    rector: ['asignaciones', 'reintegros', 'reintegros/lotes', 'reintegros/lotes/generar', 'reintegros/unidades', 'reintegros/solicitudes',
        'bajas', 'bienes/qr-masivo', 'bienes/qr-masivo/bodega', 'espacios', 'usuarios', 'categorias', 'verificaciones',
        'cargas-masivas', 'espacios/carga-masiva', 'usuarios/carga-masiva'],
    superusuario: ['cargos', 'instituciones', 'papelera', 'auditoria'],
};

for (const [rol, rutas] of Object.entries(PANTALLAS)) {
    test(`${rol}: cada tabla con filas se recorre con las flechas`, async ({ browser }) => {
        const pagina = await (await comoRol(browser, rol)).newPage();
        await pagina.setViewportSize({ width: 1280, height: 800 });
        let probadas = 0;
        for (const ruta of rutas) {
            await pagina.goto(ruta);
            // Algunas pantallas no pintan la tabla cuando no hay nada (Lotes, Papelera).
            if (await pagina.locator('[data-navegable] table').count() === 0) { continue; }
            const tabla = pagina.locator('[data-navegable] table').first();
            await expect(tabla, ruta).toHaveAttribute('role', 'grid');
            // Las filas de datos (sin la del «no hay resultados», que es una sola celda).
            const filas = tabla.locator(':scope > tbody > tr:not(:has(> td[colspan]))');
            if (await filas.count() < 2) { continue; }
            const columna = (await filas.nth(0).locator(':scope > td').count()) > 2 ? 2 : 1;
            await filas.nth(0).locator(':scope > td').nth(columna).click({ position: { x: 3, y: 3 } });
            await expect(filas.nth(0).locator(':scope > td').nth(columna), ruta).toBeFocused();
            await pagina.keyboard.press('ArrowDown');
            await expect(filas.nth(1).locator(':scope > td').nth(columna), ruta).toBeFocused();
            await expect(pagina.getByRole('button', { name: 'Vista compacta' }).first(), ruta).toBeVisible();
            await expect(pagina.getByRole('button', { name: 'Ayuda: Moverse por la tabla con el teclado' }).first(), ruta).toBeVisible();
            probadas++;
        }
        expect(probadas, 'al menos una tabla con datos').toBeGreaterThan(0);
        await pagina.context().close();
    });
}

test('en una tabla para seleccionar, Enter y Espacio marcan la casilla de la fila', async ({ browser }) => {
    const pagina = await (await comoRol(browser, 'rector')).newPage();
    await pagina.setViewportSize({ width: 1280, height: 800 });
    await pagina.goto('bienes/qr-masivo');
    const fila = pagina.locator('[data-navegable] tbody tr').filter({ has: pagina.locator('input.casilla-bien') }).first();
    const casilla = fila.locator('input.casilla-bien');
    await expect(casilla).not.toBeChecked();
    await fila.locator('td').nth(2).click({ position: { x: 3, y: 3 } });
    await expect(fila.locator('td').nth(2)).toBeFocused();
    await pagina.keyboard.press('Enter');
    await expect(casilla, 'Enter marca').toBeChecked();
    await expect(fila.locator('td').nth(2), 'el cursor sigue en la celda').toBeFocused();
    await pagina.keyboard.press(' ');
    await expect(casilla, 'Espacio desmarca').not.toBeChecked();
    await pagina.context().close();
});

test('en Categorías las flechas mueven el cursor del texto mientras se renombra (no cambian de celda)', async ({ browser }) => {
    const pagina = await (await comoRol(browser, 'rector')).newPage();
    await pagina.setViewportSize({ width: 1280, height: 800 });
    await pagina.goto('categorias');
    const celda = pagina.locator('[data-navegable] tbody td', { has: pagina.locator('input[name="nombre"]') }).first();
    const campo = celda.locator('input[name="nombre"]');
    await celda.click({ position: { x: 2, y: 2 } });
    await expect(celda).toBeFocused();
    await pagina.keyboard.press('Enter');
    await expect(campo, 'Enter entra al campo').toBeFocused();
    await pagina.keyboard.press('End');
    await pagina.keyboard.press('ArrowLeft');
    await expect(campo, 'la flecha mueve el cursor dentro del texto').toBeFocused();
    await pagina.keyboard.press('Escape');
    await expect(celda, 'Escape vuelve a la celda').toBeFocused();
    await pagina.context().close();
});

test('en Usuarios lo buscado se marca y Enter abre el usuario', async ({ browser }) => {
    const d = datos();
    const pagina = await (await comoRol(browser, 'rector')).newPage();
    await pagina.setViewportSize({ width: 1280, height: 800 });
    const documento = bd(`SELECT documento FROM usuarios WHERE id = ${d.usuarios.docente.id}`);
    await pagina.goto(`usuarios?q=${documento}`);
    const fila = pagina.locator('[data-navegable] tbody tr', { hasText: documento }).first();
    await expect(fila.locator('mark.coincidencia')).toHaveText(documento);
    await fila.locator('td[data-label="Documento"]').click();
    await pagina.keyboard.press('Enter');
    await expect(pagina).toHaveURL(new RegExp(`/usuarios/${d.usuarios.docente.id}/editar`));
    await pagina.context().close();
});

test.describe('en el celular', () => {
    test.use({ viewport: { width: 390, height: 844 } });

    test('las tablas siguen como tarjetas, sin herramientas de teclado', async ({ browser }) => {
        const pagina = await (await comoRol(browser, 'rector')).newPage();
        await pagina.setViewportSize({ width: 390, height: 844 });
        for (const ruta of ['usuarios', 'espacios', 'reintegros']) {
            await pagina.goto(ruta);
            await expect(pagina.locator('[data-navegable] table').first(), ruta).not.toHaveAttribute('role', 'grid');
            await expect(pagina.getByRole('button', { name: 'Vista compacta' }).first(), ruta).toBeHidden();
        }
        await pagina.context().close();
    });
});
