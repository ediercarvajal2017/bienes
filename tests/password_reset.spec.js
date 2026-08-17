import { test, expect } from '@playwright/test';
import { seleccionarPrimeraOpcionTomSelect } from './helpers/tomSelect.js';

/**
 * PasswordController es el flujo de autenticación más sensible después del login
 * (tokens de un solo uso) y no tenía ningún test. El enlace real solo se entrega por
 * correo -no hay forma de leerlo desde el navegador-, así que esta prueba cubre lo que
 * SÍ es alcanzable sin interceptar un correo: que no se pueda distinguir por la
 * respuesta si un correo existe o no (protección contra enumeración de cuentas), que
 * un token inválido nunca muestre el formulario de nueva contraseña, y el flujo
 * completo de "¿Cuál es mi correo?" (que no depende de correo saliente).
 */
test.use({ storageState: 'playwright/.auth/user.json' });

test('recuperación de contraseña y de correo', async ({ page }) => {
    const sufijo = Date.now();
    // documento es VARCHAR(20): el prefijo tiene que ser corto para no truncarse junto
    // con los 13 dígitos de Date.now() (igual que en usuarios.spec.js).
    const documento = `PWTEST${sufijo}`;
    const email = `pw-test-pwd-${sufijo}@example.com`;

    // --- Usuario desechable para la parte de "¿Cuál es mi correo?" ---
    await page.goto('usuarios/crear');
    await page.locator('input[name="documento"]').fill(documento);
    await page.locator('input[name="email"]').fill(email);
    await page.locator('input[name="nombres"]').fill('PW-TEST-Nombres');
    await page.locator('input[name="apellidos"]').fill(`Apellidos-${sufijo}`);
    await page.locator('select[name="cargo_id"]').selectOption({ index: 0 });
    await page.locator('select[name="rol_id"]').selectOption({ label: 'Docente' });
    // El select de institución no tiene opción en blanco -queda la primera por
    // defecto-, igual que en usuarios.spec.js.
    await page.locator('input[name="password"]').fill('ContraseñaPrueba123');
    await page.getByRole('button', { name: 'Registrar usuario' }).click();
    await expect(page).toHaveURL(/\/usuarios$/);

    // --- "Olvidé mi contraseña": mismo mensaje exista o no el correo ---
    const mensajeGenerico = 'Si el correo está registrado en el sistema, te enviamos un enlace para restablecer la contraseña.';

    await page.goto('olvide-contrasena');
    await expect(page.locator('input[name="email"]')).toBeVisible();
    await page.locator('input[name="email"]').fill('no-existe-en-sigebi@example.com');
    await page.getByRole('button', { name: 'Enviar enlace' }).click();
    await expect(page.locator('.alert-success')).toContainText(mensajeGenerico);

    await page.locator('input[name="email"]').fill(email);
    await page.getByRole('button', { name: 'Enviar enlace' }).click();
    await expect(page.locator('.alert-success')).toContainText(mensajeGenerico);

    // --- Un token inválido nunca muestra el formulario de nueva contraseña ---
    await page.goto('restablecer-contrasena/token-que-no-existe');
    await expect(page.locator('input[name="password"]')).toHaveCount(0);
    await expect(page.getByText('Este enlace ya no es válido', { exact: false })).toBeVisible();

    // --- "¿Cuál es mi correo?": documento inexistente vs. el del usuario de prueba ---
    // El select de institución aquí no tiene id fijo (Tom Select le asigna uno propio
    // al inicializarlo) -- se lee en cada carga en vez de asumir un valor fijo.
    async function elegirPrimeraInstitucion() {
        const id = await page.locator('select[name="institucion_id"]').evaluate((el) => el.id);
        await seleccionarPrimeraOpcionTomSelect(page, id);
    }

    await page.goto('olvide-correo');
    await elegirPrimeraInstitucion();
    await page.locator('input[name="documento"]').fill('00000000000');
    await page.getByRole('button', { name: 'Buscar mi correo' }).click();
    await expect(page.locator('.alert-danger')).toContainText('No encontramos una cuenta activa');

    await elegirPrimeraInstitucion();
    await page.locator('input[name="documento"]').fill(documento);
    await page.getByRole('button', { name: 'Buscar mi correo' }).click();
    await expect(page.locator('.alert-success')).toContainText('Tu correo registrado es');

    // --- Limpieza del usuario desechable ---
    await page.goto('usuarios');
    const fila = page.locator('tr', { hasText: email });
    await fila.getByRole('button', { name: 'Desactivar' }).click();
    page.once('dialog', (dialog) => dialog.accept());
    await fila.getByRole('button', { name: 'Eliminar' }).click();
    await expect(page.locator('tr', { hasText: email })).toHaveCount(0);
});
