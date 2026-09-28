import { execFileSync } from 'node:child_process';
import crypto from 'node:crypto';
import fs from 'node:fs';
import { MYSQL_BIN, BD_PRUEBAS } from '../../playwright.config.js';

/**
 * Ayudantes para las pruebas por rol:
 *  - datos(): identificadores creados por database/seeders/pruebas.php (instituciones,
 *    usuarios de cada rol, espacios y bienes).
 *  - comoRol(browser, rol): un contexto de navegador ya autenticado con ese rol.
 *  - bd(sql): consulta directa a la base DESECHABLE de pruebas (para verificar efectos).
 *  - totp(secreto): código de 6 dígitos como el de la aplicación autenticadora.
 */
let cache = null;
export function datos() {
    if (!cache) {
        cache = JSON.parse(fs.readFileSync('playwright/.datos-prueba.json', 'utf8'));
    }
    return cache;
}

export async function comoRol(browser, rol) {
    return browser.newContext({ storageState: `playwright/.auth/${rol}.json` });
}

/** Contexto SIN sesión (browser.newContext() a secas hereda la del proyecto). */
export async function sinSesion(browser) {
    return browser.newContext({ storageState: { cookies: [], origins: [] } });
}

/** Devuelve el resultado como texto (filas separadas por salto de línea, columnas por tabulador). */
export function bd(sql) {
    if (!/test/i.test(BD_PRUEBAS)) {
        throw new Error('bd() solo consulta la base de pruebas');
    }
    // En Windows mysql.exe devuelve CRLF: se normaliza para que split() por salto de línea
    // no deje un retorno de carro pegado a cada valor.
    const argumentos = ['-u', process.env.DB_USERNAME || 'root', '-N', '--default-character-set=utf8mb4'];
    if (process.env.DB_HOST) {
        argumentos.push('-h', process.env.DB_HOST);
    }
    // La contraseña va por MYSQL_PWD (no en la línea de comandos).
    const entorno = { ...process.env, ...(process.env.DB_PASSWORD ? { MYSQL_PWD: process.env.DB_PASSWORD } : {}) };
    return execFileSync(MYSQL_BIN, [...argumentos, BD_PRUEBAS, '-e', sql], { encoding: 'utf8', env: entorno })
        .replace(/\r\n/g, '\n').trim();
}

export function totp(secretoBase32, paso = Math.floor(Date.now() / 30000)) {
    const alfabeto = 'ABCDEFGHIJKLMNOPQRSTUVWXYZ234567';
    let bits = '';
    for (const c of secretoBase32.replace(/\s+/g, '').toUpperCase()) {
        bits += alfabeto.indexOf(c).toString(2).padStart(5, '0');
    }
    const clave = Buffer.from(bits.match(/.{8}/g).map((b) => parseInt(b, 2)));
    const contador = Buffer.alloc(8);
    contador.writeBigUInt64BE(BigInt(paso));
    const h = crypto.createHmac('sha1', clave).update(contador).digest();
    const o = h[19] & 15;
    return String((h.readUInt32BE(o) & 0x7fffffff) % 1000000).padStart(6, '0');
}

/** Token CSRF de la página actual (formularios de MIA). */
export async function csrf(page) {
    return page.locator('input[name="_csrf"]').first().inputValue();
}
