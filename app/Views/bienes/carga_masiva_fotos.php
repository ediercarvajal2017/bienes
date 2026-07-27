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
<div class="d-flex flex-wrap justify-content-between align-items-center gap-2 mb-3">
    <h1 class="h4 mb-0">Carga masiva de fotos</h1>
    <a href="<?= Url::to('/cargas-masivas') ?>" class="btn btn-sm btn-outline-secondary">Volver</a>
</div>

<?php if (!empty($error)): ?>
    <div class="alert alert-danger py-2 small" style="max-width:640px;"><?= htmlspecialchars($error, ENT_QUOTES) ?></div>
<?php endif; ?>

<div class="card mb-4" style="max-width: 640px;">
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
            <input type="file" name="archivo" accept=".zip" class="form-control form-control-sm" required>
            <button type="submit" class="btn btn-sm btn-primary text-nowrap">Subir .zip</button>
        </form>
    </div>
</div>

<?php if ($resultado !== null): ?>
    <div class="row g-3 mb-4" style="max-width: 760px;">
        <div class="col-6 col-md-3">
            <div class="card h-100">
                <div class="card-body text-center py-3">
                    <div class="fs-4 fw-semibold text-success"><?= count($resultado['emparejadas']) ?></div>
                    <div class="small text-muted">Fotos asociadas</div>
                </div>
            </div>
        </div>
        <div class="col-6 col-md-3">
            <div class="card h-100">
                <div class="card-body text-center py-3">
                    <div class="fs-4 fw-semibold text-muted"><?= count($resultado['ya_tenian_foto']) ?></div>
                    <div class="small text-muted">Ya tenían foto</div>
                </div>
            </div>
        </div>
        <div class="col-6 col-md-3">
            <div class="card h-100">
                <div class="card-body text-center py-3">
                    <div class="fs-4 fw-semibold text-warning"><?= count($resultado['sin_bien']) ?></div>
                    <div class="small text-muted">Sin bien correspondiente</div>
                </div>
            </div>
        </div>
        <div class="col-6 col-md-3">
            <div class="card h-100">
                <div class="card-body text-center py-3">
                    <div class="fs-4 fw-semibold text-danger"><?= count($resultado['formato_invalido']) ?></div>
                    <div class="small text-muted">Formato no válido</div>
                </div>
            </div>
        </div>
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
<?php endif; ?>
