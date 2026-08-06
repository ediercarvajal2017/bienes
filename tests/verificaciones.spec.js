import { test, expect } from '@playwright/test';

/**
 * Solo puede haber una jornada de verificación activa por institución — si una
 * corrida anterior de esta prueba quedó a medias (falló antes de cerrarla), la
 * siguiente corrida no podría crear una nueva. Por eso se cierra cualquier jornada
 * activa que encuentre antes de empezar, en vez de asumir que no hay ninguna.
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

test('crear una jornada, verificar un bien y cerrarla', async ({ page }) => {
    await cerrarJornadaActivaSiExiste(page);

    const sufijo = Date.now();
    const codigoBien = `PW-TEST-VERIF-${sufijo}`;
    const descripcionBien = `PW-TEST bien para verificación ${sufijo}`;

    await page.goto('bienes/crear');
    await page.locator('input[name="codigo_identificacion"]').fill(codigoBien);
    await page.locator('input[name="descripcion"]').fill(descripcionBien);
    await page.getByRole('button', { name: 'Registrar bien' }).click();
    await expect(page).toHaveURL(/\/bienes$/);

    const filaBien = page.locator('tr', { hasText: codigoBien });
    await filaBien.getByRole('link', { name: 'Editar' }).click();
    const href = await page.getByRole('link', { name: 'Ver ficha pública' }).getAttribute('href');
    const token = href.split('/').filter(Boolean).pop();

    await page.goto('verificaciones/crear');
    await page.locator('input[name="nombre"]').fill(`PW-TEST Jornada ${sufijo}`);
    await page.getByRole('button', { name: 'Iniciar jornada' }).click();
    await expect(page).toHaveURL(/\/verificaciones\/\d+$/);
    const urlJornada = page.url();

    // Verificar el bien desde su ficha pública (lo mismo que escanear su QR).
    await page.goto(`qr/${token}`);
    await page.getByRole('button', { name: 'Confirmar: el bien está aquí' }).click();
    await expect(page).toHaveURL(new RegExp(`/qr/${token}$`));
    await expect(page.getByText('Ya verificado por')).toBeVisible();

    // Confirmarlo también desde la jornada, en la pestaña "Verificados".
    await page.goto(urlJornada);
    await page.getByRole('link', { name: /Verificados/ }).click();
    await page.locator('#buscadorOk').fill(codigoBien);
    await page.waitForURL(/qOk=/);
    await expect(page.locator('tr', { hasText: codigoBien })).toBeVisible();

    page.once('dialog', (dialog) => dialog.accept());
    await page.getByRole('button', { name: 'Cerrar jornada' }).click();

    await expect(page.locator('.alert-success')).toBeVisible();
    await expect(page.getByText(/Cerrada el/)).toBeVisible();
});
