<?php
/**
 * Estado vacío que explica: qué pasó (título), por qué y qué hacer (detalle), con la acción a
 * un clic. Para búsquedas o listas sin resultados donde el motivo no es obvio (p. ej. un código
 * que no está en Reintegrar por cantidad porque no se registró con Alta masiva idéntica).
 * Para una lista vacía sin más que decir basta partials/tabla_vacia.php.
 *
 * Variables:
 *  - $titulo (string): qué pasó, en una línea.
 *  - $detalle (string): por qué y qué hacer.
 *  - $acciones: list<array{texto: string, url: string}>; la primera es la acción principal.
 *  - $clases (string): clases extra del contenedor.
 */

$acciones ??= [];
$clases ??= '';
$e = static fn (mixed $texto): string => htmlspecialchars((string) $texto, ENT_QUOTES);
?>
<div class="<?= $e(trim('estado-vacio-explicado ' . $clases)) ?>" role="status">
    <p class="estado-vacio-titulo"><?= $e($titulo) ?></p>
    <p class="estado-vacio-detalle"><?= $e($detalle) ?></p>
    <?php if ($acciones !== []): ?>
        <div class="estado-vacio-acciones">
            <?php foreach ($acciones as $i => $accion): ?>
                <?= ui('boton', ['texto' => $accion['texto'], 'url' => $accion['url'], 'tamano' => 'sm', 'tipo' => $i === 0 ? 'primario' : 'secundario']) ?>
            <?php endforeach; ?>
        </div>
    <?php endif; ?>
</div>
