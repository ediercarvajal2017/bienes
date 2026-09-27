import { test, expect } from '@playwright/test';
import { datos, comoRol, sinSesion, bd, totp } from './helpers/datos.js';

/**
 * Verificación en dos pasos (OPCIONAL): el usuario la activa por decisión propia, el
 * segundo paso del inicio de sesión, la protección contra reusar un código, los códigos
 * de recuperación y el restablecimiento por un administrador. Usa la cuenta dedicada
 * "dosfa" de database/seeders/pruebas.php (activarla cierra sus otras sesiones).
 */
const pasoActual = () => Math.floor(Date.now() / 30000);

async function ingresar(pagina, cuenta) {
    await pagina.goto('login');
    await pagina.fill('#email', cuenta.email);
    await pagina.fill('#password', cuenta.clave);
    await pagina.getByRole('button', { name: 'Ingresar' }).click();
}

test('verificación en dos pasos: opcional, segundo paso, anti-repetición, recuperación y restablecimiento', async ({ browser }) => {
    test.setTimeout(120_000);
    const cuenta = datos().usuarios.dosfa;
    const contexto = await sinSesion(browser);
    const pagina = await contexto.newPage();

    // Sin 2FA entra directo: es opcional, nadie la exige.
    await ingresar(pagina, cuenta);
    await expect(pagina).toHaveURL(/dashboard/);
    await expect(pagina.getByText('Tu rol exige')).toHaveCount(0);

    // La activa desde Mi cuenta.
    await pagina.goto('mi-cuenta');
    await pagina.getByRole('link', { name: 'Activar verificación en dos pasos' }).click();
    await expect(pagina.getByText('Es opcional')).toBeVisible();
    await expect(pagina.locator('img[alt^="Código QR"]')).toBeVisible();
    const secreto = (await pagina.locator('code.user-select-all').textContent()).replace(/\s+/g, '');
    expect(secreto).toHaveLength(32);

    await pagina.fill('#codigoActivar', '000000');
    await pagina.getByRole('button', { name: 'Activar' }).click();
    await expect(pagina.locator('.alert-danger')).toContainText('no coincide');

    const pasoActivacion = pasoActual();
    await pagina.fill('#codigoActivar', totp(secreto, pasoActivacion));
    await pagina.getByRole('button', { name: 'Activar' }).click();
    await expect(pagina).toHaveURL(/2fa\/codigos/);
    const codigos = await pagina.locator('.codigos-recuperacion li').allTextContents();
    expect(codigos.map((c) => c.trim())).toHaveLength(10);
    expect(bd(`SELECT totp_activado_en IS NOT NULL FROM usuarios WHERE id = ${cuenta.id}`)).toBe('1');

    // Cerrar sesión y volver: ahora pide el código.
    await pagina.goto('mi-cuenta');
    await pagina.locator('form[action$="/logout"] button').first().click({ force: true });
    await ingresar(pagina, cuenta);
    await expect(pagina).toHaveURL(/2fa\/verificar/);
    expect((await contexto.request.get('dashboard', { maxRedirects: 0 })).headers().location).toMatch(/login$/);

    await pagina.fill('#codigo', '123456');
    await pagina.getByRole('button', { name: 'Verificar' }).click();
    await expect(pagina.locator('.alert-danger')).toContainText('Código incorrecto');

    // El mismo código de la activación no sirve dos veces.
    await pagina.fill('#codigo', totp(secreto, pasoActivacion));
    await expect(pagina).toHaveURL(/2fa\/verificar/); // se envía solo al completar 6 dígitos
    await expect(pagina.locator('.alert-danger')).toContainText('Código incorrecto');

    // Un código nuevo sí.
    await pagina.fill('#codigo', totp(secreto, pasoActivacion + 1));
    await expect(pagina).toHaveURL(/dashboard/);

    // Código de recuperación: entra una vez, no dos.
    await pagina.locator('form[action$="/logout"] button').first().click({ force: true });
    await ingresar(pagina, cuenta);
    await pagina.fill('#codigo', codigos[0].trim().toLowerCase());
    await pagina.getByRole('button', { name: 'Verificar' }).click();
    await expect(pagina).toHaveURL(/dashboard/);
    await pagina.locator('form[action$="/logout"] button').first().click({ force: true });
    await ingresar(pagina, cuenta);
    await pagina.fill('#codigo', codigos[0].trim());
    await pagina.getByRole('button', { name: 'Verificar' }).click();
    await expect(pagina).toHaveURL(/2fa\/verificar/);
    await contexto.close();

    // El rector de su institución la restablece (con su contraseña) y queda sin 2FA.
    const rector = await comoRol(browser, 'rector');
    const paginaRector = await rector.newPage();
    await paginaRector.goto(`usuarios/${cuenta.id}/editar`);
    paginaRector.on('dialog', (dialogo) => dialogo.accept());
    await paginaRector.fill('#passwordConfirmacion2fa', 'incorrecta-123');
    await paginaRector.getByRole('button', { name: 'Restablecer' }).click();
    expect(bd(`SELECT totp_activado_en IS NOT NULL FROM usuarios WHERE id = ${cuenta.id}`), 'con la contraseña equivocada no se restablece').toBe('1');
    await paginaRector.fill('#passwordConfirmacion2fa', datos().usuarios.rector.clave);
    await paginaRector.getByRole('button', { name: 'Restablecer' }).click();
    await expect(paginaRector.locator('.alert-success')).toContainText('restablecida');
    expect(bd(`SELECT totp_activado_en IS NULL AND totp_secreto IS NULL FROM usuarios WHERE id = ${cuenta.id}`)).toBe('1');
    expect(bd(`SELECT COUNT(*) FROM auditoria WHERE accion = '2fa_restablecer' AND entidad_id = ${cuenta.id}`)).toBe('1');
    await rector.close();
});
