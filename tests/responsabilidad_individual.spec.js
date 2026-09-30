import { test, expect } from '@playwright/test';
import { datos, bd, comoRol, csrf } from './helpers/datos.js';
import { seleccionarTomSelect } from './helpers/tomSelect.js';

/**
 * Responsabilidad de un bien (App\Models\Asignacion):
 *  - Grupal: en un espacio; responden los responsables del espacio.
 *  - Individual: a cargo de una persona; el espacio es opcional ("guardado en") y responde
 *    solo esa persona, no los responsables del espacio donde está guardado.
 */
test.beforeEach(() => test.skip(datos().remoto === true, 'Necesita la base de pruebas local'));

const fechaHoy = () => new Date().toLocaleDateString('en-CA', { timeZone: 'America/Bogota' });
const nombre = (id) => bd(`SELECT CONCAT(nombres, ' ', apellidos) FROM usuarios WHERE id = ${id}`);

/** Un espacio de la institución A cuyo único responsable es $responsableId. */
function crearEspacio(sufijo, etiqueta, responsableId) {
    const inst = datos().instituciones.A;
    bd(`INSERT INTO espacios (institucion_id, codigo, nombre, activo) VALUES (${inst}, 'PWR${etiqueta}-${sufijo}', 'PW ${etiqueta} ${sufijo}', 1)`);
    const id = Number(bd(`SELECT id FROM espacios WHERE codigo = 'PWR${etiqueta}-${sufijo}'`));
    bd(`INSERT INTO espacio_responsables (espacio_id, usuario_id) VALUES (${id}, ${responsableId})`);
    return id;
}

/** Un bien activo de la institución A (categoría Muebles), con la asignación indicada. */
function crearBien(sufijo, etiqueta, { espacio = null, persona = null } = {}) {
    const inst = datos().instituciones.A;
    bd(`INSERT INTO bienes (institucion_id, codigo_identificacion, descripcion, categoria_id, fecha_ingreso, valor, estado, qr_token)
        SELECT ${inst}, 'PWR-${etiqueta}-${sufijo}', 'PW ${etiqueta} ${sufijo}', id, CURDATE(), 1000, 'activo', UUID()
          FROM categorias_bienes WHERE institucion_id = ${inst} AND nombre = 'Muebles' LIMIT 1`);
    const id = Number(bd(`SELECT id FROM bienes WHERE codigo_identificacion = 'PWR-${etiqueta}-${sufijo}'`));
    if (espacio !== null || persona !== null) {
        bd(`INSERT INTO asignaciones (bien_id, usuario_responsable_id, espacio_id, fecha_asignacion, activa)
            VALUES (${id}, ${persona ?? 'NULL'}, ${espacio ?? 'NULL'}, CURDATE(), 1)`);
    }
    return id;
}

const asignacionActiva = (bien) => bd(`SELECT CONCAT(IFNULL(usuario_responsable_id, '-'), '/', IFNULL(espacio_id, '-'))
                                       FROM asignaciones WHERE bien_id = ${bien} AND activa = 1`);

test('registrar un bien con responsabilidad Individual y sin espacio lo deja a cargo de esa persona', async ({ browser }) => {
    const sufijo = Date.now();
    const d = datos();
    const contexto = await comoRol(browser, 'rector');
    const pagina = await contexto.newPage();

    await pagina.goto('bienes/crear');
    await pagina.locator('input[name="codigo_identificacion"]').fill(`PWR-NUEVO-${sufijo}`);
    await pagina.locator('input[name="descripcion"]').fill(`PW portatil ${sufijo}`);
    await expect(pagina.locator('#nuevoPersona')).toBeDisabled();
    await pagina.getByLabel(/Individual/).check();
    await expect(pagina.locator('#datosBien [data-etiqueta-espacio]')).toHaveText('Guardado en (espacio, opcional)');
    await seleccionarTomSelect(pagina, 'nuevoPersona', nombre(d.usuarios.docente.id));
    await pagina.getByRole('button', { name: 'Registrar bien' }).click();

    await expect(pagina).toHaveURL(/\/bienes\/\d+\/editar$/);
    await expect(pagina.locator('.alert-success')).toContainText(`Queda a cargo de ${nombre(d.usuarios.docente.id)}`);
    await expect(pagina.locator('#ubicacionActual')).toContainText('Individual');
    const bien = Number(bd(`SELECT id FROM bienes WHERE codigo_identificacion = 'PWR-NUEVO-${sufijo}'`));
    expect(asignacionActiva(bien)).toBe(`${d.usuarios.docente.id}/-`);
    await contexto.close();

    // El docente lo ve en "Mis bienes".
    const docente = await (await comoRol(browser, 'docente')).newPage();
    await docente.goto(`bienes?q=PWR-NUEVO-${sufijo}`);
    await expect(docente.locator('tbody tr')).toHaveCount(1);
    await expect(docente.locator('tbody tr')).toContainText('Individual');
    await docente.context().close();
});

test('Individual guardado en un espacio: responde solo la persona, no el responsable del espacio', async ({ browser }) => {
    const sufijo = Date.now();
    const d = datos();
    // El docente es el responsable del espacio; el bien individual es del secretario.
    const espacio = crearEspacio(sufijo, 'SALA', d.usuarios.docente.id);
    const grupal = crearBien(sufijo, 'TABLERO', { espacio });
    const individual = crearBien(sufijo, 'BEAM', { espacio, persona: d.usuarios.secretario.id });

    const docente = await (await comoRol(browser, 'docente')).newPage();
    await docente.goto(`bienes?q=PWR-`);
    const filas = docente.locator('tbody tr');
    await expect(filas.filter({ hasText: `PWR-TABLERO-${sufijo}` })).toHaveCount(1);
    await expect(filas.filter({ hasText: `PWR-BEAM-${sufijo}` })).toHaveCount(0);
    // La ficha pública del QR no le ofrece "ver ficha" de un bien que no es suyo.
    const token = bd(`SELECT qr_token FROM bienes WHERE id = ${individual}`);
    await docente.goto(`qr/${token}`);
    await expect(docente.getByText('Individual')).toBeVisible();
    await expect(docente.getByText(nombre(d.usuarios.secretario.id))).toBeVisible();
    await docente.context().close();

    // El rector lo ve con su etiqueta en la lista general.
    const rector = await (await comoRol(browser, 'rector')).newPage();
    await rector.goto(`bienes?q=PWR-BEAM-${sufijo}`);
    const fila = rector.locator('tbody tr').filter({ hasText: `PWR-BEAM-${sufijo}` });
    await expect(fila).toContainText('Individual');
    await expect(fila).toContainText(nombre(d.usuarios.secretario.id));
    await expect(fila).toContainText(`Guardado en PWRSALA-${sufijo} - PW SALA ${sufijo}`);
    await rector.goto(`bienes?q=PWR-TABLERO-${sufijo}`);
    await expect(rector.locator('tbody tr').filter({ hasText: `PWR-TABLERO-${sufijo}` })).toContainText('Grupal');
    await rector.context().close();
    expect(grupal).toBeGreaterThan(0);
});

test('traslados entre Grupal e Individual quedan en el historial con su origen y destino', async ({ browser }) => {
    const sufijo = Date.now();
    const d = datos();
    const aula = crearEspacio(sufijo, 'AULA', d.usuarios.docente.id);
    const bodega = crearEspacio(sufijo, 'BOD', d.usuarios.rector.id);
    const bien = crearBien(sufijo, 'SILLA', { espacio: aula });
    const pagina = await (await comoRol(browser, 'rector')).newPage();

    async function trasladar(tipo, persona, espacioTexto) {
        await pagina.goto(`bienes/${bien}/editar`);
        await pagina.locator('#accionBien').selectOption('trasladar');
        await pagina.locator(`#accionTipo${tipo === 'individual' ? 'Individual' : 'Grupal'}`).check();
        if (persona) { await seleccionarTomSelect(pagina, 'accionPersona', persona); }
        if (espacioTexto) { await seleccionarTomSelect(pagina, 'accionEspacio', espacioTexto); }
        await pagina.getByRole('button', { name: 'Guardar y trasladar' }).click();
        await expect(pagina.locator('.alert-success')).toBeVisible();
    }

    // Grupal (Aula) → Individual (docente).
    await trasladar('individual', nombre(d.usuarios.docente.id), null);
    await expect(pagina.locator('.alert-success')).toContainText(`Ahora está a cargo de ${nombre(d.usuarios.docente.id)}`);
    expect(asignacionActiva(bien)).toBe(`${d.usuarios.docente.id}/-`);
    expect(bd(`SELECT CONCAT(IFNULL(espacio_origen_id,'-'),'/',IFNULL(persona_destino_id,'-')) FROM movimientos
               WHERE bien_id = ${bien} ORDER BY id DESC LIMIT 1`)).toBe(`${aula}/${d.usuarios.docente.id}`);

    // Individual (docente) → Individual (secretario), guardado en la Bodega.
    await trasladar('individual', nombre(d.usuarios.secretario.id), `PW BOD ${sufijo}`);
    expect(asignacionActiva(bien)).toBe(`${d.usuarios.secretario.id}/${bodega}`);
    expect(bd(`SELECT CONCAT(persona_origen_id,'/',persona_destino_id,'/',espacio_destino_id) FROM movimientos
               WHERE bien_id = ${bien} ORDER BY id DESC LIMIT 1`)).toBe(`${d.usuarios.docente.id}/${d.usuarios.secretario.id}/${bodega}`);

    // Individual (secretario) → Grupal (Aula).
    await trasladar('grupal', null, `PW AULA ${sufijo}`);
    expect(asignacionActiva(bien)).toBe(`-/${aula}`);
    expect(bd(`SELECT CONCAT(persona_origen_id,'/',IFNULL(persona_destino_id,'-'),'/',espacio_destino_id) FROM movimientos
               WHERE bien_id = ${bien} ORDER BY id DESC LIMIT 1`)).toBe(`${d.usuarios.secretario.id}/-/${aula}`);

    // El historial de la ficha muestra a quién pasó.
    await pagina.getByText(/Ver historial de movimientos/).click();
    await expect(pagina.getByText(`A cargo de ${nombre(d.usuarios.secretario.id)} (guardado en PWRBOD-${sufijo} - PW BOD ${sufijo})`)).toBeVisible();
    await pagina.context().close();
});

test('Individual sin persona, o con una persona de otra institución, se rechaza', async ({ browser }) => {
    const sufijo = Date.now();
    const d = datos();
    const bien = crearBien(sufijo, 'VALIDA');
    const pagina = await (await comoRol(browser, 'rector')).newPage();
    await pagina.goto('asignaciones');
    const token = await csrf(pagina);
    const asignar = (campos) => pagina.request.post('asignaciones', {
        form: { _csrf: token, 'bienes[]': String(bien), fecha_asignacion: fechaHoy(), tipo_responsabilidad: 'individual', ...campos },
        maxRedirects: 0,
    });

    await asignar({});
    await pagina.goto('asignaciones');
    await expect(pagina.locator('.alert-danger')).toContainText('Selecciona la persona responsable');

    await asignar({ persona_id: String(d.usuarios.rector_b.id) });
    await pagina.goto('asignaciones');
    await expect(pagina.locator('.alert-danger')).toContainText('no puede tener bienes a cargo');
    expect(bd(`SELECT COUNT(*) FROM asignaciones WHERE bien_id = ${bien}`)).toBe('0');

    // Con una persona válida, la asignación masiva lo deja a su cargo.
    await asignar({ persona_id: String(d.usuarios.docente.id) });
    expect(asignacionActiva(bien)).toBe(`${d.usuarios.docente.id}/-`);
    // Repetirla no crea otra asignación.
    await asignar({ persona_id: String(d.usuarios.docente.id) });
    await pagina.goto('asignaciones');
    await expect(pagina.locator('.alert-danger')).toContainText('ya estaban a cargo de esa persona');
    expect(bd(`SELECT COUNT(*) FROM asignaciones WHERE bien_id = ${bien}`)).toBe('1');
    await pagina.context().close();
});

test('un bien Individual se reintegra y el movimiento guarda de quién salió', async ({ browser }) => {
    const sufijo = Date.now();
    const d = datos();
    const bien = crearBien(sufijo, 'REINT', { persona: d.usuarios.docente.id });
    const pagina = await (await comoRol(browser, 'rector')).newPage();

    await pagina.goto(`bienes/${bien}/editar`);
    await pagina.locator('#accionBien').selectOption('reintegrar');
    await pagina.locator('#accionDestino').fill('Almacén de la Alcaldía');
    pagina.once('dialog', (dialogo) => dialogo.accept());
    await pagina.getByRole('button', { name: 'Guardar y reintegrar' }).click();
    await expect(pagina.locator('.alert-success')).toContainText('Reintegro registrado');

    expect(bd(`SELECT estado FROM bienes WHERE id = ${bien}`)).toBe('reintegrado');
    expect(bd(`SELECT CONCAT(tipo, '/', persona_origen_id) FROM movimientos WHERE bien_id = ${bien} ORDER BY id DESC LIMIT 1`))
        .toBe(`reintegro/${d.usuarios.docente.id}`);
    await pagina.context().close();
});
