import { test, expect } from '@playwright/test';
import { datos, bd, comoRol, csrf } from './helpers/datos.js';

/**
 * Notas rápidas (NotaController y public/assets/js/notas.js): ícono de la barra superior,
 * panel lateral, guardado automático, colores, borrado y privacidad (cada usuario ve y
 * cambia solo las suyas, ni el superusuario ve las de otro).
 */
test.beforeEach(() => test.skip(datos().remoto === true, 'Necesita la base de pruebas local'));

const idUsuario = (rol) => datos().usuarios[rol].id;

test('una nota se crea, se guarda sola, cambia de color y se borra', async ({ browser }) => {
    const uid = idUsuario('secretario');
    bd(`DELETE FROM notas WHERE usuario_id = ${uid}`);
    const contexto = await comoRol(browser, 'secretario');
    const pagina = await contexto.newPage();
    await pagina.goto('dashboard');

    const boton = pagina.locator('[data-abrir-notas]');
    const panel = pagina.locator('#panelNotas');
    await expect(boton).toHaveAttribute('aria-label', 'Mis notas');
    await expect(panel).toBeHidden();
    await boton.click();
    await expect(panel).toBeVisible();
    await expect(panel.getByText('Todavía no tienes notas')).toBeVisible();

    await panel.getByRole('button', { name: 'Nueva nota' }).click();
    const texto = `Revisar las sillas del aula 101 ${Date.now()}`;
    await panel.getByLabel('Texto de la nota').fill(texto);
    await expect(panel.locator('.nota-estado')).toHaveText('Guardado');
    expect(bd(`SELECT texto FROM notas WHERE usuario_id = ${uid}`)).toBe(texto);
    await expect(boton.locator('[data-cuenta-notas]')).toHaveText('1');

    await panel.getByRole('button', { name: 'Color verde' }).click();
    await expect.poll(() => bd(`SELECT color FROM notas WHERE usuario_id = ${uid}`)).toBe('verde');

    // Al recargar sigue ahí, con su color, y el ícono muestra cuántas hay.
    await pagina.reload();
    await expect(boton).toHaveAttribute('aria-label', 'Mis notas (1)');
    await boton.click();
    await expect(panel.getByLabel('Texto de la nota')).toHaveValue(texto);
    await expect(panel.locator('.nota')).toHaveClass(/nota-verde/);
    await expect(panel.getByRole('button', { name: 'Color verde' })).toHaveAttribute('aria-pressed', 'true');

    // Borrar pide confirmación dentro de la nota.
    await panel.getByRole('button', { name: 'Borrar nota' }).click();
    await panel.getByRole('button', { name: 'Borrar', exact: true }).click();
    await expect(panel.locator('.nota')).toHaveCount(0);
    await expect.poll(() => bd(`SELECT COUNT(*) FROM notas WHERE usuario_id = ${uid}`)).toBe('0');
    await expect(boton.locator('[data-cuenta-notas]')).toBeHidden();

    // Escape cierra el panel y el foco vuelve al ícono.
    await pagina.keyboard.press('Escape');
    await expect(panel).toBeHidden();
    await expect(boton).toBeFocused();
    await contexto.close();
});

test('una nota nueva que se deja vacía no se crea', async ({ browser }) => {
    const uid = idUsuario('secretario');
    bd(`DELETE FROM notas WHERE usuario_id = ${uid}`);
    const contexto = await comoRol(browser, 'secretario');
    const pagina = await contexto.newPage();
    await pagina.goto('dashboard');
    const panel = pagina.locator('#panelNotas');

    await pagina.locator('[data-abrir-notas]').click();
    await panel.getByRole('button', { name: 'Nueva nota' }).click();
    // Pulsar otra vez no abre una segunda nota vacía.
    await panel.getByRole('button', { name: 'Nueva nota' }).click();
    await expect(panel.locator('.nota')).toHaveCount(1);

    await panel.getByRole('button', { name: 'Cerrar las notas' }).click();
    await expect(panel).toBeHidden();
    await pagina.locator('[data-abrir-notas]').click();
    await expect(panel.locator('.nota')).toHaveCount(0);
    expect(bd(`SELECT COUNT(*) FROM notas WHERE usuario_id = ${uid}`)).toBe('0');
    await contexto.close();
});

test('son privadas: ni otro usuario ni el superusuario las ven, cambian o borran', async ({ browser }) => {
    const uidRector = idUsuario('rector');
    bd(`INSERT INTO notas (usuario_id, texto) VALUES (${uidRector}, 'nota privada del rector')`);
    const idNota = bd(`SELECT MAX(id) FROM notas WHERE usuario_id = ${uidRector}`);

    for (const rol of ['docente', 'superusuario']) {
        const contexto = await comoRol(browser, rol);
        const pagina = await contexto.newPage();
        await pagina.goto('dashboard');
        const token = await csrf(pagina);

        const lista = await contexto.request.get('notas');
        expect(lista.status()).toBe(200);
        expect(JSON.stringify(await lista.json())).not.toContain('nota privada del rector');
        const cambio = await contexto.request.post(`notas/${idNota}`, { form: { _csrf: token, texto: 'cambiada' } });
        expect(cambio.status(), `${rol}: cambiar la nota de otro`).toBe(404);
        const borrado = await contexto.request.post(`notas/${idNota}/eliminar`, { form: { _csrf: token } });
        expect(borrado.status(), `${rol}: borrar la nota de otro`).toBe(404);
        await contexto.close();
    }

    expect(bd(`SELECT texto FROM notas WHERE id = ${idNota}`)).toBe('nota privada del rector');
    bd(`DELETE FROM notas WHERE id = ${idNota}`);
});

test('valida el token, el largo, el color y el máximo de notas', async ({ browser }) => {
    const uid = idUsuario('docente');
    bd(`DELETE FROM notas WHERE usuario_id = ${uid}`);
    const contexto = await comoRol(browser, 'docente');
    const pagina = await contexto.newPage();
    await pagina.goto('dashboard');
    const token = await csrf(pagina);
    const crear = (campos) => contexto.request.post('notas', { form: campos });

    expect((await crear({ texto: 'sin token' })).status()).toBe(403);
    expect((await crear({ _csrf: token, texto: 'x'.repeat(2001) })).status()).toBe(422);
    expect((await crear({ _csrf: token, texto: 'hola', color: 'negro' })).status()).toBe(422);

    // Tildes y emojis se guardan tal cual; 2.000 caracteres es el máximo permitido.
    const texto = 'Ñandú 😀 ' + 'x'.repeat(1992);
    const creada = await crear({ _csrf: token, texto, color: 'azul' });
    expect(creada.status()).toBe(201);
    const nota = (await creada.json()).nota;
    expect(nota.color).toBe('azul');
    const guardada = (await (await contexto.request.get('notas')).json()).notas.find((n) => n.id === nota.id);
    expect(guardada.texto).toBe(texto);

    // Máximo 100 notas por usuario.
    bd(`INSERT INTO notas (usuario_id, texto) SELECT ${uid}, 'relleno' FROM information_schema.COLUMNS LIMIT 99`);
    const sobra = await crear({ _csrf: token, texto: 'la número 101' });
    expect(sobra.status()).toBe(422);
    expect((await sobra.json()).error).toContain('máximo');

    bd(`DELETE FROM notas WHERE usuario_id = ${uid}`);
    await contexto.close();
});

test('en el celular el ícono está en la barra superior y el panel ocupa toda la pantalla', async ({ browser }) => {
    const contexto = await browser.newContext({
        storageState: 'playwright/.auth/docente.json',
        viewport: { width: 360, height: 740 }, isMobile: true, hasTouch: true,
    });
    const pagina = await contexto.newPage();
    await pagina.goto('dashboard');

    const boton = pagina.locator('[data-abrir-notas]');
    await expect(boton).toBeVisible();
    const caja = await boton.boundingBox();
    expect(caja.width).toBeGreaterThanOrEqual(44);
    expect(caja.height).toBeGreaterThanOrEqual(44);

    await boton.tap();
    const panel = pagina.locator('#panelNotas');
    await expect(panel).toBeVisible();
    await expect.poll(async () => Math.round((await panel.boundingBox()).x)).toBe(0);
    expect(Math.round((await panel.boundingBox()).width)).toBe(360);
    expect(await pagina.evaluate(() => document.documentElement.scrollWidth - document.documentElement.clientWidth)).toBeLessThanOrEqual(0);
    await contexto.close();
});
