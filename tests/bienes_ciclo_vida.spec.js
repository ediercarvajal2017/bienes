import { test, expect } from '@playwright/test';
import { seleccionarTomSelect, seleccionarPrimeraOpcionTomSelect } from './helpers/tomSelect.js';

/**
 * A diferencia de los demás catálogos, un bien nunca se puede borrar en SIGEBI (solo
 * cambia de estado) — por eso esta prueba no "limpia" el bien al final, queda como
 * historial reintegrado, igual que ya documenta tests/README.md para
 * carga_masiva.spec.js. Los dos espacios de apoyo sí se limpian (van a la papelera).
 */
test('un bien recorre crear → asignar → trasladar → reintegrar', async ({ page }) => {
    const sufijo = Date.now();
    const codigoBien = `PW-TEST-CICLO-${sufijo}`;
    const descripcionBien = `PW-TEST bien de ciclo de vida ${sufijo}`;
    const nombreEspacioA = `PW-TEST-EspacioA-${sufijo}`;
    const nombreEspacioB = `PW-TEST-EspacioB-${sufijo}`;

    async function crearEspacio(codigo, nombre) {
        await page.goto('espacios/crear');
        await page.locator('input[name="codigo"]').fill(codigo);
        await page.locator('input[name="nombre"]').fill(nombre);
        await seleccionarTomSelect(page, 'responsables', 'Edier');
        await page.getByRole('button', { name: 'Registrar espacio' }).click();
        await expect(page).toHaveURL(/\/espacios$/);
    }

    // Dos espacios: uno para la asignación inicial, otro para el traslado.
    await crearEspacio(`PWTA-${sufijo}`, nombreEspacioA);
    await crearEspacio(`PWTB-${sufijo}`, nombreEspacioB);

    // --- Crear el bien ---
    // La categoría no es obligatoria para CREAR un bien, pero SIGEBI sí la exige más
    // adelante para poder reintegrarlo ("Este bien no tiene categoría asignada..."),
    // así que se elige una desde ahora para no toparse con eso recién en el último paso.
    await page.goto('bienes/crear');
    await page.locator('input[name="codigo_identificacion"]').fill(codigoBien);
    await page.locator('input[name="descripcion"]').fill(descripcionBien);
    await seleccionarPrimeraOpcionTomSelect(page, 'categoriaBien');
    await page.getByRole('button', { name: 'Registrar bien' }).click();

    await expect(page).toHaveURL(/\/bienes$/);
    const filaBien = page.locator('tr', { hasText: codigoBien });
    await expect(filaBien).toBeVisible();

    await filaBien.getByRole('link', { name: 'Editar' }).click();
    await expect(page).toHaveURL(/\/bienes\/\d+\/editar/);

    // --- Asignar al espacio A ---
    await page.locator('#panelAsignar summary').click();
    await seleccionarTomSelect(page, 'espacioAsignar', nombreEspacioA);
    await page.locator('#panelAsignar').getByRole('button', { name: 'Asignar' }).click();

    await expect(page.locator('.alert-success')).toBeVisible();
    // .fw-semibold es el nombre del espacio en la tarjeta "Asignación activa" -sin
    // acotar ahí, getByText también matchea la misma opción dentro de los <select>
    // (ocultos, pero con el mismo texto) de los paneles Asignar/Trasladar.
    await expect(page.locator('.fw-semibold', { hasText: nombreEspacioA })).toBeVisible();

    // --- Trasladar al espacio B ---
    await page.locator('#panelTrasladar summary').click();
    await seleccionarTomSelect(page, 'espacioTrasladar', nombreEspacioB);
    await page.locator('#panelTrasladar').getByRole('button', { name: 'Registrar traslado' }).click();

    await expect(page.locator('.alert-success')).toBeVisible();
    await expect(page.locator('.fw-semibold', { hasText: nombreEspacioB })).toBeVisible();

    // --- Reintegrar ---
    await page.locator('#panelReintegrar summary').click();
    await page.locator('#panelReintegrar input[name="destino_texto"]').fill('PW-TEST Almacén institucional');
    await page.locator('#panelReintegrar').getByRole('button', { name: 'Registrar reintegro' }).click();

    await expect(page.locator('.alert-success')).toBeVisible();
    // Un bien reintegrado muestra el estado como texto deshabilitado, no como badge
    // (esa clase solo existe en el listado de /bienes, no en esta pantalla de edición).
    await expect(page.locator('input[disabled]')).toHaveValue('Reintegrado');

    // --- Limpieza de los espacios de apoyo ---
    // A esta altura ya tienen historial (asignación y/o traslado), así que SIGEBI
    // rechaza eliminarlos ("tiene asignaciones o movimientos registrados") — el mismo
    // mensaje sugiere desactivarlos en su lugar, que es justamente lo que hacemos.
    async function desactivarEspacio(nombre) {
        await page.goto('espacios');
        const fila = page.locator('tr', { hasText: nombre });
        await fila.getByRole('button', { name: 'Desactivar' }).click();
        await expect(page.locator('tr', { hasText: nombre }).locator('.badge')).toHaveText('Inactivo');
    }
    await desactivarEspacio(nombreEspacioA);
    await desactivarEspacio(nombreEspacioB);
});
