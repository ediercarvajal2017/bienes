import { execFileSync } from 'node:child_process';
import fs from 'node:fs';
import path from 'node:path';
import { PHP_BIN, BD_PRUEBAS, MODO_REMOTO } from '../playwright.config.js';

/**
 * Antes de cada corrida (modo local): recrea la base DESECHABLE de pruebas desde cero
 * (migraciones + seed + database/seeders/pruebas.php) y guarda los identificadores que
 * devuelve en playwright/.datos-prueba.json (los usan auth.setup.js y tests/helpers/datos.js).
 *
 * En modo remoto (TEST_BASE_URL) no se toca ninguna base: las credenciales se toman de
 * TEST_USER_EMAIL / TEST_USER_PASSWORD.
 */
export default async function globalSetup() {
    fs.mkdirSync('playwright', { recursive: true });

    if (MODO_REMOTO) {
        fs.writeFileSync('playwright/.datos-prueba.json', JSON.stringify({
            remoto: true,
            usuarios: { superusuario: { email: process.env.TEST_USER_EMAIL, clave: process.env.TEST_USER_PASSWORD } },
        }));
        return;
    }

    // Archivos subidos por las pruebas: se empiezan de cero (carpeta propia de pruebas).
    const almacenamiento = path.resolve('storage/pruebas');
    fs.rmSync(almacenamiento, { recursive: true, force: true });
    for (const carpeta of ['uploads', 'logs', 'backups', 'diagnosticos', 'archivo_auditoria']) {
        fs.mkdirSync(path.join(almacenamiento, carpeta), { recursive: true });
    }

    const salida = execFileSync(PHP_BIN, ['database/herramientas/preparar_bd_pruebas.php'], {
        env: { ...process.env, DB_DATABASE: BD_PRUEBAS, STORAGE_PATH: almacenamiento },
        encoding: 'utf8',
        stdio: ['ignore', 'pipe', 'inherit'],
    });

    const datos = JSON.parse(salida.slice(salida.indexOf('{')));
    fs.writeFileSync('playwright/.datos-prueba.json', JSON.stringify(datos, null, 2));
}
