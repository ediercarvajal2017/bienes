<?php

use App\Core\Csrf;
use App\Core\Url;
use App\Core\View;

$viejo ??= [];
$v = static fn (string $campo, mixed $porDefecto = '') => $viejo[$campo] ?? $porDefecto;
?>
<div class="d-flex flex-wrap justify-content-between align-items-center gap-2 mb-3">
    <h1 class="h4 mb-0">Reportar bien no registrado</h1>
    <a href="<?= Url::to('/escanear') ?>" class="btn btn-sm btn-outline-secondary">Volver</a>
</div>

<p class="text-muted small" style="max-width: 640px;">
    ¿Encontraste un bien físico en tu espacio que no tiene código ni QR? Repórtalo aquí con una
    descripción breve — quien administra la verificación lo revisará y, si corresponde, lo
    registrará formalmente en el sistema.
</p>

<?php if (!empty($error)): ?>
    <div class="alert alert-danger py-2 small" style="max-width: 640px;"><?= htmlspecialchars($error, ENT_QUOTES) ?></div>
<?php endif; ?>

<p class="text-muted small mb-2">Los campos marcados con <span class="text-danger">*</span> son obligatorios.</p>

<form method="post" action="<?= Url::to('/hallazgos') ?>" enctype="multipart/form-data" class="row g-3" style="max-width: 640px;">
    <?= Csrf::field() ?>

    <div class="col-12">
        <label class="form-label small requerido">Espacio donde lo encontraste</label>
        <select name="espacio_id" class="form-select selector-buscable" required>
            <option value="">-- Selecciona --</option>
            <?php foreach ($espacios as $e): ?>
                <option value="<?= $e['id'] ?>" <?= (string) $v('espacio_id') === (string) $e['id'] ? 'selected' : '' ?>>
                    <?= htmlspecialchars($e['codigo'] . ' - ' . $e['nombre'], ENT_QUOTES) ?>
                </option>
            <?php endforeach; ?>
        </select>
    </div>

    <div class="col-12">
        <label class="form-label small requerido">Descripción</label>
        <input type="text" name="descripcion" class="form-control" required
               placeholder="Ej. Ventilador de techo, silla plástica azul..."
               value="<?= htmlspecialchars($v('descripcion'), ENT_QUOTES) ?>">
    </div>

    <div class="col-12">
        <?php View::render('partials/campo_foto', [
            'nombreCampo' => 'foto',
            'etiqueta' => 'Foto (opcional, ayuda a identificarlo)',
            'fotoActualUrl' => null,
        ]); ?>
    </div>

    <div class="col-12">
        <button type="submit" class="btn btn-primary">Reportar hallazgo</button>
    </div>
</form>
