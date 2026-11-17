import { test, expect } from '@playwright/test';
import AxeBuilder from '@axe-core/playwright';
import { datos, comoRol, bd, csrf } from './helpers/datos.js';

// Crea y consulta datos directamente en la base de pruebas.
test.beforeEach(() => test.skip(datos().remoto === true, 'Necesita la base de pruebas local'));

/**
 * Cada acción dice qué pasó, por qué y qué hacer (App\Core\Aviso, docs/guia-estilo-ui.md
 * "Mensajes"): el aviso de tres partes con su botón, los campos marcados, la validación del
 * navegador en español, los avisos que no se pierden ni se repiten, lo que no aparece en la
 * ficha y las búsquedas vacías que explican. Todo con el verde MIA.
 */
test.use({ storageState: 'playwright/.auth/rector.json' });

const hoy = () => new Date().toISOString().slice(0, 10);
const codigoDe = (bienId) => bd(`SELECT codigo_identificacion FROM bienes WHERE id = ${bienId}`);

test('una placa repetida: aviso de tres partes, el campo en rojo, los datos se conservan y el botón abre el bien', async ({ page }) => {
    const d = datos();
    const placa = codigoDe(d.bienes.A.silla);

    await page.goto('bienes/crear');
    await page.locator('#codigoIdentificacion').fill(placa);
    await page.locator('#campo-descripcion').fill('PW-AVISO silla repetida');
    await page.locator('#botonGuardarBien').click();

    const aviso = page.locator('.avisos-pagina .alert-danger');
    await expect(aviso.locator('.aviso-titulo')).toHaveText('No se registró el bien');
    await expect(aviso).toContainText(`La placa ${placa} ya está registrada en esta institución.`);
    await expect(aviso.locator('.aviso-solucion')).toContainText('Qué hacer: Revisa el número.');
    await expect(aviso, 'el error recibe el foco: el lector de pantalla lo anuncia').toBeFocused();

    await expect(page.locator('#codigoIdentificacion')).toHaveClass(/is-invalid/);
    await expect(page.locator('#codigo_identificacion-error')).toHaveText('Esta placa ya existe.');
    await expect(page.locator('#campo-descripcion'), 'lo escrito no se pierde').toHaveValue(/PW-AVISO silla repetida/i);

    await aviso.getByRole('link', { name: `Ver el bien ${placa}` }).click();
    await expect(page).toHaveURL(new RegExp(`bienes/${d.bienes.A.silla}/editar`));
    await expect(page.locator('.avisos-pagina'), 'el aviso no se repite en la página siguiente').toHaveCount(0);
});

test('el formulario vencido dice que no se guardó y conserva lo escrito', async ({ page }) => {
    await page.goto('bienes/crear');
    await page.locator('#codigoIdentificacion').fill(`PW-AVISO-CSRF-${Date.now()}`);
    await page.locator('#campo-descripcion').fill('PW-AVISO formulario vencido');
    await page.locator('#datosBien input[name="_csrf"]').evaluate((campo) => { campo.value = 'vencido'; });
    await page.locator('#botonGuardarBien').click();

    const aviso = page.locator('.avisos-pagina .alert-danger');
    await expect(aviso.locator('.aviso-titulo')).toHaveText('No se guardó ningún cambio');
    await expect(aviso).toContainText('Tus datos siguen en el formulario');
    await expect(page.locator('#campo-descripcion')).toHaveValue(/PW-AVISO formulario vencido/i);
});

test('el navegador no envía un formulario incompleto y lo explica en español', async ({ page }) => {
    let envios = 0;
    page.on('request', (peticion) => { if (peticion.method() === 'POST') { envios++; } });

    await page.goto('bienes/crear');
    await page.locator('#botonGuardarBien').click();

    const resumen = page.locator('#datosBien > .resumen-validacion');
    await expect(resumen).toContainText('No se envió: hay 2 datos por corregir');
    await expect(resumen).toContainText('Código de identificación, Descripción.');
    await expect(resumen).toContainText('vuelve a pulsar «Registrar bien»');
    const codigo = page.locator('#codigoIdentificacion');
    await expect(codigo).toHaveClass(/is-invalid/);
    await expect(codigo).toHaveAttribute('aria-invalid', 'true');
    await expect(page.locator('#codigoIdentificacion-validacion')).toHaveText('Completa «Código de identificación»: es obligatorio.');
    await expect(codigo, 'el foco va al primer dato por corregir').toBeFocused();
    expect(envios, 'no se envió nada al servidor').toBe(0);

    // Al corregir el campo, su mensaje se va; al completar todo, también el resumen.
    await codigo.fill('PW-AVISO-NAV');
    await expect(page.locator('#codigoIdentificacion-validacion')).toHaveCount(0);
    await page.locator('#campo-descripcion').fill('PW-AVISO navegador');
    await expect(resumen).toHaveCount(0);
});

test('un aviso se muestra una sola vez, en la página a la que se vuelve', async ({ browser }) => {
    const d = datos();
    const contexto = await comoRol(browser, 'superusuario');
    const pagina = await contexto.newPage();
    await pagina.goto('buscar');
    await pagina.locator('#filtroInstitucionSelect').evaluate((select, id) => {
        select.value = String(id);
        select.form.submit();
    }, d.instituciones.A);

    await expect(pagina).toHaveURL(/\/buscar$/);
    await expect(pagina.locator('.avisos-pagina .alert-success')).toContainText('Los listados muestran solo');
    await pagina.goto('dashboard');
    await expect(pagina.locator('.avisos-pagina')).toHaveCount(0);

    await pagina.locator('#filtroInstitucionSelect').evaluate((select) => { select.value = ''; select.form.submit(); });
    await expect(pagina.locator('.avisos-pagina .alert-success')).toContainText('Ves de nuevo todas las instituciones');
    await contexto.close();
});

test('reintegrar sin marcar ningún bien guía hacia el botón', async ({ page }) => {
    await page.goto('reintegros');
    const token = await csrf(page);
    await page.request.post('reintegros', { form: { _csrf: token, destino_texto: 'Almacén', fecha: hoy() }, maxRedirects: 0 });
    await page.goto('reintegros');

    const aviso = page.locator('.avisos-pagina .alert-danger');
    await expect(aviso.locator('.aviso-titulo')).toHaveText('No se reintegró ningún bien');
    await expect(aviso).toContainText('No marcaste ningún bien.');
    await expect(aviso).toContainText('Marca la casilla de cada bien en la lista y pulsa «Reintegro».');
});

test('la búsqueda de Reintegrar bienes dice por qué no aparece un bien', async ({ page }) => {
    const d = datos();
    const libre = codigoDe(d.bienes.A.libre);
    expect(bd(`SELECT COUNT(*) FROM asignaciones WHERE bien_id = ${d.bienes.A.libre} AND activa = 1`), 'el bien de prueba no está asignado').toBe('0');

    await page.goto(`reintegros?q=${encodeURIComponent(libre)}`);
    const vacio = page.locator('.estado-vacio-explicado');
    await expect(vacio).toContainText(`El bien ${libre} no aparece porque el bien no tiene una asignación activa.`);
    await expect(vacio).toContainText('Asígnalo primero');
    await expect(vacio.getByRole('link', { name: 'Abrir el bien' })).toHaveAttribute('href', new RegExp(`bienes/${d.bienes.A.libre}/editar`));
});

test('la ficha de un bien en cartera explica por qué no puede reportarse para baja', async ({ page }) => {
    const d = datos();
    await page.goto(`bienes/${d.bienes.A.silla}/editar`);

    await expect(page.locator('#accionBien option[value="reportar_baja"]')).toHaveCount(0);
    const porQue = page.locator('#porQueNoAcciones');
    await porQue.locator('summary').click();
    await expect(porQue.locator('[data-no-disponible="reportar_baja"]')).toContainText('solo los bienes «Sin cartera» se dan de baja');
    await expect(porQue.locator('[data-no-disponible="reportar_baja"]')).toContainText('Qué hacer: Si ya no se usa, reintégralo');
});

test('Bienes sin resultados dice qué se buscó y cómo seguir', async ({ page }) => {
    await page.goto('bienes?q=PW-NO-EXISTE-XYZ&estado=reintegrado');

    const vacio = page.locator('.estado-vacio-explicado');
    await expect(vacio.locator('.estado-vacio-titulo')).toHaveText('Ningún bien coincide con la búsqueda.');
    await expect(vacio).toContainText('Buscaste «PW-NO-EXISTE-XYZ» con Estado: Reintegrado.');
    await vacio.getByRole('link', { name: 'Buscar sin filtros' }).click();
    await expect(page).toHaveURL(/bienes\?q=PW-NO-EXISTE-XYZ$/);
    await expect(page.locator('.estado-vacio-explicado').getByRole('link', { name: 'Buscar en todo MIA' })).toBeVisible();
});

test('los avisos usan el verde MIA; solo el error lleva la franja roja de MIA', async ({ page }) => {
    const d = datos();
    await page.goto('bienes/crear');
    await page.locator('#codigoIdentificacion').fill(codigoDe(d.bienes.A.silla));
    await page.locator('#campo-descripcion').fill('PW-AVISO colores');
    await page.locator('#botonGuardarBien').click();
    await expect(page.locator('.avisos-pagina .alert-danger')).toBeVisible();

    const colores = await page.evaluate(() => {
        const raiz = getComputedStyle(document.documentElement);
        const comoRgb = (variable) => {
            const prueba = document.createElement('span');
            prueba.style.color = raiz.getPropertyValue(variable).trim();
            document.body.appendChild(prueba);
            const color = getComputedStyle(prueba).color;
            prueba.remove();
            return color;
        };
        const alerta = document.querySelector('.avisos-pagina .alert-danger');
        return {
            franja: getComputedStyle(alerta).borderLeftColor, rojo: comoRgb('--sigebi-danger'),
            fondo: getComputedStyle(alerta).backgroundColor, superficie: comoRgb('--sigebi-surface'),
            titulo: getComputedStyle(alerta.querySelector('.aviso-titulo')).color, verde: comoRgb('--sigebi-primary'),
            boton: getComputedStyle(alerta.querySelector('.aviso-acciones .btn')).backgroundColor, relleno: comoRgb('--sigebi-primary-relleno'),
            campo: getComputedStyle(document.querySelector('#codigoIdentificacion')).borderColor,
        };
    });
    expect(colores.franja).toBe(colores.rojo);
    expect(colores.fondo, 'sin fondo de color: la superficie de MIA').toBe(colores.superficie);
    expect(colores.titulo).toBe(colores.verde);
    expect(colores.boton).toBe(colores.relleno);
    expect(colores.campo, 'el campo en rojo usa el rojo de MIA, no el de Bootstrap').toBe(colores.rojo);
});

for (const tema of ['light', 'dark']) {
    test(`el aviso y la validación cumplen accesibilidad en tema ${tema === 'light' ? 'claro' : 'oscuro'}`, async ({ page }) => {
        const d = datos();
        await page.addInitScript((t) => { try { localStorage.setItem('sigebi-theme', t); } catch (e) {} }, tema);
        await page.goto('bienes/crear');
        await page.locator('#codigoIdentificacion').fill(codigoDe(d.bienes.A.silla));
        await page.locator('#campo-descripcion').fill('PW-AVISO accesibilidad');
        await page.locator('#botonGuardarBien').click();
        await expect(page.locator('.avisos-pagina .alert-danger')).toBeVisible();
        // Además, el resumen de la validación del navegador.
        await page.locator('#campo-descripcion').fill('');
        await page.locator('#botonGuardarBien').click();
        await expect(page.locator('.resumen-validacion')).toBeVisible();

        const { violations } = await new AxeBuilder({ page })
            .include('.avisos-pagina').include('.resumen-validacion').include('#codigoIdentificacion')
            .withTags(['wcag2a', 'wcag2aa']).analyze();
        expect(violations.map((v) => `${v.id}: ${v.nodes.map((n) => n.target).join(', ')}`)).toEqual([]);
    });
}
