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
<div class="mb-3">
    <h1 class="h4 mb-0"><i class="bi bi-trash3 me-1"></i>Papelera de reciclaje</h1>
    <p class="text-muted small mb-0">
        Todo lo eliminado en cualquier institución, de cualquier tipo. Se conserva aquí hasta que lo restaures o
        hasta que la limpieza automática lo borre después del periodo de retención.
    </p>
</div>

<?php if (!empty($mensaje)): ?><div class="alert alert-success py-2 small"><?= htmlspecialchars($mensaje, ENT_QUOTES) ?></div><?php endif; ?>
<?php if (!empty($error)): ?><div class="alert alert-danger py-2 small"><?= htmlspecialchars($error, ENT_QUOTES) ?></div><?php endif; ?>

<?php if (empty($elementos)): ?>
    <p class="text-muted">La papelera está vacía.</p>
<?php else: ?>
    <div class="mb-3" style="max-width: 420px;">
        <input type="search" id="buscadorPapelera" class="form-control form-control-sm"
               placeholder="Buscar por tipo, elemento, institución o quién lo eliminó...">
    </div>
    <p id="papeleraSinResultados" class="text-muted" style="display:none;">Ningún elemento coincide con la búsqueda.</p>
    <div class="table-responsive" id="envoltorioTablaPapelera">
        <table class="table table-sm bg-white align-middle tabla-cards" id="tablaPapelera">
            <thead>
            <tr>
                <th>Tipo</th>
                <th>Elemento</th>
                <th>Institución</th>
                <th>Eliminado por</th>
                <th>Eliminado el</th>
                <th></th>
            </tr>
            </thead>
            <tbody>
            <?php foreach ($elementos as $el): ?>
                <tr>
                    <td data-label="Tipo"><span class="badge text-bg-secondary"><?= htmlspecialchars($etiquetasTipo[$el['tipo']] ?? $el['tipo'], ENT_QUOTES) ?></span></td>
                    <td data-label="Elemento"><?= htmlspecialchars($el['titulo'], ENT_QUOTES) ?></td>
                    <td class="text-muted" data-label="Institución"><?= htmlspecialchars($el['institucion_nombre'] ?? '—', ENT_QUOTES) ?></td>
                    <td class="text-muted" data-label="Eliminado por"><?= htmlspecialchars($el['eliminado_por_nombre'] ?? '—', ENT_QUOTES) ?></td>
                    <td class="text-muted mono" data-label="Eliminado el"><?= htmlspecialchars($el['eliminado_en'], ENT_QUOTES) ?></td>
                    <td class="text-end">
                        <form method="post" action="<?= Url::to('/papelera/' . $el['tipo'] . '/' . $el['id'] . '/restaurar') ?>" class="d-inline">
                            <?= Csrf::field() ?>
                            <button type="submit" class="btn btn-sm btn-primary">
                                <i class="bi bi-arrow-counterclockwise me-1"></i>Restaurar
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

        input.addEventListener('input', function () {
            const valor = input.value.trim().toLowerCase();
            let visibles = 0;
            filas.forEach(function (fila) {
                const coincide = fila.textContent.toLowerCase().includes(valor);
                fila.style.display = coincide ? '' : 'none';
                if (coincide) { visibles++; }
            });
            sinResultados.style.display = visibles === 0 ? '' : 'none';
            envoltorio.style.display = visibles === 0 ? 'none' : '';
        });
    })();
    </script>
<?php endif; ?>
