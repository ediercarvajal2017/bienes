import { test, expect } from '@playwright/test';
import { seleccionarPrimeraOpcionTomSelect } from './helpers/tomSelect.js';

/**
 * La búsqueda manual por código (para cuando no hay cámara a mano) es la alternativa
 * real al escaneo de QR con cámara, que tests/README.md ya documenta como fuera de
 * alcance de esta suite. Para un superusuario, tanto index() como buscar() miran el
 * filtro de institución del encabezado, no su institución de sesión.
 */
test.use({ storageState: 'playwright/.auth/user.json' });

test('buscar un bien por código desde /escanear', async ({ page }) => {
    // El filtro de institución del encabezado es una sesión compartida con el resto de
    // la suite -- otra prueba puede haberlo dejado con un valor. Se fuerza a blanco
    // antes de este caso, en vez de asumir que ya empieza así.
    await page.goto('dashboard');
    await page.locator('#filtroInstitucionSelect + .ts-wrapper .ts-control').click();
    await page.locator('#filtroInstitucionSelect-ts-dropdown [role="option"][data-value=""]').click();

    // Sin institución en el filtro, la búsqueda avisa en vez de fallar en silencio.
    await page.goto('escanear');
    await page.locator('input[name="codigo"]').fill('CUALQUIER-CODIGO');
    await page.getByRole('button', { name: 'Buscar' }).click();
    await expect(page.locator('.alert-danger')).toContainText('Selecciona una institución en el filtro');

    // Con institución elegida: crea un bien real primero para tener un código que buscar.
    await Promise.all([
        page.waitForLoadState('networkidle'),
        seleccionarPrimeraOpcionTomSelect(page, 'filtroInstitucionSelect'),
    ]);

    const codigoBien = `PW-TEST-ESCANEO-${Date.now()}`;
    await page.goto('bienes/crear');
    await page.locator('input[name="codigo_identificacion"]').fill(codigoBien);
    await page.locator('input[name="descripcion"]').fill('PW-TEST bien para búsqueda manual');
    await page.getByRole('button', { name: 'Registrar bien' }).click();
    await expect(page).toHaveURL(/\/bienes$/);

    await page.goto('escanear');
    await page.locator('input[name="codigo"]').fill(codigoBien);
    await page.getByRole('button', { name: 'Buscar' }).click();
    await expect(page).toHaveURL(/\/qr\/[a-f0-9-]+$/);
    await expect(page.getByText(codigoBien)).toBeVisible();

    // Un código que no existe en esa institución avisa en vez de fallar en silencio.
    await page.goto('escanear');
    await page.locator('input[name="codigo"]').fill('PW-TEST-NO-EXISTE-JAMAS');
    await page.getByRole('button', { name: 'Buscar' }).click();
    await expect(page.locator('.alert-danger')).toContainText('No se encontró ningún bien');

    // Limpieza: el filtro de institución del encabezado persiste en la sesión del
    // servidor y afectaría a las pruebas que corran después.
    await page.locator('#filtroInstitucionSelect + .ts-wrapper .ts-control').click();
    await page.locator('#filtroInstitucionSelect-ts-dropdown [role="option"][data-value=""]').click();
});
