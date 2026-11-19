import { test, expect } from '@playwright/test';
import { datos, bd } from './helpers/datos.js';

/**
 * Tabla de Bienes como una planilla (ui('tabla', ['navegable' => true]), tabla-navegable.js):
 * se recorre con el teclado, Foto/Descripción/Código quedan inmovilizadas, hay vista
 * compacta y la celda del cursor se recuerda al recargar. En el celular siguen las tarjetas.
 */
test.beforeEach(() => test.skip(datos().remoto === true, 'Necesita la base de pruebas local'));
test.use({ storageState: 'playwright/.auth/rector.json', viewport: { width: 1280, height: 800 } });

/** n bienes de prueba con el mismo prefijo: buscándolo, la tabla trae solo esos (2 páginas de 10). */
function crearBienes(prefijo, n) {
    const inst = datos().instituciones.A;
    const filas = Array.from({ length: n }, (_, i) =>
        `(${inst}, '${prefijo}-${String(i + 1).padStart(2, '0')}', 'PW TABLA ${prefijo} ${i + 1}', CURDATE(), ${1000 * (i + 1)}, 'activo', UUID())`);
    bd(`INSERT INTO bienes (institucion_id, codigo_identificacion, descripcion, fecha_ingreso, valor, estado, qr_token) VALUES ${filas.join(',')}`);
}

async function abrirTabla(page, prefijo) {
    await page.goto(`bienes?q=${prefijo}&porPagina=10`);
    await expect(page.locator('.tabla-datos table')).toHaveAttribute('role', 'grid');
}

const filas = (page) => page.locator('.tabla-datos tbody tr');
const celda = (page, f, c) => filas(page).nth(f).locator('td').nth(c);

test('las flechas, Inicio/Fin y Ctrl+Inicio/Fin mueven el cursor por las celdas; Tab sale de la tabla', async ({ page }) => {
    const p = `PWN${Date.now()}`;
    crearBienes(p, 12);
    await abrirTabla(page, p);
    await expect(filas(page)).toHaveCount(10);
    await expect(page.locator('.tabla-datos table')).toHaveAttribute('aria-rowcount', '13');
    await expect(filas(page).nth(0)).toHaveAttribute('aria-rowindex', '2');

    await celda(page, 0, 1).click();
    await expect(celda(page, 0, 1)).toBeFocused();
    await page.keyboard.press('ArrowDown');
    await expect(celda(page, 1, 1)).toBeFocused();
    await expect(filas(page).nth(1)).toHaveClass(/fila-activa/);
    await page.keyboard.press('ArrowRight');
    await expect(celda(page, 1, 2)).toBeFocused();
    await page.keyboard.press('End');
    await expect(celda(page, 1, 8)).toBeFocused();
    await page.keyboard.press('Home');
    await expect(celda(page, 1, 0)).toBeFocused();
    await page.keyboard.press('Control+End');
    await expect(celda(page, 9, 8)).toBeFocused();
    await page.keyboard.press('Control+Home');
    await expect(celda(page, 0, 0)).toBeFocused();
    await expect(page.locator('.tabla-datos tbody [tabindex="0"]'), 'una sola celda enfocable a la vez').toHaveCount(1);

    await page.keyboard.press('Tab');
    expect(await page.evaluate(() => Boolean(document.activeElement.closest('.tabla-datos'))), 'Tab sale de la tabla').toBe(false);
});

test('Av Pág desde la última fila pasa a la página siguiente; Enter abre el bien y al volver queda en su fila', async ({ page }) => {
    const p = `PWP${Date.now()}`;
    crearBienes(p, 12);
    await abrirTabla(page, p);

    await celda(page, 9, 1).click();
    await page.keyboard.press('PageDown');
    await expect(page).toHaveURL(/pagina=2/);
    await expect(filas(page)).toHaveCount(2);
    await expect(celda(page, 0, 1)).toBeFocused();
    await page.keyboard.press('PageUp');
    await expect(page).toHaveURL(/pagina=1/);
    await expect(celda(page, 9, 1)).toBeFocused();

    const id = await filas(page).nth(9).getAttribute('data-id');
    await page.keyboard.press('Enter');
    await expect(page).toHaveURL(new RegExp(`/bienes/${id}/editar`));

    // Al guardar se vuelve al listado con ?editado=ID: el cursor queda en ese bien.
    await page.goto(`bienes?q=${p}&porPagina=10&editado=${id}`);
    await expect(page.locator(`tr[data-id="${id}"] td`).nth(1)).toBeFocused();
});

test('al recargar, el cursor sigue en la misma celda', async ({ page }) => {
    const p = `PWR${Date.now()}`;
    crearBienes(p, 6);
    await abrirTabla(page, p);
    await celda(page, 3, 4).click();
    await page.reload();
    await expect(page.locator('.tabla-datos table')).toHaveAttribute('role', 'grid');
    await expect(celda(page, 3, 4)).toHaveAttribute('tabindex', '0');
    await expect(filas(page).nth(3)).toHaveClass(/fila-activa/);
});

test('«/» lleva al buscador y Escape vuelve a la tabla sin borrar la búsqueda', async ({ page }) => {
    const p = `PWB${Date.now()}`;
    crearBienes(p, 4);
    await abrirTabla(page, p);
    await celda(page, 2, 1).click();
    await page.keyboard.press('/');
    await expect(page.locator('#buscador')).toBeFocused();
    await page.keyboard.press('Escape');
    await expect(celda(page, 2, 1)).toBeFocused();
    await expect(page.locator('#buscador')).toHaveValue(p);
    await expect(page).toHaveURL(new RegExp(`q=${p}`));
});

test('Foto, Descripción y Código quedan inmovilizadas al desplazarse a la derecha', async ({ page }) => {
    const p = `PWF${Date.now()}`;
    crearBienes(p, 3);
    await abrirTabla(page, p);
    // Una columna muy ancha obliga a desplazarse a la derecha, como con una pantalla angosta.
    await page.evaluate(() => {
        document.querySelector('.tabla-datos thead th:nth-child(5)').style.minWidth = '1400px';
        window.dispatchEvent(new Event('resize'));
    });
    const contenedor = page.locator('.tabla-datos');
    await expect(contenedor).toHaveClass(/tabla-fijas-activas/);
    const antes = { descripcion: (await celda(page, 0, 1).boundingBox()).x, categoria: (await celda(page, 0, 3).boundingBox()).x };
    await contenedor.evaluate((c) => { c.scrollLeft = 600; });
    expect((await celda(page, 0, 1).boundingBox()).x, 'Descripción no se mueve').toBeCloseTo(antes.descripcion, 0);
    expect((await celda(page, 0, 3).boundingBox()).x, 'Categoría sí se desplaza').toBeLessThan(antes.categoria - 300);
});

test('la vista compacta muestra filas más bajas y se recuerda al recargar', async ({ page }) => {
    const p = `PWC${Date.now()}`;
    crearBienes(p, 4);
    await abrirTabla(page, p);
    const alto = async () => (await filas(page).nth(0).boundingBox()).height;
    const normal = await alto();
    const boton = page.getByRole('button', { name: 'Vista compacta' }).first();
    await expect(boton).toHaveAttribute('aria-pressed', 'false');
    await boton.click();
    await expect(boton).toHaveAttribute('aria-pressed', 'true');
    expect(await alto()).toBeLessThan(normal);
    await page.reload();
    await expect(page.locator('.tabla-datos')).toHaveClass(/tabla-compacta/);
    await expect(page.getByRole('button', { name: 'Vista compacta' }).first()).toHaveAttribute('aria-pressed', 'true');
});

test('la ayuda junto al título explica las teclas', async ({ page }) => {
    await page.goto('bienes');
    await page.getByRole('button', { name: 'Ayuda: Moverse por la tabla' }).hover();
    await expect(page.locator('.ayuda-campo-globo:visible')).toContainText('Av Pág / Re Pág:');
});

test.describe('en el celular', () => {
    test.use({ viewport: { width: 390, height: 844 } });

    test('siguen las tarjetas y las teclas no cambian nada', async ({ page }) => {
        const p = `PWM${Date.now()}`;
        crearBienes(p, 3);
        await page.goto(`bienes?q=${p}`);
        await expect(page.locator('.tabla-datos tbody tr')).toHaveCount(3);
        await expect(page.locator('.tabla-datos table')).not.toHaveAttribute('role', 'grid');
        await expect(page.locator('.tabla-datos tbody td[tabindex]')).toHaveCount(0);
        await expect(page.locator('.tabla-datos thead')).toBeHidden();
        await expect(page.getByRole('button', { name: 'Vista compacta' }).first()).toBeHidden();
    });
});
