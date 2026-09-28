import { test, expect } from '@playwright/test';
import { datos, bd, comoRol } from './helpers/datos.js';

/**
 * Menú de navegación renovado (partials/menu_definicion.php, menu_lateral.php,
 * menu_inferior.php y assets/js/menu.js): secciones por permiso, opción y sección activas,
 * contadores de pendientes, modo compacto recordado y barra inferior en el celular.
 */
test.beforeEach(() => test.skip(datos().remoto === true, 'Necesita la base de pruebas local'));
test.use({ storageState: 'playwright/.auth/rector.json' });

const opcion = (page, ruta) => page.locator(`#sidebar a.menu-opcion[href$="${ruta}"]`);

test('el rector ve las secciones del menú y "Carga masiva" ya no está en Administración', async ({ page }) => {
    await page.goto('dashboard');
    const titulos = await page.locator('#sidebar .menu-seccion-titulo').allInnerTexts();
    expect(titulos.map((t) => t.trim().toUpperCase())).toEqual(expect.arrayContaining(['INVENTARIO', 'REINTEGROS', 'CONTROL', 'DOCUMENTOS']));
    await expect(opcion(page, '/bienes')).toBeVisible();
    await expect(opcion(page, '/reintegros/solicitudes')).toBeVisible();
    await expect(page.locator('#sidebar a[href$="/cargas-masivas"]')).toHaveCount(0);
});

test('el docente solo ve lo que su rol permite', async ({ browser }) => {
    const docente = await (await comoRol(browser, 'docente')).newPage();
    await docente.goto('dashboard');
    for (const ruta of ['/usuarios', '/reportes', '/reintegros/lotes', '/categorias', '/auditoria']) {
        await expect(docente.locator(`#sidebar a[href$="${ruta}"]`), ruta).toHaveCount(0);
    }
    await expect(docente.locator('#sidebar a.menu-opcion[href$="/escanear"]')).toBeVisible();
    await docente.context().close();
});

test('en una página interna quedan marcadas la opción, su sección y la ruta de navegación', async ({ page }) => {
    await page.goto(`bienes/${datos().bienes.A.silla}/editar`);
    await expect(opcion(page, '/bienes')).toHaveAttribute('aria-current', 'page');
    await expect(page.locator('#sidebar details[data-seccion="inventario"] > summary')).toHaveClass(/activa/);
    await expect(page.locator('.breadcrumb')).toContainText('Inventario');
});

test('el contador de bajas coincide con las pendientes de la institución', async ({ page }) => {
    const pendientes = Number(bd(`SELECT COUNT(*) FROM bajas_bienes bb JOIN bienes b ON b.id = bb.bien_id
                                  WHERE bb.estado = 'pendiente' AND b.institucion_id = ${datos().instituciones.A}`));
    await page.goto('dashboard');
    const contador = opcion(page, '/bajas').locator('.menu-contador');
    if (pendientes === 0) {
        await expect(contador).toHaveCount(0);
    } else {
        await expect(contador).toContainText(String(pendientes));
    }
});

test('el modo compacto se recuerda y las secciones plegadas también', async ({ page }) => {
    await page.setViewportSize({ width: 1280, height: 800 });
    await page.goto('dashboard');

    await page.locator('#btnMenu').click();
    await expect(page.locator('html')).toHaveClass(/menu-compacto/);
    await page.reload();
    await expect(page.locator('html')).toHaveClass(/menu-compacto/);
    await expect(opcion(page, '/bienes')).toHaveAttribute('title', 'Bienes');
    await page.locator('#btnMenu').click();
    await expect(page.locator('html')).not.toHaveClass(/menu-compacto/);

    // Plegar "Documentos": sigue plegada al recargar, pero se abre sola si se entra a una de sus páginas.
    const documentos = page.locator('#sidebar details[data-seccion="documentos"]');
    await documentos.locator('summary').click();
    await expect(documentos).not.toHaveAttribute('open');
    await page.reload();
    await expect(documentos).not.toHaveAttribute('open');
    await page.goto('reportes');
    await expect(documentos).toHaveAttribute('open', '');
});

test('en el celular hay barra inferior y "Menú" abre y cierra el menú lateral', async ({ page }) => {
    await page.setViewportSize({ width: 390, height: 780 });
    await page.goto('bienes');

    const barra = page.getByRole('navigation', { name: 'Accesos rápidos' });
    await expect(barra).toBeVisible();
    await expect(barra.getByRole('link', { name: 'Bienes' })).toHaveAttribute('aria-current', 'page');

    const botonMenu = barra.getByRole('button', { name: /Menú/ });
    await botonMenu.click();
    await expect(page.locator('#sidebar')).toHaveClass(/abierto/);
    await expect(botonMenu).toHaveAttribute('aria-expanded', 'true');
    // El menú queda debajo de la barra superior, no encima.
    const arriba = await page.locator('#sidebar').evaluate((el) => el.getBoundingClientRect().top);
    expect(arriba).toBeGreaterThan(40);

    await page.keyboard.press('Escape');
    await expect(page.locator('#sidebar')).not.toHaveClass(/abierto/);
    await expect(botonMenu).toBeFocused();
});
