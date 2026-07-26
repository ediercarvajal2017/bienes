<?php
/**
 * Partial reutilizable para el pie (o cabecera) de una tabla paginada. El llamador debe pasar:
 * - $pagina, $porPagina, $total, $totalPaginas
 * - $urlBase: URL ya resuelta con Url::to() e incluyendo cualquier query string
 *   propio de la pantalla (búsqueda, institución, etc.) EXCEPTO 'pagina'/'porPagina'.
 * - $opcionesPorPagina (opcional): array de enteros (ej. [10,25,50,100,0]). Si se pasa,
 *   se muestra un selector "Mostrar N por página" que recarga con ?porPagina=N&pagina=1.
 *   El valor 0 se muestra como "Todos" y desactiva el límite (una sola "página" con todo).
 *
 * Se puede incluir varias veces en la misma vista (arriba y abajo de la tabla): el
 * selector usa una clase (no id) y el script queda protegido contra registrarse más
 * de una vez, así que no importa cuántas copias del partial haya en la página.
 */
$separador = str_contains($urlBase, '?') ? '&' : '?';
$opcionesPorPagina = $opcionesPorPagina ?? [];
?>
<div class="d-flex flex-wrap justify-content-between align-items-center gap-2 my-3">
    <span class="text-muted small">
        <?php if ($total > 0 && $porPagina > 0): ?>
            Mostrando <?= (($pagina - 1) * $porPagina) + 1 ?>–<?= min($pagina * $porPagina, $total) ?> de <?= $total ?>
        <?php elseif ($total > 0): ?>
            Mostrando los <?= $total ?> registros
        <?php else: ?>
            Sin resultados
        <?php endif; ?>
    </span>

    <div class="d-flex flex-wrap align-items-center gap-3">
        <?php if (!empty($opcionesPorPagina)): ?>
            <div class="d-flex align-items-center gap-2">
                <label class="small text-muted mb-0 text-nowrap">Mostrar</label>
                <select class="form-select form-select-sm selectorPorPagina" style="width: auto;"
                        data-url-base="<?= htmlspecialchars($urlBase, ENT_QUOTES) ?>">
                    <?php foreach ($opcionesPorPagina as $opcion): ?>
                        <option value="<?= $opcion ?>" <?= $porPagina === $opcion ? 'selected' : '' ?>>
                            <?= $opcion === 0 ? 'Todos' : $opcion ?>
                        </option>
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

<?php if (!empty($opcionesPorPagina) && !($GLOBALS['__paginacionScriptImpreso'] ?? false)): ?>
    <?php $GLOBALS['__paginacionScriptImpreso'] = true; ?>
    <script>
    document.addEventListener('change', function (evento) {
        if (!evento.target.classList.contains('selectorPorPagina')) {
            return;
        }
        const url = new URL(evento.target.getAttribute('data-url-base'), window.location.origin);
        url.searchParams.set('porPagina', evento.target.value);
        url.searchParams.set('pagina', '1');
        window.location = url.toString();
    });
    </script>
<?php endif; ?>
