<?php

use App\Core\Auth;
use App\Core\Csrf;
use App\Core\Url;

?>
<div class="d-flex flex-wrap justify-content-between align-items-center gap-2 mb-3">
    <div>
        <h1 class="h4 mb-0">Bodega de impresión de QR</h1>
        <p class="text-muted small mb-0">
            Bienes que alguien marcó con "Imprimir QR" al crearlos o editarlos — imprímelos aquí de una sola vez.
        </p>
    </div>
    <a href="<?= Url::to('/bienes/qr-masivo') ?>" class="btn btn-sm btn-outline-secondary">
        <i class="bi bi-search me-1"></i>Buscar bienes para imprimir
    </a>
</div>

<?php if (!empty($error)): ?><div class="alert alert-danger py-2 small"><?= htmlspecialchars($error, ENT_QUOTES) ?></div><?php endif; ?>

<?php if (Auth::esSuperusuario()): ?>
    <div class="mb-3" style="max-width: 320px;">
        <label class="form-label small">Institución</label>
        <select id="selectorInstitucion" class="form-select form-select-sm selector-buscable">
            <option value="">-- Selecciona una institución --</option>
            <?php foreach ($instituciones as $i): ?>
                <option value="<?= $i['id'] ?>" <?= $institucionId === (int) $i['id'] ? 'selected' : '' ?>>
                    <?= htmlspecialchars($i['nombre'], ENT_QUOTES) ?>
                </option>
            <?php endforeach; ?>
        </select>
    </div>
    <script>
    document.getElementById('selectorInstitucion').addEventListener('change', function () {
        window.location = <?= json_encode(Url::to('/bienes/qr-masivo/bodega')) ?> + (this.value ? '?institucion=' + encodeURIComponent(this.value) : '');
    });
    </script>
<?php endif; ?>

<?php if ($institucionId === null): ?>
    <p class="text-muted">Selecciona una institución para continuar.</p>
<?php elseif (empty($bienes)): ?>
    <p class="text-muted">No hay ningún bien pendiente en la bodega — nadie ha marcado "Imprimir QR" todavía.</p>
<?php else: ?>
    <form method="post" action="<?= Url::to('/bienes/qr-masivo') ?>" id="formQrBodega" target="_blank">
        <?= Csrf::field() ?>
        <input type="hidden" name="institucion_id" value="<?= $institucionId ?>">

        <div class="table-responsive">
            <table class="table table-sm table-hover align-middle bg-white tabla-cards">
                <thead>
                <tr>
                    <th style="width: 32px;"><input type="checkbox" id="seleccionarTodos" class="form-check-input"></th>
                    <th>Solicitado por</th>
                    <th>Código</th>
                    <th>Nombre del bien</th>
                    <th class="text-center">Estado</th>
                </tr>
                </thead>
                <tbody>
                <?php foreach ($bienes as $b): ?>
                    <?php $yaImpreso = !empty($b['qr_impreso_en']); ?>
                    <tr>
                        <td data-label="Seleccionar"><input type="checkbox" name="bienes[]" value="<?= $b['id'] ?>" class="form-check-input casilla-bien"></td>
                        <td class="small text-muted" data-label="Solicitado por"><?= htmlspecialchars(trim((string) $b['solicitado_por_nombre']) ?: '—', ENT_QUOTES) ?></td>
                        <td class="mono" data-label="Código"><?= htmlspecialchars($b['codigo_identificacion'], ENT_QUOTES) ?></td>
                        <td data-label="Nombre del bien"><?= htmlspecialchars($b['descripcion'], ENT_QUOTES) ?></td>
                        <td class="text-center" data-label="Estado">
                            <?php if ($yaImpreso): ?>
                                <i class="bi bi-printer-fill text-muted" style="opacity:.4;" title="Ya impreso — puedes volver a imprimirlo si lo necesitas"></i>
                            <?php else: ?>
                                <i class="bi bi-printer text-primary" title="Pendiente de imprimir"></i>
                            <?php endif; ?>
                        </td>
                    </tr>
                <?php endforeach; ?>
                </tbody>
            </table>
        </div>

        <div class="mb-3" style="max-width: 360px;">
            <label class="form-label small mb-1">Formato de impresión</label>
            <select name="formato" class="form-select form-select-sm">
                <option value="hoja">Hoja para recortar (varios QR por página, papel normal)</option>
                <option value="etiqueta">Etiqueta térmica 50x25mm (una por etiqueta, rollo continuo)</option>
            </select>
        </div>

        <button type="submit" class="btn btn-primary" id="botonGenerar" disabled>
            <i class="bi bi-qr-code me-1"></i>Generar QR (<span id="contadorSeleccionados">0</span>)
        </button>
    </form>

    <script>
    (function () {
        const casillas = document.querySelectorAll('.casilla-bien');
        const todos = document.getElementById('seleccionarTodos');
        const boton = document.getElementById('botonGenerar');
        const contador = document.getElementById('contadorSeleccionados');

        function actualizar() {
            const seleccionados = document.querySelectorAll('.casilla-bien:checked').length;
            contador.textContent = seleccionados;
            boton.disabled = seleccionados === 0;
            todos.checked = seleccionados === casillas.length && casillas.length > 0;
        }

        todos.addEventListener('change', function () {
            casillas.forEach(function (c) { c.checked = todos.checked; });
            actualizar();
        });
        casillas.forEach(function (c) { c.addEventListener('change', actualizar); });
    })();
    </script>
<?php endif; ?>
