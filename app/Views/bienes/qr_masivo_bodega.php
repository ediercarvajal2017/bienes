<?php

use App\Core\Auth;
use App\Core\Csrf;
use App\Core\Url;

$porImprimir ??= [];
$porPegar ??= [];
$urlPestana = static fn (string $p): string => Url::to('/bienes/qr-masivo/bodega') . '?' . http_build_query(
    array_filter(['institucion' => Auth::esSuperusuario() ? $institucionId : null, 'pestana' => $p])
);
$fecha = static fn (?string $f): string => $f ? date('d/m/Y H:i', strtotime($f)) : '—';

?>
<div class="d-flex flex-wrap justify-content-between align-items-center gap-2 mb-3">
    <div>
        <h1 class="h4 mb-0">Bodega de impresión de QR</h1>
        <p class="text-muted small mb-0">
            1. Imprima los pendientes · 2. Pegue cada sticker en su bien · 3. Confirme los pegados.
        </p>
    </div>
    <a href="<?= Url::to('/bienes/qr-masivo') ?>" class="btn btn-sm btn-outline-secondary">
        <i class="bi bi-search me-1"></i>Buscar bienes para imprimir
    </a>
</div>

<?php if (!empty($mensaje)): ?><div class="alert alert-success py-2 small"><?= htmlspecialchars($mensaje, ENT_QUOTES) ?></div><?php endif; ?>
<?php if (!empty($error)): ?><div class="alert alert-danger py-2 small"><?= htmlspecialchars($error, ENT_QUOTES) ?></div><?php endif; ?>

<?php if (Auth::esSuperusuario()): ?>
    <div class="mb-3" style="max-width: 320px;">
        <label for="selectorInstitucion" class="form-label small">Institución</label>
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
<?php else: ?>
    <ul class="nav nav-tabs mb-3" role="tablist">
        <li class="nav-item">
            <a class="nav-link <?= $pestana === 'por_imprimir' ? 'active' : '' ?>" id="pestanaPorImprimir"
               href="<?= $urlPestana('por_imprimir') ?>" <?= $pestana === 'por_imprimir' ? 'aria-current="page"' : '' ?>>
                <i class="bi bi-printer me-1" aria-hidden="true"></i>Por imprimir
                <span class="badge rounded-pill text-bg-primary ms-1"><?= count($porImprimir) ?></span>
            </a>
        </li>
        <li class="nav-item">
            <a class="nav-link <?= $pestana === 'por_pegar' ? 'active' : '' ?>" id="pestanaPorPegar"
               href="<?= $urlPestana('por_pegar') ?>" <?= $pestana === 'por_pegar' ? 'aria-current="page"' : '' ?>>
                <i class="bi bi-sticky me-1" aria-hidden="true"></i>Impresos, por pegar
                <span class="badge rounded-pill text-bg-warning ms-1"><?= count($porPegar) ?></span>
            </a>
        </li>
    </ul>

    <?php if ($pestana === 'por_imprimir'): ?>
        <?php if ($porImprimir === []): ?>
            <p class="text-muted"><i class="bi bi-check2-circle me-1"></i>No hay QR pendientes por imprimir.</p>
        <?php else: ?>
            <form method="post" action="<?= Url::to('/bienes/qr-masivo') ?>" id="formQrBodega" target="_blank" class="form-bodega-qr">
                <?= Csrf::field() ?>
                <input type="hidden" name="institucion_id" value="<?= $institucionId ?>">

                <div class="table-responsive">
                    <table class="table table-sm table-hover align-middle bg-white tabla-cards">
                        <thead>
                        <tr>
                            <th style="width: 32px;"><input type="checkbox" class="form-check-input seleccionar-todos" aria-label="Seleccionar todos" checked></th>
                            <th>Código</th>
                            <th>Nombre del bien</th>
                            <th>Solicitado por</th>
                            <th>Solicitado el</th>
                        </tr>
                        </thead>
                        <tbody>
                        <?php foreach ($porImprimir as $b): ?>
                            <tr>
                                <td data-label="Seleccionar"><input type="checkbox" name="bienes[]" value="<?= $b['id'] ?>" class="form-check-input casilla-bien" checked aria-label="Seleccionar <?= htmlspecialchars($b['codigo_identificacion'], ENT_QUOTES) ?>"></td>
                                <td class="mono" data-label="Código"><?= htmlspecialchars($b['codigo_identificacion'], ENT_QUOTES) ?></td>
                                <td data-label="Nombre del bien"><?= htmlspecialchars($b['descripcion'], ENT_QUOTES) ?></td>
                                <td class="small text-muted" data-label="Solicitado por"><?= htmlspecialchars(trim((string) $b['solicitado_por_nombre']) ?: '—', ENT_QUOTES) ?></td>
                                <td class="small text-muted" data-label="Solicitado el"><?= $fecha($b['qr_solicitado_en']) ?></td>
                            </tr>
                        <?php endforeach; ?>
                        </tbody>
                    </table>
                </div>

                <div class="d-flex flex-wrap align-items-end gap-2">
                    <div style="max-width: 360px;">
                        <label for="campo-formato" class="form-label small mb-1">Formato de impresión</label>
                        <select id="campo-formato" name="formato" class="form-select form-select-sm">
                            <option value="hoja">Hoja para recortar (varios QR por página, papel normal)</option>
                            <option value="etiqueta">Etiqueta térmica 50x25mm (una por etiqueta, rollo continuo)</option>
                        </select>
                    </div>
                    <button type="submit" class="btn btn-primary boton-accion" id="botonGenerar">
                        <i class="bi bi-printer me-1"></i>Imprimir <span class="contador">0</span> QR
                    </button>
                </div>
                <p class="form-text">Al imprimir, estos bienes pasan a la pestaña <strong>"Impresos, por pegar"</strong>.</p>
            </form>
        <?php endif; ?>
    <?php else: ?>
        <?php if ($porPegar === []): ?>
            <p class="text-muted"><i class="bi bi-check2-circle me-1"></i>No hay stickers pendientes por pegar.</p>
        <?php else: ?>
            <p class="small mb-2">Pegue cada sticker en su bien y marque los que ya quedaron pegados.</p>
            <form method="post" action="<?= Url::to('/bienes/qr-masivo/bodega/confirmar') ?>" id="formQrPegados" class="form-bodega-qr">
                <?= Csrf::field() ?>
                <input type="hidden" name="institucion_id" value="<?= $institucionId ?>">

                <div class="table-responsive">
                    <table class="table table-sm table-hover align-middle bg-white tabla-cards">
                        <thead>
                        <tr>
                            <th style="width: 32px;"><input type="checkbox" class="form-check-input seleccionar-todos" aria-label="Seleccionar todos"></th>
                            <th>Código</th>
                            <th>Nombre del bien</th>
                            <th>Impreso el</th>
                        </tr>
                        </thead>
                        <tbody>
                        <?php foreach ($porPegar as $b): ?>
                            <tr>
                                <td data-label="Pegado"><input type="checkbox" name="bienes[]" value="<?= $b['id'] ?>" class="form-check-input casilla-bien" aria-label="Pegado <?= htmlspecialchars($b['codigo_identificacion'], ENT_QUOTES) ?>"></td>
                                <td class="mono" data-label="Código"><?= htmlspecialchars($b['codigo_identificacion'], ENT_QUOTES) ?></td>
                                <td data-label="Nombre del bien"><?= htmlspecialchars($b['descripcion'], ENT_QUOTES) ?></td>
                                <td class="small text-muted" data-label="Impreso el"><?= $fecha($b['qr_impreso_en']) ?></td>
                            </tr>
                        <?php endforeach; ?>
                        </tbody>
                    </table>
                </div>

                <div class="d-flex flex-wrap align-items-center gap-2">
                    <button type="submit" class="btn btn-success boton-accion" id="botonConfirmarPegados">
                        <i class="bi bi-check2-all me-1"></i>Confirmar pegados (<span class="contador">0</span>)
                    </button>
                    <span class="ms-auto d-inline-flex align-items-center gap-1 small text-muted">
                        <label for="formatoReimpresion" class="visually-hidden">Formato de reimpresión</label>
                        <select id="formatoReimpresion" name="formato" class="form-select form-select-sm" style="width: auto;">
                            <option value="hoja">Hoja</option>
                            <option value="etiqueta">Etiqueta térmica</option>
                        </select>
                        <button type="button" class="btn btn-sm btn-outline-secondary boton-accion" id="botonReimprimir"
                                title="Solo si el sticker se dañó o se perdió">
                            <i class="bi bi-arrow-repeat me-1"></i>Reimprimir
                        </button>
                    </span>
                </div>
            </form>
        <?php endif; ?>
    <?php endif; ?>

    <script>
    (function () {
        document.querySelectorAll('.form-bodega-qr').forEach(function (form) {
            const casillas = form.querySelectorAll('.casilla-bien');
            const todos = form.querySelector('.seleccionar-todos');

            function actualizar() {
                const n = form.querySelectorAll('.casilla-bien:checked').length;
                form.querySelectorAll('.contador').forEach(function (c) { c.textContent = n; });
                form.querySelectorAll('.boton-accion').forEach(function (b) { b.disabled = n === 0; });
                todos.checked = n === casillas.length && casillas.length > 0;
            }

            todos.addEventListener('change', function () {
                casillas.forEach(function (c) { c.checked = todos.checked; });
                actualizar();
            });
            casillas.forEach(function (c) { c.addEventListener('change', actualizar); });
            actualizar();
        });

        // Imprimir: la hoja se abre en otra pestaña y esta se recarga, para que los bienes
        // impresos pasen a "Impresos, por pegar" y no se vuelvan a imprimir por descuido.
        const formImprimir = document.getElementById('formQrBodega');
        if (formImprimir) {
            formImprimir.addEventListener('submit', function () {
                window.setTimeout(function () { window.location.reload(); }, 2500);
            });
        }

        // Confirmar pegados (en esta pestaña) o reimprimir (en otra), con el mismo formulario.
        const formPegados = document.getElementById('formQrPegados');
        if (formPegados) {
            const accionConfirmar = formPegados.getAttribute('action');
            document.getElementById('botonConfirmarPegados').addEventListener('click', function () {
                formPegados.setAttribute('action', accionConfirmar);
                formPegados.removeAttribute('target');
                formPegados.setAttribute('data-confirmar', '¿Confirma que los stickers seleccionados ya quedaron pegados en sus bienes? Saldrán de la bodega.');
            });
            document.getElementById('botonReimprimir').addEventListener('click', function () {
                if (!window.confirm('Estos QR ya se imprimieron. ¿Desea reimprimirlos? Úselo solo si el sticker se dañó o se perdió.')) { return; }
                formPegados.setAttribute('action', <?= json_encode(Url::to('/bienes/qr-masivo')) ?>);
                formPegados.setAttribute('target', '_blank');
                formPegados.removeAttribute('data-confirmar');
                formPegados.requestSubmit();
                // Se restaura para que "Confirmar pegados" siga funcionando en esta pestaña.
                window.setTimeout(function () {
                    formPegados.setAttribute('action', accionConfirmar);
                    formPegados.removeAttribute('target');
                }, 0);
            });
        }
    })();
    </script>
<?php endif; ?>
