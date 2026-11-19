import { test, expect } from '@playwright/test';
import { execFileSync } from 'node:child_process';
import fs from 'node:fs';
import os from 'node:os';
import path from 'node:path';
import { PHP_BIN } from '../playwright.config.js';
import { datos, bd } from './helpers/datos.js';
import { confirmar } from './helpers/confirmacion.js';

/**
 * Carga masiva de bienes (CargaMasivaService, v1.2.39): una columna opcional en blanco
 * conserva lo que el bien ya tiene (antes Marca en blanco la borraba y Valor en blanco lo
 * dejaba en $0), y los valores imposibles o la regla de «Sin cartera» marcan la fila como
 * inválida con su motivo, sin tumbar la carga.
 */
test.beforeEach(() => test.skip(datos().remoto === true, 'Necesita la base de pruebas local'));
test.use({ storageState: 'playwright/.auth/rector.json' });

/** .xlsx de carga masiva (columnas A-H) con las filas dadas; los valores van como texto. */
function crearExcel(filas) {
    const ruta = path.join(os.tmpdir(), `carga-${Date.now()}-${Math.random().toString(16).slice(2)}.xlsx`);
    const codigo = `require 'vendor/autoload.php';
        $filas = json_decode($argv[2], true); $libro = new PhpOffice\\PhpSpreadsheet\\Spreadsheet(); $hoja = $libro->getActiveSheet();
        $hoja->fromArray(['Codigo', 'Descripcion', 'Marca', 'Fecha_Ingreso', 'Valor', 'Ubicacion', 'Categoria', 'Responsable_individual'], null, 'A1');
        foreach ($filas as $i => $fila) { foreach ($fila as $j => $valor) {
            if ($valor !== '') { $hoja->getCell([$j + 1, $i + 2])->setValueExplicit((string) $valor, PhpOffice\\PhpSpreadsheet\\Cell\\DataType::TYPE_STRING); }
        } }
        (new PhpOffice\\PhpSpreadsheet\\Writer\\Xlsx($libro))->save($argv[1]);`;
    execFileSync(PHP_BIN, ['-r', codigo, ruta, JSON.stringify(filas)]);
    return ruta;
}

test('una columna en blanco conserva lo que el bien ya tiene; los valores imposibles se rechazan con su motivo', async ({ page }) => {
    const s = Date.now();
    const inst = datos().instituciones.A;
    bd(`INSERT INTO bienes (institucion_id, codigo_identificacion, descripcion, marca, categoria_id, fecha_ingreso, valor, estado, qr_token)
        SELECT ${inst}, 'PWC-${s}-EX', 'PW CARGA CONSERVA', 'Rimax', id, '2024-03-10', 350000, 'activo', UUID()
          FROM categorias_bienes WHERE institucion_id = ${inst} AND nombre = 'Muebles' LIMIT 1`);

    const archivo = crearExcel([
        [`PWC-${s}-EX`, 'PW CARGA CONSERVA', '', '', '', '', '', ''],
        [`PWC-${s}-NEG`, 'PW NEGATIVO', '', '', '-5', '', '', ''],
        [`PWC-${s}-GRANDE`, 'PW GRANDE', '', '', '99999999999', '', '', ''],
        [`PWC-${s}-ABC`, 'PW ILEGIBLE', '', '', 'abc', '', '', ''],
        [`PWC-${s}-SC`, 'PW SIN CARTERA', '', '', '1000', '', 'Sin cartera', ''],
        [`PWC-${s}-NUEVO`, 'PW NUEVO', '', '', '', '', '', ''],
    ]);

    await page.goto('cargas-masivas');
    await expect(page.getByText('Sube la plantilla diligenciada')).toBeVisible();
    await page.setInputFiles('input[name="archivo"]', archivo);
    await page.getByRole('button', { name: 'Analizar archivo' }).click();
    await expect(page).toHaveURL(/\/cargas-masivas\/\d+/);
    fs.unlinkSync(archivo);

    const fila = (codigo) => page.locator('tr', { has: page.locator('td[data-label="Código"]', { hasText: codigo }) });
    await expect(fila(`PWC-${s}-EX`).locator('.badge')).toHaveText('Sin cambios');
    await expect(fila(`PWC-${s}-NEG`)).toContainText('El valor no puede ser negativo ni tener más de 10 dígitos');
    await expect(fila(`PWC-${s}-GRANDE`)).toContainText('El valor no puede ser negativo ni tener más de 10 dígitos');
    await expect(fila(`PWC-${s}-ABC`)).toContainText('El valor «abc» no se pudo leer');
    await expect(fila(`PWC-${s}-SC`)).toContainText('llevan un código de exactamente 10 números');
    await expect(fila(`PWC-${s}-NUEVO`).locator('.badge')).toHaveText('Nuevo');

    await confirmar(page, () => page.getByRole('button', { name: 'Confirmar importación' }).click());
    await expect(page.locator('.avisos-pagina .alert-danger')).toContainText('aplicada parcialmente');

    expect(bd(`SELECT CONCAT_WS('|', marca, valor, fecha_ingreso) FROM bienes WHERE codigo_identificacion = 'PWC-${s}-EX'`),
        'el bien existente conserva su marca, su valor y su fecha').toBe('Rimax|350000.00|2024-03-10');
    expect(bd(`SELECT valor FROM bienes WHERE codigo_identificacion = 'PWC-${s}-NUEVO'`), 'un bien nuevo sin valor queda en 0').toBe('0.00');
    expect(bd(`SELECT COUNT(*) FROM bienes WHERE codigo_identificacion IN ('PWC-${s}-NEG', 'PWC-${s}-GRANDE', 'PWC-${s}-ABC', 'PWC-${s}-SC')`),
        'las filas inválidas no crean nada').toBe('0');
});

test('una fila con marca o valor nuevos sí los cambia', async ({ page }) => {
    const s = Date.now();
    const inst = datos().instituciones.A;
    bd(`INSERT INTO bienes (institucion_id, codigo_identificacion, descripcion, marca, categoria_id, fecha_ingreso, valor, estado, qr_token)
        SELECT ${inst}, 'PWC-${s}-CAMBIA', 'PW CARGA CAMBIA', 'Rimax', id, '2024-03-10', 350000, 'activo', UUID()
          FROM categorias_bienes WHERE institucion_id = ${inst} AND nombre = 'Muebles' LIMIT 1`);

    const archivo = crearExcel([[`PWC-${s}-CAMBIA`, 'PW CARGA CAMBIA', 'Pelikan', '', '$ 420.000', '', '', '']]);
    await page.goto('cargas-masivas');
    await page.setInputFiles('input[name="archivo"]', archivo);
    await page.getByRole('button', { name: 'Analizar archivo' }).click();
    fs.unlinkSync(archivo);

    const cambios = page.locator('td[data-label="Cambios"]');
    await expect(cambios).toContainText('Marca: "Rimax" → "Pelikan"');
    await expect(cambios).toContainText('Valor: "350000.00" → "420000"');
    await confirmar(page, () => page.getByRole('button', { name: 'Confirmar importación' }).click());
    await expect(page.locator('.avisos-pagina .alert-success')).toContainText('Carga masiva aplicada correctamente.');
    expect(bd(`SELECT CONCAT_WS('|', marca, valor) FROM bienes WHERE codigo_identificacion = 'PWC-${s}-CAMBIA'`)).toBe('Pelikan|420000.00');
});
