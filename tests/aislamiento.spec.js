import { test, expect } from '@playwright/test';
import { datos, comoRol, bd } from './helpers/datos.js';

/**
 * AISLAMIENTO ENTRE INSTITUCIONES: un rector de la institución A no puede ver, modificar
 * ni tomar el control de nada de la institución B, ni de la cuenta del superusuario.
 * Regresión de los defectos corregidos en la fase 0 (escalamiento vía sede padre y vía
 * cuentas del superusuario) y en la fase 1 (espacios de otra institución).
 */
test.describe('Aislamiento entre instituciones', () => {
    let rector;
    let pagina;
    let token;

    test.beforeEach(async ({ browser }) => {
        rector = await comoRol(browser, 'rector');
        pagina = await rector.newPage();
        await pagina.goto('dashboard');
        token = await pagina.locator('input[name="_csrf"]').first().inputValue();
    });

    test.afterEach(async () => {
        await rector.close();
    });

    test('no ve ni edita bienes, espacios ni usuarios de otra institución', async () => {
        const d = datos();
        for (const url of [
            `bienes/${d.bienes.B.silla}/editar`,
            `espacios/${d.espacios.aulaB}/editar`,
            `usuarios/${d.usuarios.rector_b.id}/editar`,
            `instituciones/${d.instituciones.B}/editar`,
        ]) {
            const estado = (await rector.request.get(url, { maxRedirects: 0 })).status();
            expect([403, 404], `${url} respondió ${estado}`).toContain(estado);
        }

        await pagina.goto('bienes?q=PB-0001');
        await expect(pagina.getByText('PB-0001')).toHaveCount(0);
        await pagina.goto('buscar?q=PB-0001');
        await expect(pagina.getByText('Silla de otra institución')).toHaveCount(0);
    });

    test('no puede asignar un bien propio a un espacio de otra institución', async () => {
        const d = datos();
        const bien = d.bienes.A.libre;
        await rector.request.post(`bienes/${bien}/asignar`, {
            form: { _csrf: token, espacio_id: String(d.espacios.aulaB), fecha_asignacion: '2026-09-01' },
            maxRedirects: 0,
        });
        expect(bd(`SELECT COUNT(*) FROM asignaciones WHERE bien_id = ${bien} AND espacio_id = ${d.espacios.aulaB}`)).toBe('0');
    });

    test('no puede convertir su institución en sección de otra ni activarla como sede', async () => {
        const d = datos();
        const antes = bd(`SELECT tipo_sede, IFNULL(institucion_padre_id, 'NULL'), codigo_dane FROM instituciones WHERE id = ${d.instituciones.A}`);
        await rector.request.post(`instituciones/${d.instituciones.A}`, {
            form: {
                _csrf: token, codigo_dane: '999', nombre: 'IE Prueba A', direccion: '', email_institucional: '',
                tipo_sede: 'seccion', institucion_padre_id: String(d.instituciones.B),
            },
            maxRedirects: 0,
        });
        expect(bd(`SELECT tipo_sede, IFNULL(institucion_padre_id, 'NULL'), codigo_dane FROM instituciones WHERE id = ${d.instituciones.A}`),
            'tipo de sede, institución principal y DANE no cambian').toBe(antes);

        await rector.request.post('sede-activa', {
            form: { _csrf: token, institucion_id: String(d.instituciones.B), volver: '/dashboard' },
            maxRedirects: 0,
        });
        await pagina.goto('dashboard');
        await expect(pagina.getByText('IE Prueba B')).toHaveCount(0);
    });

    test('no puede ver ni administrar la cuenta del superusuario (vive en su institución)', async () => {
        const superId = datos().usuarios.superusuario.id;
        expect((await rector.request.get(`usuarios/${superId}/editar`, { maxRedirects: 0 })).status()).toBe(403);

        await pagina.goto('usuarios');
        await expect(pagina.getByText(datos().usuarios.superusuario.email)).toHaveCount(0);

        for (const accion of ['estado', 'eliminar', 'restablecer-2fa']) {
            const estado = (await rector.request.post(`usuarios/${superId}/${accion}`, {
                form: { _csrf: token, password_confirmacion: datos().usuarios.rector.clave }, maxRedirects: 0,
            })).status();
            expect(estado, `POST usuarios/${superId}/${accion}`).toBe(403);
        }
        expect(bd(`SELECT activo, IFNULL(eliminado_en, 'NULL') FROM usuarios WHERE id = ${superId}`)).toBe('1\tNULL');
    });
});
