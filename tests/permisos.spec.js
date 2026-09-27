import { test, expect } from '@playwright/test';
import fs from 'node:fs';
import { datos, comoRol, sinSesion, bd } from './helpers/datos.js';

// Solo en modo local: en modo remoto no existen los usuarios de cada rol.
test.beforeEach(() => test.skip(datos().remoto === true, 'Necesita la base de pruebas local (usuarios por rol)'));

/**
 * MATRIZ DE PERMISOS: cada rol contra cada ruta del sistema.
 *
 * No hay una lista escrita a mano que se desactualice: las rutas y sus middlewares se
 * leen de public/index.php, y los permisos de cada rol, de la base (sembrados por
 * database/seeders/seed.php). Regla esperada:
 *   - SuperusuarioMiddleware → solo el superusuario;
 *   - PermissionMiddleware:<permiso> → quien tenga ese permiso (el superusuario, todos);
 *   - solo AuthMiddleware → cualquier usuario con sesión, salvo las EXCEPCIONES de abajo
 *     (rutas cuyo controlador exige además un permiso, documentadas una por una).
 *
 * Denegado = HTTP 403. También se prueban los POST denegados: el middleware los frena
 * ANTES de ejecutar nada (sin efectos). Los POST permitidos no se envían aquí (tienen
 * efectos); los cubren las pruebas de cada módulo.
 */

// Rutas cuyo CONTROLADOR aplica una regla además del middleware (cada una con su motivo).
const sinDocente = (p, rol) => rol !== 'docente' && p.has('bienes.ver');
const EXCEPCIONES = {
    // Solo AuthMiddleware: el listado lo ven quien solicita y quien aprueba.
    'GET /reintegros/solicitudes': (p) => p.has('asignaciones.crear') || p.has('reintegros.solicitar'),
    // QrMasivoController::verificarAcceso: la impresión masiva de QR no es del docente,
    // aunque tenga bienes.ver.
    'GET /bienes/qr-masivo': sinDocente,
    'POST /bienes/qr-masivo': sinDocente,
    'GET /bienes/qr-masivo/bodega': sinDocente,
};

// Rutas que no se prueban aquí (motivo al lado).
const OMITIR = new Set([
    'POST /logout', // cierra la sesión compartida de la prueba
    'GET /archivos/{tipo}/{archivo}', // cubierta por archivos.spec.js (permisos por institución)
]);

function leerRutas() {
    const fuente = fs.readFileSync('public/index.php', 'utf8');
    const rutas = [];
    const patron = /\$router->(get|post)\('([^']+)',\s*\[[^\]]+\](?:,\s*\[([\s\S]*?)\])?\s*\);/g;
    let m;
    while ((m = patron.exec(fuente)) !== null) {
        const middlewares = m[3] || '';
        const permiso = (middlewares.match(/PermissionMiddleware::class\s*\.\s*':([a-z_.]+)'/) || [])[1] || null;
        rutas.push({
            metodo: m[1].toUpperCase(),
            ruta: m[2],
            requiereSesion: middlewares.includes('AuthMiddleware'),
            soloSuperusuario: middlewares.includes('SuperusuarioMiddleware'),
            permiso,
        });
    }
    return rutas;
}

function rellenar(ruta) {
    const d = datos();
    const reemplazos = {
        '/usuarios/{id}': d.usuarios.docente.id,
        '/espacios/{id}': d.espacios.aulaA,
        '/bienes/{id}': d.bienes.A.silla,
        '/instituciones/{id}': d.instituciones.A,
    };
    let url = ruta;
    for (const [prefijo, id] of Object.entries(reemplazos)) {
        if (url.startsWith(prefijo)) {
            url = url.replace('{id}', String(id));
        }
    }
    return url
        .replace('{token}', d.qr.A)
        .replace('{tipo}', 'papelera-no-existe')
        .replace(/\{[a-z_]+\}/gi, '999999')
        .replace(/^\//, '');
}

function permitido(ruta, rol, permisos) {
    if (rol === 'superusuario') {
        return true;
    }
    if (ruta.soloSuperusuario) {
        return false;
    }
    const excepcion = EXCEPCIONES[`${ruta.metodo} ${ruta.ruta}`];
    if (excepcion) {
        return excepcion(permisos, rol);
    }
    return ruta.permiso === null || permisos.has(ruta.permiso);
}

test.describe('Matriz de permisos', () => {
    test.setTimeout(240_000);

    test('la lectura de rutas encuentra las rutas del sistema', () => {
        const rutas = leerRutas();
        expect(rutas.length).toBeGreaterThan(150);
        expect(rutas.filter((r) => r.permiso).length).toBeGreaterThan(100);
    });

    for (const rol of ['rector', 'secretario', 'docente', 'superusuario']) {
        test(`rol ${rol}: cada ruta responde según sus permisos`, async ({ browser }) => {
            const idRol = datos().usuarios[rol].id;
            const permisos = new Set(bd(
                `SELECT p.codigo FROM rol_permiso rp JOIN permisos p ON p.id = rp.permiso_id
                 JOIN usuarios u ON u.rol_id = rp.rol_id WHERE u.id = ${idRol}`
            ).split('\n').filter(Boolean));

            const contexto = await comoRol(browser, rol);
            const pagina = await contexto.newPage();
            await pagina.goto('dashboard');
            const token = await pagina.locator('input[name="_csrf"]').first().inputValue();

            const errores = [];
            let revisadas = 0;
            for (const ruta of leerRutas().filter((r) => r.requiereSesion && !OMITIR.has(`${r.metodo} ${r.ruta}`))) {
                const debePermitir = permitido(ruta, rol, permisos);
                if (ruta.metodo === 'POST' && debePermitir) {
                    continue; // tiene efectos: lo cubren las pruebas de cada módulo
                }
                const url = rellenar(ruta.ruta);
                const respuesta = ruta.metodo === 'GET'
                    ? await contexto.request.get(url, { maxRedirects: 0 })
                    : await contexto.request.post(url, { form: { _csrf: token }, maxRedirects: 0 });
                const estado = respuesta.status();
                revisadas++;

                if (!debePermitir && estado !== 403) {
                    errores.push(`${ruta.metodo} ${ruta.ruta} debía negarse (403) y respondió ${estado} [permiso: ${ruta.permiso || (ruta.soloSuperusuario ? 'superusuario' : 'sesión')}]`);
                }
                if (debePermitir && (estado === 403 || estado >= 500)) {
                    errores.push(`${ruta.metodo} ${ruta.ruta} debía permitirse y respondió ${estado} [permiso: ${ruta.permiso || 'sesión'}]`);
                }
            }
            await contexto.close();

            expect(revisadas).toBeGreaterThan(30);
            expect(errores, `Rol ${rol}: rutas que no responden según sus permisos`).toEqual([]);
        });
    }

    test('sin sesión, toda ruta protegida lleva al inicio de sesión', async ({ browser }) => {
        const contexto = await sinSesion(browser);
        const errores = [];
        for (const ruta of leerRutas().filter((r) => r.requiereSesion && r.metodo === 'GET' && !OMITIR.has(`GET ${r.ruta}`))) {
            const respuesta = await contexto.request.get(rellenar(ruta.ruta), { maxRedirects: 0 });
            const destino = respuesta.headers().location || '';
            if (respuesta.status() !== 302 || !/\/login$/.test(destino)) {
                errores.push(`GET ${ruta.ruta} → ${respuesta.status()} ${destino}`);
            }
        }
        await contexto.close();
        expect(errores).toEqual([]);
    });
});
