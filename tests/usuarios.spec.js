import { test, expect } from '@playwright/test';

test('crear, editar, desactivar y eliminar un usuario', async ({ page }) => {
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

    await page.getByRole('button', { name: 'Crear usuario' }).click();

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

    page.once('dialog', (dialog) => dialog.accept());
    await fila.getByRole('button', { name: 'Eliminar' }).click();

    await expect(page.locator('tr', { hasText: emailEditado })).toHaveCount(0);
});
