<?php

use App\Core\Aviso;
use App\Core\Url;
use App\Helpers\AyudaCampo;
use App\Helpers\Evidencia;

/**
 * Verificar un lote en la cartera de la Alcaldía (CarteraController::verificacion): se elige un
 * lote entregado y la tabla muestra solo sus bienes que siguen en la cartera
 * (App\Services\VerificacionCartera). Solo lee.
 *
 * Variables: $envio, $lotes, $loteId, $lote, $problema (aviso guiado o null), $lectura,
 * $resultado, $columnaPlaca, $columnaCantidad.
 */
$e = static fn (mixed $texto): string => htmlspecialchars((string) $texto, ENT_QUOTES);
$envioId = (int) $envio['id'];
$urlPagina = Url::to('/cartera/' . $envioId . '/verificacion');
$cifra = static fn (int $n): string => number_format($n, 0, ',', '.');
$bienes = static fn (int $n): string => $n === 1 ? '1 bien' : $cifra($n) . ' bienes';
$consulta = array_filter(['lote' => $loteId ?: null, 'columna' => $columnaPlaca, 'cantidad' => $columnaCantidad]);
$urlExcel = Url::to('/cartera/' . $envioId . '/verificacion.xlsx') . '?' . http_build_query($consulta);

$opcionesLote = [];
foreach ($lotes as $l) {
    $categorias = !empty($l['categorias']) ? ' · ' . str_replace('|', ', ', (string) $l['categorias']) : '';
    $despues = (string) $l['reintegrado_fecha'] > (string) $envio['fecha_envio'] ? ' (después de esta cartera)' : '';
    $opcionesLote[(int) $l['id']] = 'Lote #' . (int) $l['id'] . $categorias . ' · entregado el ' . $l['reintegrado_fecha']
        . ' · ' . $bienes((int) $l['total_bienes']) . $despues;
}
$conResultado = $resultado !== null;
?>
<?php ob_start(); ?>
<?= ui('boton', ['texto' => 'Volver', 'icono' => 'volver', 'tamano' => 'sm', 'url' => Url::to(Evidencia::ruta('/cartera/enviar', (int) $envio['institucion_id']))]) ?>
<?php if ($conResultado && $resultado['filas'] !== []): ?>
    <?= ui('boton', ['texto' => 'Descargar en Excel', 'icono' => 'exportar_excel', 'tamano' => 'sm', 'url' => $urlExcel]) ?>
<?php endif; ?>
<?= ui('encabezado_pagina', ['titulo' => 'Verificar un lote en la cartera', 'icono' => 'verificacion',
    'subtitulo' => 'Cartera recibida de la Alcaldía el ' . $envio['fecha_envio'] . (!empty($envio['nombre_remitente']) ? ', enviada por ' . $envio['nombre_remitente'] : '') . '.',
    'ayuda' => AyudaCampo::boton('verificacion_cartera'), 'acciones' => (string) ob_get_clean()]) ?>

<?php if ($lotes === []): ?>
    <?= ui('estado_vacio', ['titulo' => 'No hay lotes entregados para verificar',
        'detalle' => 'Solo se verifican los lotes marcados como entregados a la Alcaldía. Cuando entregues un lote, márcalo como reintegrado en Lotes de reintegro y vuelve aquí con la cartera siguiente.',
        'acciones' => [['texto' => 'Ir a Lotes de reintegro', 'url' => Url::to('/reintegros/lotes')]]]) ?>
<?php else: ?>
    <section class="card mb-3 contenedor-md" aria-labelledby="tituloElegirLote">
        <div class="card-body">
            <h2 class="h6 mb-2" id="tituloElegirLote">¿Qué lote quieres verificar?</h2>
            <form method="get" action="<?= $urlPagina ?>" class="row g-2 align-items-end">
                <?php if ($columnaPlaca !== null): ?><input type="hidden" name="columna" value="<?= $e($columnaPlaca) ?>"><?php endif; ?>
                <?php if ($columnaCantidad !== null): ?><input type="hidden" name="cantidad" value="<?= $e($columnaCantidad) ?>"><?php endif; ?>
                <div class="col-md-9">
                    <?= ui('campo_formulario', ['id' => 'campoLote', 'nombre' => 'lote', 'etiqueta' => 'Lote entregado a la Alcaldía', 'tipo' => 'select',
                        'opciones' => $opcionesLote, 'valor' => $loteId ?: '', 'vacia' => '-- Elige un lote --', 'requerido' => true]) ?>
                </div>
                <div class="col-md-3">
                    <?= ui('boton', ['texto' => 'Verificar', 'icono' => 'verificacion', 'tipo' => $conResultado ? 'secundario' : 'primario', 'tipoBoton' => 'submit', 'clases' => 'w-100']) ?>
                </div>
            </form>
        </div>
    </section>
<?php endif; ?>

<?php if ($problema !== null): ?>
    <div class="contenedor-md"><?= ui('avisos', ['avisos' => [$problema]]) ?></div>
<?php endif; ?>

<?php if ($lectura !== null): ?>
    <?php // Elegir la columna a mano: siempre que MIA no la reconoce y, si la reconoció, para corregirla. ?>
    <?php ob_start(); ?>
    <form method="get" action="<?= $urlPagina ?>" class="row g-2 align-items-end">
        <input type="hidden" name="lote" value="<?= (int) $loteId ?>">
        <div class="col-md-5">
            <?= ui('campo_formulario', ['id' => 'campoColumna', 'nombre' => 'columna', 'etiqueta' => '¿En qué columna está la placa?', 'tipo' => 'select',
                'opciones' => $lectura['columnas'], 'valor' => $lectura['columnaPlaca'] ?? '', 'vacia' => '-- Elige la columna --', 'requerido' => true]) ?>
        </div>
        <div class="col-md-4">
            <?= ui('campo_formulario', ['id' => 'campoCantidad', 'nombre' => 'cantidad', 'etiqueta' => '¿Y la cantidad? (opcional)', 'tipo' => 'select',
                'opciones' => $lectura['columnas'], 'valor' => $lectura['columnaCantidad'] ?? '', 'vacia' => 'No tiene columna de cantidad']) ?>
        </div>
        <div class="col-md-3">
            <?= ui('boton', ['texto' => 'Verificar con estas columnas', 'tipoBoton' => 'submit', 'tipo' => $lectura['encontrada'] ? 'secundario' : 'primario', 'clases' => 'w-100']) ?>
        </div>
    </form>
    <?php $formularioColumnas = (string) ob_get_clean(); ?>

    <?php if (!$lectura['encontrada']): ?>
        <section class="card mb-3 contenedor-md" aria-label="Elegir las columnas de la cartera">
            <div class="card-body"><?= $formularioColumnas ?></div>
        </section>
    <?php else: ?>
        <?php
        $dondePlaca = 'la columna ' . $lectura['columnaPlaca'] . ($lectura['tituloPlaca'] !== null ? ' «' . $lectura['tituloPlaca'] . '»' : '');
        $dondeCantidad = $lectura['columnaCantidad'] !== null
            ? 'la cantidad, de la columna ' . $lectura['columnaCantidad'] . ($lectura['tituloCantidad'] !== null ? ' «' . $lectura['tituloCantidad'] . '»' : '')
            : 'sin columna de cantidad: se toma el número con que empieza la descripción, o 1';
        ?>
        <details class="small text-muted mb-3 contenedor-md">
            <summary>Se leyeron <?= $cifra(count($lectura['placas'])) ?> placas de la cartera (placa de <?= $e($dondePlaca) ?>; <?= $e($dondeCantidad) ?>). ¿No son las columnas correctas?</summary>
            <div class="mt-2"><?= $formularioColumnas ?></div>
        </details>
    <?php endif; ?>
<?php endif; ?>

<?php if ($conResultado && $lote !== null): ?>
    <?php
    $numero = '#' . (int) $lote['id'];
    $totalSiguen = (int) $resultado['total_siguen'];
    $totalLote = (int) $resultado['total_lote'];
    $textoLote = $bienes($totalLote);
    $textoSiguen = $cifra($totalSiguen);
    if ($totalLote === 0) {
        $aviso = Aviso::info("El lote {$numero} no tiene bienes por verificar",
            'Sus bienes se reactivaron o se reintegraron de nuevo en otro lote: ya no le corresponde a este lote.');
    } elseif ($totalSiguen === 0) {
        $aviso = Aviso::exito('La Alcaldía descargó todos los bienes de este lote.',
            ($totalLote === 1 ? "El bien del lote {$numero}" : "Ninguno de los {$textoLote} del lote {$numero}")
            . " (entregado el {$lote['reintegrado_fecha']}) " . ($totalLote === 1 ? 'ya no aparece' : 'aparece')
            . " en la cartera recibida el {$envio['fecha_envio']}.");
    } else {
        $aviso = Aviso::advertencia(
            ($totalSiguen === 1 ? "1 bien del lote {$numero} sigue" : "{$textoSiguen} bienes del lote {$numero} siguen") . ' en la cartera de la Alcaldía',
            "El lote se entregó el {$lote['reintegrado_fecha']} y la cartera recibida el {$envio['fecha_envio']} todavía " . ($totalSiguen === 1 ? 'lo tiene' : 'los tiene') . ": {$textoSiguen} de {$textoLote}.",
            'Descarga el Excel y envíalo a la Alcaldía para que ' . ($totalSiguen === 1 ? 'lo descargue.' : 'los descargue.'),
            ['texto' => 'Descargar en Excel', 'url' => $urlExcel]);
    }
    ?>
    <div class="contenedor-md"><?= ui('avisos', ['avisos' => [$aviso], 'enfocar' => false]) ?></div>

    <?php if ($resultado['filas'] !== []): ?>
        <?= ui('herramientas_tabla', ['suelta' => true]) ?>
        <?= ui('tabla', ['leyenda' => "Bienes del lote {$numero} que siguen en la cartera", 'navegable' => true, 'columnas' => [
            ['texto' => 'Placa', 'fija' => true, 'ordenLocal' => true],
            ['texto' => 'Descripción', 'ordenLocal' => true],
            ['texto' => 'Siguen en la cartera', 'alineacion' => 'derecha', 'ordenLocal' => true],
            ['texto' => 'Reintegrado el', 'ordenLocal' => true],
        ]]) ?>
        <?php foreach ($resultado['filas'] as $f): ?>
            <tr>
                <td class="mono" data-label="Placa"><?= $e($f['placa']) ?></td>
                <td data-label="Descripción">
                    <?= $e($f['descripcion']) ?>
                    <?php if ($f['con_unidades']): ?>
                        <div class="small text-muted"><?= $f['cantidad_lote'] === 1 ? '1 unidad' : $cifra($f['cantidad_lote']) . ' unidades' ?> de esta placa en el lote</div>
                    <?php endif; ?>
                </td>
                <td class="text-end" data-label="Siguen en la cartera">
                    <span class="fw-semibold"><?= $cifra($f['siguen']) ?></span>
                    <?php if ($f['con_unidades']): ?>
                        <div class="small text-muted">La cartera dice <?= $cifra($f['cartera_dice']) ?>; deberían quedar <?= $cifra((int) $f['deberian_quedar']) ?>.</div>
                        <?php if ($f['en_otros_lotes'] > 0): ?>
                            <div class="small text-muted">
                                Otros lotes entregados también tienen <?= $cifra($f['en_otros_lotes']) ?> de esta placa
                                (<?= $e(implode(', ', array_map(static fn (int $id): string => '#' . $id, $f['otros_lotes']))) ?>): la cartera no dice de cuál son.
                            </div>
                        <?php endif; ?>
                    <?php else: ?>
                        <div class="small text-muted">La placa sigue en la cartera.</div>
                    <?php endif; ?>
                </td>
                <td class="mono text-muted" data-label="Reintegrado el">
                    <?= $e($f['fecha_desde']) ?><?= $f['fecha_hasta'] !== $f['fecha_desde'] ? ' → ' . $e($f['fecha_hasta']) : '' ?>
                </td>
            </tr>
        <?php endforeach; ?>
        <?= ui('tabla_fin') ?>
    <?php endif; ?>
<?php endif; ?>
