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
<?= ui('encabezado_pagina', ['titulo' => 'Carga masiva de fotos', 'icono' => 'subir',
    'acciones' => ui('boton', ['texto' => 'Volver', 'icono' => 'volver', 'tamano' => 'sm', 'url' => Url::to('/cargas-masivas')]),
]) ?>

<?php if (!empty($error)): ?><?= ui('alerta', ['tipo' => 'peligro', 'mensaje' => (string) $error, 'clases' => 'py-2 small contenedor-md']) ?><?php endif; ?>

<div class="card mb-4 contenedor-md">
    <div class="card-body">
        <p class="small text-muted mb-2">
            Sube un archivo <strong>.zip</strong> con las fotos de los bienes, cada una nombrada
            exactamente igual al <strong>código del bien</strong> (por ejemplo <code>EQ-001.jpg</code>).
            El sistema busca cada código en el sistema y asocia la foto automáticamente.
        </p>
        <p class="small text-muted mb-3">
            No se reemplaza la foto de un bien que ya tiene una. Si el .zip es muy pesado,
            divídelo en lotes más pequeños y sube varios — puedes repetir este paso las veces
            que necesites, los bienes ya emparejados simplemente se omiten.
        </p>
        <form method="post" action="<?= Url::to('/bienes/carga-masiva-fotos') ?>" enctype="multipart/form-data" class="d-flex gap-2">
            <?= Csrf::field() ?>
            <input type="file" name="archivo" accept=".zip" aria-label="Archivo .zip con las fotos" class="form-control form-control-sm" required>
            <button type="submit" class="btn btn-sm btn-primary text-nowrap">Subir .zip</button>
        </form>
    </div>
</div>

<?php if ($resultado !== null): ?>
    <div class="contenedor-sm mb-4">
        <h2 class="titulo-seccion">Resultado de la carga</h2>
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
        <p class="small text-muted mt-1">El nombre del archivo (sin extensión) no coincide con ningún código de bien registrado en esta institución.</p>
    </div>

    <div class="mb-3">
        <h2 class="h6">Bienes que ya tenían foto (no se reemplazó)</h2>
        <p class="small mb-0"><?= $listar($resultado['ya_tenian_foto']) ?></p>
    </div>

    <div class="mb-3">
        <h2 class="h6">Archivos con formato no válido</h2>
        <p class="small mb-0"><?= $listar($resultado['formato_invalido']) ?></p>
        <p class="small text-muted mt-1">Solo se aceptan imágenes JPG o PNG.</p>
    </div>

    <?php if (!empty($resultado['demasiado_grandes'])): ?>
        <div class="mb-3">
            <h2 class="h6">Imágenes demasiado grandes (no se cargaron)</h2>
            <p class="small mb-0"><?= $listar($resultado['demasiado_grandes']) ?></p>
            <p class="small text-muted mt-1">Cada imagen puede pesar como máximo <?= (int) round(\App\Helpers\Uploader::TAMANO_MAXIMO_IMAGEN / 1024 / 1024) ?> MB. Redúzcalas y vuelva a subirlas.</p>
        </div>
    <?php endif; ?>
<?php endif; ?>
