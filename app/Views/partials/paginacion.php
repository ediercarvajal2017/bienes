<?php
/**
 * Partial reutilizable para el pie (o cabecera) de una tabla paginada. El llamador debe pasar:
 * - $pagina, $porPagina, $total, $totalPaginas
 * - $urlBase: URL ya resuelta con Url::to() e incluyendo cualquier query string
 *   propio de la pantalla (búsqueda, institución, etc.) EXCEPTO 'pagina'/'porPagina'.
 * - $opcionesPorPagina (opcional): array de enteros (ej. [10,25,50,100,0]). Si se pasa,
 *   se muestra un selector "Mostrar N por página" que recarga con ?porPagina=N&pagina=1.
 *   El valor 0 se muestra como "Todos" y desactiva el límite (una sola "página" con todo).
 * - $paramPagina / $paramPorPagina (opcional, default 'pagina'/'porPagina'): nombre de los
 *   parámetros de query string a usar. Necesario cuando una misma pantalla tiene varias
 *   tablas paginadas de forma independiente (ej. /verificaciones/{id}: verificados,
 *   discrepancias y pendientes cada una con su propia paginación) — así cambiar de
 *   página en una tabla no pisa ni reinicia las otras dos.
 *
 * Se puede incluir varias veces en la misma vista (arriba y abajo de la tabla): el
 * selector usa una clase (no id) y el script queda protegido contra registrarse más
 * de una vez, así que no importa cuántas copias del partial haya en la página.
 */
$separador = str_contains($urlBase, '?') ? '&' : '?';
$opcionesPorPagina = $opcionesPorPagina ?? [];
$paramPagina = $paramPagina ?? 'pagina';
$paramPorPagina = $paramPorPagina ?? 'porPagina';

// Enlace a una página conservando el tamaño elegido: antes "Siguiente" no llevaba
// porPagina y, con "Mostrar 50", la página 2 volvía a 25 y mostraba otros registros.
$enlacePagina = static function (int $numero) use ($urlBase, $separador, $paramPagina, $paramPorPagina, $opcionesPorPagina, $porPagina): string {
    $url = $urlBase . $separador . $paramPagina . '=' . $numero;
    if (!empty($opcionesPorPagina)) {
        $url .= '&' . $paramPorPagina . '=' . $porPagina;
    }

    return htmlspecialchars($url, ENT_QUOTES);
};

// Números visibles: la primera, la última y dos a cada lado de la actual; el resto, "…".
$numerosVisibles = [];
if ($totalPaginas > 1) {
    foreach (range(1, $totalPaginas) as $n) {
        if ($n === 1 || $n === $totalPaginas || abs($n - $pagina) <= 2) {
            $numerosVisibles[] = $n;
        }
    }
}
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
                <label class="small text-muted mb-0 text-nowrap">Mostrar
                <select class="form-select form-select-sm selectorPorPagina d-inline-block w-auto ms-1"
                        data-url-base="<?= htmlspecialchars($urlBase, ENT_QUOTES) ?>"
                        data-param-pagina="<?= htmlspecialchars($paramPagina, ENT_QUOTES) ?>"
                        data-param-por-pagina="<?= htmlspecialchars($paramPorPagina, ENT_QUOTES) ?>">
                    <?php foreach ($opcionesPorPagina as $opcion): ?>
                        <option value="<?= $opcion ?>" <?= $porPagina === $opcion ? 'selected' : '' ?>>
                            <?= $opcion === 0 ? 'Todos' : $opcion ?>
                        </option>
                    <?php endforeach; ?>
                </select>
                </label>
                <span class="small text-muted text-nowrap">por página</span>
            </div>
        <?php endif; ?>

        <?php if ($totalPaginas > 1): ?>
            <nav aria-label="Paginación">
                <ul class="pagination pagination-sm mb-0 flex-wrap">
                    <li class="page-item<?= $pagina <= 1 ? ' disabled' : '' ?>">
                        <a class="page-link" href="<?= $enlacePagina(max(1, $pagina - 1)) ?>" aria-label="Página anterior"<?= $pagina <= 1 ? ' tabindex="-1" aria-disabled="true"' : '' ?>>
                            <i class="bi bi-chevron-left" aria-hidden="true"></i><span class="d-none d-md-inline ms-1">Anterior</span>
                        </a>
                    </li>
                    <?php $anterior = 0; ?>
                    <?php foreach ($numerosVisibles as $n): ?>
                        <?php if ($n - $anterior > 1): ?>
                            <li class="page-item disabled d-none d-sm-block"><span class="page-link">…</span></li>
                        <?php endif; ?>
                        <li class="page-item<?= $n === $pagina ? ' active' : ' d-none d-sm-block' ?>">
                            <?php if ($n === $pagina): ?>
                                <span class="page-link" aria-current="page"><span class="d-sm-none">Página </span><?= $n ?><span class="d-sm-none"> de <?= $totalPaginas ?></span></span>
                            <?php else: ?>
                                <a class="page-link" href="<?= $enlacePagina($n) ?>" aria-label="Página <?= $n ?>"><?= $n ?></a>
                            <?php endif; ?>
                        </li>
                        <?php $anterior = $n; ?>
                    <?php endforeach; ?>
                    <li class="page-item<?= $pagina >= $totalPaginas ? ' disabled' : '' ?>">
                        <a class="page-link" href="<?= $enlacePagina(min($totalPaginas, $pagina + 1)) ?>" aria-label="Página siguiente"<?= $pagina >= $totalPaginas ? ' tabindex="-1" aria-disabled="true"' : '' ?>>
                            <span class="d-none d-md-inline me-1">Siguiente</span><i class="bi bi-chevron-right" aria-hidden="true"></i>
                        </a>
                    </li>
                </ul>
            </nav>
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
        url.searchParams.set(evento.target.getAttribute('data-param-por-pagina'), evento.target.value);
        url.searchParams.set(evento.target.getAttribute('data-param-pagina'), '1');
        window.location = url.toString();
    });
    </script>
<?php endif; ?>
