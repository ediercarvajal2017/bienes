import { test, expect } from '@playwright/test';

/**
 * Reporta una baja desde la ficha pública /qr/{token}/baja y la aprueba desde /bajas.
 * Con una sola cuenta (superusuario) no se puede probar la separación real de roles
 * (quien reporta normalmente no es quien aprueba) — cubre el flujo funcional, no el
 * control de permisos entre roles distintos. Ver tests/README.md.
 */
test('reportar una baja y aprobarla', async ({ page }) => {
    const sufijo = Date.now();
    const codigoBien = `PW-TEST-BAJA-${sufijo}`;
    const descripcionBien = `PW-TEST bien para baja ${sufijo}`;

    await page.goto('bienes/crear');
    await page.locator('input[name="codigo_identificacion"]').fill(codigoBien);
    await page.locator('input[name="descripcion"]').fill(descripcionBien);
    await page.getByRole('button', { name: 'Registrar bien' }).click();

    await expect(page).toHaveURL(/\/bienes$/);
    const filaBien = page.locator('tr', { hasText: codigoBien });
    await filaBien.getByRole('link', { name: 'Editar' }).click();

    // El token del QR viaja en el enlace "Ver ficha pública" (/qr/{token}).
    const href = await page.getByRole('link', { name: 'Ver ficha pública' }).getAttribute('href');
    const token = href.split('/').filter(Boolean).pop();

    await page.goto(`qr/${token}/baja`);
    await page.locator('input[name="estado_reportado"]').fill('Dañado');
    await page.locator('textarea[name="descripcion"]').fill('PW-TEST: motivo de prueba de la baja');
    await page.getByRole('button', { name: 'Enviar reporte' }).click();

    // qr/mostrar.php no muestra el mensaje flash (no lo lee de la sesión) — se confirma
    // el éxito viendo que el reporte aparece pendiente en /bajas, no con una alerta acá.
    await expect(page).toHaveURL(new RegExp(`/qr/${token}$`));

    await page.goto('bajas');
    let filaBaja = page.locator('tr', { hasText: codigoBien });
    await expect(filaBaja).toBeVisible();
    await expect(filaBaja.locator('.badge')).toHaveText('Pendiente');

    await filaBaja.getByRole('button', { name: 'Aprobar' }).click();

    await expect(page.locator('.alert-success')).toBeVisible();
    filaBaja = page.locator('tr', { hasText: codigoBien });
    await expect(filaBaja.locator('.badge')).toHaveText('Aprobada');
});
