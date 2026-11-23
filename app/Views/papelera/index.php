<?php

use App\Core\Csrf;
use App\Core\Url;

$etiquetasTipo = [
    'usuario' => 'Usuario',
    'espacio' => 'Espacio',
    'categoria' => 'Categoría',
    'cargo' => 'Cargo',
    'factura_administrativa' => 'Factura',
    'formato_reintegro' => 'Formato de reintegro',
    'formato_plaqueteo' => 'Formato de plaqueteo',
    'cartera_envio' => 'Cartera',
];
?>
<?= ui('encabezado_pagina', ['titulo' => 'Papelera de reciclaje', 'icono' => 'papelera',
    'subtitulo' => 'Todo lo eliminado en cualquier institución, de cualquier tipo. Se conserva aquí hasta que lo restaures o hasta que la limpieza automática lo borre después del periodo de retención.', 'ayuda' => \App\Helpers\AyudaCampo::boton('papelera')]) ?>

<?php if (empty($elementos)): ?>
    <p class="text-muted">La papelera está vacía.</p>
<?php else: ?>
    <div class="mb-3 contenedor-sm">
        <?= \App\Helpers\AyudaCampo::etiqueta('buscadorPapelera', 'Buscar', 'papelera_buscar') ?>
        <input type="search" id="buscadorPapelera" data-buscar-local class="form-control form-control-sm"
               placeholder="Buscar por tipo, elemento, institución o quién lo eliminó...">
    </div>
    <p id="papeleraSinResultados" class="text-muted" hidden>Ningún elemento coincide con la búsqueda.</p>
    <?= ui('herramientas_tabla', ['suelta' => true]) ?>
    <div data-navegable class="table-responsive" id="envoltorioTablaPapelera">
        <table class="table table-sm bg-white align-middle tabla-cards" id="tablaPapelera">
            <thead>
            <tr>
                <th data-ordenar>Tipo</th>
                <th data-ordenar>Elemento</th>
                <th data-ordenar>Institución</th>
                <th data-ordenar>Eliminado por</th>
                <th data-ordenar>Eliminado el</th>
                <th></th>
            </tr>
            </thead>
            <tbody>
            <?php foreach ($elementos as $el): ?>
                <tr>
                    <td data-label="Tipo"><span class="badge badge-neutro"><?= htmlspecialchars($etiquetasTipo[$el['tipo']] ?? $el['tipo'], ENT_QUOTES) ?></span></td>
                    <td data-label="Elemento"><?= htmlspecialchars($el['titulo'], ENT_QUOTES) ?></td>
                    <td class="text-muted" data-label="Institución"><?= htmlspecialchars($el['institucion_nombre'] ?? '—', ENT_QUOTES) ?></td>
                    <td class="text-muted" data-label="Eliminado por"><?= htmlspecialchars($el['eliminado_por_nombre'] ?? '—', ENT_QUOTES) ?></td>
                    <td class="text-muted mono" data-label="Eliminado el"><?= htmlspecialchars($el['eliminado_en'], ENT_QUOTES) ?></td>
                    <td class="text-end">
                        <form method="post" action="<?= Url::to('/papelera/' . $el['tipo'] . '/' . $el['id'] . '/restaurar') ?>" class="d-inline"
                              data-confirmar="¿Restaurar este elemento? Volverá a estar disponible en el sistema.">
                            <?= Csrf::field() ?>
                            <button type="submit" class="btn btn-sm btn-primary">
                                <?= icono('restaurar', 'me-1') ?>Restaurar
                            </button>
                        </form>
                    </td>
                </tr>
            <?php endforeach; ?>
            </tbody>
        </table>
    </div>

    <script>
    (function () {
        const input = document.getElementById('buscadorPapelera');
        const filas = document.querySelectorAll('#tablaPapelera tbody tr');
        const sinResultados = document.getElementById('papeleraSinResultados');
        const envoltorio = document.getElementById('envoltorioTablaPapelera');

        input.addEventListener('sigebi:buscar', function (evento) {
            const valor = String(evento.detail).toLowerCase();
            let visibles = 0;
            filas.forEach(function (fila) {
                const coincide = fila.textContent.toLowerCase().includes(valor);
                fila.style.display = coincide ? '' : 'none';
                if (coincide) { visibles++; }
            });
            sinResultados.hidden = visibles !== 0;
            envoltorio.style.display = visibles === 0 ? 'none' : '';
        });
    })();
    </script>
<?php endif; ?>
