import { defineConfig, devices } from '@playwright/test';
import path from 'node:path';

// Configuración local de la suite (opcional): .env.test (ver .env.test.example).
try {
    process.loadEnvFile('.env.test');
} catch {
    // Sin .env.test se usan los valores por defecto (XAMPP en Windows).
}

/*
 * DOS MODOS
 *
 * 1) LOCAL (por defecto, recomendado): Playwright levanta su propio servidor PHP en
 *    127.0.0.1:8090 conectado a una base DESECHABLE (sigebi_test), que tests/global-setup.js
 *    recrea desde cero en cada corrida con datos conocidos (database/seeders/pruebas.php).
 *    No depende de Apache ni toca la base de desarrollo ni la de producción.
 *
 * 2) REMOTO: TEST_BASE_URL apunta a un entorno de ENSAYO o DEMO (su dominio debe contener
 *    staging, ensayo o demo) y TEST_PERMITIR_REMOTO=1. Nunca producción.
 */
const esWindows = process.platform === 'win32';
export const PHP_BIN = process.env.PHP_BIN || (esWindows ? 'C:/xampp/php/php.exe' : 'php');
export const MYSQL_BIN = process.env.MYSQL_BIN || (esWindows ? 'C:/xampp/mysql/bin/mysql.exe' : 'mysql');
export const BD_PRUEBAS = process.env.TEST_DB_DATABASE || 'sigebi_test';
export const MODO_REMOTO = Boolean(process.env.TEST_BASE_URL);
const PUERTO = Number(process.env.TEST_PUERTO || 8090);

if (!/test/i.test(BD_PRUEBAS) || /prod/i.test(BD_PRUEBAS)) {
    throw new Error(`Pruebas bloqueadas: la base de pruebas debe contener "test" y no "prod" (actual: ${BD_PRUEBAS}).`);
}

// El slash final importa: MIA puede vivir en un subdirectorio.
let baseURL = MODO_REMOTO ? process.env.TEST_BASE_URL : `http://127.0.0.1:${PUERTO}/`;
if (!baseURL.endsWith('/')) {
    baseURL += '/';
}

// BLINDAJE: las pruebas crean, editan y eliminan datos. En modo remoto solo se acepta un
// entorno de ensayo/demo, y a propósito.
if (MODO_REMOTO) {
    const host = new URL(baseURL).hostname;
    const esLocal = ['localhost', '127.0.0.1', '::1'].includes(host) || /\.(test|local|localhost)$/.test(host);
    const esEnsayoRemoto = /(^|[.-])(staging|ensayo|demo)([.-]|$)/i.test(host);
    if (!esLocal && !(esEnsayoRemoto && process.env.TEST_PERMITIR_REMOTO === '1')) {
        throw new Error(
            `Pruebas bloqueadas: TEST_BASE_URL apunta a "${host}", que no es un entorno local ni de ensayo. ` +
            'Las pruebas modifican datos y no pueden correr contra producción. Para un entorno de ' +
            'ensayo/demo (su dominio debe contener staging, ensayo o demo) use TEST_PERMITIR_REMOTO=1.'
        );
    }
}

// Llave fija SOLO para la base de pruebas (cifra las claves de la verificación en dos pasos).
const APP_KEY_PRUEBAS = 'cHJ1ZWJhcy1zaWdlYmktbGxhdmUtZGUtMzItYnl0ZXM=';

const sesion = (rol) => `playwright/.auth/${rol}.json`;

export default defineConfig({
    testDir: './tests',
    globalSetup: './tests/global-setup.js',
    fullyParallel: false,
    // MIA usa sesiones PHP con el token CSRF en el servidor, y el servidor embebido de
    // PHP atiende una petición a la vez: la suite corre en serie a propósito.
    workers: 1,
    forbidOnly: !!process.env.CI,
    retries: process.env.CI ? 1 : 0,
    reporter: [['list'], ['html', { open: 'never' }]],

    use: {
        baseURL,
        trace: 'on-first-retry',
        screenshot: 'only-on-failure',
        // PW_CANAL=msedge (o chrome): usa el navegador instalado en vez de los que descarga
        // "npx playwright install".
        ...(process.env.PW_CANAL ? { channel: process.env.PW_CANAL } : {}),
    },

    webServer: MODO_REMOTO ? undefined : {
        command: `"${PHP_BIN}" -S 127.0.0.1:${PUERTO} -t public tests/servidor/router.php`,
        url: `http://127.0.0.1:${PUERTO}/login`,
        reuseExistingServer: false,
        timeout: 30_000,
        stdout: 'ignore',
        stderr: 'ignore',
        env: {
            DB_DATABASE: BD_PRUEBAS,
            APP_KEY: APP_KEY_PRUEBAS,
            APP_DEBUG: '0',
            // Archivos subidos durante las pruebas: aparte de los de desarrollo.
            STORAGE_PATH: path.resolve('storage/pruebas'),
            // Revalida la sesión en cada petición (las pruebas de revocación no esperan 60 s).
            SESSION_REVALIDACION_SEGUNDOS: '0',
        },
    },

    projects: [
        // login.spec.js empieza SIN sesión.
        { name: 'guest', testMatch: /login\.spec\.js/, use: { ...devices['Desktop Chrome'] } },

        // Inicia sesión una vez por rol y guarda playwright/.auth/<rol>.json.
        { name: 'setup', testMatch: /auth\.setup\.js/ },

        // Todas las specs (salvo login y responsive), como superusuario; las de roles
        // (permisos, aislamiento, regresiones) abren sus propios contextos por rol.
        {
            name: 'authenticated',
            testMatch: /\.spec\.js$/,
            testIgnore: [/login\.spec\.js/, /responsive\.spec\.js/],
            use: { ...devices['Desktop Chrome'], storageState: sesion('superusuario') },
            dependencies: ['setup'],
        },

        // Diseño responsive (tests/responsive.spec.js) en 7 dispositivos, con Chromium.
        // Solo estos: npx playwright test --project="responsive-*"
        ...[
            ['responsive-movil-320', { viewport: { width: 320, height: 640 }, isMobile: true, hasTouch: true, deviceScaleFactor: 2 }],
            ['responsive-android', devices['Pixel 7']],
            ['responsive-iphone', devices['iPhone 13']],
            ['responsive-tablet', devices['iPad (gen 7)']],
            ['responsive-tablet-horizontal', devices['iPad (gen 7) landscape']],
            ['responsive-escritorio', { viewport: { width: 1366, height: 768 } }],
            ['responsive-grande', { viewport: { width: 1920, height: 1080 } }],
        ].map(([name, dispositivo]) => ({
            name,
            testMatch: /responsive\.spec\.js/,
            use: {
                ...dispositivo,
                browserName: 'chromium',
                defaultBrowserType: 'chromium',
                ...(process.env.PW_CANAL ? { channel: process.env.PW_CANAL } : {}),
                storageState: sesion('superusuario'),
            },
            dependencies: ['setup'],
        })),
    ],
});
