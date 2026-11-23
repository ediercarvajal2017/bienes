<?php

use App\Core\Csrf;
use App\Core\Url;
use App\Core\View;
use App\Helpers\AyudaCampo;

$viejo ??= [];
$v = static fn (string $campo, mixed $porDefecto = '') => $viejo[$campo] ?? $porDefecto;
?>
<?php ob_start(); ?>
    <a href="<?= Url::to('/escanear') ?>" class="btn btn-sm btn-outline-secondary">Volver</a>
<?= ui('encabezado_pagina', ['titulo' => 'Reportar bien no registrado', 'icono' => 'hallazgo', 'ayuda' => AyudaCampo::boton('hallazgos'), 'acciones' => (string) ob_get_clean()]) ?>

<p class="text-muted small contenedor-md">
    ¿Encontraste un bien físico en tu espacio que no tiene código ni QR? Repórtalo aquí con una
    descripción breve — quien administra la verificación lo revisará y, si corresponde, lo
    registrará formalmente en el sistema.
</p>

<p class="small mb-3 contenedor-md">
    <a href="<?= Url::to('/bienes/buscar-por-foto') ?>"><?= icono('foto', 'me-1') ?>¿Ya existe con otra foto?
    Búscalo antes de reportarlo</a>, para no duplicarlo.
</p>

<form method="post" action="<?= Url::to('/hallazgos') ?>" enctype="multipart/form-data" class="row g-3 contenedor-md">
    <?= Csrf::field() ?>

    <div class="col-12">
        <?= AyudaCampo::etiqueta('campo-espacio-id', 'Espacio donde lo encontraste', 'hallazgo_espacio', true) ?>
        <select id="campo-espacio-id" name="espacio_id" class="form-select selector-buscable" required>
            <option value="">-- Selecciona --</option>
            <?php foreach ($espacios as $e): ?>
                <option value="<?= $e['id'] ?>" <?= (string) $v('espacio_id') === (string) $e['id'] ? 'selected' : '' ?>>
                    <?= htmlspecialchars($e['codigo'] . ' - ' . $e['nombre'], ENT_QUOTES) ?>
                </option>
            <?php endforeach; ?>
        </select>
    </div>

    <div class="col-12">
        <?= AyudaCampo::etiqueta('campo-descripcion', 'Descripción', 'hallazgo_descripcion', true) ?>
        <input id="campo-descripcion" type="text" name="descripcion" class="form-control" required maxlength="300"
               placeholder="Ej. Ventilador de techo, silla plástica azul..."
               value="<?= htmlspecialchars($v('descripcion'), ENT_QUOTES) ?>">
    </div>

    <div class="col-12">
        <?php View::render('partials/campo_foto', [
            'nombreCampo' => 'foto',
            'etiqueta' => 'Foto (opcional, ayuda a identificarlo)',
            'ayuda' => 'hallazgo_foto',
            'fotoActualUrl' => null,
        ]); ?>
    </div>

    <div class="col-12">
        <button type="submit" class="btn btn-primary">Reportar hallazgo</button>
    </div>
</form>
