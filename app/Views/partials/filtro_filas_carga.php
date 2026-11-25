<?php
/**
 * Filtro por estado y paginación del resultado de una carga masiva (bienes, espacios o
 * usuarios), con App\Helpers\FilasCarga.
 *
 * Variables:
 *  - $paginaFilas: lo que devuelve FilasCarga::pagina().
 *  - $urlBase: dirección de la pantalla, ya resuelta con Url::to().
 *  - $abajo (bool, opcional): debajo de la tabla va solo la paginación, sin el filtro.
 */

use App\Core\View;
use App\Helpers\FilasCarga;

$cifra = static fn (int $n): string => number_format($n, 0, ',', '.');
$conPorPagina = $paginaFilas['porPagina'] !== FilasCarga::POR_PAGINA_DEFECTO ? ['porPagina' => $paginaFilas['porPagina']] : [];
$enlaceEstado = static function (string $estado) use ($urlBase, $conPorPagina): string {
    $parametros = ($estado !== '' ? ['estado' => $estado] : []) + $conPorPagina;

    return $urlBase . ($parametros !== [] ? '?' . http_build_query($parametros) : '');
};
$urlFilas = $urlBase . ($paginaFilas['estado'] !== '' ? '?' . http_build_query(['estado' => $paginaFilas['estado']]) : '');
$opciones = ['' => ['Todas', array_sum($paginaFilas['conteo'])]];
foreach (FilasCarga::ESTADOS as $clave => $texto) {
    if ($paginaFilas['conteo'][$clave] > 0) {
        $opciones[$clave] = [$texto, $paginaFilas['conteo'][$clave]];
    }
}
$abajo ??= false;
?>
<?php if (!$abajo): ?>
<nav class="d-flex flex-wrap gap-2 mb-2" aria-label="Mostrar filas de la carga">
    <?php foreach ($opciones as $clave => [$texto, $cantidad]): ?>
        <?php $activa = $paginaFilas['estado'] === (string) $clave; ?>
        <?= ui('boton', [
            'texto' => $texto . ' (' . $cifra($cantidad) . ')',
            'url' => $enlaceEstado((string) $clave),
            'tamano' => 'sm',
            'clases' => $activa ? 'active' : '',
            'atributos' => ['aria-current' => $activa ? 'true' : null],
        ]) ?>
    <?php endforeach; ?>
</nav>
<?php endif; ?>

<?php View::render('partials/paginacion', ['densidad' => !$abajo,
    'pagina' => $paginaFilas['pagina'], 'porPagina' => $paginaFilas['porPagina'], 'total' => $paginaFilas['total'],
    'totalPaginas' => $paginaFilas['totalPaginas'], 'opcionesPorPagina' => $paginaFilas['opcionesPorPagina'],
    'urlBase' => $urlFilas,
]); ?>
