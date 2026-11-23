import { test, expect } from '@playwright/test';
import { datos, bd, comoRol } from './helpers/datos.js';

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
async function revisarAyudas(page, ayudas) {
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
