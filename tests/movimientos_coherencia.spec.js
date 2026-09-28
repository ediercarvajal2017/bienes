import { test, expect } from '@playwright/test';
import { datos, bd, csrf } from './helpers/datos.js';

/**
 * Coherencia del historial: las operaciones en lote siguen las mismas reglas que la ficha
 * del bien, y ningún movimiento acepta fechas futuras.
 */
test.beforeEach(() => test.skip(datos().remoto === true, 'Necesita la base de pruebas local'));
test.use({ storageState: 'playwright/.auth/rector.json' });

// "Hoy" en la hora de Colombia (APP_TIMEZONE, la que usa MIA), no la del equipo: GitHub
// corre las pruebas en UTC y de 7 p. m. a medianoche allá ya es el día siguiente.
const fechaLocal = (dias = 0) => new Date(Date.now() + dias * 86400000).toLocaleDateString('en-CA', { timeZone: 'America/Bogota' });
const hoy = () => fechaLocal(0);
const manana = () => fechaLocal(1);

/** Un bien activo de la institución A, asignado a un espacio propio, y un segundo espacio. */
function prepararBien(sufijo) {
    const inst = datos().instituciones.A;
    bd(`INSERT INTO espacios (institucion_id, codigo, nombre, activo) VALUES
        (${inst}, 'PWC1-${sufijo}', 'PW Origen ${sufijo}', 1), (${inst}, 'PWC2-${sufijo}', 'PW Destino ${sufijo}', 1)`);
    const [origen, destino] = bd(`SELECT id FROM espacios WHERE codigo IN ('PWC1-${sufijo}','PWC2-${sufijo}') ORDER BY codigo`).split('\n').map(Number);
    bd(`INSERT INTO bienes (institucion_id, codigo_identificacion, descripcion, categoria_id, fecha_ingreso, valor, estado, qr_token)
        SELECT ${inst}, 'PW-COH-${sufijo}', 'PW coherencia ${sufijo}', id, CURDATE(), 1000, 'activo', UUID()
          FROM categorias_bienes WHERE institucion_id = ${inst} AND nombre = 'Muebles' LIMIT 1`);
    const bien = Number(bd(`SELECT id FROM bienes WHERE codigo_identificacion = 'PW-COH-${sufijo}'`));
    bd(`INSERT INTO asignaciones (bien_id, espacio_id, fecha_asignacion, activa) VALUES (${bien}, ${origen}, CURDATE(), 1)`);
    return { bien, origen, destino };
}

async function asignarEnLote(page, bien, espacio, fecha) {
    await page.goto('asignaciones');
    return page.request.post('asignaciones', {
        form: { _csrf: await csrf(page), 'bienes[]': String(bien), espacio_id: String(espacio), fecha_asignacion: fecha },
        maxRedirects: 0,
    });
}

test('la asignación masiva de un bien que ya tenía espacio registra el traslado', async ({ page }) => {
    const { bien, origen, destino } = prepararBien(Date.now());

    await asignarEnLote(page, bien, destino, hoy());

    expect(bd(`SELECT GROUP_CONCAT(espacio_id) FROM asignaciones WHERE bien_id = ${bien} AND activa = 1`)).toBe(String(destino));
    expect(bd(`SELECT COUNT(*) FROM movimientos WHERE bien_id = ${bien} AND tipo = 'traslado'
               AND espacio_origen_id = ${origen} AND espacio_destino_id = ${destino}`)).toBe('1');
    expect(bd(`SELECT accion FROM auditoria WHERE entidad = 'bien' AND entidad_id = ${bien} ORDER BY id DESC LIMIT 1`)).toBe('trasladar');
});

test('la asignación masiva al mismo espacio no repite la asignación', async ({ page }) => {
    const { bien, origen } = prepararBien(Date.now());

    await asignarEnLote(page, bien, origen, hoy());
    await page.goto('asignaciones');
    await expect(page.locator('.alert-danger')).toContainText('ya estaban en ese espacio');

    expect(bd(`SELECT COUNT(*) FROM asignaciones WHERE bien_id = ${bien}`)).toBe('1');
    expect(bd(`SELECT COUNT(*) FROM movimientos WHERE bien_id = ${bien}`)).toBe('0');
});

test('ningún movimiento acepta una fecha futura', async ({ page }) => {
    const { bien, destino } = prepararBien(Date.now());

    // Asignación masiva.
    await asignarEnLote(page, bien, destino, manana());
    await page.goto('asignaciones');
    await expect(page.locator('.alert-danger')).toContainText('no puede ser posterior a hoy');

    // Ficha del bien (traslado). El calendario ya no deja elegirla; se prueba el servidor.
    await page.goto(`bienes/${bien}/editar`);
    await expect(page.locator('#accionFecha')).toHaveAttribute('max', hoy());
    await page.request.post(`bienes/${bien}/trasladar`, {
        form: { _csrf: await csrf(page), espacio_destino_id: String(destino), fecha: manana() },
        maxRedirects: 0,
    });
    await page.goto(`bienes/${bien}/editar`);
    await expect(page.locator('.alert-danger')).toContainText('no puede ser posterior a hoy');

    expect(bd(`SELECT COUNT(*) FROM movimientos WHERE bien_id = ${bien}`)).toBe('0');
    expect(bd(`SELECT COUNT(*) FROM asignaciones WHERE bien_id = ${bien} AND espacio_id = ${destino}`)).toBe('0');
});

test('el reintegro masivo rechaza una fecha futura y registra el reintegro con fecha válida', async ({ page }) => {
    const { bien } = prepararBien(Date.now());
    await page.goto('reintegros');
    const token = await csrf(page);

    await page.request.post('reintegros', {
        form: { _csrf: token, 'bienes[]': String(bien), fecha: manana(), destino_texto: 'Almacén' }, maxRedirects: 0,
    });
    expect(bd(`SELECT estado FROM bienes WHERE id = ${bien}`)).toBe('activo');

    await page.request.post('reintegros', {
        form: { _csrf: token, 'bienes[]': String(bien), fecha: hoy(), destino_texto: 'Almacén' }, maxRedirects: 0,
    });
    expect(bd(`SELECT estado FROM bienes WHERE id = ${bien}`)).toBe('reintegrado');
    expect(bd(`SELECT COUNT(*) FROM movimientos WHERE bien_id = ${bien} AND tipo = 'reintegro'`)).toBe('1');
    expect(bd(`SELECT COUNT(*) FROM asignaciones WHERE bien_id = ${bien} AND activa = 1`)).toBe('0');
});
