<?php
/**
 * Campos de "Cartera recibida de la Alcaldía" (registrar y editar). public/assets/js/cartera.js
 * llena el correo del solicitante al elegir el funcionario (se puede cambiar).
 * Variables: $funcionarios (Usuario::elegiblesACargo) y $valores (funcionario_id,
 * correo_solicitante, correo_remitente, fecha_envio).
 */

$e = static fn (mixed $valor): string => htmlspecialchars((string) $valor, ENT_QUOTES);
$correos = [];
foreach ($funcionarios as $f) {
    $correos[(string) $f['id']] = (string) ($f['email'] ?? '');
}
?>
<div class="col-md-6">
    <label for="campo-funcionario" class="form-label small requerido">Funcionario que solicitó la cartera</label>
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
    <label for="campo-correo-solicitante" class="form-label small requerido">Correo del funcionario que la solicitó</label>
    <input id="campo-correo-solicitante" type="email" name="correo_solicitante" class="form-control" required
           value="<?= $e($valores['correo_solicitante'] ?? '') ?>">
    <div class="form-text">Se llena con el correo del funcionario elegido; cámbialo si la solicitud salió de otro correo.</div>
</div>
<div class="col-md-6">
    <label for="campo-correo-remitente" class="form-label small requerido">Correo desde el que llegó la cartera</label>
    <input id="campo-correo-remitente" type="email" name="correo_remitente" class="form-control" required
           placeholder="Ej.: el correo de la Alcaldía" value="<?= $e($valores['correo_remitente'] ?? '') ?>">
</div>
<div class="col-md-6">
    <label for="campo-fecha-envio" class="form-label small requerido">Fecha en que se recibió</label>
    <input id="campo-fecha-envio" type="date" name="fecha_envio" class="form-control" required
           min="<?= \App\Helpers\FechaMovimiento::MINIMA ?>" max="<?= \App\Helpers\FechaMovimiento::hoy() ?>"
           value="<?= $e($valores['fecha_envio'] ?? date('Y-m-d')) ?>">
</div>
<script src="<?= \App\Core\Url::asset('/assets/js/cartera.js') ?>" defer></script>
