<?php
/**
 * Selector de institución del superusuario en las bibliotecas de evidencia: al elegir una,
 * la ventana se recarga con ?institucion=ID (public/assets/js/evidencia.js).
 * Variables: $instituciones, $institucionId (0 = ninguna) y $ventana (ruta de la app).
 */

use App\Core\Auth;
use App\Core\Url;

if (!Auth::esSuperusuario()) {
    return;
}
?>
<div class="mb-3" style="max-width: 320px;">
    <label for="selectorInstitucion" class="form-label small">Institución</label>
    <select id="selectorInstitucion" class="form-select form-select-sm selector-buscable" data-selector-institucion="<?= Url::to($ventana) ?>">
        <option value="">-- Todas (elige una para registrar) --</option>
        <?php foreach ($instituciones as $i): ?>
            <option value="<?= (int) $i['id'] ?>" <?= $institucionId === (int) $i['id'] ? 'selected' : '' ?>>
                <?= htmlspecialchars($i['nombre'], ENT_QUOTES) ?>
            </option>
        <?php endforeach; ?>
    </select>
</div>
<script src="<?= Url::asset('/assets/js/evidencia.js') ?>" defer></script>
