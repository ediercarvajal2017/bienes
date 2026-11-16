<?php
/**
 * Filtros aplicados en un listado, como etiquetas que se quitan con un clic. Así se ve qué
 * está filtrando la tabla sin tener abierto el panel de filtros.
 *
 * Variables:
 *  - $filtros: list<array{etiqueta: string, valor: string, urlQuitar: string}>
 *    (urlQuitar: la misma dirección sin ese filtro).
 *  - $urlQuitarTodos (?string): enlace "Quitar filtros"; null, no se muestra.
 *  - $idQuitarTodos (?string): id de ese enlace.
 *  - $clases (string): clases extra del contenedor.
 *
 * Sin filtros ni "Quitar filtros" no pinta nada.
 */

$filtros ??= [];
$urlQuitarTodos ??= null;
$idQuitarTodos ??= null;
$clases ??= '';
if ($filtros === [] && $urlQuitarTodos === null) {
    return;
}
$e = static fn (mixed $texto): string => htmlspecialchars((string) $texto, ENT_QUOTES);
?>
<div class="<?= $e(trim('filtros-activos ' . $clases)) ?>" role="group" aria-label="Filtros aplicados">
    <?php foreach ($filtros as $f): ?>
        <a class="filtro-activo" href="<?= $e($f['urlQuitar']) ?>" aria-label="<?= $e("Quitar filtro {$f['etiqueta']}: {$f['valor']}") ?>">
            <span class="filtro-activo-etiqueta"><?= $e($f['etiqueta']) ?>:</span> <?= $e($f['valor']) ?><?= icono('cancelar', 'filtro-activo-quitar') ?>
        </a>
    <?php endforeach; ?>
    <?php if ($urlQuitarTodos !== null): ?>
        <?= ui('boton', ['texto' => 'Quitar filtros', 'tipo' => 'enlace', 'tamano' => 'sm', 'url' => $urlQuitarTodos,
            'clases' => 'filtros-activos-quitar', 'atributos' => ['id' => $idQuitarTodos]]) ?>
    <?php endif; ?>
</div>
