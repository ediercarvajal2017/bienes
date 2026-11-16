import { test, expect } from '@playwright/test';
import { datos, bd, comoRol } from './helpers/datos.js';

/**
 * Listado de Bienes sin elementos sueltos: los filtros quedan plegados tras el botón Filtros,
 * lo aplicado se ve como etiquetas que se quitan con un clic, el filtro Estado dice cuántos
 * bienes hay en cada estado (reemplaza los botones "Bodega Reintegro" y "Bodega de Baja") y
 * la descarga en Excel vive en el menú Acciones.
 */
test.beforeEach(() => test.skip(datos().remoto === true, 'Necesita la base de pruebas local'));

/** Dos bienes con un código propio: uno activo y uno reintegrado. */
function prepararBienes() {
    const s = Date.now();
    const inst = datos().instituciones.A;
    for (const [etiqueta, estado] of [['A', 'activo'], ['R', 'reintegrado']]) {
        bd(`INSERT INTO bienes (institucion_id, codigo_identificacion, descripcion, categoria_id, fecha_ingreso, valor, estado, qr_token)
            SELECT ${inst}, 'PWL-${s}-${etiqueta}', 'PW listado ${etiqueta}', id, CURDATE(), 1000, '${estado}', UUID()
              FROM categorias_bienes WHERE institucion_id = ${inst} AND nombre = 'Muebles' LIMIT 1`);
    }
    return s;
}

test('sin filtros: ni bodegas, ni selectores, ni descarga a la vista', async ({ browser }) => {
    const pagina = await (await comoRol(browser, 'rector')).newPage();
    await pagina.goto('bienes');

    await expect(pagina.getByText(/Bodega Reintegro|Bodega de Baja/)).toHaveCount(0);
    await expect(pagina.locator('#filtroEstado')).toBeHidden();
    await expect(pagina.locator('#botonFiltrosBienes')).toHaveText('Filtros');
    await expect(pagina.locator('#botonFiltrosBienes')).toHaveAttribute('aria-expanded', 'false');
    await expect(pagina.locator('.filtros-activos')).toHaveCount(0);
    await expect(pagina.locator('#descargarCarteraFiltrada')).toBeHidden();

    // La descarga está en Acciones > Exportar; sin filtros no dice nada más.
    await pagina.locator('#botonAccionesMasivas').click();
    await expect(pagina.locator('#descargarCarteraFiltrada')).toBeVisible();
    await expect(pagina.locator('#descargarCarteraFiltrada')).toHaveText('Descargar cartera en Excel');
    // Sin títulos de grupo: solo las opciones.
    await expect(pagina.locator('.menu-acciones .dropdown-header')).toHaveCount(0);
    await expect(pagina.locator('.menu-acciones .dropdown-item')).toHaveText([
        'Alta masiva idéntica', 'Carga masiva de bienes', 'Asignar bienes', 'Reintegrar bienes', 'Reintegrar por cantidad',
        'Generar QR masivo', 'Buscar por foto', 'Descargar cartera en Excel',
    ]);
    await pagina.context().close();
});

test('filtrar por Estado: conteos, etiqueta que se quita y panel que sigue abierto', async ({ browser }) => {
    const s = prepararBienes();
    const pagina = await (await comoRol(browser, 'rector')).newPage();
    await pagina.goto(`bienes?q=PWL-${s}`);

    // Los conteos del filtro Estado respetan la búsqueda.
    await pagina.locator('#botonFiltrosBienes').click();
    await expect(pagina.locator('#botonFiltrosBienes')).toHaveAttribute('aria-expanded', 'true');
    const estado = pagina.locator('#filtroEstado');
    await expect(estado.locator('option[value="activo"]')).toHaveText('Activo (1)');
    await expect(estado.locator('option[value="reintegrado"]')).toHaveText('Reintegrado (1)');
    await expect(estado.locator('option[value="dado_de_baja"]')).toHaveText('Dado de baja (0)');

    await estado.selectOption('reintegrado');
    await expect(pagina).toHaveURL(/estado=reintegrado/);
    await expect(pagina.locator('tbody td[data-label="Código"]')).toHaveText([`PWL-${s}-R`]);
    await expect(pagina.locator('#botonFiltrosBienes')).toHaveText('Filtros (1)');
    // El panel sigue como el usuario lo dejó: abierto.
    await expect(estado).toBeVisible();

    // La descarga lleva los mismos filtros y lo dice.
    await pagina.locator('#botonAccionesMasivas').click();
    const descarga = pagina.locator('#descargarCarteraFiltrada');
    await expect(descarga).toContainText('Con la búsqueda y el filtro de la lista');
    expect(await descarga.getAttribute('href')).toMatch(/estado=reintegrado/);
    await pagina.keyboard.press('Escape');

    // La etiqueta quita solo ese filtro; "Quitar filtros", todo.
    await pagina.getByRole('link', { name: 'Quitar filtro Estado: Reintegrado' }).click();
    await expect(pagina).not.toHaveURL(/estado=/);
    await expect(pagina).toHaveURL(new RegExp(`q=PWL-${s}`));
    await pagina.locator('#quitarFiltrosBienes').click();
    await expect(pagina).toHaveURL(/\/bienes$/);

    // Cerrado a mano, sigue cerrado al recargar.
    await pagina.locator('#botonFiltrosBienes').click();
    await expect(estado).toBeHidden();
    await pagina.reload();
    await expect(estado).toBeHidden();
    await pagina.context().close();
});

test('el menú Acciones cabe en la pantalla en escritorio y en el celular', async ({ browser }) => {
    for (const ancho of [1440, 375]) {
        const contexto = await comoRol(browser, 'rector');
        const pagina = await contexto.newPage();
        await pagina.setViewportSize({ width: ancho, height: 900 });
        await pagina.goto('bienes');
        await pagina.locator('#botonAccionesMasivas').click();
        const caja = await pagina.locator('.menu-acciones .dropdown-menu').boundingBox();
        expect(caja.x, `menú a ${ancho} px`).toBeGreaterThanOrEqual(0);
        expect(caja.x + caja.width, `menú a ${ancho} px`).toBeLessThanOrEqual(ancho);
        await contexto.close();
    }
});

test('el docente: menú sin acciones de gestión y Estado sin conteos', async ({ browser }) => {
    const pagina = await (await comoRol(browser, 'docente')).newPage();
    await pagina.goto('bienes');

    await pagina.locator('#botonAccionesMasivas').click();
    const menu = pagina.locator('.menu-acciones .dropdown-menu');
    await expect(menu.getByRole('link', { name: 'Buscar por foto' })).toBeVisible();
    await expect(menu.getByRole('link', { name: /Alta masiva|Asignar bienes|Generar QR/ })).toHaveCount(0);
    await pagina.keyboard.press('Escape');
    await expect(menu).toBeHidden();

    await pagina.locator('#botonFiltrosBienes').click();
    await expect(pagina.locator('#filtroEstado option[value="reintegrado"]')).toHaveText('Reintegrado');
    await pagina.context().close();
});
