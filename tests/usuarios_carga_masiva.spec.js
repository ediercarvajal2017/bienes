import { test, expect } from '@playwright/test';
import { execFileSync } from 'node:child_process';
import path from 'node:path';
import os from 'node:os';
import { fileURLToPath } from 'node:url';

const __dirname = path.dirname(fileURLToPath(import.meta.url));

/**
 * El fixture se genera en cada corrida (en vez de un .xlsx estático) con documento y
 * correo únicos: SIGEBI no libera el documento de un usuario solo por estar en la
 * papelera (Usuario::findByDocumento() no filtra eliminado_en), así que reusar el
 * mismo documento entre corridas hacía que la fila se tratara como "modificado" en
 * vez de "nuevo" -- nunca creaba un usuario activo de verdad la segunda vez.
 */
test('carga masiva de usuarios: la fila inválida no se crea, la válida sí', async ({ page }) => {
    const sufijo = String(Date.now());
    const emailValido = `pw-test-carga-${sufijo}@example.com`;
    const fixture = path.join(os.tmpdir(), `usuarios_carga_masiva_${sufijo}.xlsx`);
    execFileSync('php', [path.join(__dirname, 'fixtures', 'generar_carga_masiva_usuarios.php'), sufijo, fixture]);

    await page.goto('usuarios/carga-masiva');

    await page.setInputFiles('input[name="archivo"]', fixture);
    await page.getByRole('button', { name: 'Analizar archivo' }).click();

    await expect(page).toHaveURL(/\/usuarios\/carga-masiva\/\d+/);
    await expect(page.locator('.badge', { hasText: 'Inválido' })).toBeVisible();
    await expect(page.getByText('Correo electrónico inválido')).toBeVisible();

    page.once('dialog', (dialog) => dialog.accept());
    await page.getByRole('button', { name: 'Confirmar importación' }).click();

    await expect(page.locator('.alert-success')).toContainText('Carga masiva aplicada correctamente.');

    await page.goto('usuarios');
    await expect(page.locator('tr', { hasText: emailValido })).toBeVisible();
    await expect(page.locator('tr', { hasText: 'PW-TEST Nombres Invalida' })).toHaveCount(0);

    // Limpieza del usuario que sí se creó (fila válida) -- desactivar y eliminar,
    // igual que usuarios.spec.js.
    const fila = page.locator('tr', { hasText: emailValido });
    await fila.getByRole('button', { name: 'Desactivar' }).click();
    page.once('dialog', (dialog) => dialog.accept());
    await fila.getByRole('button', { name: 'Eliminar' }).click();
    await expect(page.locator('tr', { hasText: emailValido })).toHaveCount(0);
});
