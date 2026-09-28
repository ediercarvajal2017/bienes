import { test, expect } from '@playwright/test';
import { datos, bd, sinSesion } from './helpers/datos.js';

/**
 * Política de tratamiento de datos (PoliticaController, AuthMiddleware y
 * App\Helpers\PoliticaDatos): la página es pública y cada usuario la acepta una vez, en su
 * primer ingreso. Mientras no la acepte no puede usar nada más; al aceptarla queda la
 * versión, la fecha y un registro en la auditoría.
 */
test.beforeEach(() => test.skip(datos().remoto === true, 'Necesita la base de pruebas local'));

test('la política se puede leer sin iniciar sesión y está enlazada desde el ingreso', async ({ browser }) => {
    const contexto = await sinSesion(browser);
    const pagina = await contexto.newPage();

    await pagina.goto('login');
    await pagina.getByRole('link', { name: 'Política de tratamiento de datos' }).click();
    await expect(pagina).toHaveURL(/\/politica-de-datos$/);
    await expect(pagina.getByRole('heading', { level: 1 })).toContainText('Política de tratamiento de datos personales');
    await expect(pagina.locator('.texto-legal')).toContainText('Ley 1581 de 2012');
    await expect(pagina.getByRole('link', { name: 'Ir a iniciar sesión' })).toBeVisible();

    await contexto.close();
});

test('un usuario nuevo debe aceptar la política antes de usar MIA, y solo una vez', async ({ browser }) => {
    const docente = datos().usuarios.docente;
    const marca = Date.now();
    const correo = `politica-${marca}@prueba.test`;
    // Cuenta nueva (sin política aceptada) con la misma contraseña que el docente de pruebas.
    bd(`INSERT INTO usuarios (documento, nombres, apellidos, cargo_id, email, password_hash, institucion_id, rol_id, activo)
        SELECT 'POL-${marca}', 'Paula', 'Prueba', cargo_id, '${correo}', password_hash, institucion_id, rol_id, 1
        FROM usuarios WHERE id = ${docente.id}`);
    const id = Number(bd(`SELECT id FROM usuarios WHERE email = '${correo}'`));

    const contexto = await sinSesion(browser);
    const pagina = await contexto.newPage();
    const ingresar = async () => {
        await pagina.goto('login');
        await pagina.fill('#email', correo);
        await pagina.fill('#password', docente.clave);
        await pagina.getByRole('button', { name: 'Ingresar' }).click();
    };

    await ingresar();
    await expect(pagina).toHaveURL(/\/politica\/aceptar$/);
    await expect(pagina.getByRole('heading', { name: 'Antes de continuar' })).toBeVisible();
    await expect(pagina.getByRole('region', { name: /política de datos/ })).toContainText('Responsable del tratamiento');

    // Cualquier otra página lo devuelve a la aceptación (y se recuerda cuál pidió).
    await pagina.goto('bienes');
    await expect(pagina).toHaveURL(/\/politica\/aceptar$/);

    // Sin marcar la casilla, el servidor tampoco acepta (aunque se salte el "required").
    await pagina.locator('#acepto').evaluate((casilla) => casilla.removeAttribute('required'));
    await pagina.getByRole('button', { name: 'Aceptar y continuar' }).click();
    await expect(pagina.getByRole('alert')).toContainText('marcar la casilla');
    expect(bd(`SELECT COALESCE(politica_version, '') FROM usuarios WHERE id = ${id}`)).toBe('');

    await pagina.getByLabel(/He leído y acepto/).check();
    await pagina.getByRole('button', { name: 'Aceptar y continuar' }).click();
    await expect(pagina).toHaveURL(/\/bienes$/);

    expect(bd(`SELECT politica_version IS NOT NULL AND politica_aceptada_en IS NOT NULL FROM usuarios WHERE id = ${id}`)).toBe('1');
    expect(Number(bd(`SELECT COUNT(*) FROM auditoria WHERE accion = 'aceptar_politica' AND usuario_id = ${id}`))).toBe(1);

    await pagina.goto('mi-cuenta');
    await expect(pagina.locator('#tituloPrivacidad').locator('..')).toContainText('Aceptaste la política');

    // En el siguiente ingreso ya no se pide.
    await pagina.locator('form[action$="/logout"] button').first().click();
    await ingresar();
    await expect(pagina).toHaveURL(/\/dashboard$/);

    await contexto.close();
    bd(`DELETE FROM auditoria WHERE usuario_id = ${id}; DELETE FROM usuarios WHERE id = ${id}`);
});
