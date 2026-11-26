import { test, expect } from '@playwright/test';
import { execFileSync } from 'node:child_process';
import fs from 'node:fs';
import os from 'node:os';
import path from 'node:path';
import { PHP_BIN } from '../playwright.config.js';
import { datos, bd } from './helpers/datos.js';
import { confirmar } from './helpers/confirmacion.js';

/**
 * Cargas masivas de espacios y usuarios (v1.2.40), con el mismo criterio que la de bienes:
 * una columna en blanco conserva lo que ya hay en MIA, nunca se borra nada, y cada fila
 * que no se puede cargar dice por qué.
 */
test.beforeEach(() => test.skip(datos().remoto === true, 'Necesita la base de pruebas local'));
test.use({ storageState: 'playwright/.auth/rector.json' });

/** .xlsx con los títulos y las filas dadas; las celdas vacías quedan vacías de verdad. */
function crearExcel(titulos, filas) {
    const ruta = path.join(os.tmpdir(), `carga-${Date.now()}-${Math.random().toString(16).slice(2)}.xlsx`);
    const codigo = `require 'vendor/autoload.php';
        $titulos = json_decode($argv[2], true); $filas = json_decode($argv[3], true);
        $libro = new PhpOffice\\PhpSpreadsheet\\Spreadsheet(); $hoja = $libro->getActiveSheet();
        $hoja->fromArray($titulos, null, 'A1');
        foreach ($filas as $i => $fila) { foreach ($fila as $j => $valor) {
            if ($valor !== '') { $hoja->getCell([$j + 1, $i + 2])->setValueExplicit((string) $valor, PhpOffice\\PhpSpreadsheet\\Cell\\DataType::TYPE_STRING); }
        } }
        (new PhpOffice\\PhpSpreadsheet\\Writer\\Xlsx($libro))->save($argv[1]);`;
    execFileSync(PHP_BIN, ['-r', codigo, ruta, JSON.stringify(titulos), JSON.stringify(filas)]);
    return ruta;
}

const documentoDe = (id) => bd(`SELECT documento FROM usuarios WHERE id = ${id}`);

async function analizar(page, ruta, archivo) {
    await page.goto(ruta);
    await expect(page.getByLabel('Archivo Excel (.xlsx)', { exact: true })).toBeVisible();
    await page.setInputFiles('input[name="archivo"]', archivo);
    await page.getByRole('button', { name: 'Analizar archivo' }).click();
    await expect(page).toHaveURL(new RegExp(`${ruta}/\\d+`));
    fs.unlinkSync(archivo);
}

const fila = (page, texto) => page.locator('tbody tr', { hasText: texto });

test('espacios: un espacio nuevo se crea con sus responsables y uno existente sin responsables los conserva', async ({ page }) => {
    const s = Date.now();
    const d = datos();
    const inst = d.instituciones.A;
    const aula = bd(`SELECT CONCAT_WS('|', codigo, nombre) FROM espacios WHERE id = ${d.espacios.aulaA}`).split('|');
    const responsablesAntes = bd(`SELECT GROUP_CONCAT(usuario_id ORDER BY usuario_id) FROM espacio_responsables WHERE espacio_id = ${d.espacios.aulaA}`);
    // Un usuario inactivo y un espacio en la papelera.
    bd(`INSERT INTO usuarios (documento, nombres, apellidos, cargo_id, email, password_hash, institucion_id, rol_id, activo)
        SELECT 'IN${s}', 'PW', 'INACTIVO', cargo_id, 'inactivo${s}@prueba.test', password_hash, institucion_id, rol_id, 0
          FROM usuarios WHERE id = ${d.usuarios.docente.id}`);
    bd(`INSERT INTO espacios (institucion_id, nombre, codigo, activo, eliminado_en) VALUES (${inst}, 'PW EN PAPELERA', 'PWE${s}DEL', 1, NOW())`);

    const archivo = crearExcel(['Numero', 'Nombre', 'Documentos_Responsables'], [
        [`PWE${s}N`, 'PW ESPACIO NUEVO', documentoDe(d.usuarios.secretario.id)],
        [aula[0], aula[1], ''],
        [`PWE${s}SIN`, 'PW NUEVO SIN RESPONSABLE', ''],
        [`PWE${s}INA`, 'PW CON INACTIVO', `IN${s}`],
        [`PWE${s}DEL`, 'PW EN PAPELERA', documentoDe(d.usuarios.secretario.id)],
    ]);
    await analizar(page, 'espacios/carga-masiva', archivo);

    await expect(fila(page, `PWE${s}N`).locator('.badge')).toHaveText('Nuevo');
    await expect(fila(page, aula[1]).locator('.badge')).toHaveText('Sin cambios');
    await expect(fila(page, `PWE${s}SIN`)).toContainText('Un espacio nuevo necesita al menos un documento de responsable');
    await expect(fila(page, `PWE${s}INA`)).toContainText(`IN${s} (usuario inactivo)`);
    await expect(fila(page, `PWE${s}DEL`)).toContainText('está en la papelera');

    // Con una fila válida, confirmar ya no falla (antes abría una transacción dentro de otra).
    await confirmar(page, () => page.getByRole('button', { name: 'Confirmar importación' }).click());
    await expect(page.locator('.avisos-pagina .alert-danger')).toContainText('aplicada parcialmente');
    expect(bd(`SELECT COUNT(*) FROM espacio_responsables er JOIN espacios e ON e.id = er.espacio_id
        WHERE e.codigo = 'PWE${s}N' AND er.usuario_id = ${d.usuarios.secretario.id}`), 'el espacio nuevo quedó con su responsable').toBe('1');
    expect(bd(`SELECT GROUP_CONCAT(usuario_id ORDER BY usuario_id) FROM espacio_responsables WHERE espacio_id = ${d.espacios.aulaA}`),
        'el espacio existente conserva sus responsables').toBe(responsablesAntes);
    expect(bd(`SELECT COUNT(*) FROM espacios WHERE codigo IN ('PWE${s}SIN', 'PWE${s}INA')`)).toBe('0');
});

test('usuarios: uno existente con columnas en blanco conserva sus datos y su rol; uno nuevo incompleto se rechaza', async ({ page }) => {
    const s = Date.now();
    const d = datos();
    const secretario = d.usuarios.secretario.id;
    const antes = bd(`SELECT CONCAT_WS('|', nombres, apellidos, email, cargo_id, rol_id) FROM usuarios WHERE id = ${secretario}`);
    const cargo = bd("SELECT nombre FROM cargos WHERE activo = 1 AND eliminado_en IS NULL ORDER BY id LIMIT 1");

    const archivo = crearExcel(['Documento', 'Nombres', 'Apellidos', 'Cargo', 'Correo', 'Rol'], [
        [documentoDe(secretario), '', '', '', '', ''],
        [`NS${s}`, '', 'SIN NOMBRES', cargo, `ns${s}@prueba.test`, ''],
        [`NC${s}`, 'PW', 'SIN CARGO', '', `nc${s}@prueba.test`, ''],
        [`NU${s}`, 'PW', 'NUEVO', cargo, `nu${s}@prueba.test`, ''],
    ]);
    await analizar(page, 'usuarios/carga-masiva', archivo);

    await expect(fila(page, documentoDe(secretario)).locator('.badge')).toHaveText('Sin cambios');
    await expect(fila(page, `NS${s}`)).toContainText('Un usuario nuevo necesita nombres, apellidos y correo');
    await expect(fila(page, `NC${s}`)).toContainText('Un usuario nuevo necesita el cargo');
    await expect(fila(page, `NU${s}`).locator('.badge')).toHaveText('Nuevo');

    await confirmar(page, () => page.getByRole('button', { name: 'Confirmar importación' }).click());
    await expect(page.locator('.avisos-pagina .alert-danger')).toContainText('2 filas no se cargaron');
    expect(bd(`SELECT CONCAT_WS('|', nombres, apellidos, email, cargo_id, rol_id) FROM usuarios WHERE id = ${secretario}`),
        'el secretario conserva sus datos y su rol (antes Rol en blanco lo pasaba a docente)').toBe(antes);
    expect(bd(`SELECT r.nombre FROM usuarios u JOIN roles r ON r.id = u.rol_id WHERE u.documento = 'NU${s}'`), 'Rol en blanco: docente').toBe('docente');
    expect(bd(`SELECT COUNT(*) FROM usuarios WHERE documento IN ('NS${s}', 'NC${s}')`)).toBe('0');
});

test('usuarios: un rector no puede modificar una cuenta de superusuario por la carga masiva', async ({ page }) => {
    const s = Date.now();
    const d = datos();
    const superusuario = d.usuarios.superusuario.id;
    const institucionRector = bd(`SELECT institucion_id FROM usuarios WHERE id = ${d.usuarios.rector.id}`);
    test.skip(bd(`SELECT institucion_id FROM usuarios WHERE id = ${superusuario}`) !== institucionRector,
        'El superusuario de prueba no comparte institución con el rector');
    const antes = bd(`SELECT CONCAT_WS('|', email, rol_id) FROM usuarios WHERE id = ${superusuario}`);

    const archivo = crearExcel(['Documento', 'Nombres', 'Apellidos', 'Cargo', 'Correo', 'Rol'], [
        [documentoDe(superusuario), '', '', '', `atacante${s}@prueba.test`, ''],
    ]);
    await analizar(page, 'usuarios/carga-masiva', archivo);

    await expect(fila(page, documentoDe(superusuario))).toContainText('Es una cuenta de superusuario: solo otro superusuario puede modificarla');
    await confirmar(page, () => page.getByRole('button', { name: 'Confirmar importación' }).click());
    await expect(page.locator('.avisos-pagina .alert-danger')).toContainText('aplicada parcialmente');
    expect(bd(`SELECT CONCAT_WS('|', email, rol_id) FROM usuarios WHERE id = ${superusuario}`), 'el correo y el rol del superusuario no cambian').toBe(antes);
});
