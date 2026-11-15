<?php
/**
 * Barra horizontal de proporciones (cifras del panel): uno o varios segmentos con su ancho
 * en porcentaje. El ancho es un dato, no un estilo de diseño: por eso va en línea aquí, en el
 * componente, y no en las vistas.
 *
 * Variables:
 *  - $segmentos: list<array{porcentaje: int|float, token?: string, clase?: string, titulo?: string}>
 *    (token: color de fondo, nombre de la variable CSS sin "--", p. ej. "sigebi-estado-activo").
 *  - $clase (string): clases del contenedor (p. ej. "barra-apilada", "progress").
 *  - $etiqueta (?string): descripción para lectores de pantalla.
 *  - $rol: 'img' | 'progressbar' | null (null: decorativa, aria-hidden).
 *  - $valor (?int): valor de la barra de progreso (0-100).
 */

$clase ??= '';
$etiqueta ??= null;
$rol ??= null;
$valor ??= null;
$e = static fn (mixed $texto): string => htmlspecialchars((string) $texto, ENT_QUOTES);

$atributos = $rol === null ? ' aria-hidden="true"' : ' role="' . $e($rol) . '"' . ($etiqueta !== null ? ' aria-label="' . $e($etiqueta) . '"' : '');
if ($rol === 'progressbar' && $valor !== null) {
    $atributos .= ' aria-valuenow="' . (int) $valor . '" aria-valuemin="0" aria-valuemax="100"';
}
?>
<div class="<?= $e($clase) ?>"<?= $atributos ?>>
    <?php foreach ($segmentos as $s): ?>
        <?php
        $ancho = max(0, min(100, (float) $s['porcentaje']));
        $token = $s['token'] ?? null;
        if ($token !== null && preg_match('/^[a-z0-9-]+$/', $token) !== 1) {
            throw new \InvalidArgumentException("Token no válido: \"{$token}\".");
        }
        ?>
        <span<?= isset($s['clase']) ? ' class="' . $e($s['clase']) . '"' : '' ?><?= isset($s['titulo']) ? ' title="' . $e($s['titulo']) . '"' : '' ?>
            style="width: <?= rtrim(rtrim(number_format($ancho, 2, '.', ''), '0'), '.') ?>%;<?= $token !== null ? ' background: var(--' . $token . ');' : '' ?>"></span>
    <?php endforeach; ?>
</div>
