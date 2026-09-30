import { test, expect } from '@playwright/test';
import { execFileSync } from 'node:child_process';
import fs from 'node:fs';
import os from 'node:os';
import path from 'node:path';
import { PHP_BIN } from '../playwright.config.js';
import { datos, bd, comoRol } from './helpers/datos.js';

/**
 * "Acta de bienes a cargo" (ActaACargo) y la columna "Responsable individual" de la carga
 * masiva de bienes (CargaMasivaService).
 */
test.beforeEach(() => test.skip(datos().remoto === true, 'Necesita la base de pruebas local'));

const nombre = (id) => bd(`SELECT CONCAT(nombres, ' ', apellidos) FROM usuarios WHERE id = ${id}`);
const documento = (id) => bd(`SELECT documento FROM usuarios WHERE id = ${id}`);

function leerXlsx(cuerpo) {
    const ruta = path.join(os.tmpdir(), `acta-${Date.now()}-${Math.random().toString(16).slice(2)}.xlsx`);
    fs.writeFileSync(ruta, cuerpo);
    const codigo = `require 'vendor/autoload.php';
        $libro = PhpOffice\\PhpSpreadsheet\\IOFactory::load($argv[1]); $h = [];
        foreach ($libro->getWorksheetIterator() as $hoja) { $h[$hoja->getTitle()] = $hoja->toArray(null, false, false); }
        echo json_encode($h);`;
    try {
        return JSON.parse(execFileSync(PHP_BIN, ['-r', codigo, ruta], { encoding: 'utf8' }));
    } finally {
        fs.unlinkSync(ruta);
    }
}

/** Escribe un .xlsx de carga masiva (columnas A-H) con las filas dadas; los valores van como texto. */
function crearExcel(filas) {
    const ruta = path.join(os.tmpdir(), `carga-${Date.now()}-${Math.random().toString(16).slice(2)}.xlsx`);
    const codigo = `require 'vendor/autoload.php';
        $filas = json_decode($argv[2], true); $libro = new PhpOffice\\PhpSpreadsheet\\Spreadsheet(); $hoja = $libro->getActiveSheet();
        $hoja->fromArray(['Codigo', 'Descripcion', 'Marca', 'Fecha_Ingreso', 'Valor', 'Ubicacion', 'Categoria', 'Responsable_individual'], null, 'A1');
        foreach ($filas as $i => $fila) { foreach ($fila as $j => $valor) {
            $hoja->getCell([$j + 1, $i + 2])->setValueExplicit((string) $valor, PhpOffice\\PhpSpreadsheet\\Cell\\DataType::TYPE_STRING);
        } }
        (new PhpOffice\\PhpSpreadsheet\\Writer\\Xlsx($libro))->save($argv[1]);`;
    execFileSync(PHP_BIN, ['-r', codigo, ruta, JSON.stringify(filas)]);
    return ruta;
}

test('el acta trae los bienes individuales y los grupales del funcionario, con totales', async ({ browser }) => {
    const s = Date.now();
    const d = datos();
    const inst = d.instituciones.A;
    // Un espacio del docente con un bien (grupal) y un portátil a su cargo (individual).
    bd(`INSERT INTO espacios (institucion_id, codigo, nombre, activo) VALUES (${inst}, 'PWA-${s}', 'PW Acta ${s}', 1)`);
    const espacio = Number(bd(`SELECT id FROM espacios WHERE codigo = 'PWA-${s}'`));
    bd(`INSERT INTO espacio_responsables (espacio_id, usuario_id) VALUES (${espacio}, ${d.usuarios.docente.id}), (${espacio}, ${d.usuarios.secretario.id})`);
    for (const [etiqueta, valor] of [['GRUPAL', 300000], ['INDIVIDUAL', 2500000]]) {
        bd(`INSERT INTO bienes (institucion_id, codigo_identificacion, descripcion, categoria_id, fecha_ingreso, valor, estado, qr_token)
            SELECT ${inst}, 'PWA-${s}-${etiqueta}', 'PW acta ${etiqueta}', id, CURDATE(), ${valor}, 'activo', UUID()
              FROM categorias_bienes WHERE institucion_id = ${inst} AND nombre = 'Muebles' LIMIT 1`);
    }
    const grupal = Number(bd(`SELECT id FROM bienes WHERE codigo_identificacion = 'PWA-${s}-GRUPAL'`));
    const individual = Number(bd(`SELECT id FROM bienes WHERE codigo_identificacion = 'PWA-${s}-INDIVIDUAL'`));
    bd(`INSERT INTO asignaciones (bien_id, usuario_responsable_id, espacio_id, fecha_asignacion, activa) VALUES
        (${grupal}, NULL, ${espacio}, CURDATE(), 1), (${individual}, ${d.usuarios.docente.id}, NULL, CURDATE(), 1)`);

    const contexto = await comoRol(browser, 'rector');
    const pagina = await contexto.newPage();
    // Desde la lista, con el filtro Responsable.
    await pagina.goto(`bienes?responsable=${d.usuarios.docente.id}`);
    const href = await pagina.locator('#descargarActaACargo').getAttribute('href');
    expect(href).toContain(`usuario=${d.usuarios.docente.id}`);

    const respuesta = await contexto.request.get(href);
    expect(respuesta.status()).toBe(200);
    expect(respuesta.headers()['content-disposition']).toContain('acta_bienes_a_cargo_');
    const hoja = leerXlsx(await respuesta.body())['Acta de bienes a cargo'];
    const texto = hoja.map((f) => f.filter((c) => c !== null && c !== '').join(' | '));
    const todo = texto.join('\n');
    expect(todo).toContain('ACTA DE BIENES A CARGO');
    expect(todo).toContain(nombre(d.usuarios.docente.id));

    const inicioIndividual = texto.findIndex((t) => t.startsWith('RESPONSABILIDAD INDIVIDUAL'));
    const inicioGrupal = texto.findIndex((t) => t.startsWith('RESPONSABILIDAD GRUPAL'));
    expect(inicioIndividual).toBeGreaterThan(0);
    expect(inicioGrupal).toBeGreaterThan(inicioIndividual);
    const seccionIndividual = texto.slice(inicioIndividual, inicioGrupal).join('\n');
    const seccionGrupal = texto.slice(inicioGrupal).join('\n');
    expect(seccionIndividual).toContain(`PWA-${s}-INDIVIDUAL`);
    expect(seccionIndividual).not.toContain(`PWA-${s}-GRUPAL`);
    expect(seccionGrupal).toContain(`PWA-${s}-GRUPAL`);
    // En la sección grupal se nombra a los demás responsables del espacio.
    expect(seccionGrupal).toContain(`(con ${nombre(d.usuarios.secretario.id)})`);

    // El total coincide con lo que la base dice que responde el docente.
    const filaTotal = hoja.find((f) => typeof f[0] === 'string' && f[0].startsWith('TOTAL:'));
    const [cantidad, valor] = bd(`SELECT COUNT(*), ROUND(SUM(b.valor)) FROM bienes b JOIN asignaciones a ON a.bien_id = b.id AND a.activa = 1
        WHERE b.institucion_id = ${inst} AND b.estado NOT IN ('reintegrado', 'dado_de_baja')
          AND (a.usuario_responsable_id = ${d.usuarios.docente.id} OR (a.usuario_responsable_id IS NULL AND EXISTS (
               SELECT 1 FROM espacio_responsables er WHERE er.espacio_id = a.espacio_id AND er.usuario_id = ${d.usuarios.docente.id})))`)
        .split('\t').map(Number);
    expect(filaTotal[0]).toBe(`TOTAL: ${cantidad} bien(es)`);
    expect(Math.round(Number(filaTotal[7]))).toBe(valor);
    await contexto.close();
});

test('el acta es para quien genera reportes y solo de funcionarios de su institución', async ({ browser }) => {
    const d = datos();
    const docente = await comoRol(browser, 'docente');
    expect((await docente.request.get(`reportes/acta-a-cargo.xlsx?usuario=${d.usuarios.docente.id}`, { maxRedirects: 0 })).status()).toBe(403);
    await docente.close();

    const secretario = await comoRol(browser, 'secretario');
    expect((await secretario.request.get(`reportes/acta-a-cargo.xlsx?usuario=${d.usuarios.docente.id}`)).status()).toBe(200);
    // Un funcionario de otra institución: vuelve a Reportes con un aviso.
    const ajena = await secretario.request.get(`reportes/acta-a-cargo.xlsx?usuario=${d.usuarios.rector_b.id}`, { maxRedirects: 0 });
    expect(ajena.status()).toBe(302);
    const pagina = await secretario.newPage();
    await pagina.goto('reportes');
    await expect(pagina.locator('.alert-danger')).toContainText('Elige un funcionario de tu institución');
    await secretario.close();
});

test('carga masiva: la columna "Responsable individual" deja los bienes a cargo de esa persona', async ({ browser }) => {
    const s = Date.now();
    const d = datos();
    const inst = d.instituciones.A;
    const aula = bd(`SELECT codigo FROM espacios WHERE id = ${d.espacios.aulaA}`);
    // Un bien ya existente, grupal en el aula: la fila solo le pone responsable.
    bd(`INSERT INTO bienes (institucion_id, codigo_identificacion, descripcion, categoria_id, fecha_ingreso, valor, estado, qr_token)
        SELECT ${inst}, 'PWM-${s}-EXISTE', 'PW CARGA EXISTENTE', id, CURDATE(), 1000, 'activo', UUID()
          FROM categorias_bienes WHERE institucion_id = ${inst} AND nombre = 'Muebles' LIMIT 1`);
    const existente = Number(bd(`SELECT id FROM bienes WHERE codigo_identificacion = 'PWM-${s}-EXISTE'`));
    bd(`INSERT INTO asignaciones (bien_id, espacio_id, fecha_asignacion, activa) VALUES (${existente}, ${d.espacios.aulaA}, CURDATE(), 1)`);

    const archivo = crearExcel([
        [`PWM-${s}-1`, 'PW CARGA SIN ESPACIO', '', '', '1000', '', 'Muebles', documento(d.usuarios.docente.id)],
        [`PWM-${s}-2`, 'PW CARGA GUARDADO', '', '', '1000', aula, 'Muebles', documento(d.usuarios.secretario.id)],
        [`PWM-${s}-3`, 'PW CARGA ERRADA', '', '', '1000', '', 'Muebles', `NOEXISTE${s}`],
        [`PWM-${s}-EXISTE`, 'PW CARGA EXISTENTE', '', '', '1000', '', 'Muebles', documento(d.usuarios.docente.id)],
    ]);

    const pagina = await (await comoRol(browser, 'rector')).newPage();
    await pagina.goto('cargas-masivas');
    await pagina.setInputFiles('input[name="archivo"]', archivo);
    await pagina.getByRole('button', { name: 'Analizar archivo' }).click();
    await expect(pagina).toHaveURL(/\/cargas-masivas\/\d+/);
    await expect(pagina.getByText(`NOEXISTE${s}`)).toBeVisible();
    await expect(pagina.getByText('Responsable individual')).toBeVisible();

    pagina.once('dialog', (dialogo) => dialogo.accept());
    await pagina.getByRole('button', { name: 'Confirmar importación' }).click();
    await expect(pagina.locator('.alert-danger')).toContainText('aplicada parcialmente');
    fs.unlinkSync(archivo);

    const asignacion = (codigo) => bd(`SELECT CONCAT(IFNULL(a.usuario_responsable_id, '-'), '/', IFNULL(a.espacio_id, '-'))
        FROM asignaciones a JOIN bienes b ON b.id = a.bien_id WHERE b.codigo_identificacion = '${codigo}' AND a.activa = 1`);
    expect(asignacion(`PWM-${s}-1`)).toBe(`${d.usuarios.docente.id}/-`);
    expect(asignacion(`PWM-${s}-2`)).toBe(`${d.usuarios.secretario.id}/${d.espacios.aulaA}`);
    expect(bd(`SELECT COUNT(*) FROM bienes WHERE codigo_identificacion = 'PWM-${s}-3'`)).toBe('0');
    // El existente conserva su espacio y queda a cargo del docente.
    expect(asignacion(`PWM-${s}-EXISTE`)).toBe(`${d.usuarios.docente.id}/${d.espacios.aulaA}`);
    await pagina.context().close();
});

test('la plantilla de la carga masiva trae la columna del responsable', async ({ browser }) => {
    const contexto = await comoRol(browser, 'rector');
    const respuesta = await contexto.request.get('cargas-masivas/plantilla.xlsx');
    expect(respuesta.status()).toBe(200);
    const hoja = Object.values(leerXlsx(await respuesta.body()))[0];
    expect(hoja[0]).toEqual(['Codigo', 'Descripcion', 'Marca', 'Fecha_Ingreso', 'Valor', 'Ubicacion', 'Categoria', 'Responsable_individual']);
    await contexto.close();
});
