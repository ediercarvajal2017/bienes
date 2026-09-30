import { test, expect } from '@playwright/test';
import path from 'node:path';
import { fileURLToPath } from 'node:url';
import { seleccionarPrimeraOpcionTomSelect, seleccionarTomSelect } from './helpers/tomSelect.js';
import { datos, bd, csrf } from './helpers/datos.js';

const __dirname = path.dirname(fileURLToPath(import.meta.url));
const excelPrueba = path.join(__dirname, 'fixtures', 'excel_prueba.xlsx');

/**
 * "Cartera recibida de la Alcaldía" (CarteraController): la institución la solicitó por
 * correo y la Alcaldía la envió; se registra quién la solicitó (usuario de la lista) y su
 * correo, desde qué correo llegó, la fecha en que se recibió y el archivo.
 */
const nombre = (id) => bd(`SELECT CONCAT(nombres, ' ', apellidos) FROM usuarios WHERE id = ${id}`);

test('el superusuario elige la institución antes de registrar', async ({ page }) => {
    await page.goto('cartera/enviar');
    await expect(page.getByRole('heading', { name: 'Cartera recibida de la Alcaldía' })).toBeVisible();
    await expect(page.getByText('que la institución solicitó por correo')).toBeVisible();
    await Promise.all([
        page.waitForURL(/institucion=\d+/),
        seleccionarPrimeraOpcionTomSelect(page, 'selectorInstitucion'),
    ]);
    await expect(page.locator('select[name="funcionario_id"]')).toHaveCount(1);
});

test('registrar, editar y eliminar una cartera recibida', async ({ page }) => {
    test.skip(datos().remoto === true, 'Necesita la base de pruebas local');
    const d = datos();
    const correo = `alcaldia-${Date.now()}@example.com`;
    const correoEditado = `alcaldia-${Date.now()}-ed@example.com`;

    await page.goto(`cartera/enviar?institucion=${d.instituciones.A}`);

    // Al elegir el funcionario, su correo de MIA se llena solo.
    await seleccionarTomSelect(page, 'campo-funcionario', nombre(d.usuarios.docente.id));
    await expect(page.locator('input[name="correo_solicitante"]')).toHaveValue(d.usuarios.docente.email);
    await page.locator('input[name="correo_remitente"]').fill(correo);
    await page.setInputFiles('input[name="archivo"]', excelPrueba);
    await page.getByRole('button', { name: 'Guardar registro' }).click();
    await expect(page.locator('.alert-success')).toContainText('Cartera recibida registrada');

    expect(bd(`SELECT CONCAT(funcionario_id, '|', correo_solicitante, '|', nombre_funcionario) FROM cartera_envios WHERE correo_remitente = '${correo}'`))
        .toBe(`${d.usuarios.docente.id}|${d.usuarios.docente.email}|${nombre(d.usuarios.docente.id)}`);

    await page.goto('cartera/enviados');
    await expect(page.getByRole('heading', { name: 'Histórico de cartera recibida' })).toBeVisible();
    let fila = page.locator('tr', { hasText: correo });
    await expect(fila).toContainText(nombre(d.usuarios.docente.id));
    await expect(fila).toContainText(d.usuarios.docente.email);

    await fila.getByRole('link', { name: 'Editar' }).click();
    await expect(page.locator('input[name="correo_remitente"]')).toHaveValue(correo);
    await page.locator('input[name="correo_remitente"]').fill(correoEditado);
    await page.getByRole('button', { name: 'Guardar cambios' }).click();

    await expect(page).toHaveURL(/\/cartera\/enviados$/);
    fila = page.locator('tr', { hasText: correoEditado });
    await expect(fila).toBeVisible();

    await fila.getByRole('link', { name: 'Editar' }).click();
    page.once('dialog', (dialog) => dialog.accept('ELIMINAR'));
    await page.getByRole('button', { name: 'Eliminar registro' }).click();

    await expect(page).toHaveURL(/\/cartera\/enviados$/);
    await expect(page.locator('tr', { hasText: correoEditado })).toHaveCount(0);
});

test('se rechaza un funcionario de otra institución y un correo inválido', async ({ page }) => {
    test.skip(datos().remoto === true, 'Necesita la base de pruebas local');
    const d = datos();
    const url = `cartera/enviar?institucion=${d.instituciones.A}`;
    await page.goto(url);
    const token = await csrf(page);
    const enviar = (campos) => page.request.post('cartera/enviar', {
        form: { _csrf: token, institucion_id: String(d.instituciones.A), fecha_envio: '2026-01-15', ...campos },
        maxRedirects: 0,
    });

    await enviar({ funcionario_id: String(d.usuarios.rector_b.id), correo_solicitante: 'a@example.com', correo_remitente: 'b@example.com' });
    await page.goto(url);
    await expect(page.locator('.alert-danger')).toContainText('Elige el funcionario de la institución');

    await enviar({ funcionario_id: String(d.usuarios.docente.id), correo_solicitante: 'a@example.com', correo_remitente: 'no-es-correo' });
    await page.goto(url);
    await expect(page.locator('.alert-danger')).toContainText('correo válido desde el que llegó');
    // Lo escrito se conserva.
    await expect(page.locator('input[name="correo_solicitante"]')).toHaveValue('a@example.com');
});

test('un registro de antes, sin funcionario enlazado, se muestra y se completa al editarlo', async ({ page }) => {
    test.skip(datos().remoto === true, 'Necesita la base de pruebas local');
    const d = datos();
    const correo = `vieja-${Date.now()}@example.com`;
    bd(`INSERT INTO cartera_envios (institucion_id, archivo_path, correo_remitente, nombre_funcionario, fecha_envio, registrado_por)
        VALUES (${d.instituciones.A}, 'cartera/no-existe.xlsx', '${correo}', 'Funcionaria de antes', '2026-08-01', ${d.usuarios.superusuario.id})`);
    const id = bd(`SELECT id FROM cartera_envios WHERE correo_remitente = '${correo}'`);

    await page.goto('cartera/enviados');
    const fila = page.locator('tr', { hasText: correo });
    await expect(fila).toContainText('Funcionaria de antes');

    await page.goto(`cartera/${id}/editar`);
    await expect(page.locator('.alert-info')).toContainText('Funcionaria de antes');
    await seleccionarTomSelect(page, 'campo-funcionario', nombre(d.usuarios.secretario.id));
    await expect(page.locator('input[name="correo_solicitante"]')).toHaveValue(d.usuarios.secretario.email);
    await page.getByRole('button', { name: 'Guardar cambios' }).click();
    await expect(page).toHaveURL(/\/cartera\/enviados$/);
    expect(bd(`SELECT CONCAT(funcionario_id, '|', correo_solicitante) FROM cartera_envios WHERE id = ${id}`))
        .toBe(`${d.usuarios.secretario.id}|${d.usuarios.secretario.email}`);
});
