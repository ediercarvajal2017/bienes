import { defineConfig, devices } from '@playwright/test';

// process.loadEnvFile no lanza si el archivo no existe en algunas versiones de Node,
// pero para no depender de eso (y para no agregar la dependencia "dotenv" solo por esto)
// se envuelve en try/catch: sin .env.test igual se puede correr login.spec.js (no
// necesita sesión), solo se omiten las pruebas que sí la requieren.
try {
    process.loadEnvFile('.env.test');
} catch {
    // .env.test no existe todavía — ver .env.test.example para crearlo.
}

// El slash final importa: con baseURL "http://host/gestionbienes" (sin slash), Playwright
// resuelve un goto('login') como "http://host/login" (reemplaza el último segmento en vez
// de agregarlo) porque SIGEBI vive en un subdirectorio, no en la raíz del dominio.
let baseURL = process.env.TEST_BASE_URL || 'http://localhost/gestionbienes/';
if (!baseURL.endsWith('/')) {
    baseURL += '/';
}

// BLINDAJE: las pruebas crean, editan y eliminan datos a través de la web. SIGEBI está en
// producción con información real, así que NUNCA deben correr contra el sitio real.
// Solo se aceptan URLs locales. Un entorno remoto de ensayo/demo se permite únicamente si
// su dominio lo dice (staging, ensayo o demo) Y se activa TEST_PERMITIR_REMOTO=1 a propósito.
{
    const host = new URL(baseURL).hostname;
    const esLocal = ['localhost', '127.0.0.1', '::1'].includes(host) || /\.(test|local|localhost)$/.test(host);
    const esEnsayoRemoto = /(^|[.-])(staging|ensayo|demo)([.-]|$)/i.test(host);

    if (!esLocal && !(esEnsayoRemoto && process.env.TEST_PERMITIR_REMOTO === '1')) {
        throw new Error(
            `Pruebas bloqueadas: TEST_BASE_URL apunta a "${host}", que no es un entorno local. ` +
            'Las pruebas modifican datos y no pueden correr contra producción. Para un entorno ' +
            'remoto de ensayo/demo (su dominio debe contener staging, ensayo o demo) use TEST_PERMITIR_REMOTO=1.'
        );
    }
}

export default defineConfig({
    testDir: './tests',
    fullyParallel: false,
    // SIGEBI usa sesiones de PHP tradicionales con un token CSRF guardado en la sesión
    // del servidor. Todas las pruebas "authenticated" reutilizan la MISMA sesión (ver
    // storageState más abajo) — si dos corren en paralelo, una puede regenerar/consumir
    // el token CSRF justo cuando la otra lo estaba por usar, y esa otra falla con
    // "Tu sesión expiró" aunque la app esté perfectamente bien. No es una prueba
    // "flaky": es una corrida en paralelo pisándose la sesión. workers:1 corre todo en
    // serie para que esto no pase nunca (la suite es chica, el costo es unos segundos).
    workers: 1,
    forbidOnly: !!process.env.CI,
    retries: process.env.CI ? 1 : 0,
    reporter: [['list'], ['html', { open: 'never' }]],

    use: {
        baseURL,
        trace: 'on-first-retry',
        screenshot: 'only-on-failure',
    },

    projects: [
        // login.spec.js necesita empezar SIN sesión (la pantalla de login redirige a
        // /dashboard si ya hay una sesión activa) — por eso vive en su propio proyecto,
        // sin el storageState que usan los demás.
        {
            name: 'guest',
            testMatch: /login\.spec\.js/,
            use: { ...devices['Desktop Chrome'] },
        },

        // Inicia sesión una sola vez y guarda las cookies en playwright/.auth/user.json;
        // el proyecto "authenticated" reutiliza esa sesión en vez de loguearse en cada prueba.
        {
            name: 'setup',
            testMatch: /auth\.setup\.js/,
        },

        // Cualquier *.spec.js nuevo entra acá automáticamente (menos login.spec.js,
        // que vive en "guest") — no hay que acordarse de agregarlo a una lista.
        {
            name: 'authenticated',
            testMatch: /\.spec\.js$/,
            testIgnore: /login\.spec\.js/,
            use: { ...devices['Desktop Chrome'], storageState: 'playwright/.auth/user.json' },
            dependencies: ['setup'],
        },
    ],
});
