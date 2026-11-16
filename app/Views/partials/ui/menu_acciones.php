<?php
/**
 * Menú desplegable "Acciones" de una pantalla: una lista corta de opciones, sin títulos de
 * grupo ni íconos. Lo abre y lo cierra public/assets/js/desplegable.js (Bootstrap sin su JS).
 *
 * Variables:
 *  - $opciones: list<array{clave: string, texto: string, url: string}> (p. ej.
 *    App\Helpers\AccionesBienes::opciones()).
 *  - $actual (?string): clave de la opción de esta pantalla; se marca con aria-current.
 *  - $extras (string): HTML de opciones propias de la pantalla (<li>…</li>), al final.
 *  - $id (string): id del botón (por defecto "botonAccionesMasivas").
 *
 * En una pantalla de destino ($actual), si no hay otra opción a la cual pasar, no pinta nada.
 */

$actual ??= null;
$extras ??= '';
$id ??= 'botonAccionesMasivas';
$otras = array_filter($opciones, static fn (array $o): bool => $o['clave'] !== $actual);
if (($actual !== null && $otras === [] && $extras === '') || ($opciones === [] && $extras === '')) {
    return;
}
$e = static fn (mixed $texto): string => htmlspecialchars((string) $texto, ENT_QUOTES);
?>
<div class="dropdown menu-acciones">
    <?= ui('boton', ['texto' => 'Acciones', 'tamano' => 'sm', 'clases' => 'dropdown-toggle',
        'atributos' => ['id' => $id, 'data-desplegable' => true, 'aria-haspopup' => 'true', 'aria-expanded' => 'false']]) ?>
    <ul class="dropdown-menu" aria-labelledby="<?= $e($id) ?>">
        <?php foreach ($opciones as $o): ?>
            <li><a class="dropdown-item" href="<?= $e($o['url']) ?>"<?= $o['clave'] === $actual ? ' aria-current="page"' : '' ?>><?= $e($o['texto']) ?></a></li>
        <?php endforeach; ?>
        <?= $extras ?>
    </ul>
</div>
