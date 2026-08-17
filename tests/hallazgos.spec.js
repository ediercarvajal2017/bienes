import { test, expect } from '@playwright/test';
import { seleccionarPrimeraOpcionTomSelect } from './helpers/tomSelect.js';

/**
 * Reportar un "hallazgo" (bien físico sin código/QR encontrado durante una jornada de
 * verificación) exige una jornada activa -- mismo candado de "solo una jornada activa
 * por institución" que verificaciones.spec.js, así que se sigue la misma protección.
 */
async function cerrarJornadaActivaSiExiste(page) {
    await page.goto('verificaciones');
    const linkActiva = page.getByRole('link', { name: 'Ir a la jornada en progreso' });
    if ((await linkActiva.count()) === 0) {
        return;
    }
    await linkActiva.click();
    page.once('dialog', (dialog) => dialog.accept());
    await page.getByRole('button', { name: 'Cerrar jornada' }).click();
}

test.use({ storageState: 'playwright/.auth/user.json' });

test('reportar un hallazgo durante una jornada y descartarlo', async ({ page }) => {
    await cerrarJornadaActivaSiExiste(page);

    const sufijo = Date.now();
    await page.goto('verificaciones/crear');
    await page.locator('input[name="nombre"]').fill(`PW-TEST Jornada hallazgo ${sufijo}`);
    await page.getByRole('button', { name: 'Iniciar jornada' }).click();
    await expect(page).toHaveURL(/\/verificaciones\/\d+$/);
    const urlJornada = page.url();

    // Desde /escanear aparece el acceso a "Reportar bien no registrado" solo con
    // jornada activa -- para un superusuario, esa pantalla mira el filtro de
    // institución del encabezado, no su institución de sesión, así que hay que
    // dejarlo apuntando a la misma institución donde se creó la jornada.
    await Promise.all([
        page.waitForLoadState('networkidle'),
        seleccionarPrimeraOpcionTomSelect(page, 'filtroInstitucionSelect'),
    ]);
    await page.goto('escanear');
    await page.getByRole('link', { name: 'Reportar bien no registrado' }).click();
    await expect(page).toHaveURL(/\/hallazgos\/crear$/);

    const idEspacio = await page.locator('select[name="espacio_id"]').evaluate((el) => el.id);
    await seleccionarPrimeraOpcionTomSelect(page, idEspacio);
    const descripcionHallazgo = `PW-TEST hallazgo ${sufijo}`;
    await page.locator('input[name="descripcion"]').fill(descripcionHallazgo);
    await page.getByRole('button', { name: 'Reportar hallazgo' }).click();

    await expect(page).toHaveURL(/\/escanear$/);
    await expect(page.locator('.alert-success')).toContainText('Hallazgo reportado');

    // Se ve en la jornada, pestaña "Hallazgos", y se puede descartar desde ahí.
    await page.goto(urlJornada);
    await expect(page.locator('tr', { hasText: descripcionHallazgo })).toBeVisible();

    page.once('dialog', (dialog) => dialog.accept());
    await page.locator('tr', { hasText: descripcionHallazgo }).getByRole('button', { name: 'Descartar' }).click();
    await expect(page.locator('.alert-success')).toContainText('Hallazgo descartado');
    await expect(page.locator('tr', { hasText: descripcionHallazgo })).toHaveCount(0);

    // --- Limpieza: cerrar la jornada de prueba y devolver el filtro de institución
    // del encabezado a "Ver todas" (persiste en la sesión del servidor, no solo en
    // esta página, y afectaría a las pruebas que corran después). ---
    page.once('dialog', (dialog) => dialog.accept());
    await page.getByRole('button', { name: 'Cerrar jornada' }).click();
    await expect(page.locator('.alert-success')).toBeVisible();

    await page.locator('#filtroInstitucionSelect + .ts-wrapper .ts-control').click();
    await page.locator('#filtroInstitucionSelect-ts-dropdown [role="option"][data-value=""]').click();
});
