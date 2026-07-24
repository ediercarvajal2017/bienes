<?php
/**
 * Partial reutilizable para el pie de tabla paginada. El llamador debe pasar:
 * - $pagina, $porPagina, $total, $totalPaginas
 * - $urlBase: URL ya resuelta con Url::to() e incluyendo cualquier query string
 *   propio de la pantalla (búsqueda, institución, etc.) EXCEPTO 'pagina'.
 */
$separador = str_contains($urlBase, '?') ? '&' : '?';
?>
<div class="d-flex flex-wrap justify-content-between align-items-center gap-2 mt-3">
    <span class="text-muted small">
        <?php if ($total > 0): ?>
            Mostrando <?= (($pagina - 1) * $porPagina) + 1 ?>–<?= min($pagina * $porPagina, $total) ?> de <?= $total ?>
        <?php else: ?>
            Sin resultados
        <?php endif; ?>
    </span>
    <?php if ($totalPaginas > 1): ?>
        <div class="btn-group btn-group-sm">
            <a class="btn btn-outline-secondary<?= $pagina <= 1 ? ' disabled' : '' ?>"
               href="<?= $urlBase . $separador . 'pagina=' . max(1, $pagina - 1) ?>">Anterior</a>
            <span class="btn btn-outline-secondary disabled">Página <?= $pagina ?> de <?= $totalPaginas ?></span>
            <a class="btn btn-outline-secondary<?= $pagina >= $totalPaginas ? ' disabled' : '' ?>"
               href="<?= $urlBase . $separador . 'pagina=' . min($totalPaginas, $pagina + 1) ?>">Siguiente</a>
        </div>
    <?php endif; ?>
</div>
