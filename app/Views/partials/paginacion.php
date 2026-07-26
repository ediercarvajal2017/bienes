<?php
/**
 * Partial reutilizable para el pie de tabla paginada. El llamador debe pasar:
 * - $pagina, $porPagina, $total, $totalPaginas
 * - $urlBase: URL ya resuelta con Url::to() e incluyendo cualquier query string
 *   propio de la pantalla (búsqueda, institución, etc.) EXCEPTO 'pagina'.
 * - $opcionesPorPagina (opcional): array de enteros (ej. [10,25,50,100]). Si se pasa,
 *   se muestra un selector "Mostrar N por página" que recarga con ?porPagina=N&pagina=1.
 */
$separador = str_contains($urlBase, '?') ? '&' : '?';
$opcionesPorPagina = $opcionesPorPagina ?? [];
?>
<div class="d-flex flex-wrap justify-content-between align-items-center gap-2 mt-3">
    <span class="text-muted small">
        <?php if ($total > 0): ?>
            Mostrando <?= (($pagina - 1) * $porPagina) + 1 ?>–<?= min($pagina * $porPagina, $total) ?> de <?= $total ?>
        <?php else: ?>
            Sin resultados
        <?php endif; ?>
    </span>

    <div class="d-flex flex-wrap align-items-center gap-3">
        <?php if (!empty($opcionesPorPagina)): ?>
            <div class="d-flex align-items-center gap-2">
                <label for="selectorPorPagina" class="small text-muted mb-0 text-nowrap">Mostrar</label>
                <select id="selectorPorPagina" class="form-select form-select-sm" style="width: auto;">
                    <?php foreach ($opcionesPorPagina as $opcion): ?>
                        <option value="<?= $opcion ?>" <?= $porPagina === $opcion ? 'selected' : '' ?>><?= $opcion ?></option>
                    <?php endforeach; ?>
                </select>
                <span class="small text-muted text-nowrap">por página</span>
            </div>
        <?php endif; ?>

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
</div>

<?php if (!empty($opcionesPorPagina)): ?>
<script>
document.getElementById('selectorPorPagina').addEventListener('change', function () {
    const url = new URL(<?= json_encode($urlBase) ?>, window.location.origin);
    url.searchParams.set('porPagina', this.value);
    url.searchParams.set('pagina', '1');
    window.location = url.toString();
});
</script>
<?php endif; ?>
