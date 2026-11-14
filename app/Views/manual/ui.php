<?php
/**
 * Guía visual interna (/manual/ui, solo superusuario): los tokens, el diccionario de íconos y
 * cada componente de partials/ui en uso. Es la referencia viva de docs/guia-estilo-ui.md:
 * si un componente cambia, aquí se ve el resultado en tema claro y oscuro.
 *
 * Variables: $iconos (Icono::mapa()).
 */

use App\Core\Url;
use App\Core\View;

$e = static fn (mixed $texto): string => htmlspecialchars((string) $texto, ENT_QUOTES);
$pesos = static fn (float $valor): string => '$' . number_format($valor, 0, ',', '.');
$colores = [
    ['sigebi-primary', 'Texto y enlaces de la marca'],
    ['sigebi-primary-relleno', 'Fondo del botón primario (texto blanco)'],
    ['sigebi-accent', 'Acento: solo de fondo, con texto oscuro'],
    ['sigebi-success', 'Éxito'],
    ['sigebi-danger', 'Peligro y error'],
    ['sigebi-warning', 'Texto de advertencia'],
    ['sigebi-info', 'Información'],
    ['sigebi-bg', 'Fondo de la página'],
    ['sigebi-surface', 'Superficie (tarjetas, tablas)'],
    ['sigebi-border', 'Bordes de 1 px'],
    ['sigebi-text', 'Texto'],
    ['sigebi-text-muted', 'Texto secundario'],
];
$neutros = [50, 100, 200, 300, 400, 500, 600, 700, 800, 900];
$filasEjemplo = [
    ['placa' => '200033042', 'descripcion' => 'SILLA UNIVERSITARIA EN MADERA', 'estado' => 'activo', 'cantidad' => 178, 'valor' => 11356400],
    ['placa' => '200045110', 'descripcion' => 'PROYECTOR EPSON X200', 'estado' => 'en_reparacion', 'cantidad' => 1, 'valor' => 2450000],
    ['placa' => '1000000017', 'descripcion' => 'mesa de trabajo', 'estado' => 'reintegrado', 'cantidad' => 1, 'valor' => 0],
];
?>
<?= ui('encabezado_pagina', [
    'titulo' => 'Guía visual',
    'icono' => 'ayuda',
    'subtitulo' => 'Tokens, íconos y componentes de MIA. La documentación está en docs/guia-estilo-ui.md.',
    'acciones' => ui('boton', ['texto' => 'Volver', 'icono' => 'volver', 'url' => Url::to('/manual')]),
]) ?>

<div class="contenedor-lg">
    <?= ui('tarjeta', ['titulo' => 'Colores', 'id' => 'colores']) ?>
        <div class="row g-3">
            <?php foreach ($colores as [$token, $uso]): ?>
                <div class="col-sm-6 col-lg-4"><?= ui('muestra_color', ['token' => $token, 'uso' => $uso]) ?></div>
            <?php endforeach; ?>
        </div>
        <h3 class="titulo-seccion mt-4">Neutros</h3>
        <div class="row g-3">
            <?php foreach ($neutros as $n): ?>
                <div class="col-6 col-sm-4 col-lg-2"><?= ui('muestra_color', ['token' => 'sigebi-gris-' . $n, 'uso' => 'Gris ' . $n]) ?></div>
            <?php endforeach; ?>
        </div>
    <?= ui('tarjeta_fin') ?>

    <?= ui('tarjeta', ['titulo' => 'Tipografía', 'id' => 'tipografia']) ?>
        <p class="titulo-pagina mb-2">Título de página (h1)</p>
        <p class="titulo-seccion">Título de sección o tarjeta (h2)</p>
        <p class="mb-2">Texto normal: el bien queda asignado al espacio y responden sus responsables.</p>
        <p class="small text-muted mb-2">Texto secundario: notas, fechas y ayudas breves.</p>
        <p class="mb-0">Código y placas: <span class="mono">200033042-001</span> · Valores: <span class="celda-numero"><?= $pesos(11356400) ?></span></p>
    <?= ui('tarjeta_fin') ?>

    <?= ui('tarjeta', ['titulo' => 'Botones', 'id' => 'botones']) ?>
        <p class="small text-muted">Un solo botón primario por pantalla. Las acciones destructivas van en peligro y siempre piden confirmación.</p>
        <div class="d-flex flex-wrap gap-2 mb-3">
            <?= ui('boton', ['texto' => 'Registrar bien', 'tipo' => 'primario', 'icono' => 'crear']) ?>
            <?= ui('boton', ['texto' => 'Exportar Excel', 'icono' => 'exportar_excel']) ?>
            <?= ui('boton', ['texto' => 'Eliminar', 'tipo' => 'peligro', 'icono' => 'eliminar', 'url' => Url::to('/manual/ui#botones'),
                'confirmar' => 'El espacio pasará a la papelera. Podrás restaurarlo desde allí.']) ?>
            <?= ui('boton', ['texto' => 'Ver historial', 'tipo' => 'enlace']) ?>
        </div>
        <div class="d-flex flex-wrap gap-2 align-items-center">
            <?= ui('boton', ['texto' => 'Guardar', 'tipo' => 'primario', 'tamano' => 'sm']) ?>
            <?= ui('boton', ['texto' => 'Cancelar', 'tamano' => 'sm']) ?>
            <?= ui('boton', ['texto' => 'Editar bien', 'icono' => 'editar', 'tamano' => 'sm', 'soloIcono' => true]) ?>
            <?= ui('boton', ['texto' => 'Descargar el código QR', 'icono' => 'descargar', 'tamano' => 'sm', 'soloIcono' => true]) ?>
            <button type="button" class="btn btn-primary btn-sm" disabled><span class="spinner-border spinner-border-sm me-1" aria-hidden="true"></span>Guardando</button>
        </div>
    <?= ui('tarjeta_fin') ?>

    <?= ui('tarjeta', ['titulo' => 'Estados', 'id' => 'estados']) ?>
        <?php foreach (['bien' => ['activo', 'en_reparacion', 'reintegrado', 'dado_de_baja'], 'solicitud' => ['pendiente', 'aprobada', 'rechazada', 'cancelada'],
            'lote' => ['pendiente', 'reintegrado', 'unificado'], 'responsabilidad' => ['grupal', 'individual']] as $tipo => $estados): ?>
            <div class="d-flex flex-wrap align-items-center gap-2 mb-2">
                <span class="small text-muted me-1"><?= $e(ucfirst($tipo)) ?>:</span>
                <?php foreach ($estados as $estado): ?><?= ui('insignia_estado', ['tipo' => $tipo, 'estado' => $estado]) ?><?php endforeach; ?>
            </div>
        <?php endforeach; ?>
    <?= ui('tarjeta_fin') ?>

    <?= ui('tarjeta', ['titulo' => 'Avisos', 'id' => 'avisos']) ?>
        <?= ui('alerta', ['tipo' => 'exito', 'mensaje' => 'Bien registrado. Ya puedes asignarlo a un espacio.', 'cerrable' => false]) ?>
        <?= ui('alerta', ['tipo' => 'info', 'mensaje' => 'Corrigiendo la ubicación a partir de una discrepancia de la verificación física.', 'cerrable' => false]) ?>
        <?= ui('alerta', ['tipo' => 'advertencia', 'mensaje' => 'Este lote lleva 8 meses pendiente de entregar a la Alcaldía.', 'cerrable' => false]) ?>
        <?= ui('alerta', ['tipo' => 'peligro', 'mensaje' => 'La placa ya existe. Verifica el número o busca el bien.', 'cerrable' => false, 'clases' => 'mb-0']) ?>
    <?= ui('tarjeta_fin') ?>

    <?= ui('tarjeta', ['titulo' => 'Formulario', 'id' => 'formulario']) ?>
        <form class="row g-3" action="#" data-sin-cargando>
            <div class="col-md-6">
                <?= ui('campo_formulario', ['id' => 'uiCodigo', 'nombre' => 'codigo', 'etiqueta' => 'Código de identificación', 'ayuda' => 'codigo',
                    'requerido' => true, 'valor' => '200033042', 'error' => 'La placa ya existe. Verifica el número o busca el bien.']) ?>
            </div>
            <div class="col-md-6">
                <?= ui('campo_formulario', ['id' => 'uiEstado', 'nombre' => 'estado', 'etiqueta' => 'Estado', 'ayuda' => 'estado', 'tipo' => 'select',
                    'opciones' => ['activo' => 'Activo', 'en_reparacion' => 'En reparación'], 'vacia' => null, 'valor' => 'activo']) ?>
            </div>
            <div class="col-12">
                <?= ui('campo_formulario', ['id' => 'uiDescripcion', 'nombre' => 'descripcion', 'etiqueta' => 'Descripción', 'ayuda' => 'descripcion',
                    'requerido' => true, 'placeholder' => 'Ej. Silla universitaria en madera']) ?>
            </div>
            <div class="col-12 acciones-formulario">
                <?= ui('boton', ['texto' => 'Cancelar']) ?>
                <?= ui('boton', ['texto' => 'Guardar', 'tipo' => 'primario', 'icono' => 'guardar']) ?>
            </div>
        </form>
    <?= ui('tarjeta_fin') ?>

    <h2 class="titulo-seccion" id="tabla">Tabla de datos</h2>
    <?= ui('tabla', ['leyenda' => 'Ejemplo de tabla de bienes', 'columnas' => [
        ['texto' => 'Placa'], ['texto' => 'Descripción'], ['texto' => 'Estado'],
        ['texto' => 'Cant.', 'alineacion' => 'derecha'], ['texto' => 'Valor', 'alineacion' => 'derecha'],
        ['texto' => 'Acciones', 'alineacion' => 'derecha', 'oculta' => true],
    ]]) ?>
        <?php foreach ($filasEjemplo as $fila): ?>
            <tr>
                <td class="mono" data-label="Placa"><?= $e($fila['placa']) ?></td>
                <td data-label="Descripción"><?= $e($fila['descripcion']) ?></td>
                <td data-label="Estado"><?= ui('insignia_estado', ['tipo' => 'bien', 'estado' => $fila['estado']]) ?></td>
                <td class="celda-numero" data-label="Cant."><?= number_format($fila['cantidad'], 0, ',', '.') ?></td>
                <td class="celda-numero" data-label="Valor"><?= $pesos($fila['valor']) ?></td>
                <td class="celda-acciones" data-label="Acciones">
                    <?= ui('boton', ['texto' => 'Ver el bien ' . $fila['placa'], 'icono' => 'ver', 'tamano' => 'sm', 'soloIcono' => true]) ?>
                    <?= ui('boton', ['texto' => 'Editar el bien ' . $fila['placa'], 'icono' => 'editar', 'tamano' => 'sm', 'soloIcono' => true]) ?>
                </td>
            </tr>
        <?php endforeach; ?>
        <?php View::render('partials/tabla_vacia', ['colspan' => 6, 'mensaje' => 'Estado vacío: no hay bienes con estos filtros.', 'ctaTexto' => 'Limpiar filtros', 'ctaUrl' => Url::to('/manual/ui#tabla')]); ?>
    <?= ui('tabla_fin') ?>
    <?php View::render('partials/paginacion', ['pagina' => 2, 'porPagina' => 25, 'total' => 1340, 'totalPaginas' => 54, 'urlBase' => Url::to('/manual/ui')]); ?>

    <?= ui('tarjeta', ['titulo' => 'Íconos', 'id' => 'iconos']) ?>
        <p class="small text-muted">Una acción o un concepto = un ícono. En las vistas: <code>icono('clave')</code>. La versión rellena solo la usa el menú para la opción activa.</p>
        <?= ui('tabla', ['leyenda' => 'Diccionario de íconos', 'encabezadoFijo' => false, 'columnas' => [
            ['texto' => 'Ícono', 'alineacion' => 'centro'], ['texto' => 'Clave'], ['texto' => 'Bootstrap Icons'], ['texto' => 'Uso'],
        ]]) ?>
            <?php foreach ($iconos as $clave => $entrada): ?>
                <tr>
                    <td class="text-center" data-label="Ícono"><?= icono($clave) ?></td>
                    <td class="mono" data-label="Clave"><?= $e($clave) ?></td>
                    <td class="mono small" data-label="Bootstrap Icons"><?= $e($entrada['icono']) ?><?= isset($entrada['activo']) ? ' / ' . $e($entrada['activo']) : '' ?></td>
                    <td data-label="Uso"><?= $e($entrada['uso']) ?></td>
                </tr>
            <?php endforeach; ?>
        <?= ui('tabla_fin') ?>
    <?= ui('tarjeta_fin') ?>
</div>
