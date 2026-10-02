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
 * correo, quién la envió en la Alcaldía y desde qué correo, la fecha en que se recibió y el archivo.
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
    await page.locator('input[name="nombre_remitente"]').fill('María Fernanda López Ruiz');
    await page.locator('input[name="correo_remitente"]').fill(correo);
    await page.setInputFiles('input[name="archivo"]', excelPrueba);
    await page.getByRole('button', { name: 'Guardar registro' }).click();
    await expect(page.locator('.alert-success')).toContainText('Cartera recibida registrada');

    expect(bd(`SELECT CONCAT(funcionario_id, '|', correo_solicitante, '|', nombre_funcionario) FROM cartera_envios WHERE correo_remitente = '${correo}'`))
        .toBe(`${d.usuarios.docente.id}|${d.usuarios.docente.email}|${nombre(d.usuarios.docente.id)}`);
    expect(bd(`SELECT nombre_remitente FROM cartera_envios WHERE correo_remitente = '${correo}'`)).toBe('María Fernanda López Ruiz');

    // El registro aparece en la lista de la misma ventana, sin "Ver histórico".
    await expect(page.getByRole('link', { name: 'Ver histórico' })).toHaveCount(0);
    let fila = page.locator('tbody tr', { hasText: correo });
    await expect(fila).toContainText(nombre(d.usuarios.docente.id));
    await expect(fila).toContainText(d.usuarios.docente.email);
    await expect(fila).toContainText('María Fernanda López Ruiz');

    // Editar en la misma ventana.
    await fila.getByRole('link', { name: 'Editar' }).click();
    await expect(page).toHaveURL(/cartera\/enviar\?.*editar=\d+/);
    await expect(page.getByRole('heading', { name: 'Editar registro' })).toBeVisible();
    await expect(page.locator('input[name="correo_remitente"]')).toHaveValue(correo);
    await expect(page.locator('input[name="nombre_remitente"]')).toHaveValue('María Fernanda López Ruiz');
    await page.locator('input[name="correo_remitente"]').fill(correoEditado);
    await page.getByRole('button', { name: 'Guardar cambios' }).click();

    await expect(page).toHaveURL(/cartera\/enviar/);
    await expect(page).not.toHaveURL(/editar=/);
    fila = page.locator('tbody tr', { hasText: correoEditado });
    await expect(fila).toBeVisible();

    // La dirección vieja del histórico lleva a la misma ventana.
    const historial = await page.request.get('cartera/enviados', { maxRedirects: 0 });
    expect(historial.headers().location).toContain('/cartera/enviar');

    // Eliminar desde la lista.
    page.once('dialog', (dialog) => dialog.accept());
    await fila.getByRole('button', { name: 'Eliminar' }).click();
    await expect(page.locator('.alert-success')).toContainText('papelera');
    await expect(page.locator('tbody tr', { hasText: correoEditado })).toHaveCount(0);
});

test('se rechaza un funcionario de otra institución, quien envió vacío y un correo inválido', async ({ page }) => {
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

    await enviar({ funcionario_id: String(d.usuarios.docente.id), correo_solicitante: 'a@example.com', nombre_remitente: '   ', correo_remitente: 'b@example.com' });
    await page.goto(url);
    await expect(page.locator('.alert-danger')).toContainText('funcionario de la Alcaldía que envió');

    await enviar({ funcionario_id: String(d.usuarios.docente.id), correo_solicitante: 'a@example.com', nombre_remitente: 'Ana Ruiz', correo_remitente: 'no-es-correo' });
    await page.goto(url);
    await expect(page.locator('.alert-danger')).toContainText('correo válido desde el que llegó');
    // Lo escrito se conserva.
    await expect(page.locator('input[name="correo_solicitante"]')).toHaveValue('a@example.com');
    await expect(page.locator('input[name="nombre_remitente"]')).toHaveValue('Ana Ruiz');
});

test('un registro de antes, sin funcionario enlazado, se muestra y se completa al editarlo', async ({ page }) => {
    test.skip(datos().remoto === true, 'Necesita la base de pruebas local');
    const d = datos();
    const correo = `vieja-${Date.now()}@example.com`;
    bd(`INSERT INTO cartera_envios (institucion_id, archivo_path, correo_remitente, nombre_funcionario, fecha_envio, registrado_por)
        VALUES (${d.instituciones.A}, 'cartera/no-existe.xlsx', '${correo}', 'Funcionaria de antes', '2026-08-01', ${d.usuarios.superusuario.id})`);
    const id = bd(`SELECT id FROM cartera_envios WHERE correo_remitente = '${correo}'`);

    await page.goto(`cartera/enviar?institucion=${d.instituciones.A}`);
    const fila = page.locator('tbody tr', { hasText: correo });
    await expect(fila).toContainText('Funcionaria de antes');

    // La dirección vieja de editar lleva a la ventana en modo edición.
    await page.goto(`cartera/${id}/editar`);
    expect(page.url()).toContain(`cartera/enviar?editar=${id}`);
    await expect(page.locator('.alert-info')).toContainText('Funcionaria de antes');
    await seleccionarTomSelect(page, 'campo-funcionario', nombre(d.usuarios.secretario.id));
    await expect(page.locator('input[name="correo_solicitante"]')).toHaveValue(d.usuarios.secretario.email);
    // Quien la envió en la Alcaldía no existía antes: se completa al editar.
    await expect(page.locator('input[name="nombre_remitente"]')).toHaveValue('');
    await page.locator('input[name="nombre_remitente"]').fill('Luz Marina Gómez');
    await page.getByRole('button', { name: 'Guardar cambios' }).click();
    await expect(page.locator('.alert-success')).toContainText('Registro actualizado');
    expect(bd(`SELECT CONCAT(funcionario_id, '|', correo_solicitante, '|', nombre_remitente) FROM cartera_envios WHERE id = ${id}`))
        .toBe(`${d.usuarios.secretario.id}|${d.usuarios.secretario.email}|Luz Marina Gómez`);
});
