<?php
/**
 * Campos de "Cartera recibida de la Alcaldía" (registrar y editar). public/assets/js/cartera.js
 * llena el correo del solicitante al elegir el funcionario (se puede cambiar).
 * Variables: $funcionarios (Usuario::elegiblesACargo) y $valores (funcionario_id,
 * correo_solicitante, nombre_remitente, correo_remitente, fecha_envio). Cada campo lleva su
 * ícono (?) con la explicación (App\Helpers\AyudaCampo, claves cartera_*).
 */

use App\Helpers\AyudaCampo;

$e = static fn (mixed $valor): string => htmlspecialchars((string) $valor, ENT_QUOTES);
$correos = [];
foreach ($funcionarios as $f) {
    $correos[(string) $f['id']] = (string) ($f['email'] ?? '');
}
?>
<div class="col-md-6">
    <?= AyudaCampo::etiqueta('campo-funcionario', 'Funcionario que solicitó la cartera', 'cartera_funcionario', true) ?>
    <select id="campo-funcionario" name="funcionario_id" class="form-select selector-buscable" required
            data-correos="<?= $e(json_encode($correos, JSON_UNESCAPED_UNICODE)) ?>" data-correo-destino="campo-correo-solicitante">
        <option value="">-- Selecciona --</option>
        <?php foreach ($funcionarios as $f): ?>
            <option value="<?= (int) $f['id'] ?>" <?= (string) ($valores['funcionario_id'] ?? '') === (string) $f['id'] ? 'selected' : '' ?>>
                <?= $e(trim($f['nombres'] . ' ' . $f['apellidos']) . (!empty($f['cargo_nombre']) ? ' · ' . $f['cargo_nombre'] : '')) ?>
            </option>
        <?php endforeach; ?>
    </select>
</div>
<div class="col-md-6">
    <?= AyudaCampo::etiqueta('campo-correo-solicitante', 'Correo del funcionario que la solicitó', 'cartera_correo_solicitante', true) ?>
    <input id="campo-correo-solicitante" type="email" name="correo_solicitante" class="form-control" required
           value="<?= $e($valores['correo_solicitante'] ?? '') ?>">
</div>
<div class="col-md-6">
    <?= AyudaCampo::etiqueta('campo-nombre-remitente', 'Funcionario de la Alcaldía que envió la cartera', 'cartera_remitente', true) ?>
    <input id="campo-nombre-remitente" type="text" name="nombre_remitente" class="form-control" required maxlength="150"
           autocomplete="off" placeholder="Nombres y apellidos completos" value="<?= $e($valores['nombre_remitente'] ?? '') ?>">
</div>
<div class="col-md-6">
    <?= AyudaCampo::etiqueta('campo-correo-remitente', 'Correo desde el que llegó la cartera', 'cartera_correo_remitente', true) ?>
    <input id="campo-correo-remitente" type="email" name="correo_remitente" class="form-control" required
           placeholder="Ej.: el correo de la Alcaldía" value="<?= $e($valores['correo_remitente'] ?? '') ?>">
</div>
<div class="col-md-6">
    <?= AyudaCampo::etiqueta('campo-fecha-envio', 'Fecha en que se recibió', 'cartera_fecha', true) ?>
    <input id="campo-fecha-envio" type="date" name="fecha_envio" class="form-control" required
           min="<?= \App\Helpers\FechaMovimiento::MINIMA ?>" max="<?= \App\Helpers\FechaMovimiento::hoy() ?>"
           value="<?= $e($valores['fecha_envio'] ?? date('Y-m-d')) ?>">
</div>
<script src="<?= \App\Core\Url::asset('/assets/js/cartera.js') ?>" defer></script>
