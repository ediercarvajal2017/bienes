import { test, expect } from '@playwright/test';
import { datos, bd } from './helpers/datos.js';
import { seleccionarTomSelect } from './helpers/tomSelect.js';

/**
 * La descripción del bien va en minúscula en "Sin cartera" y en mayúscula en cualquier otra
 * categoría (App\Models\Bien::descripcionSegunCategoria y assets/js/descripcion-categoria.js):
 * al crear, al editar, al cambiar de categoría y en el alta masiva. Sin categoría, no se toca.
 */
test.beforeEach(() => test.skip(datos().remoto === true, 'Necesita la base de pruebas local'));
test.use({ storageState: 'playwright/.auth/rector.json' });

const descripcionDe = (id) => bd(`SELECT descripcion FROM bienes WHERE id = ${id}`);

test('crear en "Sin cartera" la deja en minúscula, y cambiar de categoría la ajusta', async ({ page }) => {
    // Al volver a "Sin cartera" MIA pide confirmar el código nuevo: se acepta.
    page.on('dialog', (dialogo) => dialogo.accept());

    await page.goto('bienes/crear');
    await seleccionarTomSelect(page, 'categoriaBien', 'Sin cartera');
    await expect(page.locator('#codigoIdentificacion')).toHaveValue(/^\d{10}$/);
    const campo = page.locator('#campo-descripcion');
    await campo.fill('SILLA Azul Ñandú');
    // Mientras se escribe se VE en minúscula; al salir del campo el texto se convierte.
    await expect(campo).toHaveCSS('text-transform', 'lowercase');
    await campo.blur();
    await expect(campo).toHaveValue('silla azul ñandú');
    await page.getByRole('button', { name: 'Registrar bien' }).click();
    await expect(page).toHaveURL(/\/bienes\/\d+\/editar/);
    const id = Number(page.url().match(/bienes\/(\d+)\/editar/)[1]);
    expect(descripcionDe(id)).toBe('silla azul ñandú');

    // A "Tecnología": mayúscula (también en el campo, apenas se elige la categoría).
    await seleccionarTomSelect(page, 'categoriaBien', 'Tecnología');
    await expect(campo).toHaveValue('SILLA AZUL ÑANDÚ');
    await page.locator('#botonGuardarBien').click();
    await expect(page.locator('.alert-success')).toContainText('Bien actualizado');
    expect(descripcionDe(id)).toBe('SILLA AZUL ÑANDÚ');

    // De vuelta a "Sin cartera": minúscula otra vez.
    await seleccionarTomSelect(page, 'categoriaBien', 'Sin cartera');
    await expect(campo).toHaveValue('silla azul ñandú');
    await page.locator('#botonGuardarBien').click();
    await expect(page.locator('.alert-success')).toContainText('Bien actualizado');
    expect(descripcionDe(id)).toBe('silla azul ñandú');
});

test('el servidor aplica la regla aunque el navegador no la aplique, y sin categoría no toca nada', async ({ page }) => {
    await page.goto('bienes/crear');
    const token = await page.locator('input[name="_csrf"]').first().inputValue();
    const idTecnologia = bd(`SELECT id FROM categorias_bienes WHERE institucion_id = ${datos().instituciones.A} AND nombre LIKE 'Tecnolog%'`);
    const marca = Date.now();

    // Petición directa (sin el JavaScript del formulario): en "Tecnología" se guarda en mayúscula.
    await page.request.post('bienes', {
        form: { _csrf: token, codigo_identificacion: `PW-DESC-${marca}`, descripcion: 'proyector epson', categoria_id: idTecnologia,
            fecha_ingreso: '2026-01-15', valor: '1000', estado: 'activo' },
        maxRedirects: 0,
    });
    expect(bd(`SELECT descripcion FROM bienes WHERE codigo_identificacion = 'PW-DESC-${marca}'`)).toBe('PROYECTOR EPSON');

    // Sin categoría: queda como se escribió.
    await page.request.post('bienes', {
        form: { _csrf: token, codigo_identificacion: `PW-DESC2-${marca}`, descripcion: 'Proyector Epson', categoria_id: '',
            fecha_ingreso: '2026-01-15', valor: '1000', estado: 'activo' },
        maxRedirects: 0,
    });
    expect(bd(`SELECT descripcion FROM bienes WHERE codigo_identificacion = 'PW-DESC2-${marca}'`)).toBe('Proyector Epson');
});

test('el alta masiva sigue la misma regla', async ({ page }) => {
    const lote = `PWDESCLOTE${Date.now()}`;
    await page.goto('bienes/alta-masiva');
    await page.locator('input[name="lote"]').fill(lote);
    await page.locator('input[name="cantidad"]').fill('2');
    await seleccionarTomSelect(page, 'categoriaLote', 'Tecnología');
    await page.locator('#campo-descripcion').fill('computador portátil');
    await page.getByRole('button', { name: 'Crear bienes del lote' }).click();
    await expect(page).toHaveURL(/\/bienes$/);
    expect(bd(`SELECT DISTINCT descripcion FROM bienes WHERE lote = '${lote}'`)).toBe('COMPUTADOR PORTÁTIL');
});
