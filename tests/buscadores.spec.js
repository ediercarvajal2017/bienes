import { test, expect } from '@playwright/test';

/**
 * Comportamiento común de los buscadores (public/assets/js/buscador-vivo.js): la búsqueda
 * se hace al SALIR del cuadro, con Enter o al borrar con la ✕; nunca por un temporizador
 * mientras el usuario escribe.
 */

// Con la rectora: el superusuario, en varias pantallas, primero debe elegir una institución.
test.use({ storageState: 'playwright/.auth/rector.json' });

const LISTADOS = ['/bienes', '/asignaciones', '/reintegros', '/usuarios', '/espacios', '/cargas-masivas', '/bienes/qr-masivo'];

test.describe('Buscadores: buscan al terminar de escribir', () => {
    test('escribir NO recarga la página (no hay temporizador)', async ({ page }) => {
        await page.goto('/bienes');
        await page.locator('#buscador').pressSequentially('silla', { delay: 50 });
        await page.waitForTimeout(1500);
        expect(new URL(page.url()).searchParams.get('q')).toBeNull();
    });

    for (const ruta of LISTADOS) {
        test(`al salir del cuadro busca: ${ruta}`, async ({ page }) => {
            await page.goto(ruta);
            const buscador = page.locator('#buscador');
            await expect(buscador).toHaveAttribute('data-buscar', 'q');
            await buscador.fill('prueba');
            await Promise.all([page.waitForURL(/[?&]q=prueba/), buscador.blur()]);
            expect(new URL(page.url()).searchParams.get('pagina')).toBe('1');
            await expect(page.locator('#buscador')).toHaveValue('prueba');
        });
    }

    test('Enter busca y deja el cursor en el cuadro', async ({ page }) => {
        await page.goto('/bienes');
        await page.locator('#buscador').fill('Mesa');
        await Promise.all([page.waitForURL(/[?&]q=Mesa/), page.locator('#buscador').press('Enter')]);
        await expect(page.locator('#buscador')).toBeFocused();
    });

    test('borrar el texto y salir quita el filtro', async ({ page }) => {
        await page.goto('/bienes?q=Mesa');
        const buscador = page.locator('#buscador');
        await buscador.fill('');
        await Promise.all([page.waitForURL((url) => !url.searchParams.has('q')), buscador.blur()]);
    });

    test('salir sin cambiar el texto no recarga', async ({ page }) => {
        await page.goto('/bienes?q=Mesa');
        const antes = page.url();
        await page.locator('#buscador').focus();
        await page.locator('#buscador').blur();
        await page.waitForTimeout(800);
        expect(page.url()).toBe(antes);
    });

    test('verificación física: cada pestaña busca con su propio parámetro', async ({ page }) => {
        await page.goto('/verificaciones');
        const enlace = page.locator('a[href*="/verificaciones/"]').filter({ hasNotText: /nueva|crear/i }).first();
        test.skip(!(await enlace.count()), 'No hay jornadas en la base de pruebas');
        await enlace.click();
        const pendientes = page.locator('#buscadorPendientes');
        test.skip(!(await pendientes.isVisible()), 'La jornada no muestra la pestaña de pendientes');
        await pendientes.fill('silla');
        await Promise.all([page.waitForURL(/[?&]q=silla.*#seccion-pendientes|[?&]q=silla/), pendientes.blur()]);
    });

    test('papelera: filtra en la misma página al salir del cuadro', async ({ page }) => {
        await page.goto('/papelera');
        const buscador = page.locator('#buscadorPapelera');
        test.skip(!(await buscador.count()), 'La papelera está vacía');
        await buscador.fill('zzz-no-existe-zzz');
        await page.waitForTimeout(500);
        await expect(page.locator('#papeleraSinResultados')).toBeHidden();
        await buscador.blur();
        await expect(page.locator('#papeleraSinResultados')).toBeVisible();
    });

    test('buscador global: al salir del cuadro abre los resultados', async ({ page }) => {
        await page.goto('/dashboard');
        const global = page.locator('#buscadorGlobal');
        test.skip(!(await global.isVisible()), 'El buscador global no se muestra en este ancho');
        await global.fill('Silla');
        await Promise.all([page.waitForURL(/\/buscar\?q=Silla/), global.blur()]);
    });
});
