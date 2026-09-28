import { test, expect } from '@playwright/test';
import { execFileSync } from 'node:child_process';
import fs from 'node:fs';
import os from 'node:os';
import path from 'node:path';
import { PHP_BIN } from '../playwright.config.js';
import { datos, bd, comoRol } from './helpers/datos.js';

/**
 * "Descargar toda la información" (ReporteController::exportacionCompleta y
 * App\Services\ExportacionInstitucion): un ZIP con un libro de Excel de la institución
 * y, si se pide, sus fotos. Solo el rector (de su institución) y el superusuario (de la que
 * elija); nunca contraseñas ni datos de otra institución.
 */
test.beforeEach(() => test.skip(datos().remoto === true, 'Necesita la base de pruebas local'));

/**
 * Guarda el ZIP y devuelve sus entradas y, de cada hoja pedida del libro informacion.xlsx,
 * sus filas como texto separado por ";" (lo lee PHP con ZipArchive y PhpSpreadsheet).
 */
function leerZip(cuerpo, hojas) {
    const ruta = path.join(os.tmpdir(), `exportacion-${Date.now()}-${Math.random().toString(16).slice(2)}.zip`);
    fs.writeFileSync(ruta, cuerpo);
    const codigo = `require 'vendor/autoload.php';
        $z = new ZipArchive(); $z->open($argv[1]); $e = []; $h = [];
        for ($i = 0; $i < $z->numFiles; $i++) { $e[] = $z->getNameIndex($i); }
        $xlsx = $argv[1] . '.xlsx'; file_put_contents($xlsx, (string) $z->getFromName('informacion.xlsx')); $z->close();
        $libro = PhpOffice\\PhpSpreadsheet\\IOFactory::load($xlsx);
        foreach (array_slice($argv, 2) as $n) {
            $h[$n] = implode("\n", array_map(fn ($f) => implode(';', array_map('strval', $f)), $libro->getSheetByName($n)->toArray(null, false, false)));
        }
        unlink($xlsx);
        echo json_encode(['entradas' => $e, 'hojas' => $h]);`;
    try {
        return JSON.parse(execFileSync(PHP_BIN, ['-r', codigo, ruta, ...hojas], { encoding: 'utf8' }));
    } finally {
        fs.unlinkSync(ruta);
    }
}

test('el rector descarga los datos de su institución, sin contraseñas ni datos de otra', async ({ browser }) => {
    const d = datos();
    const contexto = await comoRol(browser, 'rector');
    const pagina = await contexto.newPage();

    await pagina.goto('reportes');
    const seccion = pagina.locator('section', { has: pagina.getByRole('heading', { name: 'Descargar toda la información' }) });
    await expect(seccion).toBeVisible();

    const [descarga] = await Promise.all([
        pagina.waitForEvent('download'),
        seccion.getByRole('link', { name: 'Solo los datos' }).click(),
    ]);
    expect(descarga.suggestedFilename()).toMatch(/^MIA_.+_\d{4}-\d{2}-\d{2}\.zip$/);
    const zip = leerZip(fs.readFileSync(await descarga.path()), ['Bienes', 'Usuarios']);

    expect(zip.entradas).toEqual(expect.arrayContaining(['LEEME.txt', 'informacion.xlsx']));
    expect(zip.entradas.some((e) => e.startsWith('archivos/'))).toBe(false);

    // Bienes: los de su institución sí, los de la institución B no.
    expect(zip.hojas.Bienes).toContain('PA-0001');
    expect(zip.hojas.Bienes).not.toContain('PB-0001');
    expect(zip.hojas.Bienes.split('\n')[0]).toContain('Código;Descripción');
    // Usuarios: con correo, pero nunca la contraseña (ni su hash) ni la clave del autenticador.
    expect(zip.hojas.Usuarios).toContain(d.usuarios.docente.email);
    expect(zip.hojas.Usuarios).not.toContain(d.usuarios.rector_b.email);
    expect(zip.hojas.Usuarios).not.toMatch(/\$2y\$/);
    expect(zip.hojas.Usuarios.split('\n')[0]).not.toMatch(/password|totp_secreto/i);

    const registradas = Number(bd(`SELECT COUNT(*) FROM auditoria WHERE accion = 'exportar_todo' AND usuario_id = ${d.usuarios.rector.id}`));
    expect(registradas).toBeGreaterThan(0);
    await contexto.close();
});

test('con fotos y documentos, el ZIP trae la foto del bien', async ({ browser }) => {
    const contexto = await comoRol(browser, 'rector');
    const respuesta = await contexto.request.get('reportes/exportacion-completa.zip?archivos=1');
    expect(respuesta.status()).toBe(200);
    expect(respuesta.headers()['content-type']).toContain('application/zip');
    const zip = leerZip(await respuesta.body(), []);
    expect(zip.entradas).toContain('archivos/fotos_bienes/PA-0001_1.jpg');
    await contexto.close();
});

test('el secretario y el docente no pueden descargarla, y el superusuario debe elegir institución', async ({ browser, page }) => {
    for (const rol of ['secretario', 'docente']) {
        const contexto = await comoRol(browser, rol);
        const respuesta = await contexto.request.get('reportes/exportacion-completa.zip', { maxRedirects: 0 });
        expect(respuesta.status(), rol).toBe(403);
        await contexto.close();
    }

    // Proyecto "authenticated": superusuario. Sin institución, los botones están inactivos.
    await page.goto('reportes');
    const soloDatos = page.getByRole('link', { name: 'Solo los datos' });
    await expect(soloDatos).toHaveClass(/disabled/);
    const respuesta = await page.request.get('reportes/exportacion-completa.zip', { maxRedirects: 0 });
    expect(respuesta.status()).toBe(302);

    // Con la institución B elegida, descarga la B (y no la A).
    const zip = leerZip(await (await page.request.get(`reportes/exportacion-completa.zip?institucion=${datos().instituciones.B}`)).body(), ['Bienes']);
    expect(zip.hojas.Bienes).toContain('PB-0001');
    expect(zip.hojas.Bienes).not.toContain('PA-0001');
});
