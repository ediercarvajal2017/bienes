import { test, expect } from '@playwright/test';
import { execFileSync } from 'node:child_process';
import fs from 'node:fs';
import os from 'node:os';
import path from 'node:path';
import { PHP_BIN } from '../playwright.config.js';
import { datos, comoRol, bd } from './helpers/datos.js';
import { confirmar } from './helpers/confirmacion.js';

// Solo en modo local: crea lotes directamente en la base de pruebas.
test.beforeEach(() => test.skip(datos().remoto === true, 'Necesita la base de pruebas local'));

/**
 * Estado de los lotes de reintegro: pendiente → reintegrado (entregado a la Alcaldía),
 * reabrir, y que todo sea "todo o nada" (un lote que no cumple no deja cambiar ninguno).
 *
 * Los lotes se crean por SQL (rápido y sin depender de otras pantallas), cada uno con su
 * propia fecha para poder aislarlo con el filtro "desde/hasta" de la lista.
 */
let contador = 0;

/** Lote PENDIENTE con un bien reintegrado por cada categoría ('Muebles', 'Tecnolog'…). */
function crearLote(institucion, categorias, fecha) {
    const d = datos();
    const sufijo = `${Date.now()}-${++contador}`;
    const bienes = categorias.map((categoria, i) => `
        INSERT INTO bienes (institucion_id, codigo_identificacion, descripcion, categoria_id, fecha_ingreso, valor, estado, qr_token, created_by)
        VALUES (${institucion}, 'PW-LT-${sufijo}-${i}', 'PW-TEST bien de lote ${sufijo}-${i}',
                (SELECT id FROM categorias_bienes WHERE institucion_id = ${institucion} AND nombre LIKE '${categoria}%' ORDER BY id LIMIT 1),
                '2024-01-15', 1000, 'reintegrado', UUID(), ${d.usuarios.superusuario.id});
        INSERT INTO movimientos (bien_id, tipo, fecha, responsable_id, destino_texto, lote_reintegro_id)
        VALUES (LAST_INSERT_ID(), 'reintegro', '${fecha}', ${d.usuarios.rector.id}, 'Bodega de reintegro', @lote);`).join('');

    return Number(bd(`
        INSERT INTO lotes_reintegro (institucion_id, fecha, destino_texto, registrado_por)
        VALUES (${institucion}, '${fecha}', 'PW-TEST lote ${sufijo}', ${d.usuarios.rector.id});
        SET @lote = LAST_INSERT_ID();
        ${bienes}
        SELECT @lote;`));
}

function estadoDe(lote) {
    // Con "|": bd() recorta los espacios y tabuladores del final.
    return bd(`SELECT CONCAT_WS('|', estado, IFNULL(reintegrado_fecha, ''), IFNULL(reintegrado_nota, '')) FROM lotes_reintegro WHERE id = ${lote}`);
}

async function sesionDe(browser, rol) {
    const contexto = await comoRol(browser, rol);
    const pagina = await contexto.newPage();
    await pagina.goto('reintegros/lotes');
    const token = await pagina.locator('input[name="_csrf"]').first().inputValue();
    return { contexto, pagina, token };
}

/** POST de formulario con campos repetidos (lotes[]=…&lotes[]=…). */
function postFormulario(contexto, url, campos) {
    const cuerpo = new URLSearchParams();
    for (const [nombre, valor] of campos) {
        cuerpo.append(nombre, String(valor));
    }
    return contexto.request.post(url, {
        data: cuerpo.toString(),
        headers: { 'Content-Type': 'application/x-www-form-urlencoded' },
        // Sin seguir la redirección: así el mensaje queda para la página que se abre después.
        maxRedirects: 0,
    });
}

const filaDeLote = (pagina, lote) => pagina.locator('tbody tr', {
    has: pagina.locator('td[data-label="Lote"] .mono', { hasText: new RegExp(`^#${lote}$`) }),
});

test('marcar varios lotes como reintegrados desde la lista', async ({ browser }) => {
    const d = datos();
    const loteMuebles = crearLote(d.instituciones.A, ['Muebles'], '2024-05-10');
    const loteMixto = crearLote(d.instituciones.A, ['Muebles', 'Tecnolog'], '2024-05-11');
    const rector = await sesionDe(browser, 'rector');
    const { pagina } = rector;

    await pagina.goto('reintegros/lotes?estado=pendiente&desde=2024-05-10&hasta=2024-05-11');
    await expect(filaDeLote(pagina, loteMuebles)).toBeVisible();
    await expect(filaDeLote(pagina, loteMuebles).locator('td[data-label="Estado"]')).toContainText(/Pendiente\s*hace \d+ años?/);
    await expect(filaDeLote(pagina, loteMixto).locator('td[data-label="Categoría"]')).toContainText('(mixto)');

    // Sin selección, el botón está desactivado.
    await expect(pagina.locator('#botonMostrarMarcar')).toBeDisabled();
    await filaDeLote(pagina, loteMuebles).locator('.casilla-lote').check();
    await filaDeLote(pagina, loteMixto).locator('.casilla-lote').check();
    await pagina.locator('#botonMostrarMarcar').click();
    await expect(pagina.locator('#panelMarcar')).toBeVisible();
    await pagina.locator('#campoFechaEntrega').fill('2024-06-01');
    await pagina.locator('#campoNota').fill('PW-TEST recibido por Bienes Muebles');

    await confirmar(pagina, () => pagina.locator('#botonConfirmarMarcar').click());

    await expect(pagina.locator('.alert-success')).toContainText('2 lotes marcados como reintegrados');
    // Vuelve a la misma vista filtrada, donde ya no aparecen.
    await expect(pagina).toHaveURL(/estado=pendiente/);
    await expect(filaDeLote(pagina, loteMuebles)).toHaveCount(0);
    await expect(filaDeLote(pagina, loteMixto)).toHaveCount(0);

    expect(estadoDe(loteMuebles)).toBe('reintegrado|2024-06-01|PW-TEST recibido por Bienes Muebles');
    expect(estadoDe(loteMixto)).toBe('reintegrado|2024-06-01|PW-TEST recibido por Bienes Muebles');
    expect(bd(`SELECT reintegrado_por FROM lotes_reintegro WHERE id = ${loteMuebles}`)).toBe(String(d.usuarios.rector.id));
    expect(bd(`SELECT COUNT(*) FROM auditoria WHERE entidad = 'lote_reintegro' AND accion = 'marcar_reintegrado'
               AND entidad_id IN (${loteMuebles}, ${loteMixto})`), 'una auditoría por lote').toBe('2');

    // En "Reintegrados" se ven con su fecha de entrega, y el Excel se sigue descargando.
    await pagina.goto('reintegros/lotes?estado=reintegrado&desde=2024-05-10&hasta=2024-05-11');
    await expect(filaDeLote(pagina, loteMuebles).locator('td[data-label="Estado"]')).toContainText('Entregado el 2024-06-01');
    await expect(filaDeLote(pagina, loteMuebles).locator('.casilla-lote')).toHaveCount(0);
    const excel = await rector.contexto.request.get(`reintegros/lotes/${loteMuebles}/formato.xlsx`);
    expect(excel.status()).toBe(200);
    expect(excel.headers()['content-type']).toContain('spreadsheetml');

    await rector.contexto.close();
});

test('todo o nada: no se marca un lote ya reintegrado ni uno de otra institución', async ({ browser }) => {
    const d = datos();
    const lotePendiente = crearLote(d.instituciones.A, ['Muebles'], '2024-07-01');
    const loteYaEntregado = crearLote(d.instituciones.A, ['Muebles'], '2024-07-02');
    bd(`UPDATE lotes_reintegro SET estado = 'reintegrado', reintegrado_fecha = '2024-07-05' WHERE id = ${loteYaEntregado}`);

    // Rector de A: uno pendiente + uno ya reintegrado → no cambia ninguno.
    const rector = await sesionDe(browser, 'rector');
    await postFormulario(rector.contexto, 'reintegros/lotes/marcar-reintegrados', [
        ['_csrf', rector.token], ['lotes[]', lotePendiente], ['lotes[]', loteYaEntregado], ['fecha_entrega', '2024-08-01'],
    ]);
    await rector.pagina.goto('reintegros/lotes');
    await expect(rector.pagina.locator('.alert-danger')).toContainText(`El lote #${loteYaEntregado} ya no está pendiente`);
    expect(estadoDe(lotePendiente)).toBe('pendiente||');
    expect(estadoDe(loteYaEntregado), 'conserva su fecha de entrega').toBe('reintegrado|2024-07-05|');

    // Fecha futura: rechazada.
    await postFormulario(rector.contexto, 'reintegros/lotes/marcar-reintegrados', [
        ['_csrf', rector.token], ['lotes[]', lotePendiente], ['fecha_entrega', '2999-01-01'],
    ]);
    await rector.pagina.goto('reintegros/lotes');
    await expect(rector.pagina.locator('.alert-danger')).toContainText('posterior a hoy');
    expect(estadoDe(lotePendiente)).toBe('pendiente||');
    await rector.contexto.close();

    // Rector de B: no puede tocar un lote de A.
    const rectorB = await sesionDe(browser, 'rector_b');
    await postFormulario(rectorB.contexto, 'reintegros/lotes/marcar-reintegrados', [
        ['_csrf', rectorB.token], ['lotes[]', lotePendiente], ['fecha_entrega', '2024-08-01'],
    ]);
    await rectorB.pagina.goto('reintegros/lotes');
    await expect(rectorB.pagina.locator('.alert-danger')).toContainText('No tienes acceso');
    expect(estadoDe(lotePendiente)).toBe('pendiente||');
    await rectorB.contexto.close();
});

test('reabrir un lote: el secretario no puede; el rector sí, con motivo', async ({ browser }) => {
    const d = datos();
    const lote = crearLote(d.instituciones.A, ['Muebles'], '2024-09-01');
    bd(`UPDATE lotes_reintegro SET estado = 'reintegrado', reintegrado_fecha = '2024-09-03', reintegrado_por = ${d.usuarios.rector.id} WHERE id = ${lote}`);

    const secretario = await sesionDe(browser, 'secretario');
    const intento = await secretario.contexto.request.post(`reintegros/lotes/${lote}/reabrir`, {
        form: { _csrf: secretario.token, motivo: 'Intento del secretario' }, maxRedirects: 0,
    });
    expect(intento.status()).toBe(403);
    // El secretario ve el estado, pero no la opción de reabrir.
    await secretario.pagina.goto(`reintegros/lotes/${lote}`);
    await expect(secretario.pagina.locator('.estado-lote')).toContainText('entregado a la Alcaldía el 2024-09-03');
    await expect(secretario.pagina.getByText('Reabrir el lote')).toHaveCount(0);
    await secretario.contexto.close();
    expect(estadoDe(lote)).toBe('reintegrado|2024-09-03|');

    const rector = await sesionDe(browser, 'rector');
    await rector.pagina.goto(`reintegros/lotes/${lote}`);
    await rector.pagina.getByText('Reabrir el lote').click();
    await rector.pagina.locator('#campoMotivo').fill('PW-TEST se marcó el lote equivocado');
    await confirmar(rector.pagina, () => rector.pagina.getByRole('button', { name: 'Reabrir', exact: true }).click());

    await expect(rector.pagina.locator('.alert-success').first()).toContainText('volvió a quedar pendiente');
    await expect(rector.pagina.getByRole('button', { name: 'Marcar como reintegrado' })).toBeVisible();
    expect(estadoDe(lote)).toBe('pendiente||');
    expect(bd(`SELECT COUNT(*) FROM auditoria WHERE entidad = 'lote_reintegro' AND accion = 'reabrir' AND entidad_id = ${lote}`)).toBe('1');

    // Marcarlo de nuevo desde el detalle.
    await rector.pagina.locator('#campoFechaEntrega').fill('2024-09-10');
    await confirmar(rector.pagina, () => rector.pagina.getByRole('button', { name: 'Marcar como reintegrado' }).click());
    await expect(rector.pagina.locator('.alert-success').first()).toContainText('Lote marcado como reintegrado');
    expect(estadoDe(lote)).toBe('reintegrado|2024-09-10|');
    await rector.contexto.close();
});

/** Reintegro SUELTO (sin lote) de un bien de la categoría indicada; null = sin categoría. */
function crearReintegroSuelto(institucion, categoria) {
    const d = datos();
    const codigo = `PW-RS-${Date.now()}-${++contador}`;
    const categoriaSql = categoria === null
        ? 'NULL'
        : `(SELECT id FROM categorias_bienes WHERE institucion_id = ${institucion} AND nombre LIKE '${categoria}%' ORDER BY id LIMIT 1)`;
    const movimiento = Number(bd(`
        INSERT INTO bienes (institucion_id, codigo_identificacion, descripcion, categoria_id, fecha_ingreso, valor, estado, qr_token, created_by)
        VALUES (${institucion}, '${codigo}', 'PW-TEST reintegro suelto ${codigo}', ${categoriaSql}, '2024-01-15', 1000, 'reintegrado', UUID(), ${d.usuarios.superusuario.id});
        INSERT INTO movimientos (bien_id, tipo, fecha, responsable_id, destino_texto)
        VALUES (LAST_INSERT_ID(), 'reintegro', '2026-11-01', ${d.usuarios.rector.id}, 'Bodega de reintegro');
        SELECT LAST_INSERT_ID();`));
    return { codigo, movimiento };
}

test('generar lote: un lote por categoría', async ({ browser }) => {
    const d = datos();
    const muebles = crearReintegroSuelto(d.instituciones.A, 'Muebles');
    const tecnologia = crearReintegroSuelto(d.instituciones.A, 'Tecnolog');
    const sinCategoria = crearReintegroSuelto(d.instituciones.A, null);
    const rector = await sesionDe(browser, 'rector');
    const { pagina } = rector;

    await pagina.goto('reintegros/lotes/generar?porPagina=0');
    await expect(pagina.getByText('un lote por cada categoría')).toBeVisible();
    await expect(pagina.locator('tr', { hasText: muebles.codigo }).locator('td[data-label="Categoría"]')).toHaveText('Muebles');
    await expect(pagina.locator('tr', { hasText: sinCategoria.codigo }).locator('td[data-label="Categoría"]')).toHaveText('Sin categoría');
    for (const r of [muebles, tecnologia, sinCategoria]) {
        await pagina.locator('tr', { hasText: r.codigo }).locator('.casilla-bien').check();
    }
    await pagina.locator('input[name="descripcion"]').fill('PW-TEST entrega por categoría');

    await confirmar(pagina, () => pagina.locator('#botonGenerar').click());

    await expect(pagina).toHaveURL(/\/reintegros\/lotes\?estado=pendiente$/);
    await expect(pagina.locator('.alert-success')).toContainText(/Se generaron 3 lotes, uno por categoría: #\d+ Muebles \(1 bien\), #\d+ Tecnología \(1 bien\) y #\d+ Sin categoría \(1 bien\)\./);

    // Cada reintegro quedó en un lote distinto, y cada lote tiene una sola categoría.
    const ids = `${muebles.movimiento}, ${tecnologia.movimiento}, ${sinCategoria.movimiento}`;
    expect(bd(`SELECT COUNT(DISTINCT lote_reintegro_id), COUNT(lote_reintegro_id) FROM movimientos WHERE id IN (${ids})`)).toBe('3\t3');
    expect(bd(`SELECT COUNT(*) FROM (
                 SELECT m.lote_reintegro_id FROM movimientos m JOIN bienes b ON b.id = m.bien_id
                 WHERE m.lote_reintegro_id IN (SELECT lote_reintegro_id FROM movimientos WHERE id IN (${ids}))
                 GROUP BY m.lote_reintegro_id HAVING COUNT(DISTINCT IFNULL(b.categoria_id, 0)) > 1) mezclados`), 'ningún lote mezcla categorías').toBe('0');
    expect(bd(`SELECT COUNT(*) FROM lotes_reintegro WHERE estado = 'pendiente' AND destino_texto LIKE 'PW-TEST entrega por categor%'
               AND id IN (SELECT lote_reintegro_id FROM movimientos WHERE id IN (${ids}))`)).toBe('3');
    await rector.contexto.close();
});

/** Títulos de las hojas de un .xlsx descargado. */
function hojasDelExcel(cuerpo) {
    const ruta = path.join(os.tmpdir(), `lote-${Date.now()}-${Math.random().toString(16).slice(2)}.xlsx`);
    fs.writeFileSync(ruta, cuerpo);
    try {
        return JSON.parse(execFileSync(PHP_BIN, ['-r',
            `require 'vendor/autoload.php'; echo json_encode(PhpOffice\\PhpSpreadsheet\\IOFactory::load($argv[1])->getSheetNames());`, ruta,
        ], { encoding: 'utf8' }));
    } finally {
        fs.unlinkSync(ruta);
    }
}

const lotesDestinoDe = (origenes) => bd(`SELECT DISTINCT lote_destino_id FROM lotes_reintegro_unificaciones
    WHERE lote_origen_id IN (${origenes.join(',')}) ORDER BY lote_destino_id`).split('\n').filter(Boolean).map(Number);

test('unificar lotes pendientes: un lote nuevo por categoría', async ({ browser }) => {
    const d = datos();
    const loteMixto = crearLote(d.instituciones.A, ['Muebles', 'Tecnolog'], '2024-10-01');
    const loteMuebles = crearLote(d.instituciones.A, ['Muebles', 'Muebles'], '2024-10-02');
    const rector = await sesionDe(browser, 'rector');
    const { pagina } = rector;

    await pagina.goto('reintegros/lotes?estado=pendiente&desde=2024-10-01&hasta=2024-10-02');
    await expect(pagina.locator('#botonUnificar')).toBeDisabled();
    await filaDeLote(pagina, loteMixto).locator('.casilla-lote').check();
    await filaDeLote(pagina, loteMuebles).locator('.casilla-lote').check();
    await pagina.locator('#botonUnificar').click();

    // Vista previa: no cambia nada todavía.
    await expect(pagina).toHaveURL(/\/reintegros\/lotes\/unificar\?/);
    await expect(pagina.getByRole('heading', { name: 'Unificar lotes' })).toBeVisible();
    await expect(pagina.getByRole('heading', { name: 'Se crearán 2 lotes nuevos' })).toBeVisible();
    const tarjetaMuebles = pagina.locator('.card', { hasText: 'Muebles' }).filter({ hasText: 'Viene de' });
    await expect(tarjetaMuebles).toContainText(`Viene de #${loteMixto} y #${loteMuebles}`);
    await expect(tarjetaMuebles).toContainText('3');
    await expect(pagina.locator('.card', { hasText: 'Tecnología' }).filter({ hasText: 'Viene de' })).toContainText(`Viene de #${loteMixto}`);
    expect(bd(`SELECT GROUP_CONCAT(estado) FROM lotes_reintegro WHERE id IN (${loteMixto}, ${loteMuebles})`)).toBe('pendiente,pendiente');

    await confirmar(pagina, () => pagina.locator('#botonConfirmarUnificar').click());

    await expect(pagina).toHaveURL(/\/reintegros\/lotes\?estado=pendiente$/);
    await expect(pagina.locator('.alert-success')).toContainText(/Listo: 2 lotes nuevos, uno por categoría: #\d+ Muebles \(3 bienes\) y #\d+ Tecnología \(1 bien\)\./);

    // Los de origen quedan "unificado" y vacíos; los nuevos tienen los 4 bienes, uno por categoría.
    expect(bd(`SELECT GROUP_CONCAT(estado) FROM lotes_reintegro WHERE id IN (${loteMixto}, ${loteMuebles})`)).toBe('unificado,unificado');
    expect(bd(`SELECT COUNT(*) FROM movimientos WHERE lote_reintegro_id IN (${loteMixto}, ${loteMuebles})`)).toBe('0');
    const nuevos = lotesDestinoDe([loteMixto, loteMuebles]);
    expect(nuevos).toHaveLength(2);
    expect(bd(`SELECT COUNT(*) FROM movimientos WHERE lote_reintegro_id IN (${nuevos.join(',')})`), 'ningún bien se pierde').toBe('4');
    expect(bd(`SELECT COUNT(*) FROM lotes_reintegro_unificaciones WHERE lote_origen_id IN (${loteMixto}, ${loteMuebles})`)).toBe('3');
    expect(bd(`SELECT COUNT(*) FROM auditoria WHERE entidad = 'lote_reintegro' AND accion = 'unificar' AND entidad_id IN (${loteMixto}, ${loteMuebles})`)).toBe('2');
    const loteNuevoMuebles = Number(bd(`SELECT m.lote_reintegro_id FROM movimientos m JOIN bienes b ON b.id = m.bien_id
        JOIN categorias_bienes c ON c.id = b.categoria_id WHERE m.lote_reintegro_id IN (${nuevos.join(',')}) AND c.nombre = 'Muebles' LIMIT 1`));

    // El Excel del lote nuevo de Muebles solo trae hojas de Muebles.
    const excel = await rector.contexto.request.get(`reintegros/lotes/${loteNuevoMuebles}/formato.xlsx`);
    expect(excel.status()).toBe(200);
    const hojas = hojasDelExcel(await excel.body());
    expect(hojas.length).toBeGreaterThan(0);
    expect(hojas.every((h) => h.includes('Muebles'))).toBe(true);

    // El de origen ya no descarga Excel y enlaza a los nuevos; el nuevo dice de dónde salió.
    const excelOrigen = await rector.contexto.request.get(`reintegros/lotes/${loteMixto}/formato.xlsx`, { maxRedirects: 0 });
    expect(excelOrigen.status()).toBe(302);
    await pagina.goto(`reintegros/lotes/${loteMixto}`);
    await expect(pagina.locator('.estado-lote')).toContainText('Unificado');
    for (const nuevo of nuevos) {
        await expect(pagina.locator('.estado-lote').getByRole('link', { name: `#${nuevo}` })).toBeVisible();
    }
    await pagina.goto(`reintegros/lotes/${loteNuevoMuebles}`);
    await expect(pagina.getByText(`Formado a partir de los lotes #${loteMixto} y #${loteMuebles}.`)).toBeVisible();
    await rector.contexto.close();
});

test('unificar: todo o nada y reglas', async ({ browser }) => {
    const d = datos();
    const loteEntregado = crearLote(d.instituciones.A, ['Muebles'], '2024-11-01');
    bd(`UPDATE lotes_reintegro SET estado = 'reintegrado', reintegrado_fecha = '2024-11-05' WHERE id = ${loteEntregado}`);
    const lotePendiente = crearLote(d.instituciones.A, ['Muebles'], '2024-11-02');
    const loteOtraSede = crearLote(d.instituciones.B, ['Muebles'], '2024-11-03');

    // Con un lote ya reintegrado: no se toca nada.
    const rector = await sesionDe(browser, 'rector');
    await postFormulario(rector.contexto, 'reintegros/lotes/unificar', [
        ['_csrf', rector.token], ['lotes[]', loteEntregado], ['lotes[]', lotePendiente],
    ]);
    await rector.pagina.goto('reintegros/lotes');
    await expect(rector.pagina.locator('.alert-danger')).toContainText(`El lote #${loteEntregado} ya no está pendiente`);
    expect(estadoDe(lotePendiente)).toBe('pendiente||');
    expect(bd(`SELECT COUNT(*) FROM movimientos WHERE lote_reintegro_id = ${lotePendiente}`)).toBe('1');
    expect(bd(`SELECT COUNT(*) FROM lotes_reintegro_unificaciones WHERE lote_origen_id IN (${loteEntregado}, ${lotePendiente})`)).toBe('0');

    // Un lote de una sola categoría: nada que separar (desde la vista previa).
    await rector.pagina.goto(`reintegros/lotes/unificar?lotes[]=${lotePendiente}`);
    await expect(rector.pagina).toHaveURL(/\/reintegros\/lotes$/);
    await expect(rector.pagina.locator('.alert-danger')).toContainText('ya es de una sola categoría');

    // Un lote de otra institución: sin acceso.
    await rector.pagina.goto(`reintegros/lotes/unificar?lotes[]=${lotePendiente}&lotes[]=${loteOtraSede}`);
    await expect(rector.pagina.locator('.alert-danger')).toContainText(`No tienes acceso al lote #${loteOtraSede}`);
    await rector.contexto.close();

    // El superusuario tampoco puede mezclar sedes.
    const superusuario = await sesionDe(browser, 'superusuario');
    await postFormulario(superusuario.contexto, 'reintegros/lotes/unificar', [
        ['_csrf', superusuario.token], ['lotes[]', lotePendiente], ['lotes[]', loteOtraSede],
    ]);
    await superusuario.pagina.goto('reintegros/lotes');
    await expect(superusuario.pagina.locator('.alert-danger')).toContainText('misma sede');
    expect(estadoDe(lotePendiente)).toBe('pendiente||');
    expect(estadoDe(loteOtraSede)).toBe('pendiente||');
    await superusuario.contexto.close();
});

test('separar un lote mixto por categoría desde su detalle', async ({ browser }) => {
    const d = datos();
    const loteMixto = crearLote(d.instituciones.A, ['Muebles', 'Tecnolog', 'Muebles'], '2024-12-01');
    const rector = await sesionDe(browser, 'rector');
    const { pagina } = rector;

    await pagina.goto(`reintegros/lotes/${loteMixto}`);
    await pagina.getByRole('link', { name: 'Separar por categoría' }).click();
    await expect(pagina.getByRole('heading', { name: `Separar el lote #${loteMixto} por categoría` })).toBeVisible();

    await confirmar(pagina, () => pagina.getByRole('button', { name: 'Confirmar separación' }).click());
    await expect(pagina.locator('.alert-success')).toContainText(/Listo: 2 lotes nuevos, uno por categoría: #\d+ Muebles \(2 bienes\) y #\d+ Tecnología \(1 bien\)\./);

    expect(estadoDe(loteMixto)).toBe('unificado||');
    const nuevos = lotesDestinoDe([loteMixto]);
    expect(nuevos).toHaveLength(2);
    expect(bd(`SELECT COUNT(*) FROM lotes_reintegro WHERE id IN (${nuevos.join(',')}) AND destino_texto LIKE 'Separaci%n por categor%a del lote #${loteMixto}'`)).toBe('2');
    await rector.contexto.close();
});
