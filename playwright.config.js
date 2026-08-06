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

export default defineConfig({
    testDir: './tests',
    fullyParallel: false,
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

        {
            name: 'authenticated',
            testMatch: /(carga_masiva|papelera)\.spec\.js/,
            use: { ...devices['Desktop Chrome'], storageState: 'playwright/.auth/user.json' },
            dependencies: ['setup'],
        },
    ],
});
