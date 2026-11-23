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

// Listados paginados: el orden lo hace la base de datos (App\Helpers\OrdenListado).
const ORDENABLES = {
    rector: ['asignaciones', 'reintegros', 'reintegros/lotes?estado=todos', 'reintegros/solicitudes', 'bajas', 'bienes/qr-masivo', 'espacios',
        'usuarios', 'verificaciones', 'cargas-masivas', 'espacios/carga-masiva', 'usuarios/carga-masiva'],
    superusuario: ['auditoria'],
};

for (const [rol, rutas] of Object.entries(ORDENABLES)) {
    test(`${rol}: cada título ordenable ordena el listado en los dos sentidos sin error`, async ({ browser }) => {
        // Recorre 12 pantallas y cada columna en los dos sentidos: con los datos de la corrida
        // completa no cabe en los 30 s por defecto.
        test.setTimeout(180_000);
        const pagina = await (await comoRol(browser, rol)).newPage();
        await pagina.setViewportSize({ width: 1280, height: 800 });
        let probados = 0;
        for (const ruta of rutas) {
            await pagina.goto(ruta);
            const titulos = pagina.locator('thead a.encabezado-orden');
            const cuantos = await titulos.count();
            if (cuantos === 0 && await pagina.locator('[data-navegable] table').count() === 0) { continue; } // pantalla sin tabla (vacía)
            expect(cuantos, `${ruta}: tiene títulos ordenables`).toBeGreaterThan(0);
            for (let i = 0; i < cuantos; i++) {
                const nombre = (await titulos.nth(i).getAttribute('aria-label')).split(',')[0];
                for (const sentido of ['ascending', 'descending']) {
                    const respuesta = await Promise.all([pagina.waitForNavigation(), pagina.locator('thead a.encabezado-orden').nth(i).click()]).then(([r]) => r);
                    expect(respuesta.status(), `${ruta} ordenado por ${nombre}`).toBeLessThan(400);
                    await expect(pagina.locator(`thead th[aria-sort="${sentido}"]`), `${ruta}: ${nombre} ${sentido}`).toContainText(nombre);
                    probados++;
                }
                await pagina.goto(ruta);
            }
        }
        expect(probados).toBeGreaterThan(0);
        await pagina.context().close();
    });
}

test('el orden elegido sigue al cambiar de página y se quita con su etiqueta', async ({ browser }) => {
    const pagina = await (await comoRol(browser, 'rector')).newPage();
    await pagina.setViewportSize({ width: 1280, height: 800 });
    await pagina.goto('usuarios?porPagina=10');
    await pagina.getByRole('link', { name: /^Nombre, ordenar/ }).click();
    await expect(pagina).toHaveURL(/orden=nombre&dir=asc/);
    const nombres = await pagina.locator('[data-navegable] tbody td[data-label="Nombre"]').allInnerTexts();
    const primeras = nombres.map((n) => n.split('\n')[0].trim());
    expect(primeras, 'de la A a la Z').toEqual([...primeras].sort((a, b) => a.localeCompare(b, 'es', { sensitivity: 'base' })));
    const siguiente = pagina.locator('a.page-link[aria-label="Página siguiente"]').first();
    if (await siguiente.count() > 0 && await siguiente.getAttribute('aria-disabled') !== 'true') {
        await expect(siguiente).toHaveAttribute('href', /orden=nombre/);
    }
    const etiqueta = pagina.locator('.filtro-activo', { hasText: 'Orden:' });
    await expect(etiqueta).toContainText('Nombre, de la A a la Z');
    await etiqueta.click();
    await expect(pagina).not.toHaveURL(/orden=/);
    await pagina.context().close();
});

test('las tablas sin paginación se ordenan en la pantalla y lo recuerdan al recargar', async ({ browser }) => {
    const pagina = await (await comoRol(browser, 'superusuario')).newPage();
    await pagina.setViewportSize({ width: 1280, height: 800 });
    await pagina.goto('instituciones');
    const boton = pagina.locator('thead th[data-ordenar] button', { hasText: 'Institución' });
    const nombres = async () => (await pagina.locator('[data-navegable] tbody td[data-label="Institución"]').allInnerTexts()).map((n) => n.split('\n')[0].trim());
    await boton.click();
    await expect(pagina.locator('thead th[aria-sort="ascending"]')).toContainText('Institución');
    const asc = await nombres();
    expect(asc).toEqual([...asc].sort((a, b) => a.localeCompare(b, 'es', { sensitivity: 'base', numeric: true })));
    await boton.click();
    await expect(pagina.locator('thead th[aria-sort="descending"]')).toContainText('Institución');
    expect(await nombres()).toEqual([...asc].reverse());
    await pagina.reload();
    await expect(pagina.locator('thead th[aria-sort="descending"]'), 'se recuerda al recargar').toContainText('Institución');
    await pagina.context().close();
});
