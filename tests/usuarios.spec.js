import { test, expect } from '@playwright/test';

test('crear, editar, desactivar y eliminar un usuario', async ({ page }) => {
    // Activar/desactivar/eliminar piden confirmación (data-confirmar): se acepta.
    page.on('dialog', (dialogo) => dialogo.accept());
    const sufijo = Date.now();
    const documento = `PWTEST${sufijo}`;
    const email = `pw-test-${sufijo}@example.com`;
    const emailEditado = `pw-test-${sufijo}-ed@example.com`;

    await page.goto('usuarios/crear');

    await page.locator('input[name="documento"]').fill(documento);
    await page.locator('input[name="email"]').fill(email);
    await page.locator('input[name="nombres"]').fill('PW-TEST-Nombres');
    await page.locator('input[name="apellidos"]').fill(`Apellidos-${sufijo}`);
    await page.locator('select[name="cargo_id"]').selectOption({ index: 0 });
    // "Docente" a propósito: es el rol de menor privilegio, no tiene sentido crear
    // otro superusuario de prueba por accidente.
    await page.locator('select[name="rol_id"]').selectOption({ label: 'Docente' });
    // El select de institución no tiene opción en blanco -queda la primera por defecto-.
    await page.locator('input[name="password"]').fill('ContraseñaPrueba123');

    await page.getByRole('button', { name: 'Registrar usuario' }).click();

    await expect(page).toHaveURL(/\/usuarios$/);
    let fila = page.locator('tr', { hasText: email });
    await expect(fila).toBeVisible();

    await fila.getByRole('link', { name: 'Editar' }).click();
    await expect(page.locator('input[name="email"]')).toHaveValue(email);

    await page.locator('input[name="email"]').fill(emailEditado);
    await page.getByRole('button', { name: 'Guardar cambios' }).click();

    fila = page.locator('tr', { hasText: emailEditado });
    await expect(fila).toBeVisible();

    await fila.getByRole('button', { name: 'Desactivar' }).click();
    fila = page.locator('tr', { hasText: emailEditado });
    // La fila tiene dos badges (Rol y Estado) — hay que apuntar al de Estado.
    await expect(fila.locator('td[data-label="Estado"] .badge')).toHaveText('Inactivo');
    await fila.getByRole('button', { name: 'Eliminar' }).click();

    await expect(page.locator('tr', { hasText: emailEditado })).toHaveCount(0);
});

test('editar un usuario con verificación en dos pasos deja la auditoría completa y sin secretos', async ({ page }) => {
    const { datos, bd } = await import('./helpers/datos.js');
    const marca = Date.now();
    const correo = `pw-2fa-${marca}@prueba.test`;
    // Usuario con la verificación activa: totp_secreto es binario (cifrado) y antes hacía
    // fallar json_encode, así que la auditoría quedaba con "datos antes" vacío.
    bd(`INSERT INTO usuarios (documento, nombres, apellidos, cargo_id, email, password_hash, institucion_id, rol_id, activo, totp_secreto, totp_activado_en)
        SELECT 'PW2FA-${marca}', 'Ana', 'Dos Pasos', cargo_id, '${correo}', password_hash, institucion_id, rol_id, 1, UNHEX('00FF10E2C3A4FFEE99'), NOW()
        FROM usuarios WHERE id = ${datos().usuarios.docente.id}`);
    const id = Number(bd(`SELECT id FROM usuarios WHERE email = '${correo}'`));

    await page.goto(`usuarios/${id}/editar`);
    await page.locator('input[name="apellidos"]').fill('Dos Pasos Editada');
    await page.getByRole('button', { name: 'Guardar cambios' }).click();
    await expect(page).toHaveURL(/\/usuarios(\?.*)?$/);

    const antes = bd(`SELECT datos_antes FROM auditoria WHERE entidad = 'usuario' AND accion = 'editar' AND entidad_id = ${id} ORDER BY id DESC LIMIT 1`);
    const json = JSON.parse(antes);
    expect(json.apellidos).toBe('Dos Pasos');
    for (const campo of ['totp_secreto', 'password_hash', 'totp_ultimo_paso', 'sesion_version']) {
        expect(json, campo).not.toHaveProperty(campo);
    }

    bd(`DELETE FROM auditoria WHERE entidad = 'usuario' AND entidad_id = ${id}; DELETE FROM usuarios WHERE id = ${id}`);
});
