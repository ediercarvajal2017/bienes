<?php
/**
 * Muestra de un token de color para la guía visual (/manual/ui): el recuadro toma el color
 * del token, así que la muestra cambia con el tema claro u oscuro.
 *
 * Variables: $token (nombre de la variable CSS sin "--", p. ej. "sigebi-primary"), $uso (string).
 */
if (preg_match('/^[a-z0-9-]+$/', $token) !== 1) {
    throw new \InvalidArgumentException("Token no válido: \"{$token}\".");
}
?>
<div class="muestra-color">
    <span class="muestra-color-recuadro" style="background: var(--<?= $token ?>);"></span>
    <span><code>--<?= $token ?></code><br><span class="small text-muted"><?= htmlspecialchars($uso, ENT_QUOTES) ?></span></span>
</div>
