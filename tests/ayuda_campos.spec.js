import { test, expect } from '@playwright/test';
import { randomBytes } from 'node:crypto';
import { datos, bd, comoRol, sinSesion } from './helpers/datos.js';

/**
 * Ayuda emergente de los campos (App\Helpers\AyudaCampo y assets/js/ayuda-campo.js): el
 * ícono (?) junto al título de cada campo del formulario de bienes muestra una explicación
 * detallada con el cursor, con el teclado (Tab / Escape) y con un toque en el celular. El
 * formulario ya no muestra textos sueltos debajo de los campos.
 */
test.use({ storageState: 'playwright/.auth/rector.json' });

const ayudaDe = (page, titulo) => page.getByRole('button', { name: `Ayuda: ${titulo}`, exact: true });
const globoDe = async (page, boton) => page.locator(`#${await boton.getAttribute('aria-controls')}`);

test('con el cursor se abre y al salir se cierra; con un clic queda fija', async ({ page }) => {
    await page.goto('bienes/crear');
    const boton = ayudaDe(page, 'Categoría');
    const globo = await globoDe(page, boton);
    await expect(globo).toBeHidden();

    await boton.hover();
    await expect(globo).toBeVisible();
    await expect(globo).toContainText('Sin cartera');
    await expect(boton).toHaveAttribute('aria-expanded', 'true');

    // Se puede pasar del ícono al globo (para leerlo) sin que se cierre.
    await globo.hover();
    await page.waitForTimeout(400);
    await expect(globo).toBeVisible();

    await page.locator('h1').hover();
    await expect(globo).toBeHidden();
    await expect(boton).toHaveAttribute('aria-expanded', 'false');

    // Un clic la deja fija aunque el cursor se vaya; otro clic fuera la cierra.
    await boton.click();
    await page.locator('h1').hover();
    await page.waitForTimeout(400);
    await expect(globo).toBeVisible();
    await page.locator('h1').click();
    await expect(globo).toBeHidden();

    // Solo una abierta a la vez.
    await ayudaDe(page, 'Código de identificación').click();
    await ayudaDe(page, 'Descripción').click();
    await expect(page.locator('.ayuda-campo-globo:visible')).toHaveCount(1);
    await expect(page.locator('.ayuda-campo-globo:visible')).toContainText('SILLA UNIVERSITARIA EN MADERA');
});

test('con el teclado: Tab la abre y Escape la cierra', async ({ page }) => {
    await page.goto('bienes/crear');
    const boton = ayudaDe(page, 'Código de identificación');
    const globo = await globoDe(page, boton);

    await page.locator('#codigoIdentificacion').focus();
    await page.keyboard.press('Shift+Tab');
    await expect(boton).toBeFocused();
    await expect(globo).toBeVisible();
    await expect(globo).toContainText('No se puede repetir');

    await page.keyboard.press('Escape');
    await expect(globo).toBeHidden();
});

test('el nombre de cada campo no cambia y ya no hay textos sueltos', async ({ page }) => {
    await page.goto('bienes/crear');
    await expect(page.getByLabel('Código de identificación', { exact: true })).toHaveAttribute('id', 'codigoIdentificacion');
    await expect(page.getByLabel('Descripción', { exact: true })).toHaveAttribute('id', 'campo-descripcion');
    await expect(page.getByLabel('Fecha de ingreso', { exact: true })).toHaveAttribute('id', 'campo-fecha-ingreso');
    await expect(page.getByRole('group', { name: 'Responsabilidad', exact: true })).toBeVisible();
    await expect(page.getByLabel('Grupal', { exact: true })).toBeChecked();

    await expect(page.getByText('Los campos marcados con')).toHaveCount(0);
    await expect(page.locator('#datosBien .form-text')).toHaveCount(0);
    await expect(page.getByRole('heading', { name: 'Datos del bien' })).toBeVisible();
    await expect(page.getByRole('heading', { name: /Foto y documentos/ })).toBeVisible();

    // La Categoría va antes que el Código (elegir "Sin cartera" sugiere el código).
    // (Tom Select cambia el "for" de la etiqueta de Categoría: se busca por el texto.)
    const categoria = await page.locator('.etiqueta-ayuda label', { hasText: /^Categoría$/ }).boundingBox();
    const codigo = await page.locator('.etiqueta-ayuda label', { hasText: /^Código de identificación$/ }).boundingBox();
    expect(categoria.y < codigo.y || (categoria.y === codigo.y && categoria.x < codigo.x)).toBe(true);
});

test('la ficha del bien y el alta masiva también tienen su ayuda', async ({ page }) => {
    test.skip(datos().remoto === true, 'Necesita la base de pruebas local');
    await page.goto(`bienes/${datos().bienes.A.silla}/editar`);
    await ayudaDe(page, '¿Qué deseas hacer con este bien?').click();
    await expect(page.locator('.ayuda-campo-globo:visible')).toContainText('pendiente de aprobación');
    await expect(ayudaDe(page, 'Imprimir QR')).toBeVisible();

    await page.goto('bienes/alta-masiva');
    await ayudaDe(page, 'Código de lote').click();
    await expect(page.locator('.ayuda-campo-globo:visible')).toContainText('CÓDIGO-001');
    await expect(page.getByText('Los campos marcados con')).toHaveCount(0);
});

test('la cartera recibida explica cada campo como el formulario de bienes', async ({ page }) => {
    await page.goto('cartera/enviar');
    const ayudas = {
        'Cartera recibida de la Alcaldía': 'Verificar un lote',
        'Funcionario que solicitó la cartera': 'usuarios activos de la institución',
        'Correo del funcionario que la solicitó': 'Cámbialo si la solicitud salió de otro correo',
        'Funcionario de la Alcaldía que envió la cartera': 'como aparecen en la firma del correo',
        'Correo desde el que llegó la cartera': 'Cópiala del correo recibido',
        'Fecha en que se recibió': 'solo se verifican los lotes entregados a la Alcaldía hasta esta fecha',
        'Archivo de la cartera recibida': 'Máximo 8 MB',
    };
    for (const [titulo, texto] of Object.entries(ayudas)) {
        const boton = ayudaDe(page, titulo);
        const globo = await globoDe(page, boton);
        await boton.hover();
        await expect(globo, titulo).toBeVisible();
        await expect(globo, titulo).toContainText(texto);
        await page.keyboard.press('Escape');
        await page.locator('h1').hover();
        await expect(globo, titulo).toBeHidden();
    }
    // El nombre de cada campo no cambia: el ícono va fuera de la etiqueta.
    await expect(page.getByLabel('Fecha en que se recibió', { exact: true })).toHaveAttribute('type', 'date');
    // Como en bienes, sin textos sueltos: la explicación está en el ícono (?).
    await expect(page.getByText('Los campos marcados con')).toHaveCount(0);
    await expect(page.getByText('Se llena con el correo del funcionario elegido')).toHaveCount(0);
});

/** Cada (?) de la página abre su globo con el texto esperado y se cierra al salir. */
async function revisarAyudas(page, ayudas, fuera = 'h1') {
    for (const [titulo, texto] of Object.entries(ayudas)) {
        const boton = ayudaDe(page, titulo);
        const globo = await globoDe(page, boton);
        await boton.hover();
        await expect(globo, titulo).toBeVisible();
        await expect(globo, titulo).toContainText(texto);
        await page.keyboard.press('Escape');
        await page.locator(fuera).hover();
        await expect(globo, titulo).toBeHidden();
    }
    await expect(page.getByText('Los campos marcados con')).toHaveCount(0);
}

test('formatos de reintegro, de plaqueteo y facturas explican cada campo como el formulario de bienes', async ({ page }) => {
    await page.goto('formatos-reintegro');
    await revisarAyudas(page, {
        'Formatos de reintegro': 'FO-ADMI-009',
        'Fecha del reintegro': 'día en que se entregaron los bienes a la Alcaldía',
        'Descripción del formato de reintegro': 'Máximo 300 caracteres',
        'Archivo del formato de reintegro': 'escaneado en PDF. Máximo 8 MB',
    });
    await expect(page.locator('#campo-descripcion')).toHaveAttribute('maxlength', '300');

    await page.goto('formatos-plaqueteo');
    await revisarAyudas(page, {
        'Formatos de plaqueteo': 'Código de identificación',
        'Fecha del plaqueteo': 'día en que se pusieron las placas',
        'Funcionario que asistió': 'Máximo 150 caracteres',
        'Descripción del formato de plaqueteo': 'Máximo 300 caracteres',
        'Archivo del formato de plaqueteo': 'escaneado en PDF. Máximo 8 MB',
    });
    await expect(page.locator('#campo-funcionario-asistio')).toHaveAttribute('maxlength', '150');
    await expect(page.locator('#campo-descripcion')).toHaveAttribute('maxlength', '300');

    await page.goto('facturas');
    await revisarAyudas(page, {
        Facturas: 'se adjunta en la ficha de ese bien',
        'Fecha de la factura': 'fecha de expedición',
        'Descripción breve': 'Máximo 300 caracteres',
        'Archivo de la factura': 'PDF o una foto (JPG o PNG). Máximo 8 MB',
    });
    await expect(page.locator('#campo-descripcion')).toHaveAttribute('maxlength', '300');
});

test('un texto más largo de lo que cabe se rechaza con su motivo, sin error del servidor', async ({ page }) => {
    test.skip(datos().remoto === true, 'Necesita la base de pruebas local');
    const pdf = { name: 'documento.pdf', mimeType: 'application/pdf', buffer: Buffer.from('%PDF-1.4\n1 0 obj<<>>endobj\ntrailer<<>>\n%%EOF\n') };
    for (const [ventana, campos, motivo] of [
        ['formatos-reintegro', { fecha_reintegro: '2026-01-15', descripcion: 'x'.repeat(301) }, 'máximo 300 caracteres'],
        ['formatos-plaqueteo', { fecha_plaqueteo: '2026-01-15', funcionario_asistio: 'PW', descripcion: 'x'.repeat(301) }, 'máximo 300 caracteres'],
        ['formatos-plaqueteo', { fecha_plaqueteo: '2026-01-15', funcionario_asistio: 'x'.repeat(151) }, 'máximo 150 caracteres'],
        ['facturas', { fecha_factura: '2026-01-15', descripcion: 'x'.repeat(301) }, 'máximo 300 caracteres'],
    ]) {
        await page.goto(ventana);
        const token = await page.locator('input[name="_csrf"]').first().inputValue();
        const respuesta = await page.request.post(ventana, {
            multipart: { _csrf: token, ...campos, archivo: pdf },
            maxRedirects: 0,
        });
        expect(respuesta.status(), ventana).toBe(302);
        await page.goto(ventana);
        await expect(page.locator('.alert-danger'), ventana).toContainText(motivo);
    }
});

test('bajas y verificación física explican cada campo como el formulario de bienes', async ({ page }) => {
    test.skip(datos().remoto === true, 'Necesita la base de pruebas local');
    const d = datos();

    await page.goto('bajas');
    await revisarAyudas(page, { 'Bajas de bienes': 'Solo se dan de baja los bienes de la categoría' });

    await page.goto(`qr/${d.qr.para_baja}/baja`);
    await revisarAyudas(page, {
        'Reportar baja': 'no da de baja',
        'Estado del bien': 'Deteriorado por uso',
        Ubicación: 'déjalo vacío si se perdió',
        'Descripción de la baja': 'Máximo 500 caracteres',
        'Fotografía del estado actual': 'máximo 2 MB',
    });
    await expect(page.locator('#campo-estado-reportado')).toHaveAttribute('maxlength', '100');
    await expect(page.locator('#campo-ubicacion')).toHaveAttribute('maxlength', '150');
    await expect(page.locator('#campo-descripcion')).toHaveAttribute('maxlength', '500');

    await page.goto('verificaciones');
    await revisarAyudas(page, { 'Verificación física': 'Solo puede haber una en progreso a la vez' });

    // La nueva jornada solo se puede crear si no hay otra en progreso; si la prueba la crea, la borra al final.
    const activa = bd(`SELECT id FROM jornadas_verificacion WHERE institucion_id = ${d.instituciones.A} AND estado = 'en_progreso' LIMIT 1`);
    let creada = null;
    if (activa === '') {
        await page.goto('verificaciones/crear');
        await revisarAyudas(page, { 'Nombre de la jornada': 'Máximo 150 caracteres', 'Fecha de inicio': 'por defecto, hoy' });
        await expect(page.locator('#campo-nombre')).toHaveAttribute('maxlength', '150');
        creada = Number(bd(`INSERT INTO jornadas_verificacion (institucion_id, nombre, fecha_inicio, creada_por)
            VALUES (${d.instituciones.A}, 'PW-TEST ayudas', CURDATE(), ${d.usuarios.rector.id}); SELECT LAST_INSERT_ID();`));
    }
    try {
        await page.goto(`verificaciones/${creada ?? Number(activa)}`);
        await revisarAyudas(page, { 'Observaciones de cierre': 'Máximo 500 caracteres' });

        await page.goto(`qr/${d.qr.A}`);
        await page.getByRole('button', { name: 'Reportar discrepancia' }).click();
        await revisarAyudas(page, {
            '¿Qué pasa con este bien?': 'Está en otro salón',
            'Detalle de la discrepancia': 'salvo con el motivo «Otro»',
        });
        await expect(page.getByLabel('Detalle', { exact: true })).toHaveAttribute('maxlength', '500');

        await page.goto('hallazgos/crear');
        await revisarAyudas(page, {
            'Reportar bien no registrado': 'no tiene placa ni QR',
            'Espacio donde lo encontraste': 'Un docente solo ve',
            'Descripción del hallazgo': 'Máximo 300 caracteres',
            'Foto del hallazgo': 'máximo 2 MB',
        });
    } finally {
        if (creada !== null) {
            bd(`DELETE FROM jornadas_verificacion WHERE id = ${creada}`);
        }
    }
});

test('usuarios y espacios explican cada campo como el formulario de bienes', async ({ page }) => {
    test.skip(datos().remoto === true, 'Necesita la base de pruebas local');
    const d = datos();

    await page.goto('usuarios');
    await revisarAyudas(page, { Usuarios: 'quita el acceso sin borrar nada' });

    await page.goto('usuarios/crear');
    await revisarAyudas(page, {
        Documento: 'sin puntos ni espacios',
        Correo: 'el enlace para crear una nueva contraseña',
        Nombres: 'Máximo 100 caracteres',
        Apellidos: 'Máximo 100 caracteres',
        Cargo: 'Es informativo',
        Rol: 'Superusuario',
        Contraseña: 'Mínimo 10 caracteres, con letras y números',
        Fotografía: 'máximo 2 MB',
    });
    await expect(page.locator('#campo-documento')).toHaveAttribute('maxlength', '20');
    await expect(page.locator('#campo-email')).toHaveAttribute('maxlength', '150');
    await expect(page.getByText('No use su nombre, documento ni correo')).toHaveCount(0);

    // Al editar otro usuario: la contraseña es opcional y el restablecimiento de la verificación en dos pasos explica qué contraseña pide.
    await page.goto(`usuarios/${d.usuarios.docente.id}/editar`);
    await revisarAyudas(page, { Contraseña: 'déjala vacía para conservar la que tiene' });
    // Solo aparece si el usuario tiene activa la verificación en dos pasos: se activa un momento
    // en la base (si no la tenía) y se deja como estaba.
    const antes = bd(`SELECT CONCAT(IFNULL(totp_secreto, ''), '|', IFNULL(totp_activado_en, '')) FROM usuarios WHERE id = ${d.usuarios.dosfa.id}`);
    if (antes === '|') {
        bd(`UPDATE usuarios SET totp_secreto = 'PW-TEST', totp_activado_en = NOW() WHERE id = ${d.usuarios.dosfa.id}`);
    }
    try {
        await page.goto(`usuarios/${d.usuarios.dosfa.id}/editar`);
        await revisarAyudas(page, { 'Tu contraseña, para confirmar': 'no la del usuario' });
    } finally {
        if (antes === '|') {
            bd(`UPDATE usuarios SET totp_secreto = NULL, totp_activado_en = NULL WHERE id = ${d.usuarios.dosfa.id}`);
        }
    }

    await page.goto('espacios');
    await revisarAyudas(page, { Espacios: 'responsabilidad grupal' });

    await page.goto('espacios/crear');
    await revisarAyudas(page, {
        'Número de espacio': 'No se puede repetir en la institución',
        'Nombre del espacio': 'Máximo 100 caracteres',
        'Responsable(s) del espacio': 'Es obligatorio al menos uno',
    });
    await expect(page.locator('#campo-codigo')).toHaveAttribute('maxlength', '30');
    await expect(page.locator('#campo-nombre')).toHaveAttribute('maxlength', '100');
    await expect(page.getByText('Mantén Ctrl')).toHaveCount(0);
});

test('un usuario o un espacio con un texto más largo de lo que cabe se rechaza con su motivo', async ({ page }) => {
    test.skip(datos().remoto === true, 'Necesita la base de pruebas local');
    const d = datos();

    await page.goto('usuarios/crear');
    let token = await page.locator('input[name="_csrf"]').first().inputValue();
    const cargo = await page.locator('#campo-cargo-id option').first().getAttribute('value');
    const rol = await page.locator('#campo-rol-id option').first().getAttribute('value');
    let respuesta = await page.request.post('usuarios', {
        form: { _csrf: token, documento: '9'.repeat(21), email: `largo-${Date.now()}@example.com`, nombres: 'Prueba', apellidos: 'Largo',
            cargo_id: cargo, rol_id: rol, password: 'Clave-Segura-2026x' },
        maxRedirects: 0,
    });
    expect(respuesta.status()).toBe(302);
    await page.goto('usuarios/crear');
    await expect(page.locator('.alert-danger')).toContainText('El documento es más largo de lo que se puede guardar');
    await expect(page.locator('#campo-documento')).toHaveClass(/is-invalid/);

    await page.goto('espacios/crear');
    token = await page.locator('input[name="_csrf"]').first().inputValue();
    respuesta = await page.request.post('espacios', {
        form: { _csrf: token, codigo: 'X'.repeat(31), nombre: 'Espacio largo', 'responsables[]': String(d.usuarios.docente.id) },
        maxRedirects: 0,
    });
    expect(respuesta.status()).toBe(302);
    await page.goto('espacios/crear');
    await expect(page.locator('.alert-danger')).toContainText('El número del espacio es más largo de lo que se puede guardar');
});

test('categorías, instituciones y reportes explican cada campo como el formulario de bienes', async ({ page }) => {
    test.skip(datos().remoto === true, 'Necesita la base de pruebas local');
    const d = datos();

    await page.goto('categorias');
    await revisarAyudas(page, {
        'Categorías de bienes': 'MIA arma un lote por categoría',
        'Nombre de la categoría': 'Máximo 80 caracteres',
    });
    await expect(page.locator('#nombreCategoria')).toHaveAttribute('maxlength', '80');

    // El rector edita la suya (la lista de instituciones es del superusuario): el código DANE, el tipo de sede y la principal se ven, pero solo los cambia el superusuario.
    await page.goto(`instituciones/${d.instituciones.A}/editar`);
    await revisarAyudas(page, {
        'Código DANE': 'normalmente 12 dígitos',
        'Tipo de sede': 'Una institución que tiene secciones no puede volverse sección',
        Nombre: 'Máximo 150 caracteres',
        Dirección: 'Máximo 200 caracteres',
        'Correo institucional': 'rectoria@colegio.edu.co',
        'Logo institucional': 'máximo 2 MB',
    });
    await expect(page.locator('#campo-direccion')).toHaveAttribute('maxlength', '200');

    await page.goto('reportes');
    await revisarAyudas(page, {
        Reportes: 'Solo para el rector',
        'Cartera de bienes': 'Cartera por placa',
        'Bienes asignados pendientes de devolver': 'fecha de asignación',
        'Historial de bienes reintegrados': 'quién lo registró',
        'Acta de bienes a cargo': 'Grupal:',
        'Funcionario del acta': 'Escribe para buscarla',
        'Actividad de los funcionarios': 'hora de Colombia',
        Período: 'Rango de fechas',
        'Funcionario de la actividad': 'Todos los funcionarios',
        'Control del inventario': '30 días o más',
        'Descargar toda la información': 'no contraseñas',
    });
    // El (?) va fuera del título: los títulos de cada reporte no cambian de nombre.
    await expect(page.getByRole('heading', { name: 'Cartera de bienes', exact: true })).toBeVisible();
    await expect(page.getByRole('heading', { name: 'Acta de bienes a cargo', exact: true })).toBeVisible();
});

test('cargos y nueva institución (superusuario) explican cada campo', async ({ browser }) => {
    test.skip(datos().remoto === true, 'Necesita la base de pruebas local');
    const contexto = await comoRol(browser, 'superusuario');
    const page = await contexto.newPage();

    await page.goto('cargos');
    await revisarAyudas(page, {
        Cargos: 'solo el superusuario la administra',
        'Nombre del cargo': 'Máximo 80 caracteres',
    });
    await expect(page.locator('#nombreCargo')).toHaveAttribute('maxlength', '80');

    await page.goto('instituciones');
    await revisarAyudas(page, { Instituciones: 'Solo el superusuario crea instituciones' });

    await page.goto('instituciones/crear');
    await page.locator('#tipoSede').selectOption('seccion');
    await revisarAyudas(page, { 'Institución principal': 'principal activa' });

    // Un nombre de cargo más largo de lo que cabe se rechaza con su motivo.
    const token = await page.locator('input[name="_csrf"]').first().inputValue();
    await page.goto('cargos');
    const respuesta = await page.request.post('cargos', { form: { _csrf: token, nombre: 'C'.repeat(81) }, maxRedirects: 0 });
    expect(respuesta.status()).toBe(302);
    await page.goto('cargos');
    await expect(page.locator('.alert-danger')).toContainText('El nombre es más largo de lo que se puede guardar');
    await contexto.close();
});

test('asignaciones y reintegros explican cada campo como el formulario de bienes', async ({ page }) => {
    test.skip(datos().remoto === true, 'Necesita la base de pruebas local');
    const d = datos();
    const inst = d.instituciones.A;
    const s = `PWAY${Date.now()}`;
    const categoria = (nombre) => `(SELECT id FROM categorias_bienes WHERE institucion_id = ${inst} AND nombre LIKE '${nombre}%' ORDER BY id LIMIT 1)`;
    const bien = (codigo, cat, lote = 'NULL', estado = 'reintegrado') => `INSERT INTO bienes (institucion_id, codigo_identificacion, lote, descripcion, categoria_id, fecha_ingreso, valor, estado, qr_token, created_by)
        VALUES (${inst}, '${codigo}', ${lote}, 'PW-TEST ayudas', ${categoria(cat)}, '2024-01-15', 1000, '${estado}', UUID(), ${d.usuarios.superusuario.id});`;
    const movimiento = (loteReintegro) => `INSERT INTO movimientos (bien_id, tipo, fecha, responsable_id, destino_texto, lote_reintegro_id)
        VALUES (LAST_INSERT_ID(), 'reintegro', '2024-02-20', ${d.usuarios.rector.id}, 'Bodega de reintegro', ${loteReintegro});`;
    const lote = (estado) => `INSERT INTO lotes_reintegro (institucion_id, fecha, destino_texto, registrado_por, estado, reintegrado_fecha, reintegrado_por)
        VALUES (${inst}, '2024-02-20', 'PW-TEST ayudas', ${d.usuarios.rector.id}, '${estado}', ${estado === 'reintegrado' ? "'2024-03-01'" : 'NULL'}, ${estado === 'reintegrado' ? d.usuarios.rector.id : 'NULL'});`;

    // Datos de la prueba: un reintegro sin lote, dos lotes pendientes de la misma categoría, uno
    // entregado y una placa con unidades. Se borran al final para no cambiar las demás pruebas.
    bd(bien(`${s}S`, 'Muebles') + movimiento('NULL'));
    const pendienteA = Number(bd(lote('pendiente') + ' SET @l = LAST_INSERT_ID(); ' + bien(`${s}A`, 'Muebles') + movimiento('@l') + ' SELECT @l;'));
    const pendienteB = Number(bd(lote('pendiente') + ' SET @l = LAST_INSERT_ID(); ' + bien(`${s}B`, 'Muebles') + movimiento('@l') + ' SELECT @l;'));
    const entregado = Number(bd(lote('reintegrado') + ' SET @l = LAST_INSERT_ID(); ' + bien(`${s}E`, 'Muebles') + movimiento('@l') + ' SELECT @l;'));
    bd(bien(`${s}P-001`, 'Muebles', `'${s}P'`, 'activo') + bien(`${s}P-002`, 'Muebles', `'${s}P'`, 'activo'));
    try {
        await page.goto('asignaciones');
        await revisarAyudas(page, {
            'Asignar bienes': 'queda un traslado en su historial',
            'Fecha de asignación': 'No puede ser futura ni anterior al año 2000',
            'Observaciones de la asignación': 'Máximo 500 caracteres',
            'Filtro por espacio': 'están hoy en ese espacio',
            'Filtro por responsable': 'Individual',
            'Filtro por responsabilidad': 'Sin asignar',
        });
        await expect(page.locator('#campo-observaciones')).toHaveAttribute('maxlength', '500');

        await page.goto('reintegros');
        await revisarAyudas(page, {
            'Reintegrar bienes': 'Lotes de reintegro',
            Destino: 'Máximo 200 caracteres',
            'Fecha del reintegro': 'el lote y su comprobante',
            'Observaciones del reintegro': 'Máximo 500 caracteres',
        });
        await expect(page.locator('#campo-destino-texto')).toHaveAttribute('maxlength', '200');

        await page.goto('reintegros/unidades');
        await revisarAyudas(page, { 'Reintegrar por cantidad': 'Alta masiva' });
        await page.goto(`reintegros/unidades/${s}P`);
        await revisarAyudas(page, {
            'Cantidad a reintegrar': 'últimos consecutivos',
            'Generar el lote ahora': 'FO-ADMI-009',
        });

        await page.goto('reintegros/lotes/generar');
        await revisarAyudas(page, {
            'Generar lote de reintegro': 'un lote para cada categoría',
            'Descripción del lote': 'Máximo 200 caracteres',
            'Observaciones del lote': 'Máximo 500 caracteres',
        });

        await page.goto('reintegros/lotes');
        await revisarAyudas(page, {
            'Lotes de reintegro': 'Verificar un lote',
            'Filtro por categoría': 'Sin categoría',
            'Filtro por fecha del lote': 'Lote hasta',
        });

        await page.goto(`reintegros/lotes/${pendienteA}`);
        await revisarAyudas(page, {
            'Fecha de entrega': 'Verificar un lote',
            'Nota de la entrega': 'Máximo 500 caracteres',
        });
        await page.goto(`reintegros/lotes/${entregado}`);
        await page.getByText('¿Se marcó por error? Reabrir el lote').click();
        await revisarAyudas(page, { 'Motivo para reabrir': 'queda en la auditoría' });

        await page.goto(`reintegros/lotes/unificar?lotes[]=${pendienteA}&lotes[]=${pendienteB}`);
        await revisarAyudas(page, {
            'Unificar o separar lotes': 'uno por categoría',
            'Descripción de los lotes nuevos': 'en gris',
        });

        await page.goto('reintegros/solicitudes');
        await revisarAyudas(page, {
            'Solicitudes de reintegro': 'Rechazar',
            'Estado de la solicitud': 'Cancelada',
        });
        await page.goto(`qr/${d.qr.para_solicitud}/solicitar-reintegro`);
        if (await ayudaDe(page, 'Solicitar reintegro').count() > 0) {
            await revisarAyudas(page, {
                'Solicitar reintegro': 'llega al rector o al secretario',
                '¿Por qué debe reintegrarse este bien?': 'Entre 10 y 500 caracteres',
            });
        }
    } finally {
        bd(`DELETE m FROM movimientos m JOIN bienes b ON b.id = m.bien_id WHERE b.codigo_identificacion LIKE '${s}%';
            DELETE FROM bienes WHERE codigo_identificacion LIKE '${s}%';
            DELETE FROM lotes_reintegro WHERE id IN (${pendienteA}, ${pendienteB}, ${entregado});`);
    }
});

test('las cargas masivas explican el archivo, el resultado y las cargas anteriores', async ({ page }) => {
    test.skip(datos().remoto === true, 'Necesita la base de pruebas local');

    for (const [ruta, tituloCarga] of [
        ['cargas-masivas', 'Carga masiva de bienes'],
        ['espacios/carga-masiva', 'Carga masiva de espacios'],
        ['usuarios/carga-masiva', 'Carga masiva de usuarios'],
    ]) {
        await page.goto(ruta);
        await revisarAyudas(page, {
            [tituloCarga]: 'Descargar plantilla',
            'Archivo de la carga': 'sin guardar nada todavía',
        });
        // El campo del archivo tiene su etiqueta visible.
        await expect(page.getByLabel('Archivo Excel (.xlsx)', { exact: true })).toHaveAttribute('type', 'file');
        // La explicación de la plantilla pasó a la ayuda: no queda como texto suelto.
        await expect(page.getByText('Sube la plantilla diligenciada')).toHaveCount(0);
    }

    // En la de bienes: el buscador de cargas y el enlace a la carga de fotos, con su (?).
    await page.goto('cargas-masivas');
    await revisarAyudas(page, {
        'Buscar cargas': 'por quién la subió',
        'Carga masiva de fotos': 'se llama igual que el código del bien',
    });
    await expect(page.getByLabel('Buscar cargas', { exact: true })).toHaveAttribute('type', 'search');
    await expect(page.getByText('¿Ya subiste los bienes')).toHaveCount(0);

    // Una carga analizada (sin confirmar) explica cada estado de fila; después se borra.
    await page.goto('cargas-masivas');
    await page.setInputFiles('input[name="archivo"]', 'tests/fixtures/carga_masiva_bienes.xlsx');
    await page.getByRole('button', { name: 'Analizar archivo' }).click();
    await expect(page).toHaveURL(/\/cargas-masivas\/\d+/);
    const carga = Number(page.url().match(/cargas-masivas\/(\d+)/)[1]);
    try {
        await revisarAyudas(page, {
            'Resultado de la carga': 'Nada se borra',
            'Mostrar filas': 'aplica el archivo completo',
        });
        await page.goto('cargas-masivas');
        await revisarAyudas(page, { 'Cargas anteriores': 'Una carga se aplica una sola vez' });
    } finally {
        bd(`DELETE FROM cargas_masivas WHERE id = ${carga} AND aplicada = 0`);
    }

    await page.goto('bienes/carga-masiva-fotos');
    await revisarAyudas(page, {
        'Carga masiva de fotos': 'No reemplaza la foto de un bien que ya tiene una',
        'Archivo .zip con las fotos': 'hasta 3.000 archivos',
    });
    await expect(page.getByText('Sube un archivo')).toHaveCount(0);

    // El resultado de una carga de fotos explica cada caso (los textos sueltos pasaron a la ayuda).
    await page.setInputFiles('input[name="archivo"]', 'tests/fixtures/fotos_bienes.zip');
    await page.getByRole('button', { name: 'Subir .zip' }).click();
    await revisarAyudas(page, { 'Resultado de la carga de fotos': 'no coincide con el código de ningún bien' });
    await expect(page.getByText('Solo se aceptan imágenes JPG o PNG.')).toHaveCount(0);
});

test('panel principal, escáner, ficha del QR, mis notas y mi cuenta explican cada parte', async ({ page }) => {
    test.skip(datos().remoto === true, 'Necesita la base de pruebas local');
    const d = datos();

    await page.goto('dashboard');
    await revisarAyudas(page, {
        'Panel principal': 'depende de tu rol y de la sede activa',
        'Cifras del inventario': 'los activos y en reparación',
        'Accesos rápidos': 'las mismas del menú',
    });
    if (await ayudaDe(page, 'Requiere atención').count() > 0) {
        await revisarAyudas(page, { 'Requiere atención': 'bajas por aprobar' });
    }

    await page.goto('escanear');
    await revisarAyudas(page, {
        'Escanear código QR': 'permiso para usar la cámara',
        'Escribir el código': 'tal como está en la etiqueta',
    });

    // La verificación en curso solo aparece con una jornada abierta: se abre una un momento y se borra.
    const activa = bd(`SELECT id FROM jornadas_verificacion WHERE institucion_id = ${d.instituciones.A} AND estado = 'en_progreso' LIMIT 1`);
    const creada = activa === '' ? Number(bd(`INSERT INTO jornadas_verificacion (institucion_id, nombre, fecha_inicio, creada_por)
        VALUES (${d.instituciones.A}, 'PW-TEST ayudas QR', CURDATE(), ${d.usuarios.rector.id}); SELECT LAST_INSERT_ID();`)) : null;
    try {
        await page.goto(`qr/${d.qr.A}`);
        await revisarAyudas(page, {
            'Ficha del bien': 'Lo que abre el QR pegado en el bien',
            'Etiqueta QR': 'Etiqueta impresa, sin confirmar',
            'Verificación física en curso': 'reemplaza el registro anterior',
        });
    } finally {
        if (creada !== null) {
            bd(`DELETE FROM jornadas_verificacion WHERE id = ${creada}`);
        }
    }

    await page.goto('mi-cuenta');
    await revisarAyudas(page, {
        'Mi cuenta': 'pídeselo al administrador',
        'Verificación en dos pasos': 'Códigos de recuperación',
        'Contraseña actual': 'confirmar que eres tú',
        'Contraseña nueva': 'Mínimo 10 caracteres, con letras y números',
        'Confirmar contraseña nueva': 'error de digitación',
        'Tus datos y privacidad': 'política de tratamiento de datos',
    });
    await expect(page.getByText('No uses tu nombre, documento ni correo')).toHaveCount(0);

    // Mis notas: el (?) del panel.
    await page.goto('dashboard');
    await page.getByRole('button', { name: /^Mis notas/ }).click();
    const boton = ayudaDe(page, 'Mis notas');
    await boton.click();
    await expect(await globoDe(page, boton)).toContainText('Son privadas: solo tú las ves');
});

test('bienes (tabla, registrar, editar, alta masiva) y detalle de la jornada explican cada parte', async ({ page }) => {
    test.skip(datos().remoto === true, 'Necesita la base de pruebas local');
    const d = datos();

    await page.goto('bienes');
    await page.locator('#botonFiltrosBienes').click();
    await revisarAyudas(page, {
        'Buscar bienes': 'No distingue tildes ni mayúsculas',
        'Filtro por categoría': 'Sin cartera',
        'Filtro por estado': 'Dado de baja',
        'Filtro por espacio': 'están hoy en ese espacio',
        'Filtro por responsabilidad': 'Sin asignar',
    });

    await page.goto('bienes/crear');
    await revisarAyudas(page, { 'Registrar o editar un bien': 'Los datos y la acción se guardan juntos' });
    await expect(page.locator('#codigoIdentificacion')).toHaveAttribute('maxlength', '40');
    await expect(page.locator('#campo-descripcion')).toHaveAttribute('maxlength', '200');
    await expect(page.locator('#campo-marca')).toHaveAttribute('maxlength', '100');

    // Al editar, los campos de la acción elegida tienen su ayuda.
    await page.goto(`bienes/${d.bienes.A.silla}/editar`);
    await page.locator('#accionBien').selectOption('reintegrar');
    await revisarAyudas(page, {
        Destino: 'Máximo 200 caracteres',
        'Fecha de la acción': 'por defecto, hoy',
        'Observación de la acción': 'Máximo 500 caracteres',
    });
    await expect(page.locator('#accionDestino')).toHaveAttribute('maxlength', '200');

    await page.goto('bienes/alta-masiva');
    await revisarAyudas(page, {
        'Alta masiva de bienes idénticos': 'reintegrar por cantidad',
        'Código de lote': 'máximo 36 caracteres',
    });
    await expect(page.locator('#campo-lote')).toHaveAttribute('maxlength', '36');

    // Un código de lote más largo de lo que cabe se rechaza con su motivo (antes fallaba al guardar).
    const token = await page.locator('input[name="_csrf"]').first().inputValue();
    const respuesta = await page.request.post('bienes/alta-masiva', {
        form: { _csrf: token, lote: 'L'.repeat(37), cantidad: '2', descripcion: 'SILLA DE PRUEBA', fecha_ingreso: '2024-01-15', valor: '0' },
        maxRedirects: 0,
    });
    expect(respuesta.status()).toBe(302);
    await page.goto('bienes/alta-masiva');
    await expect(page.locator('.alert-danger')).toContainText('El código de lote es más largo de lo que se puede guardar');

    // Detalle de una jornada (se abre una si no hay, y se borra al final).
    const activa = bd(`SELECT id FROM jornadas_verificacion WHERE institucion_id = ${d.instituciones.A} AND estado = 'en_progreso' LIMIT 1`);
    const creada = activa === '' ? Number(bd(`INSERT INTO jornadas_verificacion (institucion_id, nombre, fecha_inicio, creada_por)
        VALUES (${d.instituciones.A}, 'PW-TEST ayudas jornada', CURDATE(), ${d.usuarios.rector.id}); SELECT LAST_INSERT_ID();`)) : null;
    try {
        await page.goto(`verificaciones/${creada ?? Number(activa)}`);
        await revisarAyudas(page, {
            'Detalle de la jornada': 'Exportar a Excel',
            Discrepancias: 'la discrepancia queda revisada sola',
        });
        await page.locator('[data-tab-link="pendientes"]').click();
        await revisarAyudas(page, { Pendientes: 'nadie ha escaneado' });
        await page.locator('[data-tab-link="ok"]').click();
        await revisarAyudas(page, { Verificados: 'el bien está aquí' });
    } finally {
        if (creada !== null) {
            bd(`DELETE FROM jornadas_verificacion WHERE id = ${creada}`);
        }
    }
});

test('auditoría y papelera (superusuario) explican cada parte', async ({ browser }) => {
    test.skip(datos().remoto === true, 'Necesita la base de pruebas local');
    const contexto = await comoRol(browser, 'superusuario');
    const page = await contexto.newPage();

    await page.goto('auditoria');
    await revisarAyudas(page, {
        Auditoría: 'Nadie puede borrar ni cambiar un registro de auditoría',
        'Filtro por institución': 'esa institución',
        'Filtro por tipo': 'un lote de reintegro',
        'Filtro por acción': 'iniciar sesión',
        'Filtro por fechas': 'ambos incluidos',
    });

    await page.goto('papelera');
    await revisarAyudas(page, { 'Papelera de reciclaje': 'más de 90 días' });
    if (await ayudaDe(page, 'Buscar en la papelera').count() > 0) {
        await revisarAyudas(page, { 'Buscar en la papelera': 'mientras escribes' });
    }
    await contexto.close();
});

test('la sede activa (rector) y el filtro de institución (superusuario) explican qué hacen', async ({ page, browser }) => {
    test.skip(datos().remoto === true, 'Necesita la base de pruebas local');

    // El rector de prueba tiene una sede principal y una sección: ve «Sede activa» en la barra.
    await page.setViewportSize({ width: 1280, height: 800 });
    await page.goto('dashboard');
    await revisarAyudas(page, { 'Sede activa': 'los bienes, espacios, listas y reportes que ves son los de esa sede' });

    const contexto = await comoRol(browser, 'superusuario');
    const superusuario = await contexto.newPage();
    await superusuario.setViewportSize({ width: 1280, height: 800 });
    await superusuario.goto('dashboard');
    await revisarAyudas(superusuario, { 'Ver institución': 'Ver todas las instituciones' });

    // En el celular están en el menú lateral, con su etiqueta y su (?).
    await superusuario.setViewportSize({ width: 390, height: 844 });
    await superusuario.goto('dashboard');
    await superusuario.locator('#btnMenu').click();
    await expect(ayudaDe(superusuario, 'Ver institución')).toBeVisible();
    await contexto.close();
});

test('el ingreso, la recuperación de contraseña y de correo y el código de dos pasos explican cada campo', async ({ browser }) => {
    test.skip(datos().remoto === true, 'Necesita la base de pruebas local');
    const contexto = await sinSesion(browser);
    const pagina = await contexto.newPage();
    // Estas páginas no tienen <h1> (salvo la del código): el cursor sale hacia el logo.
    const logo = 'img[alt="MIA"]';

    await pagina.goto('login');
    await revisarAyudas(pagina, {
        'Entrar a MIA': 'La cuenta la crea el rector',
        'Correo institucional': 'Máximo 150 caracteres',
        Contraseña: 'Distingue mayúsculas de minúsculas',
        'Recordarme en este dispositivo': 'hasta 30 días',
    }, logo);
    await expect(pagina.locator('#email')).toHaveAttribute('maxlength', '150');

    await pagina.goto('olvide-contrasena');
    await revisarAyudas(pagina, {
        'Recuperar contraseña': 'El enlace dura 60 minutos y sirve una sola vez',
        'Correo institucional': 'Máximo 150 caracteres',
    }, logo);
    await expect(pagina.getByText('Escribe el correo con el que inicias sesión')).toHaveCount(0);

    await pagina.goto('olvide-correo');
    await revisarAyudas(pagina, {
        '¿Cuál es mi correo?': 'con parte de las letras ocultas',
        Institución: 'Escribe parte del nombre',
        'Número de documento': 'sin puntos ni espacios',
    }, logo);
    await expect(pagina.locator('#campo-documento')).toHaveAttribute('maxlength', '20');

    // Cuenta desechable con la verificación en dos pasos activada y un enlace de restablecimiento vigente.
    const docente = datos().usuarios.docente;
    const marca = Date.now();
    const correo = `ayuda-ingreso-${marca}@prueba.test`;
    bd(`INSERT INTO usuarios (documento, nombres, apellidos, cargo_id, email, password_hash, institucion_id, rol_id, activo, totp_secreto, totp_activado_en)
        SELECT 'AYI-${marca}', 'Ana', 'Ayuda', cargo_id, '${correo}', password_hash, institucion_id, rol_id, 1, 'PW-TEST', NOW()
        FROM usuarios WHERE id = ${docente.id}`);
    const id = Number(bd(`SELECT id FROM usuarios WHERE email = '${correo}'`));
    try {
        const token = randomBytes(32).toString('hex');
        bd(`INSERT INTO password_resets (usuario_id, token_hash, expira_en) VALUES (${id}, SHA2('${token}', 256), '2099-01-01 00:00:00')`);
        await pagina.goto(`restablecer-contrasena/${token}`);
        await revisarAyudas(pagina, {
            'Restablecer contraseña': 'se cierran las sesiones que tengas abiertas en otros equipos',
            'Nueva contraseña': 'Mínimo 10 caracteres, con letras y números',
            'Confirmar contraseña nueva': 'para evitar un error de digitación',
        }, logo);
        await expect(pagina.getByText('No use su nombre, documento ni correo')).toHaveCount(0);

        await pagina.goto('login');
        await pagina.fill('#email', correo);
        await pagina.fill('#password', docente.clave);
        await pagina.getByRole('button', { name: 'Ingresar' }).click();
        await expect(pagina).toHaveURL(/\/2fa\/verificar$/);
        await revisarAyudas(pagina, {
            'Verificación en dos pasos': 'El código cambia cada 30 segundos',
            'Código de verificación': 'códigos de recuperación (formato ABCDE-FGHJK)',
            'No volver a pedirlo': 'durante 30 días',
        });
        await expect(pagina.getByText('¿Perdiste el teléfono? Escribe un código')).toHaveCount(0);
    } finally {
        await contexto.close();
        bd(`DELETE FROM password_resets WHERE usuario_id = ${id}; DELETE FROM auditoria WHERE usuario_id = ${id}; DELETE FROM usuarios WHERE id = ${id}`);
    }
});

test('la política de datos y su aceptación explican qué es y qué se autoriza', async ({ browser }) => {
    test.skip(datos().remoto === true, 'Necesita la base de pruebas local');
    const contexto = await sinSesion(browser);
    const pagina = await contexto.newPage();

    await pagina.goto('politica-de-datos');
    await revisarAyudas(pagina, { 'Cómo leer esta política': 'la responsable es tu institución' });

    // Cuenta desechable que aún no ha aceptado la política: al entrar se le pide.
    const docente = datos().usuarios.docente;
    const marca = Date.now();
    const correo = `ayuda-politica-${marca}@prueba.test`;
    bd(`INSERT INTO usuarios (documento, nombres, apellidos, cargo_id, email, password_hash, institucion_id, rol_id, activo)
        SELECT 'AYP-${marca}', 'Pablo', 'Prueba', cargo_id, '${correo}', password_hash, institucion_id, rol_id, 1
        FROM usuarios WHERE id = ${docente.id}`);
    const id = Number(bd(`SELECT id FROM usuarios WHERE email = '${correo}'`));
    try {
        await pagina.goto('login');
        await pagina.fill('#email', correo);
        await pagina.fill('#password', docente.clave);
        await pagina.getByRole('button', { name: 'Ingresar' }).click();
        await expect(pagina).toHaveURL(/\/politica\/aceptar$/);
        await revisarAyudas(pagina, {
            'Por qué se pide': 'Salir sin aceptar',
            'Qué autorizas': 'Queda guardada la fecha y la versión que aceptaste',
        });
        await expect(pagina.getByText('Solo se pide una vez')).toHaveCount(0);
    } finally {
        await contexto.close();
        bd(`DELETE FROM auditoria WHERE usuario_id = ${id}; DELETE FROM usuarios WHERE id = ${id}`);
    }
});

test.describe('en el celular', () => {
    test.use({ viewport: { width: 390, height: 844 }, hasTouch: true, isMobile: true });

    test('un toque la abre, tocar fuera la cierra y el globo cabe en la pantalla', async ({ page }) => {
        await page.goto('bienes/crear');
        const boton = ayudaDe(page, 'Valor');
        const globo = await globoDe(page, boton);

        await boton.scrollIntoViewIfNeeded();
        await boton.tap();
        await expect(globo).toBeVisible();
        await expect(globo).toContainText('Escribe 0 si no lo conoces');
        const caja = await globo.boundingBox();
        expect(caja.x).toBeGreaterThanOrEqual(0);
        expect(caja.x + caja.width).toBeLessThanOrEqual(390);

        await page.locator('h1').tap();
        await expect(globo).toBeHidden();

        // Otro toque en el mismo ícono también la cierra.
        await boton.tap();
        await expect(globo).toBeVisible();
        await boton.tap();
        await expect(globo).toBeHidden();
    });
});
