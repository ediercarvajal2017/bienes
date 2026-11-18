import { test, expect } from '@playwright/test';
import { datos, bd, csrf, sinSesion } from './helpers/datos.js';

// Crea y consulta datos directamente en la base de pruebas.
test.beforeEach(() => test.skip(datos().remoto === true, 'Necesita la base de pruebas local'));

/**
 * Fase 2 de los avisos guiados (v1.2.37): las demás pantallas también dicen qué pasó, por qué
 * y qué hacer — inicio de sesión, categorías, espacios, cargas masivas, escaneo y las páginas
 * de error. El formato y el diseño los cubre tests/avisos.spec.js.
 */
test.use({ storageState: 'playwright/.auth/rector.json' });

test('el inicio de sesión fallido guía hacia «¿Olvidaste tu contraseña?»', async ({ browser }) => {
    const contexto = await sinSesion(browser);
    const pagina = await contexto.newPage();
    await pagina.goto('login');
    await pagina.locator('input[name="email"]').fill('nadie-aqui@prueba.test');
    await pagina.locator('input[name="password"]').fill('clave-equivocada-123');
    await pagina.getByRole('button', { name: 'Ingresar' }).click();

    const aviso = pagina.locator('.alert-danger');
    await expect(aviso.locator('.aviso-titulo')).toHaveText('No se pudo ingresar');
    await expect(aviso).toContainText('Correo o contraseña incorrectos.');
    await expect(aviso).toContainText('Qué hacer:');
    await expect(aviso.getByRole('link', { name: '¿Olvidaste tu contraseña?' })).toHaveAttribute('href', /olvide-contrasena$/);
    await contexto.close();
});

test('una categoría repetida dice que ya existe y qué hacer', async ({ page }) => {
    await page.goto('categorias');
    const nombre = bd(`SELECT nombre FROM categorias_bienes WHERE institucion_id = ${datos().instituciones.A} AND nombre <> 'Sin cartera' AND activo = 1 LIMIT 1`);
    await page.locator('form[action$="/categorias"] input[name="nombre"]').fill(nombre);
    await page.locator('form[action$="/categorias"] button[type="submit"]').click();

    const aviso = page.locator('.avisos-pagina .alert-danger');
    await expect(aviso.locator('.aviso-titulo')).toHaveText('No se creó la categoría');
    await expect(aviso).toContainText(`La categoría «${nombre}» ya existe en esta institución.`);
    await expect(aviso).toContainText('si está inactiva, actívala');
});

test('un espacio con bienes no se elimina y el aviso lista los bienes y qué hacer', async ({ page }) => {
    const d = datos();
    await page.goto('espacios');
    const token = await csrf(page);
    await page.request.post(`espacios/${d.espacios.aulaA}/eliminar`, { form: { _csrf: token }, maxRedirects: 0 });
    await page.goto('espacios');

    const aviso = page.locator('.alert-danger[data-aviso-enfocar]');
    await expect(aviso.locator('.aviso-titulo')).toHaveText('No se eliminó el espacio');
    await expect(aviso.locator('.aviso-detalle')).toContainText('tiene');
    await expect(aviso.locator('ul a').first()).toHaveAttribute('href', /bienes\/\d+\/editar/);
    await expect(aviso.locator('.aviso-solucion')).toContainText('Qué hacer:');
    await expect(aviso).toBeFocused();
    expect(bd(`SELECT eliminado_en IS NULL FROM espacios WHERE id = ${d.espacios.aulaA}`)).toBe('1');
});

test('una carga masiva sin archivo ofrece descargar la plantilla', async ({ page }) => {
    await page.goto('cargas-masivas');
    const token = await csrf(page);
    await page.request.post('cargas-masivas', { multipart: { _csrf: token }, maxRedirects: 0 });
    await page.goto('cargas-masivas');

    const aviso = page.locator('.avisos-pagina .alert-danger');
    await expect(aviso.locator('.aviso-titulo')).toHaveText('No se leyó el archivo');
    await expect(aviso.getByRole('link', { name: 'Descargar la plantilla' })).toHaveAttribute('href', /cargas-masivas\/plantilla\.xlsx$/);
});

test('buscar por código un bien que no existe guía a buscarlo en Bienes', async ({ page }) => {
    await page.goto('escanear');
    await page.locator('#campo-codigo').fill('PW-NO-EXISTE-99');
    await page.locator('#campo-codigo').press('Enter');

    const aviso = page.locator('.avisos-pagina .alert-danger');
    await expect(aviso.locator('.aviso-titulo')).toHaveText('El código no corresponde a ningún bien');
    await expect(aviso).toContainText('No se encontró ningún bien con el código «PW-NO-EXISTE-99»');
    await expect(aviso.getByRole('link', { name: 'Buscar en Bienes' })).toHaveAttribute('href', /bienes\?q=PW-NO-EXISTE-99$/);
});

test('la página 404 explica por qué puede pasar y qué hacer', async ({ page }) => {
    await page.goto('bienes/999999999/editar');

    await expect(page.getByRole('heading', { name: 'Página no encontrada' })).toBeVisible();
    await expect(page.getByText('Puede pasar porque:')).toBeVisible();
    await expect(page.getByText('El registro se eliminó', { exact: false })).toBeVisible();
    await expect(page.getByText('Qué hacer:')).toBeVisible();
});
