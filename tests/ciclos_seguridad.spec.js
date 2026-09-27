import { test, expect } from '@playwright/test';
import { datos, comoRol, sinSesion, bd } from './helpers/datos.js';

// Solo en modo local: en modo remoto no existen los usuarios de cada rol.
test.beforeEach(() => test.skip(datos().remoto === true, 'Necesita la base de pruebas local (usuarios por rol)'));

/**
 * Regresiones de los ciclos de vida (fase 2) y de la seguridad de las sesiones (fase 1),
 * con los roles reales: docente que reporta/solicita y rector que aprueba.
 */
async function sesionDe(browser, rol) {
    const contexto = await comoRol(browser, rol);
    const pagina = await contexto.newPage();
    await pagina.goto('dashboard');
    return { contexto, pagina, token: await pagina.locator('input[name="_csrf"]').first().inputValue() };
}

test.describe('Ciclo de una baja', () => {
    test('el docente la reporta; el rector la aprueba una sola vez y el bien sale de su espacio', async ({ browser }) => {
        const d = datos();
        const bien = d.bienes.A.para_baja;
        const docente = await sesionDe(browser, 'docente');

        await docente.pagina.goto(`qr/${d.qr.para_baja}/baja`);
        const tokenBaja = await docente.pagina.locator('input[name="_csrf"]').first().inputValue();
        await docente.contexto.request.post(`qr/${d.qr.para_baja}/baja`, {
            form: { _csrf: tokenBaja, estado_reportado: 'Roto', descripcion: 'Pata partida, no se puede usar' },
        });
        const baja = bd(`SELECT id FROM bajas_bienes WHERE bien_id = ${bien} AND estado = 'pendiente'`);
        expect(baja, 'el reporte queda pendiente').not.toBe('');

        const aprobarComoDocente = await docente.contexto.request.post(`bajas/${baja}/aprobar`, { form: { _csrf: docente.token }, maxRedirects: 0 });
        expect(aprobarComoDocente.status(), 'el docente no puede aprobar').toBe(403);
        await docente.contexto.close();

        const rector = await sesionDe(browser, 'rector');
        await rector.contexto.request.post(`bajas/${baja}/aprobar`, { form: { _csrf: rector.token } });
        expect(bd(`SELECT b.estado, (SELECT COUNT(*) FROM asignaciones a WHERE a.bien_id = b.id AND a.activa = 1)
                   FROM bienes b WHERE b.id = ${bien}`), 'dado de baja y sin asignación activa').toBe('dado_de_baja\t0');

        await rector.contexto.request.post(`bajas/${baja}/aprobar`, { form: { _csrf: rector.token } });
        expect(bd(`SELECT COUNT(*) FROM auditoria WHERE accion = 'aprobar' AND entidad = 'baja' AND entidad_id = ${baja}`), 'aprobar dos veces no la procesa dos veces').toBe('1');
        await rector.contexto.close();
    });

    test('rechazar exige un motivo y conserva el reporte', async ({ browser }) => {
        const d = datos();
        const bien = d.bienes.A.para_rechazo;
        const docente = await sesionDe(browser, 'docente');
        const qr = d.qr.para_rechazo;
        await docente.pagina.goto(`qr/${qr}/baja`);
        const tokenBaja = await docente.pagina.locator('input[name="_csrf"]').first().inputValue();
        await docente.contexto.request.post(`qr/${qr}/baja`, { form: { _csrf: tokenBaja, estado_reportado: 'Rayada', descripcion: 'Superficie rayada' } });
        await docente.contexto.close();
        const baja = bd(`SELECT id FROM bajas_bienes WHERE bien_id = ${bien} AND estado = 'pendiente'`);

        const rector = await sesionDe(browser, 'rector');
        await rector.contexto.request.post(`bajas/${baja}/rechazar`, { form: { _csrf: rector.token, motivo_rechazo: '' } });
        expect(bd(`SELECT estado FROM bajas_bienes WHERE id = ${baja}`), 'sin motivo no se rechaza').toBe('pendiente');

        await rector.contexto.request.post(`bajas/${baja}/rechazar`, { form: { _csrf: rector.token, motivo_rechazo: 'Se puede reparar' } });
        expect(bd(`SELECT estado, motivo_rechazo FROM bajas_bienes WHERE id = ${baja}`)).toBe('rechazada\tSe puede reparar');
        expect(bd(`SELECT estado FROM bienes WHERE id = ${bien}`), 'el bien sigue activo').toBe('activo');
        await rector.contexto.close();
    });
});

test.describe('Solicitud de reintegro', () => {
    test('el docente la pide; el rector la aprueba y el bien queda reintegrado', async ({ browser }) => {
        const d = datos();
        const bien = d.bienes.A.para_solicitud;
        const docente = await sesionDe(browser, 'docente');
        await docente.pagina.goto(`qr/${d.qr.para_solicitud}/solicitar-reintegro`);
        const tokenSolicitud = await docente.pagina.locator('input[name="_csrf"]').first().inputValue();
        await docente.contexto.request.post(`qr/${d.qr.para_solicitud}/solicitar-reintegro`, {
            form: { _csrf: tokenSolicitud, motivo: 'Ya no se usa en el aula desde la reubicación' },
        });
        await docente.contexto.close();
        const solicitud = bd(`SELECT id FROM solicitudes_reintegro WHERE bien_id = ${bien} AND estado = 'pendiente'`);
        expect(solicitud).not.toBe('');

        const rector = await sesionDe(browser, 'rector');
        await rector.contexto.request.post(`reintegros/solicitudes/${solicitud}/aprobar`, {
            form: { _csrf: rector.token, fecha: '2026-09-26', destino_texto: 'Almacén municipal' },
        });
        expect(bd(`SELECT s.estado, b.estado FROM solicitudes_reintegro s JOIN bienes b ON b.id = s.bien_id WHERE s.id = ${solicitud}`))
            .toBe('aprobada\treintegrado');
        expect(bd(`SELECT COUNT(*) FROM asignaciones WHERE bien_id = ${bien} AND activa = 1`)).toBe('0');
        await rector.contexto.close();
    });
});

test.describe('Seguridad de las sesiones', () => {
    test('un usuario desactivado sale del sistema en su siguiente clic', async ({ browser }) => {
        const cuenta = datos().usuarios.sesion;
        const usuario = await sinSesion(browser);
        const pagina = await usuario.newPage();
        await pagina.goto('login');
        await pagina.fill('#email', cuenta.email);
        await pagina.fill('#password', cuenta.clave);
        await pagina.getByRole('button', { name: 'Ingresar' }).click();
        await expect(pagina).toHaveURL(/dashboard/);

        const rector = await sesionDe(browser, 'rector');
        await rector.contexto.request.post(`usuarios/${cuenta.id}/estado`, { form: { _csrf: rector.token } });
        await rector.contexto.close();
        expect(bd(`SELECT activo FROM usuarios WHERE id = ${cuenta.id}`)).toBe('0');

        await pagina.goto('bienes');
        await expect(pagina).toHaveURL(/login/);
        await expect(pagina.locator('.alert-danger')).toContainText('desactivada');
        await usuario.close();
        bd(`UPDATE usuarios SET activo = 1 WHERE id = ${cuenta.id}`);
    });

    test('intentos fallidos: tras 5 en una cuenta se bloquea aunque la contraseña sea correcta', async ({ browser }) => {
        const cuenta = datos().usuarios.sesion;
        const anonimo = await sinSesion(browser);
        const pagina = await anonimo.newPage();
        for (let i = 0; i < 5; i++) {
            await pagina.goto('login');
            await pagina.fill('#email', cuenta.email);
            await pagina.fill('#password', `incorrecta-${i}-123`);
            await pagina.getByRole('button', { name: 'Ingresar' }).click();
            await expect(pagina.locator('.alert-danger')).toContainText('Correo o contraseña incorrectos');
            await expect(pagina.locator('#email'), 'el correo vuelve escrito tras el fallo').toHaveValue(cuenta.email);
        }
        await pagina.goto('login');
        await pagina.fill('#email', cuenta.email);
        await pagina.fill('#password', cuenta.clave);
        await pagina.getByRole('button', { name: 'Ingresar' }).click();
        await expect(pagina.locator('.alert-danger')).toContainText('Demasiados intentos fallidos');
        await anonimo.close();
        bd("DELETE FROM intentos_acceso");
    });
});
