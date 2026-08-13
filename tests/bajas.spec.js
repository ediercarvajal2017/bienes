import { test, expect } from '@playwright/test';
import { seleccionarTomSelect } from './helpers/tomSelect.js';

/**
 * Reporta una baja desde la ficha pública /qr/{token}/baja y la aprueba desde /bajas.
 * Con una sola cuenta (superusuario) no se puede probar la separación real de roles
 * (quien reporta normalmente no es quien aprueba) — cubre el flujo funcional, no el
 * control de permisos entre roles distintos. Ver tests/README.md.
 * Solo los bienes de la categoría "Sin cartera" admiten baja directa (ver
 * BajaController::verificarAdmiteBaja()), así que el bien de prueba se crea con esa
 * categoría a propósito.
 */
test('reportar una baja y aprobarla', async ({ page }) => {
    const sufijo = Date.now();
    // La categoría "Sin cartera" exige un código de exactamente 10 dígitos numéricos
    // (ver Categoria::esProtegida()) -- por eso el código, a diferencia de otras
    // pruebas, no lleva el prefijo "PW-TEST"; la descripción sí, y es lo que se usa
    // para ubicar la fila en los listados.
    const codigoBien = String(sufijo).slice(-10);
    const descripcionBien = `PW-TEST bien para baja ${sufijo}`;

    await page.goto('bienes/crear');
    await page.locator('input[name="codigo_identificacion"]').fill(codigoBien);
    await page.locator('input[name="descripcion"]').fill(descripcionBien);
    await seleccionarTomSelect(page, 'categoriaBien', 'Sin cartera');
    await page.getByRole('button', { name: 'Registrar bien' }).click();

    await expect(page).toHaveURL(/\/bienes$/);
    const filaBien = page.locator('tr', { hasText: descripcionBien });
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
    let filaBaja = page.locator('tr', { hasText: descripcionBien });
    await expect(filaBaja).toBeVisible();
    await expect(filaBaja.locator('.badge')).toHaveText('Pendiente');

    await filaBaja.getByRole('button', { name: 'Aprobar' }).click();

    await expect(page.locator('.alert-success')).toBeVisible();
    filaBaja = page.locator('tr', { hasText: descripcionBien });
    await expect(filaBaja.locator('.badge')).toHaveText('Aprobada');
});
