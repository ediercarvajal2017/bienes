import { test, expect } from '@playwright/test';
import path from 'node:path';
import { fileURLToPath } from 'node:url';

const __dirname = path.dirname(fileURLToPath(import.meta.url));
const zipFixture = path.join(__dirname, 'fixtures', 'fotos_bienes.zip');
const CODIGO = 'PW-TEST-FOTO-001';

test('carga masiva de fotos empareja una imagen con su bien por código', async ({ page }) => {
    // Código fijo a propósito (no con timestamp, como el resto de las pruebas): el
    // .zip de fixture trae un archivo llamado exactamente "PW-TEST-FOTO-001.jpg" -el
    // emparejamiento es por nombre de archivo = código del bien-, así que regenerarlo
    // en cada corrida no aportaría nada. Por eso esta prueba es idempotente: reutiliza
    // el bien si una corrida anterior ya lo creó, en vez de fallar por "código duplicado".
    await page.goto('bienes?q=' + CODIGO);
    const yaExiste = (await page.locator('tr', { hasText: CODIGO }).count()) > 0;
    if (!yaExiste) {
        await page.goto('bienes/crear');
        await page.locator('input[name="codigo_identificacion"]').fill(CODIGO);
        await page.locator('input[name="descripcion"]').fill('PW-TEST bien para carga masiva de fotos');
        await page.getByRole('button', { name: 'Registrar bien' }).click();
        await expect(page).toHaveURL(/\/bienes$/);
    }

    await page.goto('bienes/carga-masiva-fotos');
    await page.setInputFiles('input[name="archivo"]', zipFixture);
    await page.getByRole('button', { name: 'Subir .zip' }).click();

    // La primera vez el bien cae en "emparejadas"; en corridas siguientes ya tiene
    // foto, así que cae en "ya tenían foto" -en ningún caso debe aparecer como "sin
    // bien correspondiente" ni "formato no válido", que son las fallas reales a
    // descartar.
    const seccionSinBien = page.locator('div.mb-3', {
        has: page.getByRole('heading', { name: 'Bienes sin foto encontrada en el .zip' }),
    });
    await expect(seccionSinBien.getByText(CODIGO, { exact: false })).toHaveCount(0);

    const seccionFormatoInvalido = page.locator('div.mb-3', {
        has: page.getByRole('heading', { name: 'Archivos con formato no válido' }),
    });
    await expect(seccionFormatoInvalido.getByText(CODIGO, { exact: false })).toHaveCount(0);

    // Confirmación positiva: el bien efectivamente tiene una foto ahora. El campo
    // trae además un <img> de vista previa (oculto, d-none) para cuando se toma una
    // foto en vivo -se excluye para no matchear los dos.
    await page.goto('bienes?q=' + CODIGO);
    await page.locator('tr', { hasText: CODIGO }).getByRole('link', { name: 'Editar' }).click();
    await expect(page.locator('.campo-foto img:not(.campo-foto-preview)')).toBeVisible();
});
