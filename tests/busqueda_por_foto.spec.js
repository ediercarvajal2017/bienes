import { test, expect } from '@playwright/test';
import path from 'node:path';
import { fileURLToPath } from 'node:url';
import { seleccionarPrimeraOpcionTomSelect } from './helpers/tomSelect.js';

const __dirname = path.dirname(fileURLToPath(import.meta.url));
const fotoFixture = path.join(__dirname, 'fixtures', 'foto_busqueda.jpg');
const CODIGO = 'PW-TEST-BUSQFOTO-001';

test.use({ storageState: 'playwright/.auth/user.json' });

test('busca un bien por su foto y lo encuentra por parecido', async ({ page }) => {
    // El reconocimiento (cargar el modelo, calcular la huella de la imagen) ocurre en
    // el propio navegador -- primera carga del modelo por CDN + varias inferencias,
    // más lento que el resto de la suite.
    test.setTimeout(120_000);

    // Código fijo a propósito (igual que bienes_carga_masiva_fotos.spec.js): reutiliza
    // el bien si una corrida anterior ya lo creó con esta misma foto.
    await page.goto('bienes?q=' + CODIGO);
    const yaExiste = (await page.locator('tr', { hasText: CODIGO }).count()) > 0;
    if (!yaExiste) {
        await page.goto('bienes/crear');
        await page.locator('input[name="codigo_identificacion"]').fill(CODIGO);
        await page.locator('input[name="descripcion"]').fill('PW-TEST bien para búsqueda por foto');
        await page.setInputFiles('input[name="foto"]', fotoFixture);
        await page.getByRole('button', { name: 'Registrar bien' }).click();
        await expect(page).toHaveURL(/\/bienes$/);
    }

    // Para un superusuario, esta pantalla mira el filtro de institución del
    // encabezado (sesión de servidor, no estado de la página) -- se fija a una
    // institución concreta, si no queda en "Ver todas" y la pantalla ni siquiera
    // se muestra.
    await Promise.all([
        page.waitForLoadState('networkidle'),
        seleccionarPrimeraOpcionTomSelect(page, 'filtroInstitucionSelect'),
    ]);

    await page.goto('bienes/buscar-por-foto');

    // El indexado en segundo plano (huella de las fotos que aún no la tienen, incluida
    // la de este bien si acaba de crearse) corre solo al cargar la página -- se espera
    // a la señal real de "terminó" (window.__indexadoListo) en vez del texto en
    // pantalla, que arranca vacío y sería un falso positivo inmediato.
    await page.waitForFunction(() => window.__indexadoListo === true, { timeout: 90_000 });

    await page.setInputFiles('#inputFotoBusqueda', fotoFixture);

    // Misma foto exacta subida como consulta: debe aparecer primero, con una similitud
    // muy alta (no necesariamente 100% por redondeo/decodificación, pero sí dominante).
    const primeraTarjeta = page.locator('#resultadosBusqueda .card').first();
    await expect(primeraTarjeta).toBeVisible({ timeout: 30_000 });
    await expect(primeraTarjeta).toContainText(CODIGO);

    const textoSimilitud = await primeraTarjeta.locator('.text-muted').first().innerText();
    const porcentaje = parseInt(textoSimilitud, 10);
    expect(porcentaje).toBeGreaterThanOrEqual(90);

    // --- Limpieza: devolver el filtro de institución del encabezado a "Ver todas"
    // (persiste en la sesión del servidor, no solo en esta página). ---
    await page.locator('#filtroInstitucionSelect + .ts-wrapper .ts-control').click();
    await page.locator('#filtroInstitucionSelect-ts-dropdown [role="option"][data-value=""]').click();
});
