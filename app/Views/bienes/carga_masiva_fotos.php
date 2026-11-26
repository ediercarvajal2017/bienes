<?php

use App\Core\Csrf;
use App\Core\Url;

/**
 * Muestra hasta $limite códigos/nombres de una lista y, si sobran más, un resumen
 * "y N más" en vez de imprimir centenares de filas.
 */
$listar = static function (array $items, int $limite = 40): string {
    if ($items === []) {
        return '<span class="text-muted">Ninguno.</span>';
    }

    $mostrados = array_slice($items, 0, $limite);
    $html = htmlspecialchars(implode(', ', $mostrados), ENT_QUOTES);

    $restantes = count($items) - count($mostrados);
    if ($restantes > 0) {
        $html .= " <span class=\"text-muted\">y {$restantes} más.</span>";
    }

    return $html;
};
?>
<?php $volver = ui('boton', ['texto' => 'Volver', 'icono' => 'volver', 'tamano' => 'sm', 'url' => Url::to('/cargas-masivas')]); ?>
<?= ui('encabezado_pagina', ['titulo' => 'Carga masiva de fotos', 'icono' => 'subir', 'acciones' => $volver, 'ayuda' => \App\Helpers\AyudaCampo::boton('carga_fotos')]) ?>

<div class="card mb-4 contenedor-md">
    <div class="card-body">
        <?= \App\Helpers\AyudaCampo::etiqueta('archivoFotos', 'Archivo .zip con las fotos', 'carga_fotos_archivo', true) ?>
        <form method="post" action="<?= Url::to('/bienes/carga-masiva-fotos') ?>" enctype="multipart/form-data" class="d-flex gap-2">
            <?= Csrf::field() ?>
            <input type="file" name="archivo" id="archivoFotos" accept=".zip" class="form-control form-control-sm" required>
            <button type="submit" class="btn btn-sm btn-primary text-nowrap">Subir .zip</button>
        </form>
    </div>
</div>

<?php if ($resultado !== null): ?>
    <div class="contenedor-sm mb-4">
        <div class="d-flex align-items-center gap-1 mb-3"><h2 class="titulo-seccion mb-0">Resultado de la carga</h2><?= \App\Helpers\AyudaCampo::boton('resultado_fotos') ?></div>
        <?= ui('tabla', ['leyenda' => 'Resultado de la carga de fotos', 'encabezadoFijo' => false, 'columnas' => [
            ['texto' => 'Resultado'], ['texto' => 'Fotos', 'alineacion' => 'derecha'],
        ]]) ?>
            <?php foreach ([
                'Fotos asociadas' => $resultado['emparejadas'],
                'Ya tenían foto (no se reemplazó)' => $resultado['ya_tenian_foto'],
                'Sin bien correspondiente' => $resultado['sin_bien'],
                'Formato no válido' => $resultado['formato_invalido'],
            ] as $resultadoTexto => $lista): ?>
                <tr>
                    <td data-label="Resultado"><?= htmlspecialchars($resultadoTexto, ENT_QUOTES) ?></td>
                    <td class="celda-numero" data-label="Fotos"><?= number_format(count($lista), 0, ',', '.') ?></td>
                </tr>
            <?php endforeach; ?>
        <?= ui('tabla_fin') ?>
    </div>

    <div class="mb-3">
        <h2 class="h6">Bienes sin foto encontrada en el .zip</h2>
        <p class="small mb-0"><?= $listar($resultado['sin_bien']) ?></p>
    </div>

    <div class="mb-3">
        <h2 class="h6">Bienes que ya tenían foto (no se reemplazó)</h2>
        <p class="small mb-0"><?= $listar($resultado['ya_tenian_foto']) ?></p>
    </div>

    <div class="mb-3">
        <h2 class="h6">Archivos con formato no válido</h2>
        <p class="small mb-0"><?= $listar($resultado['formato_invalido']) ?></p>
    </div>

    <?php if (!empty($resultado['demasiado_grandes'])): ?>
        <div class="mb-3">
            <h2 class="h6">Imágenes demasiado grandes (no se cargaron)</h2>
            <p class="small mb-0"><?= $listar($resultado['demasiado_grandes']) ?></p>
        </div>
    <?php endif; ?>
<?php endif; ?>
